// SMARTSIS — Face registration for the first-login student onboarding.
//
// Uses @vladmandic/face-api (maintained fork of face-api.js, per PROPOSAL 8.5):
// TinyFaceDetector + 68-point landmarks + FaceRecognitionNet 128-d descriptors.
// Models are self-hosted in /public/models/face-api so the app works on the
// school LAN without internet access.
//
// The onboarding page (pages::onboarding.index) calls window.SmartsisFace.start()
// from an Alpine x-init when step 2 renders, and receives the captured
// descriptors back through $wire.storeFaceDescriptors().

import * as faceapi from '@vladmandic/face-api';

const MODEL_URL = '/models/face-api';
const SAMPLES_NEEDED = 3;

// Two descriptors of the same person are expected to sit well below this
// euclidean distance (face-api convention: 0.5–0.6 is the match boundary).
// Samples further apart than this are rejected so one registration cannot
// mix two different faces.
const SAMPLE_CONSISTENCY_THRESHOLD = 0.45;

const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions({
    inputSize: 320,
    scoreThreshold: 0.5,
});

let modelsReady = null;
let mediaStream = null;
let detectLoopId = null;

function loadModels() {
    modelsReady ??= Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);

    return modelsReady;
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

/**
 * Euclidean distance between two 128-d descriptors (also used by the
 * attendance matcher later on).
 */
function descriptorDistance(a, b) {
    let sum = 0;

    for (let i = 0; i < a.length; i++) {
        const diff = a[i] - b[i];
        sum += diff * diff;
    }

    return Math.sqrt(sum);
}

window.SmartsisFace = {
    /**
     * Boot the face-registration UI inside the given container.
     *
     * Expected elements inside `container`:
     *   video[data-face-video], [data-face-status], button[data-face-capture],
     *   [data-face-progress] (dots showing captured samples).
     *
     * @param {HTMLElement} container
     * @param {{ storeFaceDescriptors: (samples: number[][]) => Promise<void> }} wire  Livewire $wire proxy.
     */
    async start(container, wire) {
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

        try {
            setStatus('Memuat model pengenalan wajah…');
            await loadModels();

            setStatus('Menyalakan kamera…');
            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            video.srcObject = mediaStream;
            await video.play();
        } catch (error) {
            console.error('[SmartsisFace]', error);
            setStatus(
                error?.name === 'NotAllowedError'
                    ? 'Akses kamera ditolak. Izinkan kamera pada browser lalu muat ulang halaman.'
                    : 'Kamera tidak tersedia. Pastikan perangkat memiliki kamera lalu muat ulang halaman.',
                'error',
            );

            return;
        }

        // Lightweight presence loop: keeps the status + button in sync with
        // whether a face is currently in frame.
        const detectLoop = async () => {
            if (!mediaStream) {
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

        captureButton.addEventListener('click', async () => {
            if (busy || samples.length >= SAMPLES_NEEDED) {
                return;
            }

            busy = true;
            refreshUi();
            setStatus('Memproses wajah…');

            try {
                const result = await faceapi
                    .detectSingleFace(video, DETECTOR_OPTIONS)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!result) {
                    setStatus('Wajah tidak terdeteksi. Coba lagi dengan pencahayaan yang cukup.', 'error');

                    return;
                }

                const descriptor = Array.from(result.descriptor);

                if (samples.length > 0 && descriptorDistance(samples[0], descriptor) > SAMPLE_CONSISTENCY_THRESHOLD) {
                    setStatus('Sampel tidak konsisten dengan sebelumnya. Pastikan wajah yang sama, lalu coba lagi.', 'error');

                    return;
                }

                samples.push(descriptor);
                snapshot ??= captureSnapshot(video);
                refreshUi();

                if (samples.length < SAMPLES_NEEDED) {
                    setStatus(`Sampel ${samples.length}/${SAMPLES_NEEDED} tersimpan. Ubah sedikit posisi kepala, lalu ambil lagi.`, 'success');

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
    descriptorDistance,
};

// Safety net: release the camera when navigating away (wire:navigate or full unload).
window.addEventListener('beforeunload', stopCamera);
document.addEventListener('livewire:navigating', stopCamera);
