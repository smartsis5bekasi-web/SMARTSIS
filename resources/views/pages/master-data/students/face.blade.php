<?php

use App\Actions\Student\RegisterStudentFace;
use App\Models\Student;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Daftarkan Wajah Siswa')] class extends Component {
    public Student $student;

    public function mount(Student $student): void
    {
        $this->student = $student->load('classroom');
    }

    /**
     * Store the template an admin captured for this student — the fallback
     * for a student whose own device camera failed during onboarding, and the
     * way to re-register a face that the classroom kiosk keeps missing.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    public function storeFaceDescriptors(array $descriptors, ?string $snapshot = null): void
    {
        if (! app(RegisterStudentFace::class)->handle($this->student, $descriptors, $snapshot)) {
            $this->dispatch('swal', icon: 'error', title: __('Data wajah tidak valid. Silakan ulangi.'));

            return;
        }

        toast(__('Wajah :name berhasil didaftarkan.', ['name' => $this->student->name]), 'success');

        $this->redirectRoute('master-data.students.show', $this->student, navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @vite('resources/js/face-onboarding.js')

    <x-ui.page-header :title="__('Daftarkan Wajah')" :subtitle="$student->name.' · '.($student->classroom?->name ?? __('Tanpa kelas'))">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.students.show', $student)" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-3">
            <div wire:ignore x-data x-init="window.SmartsisFace.start($el, $wire)" class="flex flex-col">
                <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary-400 bg-gray-900">
                    <video data-face-video playsinline muted autoplay class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="h-3/4 w-1/2 rounded-[50%] border-2 border-white/60"></div>
                    </div>
                </div>

                <p data-face-status class="mt-4 min-h-6 text-center text-sm text-gray-500">{{ __('Menyiapkan kamera…') }}</p>

                <div data-face-progress class="mt-2 flex items-center justify-center gap-2">
                    <span data-dot class="h-2.5 w-2.5 rounded-full bg-gray-200 transition"></span>
                </div>

                <x-ui.button variant="primary" icon="camera-outline" class="mt-6 w-full" data-face-capture disabled>
                    {{ __('Ambil Foto Wajah') }}
                </x-ui.button>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-center gap-4">
                <img class="h-16 w-16 rounded-2xl object-cover"
                    src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}"
                    alt="{{ $student->name }}" />
                <div class="flex flex-col">
                    <span class="font-bold text-gray-800">{{ $student->name }}</span>
                    <span class="text-sm text-gray-500">{{ $student->nis }}</span>
                    @if ($student->hasRegisteredFace())
                        <span class="inline-flex items-center gap-1 text-sm text-green-600">
                            <ion-icon name="shield-checkmark-outline"></ion-icon>
                            {{ __('Wajah terdaftar :date', ['date' => $student->face_registered_at?->translatedFormat('d M Y') ?? '']) }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-sm text-amber-600">
                            <ion-icon name="alert-circle-outline"></ion-icon>
                            {{ __('Wajah belum terdaftar') }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($student->hasRegisteredFace())
                <div class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-sm text-amber-700">
                    <ion-icon name="information-circle-outline" class="mt-0.5 shrink-0 text-lg"></ion-icon>
                    <span>{{ __('Mengambil foto baru akan mengganti data wajah dan foto profil yang lama.') }}</span>
                </div>
            @endif

            <ul class="flex list-disc flex-col gap-1 pl-5 text-sm text-gray-600">
                <li>{{ __('Minta siswa menghadap kamera tanpa masker atau kacamata hitam.') }}</li>
                <li>{{ __('Pastikan pencahayaan cukup dan hanya satu wajah di dalam bingkai.') }}</li>
                <li>{{ __('Tekan tombol saat status menunjukkan wajah terdeteksi.') }}</li>
            </ul>
        </div>
    </div>
</div>
