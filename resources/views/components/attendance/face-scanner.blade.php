@props([
    'mode',
    'templatesUrl',
    'classroomId' => null,
])

{{--
    Mode switch + camera for the face-scan attendance flow (HandlesFaceScan).

    The camera block is keyed by the class filter: picking another class swaps
    the element, which re-runs x-init and restarts the scanner against that
    class's templates. The old session notices its container is gone and
    releases its own camera stream.
--}}
<div {{ $attributes->class('flex flex-col') }}>
    <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1">
        <button type="button" wire:click="setMode('masuk')" @class([
            'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
            'bg-primary-600 text-white shadow' => $mode === 'masuk',
            'text-gray-600 hover:text-gray-800' => $mode !== 'masuk',
        ])>
            <ion-icon name="log-in-outline" class="text-lg"></ion-icon>
            {{ __('Absensi Masuk') }}
        </button>
        <button type="button" wire:click="setMode('pulang')" @class([
            'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
            'bg-primary-600 text-white shadow' => $mode === 'pulang',
            'text-gray-600 hover:text-gray-800' => $mode !== 'pulang',
        ])>
            <ion-icon name="log-out-outline" class="text-lg"></ion-icon>
            {{ __('Absensi Pulang') }}
        </button>
    </div>

    <div wire:ignore wire:key="face-scanner-{{ $classroomId ?? 'all' }}" x-data
        x-init="window.SmartsisAttendance.start($el, $wire, { templatesUrl: @js($templatesUrl), scoped: @js($classroomId !== null) })"
        class="flex flex-col">
        <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary-400 bg-gray-900">
            <video data-face-video playsinline muted autoplay class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <div class="h-3/4 w-1/2 rounded-[50%] border-2 border-white/60"></div>
            </div>

            {{-- Name + class plate, filled by face-attendance.js the moment a face matches. --}}
            <div data-face-identity
                class="pointer-events-none absolute inset-x-0 bottom-0 hidden bg-gradient-to-t from-black/80 to-transparent px-4 pb-4 pt-10 text-center">
                <p data-face-identity-name class="text-2xl font-bold leading-tight text-white sm:text-3xl"></p>
                <p data-face-identity-class class="text-sm font-medium text-white/80"></p>
            </div>
        </div>

        <p data-face-status class="mt-4 min-h-6 text-center text-sm text-gray-500">{{ __('Menyiapkan kamera…') }}</p>
    </div>
</div>
