// SMARTSIS — Smart Attendance kiosk (PRD F-09/F-10).
//
// Continuous loop: detect a face → match it against every registered student
// template (1:N, face-api FaceMatcher) → ask for a blink (liveness, PRD 8.5
// Blink Detection via Eye Aspect Ratio on the 68-point landmarks) → hand the
// matched student id to the Livewire page, which records check-in/check-out.
//
// The staffed kiosk (pages::attendance.absensi.scan) and the siswa's own
// Absensi page (pages::attendance.absensi.index) call
// window.SmartsisAttendance.start($el, $wire, { templatesUrl }) from Alpine
// x-init; the endpoint decides which templates that user is allowed to match
// against (everyone for the kiosk, themselves for a siswa).

import * as faceapi from '@vladmandic/face-api';

const MODEL_URL = '/models/face-api';

// face-api convention: descriptors of the same person sit below ~0.5–0.6.
const MATCH_THRESHOLD = 0.5;

// A face must match the same student on this many consecutive frames before
// the blink challenge starts (guards against one-frame mismatches).
const STABLE_FRAMES_NEEDED = 2;

// Eye Aspect Ratio bounds: closed below EAR_CLOSED, open again above EAR_OPEN.
const EAR_CLOSED = 0.25;
const EAR_OPEN = 0.3;

// How long the student has to blink before the kiosk resumes scanning.
const BLINK_TIMEOUT_MS = 8000;

// Pause after a successful/failed record before scanning the next student.
const COOLDOWN_MS = 4000;

const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions({
    inputSize: 320,
    scoreThreshold: 0.5,
});

let modelsReady = null;
let mediaStream = null;
let loopId = null;
let stopped = false;

/**
 * Load the three nets, reporting each one as it lands.
 *
 * They total ~6.8 MB, almost all of it faceRecognitionNet, so this is the slow
 * part of starting a scanner. It is kicked off alongside getUserMedia rather
 * than before it — see start() — and memoised so a second visit in the same
 * page session is instant.
 *
 * @param {(done: number, total: number) => void} [onProgress]
 */
function loadModels(onProgress) {
    if (modelsReady) {
        return modelsReady;
    }

    const nets = [
        faceapi.nets.tinyFaceDetector,
        faceapi.nets.faceLandmark68Net,
        faceapi.nets.faceRecognitionNet,
    ];

    let done = 0;

    modelsReady = Promise.all(
        nets.map((net) =>
            net.loadFromUri(MODEL_URL).then(() => {
                done++;
                onProgress?.(done, nets.length);
            }),
        ),
    ).catch((error) => {
        // Let the next attempt retry instead of caching the failure forever.
        modelsReady = null;

        throw error;
    });

    return modelsReady;
}

/**
 * The registered face templates, fetched once from the cached endpoint.
 *
 * @param {string} url
 * @returns {Promise<Array<{ id: number, name: string, descriptors: number[][] }>>}
 */
