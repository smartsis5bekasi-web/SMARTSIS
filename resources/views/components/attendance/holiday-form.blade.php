@props([
    'scope', // the component's current $scope: "all" or "grades"
    'gradeOptions', // array<int, App\Enums\GradeLevel>
    'classroomsWithoutGrade' => 0,
])

{{-- The Hari Libur fields, shared by the create and edit pages; the bindings
     resolve against the page component (App\Livewire\Concerns\EditsSchoolHoliday). --}}
<form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <div class="mb-8 grid grid-cols-1 gap-8 md:grid-cols-2">
        <div class="flex flex-col md:col-span-2">
            <label for="holiday-name" class="mb-1 font-semibold text-gray-600">{{ __('Keterangan') }} <span class="text-red-500">*</span></label>
            <input id="holiday-name" type="text" wire:model="name" placeholder="{{ __('Cuti bersama SKB 3 Menteri / Libur darurat asap vulkanik') }}"
                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <span class="mt-1 text-xs text-gray-400">{{ __('Ditampilkan ke siswa saat mereka membuka absensi pada hari libur.') }}</span>
            @error('name')
                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col">
            <label for="holiday-start" class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Mulai') }} <span class="text-red-500">*</span></label>
            <input id="holiday-start" type="date" wire:model.live="start_date"
                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('start_date')
                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col">
            <label for="holiday-end" class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Selesai') }} <span class="text-red-500">*</span></label>
            <input id="holiday-end" type="date" wire:model="end_date"
                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <span class="mt-1 text-xs text-gray-400">{{ __('Sama dengan tanggal mulai untuk libur satu hari. Sabtu & Minggu sudah otomatis libur.') }}</span>
            @error('end_date')
                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col md:col-span-2">
            <span class="mb-2 font-semibold text-gray-600">{{ __('Berlaku Untuk') }} <span class="text-red-500">*</span></span>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label @class([
                    'flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition',
                    'border-primary-500 bg-primary-50' => $scope === 'all',
                    'border-gray-200 hover:bg-gray-50' => $scope !== 'all',
                ])>
                    <input type="radio" wire:model.live="scope" value="all" class="mt-1 text-primary-600 focus:ring-primary-500">
                    <span class="flex flex-col">
                        <span class="font-semibold text-gray-800">{{ __('Seluruh sekolah') }}</span>
                        <span class="text-xs text-gray-500">{{ __('Tanggal merah, cuti bersama, atau sekolah ditutup (bencana, asap, dll).') }}</span>
                    </span>
                </label>
                <label @class([
                    'flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition',
                    'border-primary-500 bg-primary-50' => $scope === 'grades',
                    'border-gray-200 hover:bg-gray-50' => $scope !== 'grades',
                ])>
                    <input type="radio" wire:model.live="scope" value="grades" class="mt-1 text-primary-600 focus:ring-primary-500">
                    <span class="flex flex-col">
                        <span class="font-semibold text-gray-800">{{ __('Tingkat tertentu') }}</span>
                        <span class="text-xs text-gray-500">{{ __('Misalnya kelas XII masuk untuk kegiatan, kelas X dan XI libur.') }}</span>
                    </span>
                </label>
            </div>
            @error('scope')
                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>

        @if ($scope === 'grades')
            <div class="flex flex-col md:col-span-2">
                <span class="mb-2 font-semibold text-gray-600">{{ __('Tingkat yang Libur') }} <span class="text-red-500">*</span></span>
                <div class="flex flex-wrap gap-3">
                    @foreach ($gradeOptions as $option)
                        <label wire:key="grade-{{ $option->value }}" class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input type="checkbox" wire:model="grades" value="{{ $option->value }}" class="rounded text-primary-600 focus:ring-primary-500">
                            {{ $option->label() }}
                        </label>
                    @endforeach
                </div>
                <span class="mt-1 text-xs text-gray-400">{{ __('Tingkat yang tidak dicentang tetap masuk dan wajib absen seperti biasa.') }}</span>
                @error('grades')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
                @error('grades.*')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror

                @if ($classroomsWithoutGrade > 0)
                    <div class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <ion-icon name="warning-outline" class="mt-0.5 shrink-0 text-sm"></ion-icon>
                        <span>
                            {{ __(':count kelas belum diisi tingkatnya, jadi siswanya tidak ikut libur per tingkat dan tetap wajib absen.', ['count' => $classroomsWithoutGrade]) }}
                            <a href="{{ route('master-data.classrooms') }}" wire:navigate class="font-semibold underline">{{ __('Lengkapi di Data Kelas') }}</a>
                        </span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="mb-8 flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600">
        <ion-icon name="information-circle-outline" class="mt-0.5 shrink-0 text-base text-gray-500"></ion-icon>
        <span>
            {{ __('Siswa yang libur tidak bisa absen, tidak dikirimi pengingat, dan tidak ditandai "Menunggu Konfirmasi" atau Alpha. Jika tanggalnya sudah lewat, absensi "Menunggu Konfirmasi" dan Alpha otomatis yang belum dicek pada hari itu dibatalkan (poin dikembalikan). Absensi yang dicatat siswa atau guru tidak diubah.') }}
        </span>
    </div>

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="route('attendance.absensi.holidays')" wire:navigate>
            {{ __('Batal') }}
        </x-ui.button>
        <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
    </div>
</form>
