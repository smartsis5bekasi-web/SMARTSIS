<?php

use App\Actions\Student\RegisterStudentFace;
use App\Enums\UserRole;
use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::onboarding')] #[Title('Aktivasi Akun Siswa')] class extends Component {
    public int $step = 1;

    public string $nisn = '';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->hasRole(UserRole::Siswa->value)) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $student = $user->student;

        if ($student !== null && ! $student->needsOnboarding()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        // Resume where the student left off (e.g. closed the tab mid-onboarding).
        // The NISN step is always completed first, even when the account was
        // already linked to a student by the admin.
        if ($student?->hasVerifiedNisn()) {
            $this->nisn = (string) $student->nisn;
            $this->step = $student->hasRegisteredFace() ? 3 : 2;
        }
    }

    #[Computed]
    public function student(): ?Student
    {
        return auth()->user()->student()->with(['classroom', 'major'])->first();
    }

    /**
     * Step 1 — match the entered NISN against the student master data and
     * link the record to this account.
     */
    public function verifyNisn(): void
    {
        $this->validate(
            ['nisn' => ['required', 'string', 'max:30']],
            [],
            ['nisn' => 'NISN'],
        );

        $user = auth()->user();
        $student = Student::query()->where('nisn', trim($this->nisn))->first();

        if ($student === null) {
            $this->addError('nisn', __('NISN tidak ditemukan. Periksa kembali atau hubungi admin sekolah.'));

            return;
        }

        if ($user->student !== null && $user->student->isNot($student)) {
            $this->addError('nisn', __('NISN tidak sesuai dengan data siswa pada akun Anda.'));

            return;
        }

        if ($student->user_id !== null && $student->user_id !== $user->id) {
            $this->addError('nisn', __('NISN ini sudah terhubung dengan akun lain. Hubungi admin sekolah.'));

            return;
        }

        $student->update(['user_id' => $user->id, 'nisn_verified_at' => now()]);
        unset($this->student);

        $this->step = $student->hasRegisteredFace() ? 3 : 2;
    }

    /**
     * Step 2 — persist the face template captured in the browser.
     * Expects one to three samples of 128-dimension descriptors from face-api,
     * plus a JPEG snapshot of the first sample that becomes the profile photo.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    public function storeFaceDescriptors(array $descriptors, ?string $snapshot = null): void
    {
        $student = auth()->user()->student;
        abort_unless($student !== null && $student->hasVerifiedNisn(), 403);

        if (! app(RegisterStudentFace::class)->handle($student, $descriptors, $snapshot)) {
            $this->dispatch('swal', icon: 'error', title: __('Data wajah tidak valid. Silakan ulangi.'));

            return;
        }

        unset($this->student);

        $this->step = 3;
    }

    /**
     * Step 3 → 2 — the student is not happy with the photo (blurry, bad
     * lighting, wrong person in frame) and wants to shoot it again. The stored
     * template stays until a new capture overwrites it, so backing out of the
     * retake leaves the account exactly as it was.
     */
    public function retakeFace(): void
    {
        $student = auth()->user()->student;
        abort_unless($student !== null && $student->hasVerifiedNisn(), 403);

        $this->step = 2;
    }

    /**
     * Step 2 → 3 — leave the retake without capturing anything. Only reachable
     * when a face is already registered; otherwise step 2 is mandatory.
     */
    public function cancelRetakeFace(): void
    {
        $student = auth()->user()->student;
        abort_unless($student !== null && $student->hasRegisteredFace(), 403);

        $this->step = 3;
    }

    /**
     * Step 3 — student confirms their identity; onboarding is complete.
     * The face template is mandatory: the classroom kiosk identifies students
     * by face, so a student without one could never be marked present there.
     */
    public function completeOnboarding(): void
    {
        $student = auth()->user()->student;
        abort_unless($student !== null && $student->hasVerifiedNisn(), 403);

        if (! $student->hasRegisteredFace()) {
            $this->step = 2;
            $this->dispatch('swal', icon: 'error', title: __('Daftarkan wajah Anda terlebih dahulu.'));

            return;
        }

        $student->update(['onboarded_at' => now()]);

        toast(__('Selamat datang di SMARTSIS!'), 'success');

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * Steps shown on the left rail.
     *
     * @return array<int, array{title: string, subtitle: string}>
     */
    public function steps(): array
    {
        return [
            1 => ['title' => __('Verifikasi NISN'), 'subtitle' => __('Cocokkan NISN dengan data sekolah')],
            2 => ['title' => __('Registrasi Wajah'), 'subtitle' => __('Wajib, untuk absensi wajah di kelas')],
            3 => ['title' => __('Konfirmasi Data'), 'subtitle' => __('Periksa data dan selesaikan')],
        ];
    }
}; ?>

