// SMARTSIS — self-service absensi camera (PRD F-09/F-10).
//
// Deliberately dependency-free: no face-api models, no landmark tracking, no
// blink challenge. The identity is already pinned server-side to the signed-in
// siswa, so the browser's only jobs are to show a preview, grab one frame as
// evidence, and attach the GPS fix. That keeps the camera on screen in the
// time getUserMedia takes rather than the ~6.8 MB the recognition nets take.
//
// Used from Alpine x-init:
//   window.SmartsisCamera.start($el, $wire)
//
// Expected elements inside the container:
//   video[data-camera-video], [data-camera-status], button[data-camera-capture]
//   (optional) [data-camera-location]

// Longest we wait for a GPS fix before recording without one — attendance
// must never be blocked by a slow or refused location permission.
const LOCATION_TIMEOUT_MS = 8000;

// Evidence photos are downscaled to this width before upload.
const PHOTO_WIDTH = 640;
const PHOTO_QUALITY = 0.72;

/** The stream owned by the session currently on screen. */
let mediaStream = null;

/** Watchdog releasing the camera once Livewire drops the panel. */
let watchdogId = null;

function stopCamera() {
    if (watchdogId) {
        clearInterval(watchdogId);
        watchdogId = null;
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
}

/**
 * Ask the browser for a position once, resolving to null on denial/timeout
 * instead of rejecting — a missing fix is a normal outcome, not an error.
 *
 * @returns {Promise<{latitude: number, longitude: number, accuracy: number}|null>}
 */
function requestPosition() {
    if (!navigator.geolocation) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        let settled = false;

        const finish = (value) => {
            if (!settled) {
                settled = true;
                resolve(value);
            }
        };

        navigator.geolocation.getCurrentPosition(
            (position) =>
                finish({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: Math.round(position.coords.accuracy ?? 0),
                }),
            () => finish(null),
            { enableHighAccuracy: true, timeout: LOCATION_TIMEOUT_MS, maximumAge: 0 },
        );

        // Belt and braces: some browsers never fire either callback when the
        // permission prompt is dismissed rather than answered.
        setTimeout(() => finish(null), LOCATION_TIMEOUT_MS + 500);
    });
}

/**
 * Grab the current video frame as a downscaled JPEG data URL.
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

    // The preview is mirrored for the student; un-mirror it so the stored
    // evidence photo reads the way a human expects.
    context.translate(canvas.width, 0);
    context.scale(-1, 1);
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', PHOTO_QUALITY);
}

window.SmartsisCamera = {
    /**
     * @param {HTMLElement} container
     * @param {{ record: (payload: object) => Promise<void> }} wire  Livewire $wire proxy.
     */
    async start(container, wire) {
        const video = container.querySelector('[data-camera-video]');
        const statusEl = container.querySelector('[data-camera-status]');
        const captureButton = container.querySelector('[data-camera-capture]');
        const locationEl = container.querySelector('[data-camera-location]');

        const setStatus = (text, tone = 'info') => {
            if (!statusEl) {
                return;
            }

            statusEl.textContent = text;
            statusEl.classList.toggle('text-red-600', tone === 'error');
            statusEl.classList.toggle('text-green-600', tone === 'success');
            statusEl.classList.toggle('text-amber-600', tone === 'warning');
            statusEl.classList.toggle('text-gray-500', tone === 'info');
        };

        const setLocation = (text) => {
            if (locationEl) {
                locationEl.textContent = text;
            }
        };

        // The last fix we managed to get, refreshed in the background so the
        // press of the capture button rarely has to wait for the GPS.
        let position = null;

        const refreshPosition = async () => {
            setLocation('Mengambil lokasi…');
            position = await requestPosition();
            setLocation(
                position === null
                    ? 'Lokasi tidak tersedia — izinkan akses lokasi pada browser.'
                    : `Lokasi terdeteksi (±${position.accuracy} m).`,
            );
        };

        // Ask for the location permission up front, alongside the camera, so
        // the student answers both prompts before they press absen.
        refreshPosition();

        let stream = null;

        try {
            setStatus('Menyalakan kamera…');
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });

            stopCamera();
            mediaStream = stream;
            video.srcObject = stream;
            await video.play();
        } catch (error) {
            console.error('[SmartsisCamera]', error);
            setStatus(
                error?.name === 'NotAllowedError'
                    ? 'Akses kamera ditolak. Izinkan kamera pada browser lalu muat ulang halaman.'
                    : 'Kamera tidak tersedia. Pastikan perangkat memiliki kamera lalu muat ulang halaman.',
                'error',
            );

            return;
        }

        if (!container.isConnected) {
            stopCamera();

            return;
        }

        setStatus('Kamera siap. Tekan tombol untuk absen.', 'success');
        captureButton?.removeAttribute('disabled');

        // Livewire removes this panel the moment the day is fully recorded
        // (or the check-out window closes it). Nothing else would notice, so
        // watch for the detach and hand the camera back to the device.
        watchdogId = setInterval(() => {
            if (!container.isConnected && mediaStream === stream) {
                stopCamera();
            }
        }, 1000);

        captureButton?.addEventListener('click', async () => {
            captureButton.setAttribute('disabled', 'disabled');
            setStatus('Mencatat absensi…');

            const photo = grabFrame(video);

            // One more try if the background attempt came back empty, so a
            // student who granted the permission late is still located.
            if (position === null) {
                await refreshPosition();
            }

            try {
                await wire.record({
                    photo,
                    latitude: position?.latitude ?? null,
                    longitude: position?.longitude ?? null,
                    accuracy: position?.accuracy ?? null,
                });
                setStatus('Absensi tercatat.', 'success');
            } catch (error) {
                console.error('[SmartsisCamera]', error);
                setStatus('Gagal mencatat absensi. Coba lagi.', 'error');
                captureButton.removeAttribute('disabled');
            }
        });
    },

    stop: stopCamera,
};

// Release the camera when navigating away (wire:navigate or full unload).
window.addEventListener('beforeunload', stopCamera);
document.addEventListener('livewire:navigating', stopCamera);
