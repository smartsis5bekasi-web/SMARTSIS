<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\PointSetting;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    /**
     * The signed-in user's highest-priority role.
     */
    #[Computed]
    public function role(): ?UserRole
    {
        return auth()->user()->primaryRole();
    }

    /**
     * The currently active academic year, shown in the header.
     */
    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    /**
     * Summary cards tailored to the signed-in role.
     *
     * @return array<int, array{label: string, value: int|string, icon: string}>
     */
    #[Computed]
    public function stats(): array
    {
        return match (true) {
            $this->isLeadership() => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Guru', 'value' => Teacher::count(), 'icon' => 'academic-cap'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
                ['label' => 'Total Orang Tua', 'value' => ParentGuardian::count(), 'icon' => 'user-group'],
            ],
            $this->role() === UserRole::GuruBk => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
                ['label' => 'Rata-rata Poin', 'value' => (int) round((float) Student::avg('current_point')), 'icon' => 'chart-bar'],
                ['label' => 'Perlu Pembinaan', 'value' => Student::where('current_point', '<', 50)->count(), 'icon' => 'exclamation-triangle'],
            ],
            $this->role() === UserRole::WaliKelas => $this->waliKelasStats(),
            in_array($this->role(), [UserRole::GuruPiket, UserRole::GuruMapel], true) => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
            ],
            default => [],
        };
    }

    /**
     * The homeroom class led by the signed-in wali kelas, if any.
     */
    #[Computed]
    public function homeroomClass(): ?Classroom
    {
        return auth()->user()->teacher?->homeroomClassrooms()
            ->withCount('students')
            ->first();
    }

    /**
     * Students in the wali kelas' homeroom class, ordered by name.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function classStudents(): Collection
    {
        return $this->homeroomClass()
            ? $this->homeroomClass()->students()->orderBy('name')->get()
            : new Collection;
    }

    /**
     * The most recently enrolled students, for leadership and BK dashboards.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function recentStudents(): Collection
    {
        return Student::with('classroom')->latest()->take(8)->get();
    }

    /**
     * The signed-in student's own profile.
     */
    #[Computed]
    public function student(): ?Student
    {
        return auth()->user()->student?->load('classroom', 'major');
    }

    /**
     * The discipline-point overview for the signed-in student's card.
     *
     * @return array{current: int, target: int, min: int, progress: int, belowMin: bool}
     */
    #[Computed]
    public function pointSummary(): array
    {
        $setting = PointSetting::current();
        $current = $this->student?->current_point ?? 0;
        $target = max(1, $setting->target_point);

        return [
            'current' => $current,
            'target' => $setting->target_point,
            'min' => $setting->min_point,
            'progress' => (int) min(100, round($current / $target * 100)),
            'belowMin' => $current < $setting->min_point,
        ];
    }

    /**
     * The children linked to the signed-in parent.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function children(): Collection
    {
        return auth()->user()->parentGuardian?->students()->with('classroom')->get() ?? new Collection;
    }

    /**
     * Whether the role sees the school-wide leadership dashboard.
     */
    private function isLeadership(): bool
    {
        return in_array($this->role(), [
            UserRole::SuperAdmin,
            UserRole::KepalaSekolah,
            UserRole::WakasekKesiswaan,
        ], true);
    }

    /**
     * @return array<int, array{label: string, value: int|string, icon: string}>
     */
    private function waliKelasStats(): array
    {
        $class = $this->homeroomClass();
        $studentIds = $class ? $class->students()->pluck('id') : collect();

        return [
            ['label' => 'Kelas', 'value' => $class?->name ?? '—', 'icon' => 'building-library'],
            ['label' => 'Jumlah Siswa', 'value' => $class?->students_count ?? 0, 'icon' => 'users'],
            ['label' => 'Rata-rata Poin', 'value' => (int) round((float) Student::whereIn('id', $studentIds)->avg('current_point')), 'icon' => 'chart-bar'],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Selamat datang, :name', ['name' => auth()->user()->name]) }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($this->role)
                        <flux:badge color="violet" size="sm">{{ $this->role->label() }}</flux:badge>
                    @endif
                </flux:text>
            </div>

            @if ($this->activeYear)
                <flux:badge color="amber" size="lg" icon="calendar-days">
                    {{ __('Tahun Ajaran') }} {{ $this->activeYear->name }}
                </flux:badge>
            @endif
        </div>

        {{-- Stat cards --}}
        @if (count($this->stats) > 0)
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->stats as $stat)
                    <div class="rounded-xl border border-zinc-200 bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <flux:text class="text-sm">{{ $stat['label'] }}</flux:text>
                                <flux:heading size="xl" class="mt-1 truncate">{{ $stat['value'] }}</flux:heading>
                            </div>
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                <flux:icon :name="$stat['icon']" class="size-6" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Role-specific detail --}}
        @php($role = $this->role)

        @if ($role === UserRole::Siswa && $this->student)
            @php($student = $this->student)
            <div class="rounded-xl border border-zinc-200 bg-white p-6">
                <flux:heading size="lg">{{ __('Profil Siswa') }}</flux:heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <flux:text class="text-sm">NIS</flux:text>
                        <flux:heading class="mt-1">{{ $student->nis }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-sm">{{ __('Kelas') }}</flux:text>
                        <flux:heading class="mt-1">{{ $student->classroom?->name ?? '—' }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-sm">{{ __('Jurusan') }}</flux:text>
                        <flux:heading class="mt-1">{{ $student->major?->name ?? '—' }}</flux:heading>
                    </div>
                    <div>
                        <flux:text class="text-sm">{{ __('Poin Disiplin') }}</flux:text>
                        <flux:heading class="mt-1">
                            <flux:badge size="lg" :color="$student->current_point >= 75 ? 'green' : ($student->current_point >= 50 ? 'amber' : 'red')">
                                {{ $student->current_point }}
                            </flux:badge>
                        </flux:heading>
                    </div>
                </div>
            </div>

            {{-- Development Point overview --}}
            @php($point = $this->pointSummary)
            <div class="rounded-xl border border-zinc-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Development Point') }}</flux:heading>
                    @if ($point['belowMin'])
                        <flux:badge color="red" size="sm">{{ __('Di Bawah Minimum') }}</flux:badge>
                    @else
                        <flux:badge color="green" size="sm">{{ __('Aman') }}</flux:badge>
                    @endif
                </div>

                <div class="mt-4 flex items-end justify-between">
                    <flux:text class="text-sm">{{ __('Total Points') }}</flux:text>
                    <flux:heading class="tabular-nums">{{ $point['current'] }} <span class="text-sm font-normal text-zinc-400">{{ __('of') }} {{ $point['target'] }}</span></flux:heading>
                </div>
                <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $point['progress'] }}%"></div>
                </div>

                <div class="mt-4 flex justify-end">
                    <flux:link :href="route('attendance.points.show', $student)" wire:navigate class="text-sm font-medium">
                        {{ __('View More Detail') }} →
                    </flux:link>
                </div>
            </div>
        @elseif ($role === UserRole::OrangTua)
            <div class="rounded-xl border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 p-5">
                    <flux:heading size="lg">{{ __('Anak / Siswa Terkait') }}</flux:heading>
                </div>
                @include('partials.dashboard-student-table', ['students' => $this->children, 'empty' => __('Belum ada siswa yang terhubung.')])
            </div>
        @elseif ($role === UserRole::WaliKelas)
            <div class="rounded-xl border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 p-5">
                    <flux:heading size="lg">{{ __('Siswa Kelas :name', ['name' => $this->homeroomClass?->name ?? '—']) }}</flux:heading>
                </div>
                @include('partials.dashboard-student-table', ['students' => $this->classStudents, 'empty' => __('Belum ada siswa di kelas ini.')])
            </div>
        @elseif ($this->isLeadership() || $role === UserRole::GuruBk)
            <div class="rounded-xl border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 p-5">
                    <flux:heading size="lg">{{ __('Siswa Terbaru') }}</flux:heading>
                </div>
                @include('partials.dashboard-student-table', ['students' => $this->recentStudents, 'empty' => __('Belum ada data siswa.')])
            </div>
        @elseif (! $role)
            <div class="rounded-xl border border-zinc-200 bg-white p-6">
                <flux:heading size="lg">{{ __('Dashboard') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Akun Anda belum memiliki peran. Hubungi administrator sekolah.') }}</flux:text>
            </div>
        @endif
    </div>