<div class="flex min-h-screen">
    {{-- Left rail: branding + step progress (benchmark: siwira profile page). --}}
    <div class="hidden w-4/12 flex-col bg-gray-50 xl:flex">
        <div class="flex h-full flex-col px-14 py-10">
            <div class="mb-6 flex items-center gap-3">
                <x-app-logo-icon class="h-12 w-12 fill-current text-primary-600" />
                <div class="flex flex-col">
                    <span class="text-lg font-bold text-gray-800">{{ config('app.name', 'SMARTSIS') }}</span>
                    <span class="text-sm text-gray-500">SMAN 5 Bekasi</span>
                </div>
            </div>

            <p class="text-lg text-gray-600">
                {{ __('Selamat datang! Lengkapi aktivasi akun Anda untuk mulai menggunakan SMARTSIS.') }}
            </p>

            <div class="flex flex-col gap-7 py-16">
                @foreach ($this->steps() as $number => $item)
                    <div class="flex flex-row gap-3">
                        <ion-icon
                            name="{{ $step > $number ? 'checkmark-circle' : 'checkmark-circle-outline' }}"
                            class="text-3xl {{ $step > $number ? 'text-green-600' : ($step === $number ? 'text-primary-600' : 'text-gray-400') }}">
                        </ion-icon>
                        <div class="flex flex-col">
                            <div class="font-bold {{ $step === $number ? 'text-primary-700' : 'text-gray-800' }}">{{ $item['title'] }}</div>
                            <span class="text-gray-500">{{ $item['subtitle'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-auto">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-700 cursor-pointer">
                    <ion-icon name="log-out-outline" class="text-lg"></ion-icon>
                    {{ __('Keluar') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Right: active step content. --}}
    <div class="flex w-full flex-grow justify-center py-10 xl:w-8/12">
        <div class="flex w-full flex-col px-5 lg:px-32">
            {{-- Mobile step indicator. --}}
            <div class="mb-6 flex items-center gap-2 xl:hidden">
                @foreach ($this->steps() as $number => $item)
                    <span class="h-1.5 flex-1 rounded-full {{ $step >= $number ? 'bg-primary-600' : 'bg-gray-200' }}"></span>
                @endforeach
            </div>

            <div class="mb-6 flex flex-col">
                <div class="text-xl font-bold text-gray-800">{{ $this->steps()[$step]['title'] }}</div>
                <span class="text-gray-500">{{ $this->steps()[$step]['subtitle'] }}</span>
            </div>

            @if ($step === 1)
                <form wire:submit="verifyNisn" class="flex flex-col">
                    <div class="mb-5">
                        <div class="mb-2 flex flex-row items-center gap-1">
                            <label class="block text-xs font-semibold text-gray-600" for="nisn">NISN</label>
                            <span class="text-secondary-500">*</span>
                        </div>
                        <input id="nisn" wire:model="nisn" type="text" inputmode="numeric"
                            placeholder="{{ __('Masukkan NISN Anda') }}" autofocus
                            class="w-full appearance-none rounded-md border border-gray-200 px-3 py-2.5 text-gray-700 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-200" />
                        @error('nisn')
                            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                        @enderror
                        <p class="mt-2 text-xs text-gray-500">
                            {{ __('NISN (Nomor Induk Siswa Nasional) digunakan untuk mencocokkan akun Anda dengan data siswa.') }}
                        </p>
                    </div>

                    <x-ui.button variant="primary" type="submit" icon-trailing="arrow-forward-outline" class="mt-10 w-full">
                        <span wire:loading.remove wire:target="verifyNisn">{{ __('Verifikasi & Lanjut') }}</span>
                        <span wire:loading wire:target="verifyNisn">{{ __('Memeriksa…') }}</span>
                    </x-ui.button>
                </form>
            @elseif ($step === 2)
                @php($isRetake = (bool) $this->student?->hasRegisteredFace())
                <div wire:key="face-capture" x-data x-init="window.SmartsisFace.start($el, $wire)" class="flex flex-col">
                    @if ($isRetake)
                        <div class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                            <ion-icon name="information-circle-outline" class="mt-0.5 shrink-0 text-lg"></ion-icon>
                            <span>{{ __('Foto lama masih tersimpan. Foto akan diganti hanya setelah Anda mengambil foto baru.') }}</span>
                        </div>
                    @endif

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

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse">
                        <x-ui.button variant="primary" icon="camera-outline" class="w-full sm:flex-1" data-face-capture disabled>
                            {{ __('Ambil Foto Wajah') }}
                        </x-ui.button>

                        @if ($isRetake)
                            <x-ui.button variant="secondary" icon="arrow-back-outline" class="w-full sm:w-auto"
                                x-on:click="window.SmartsisFace.stop()" wire:click="cancelRetakeFace">
                                {{ __('Batal') }}
                            </x-ui.button>
                        @endif
                    </div>

                    <p class="mt-3 text-center text-xs text-gray-500">
                        {{ __('Cukup satu foto. Wajah ini dipakai untuk absensi cepat di tablet kelas, jadi pastikan wajah terlihat jelas dan pencahayaan cukup.') }}
                    </p>
                    <p class="mt-1 text-center text-xs text-gray-500">
                        {{ __('Kamera bermasalah? Hubungi admin sekolah untuk mendaftarkan wajah Anda.') }}
                    </p>
                </div>
            @elseif ($step === 3)
                @php($student = $this->student)
                <div class="flex flex-col gap-6">
                    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div class="flex items-center gap-4">
                            <img class="h-20 w-20 rounded-2xl object-cover"
                                src="{{ $student?->avatar_url ?? asset('assets/placeholder.png') }}"
                                alt="{{ $student?->name }}" />
                            <div class="flex flex-col">
                                <span class="text-lg font-bold text-gray-800">{{ $student?->name }}</span>
                                @if ($student?->hasRegisteredFace())
                                    <span class="inline-flex items-center gap-1 text-sm text-green-600">
                                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                                        {{ __('Wajah terdaftar') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-sm text-amber-600">
                                        <ion-icon name="alert-circle-outline"></ion-icon>
                                        {{ __('Wajah belum terdaftar') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">NIS</dt>
                                <dd class="text-gray-800">{{ $student?->nis ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">NISN</dt>
                                <dd class="text-gray-800">{{ $student?->nisn ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">{{ __('Kelas') }}</dt>
                                <dd class="text-gray-800">{{ $student?->classroom?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">{{ __('Jurusan') }}</dt>
                                <dd class="text-gray-800">{{ $student?->major?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">{{ __('Tahun Masuk') }}</dt>
                                <dd class="text-gray-800">{{ $student?->year_in ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-gray-500">{{ __('Poin Saat Ini') }}</dt>
                                <dd class="font-semibold text-primary-700">{{ $student?->current_point }}</dd>
                            </div>
                        </dl>
                    </div>

                    <p class="text-sm text-gray-500">
                        {{ __('Pastikan data di atas benar milik Anda. Jika ada yang tidak sesuai, hubungi admin sekolah.') }}
                        {{ __('Foto wajah buram atau kurang jelas? Tekan “Ulangi Foto” untuk mengambil ulang.') }}
                    </p>

                    <div class="flex flex-col gap-3 sm:flex-row-reverse">
                        <x-ui.button variant="primary" icon="checkmark-circle-outline" wire:click="completeOnboarding" class="w-full sm:flex-1">
                            <span wire:loading.remove wire:target="completeOnboarding">{{ __('Konfirmasi & Masuk Dashboard') }}</span>
                            <span wire:loading wire:target="completeOnboarding">{{ __('Menyimpan…') }}</span>
                        </x-ui.button>

                        <x-ui.button variant="secondary" icon="camera-reverse-outline" wire:click="retakeFace" class="w-full sm:w-auto">
                            {{ __('Ulangi Foto') }}
                        </x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
