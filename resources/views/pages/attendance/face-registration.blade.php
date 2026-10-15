<?php

use App\Enums\Permission;
use App\Livewire\Concerns\ResolvesFaceConflicts;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kiosk "Registrasi Wajah": a staff member enrols students' faces in person,
 * so a template can only ever belong to the student standing in front of the
 * camera — the in-person counterpart to the onboarding self-registration that
 * let a student enrol on a classmate's account.
 *
 * A staff account that may register faces is the operator as soon as it opens
 * the page. A classroom tablet signed in as the kiosk device account stays
 * locked until a guru/wali kelas/admin unlocks it with their own credentials;
 * that unlock lapses after {@see self::IDLE_MINUTES} without a registration.
 */
new #[Layout('layouts::kiosk')] #[Title('Registrasi Wajah')] class extends Component {
    use ResolvesFaceConflicts;

    /** Minutes a credential unlock survives without a registration. */
    private const IDLE_MINUTES = 15;

    /** Wrong credentials allowed per tablet per minute. */
    private const UNLOCK_ATTEMPTS = 5;

    /** Students listed at once; the search narrows anything larger. */
    private const LIST_LIMIT = 40;

    /** The staff member running the session. */
    #[Locked]
    public ?int $operatorId = null;

    /** Unix time the credential unlock lapses; null when the signed-in account is the operator. */
    #[Locked]
    public ?int $unlockedUntil = null;

    #[Locked]
    public ?int $selectedStudentId = null;

    #[Url(as: 'kelas', except: null)]
    public ?int $classroomId = null;

    public string $search = '';

    public bool $onlyMissing = true;

    public string $unlockEmail = '';

    public string $unlockPassword = '';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->canRegisterStudentFaces()) {
            $this->operatorId = $user->id;
        }

        $this->normaliseClassroom();
    }

    /**
     * The active operator, or null once the unlock has lapsed or the account
     * lost its access in the meantime.
     */
    #[Computed]
    public function operator(): ?User
    {
        if ($this->operatorId === null) {
            return null;
        }

        if ($this->unlockedUntil !== null && $this->unlockedUntil < now()->timestamp) {
            return null;
        }

        $operator = User::query()->find($this->operatorId);

        return $operator !== null && $operator->isActive() && $operator->canRegisterStudentFaces() ? $operator : null;
    }

    /**
     * Whether the signed-in account can also run the attendance kiosk, so the
     * header offers a way back to it.
     */
    public function canOpenKiosk(): bool
    {
        return auth()->user()->canAny([Permission::ManageAttendance->value, Permission::UseAttendanceKiosk->value]);
    }

    /**
     * Classes the operator may pick from: every class for school-wide roles,
     * the homeroom classes for a wali kelas.
     *
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        $operator = $this->operator;

        if ($operator === null) {
            return new Collection;
        }

        return Classroom::query()
            ->unless(
                $operator->registersFacesForEveryStudent(),
                fn (Builder $query) => $query->whereIn('id', $operator->teacher?->homeroomClassrooms()->select('id') ?? [0]),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Builder<Student>
     */
    private function scopedStudents(User $operator): Builder
    {
        return Student::query()
            ->faceRegistrableBy($operator)
            ->when($this->classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $this->classroomId));
    }

    /**
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        $operator = $this->operator;

        if ($operator === null) {
            return new Collection;
        }

        $term = trim($this->search);

        return $this->scopedStudents($operator)
            ->with('classroom:id,name')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('nis', 'like', "%{$term}%")
                ->orWhere('nisn', 'like', "%{$term}%")))
            ->when($this->onlyMissing, fn (Builder $query) => $query->whereNull('face_descriptors'))
            ->orderBy('name')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'name', 'nis', 'classroom_id', 'avatar_url', 'user_id', 'face_descriptors', 'face_registered_by']);
    }

    /**
     * Registered vs total for the current class filter, as the progress line.
     *
     * @return array{registered: int, total: int}
     */
    #[Computed]
    public function progress(): array
    {
        $operator = $this->operator;

        if ($operator === null) {
            return ['registered' => 0, 'total' => 0];
        }

        return [
            'registered' => $this->scopedStudents($operator)->whereNotNull('face_descriptors')->count(),
            'total' => $this->scopedStudents($operator)->count(),
        ];
    }

    #[Computed]
    public function selectedStudent(): ?Student
    {
        $operator = $this->operator;

        if ($operator === null || $this->selectedStudentId === null) {
            return null;
        }

        return Student::query()->faceRegistrableBy($operator)->with(['classroom:id,name', 'faceRegisteredBy:id,name'])->find($this->selectedStudentId);
    }

    /**
     * Unlock a kiosk device session with a staff member's own credentials.
     */
    public function unlock(): void
    {
        $this->validate(
            ['unlockEmail' => ['required', 'email'], 'unlockPassword' => ['required', 'string']],
            [],
            ['unlockEmail' => __('Email'), 'unlockPassword' => __('Password')],
        );

        $throttleKey = 'face-registration-unlock:'.auth()->id().'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::UNLOCK_ATTEMPTS)) {
            $this->addError('unlockEmail', __('Terlalu banyak percobaan. Coba lagi dalam :seconds detik.', ['seconds' => RateLimiter::availableIn($throttleKey)]));

            return;
        }

        $user = User::query()->where('email', Str::lower(trim($this->unlockEmail)))->first();

        if ($user === null || ! Hash::check($this->unlockPassword, $user->password) || ! $user->isActive()) {
            RateLimiter::hit($throttleKey);
            $this->addError('unlockEmail', __('Email atau password salah.'));
            $this->unlockPassword = '';

            return;
        }

        if (! $user->canRegisterStudentFaces()) {
            RateLimiter::hit($throttleKey);
            $this->addError('unlockEmail', __('Akun ini tidak memiliki izin mendaftarkan wajah siswa.'));
            $this->unlockPassword = '';

            return;
        }

        RateLimiter::clear($throttleKey);

        $this->operatorId = $user->id;
        $this->unlockedUntil = now()->addMinutes(self::IDLE_MINUTES)->timestamp;
        $this->reset('unlockEmail', 'unlockPassword', 'selectedStudentId');
        unset($this->operator, $this->classrooms);
        $this->normaliseClassroom();
    }

    /**
     * End a credential unlock. A signed-in staff operator has nothing to lock.
     */
    public function lock(): void
    {
        if ($this->unlockedUntil === null) {
            return;
        }

        $this->forgetPendingCapture();
        $this->reset('operatorId', 'unlockedUntil', 'selectedStudentId', 'search');
        unset($this->operator);
    }

    /**
     * Polled while unlocked so an abandoned tablet locks itself on screen,
     * not only on the next action.
     */
    public function checkSession(): void
    {
        if ($this->unlockedUntil !== null && $this->operator === null) {
            $this->lock();
        }
    }

    public function selectStudent(int $studentId): void
    {
        $operator = $this->requireOperator();

        if ($operator === null) {
            return;
        }

        $this->forgetPendingCapture();
        $this->selectedStudentId = $this->scopedStudents($operator)->findOrFail($studentId)->id;
        unset($this->selectedStudent);
    }

    public function clearSelection(): void
    {
        $this->forgetPendingCapture();
        $this->selectedStudentId = null;
        unset($this->selectedStudent);
    }

    public function updatedClassroomId(): void
    {
        $this->forgetPendingCapture();
        $this->normaliseClassroom();
        $this->selectedStudentId = null;
    }

    /**
     * Whether the student the held-back capture ran into is one this operator
     * may register, so the dialog can offer to fix that record first.
     */
    public function canReRegisterConflictOwner(): bool
    {
        $operator = $this->operator;
        $ownerId = $this->faceConflict['owner_id'] ?? null;

        return $operator !== null && $ownerId !== null && $this->scopedStudents($operator)->whereKey($ownerId)->exists();
    }

    /**
     * Switch to the other student so their (wrong) face can be re-taken with
     * them in front of the camera.
     */
    public function reRegisterConflictOwner(): void
    {
        $ownerId = $this->faceConflict['owner_id'] ?? null;

        if ($ownerId === null || ! $this->canReRegisterConflictOwner()) {
            return;
        }

        $this->selectStudent($ownerId);
    }

    /**
     * Store the template captured for the selected student. Called by
     * resources/js/face-onboarding.js with one 128-float sample and the frame
     * it came from, which becomes the profile photo.
     *
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    public function storeFaceDescriptors(array $descriptors, ?string $snapshot = null): void
    {
        $this->forgetPendingCapture();
        $this->registerFace($descriptors, $snapshot);
    }

    protected function faceCaptureStudent(): ?Student
    {
        return $this->selectedStudent;
    }

    /**
     * @param  array<int, array<int, float|int>>  $descriptors
     */
    protected function registerFace(array $descriptors, ?string $snapshot, ?int $confirmedOwnerId = null): void
    {
        $operator = $this->requireOperator();
        $student = $this->selectedStudent;

        if ($operator === null || $student === null) {
            $this->dispatch('face-capture-rejected');

            return;
        }

        if (! $this->attemptFaceRegistration($student, $operator, $descriptors, $snapshot, $confirmedOwnerId)) {
            return;
        }

        if ($this->unlockedUntil !== null) {
            $this->unlockedUntil = now()->addMinutes(self::IDLE_MINUTES)->timestamp;
        }

        $this->selectedStudentId = null;
        unset($this->selectedStudent, $this->students, $this->progress);

        $this->dispatch('swal', icon: 'success', title: __('Wajah :name berhasil didaftarkan.', ['name' => $student->name]));
    }

    /**
     * The operator, or null after telling the tablet its session ended.
     */
    private function requireOperator(): ?User
    {
        $operator = $this->operator;

        if ($operator === null) {
            $this->reset('operatorId', 'unlockedUntil', 'selectedStudentId');
            $this->dispatch('swal', icon: 'warning', title: __('Sesi petugas berakhir. Buka kunci lagi untuk melanjutkan.'));
        }

        return $operator;
    }

    /**
     * Drop a bookmarked class that does not exist or is outside the operator's
     * scope.
     */
    private function normaliseClassroom(): void
    {
        unset($this->classrooms);

        if ($this->classroomId !== null && $this->operator !== null && $this->classrooms->firstWhere('id', $this->classroomId) === null) {
            $this->classroomId = null;
        }
    }
}; ?>

