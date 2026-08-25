<?php

use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PointApprovalStatus;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Enums\WarningLevel;
use App\Enums\WarningStatus;
use App\Models\AcademicYear;
use App\Models\Achievement;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Permit;
use App\Models\PointLog;
use App\Models\PointSetting;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Violation;
use App\Models\WarningLetter;
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
     * @return array<int, array{label: string, value: int|string, icon: string, detail?: array<string, int>}>
     */
    #[Computed]
    public function stats(): array
    {
        $pendingPermits = Permit::where('status', PermitStatus::Pending)->count();
        $pendingViolations = Violation::where('status', PointApprovalStatus::Pending)->count();
        $pendingAchievements = Achievement::where('status', PointApprovalStatus::Pending)->count();

        return match (true) {
            $this->isLeadership() => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Guru', 'value' => Teacher::count(), 'icon' => 'academic-cap'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
                ['label' => 'Total Orang Tua', 'value' => ParentGuardian::count(), 'icon' => 'user-group'],
            ],
            $this->role() === UserRole::GuruBk => [
                [
                    'label' => 'Perlu Konseling',
                    'value' => $this->warningStudents->filter(fn ($s) => collect($s->recommendations)->contains('action', 'konseling'))->count(),
                    'icon' => 'chat-bubble-left-right',
                ],
                [
                    'label' => 'Perlu Pembinaan',
                    'value' => $this->warningStudents->filter(fn ($s) => collect($s->recommendations)->contains('action', 'pembinaan'))->count(),
                    'icon' => 'exclamation-triangle',
                ],
                [
                    'label' => 'Pemanggilan Ortu',
                    'value' => $this->warningStudents->filter(fn ($s) => collect($s->recommendations)->contains('action', 'sp_ortu'))->count(),
                    'icon' => 'user-minus',
                ],
                [
                    'label' => 'Perlu Persetujuan',
                    'value' => $pendingPermits + $pendingViolations + $pendingAchievements,
                    'icon' => 'clipboard-document-check',
                    'detail' => [
                        'Izin' => $pendingPermits,
                        'Langgar' => $pendingViolations,
                        'Prestasi' => $pendingAchievements,
                    ],
                ],
            ],
            $this->role() === UserRole::WaliKelas => $this->waliKelasStats(),
            in_array($this->role(), [UserRole::GuruPiket, UserRole::GuruMapel], true) => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
            ],
            default => [
                ['label' => 'Total Siswa', 'value' => Student::count(), 'icon' => 'users'],
                ['label' => 'Total Kelas', 'value' => Classroom::count(), 'icon' => 'building-library'],
            ],
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
     * Siswa yang memerlukan tindakan bimbingan konseling / pembinaan (Guru BK).
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function warningStudents(): Collection
    {
        return Student::with('classroom')
            ->withCount(['attendances as alpha_count' => function ($query) {
                $query->where('status', AttendanceStatus::Alpha);
            }])
            ->where(function ($query) {
                $query->where('current_point', '<', 70)
                      ->orWhereHas('attendances', function ($q) {
                          $q->where('status', AttendanceStatus::Alpha);
                      }, '>', 5);
            })
            ->get()
            ->map(function ($student) {
                $recommendations = [];

                if ($student->alpha_count > 5) {
                    $recommendations[] = [
                        'label' => 'Konseling',
                        'color' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'action' => 'konseling',
                    ];
                }

                if ($student->current_point < 70 && $student->current_point >= 50) {
                    $recommendations[] = [
                        'label' => 'Pembinaan',
                        'color' => 'bg-amber-100 text-amber-700 border-amber-200',
                        'action' => 'pembinaan',
                    ];
                }

                if ($student->current_point < 50) {
                    $recommendations[] = [
                        'label' => 'Pemanggilan Ortu',
                        'color' => 'bg-red-100 text-red-700 border-red-200',
                        'action' => 'sp_ortu',
                    ];
                }

                $student->recommendations = $recommendations;
                return $student;
            });
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
     * Daily present count (hadir + terlambat) for each day of the current
     * month up to today — the "jumlah absen harian" trend chart.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function attendanceTrend(): array
    {
        $start = now()->startOfMonth();
        $today = now()->startOfDay();
        $studentIds = $this->scopedStudentIds();

        $counts = Attendance::query()
            ->whereIn('status', [AttendanceStatus::Hadir, AttendanceStatus::Terlambat])
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->selectRaw('date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = [];

        for ($date = $start->copy(); $date->lte($today); $date = $date->addDay()) {
            $days[] = [
                'label' => $date->format('j'),
                'value' => (int) ($counts[$date->toDateString()] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Attendance status mix (hadir/terlambat/izin/sakit/alpha) for the
     * current month.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function attendanceStatusBreakdown(): array
    {
        $studentIds = $this->scopedStudentIds();

        $counts = Attendance::query()
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $colors = [
            AttendanceStatus::Hadir->value => 'bg-green-500',
            AttendanceStatus::Terlambat->value => 'bg-amber-500',
            AttendanceStatus::Izin->value => 'bg-blue-500',
            AttendanceStatus::Sakit->value => 'bg-purple-500',
            AttendanceStatus::Alpha->value => 'bg-red-500',
        ];

        return collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status) => [
                'label' => $status->label(),
                'value' => (int) ($counts[$status->value] ?? 0),
                'color' => $colors[$status->value],
            ])
            ->all();
    }

    /**
     * The 5 most-recorded violation categories (approved) this month.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function violationBreakdown(): array
    {
        $studentIds = $this->scopedStudentIds();

        return Violation::query()
            ->where('violations.status', PointApprovalStatus::Approved)
            ->whereBetween('occurred_on', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('violations.student_id', $studentIds))
            ->join('point_rules', 'point_rules.id', '=', 'violations.point_rule_id')
            ->selectRaw('point_rules.name as label, COUNT(*) as value')
            ->groupBy('point_rules.name')
            ->orderByDesc('value')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value, 'color' => 'bg-red-400'])
            ->all();
    }

    /**
     * Approved achievements this month, grouped by level.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function achievementBreakdown(): array
    {
        $studentIds = $this->scopedStudentIds();

        return Achievement::query()
            ->where('status', PointApprovalStatus::Approved)
            ->whereBetween('achieved_on', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->whereNotNull('level')
            ->selectRaw('level as label, COUNT(*) as value')
            ->groupBy('level')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value, 'color' => 'bg-green-400'])
            ->all();
    }

    /**
     * Issued warning letters by level.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function warningBreakdown(): array
    {
        $studentIds = $this->scopedStudentIds();

        $counts = WarningLetter::query()
            ->where('status', WarningStatus::Approved)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        $colors = [
            WarningLevel::Sp1->value => 'bg-amber-500',
            WarningLevel::Sp2->value => 'bg-orange-500',
            WarningLevel::Sp3->value => 'bg-red-500',
        ];

        return collect(WarningLevel::cases())
            ->map(fn (WarningLevel $level) => [
                'label' => $level->label(),
                'value' => (int) ($counts[$level->value] ?? 0),
                'color' => $colors[$level->value],
            ])
            ->all();
    }

    /**
     * Permit requests this month by decision status.
     *
     * @return array<int, array{label: string, value: int, color: string}>
     */
    #[Computed]
    public function permitBreakdown(): array
    {
        $studentIds = $this->scopedStudentIds();

        $counts = Permit::query()
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $colors = [
            PermitStatus::Pending->value => 'bg-amber-500',
            PermitStatus::Approved->value => 'bg-green-500',
            PermitStatus::Rejected->value => 'bg-red-500',
        ];

        return collect(PermitStatus::cases())
            ->map(fn (PermitStatus $status) => [
                'label' => $status->label(),
                'value' => (int) ($counts[$status->value] ?? 0),
                'color' => $colors[$status->value],
            ])
            ->all();
    }

    /**
     * Discipline-point additions per month over the last 6 months.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function pointAdditionsTrend(): array
    {
        return $this->pointTypeTrend(PointType::Addition);
    }

    /**
     * Discipline-point deductions per month over the last 6 months.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function pointDeductionsTrend(): array
    {
        return $this->pointTypeTrend(PointType::Deduction);
    }

    /**
     * Monthly point-log totals of the given type.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function pointTypeTrend(PointType $type): array
    {
        $studentIds = $this->scopedStudentIds();

        if ($studentIds !== null && $studentIds->isEmpty()) {
            return [];
        }

        $start = now()->copy()->subMonths(5)->startOfMonth();

        $logs = PointLog::query()
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->where('type', $type)
            ->where('created_at', '>=', $start)
            ->get(['delta', 'created_at']);

        return collect(range(0, 5))
            ->map(function (int $offset) use ($logs, $start) {
                $month = $start->copy()->addMonths($offset);

                return [
                    'label' => $month->translatedFormat('M'),
                    'value' => (int) abs($logs->filter(fn ($log) => $log->created_at->isSameMonth($month))->sum('delta')),
                ];
            })
            ->all();
    }

    /**
     * Student ids the signed-in role's charts should be scoped to.
     *
     * @return \Illuminate\Support\Collection<int, int>|null
     */
    private function scopedStudentIds(): ?\Illuminate\Support\Collection
    {
        return match ($this->role()) {
            UserRole::WaliKelas => $this->homeroomClass?->students()->pluck('id') ?? collect(),
            UserRole::Siswa => $this->student ? collect([$this->student->id]) : collect(),
            UserRole::OrangTua => $this->children->pluck('id'),
            default => null,
        };
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
                <div class="flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <flux:text class="text-sm font-medium">{{ $stat['label'] ?? '' }}</flux:text>
                            <flux:heading size="xl" class="mt-1 truncate">{{ $stat['value'] ?? '' }}</flux:heading>
                        </div>
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                            <flux:icon :name="$stat['icon']" class="size-6" />
                        </div>
                    </div>

                    {{-- Tampilkan detail breakdown jika ada --}}
                    @if (isset($stat['detail']))
                        <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-2.5 text-xs text-zinc-500">
                            <span>Izin: <strong class="text-zinc-800 font-semibold">{{ $stat['detail']['Izin'] }}</strong></span>
                            <span class="text-zinc-300">•</span>
                            <span>Langgar: <strong class="text-zinc-800 font-semibold">{{ $stat['detail']['Langgar'] }}</strong></span>
                            <span class="text-zinc-300">•</span>
                            <span>Prestasi: <strong class="text-zinc-800 font-semibold">{{ $stat['detail']['Prestasi'] }}</strong></span>
                        </div>
                    @endif
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
    @elseif ($role === UserRole::GuruBk)
        <div class="rounded-xl border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 p-5">
                <flux:heading size="lg">{{ __('Siswa Perlu Tindakan / Pembinaan') }}</flux:heading>
            </div>
            @include('partials.dashboard-bk-table', ['warningStudents' => $this->warningStudents])
        </div>
    @elseif ($this->isLeadership())
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

    {{-- Chart summaries --}}
    @canany([
        Permission::ViewAttendance->value,
        Permission::ViewPoint->value,
        Permission::ViewViolation->value,
        Permission::ViewAchievement->value,
        Permission::ViewWarning->value,
        Permission::ViewPermit->value,
    ])
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @can(Permission::ViewAttendance->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Tren Kehadiran Bulan Ini') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Jumlah hadir & terlambat per hari.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.bar :data="$this->attendanceTrend" :label-every="5" />
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Distribusi Status Kehadiran') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Komposisi status bulan ini.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.distribution :data="$this->attendanceStatusBreakdown" />
                    </div>
                </div>
            @endcan

            @can(Permission::ViewPoint->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Penambahan Poin') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('6 bulan terakhir.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.bar :data="$this->pointAdditionsTrend" color="bg-green-500" />
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Pengurangan Poin') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('6 bulan terakhir.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.bar :data="$this->pointDeductionsTrend" color="bg-red-500" />
                    </div>
                </div>
            @endcan

            @can(Permission::ViewViolation->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Pelanggaran Terbanyak') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('5 jenis teratas bulan ini.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.ranked-list :data="$this->violationBreakdown" color="bg-red-400" :empty="__('Belum ada pelanggaran bulan ini.')" />
                    </div>
                </div>
            @endcan

            @can(Permission::ViewAchievement->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Prestasi per Tingkat') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Prestasi disetujui bulan ini.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.ranked-list :data="$this->achievementBreakdown" color="bg-green-400" :empty="__('Belum ada prestasi bulan ini.')" />
                    </div>
                </div>
            @endcan

            @can(Permission::ViewWarning->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Surat Peringatan') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Surat diterbitkan per level.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.distribution :data="$this->warningBreakdown" />
                    </div>
                </div>
            @endcan

            @can(Permission::ViewPermit->value)
                <div class="rounded-xl border border-zinc-200 bg-white p-6">
                    <flux:heading size="lg">{{ __('Status Perizinan') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Pengajuan bulan ini.') }}</flux:text>
                    <div class="mt-4">
                        <x-charts.distribution :data="$this->permitBreakdown" />
                    </div>
                </div>
            @endcan
        </div>
    @endcanany
</div>