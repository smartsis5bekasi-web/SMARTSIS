// SMARTSIS — Face registration for the first-login student onboarding.
//
// Uses @vladmandic/face-api (maintained fork of face-api.js, per PROPOSAL 8.5):
// TinyFaceDetector + 68-point landmarks + FaceRecognitionNet 128-d descriptors.
// Models are self-hosted in /public/models/face-api so the app works on the
// school LAN without internet access.
//
// The onboarding page (pages::onboarding.index), the admin "Daftarkan Wajah"
// page (pages::master-data.students.face) and the kiosk registration mode
// (pages::attendance.face-registration) call window.SmartsisFace.start() from
// an Alpine x-init, and receive the captured descriptors back through
// $wire.storeFaceDescriptors(). When the server refuses a capture (malformed,
// or a face already enrolled on another student) it dispatches
// `face-capture-rejected` and the camera comes back for another try.

import * as faceapi from '@vladmandic/face-api';

const MODEL_URL = '/models/face-api';

// One sample is enough. The template feeds the classroom kiosk's 1:N lookup,
// which the kiosk narrows to a single class, so a three-sample ritual is
// friction the student pays for little accuracy.
const SAMPLES_NEEDED = 1;

// The one sample is the average of a short burst of frames. A single frame
// carries its own blur and sensor noise, which pulls the template towards the
// "average face" and makes classmates look alike to the duplicate check and
// the kiosk; averaging cancels most of that noise out.
const FRAMES_PER_SAMPLE = 3;
const FRAME_INTERVAL_MS = 120;

// A frame below either bar is too weak to build a template from: the detector
// is unsure it even sees a face, or the face is a small blob far from the
// tablet.
const MIN_DETECTION_SCORE = 0.7;
const MIN_FACE_WIDTH_RATIO = 0.2;

const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions({
    inputSize: 320,
    scoreThreshold: 0.5,
});

let modelsReady = null;
let mediaStream = null;
let detectLoopId = null;

// Bumped by every start(). The kiosk registration mode starts a new capture
// for each student, so loops and camera prompts from an earlier one must
// notice they are stale and stop instead of fighting over the camera.
let currentSession = 0;
let onRejected = null;

/**
 * Load the three nets, reporting each one as it lands.
 *
 * They total ~6.8 MB, almost all of it faceRecognitionNet, so this is the slow
 * part of starting the wizard. It is kicked off alongside getUserMedia rather
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

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Average a burst of descriptors of the face in front of the camera into one
 * sample, or explain why the frames were not good enough.
 *
 * @returns {Promise<{ descriptor: number[] } | { error: string }>}
 */
async function captureAveragedDescriptor(video) {
    const descriptors = [];

    for (let frame = 0; frame < FRAMES_PER_SAMPLE; frame++) {
        if (frame > 0) {
            await wait(FRAME_INTERVAL_MS);
        }

        const result = await faceapi
            .detectSingleFace(video, DETECTOR_OPTIONS)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!result) {
            return { error: 'Wajah tidak terdeteksi. Tahan posisi dan coba lagi dengan pencahayaan yang cukup.' };
        }

        if (result.detection.box.width < video.videoWidth * MIN_FACE_WIDTH_RATIO) {
            return { error: 'Wajah terlalu jauh. Dekatkan wajah ke kamera hingga mengisi bingkai.' };
        }

        if (result.detection.score < MIN_DETECTION_SCORE) {
            return { error: 'Wajah kurang jelas. Hadapkan wajah lurus ke kamera dengan pencahayaan yang cukup.' };
        }

        descriptors.push(result.descriptor);
    }

    const average = Array.from(descriptors[0], (_, index) =>
        descriptors.reduce((sum, descriptor) => sum + descriptor[index], 0) / descriptors.length,
    );

    return { descriptor: average };
}

