// SMARTSIS — Smart Attendance kiosk (PRD F-09/F-10).
//
// Continuous loop: detect a face → match it against every registered student
// template (1:N, face-api FaceMatcher) → confirm the face is a live person, not
// a photo (PRD 8.5 liveness) → hand the matched student id to the Livewire
// page, which records check-in/check-out.
//
// The student does nothing but stand in front of the camera: no class to pick
// beforehand and no blink to perform, because at 30+ students per class every
// extra instruction turns into a queue. Liveness is therefore *passive* — see
// the confirm phase below.
//
// Only the kiosk screens (pages::attendance.absensi.scan for staff and the
// full-screen pages::attendance.absensi.kiosk for classroom tablets) use this,
// because they have to work out *who* is standing in front of the camera
// (1:N). Both may narrow the candidates to one class. A siswa on their own Absensi page is already identified by
// their session, so that page uses the dependency-free attendance-camera.js
// instead and starts in a fraction of the time.

import * as faceapi from '@vladmandic/face-api';

const MODEL_URL = '/models/face-api';

// face-api convention: descriptors of the same person sit below ~0.5–0.6.
const MATCH_THRESHOLD = 0.5;

// A face must match the same student on this many consecutive frames before
// the confirm phase starts (guards against one-frame mismatches).
const STABLE_FRAMES_NEEDED = 2;

// --- Passive liveness -------------------------------------------------------
//
// A live face is never perfectly still: eyelids, brows and mouth keep shifting
// by a fraction of a face-width even when someone "holds still". A photo — on
// paper or on a phone screen — moves *rigidly*, so once translation and scale
// are normalised away its landmarks freeze. nonRigidMotion() measures exactly
// that residual, in face-widths per frame, and a blink (a wide swing in eye
// aspect ratio) is accepted as proof on its own.
const LIVENESS_SAMPLES_NEEDED = 6;
const LIVENESS_MIN_MOTION = 0.0035;
const LIVENESS_EAR_RANGE = 0.06;

// How long a face may stay in the confirm phase before the kiosk gives up and
// resumes scanning. Generous on purpose: a real person clears the check in
// well under a second, so this only ever expires on a photo.
const LIVENESS_TIMEOUT_MS = 6000;

// Landmark-only passes are ~10× cheaper than a pass with the descriptor, so
// the confirm phase can sample fast enough to see eyelid movement.
const CONFIRM_TICK_MS = 60;
const SCAN_TICK_MS = 250;

// Detection drops a frame now and then (motion blur, eyes closing). Losing the
// face for a moment must not throw away a confirm phase that is almost done.
const MISSED_FRAMES_TOLERANCE = 4;

// Pause after a successful/failed record before scanning the next student.
const COOLDOWN_MS = 4000;

// Append ?facedebug to the kiosk URL to read the measured liveness numbers in
// the status line while tuning the thresholds above on a real device.
const DEBUG = typeof window !== 'undefined' && window.location.search.includes('facedebug');

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
 * @returns {Promise<Array<{ id: number, name: string, classroom: string|null, descriptors: number[][] }>>}
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

// Evidence photos are downscaled to this width before upload.
const PHOTO_WIDTH = 640;
const PHOTO_QUALITY = 0.72;

/**
 * Grab the current video frame as a downscaled JPEG data URL, un-mirrored so
 * the stored evidence photo reads the way a human expects.
 *
 * @param {HTMLVideoElement} video
 * @returns {string|null}
 */