<div class="flex min-h-screen flex-col">
    @assets
        @vite('resources/js/face-onboarding.js')
    @endassets

    <header class="flex flex-wrap items-center gap-4 border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
            <x-app-logo-icon class="h-10 w-10 fill-current text-primary-600" />
            <div class="flex flex-col">
                <span class="font-bold leading-tight text-gray-800">{{ __('Registrasi Wajah') }}</span>
                <span class="text-xs text-gray-500">
                    @if ($this->operator)
                        {{ __('Petugas: :name', ['name' => $this->operator->name]) }}
                    @else
                        {{ __('Terkunci') }}
                    @endif
                    · SMAN 5 Bekasi
                </span>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" title="{{ __('Layar penuh') }}"
                onclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50">
                <ion-icon name="expand-outline" class="text-xl"></ion-icon>
            </button>

            @if ($this->operator && $unlockedUntil !== null)
                <x-ui.button variant="secondary" icon="lock-closed-outline" wire:click="lock">
                    {{ __('Kunci') }}
                </x-ui.button>
            @endif

            @if ($this->canOpenKiosk())
                <x-ui.button variant="secondary" icon="scan-outline" :href="route('attendance.absensi.kiosk', array_filter(['kelas' => $classroomId]))">
                    {{ __('Mode Absensi') }}
                </x-ui.button>
            @else
                <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('dashboard')">
                    {{ __('Kembali') }}
                </x-ui.button>
            @endif
        </div>
    </header>

    @if (! $this->operator)
        <main class="flex flex-1 items-center justify-center p-4 sm:p-6">
            <form wire:submit="unlock" class="flex w-full max-w-md flex-col gap-5 rounded-xl bg-white p-6 drop-shadow-lg sm:p-8">
                <div class="flex flex-col items-center gap-3 text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                        <ion-icon name="lock-closed-outline" class="text-3xl"></ion-icon>
                    </span>
                    <h1 class="text-lg font-bold text-gray-800">{{ __('Mode registrasi wajah dikunci') }}</h1>
                    <p class="text-sm text-gray-500">{{ __('Masuk dengan akun guru, wali kelas, atau admin untuk mendaftarkan wajah siswa. Terkunci otomatis setelah :minutes menit tanpa aktivitas.', ['minutes' => 15]) }}</p>
                </div>

                <div class="flex flex-col">
                    <label for="unlock-email" class="mb-1 text-sm font-semibold text-gray-600">{{ __('Email') }}</label>
                    <input id="unlock-email" type="email" wire:model="unlockEmail" autocomplete="off"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('unlockEmail')
                        <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="unlock-password" class="mb-1 text-sm font-semibold text-gray-600">{{ __('Password') }}</label>
                    <input id="unlock-password" type="password" wire:model="unlockPassword" autocomplete="off"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('unlockPassword')
                        <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <x-ui.button variant="primary" type="submit" icon="lock-open-outline" class="w-full">
                    <span wire:loading.remove wire:target="unlock">{{ __('Buka Kunci') }}</span>
                    <span wire:loading wire:target="unlock">{{ __('Memeriksa…') }}</span>
                </x-ui.button>
            </form>
        </main>
    @else
        <main class="grid flex-1 grid-cols-1 gap-6 p-4 sm:p-6 lg:grid-cols-5" @if ($unlockedUntil !== null) wire:poll.60s="checkSession" @endif>
            <div class="flex flex-col gap-4 rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-3">
                @if ($student = $this->selectedStudent)
                    <div class="flex items-center gap-4">
                        <img class="h-14 w-14 rounded-2xl object-cover" src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-lg font-bold text-gray-800">{{ $student->name }}</span>
                            <span class="text-sm text-gray-500">{{ $student->nis }} · {{ $student->classroom?->name ?? __('Tanpa kelas') }}</span>
                        </div>
                        <x-ui.button variant="secondary" icon="close-outline" x-on:click="window.SmartsisFace.stop()" wire:click="clearSelection">
                            {{ __('Ganti Siswa') }}
                        </x-ui.button>
                    </div>

                    @if ($student->hasRegisteredFace())
                        <div class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-sm text-amber-700">
                            <ion-icon name="information-circle-outline" class="mt-0.5 shrink-0 text-lg"></ion-icon>
                            <span>
                                {{ __('Siswa ini sudah punya data wajah (:source). Mengambil foto baru akan mengganti data wajah dan foto profil yang lama.', [
                                    'source' => $student->hasStaffVerifiedFace()
                                        ? __('didaftarkan :name', ['name' => $student->faceRegisteredBy?->name ?? __('petugas')])
                                        : __('didaftarkan mandiri'),
                                ]) }}
                            </span>
                        </div>
                    @endif

                    <div wire:key="capture-{{ $student->id }}" wire:ignore x-data x-init="window.SmartsisFace.start($el, $wire)" class="flex flex-col">
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

                    @if ($faceConflict !== null)
                        <x-face-conflict-dialog :conflict="$faceConflict" :snapshot="$this->pendingSnapshot" :student-name="$student->name">
                            @if ($this->canReRegisterConflictOwner())
                                <x-ui.button variant="secondary" icon="refresh-outline" class="w-full" wire:click="reRegisterConflictOwner">
                                    {{ __('Daftarkan ulang wajah :name', ['name' => $faceConflict['name']]) }}
                                </x-ui.button>
                            @endif
                        </x-face-conflict-dialog>
                    @endif

                    <ul class="flex list-disc flex-col gap-1 pl-5 text-sm text-gray-600">
                        <li>{{ __('Pastikan yang berdiri di depan kamera benar siswa di atas — cocokkan dengan kartu pelajar bila perlu.') }}</li>
                        <li>{{ __('Wajah menghadap kamera, tanpa masker atau kacamata hitam, pencahayaan cukup.') }}</li>
                        <li>{{ __('Hanya satu wajah di dalam bingkai.') }}</li>
                    </ul>
                @else
                    <div class="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
                        <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                            <ion-icon name="person-add-outline" class="text-3xl"></ion-icon>
                        </span>
                        <p class="font-semibold text-gray-800">{{ __('Pilih siswa yang akan didaftarkan') }}</p>
                        <p class="max-w-sm text-sm text-gray-500">{{ __('Cari nama atau NIS di panel samping, lalu minta siswa tersebut berdiri di depan kamera.') }}</p>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-4 rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-gray-800">{{ __('Daftar Siswa') }}</span>
                    <span class="text-sm text-gray-500">
                        {{ __(':registered dari :total wajah terdaftar', ['registered' => $this->progress['registered'], 'total' => $this->progress['total']]) }}
                    </span>
                </div>

                <select wire:model.live="classroomId"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @if ($this->operator->registersFacesForEveryStudent())
                        <option value="">{{ __('Semua kelas') }}</option>
                    @else
                        <option value="">{{ __('Semua kelas binaan') }}</option>
                    @endif
                    @foreach ($this->classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>

                <div class="relative">
                    <ion-icon name="search-outline" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></ion-icon>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama, NIS, atau NISN…') }}"
                        class="w-full rounded-md border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                </div>

                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model.live="onlyMissing" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    {{ __('Hanya yang belum terdaftar') }}
                </label>

                <div class="-mx-2 flex max-h-[60vh] flex-col overflow-y-auto">
                    @forelse ($this->students as $item)
                        <button type="button" wire:key="student-{{ $item->id }}" wire:click="selectStudent({{ $item->id }})"
                            @class([
                                'flex items-center gap-3 rounded-lg px-2 py-2 text-left transition',
                                'bg-primary-50 ring-1 ring-primary-200' => $item->id === $selectedStudentId,
                                'hover:bg-gray-50' => $item->id !== $selectedStudentId,
                            ])>
                            <img class="h-10 w-10 shrink-0 rounded-xl object-cover" src="{{ $item->avatar_url ?? asset('assets/placeholder.png') }}" alt="" />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-sm font-semibold text-gray-800">{{ $item->name }}</span>
                                <span class="truncate text-xs text-gray-500">{{ $item->nis }} · {{ $item->classroom?->name ?? __('Tanpa kelas') }}</span>
                            </span>
                            @if (! $item->hasRegisteredFace())
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('Belum') }}</span>
                            @elseif ($item->hasStaffVerifiedFace())
                                <span class="shrink-0 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ __('Terverifikasi') }}</span>
                            @else
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('Mandiri') }}</span>
                            @endif
                        </button>
                    @empty
                        <p class="px-2 py-8 text-center text-sm text-gray-500">
                            {{ $onlyMissing ? __('Semua siswa di daftar ini sudah terdaftar.') : __('Tidak ada siswa yang cocok.') }}
                        </p>
                    @endforelse
                </div>
            </div>
        </main>
    @endif
</div>