function stopCamera() {
    if (detectLoopId) {
        clearTimeout(detectLoopId);
        detectLoopId = null;
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
}

/**
 * Grab the current video frame as a JPEG data URL. The first sample's frame
 * doubles as the student's profile photo.
 */
function captureSnapshot(video, maxWidth = 480) {
    const scale = Math.min(1, maxWidth / video.videoWidth);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', 0.85);
}

window.SmartsisFace = {
    /**
     * Boot the face-registration UI inside the given container.
     *
     * Expected elements inside `container`:
     *   video[data-face-video], [data-face-status], button[data-face-capture],
     *   [data-face-progress] (optional dots showing captured samples).
     *
     * @param {HTMLElement} container
     * @param {{ storeFaceDescriptors: (samples: number[][]) => Promise<void> }} wire  Livewire $wire proxy.
     */
    async start(container, wire) {
        const session = ++currentSession;
        stopCamera();

        if (onRejected) {
            window.removeEventListener('face-capture-rejected', onRejected);
            onRejected = null;
        }

        const isCurrent = () => session === currentSession && container.isConnected;

        const video = container.querySelector('[data-face-video]');
        const statusEl = container.querySelector('[data-face-status]');
        const captureButton = container.querySelector('[data-face-capture]');
        const progressDots = container.querySelectorAll('[data-face-progress] [data-dot]');

        const samples = [];
        let snapshot = null;
        let faceVisible = false;
        let busy = false;

        const setStatus = (text, tone = 'info') => {
            statusEl.textContent = text;
            statusEl.classList.toggle('text-red-600', tone === 'error');
            statusEl.classList.toggle('text-green-600', tone === 'success');
            statusEl.classList.toggle('text-gray-500', tone === 'info');
        };

        const refreshUi = () => {
            captureButton.disabled = !faceVisible || busy || samples.length >= SAMPLES_NEEDED;
            progressDots.forEach((dot, index) => {
                dot.classList.toggle('bg-primary-600', index < samples.length);
                dot.classList.toggle('bg-gray-200', index >= samples.length);
            });
        };

        refreshUi();

        // Kick the ~6.8 MB of model downloads off first, but do not wait on
        // them: the camera preview is what the student is waiting to see, and
        // it is ready in a fraction of the time the nets take.
        const modelsPromise = loadModels((done, total) => {
            if (mediaStream !== null) {
                setStatus(`Memuat model pengenalan wajah… (${done}/${total})`);
            }
        });

        // Nothing below should surface as an unhandled rejection while the
        // camera prompt is still open.
        modelsPromise.catch(() => {});

        const openCamera = async () => {
            try {
                setStatus('Menyalakan kamera…');
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                });

                // A newer capture started while the permission prompt was open.
                if (!isCurrent()) {
                    stream.getTracks().forEach((track) => track.stop());

                    return false;
                }

                mediaStream = stream;
                video.srcObject = mediaStream;
                await video.play();

                return true;
            } catch (error) {
                console.error('[SmartsisFace]', error);
                setStatus(
                    error?.name === 'NotAllowedError'
                        ? 'Akses kamera ditolak. Izinkan kamera pada browser lalu muat ulang halaman.'
                        : 'Kamera tidak tersedia. Pastikan perangkat memiliki kamera lalu muat ulang halaman.',
                    'error',
                );

                return false;
            }
        };

        if (!(await openCamera())) {
            return;
        }

        // Preview is live; the detector cannot run until the nets land.
        try {
            setStatus('Memuat model pengenalan wajah…');
            await modelsPromise;
        } catch (error) {
            console.error('[SmartsisFace]', error);
            stopCamera();
            setStatus('Gagal memuat model pengenalan wajah. Muat ulang halaman untuk mencoba lagi.', 'error');

            return;
        }

        if (!mediaStream || !isCurrent()) {
            if (session === currentSession) {
                stopCamera();
            }

            return;
        }

        // Lightweight presence loop: keeps the status + button in sync with
        // whether a face is currently in frame.
        const detectLoop = async () => {
            if (!mediaStream || session !== currentSession) {
                return;
            }

            if (!busy && samples.length < SAMPLES_NEEDED) {
                const detection = await faceapi.detectSingleFace(video, DETECTOR_OPTIONS);
                faceVisible = Boolean(detection);
                setStatus(
                    faceVisible
                        ? 'Wajah terdeteksi. Tekan tombol untuk mengambil sampel.'
                        : 'Posisikan wajah Anda di tengah kamera.',
                );
                refreshUi();
            }

            detectLoopId = setTimeout(detectLoop, 400);
        };

        detectLoop();

        // The server refused the capture (or staff cancelled the look-alike
        // dialog): clear it and bring the camera back.
        onRejected = async () => {
            if (!isCurrent()) {
                return;
            }

            samples.length = 0;
            snapshot = null;
            busy = false;
            refreshUi();

            if (await openCamera()) {
                detectLoop();
            }
        };
        window.addEventListener('face-capture-rejected', onRejected);

        captureButton.addEventListener('click', async () => {
            if (busy || samples.length >= SAMPLES_NEEDED) {
                return;
            }

            busy = true;
            refreshUi();
            setStatus('Memproses wajah…');

            try {
                const frameSnapshot = captureSnapshot(video);
                const result = await captureAveragedDescriptor(video);

                if (result.error) {
                    setStatus(result.error, 'error');

                    return;
                }

                samples.push(result.descriptor);
                snapshot ??= frameSnapshot;
                refreshUi();

                if (samples.length < SAMPLES_NEEDED) {
                    setStatus(`Sampel ${samples.length}/${SAMPLES_NEEDED} tersimpan. Ambil satu lagi.`, 'success');

                    return;
                }

                setStatus('Menyimpan data wajah…', 'success');
                stopCamera();
                await wire.storeFaceDescriptors(samples, snapshot);
            } catch (error) {
                console.error('[SmartsisFace]', error);
                setStatus('Terjadi kesalahan saat memproses wajah. Coba lagi.', 'error');
            } finally {
                busy = false;
                refreshUi();
            }
        });
    },

    stop: stopCamera,
};

// Safety net: release the camera when navigating away (wire:navigate or full unload).
window.addEventListener('beforeunload', stopCamera);
document.addEventListener('livewire:navigating', stopCamera);