async function fetchTemplates(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Face templates request failed (${response.status})`);
    }

    return response.json();
}

function stopKiosk() {
    stopped = true;

    if (loopId) {
        clearTimeout(loopId);
        loopId = null;
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
}

function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

/**
 * Eye Aspect Ratio for one eye (6 landmark points):
 * (‖p2−p6‖ + ‖p3−p5‖) / (2 · ‖p1−p4‖). Drops sharply when the eye closes.
 */
function eyeAspectRatio(eye) {
    return (distance(eye[1], eye[5]) + distance(eye[2], eye[4])) / (2 * distance(eye[0], eye[3]));
}

function averageEar(landmarks) {
    return (eyeAspectRatio(landmarks.getLeftEye()) + eyeAspectRatio(landmarks.getRightEye())) / 2;
}

window.SmartsisAttendance = {
    /**
     * Boot the kiosk inside the given container.
     *
     * Expected elements inside `container`:
     *   video[data-face-video], [data-face-status].
     *
     * @param {HTMLElement} container
     * @param {{ record: (studentId: number) => Promise<void> }} wire  Livewire $wire proxy.
     * @param {{ templatesUrl: string }} options
     */
    async start(container, wire, options) {
        const video = container.querySelector('[data-face-video]');
        const statusEl = container.querySelector('[data-face-status]');

        const setStatus = (text, tone = 'info') => {
            statusEl.textContent = text;
            statusEl.classList.toggle('text-red-600', tone === 'error');
            statusEl.classList.toggle('text-green-600', tone === 'success');
            statusEl.classList.toggle('text-amber-600', tone === 'warning');
            statusEl.classList.toggle('text-gray-500', tone === 'info');
        };

        // This session's own camera stream, so the DOM-removal guard in tick()
        // never stops a newer session's stream by accident.
        let stream = null;

        // Kick the two slow downloads off first, but do not wait on them: the
        // camera preview is what the user is waiting to see, and it is ready in
        // a fraction of the ~6.8 MB the models take.
        const modelsPromise = loadModels((done, total) => {
            if (stream !== null) {
                setStatus(`Memuat model pengenalan wajah… (${done}/${total})`);
            }
        });
        const templatesPromise = fetchTemplates(options.templatesUrl);

        // Nothing below should surface as an unhandled rejection while the
        // camera prompt is still open.
        modelsPromise.catch(() => {});
        templatesPromise.catch(() => {});

        try {
            setStatus('Menyalakan kamera…');
            stopped = false;
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            mediaStream = stream;
            video.srcObject = stream;
            await video.play();
        } catch (error) {
            console.error('[SmartsisAttendance]', error);
            setStatus(
                error?.name === 'NotAllowedError'
                    ? 'Akses kamera ditolak. Izinkan kamera pada browser lalu muat ulang halaman.'
                    : 'Kamera tidak tersedia. Pastikan perangkat memiliki kamera lalu muat ulang halaman.',
                'error',
            );

            return;
        }

        // Preview is live; now wait for what the matcher needs.
        let students = [];

        try {
            setStatus('Memuat model pengenalan wajah…');
            [, students] = await Promise.all([modelsPromise, templatesPromise]);
        } catch (error) {
            console.error('[SmartsisAttendance]', error);
            setStatus('Gagal memuat data pengenalan wajah. Muat ulang halaman untuk mencoba lagi.', 'error');

            return;
        }

        if (stopped || !container.isConnected) {
            return;
        }

        if (students.length === 0) {
            setStatus('Belum ada siswa dengan wajah terdaftar. Daftarkan wajah melalui aktivasi akun siswa.', 'error');

            return;
        }

        const matcher = new faceapi.FaceMatcher(
            students.map(
                (student) =>
                    new faceapi.LabeledFaceDescriptors(
                        String(student.id),
                        student.descriptors.map((sample) => new Float32Array(sample)),
                    ),
            ),
            MATCH_THRESHOLD,
        );

        const names = new Map(students.map((student) => [String(student.id), student.name]));

        // --- Scan state machine: scanning → blink challenge → record → cooldown.
        let matchedId = null;
        let stableFrames = 0;
        let blinkDeadline = null;
        let eyesClosed = false;

        const resetScan = () => {
            matchedId = null;
            stableFrames = 0;
            blinkDeadline = null;
            eyesClosed = false;
        };

        const tick = async () => {
            if (stopped) {
                return;
            }

            // Livewire removes the scanner block once the siswa's day is
            // fully recorded — release the camera instead of looping on.
            if (!container.isConnected) {
                stream?.getTracks().forEach((track) => track.stop());

                if (mediaStream === stream) {
                    mediaStream = null;
                }

                return;
            }

            try {
                const result = await faceapi
                    .detectSingleFace(video, DETECTOR_OPTIONS)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!result) {
                    resetScan();
                    setStatus('Posisikan wajah Anda di tengah kamera.');
                } else if (blinkDeadline === null) {
                    // Phase 1 — identify the face.
                    const match = matcher.findBestMatch(result.descriptor);

                    if (match.label === 'unknown') {
                        resetScan();
                        setStatus('Wajah tidak dikenali. Pastikan wajah Anda sudah terdaftar.', 'warning');
                    } else if (match.label === matchedId) {
                        stableFrames++;

                        if (stableFrames >= STABLE_FRAMES_NEEDED) {
                            blinkDeadline = Date.now() + BLINK_TIMEOUT_MS;
                            eyesClosed = false;
                            setStatus(`${names.get(matchedId)} terdeteksi. Kedipkan mata Anda…`, 'success');
                        }
                    } else {
                        matchedId = match.label;
                        stableFrames = 1;
                        setStatus(`Memverifikasi ${names.get(match.label)}…`);
                    }
                } else {
                    // Phase 2 — liveness: wait for a close→open transition.
                    const ear = averageEar(result.landmarks);

                    if (ear < EAR_CLOSED) {
                        eyesClosed = true;
                    }

                    if (eyesClosed && ear > EAR_OPEN) {
                        const studentId = Number(matchedId);
                        const name = names.get(matchedId);
                        resetScan();

                        setStatus(`Mencatat absensi ${name}…`, 'success');
                        await wire.record(studentId);
                        setStatus('Tercatat. Silakan siswa berikutnya.', 'success');

                        loopId = setTimeout(tick, COOLDOWN_MS);

                        return;
                    }

                    if (Date.now() > blinkDeadline) {
                        resetScan();
                        setStatus('Kedipan tidak terdeteksi. Coba posisikan wajah kembali.', 'warning');
                    }
                }
            } catch (error) {
                console.error('[SmartsisAttendance]', error);
                resetScan();
                setStatus('Terjadi kesalahan saat memproses wajah. Mencoba lagi…', 'error');
            }

            loopId = setTimeout(tick, 250);
        };

        tick();
    },

    stop: stopKiosk,
};

// Release the camera when navigating away (wire:navigate or full unload).
window.addEventListener('beforeunload', stopKiosk);
document.addEventListener('livewire:navigating', stopKiosk);