function grabFrame(video) {
    const width = video.videoWidth;
    const height = video.videoHeight;

    if (!width || !height) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = PHOTO_WIDTH;
    canvas.height = Math.round((height / width) * PHOTO_WIDTH);

    const context = canvas.getContext('2d');
    context.translate(canvas.width, 0);
    context.scale(-1, 1);
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', PHOTO_QUALITY);
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

/**
 * The 68 landmarks with translation and scale taken out: centred on their own
 * centroid and measured in face-widths. Two such sets can be compared directly
 * however far the head has drifted across the frame or towards the camera.
 *
 * @param {faceapi.FaceLandmarks68} landmarks
 * @param {{ width: number }} box
 * @returns {Array<{x: number, y: number}>}
 */
function normaliseLandmarks(landmarks, box) {
    const points = landmarks.positions;
    const scale = box.width || 1;

    let centreX = 0;
    let centreY = 0;

    for (const point of points) {
        centreX += point.x;
        centreY += point.y;
    }

    centreX /= points.length;
    centreY /= points.length;

    return points.map((point) => ({
        x: (point.x - centreX) / scale,
        y: (point.y - centreY) / scale,
    }));
}

/**
 * Mean per-landmark movement between two normalised sets — i.e. the part of the
 * motion a rigid object (a photo being held up) cannot produce.
 *
 * @param {Array<{x: number, y: number}>} previous
 * @param {Array<{x: number, y: number}>} current
 */
function nonRigidMotion(previous, current) {
    let total = 0;

    for (let index = 0; index < current.length; index++) {
        total += distance(previous[index], current[index]);
    }

    return total / current.length;
}

window.SmartsisAttendance = {
    /**
     * Boot the kiosk inside the given container.
     *
     * Expected elements inside `container`:
     *   video[data-face-video], [data-face-status], [data-face-identity] (optional).
     *
     * @param {HTMLElement} container
     * @param {{ record: (studentId: number, capture: object) => Promise<void> }} wire  Livewire $wire proxy.
     * @param {{ templatesUrl: string, scoped?: boolean }} options  `scoped` when the templates cover one class.
     */
    async start(container, wire, options) {
        const video = container.querySelector('[data-face-video]');
        const statusEl = container.querySelector('[data-face-status]');
        const identityEl = container.querySelector('[data-face-identity]');
        const identityNameEl = container.querySelector('[data-face-identity-name]');
        const identityClassEl = container.querySelector('[data-face-identity-class]');

        const setStatus = (text, tone = 'info') => {
            statusEl.textContent = text;
            statusEl.classList.toggle('text-red-600', tone === 'error');
            statusEl.classList.toggle('text-green-600', tone === 'success');
            statusEl.classList.toggle('text-amber-600', tone === 'warning');
            statusEl.classList.toggle('text-gray-500', tone === 'info');
        };

        /**
         * The big name + class plate over the camera: the only feedback a
         * student in the queue actually reads.
         */
        const showIdentity = (student) => {
            if (!identityEl) {
                return;
            }

            if (student === null) {
                identityEl.classList.add('hidden');

                return;
            }

            identityNameEl.textContent = student.name;
            identityClassEl.textContent = student.classroom ?? '';
            identityEl.classList.remove('hidden');
        };

        // This session's own camera stream, so the DOM-removal guard in tick()
        // never stops a newer session's stream by accident.
        let stream = null;

        // Picking another class swaps the scanner element and boots a new
        // session; the old one must hand its camera back wherever it notices.
        const release = () => {
            stream?.getTracks().forEach((track) => track.stop());

            if (mediaStream === stream) {
                mediaStream = null;
            }
        };

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
            release();
            setStatus('Gagal memuat data pengenalan wajah. Muat ulang halaman untuk mencoba lagi.', 'error');

            return;
        }

        if (stopped || !container.isConnected) {
            release();

            return;
        }

        if (students.length === 0) {
            // Nothing to match, and no loop will run to notice a class switch.
            release();
            setStatus(
                options.scoped
                    ? 'Belum ada siswa di kelas ini dengan wajah terdaftar. Daftarkan wajah melalui Data Siswa.'
                    : 'Belum ada siswa dengan wajah terdaftar. Daftarkan wajah melalui Data Siswa.',
                'error',
            );

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

        const byId = new Map(students.map((student) => [String(student.id), student]));

        // --- Scan state machine: scanning → confirm (liveness) → record → cooldown.
        let matchedId = null;
        let stableFrames = 0;
        let missedFrames = 0;
        let confirming = false;
        let confirmDeadline = 0;
        let livenessSamples = 0;
        let motionTotal = 0;
        let earMin = Infinity;
        let earMax = -Infinity;
        let previousShape = null;

        const resetScan = () => {
            matchedId = null;
            stableFrames = 0;
            missedFrames = 0;
            confirming = false;
            confirmDeadline = 0;
            livenessSamples = 0;
            motionTotal = 0;
            earMin = Infinity;
            earMax = -Infinity;
            previousShape = null;
            showIdentity(null);
        };

        /** Enough evidence that the thing in front of the camera is a person? */
        const isLive = () => {
            if (livenessSamples < LIVENESS_SAMPLES_NEEDED) {
                return false;
            }

            return (
                motionTotal / livenessSamples >= LIVENESS_MIN_MOTION ||
                earMax - earMin >= LIVENESS_EAR_RANGE
            );
        };

        const tick = async () => {
            if (stopped) {
                return;
            }

            // Livewire swapped the scanner out (another class was picked, or
            // the page moved on) — release the camera instead of looping on.
            if (!container.isConnected) {
                release();

                return;
            }

            let nextTick = SCAN_TICK_MS;

            try {
                // Phase 1 needs the descriptor to identify the face; phase 2
                // only reads landmarks, which is what lets it sample fast
                // enough to see an eyelid move.
                const result = confirming
                    ? await faceapi.detectSingleFace(video, DETECTOR_OPTIONS).withFaceLandmarks()
                    : await faceapi
                          .detectSingleFace(video, DETECTOR_OPTIONS)
                          .withFaceLandmarks()
                          .withFaceDescriptor();

                if (!result) {
                    missedFrames++;

                    // Mid-confirm, a dropped frame is normal — keep the match.
                    if (confirming && missedFrames <= MISSED_FRAMES_TOLERANCE) {
                        previousShape = null;
                        nextTick = CONFIRM_TICK_MS;
                    } else {
                        resetScan();
                        setStatus('Posisikan wajah Anda di tengah kamera.');
                    }
                } else if (!confirming) {
                    // Phase 1 — identify the face.
                    missedFrames = 0;
                    const match = matcher.findBestMatch(result.descriptor);

                    if (match.label === 'unknown') {
                        resetScan();
                        setStatus('Wajah tidak dikenali. Pastikan wajah Anda sudah terdaftar.', 'warning');
                    } else if (match.label === matchedId) {
                        stableFrames++;

                        if (stableFrames >= STABLE_FRAMES_NEEDED) {
                            confirming = true;
                            confirmDeadline = Date.now() + LIVENESS_TIMEOUT_MS;
                            previousShape = null;
                            nextTick = CONFIRM_TICK_MS;
                            showIdentity(byId.get(matchedId));
                            setStatus(`${byId.get(matchedId).name} terdeteksi. Tetap lihat kamera…`, 'success');
                        }
                    } else {
                        matchedId = match.label;
                        stableFrames = 1;
                        showIdentity(byId.get(match.label));
                        setStatus(`Memverifikasi ${byId.get(match.label).name}…`);
                    }
                } else {
                    // Phase 2 — passive liveness on the identified face.
                    missedFrames = 0;
                    nextTick = CONFIRM_TICK_MS;

                    const shape = normaliseLandmarks(result.landmarks, result.detection.box);
                    const ear = averageEar(result.landmarks);

                    earMin = Math.min(earMin, ear);
                    earMax = Math.max(earMax, ear);

                    if (previousShape) {
                        motionTotal += nonRigidMotion(previousShape, shape);
                        livenessSamples++;
                    }

                    previousShape = shape;

                    if (DEBUG && livenessSamples > 0) {
                        setStatus(
                            `motion ${(motionTotal / livenessSamples).toFixed(5)} · EAR Δ${(earMax - earMin).toFixed(3)} · n=${livenessSamples}`,
                        );
                    }

                    if (isLive()) {
                        const student = byId.get(matchedId);
                        const photo = grabFrame(video);

                        setStatus(`Mencatat absensi ${student.name}…`, 'success');

                        // One last full pass: nobody may step in front of the
                        // camera between identification and the record itself.
                        const verified = await faceapi
                            .detectSingleFace(video, DETECTOR_OPTIONS)
                            .withFaceLandmarks()
                            .withFaceDescriptor();

                        const stillMatches =
                            verified && matcher.findBestMatch(verified.descriptor).label === matchedId;

                        resetScan();

                        if (!stillMatches) {
                            setStatus('Wajah berubah saat konfirmasi. Silakan coba lagi.', 'warning');
                        } else {
                            await wire.record(Number(student.id), { photo });
                            setStatus('Tercatat. Silakan siswa berikutnya.', 'success');

                            loopId = setTimeout(tick, COOLDOWN_MS);

                            return;
                        }
                    } else if (Date.now() > confirmDeadline) {
                        resetScan();
                        setStatus('Wajah tidak bergerak sama sekali. Absensi hanya menerima orang, bukan foto.', 'warning');
                    }
                }
            } catch (error) {
                console.error('[SmartsisAttendance]', error);
                resetScan();
                setStatus('Terjadi kesalahan saat memproses wajah. Mencoba lagi…', 'error');
            }

            loopId = setTimeout(tick, nextTick);
        };

        tick();
    },

    stop: stopKiosk,
};

// Release the camera when navigating away (wire:navigate or full unload).
window.addEventListener('beforeunload', stopKiosk);
document.addEventListener('livewire:navigating', stopKiosk);
