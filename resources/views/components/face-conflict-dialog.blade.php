{{--
    The look-alike dialog of App\Livewire\Concerns\ResolvesFaceConflicts: the
    frame just captured next to the photo of the student the face already
    belongs to, so staff can see which record is wrong. The slot takes extra
    page-specific actions (e.g. "re-register the other student").
--}}
@props(['conflict', 'snapshot' => null, 'studentName'])

@php
    $resolution = App\Enums\FaceConflictResolution::tryFrom($conflict['resolution'] ?? '');
@endphp

<div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.dismissFaceConflict()">
    <div class="absolute inset-0 bg-gray-900/50" wire:click="dismissFaceConflict"></div>

    <div class="relative flex max-h-full w-full max-w-xl flex-col gap-5 overflow-y-auto rounded-xl bg-white px-6 py-7 shadow-xl">
        <div class="flex flex-col items-center gap-2 text-center">
            <span @class([
                'inline-flex h-12 w-12 items-center justify-center rounded-full',
                'bg-red-50 text-red-600' => $resolution === null,
                'bg-amber-50 text-amber-600' => $resolution !== null,
            ])>
                <ion-icon name="people-outline" class="text-2xl"></ion-icon>
            </span>
            <h2 class="text-xl font-bold text-gray-900">{{ __('Wajah mirip dengan siswa lain') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Bandingkan kedua foto. Cocokkan dengan kartu pelajar bila ragu.') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <figure class="flex flex-col gap-2">
                <img class="aspect-square w-full rounded-xl bg-gray-100 object-cover" src="{{ $snapshot ?? asset('assets/placeholder.png') }}" alt="" />
                <figcaption class="text-center text-sm">
                    <span class="block text-xs text-gray-500">{{ __('Foto barusan') }}</span>
                    <span class="font-semibold text-gray-800">{{ $studentName }}</span>
                </figcaption>
            </figure>
            <figure class="flex flex-col gap-2">
                <img class="aspect-square w-full rounded-xl bg-gray-100 object-cover" src="{{ $conflict['avatar'] ?? asset('assets/placeholder.png') }}" alt="" />
                <figcaption class="text-center text-sm">
                    <span class="block text-xs text-gray-500">{{ __('Sudah terdaftar') }} · {{ $conflict['source'] }}</span>
                    <span class="font-semibold text-gray-800">{{ $conflict['name'] }}</span>
                    <span class="text-gray-500">({{ $conflict['classroom'] ?? __('tanpa kelas') }})</span>
                </figcaption>
            </figure>
        </div>

        <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
            @if ($resolution === App\Enums\FaceConflictResolution::Transfer)
                {{ __('Wajah :name didaftarkan mandiri dan belum pernah diverifikasi petugas. Jika foto :name di atas sebenarnya wajah :student, pindahkan: data wajah :name dihapus dan :name perlu didaftarkan ulang di depan petugas.', ['name' => $conflict['name'], 'student' => $studentName]) }}
            @elseif ($resolution === App\Enums\FaceConflictResolution::KeepBoth)
                {{ __('Jika sudah dipastikan keduanya orang yang berbeda, simpan keduanya. Kiosk absensi tidak akan menebak bila wajah keduanya terlalu mirip saat dipindai; siswa tersebut tetap bisa absen dari akun masing-masing.') }}
            @else
                {{ __('Wajah ini hampir identik dengan data :name yang sudah diverifikasi petugas, sehingga tidak bisa disimpan untuk dua siswa. Jika data :name salah orang, daftarkan ulang wajah :name dengan :name sendiri di depan kamera, lalu ulangi pendaftaran ini.', ['name' => $conflict['name']]) }}
            @endif
            <span class="mt-1 block text-xs text-gray-400">{{ __('Jarak wajah :distance (batas 0.50)', ['distance' => number_format($conflict['distance'], 2)]) }}</span>
        </div>

        <div class="flex flex-col gap-2">
            @if ($resolution === App\Enums\FaceConflictResolution::Transfer)
                <x-ui.button variant="primary" icon="swap-horizontal-outline" class="w-full" wire:click="confirmFaceConflict" wire:loading.attr="disabled">
                    {{ __('Pindahkan ke :student', ['student' => $studentName]) }}
                </x-ui.button>
            @elseif ($resolution === App\Enums\FaceConflictResolution::KeepBoth)
                <x-ui.button variant="primary" icon="checkmark-outline" class="w-full" wire:click="confirmFaceConflict" wire:loading.attr="disabled">
                    {{ __('Orang berbeda, tetap simpan') }}
                </x-ui.button>
            @endif

            {{ $slot }}

            <x-ui.button variant="secondary" icon="camera-outline" class="w-full" wire:click="dismissFaceConflict">
                {{ __('Batal, foto ulang') }}
            </x-ui.button>
        </div>
    </div>
</div>
