<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Absensi')] class extends Component {
    use WithPagination;

    /** Daily monitoring filters (staff view). */
    public string $date = '';

    public string $status = '';

    public string $search = '';

    public ?int $classroomId = null;

    /** Personal history filters (siswa / orang tua view). */
    public string $month = '';

    public ?int $childId = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->month = now()->format('Y-m');
        $this->childId = $this->children()->first()?->id;
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['date', 'status', 'search', 'classroomId', 'month', 'childId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Siswa and Orang Tua get the personal history view instead of the
     * school-wide daily monitor.
     */
    public function isPersonal(): bool
    {
        return in_array(auth()->user()->primaryRole(), [UserRole::Siswa, UserRole::OrangTua], true);
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageAttendance->value);
    }

    /**
     * @return array<int, AttendanceStatus>
     */
    public function statuses(): array
    {
        return AttendanceStatus::cases();
    }

    /**
     * The children selectable by an Orang Tua account (the personal view's
     * subject; a Siswa account always views itself).
     *
     * @return Collection<int, Student>
     */
    public function children(): Collection
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::OrangTua => $user->parentGuardian?->students()->orderBy('name')->get() ?? collect(),
            UserRole::Siswa => collect([$user->student])->filter(),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
    }

    /**
     * Daily monitor rows: every scoped student with their attendance record
     * (if any) on the selected date.
     *
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        $date = $this->selectedDate();

        return $this->scopedStudents()
            ->with(['classroom', 'attendances' => fn ($query) => $query->whereDate('date', $date->toDateString())])
            ->when($this->classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('nis', 'like', '%'.$this->search.'%'),
            ))
            ->when($this->status === 'none', fn (Builder $query) => $query->whereDoesntHave(
                'attendances',
                fn (Builder $inner) => $inner->whereDate('date', $date->toDateString()),
            ))
            ->when($this->status !== '' && $this->status !== 'none', fn (Builder $query) => $query->whereHas(
                'attendances',
                fn (Builder $inner) => $inner->whereDate('date', $date->toDateString())->where('status', $this->status),
            ))
            ->orderBy('name')
            ->paginate(10);
    }

    /**
     * Recorded/unrecorded counts per status for the selected date (F-11).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $studentIds = $this->scopedStudents()->pluck('id');

        $byStatus = Attendance::query()
            ->onDate($this->selectedDate())
            ->whereIn('student_id', $studentIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [];

        foreach (AttendanceStatus::cases() as $case) {
            $stats[$case->value] = (int) ($byStatus[$case->value] ?? 0);
        }

        $stats['none'] = max(0, $studentIds->count() - array_sum($stats));

        return $stats;
    }

    /**
     * Personal history rows for the selected month.
     *
     * @return LengthAwarePaginator<int, Attendance>
     */
    #[Computed]
    public function history(): LengthAwarePaginator
    {
        $month = rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m', $this->month), now(), false);
        $subject = $this->children()->firstWhere('id', $this->childId);

        return Attendance::query()
            ->where('student_id', $subject?->id ?? 0)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('date')
            ->paginate(10);
    }

    /**
     * Manually set a student's status on the selected date (izin/sakit/alpha
     * or a correction). Guru Piket / Super Admin only.
     */
    public function markStatus(int $studentId, string $status): void
    {
        abort_unless($this->canManage(), 403);

        $newStatus = AttendanceStatus::tryFrom($status);
        $date = $this->selectedDate();

        if ($newStatus === null || $date->isFuture()) {
            $this->dispatch('swal', icon: 'error', title: __('Status atau tanggal tidak valid.'));

            return;
        }

        $student = Student::query()->findOrFail($studentId);

        app(RecordAttendance::class)->markStatus($student, $newStatus, auth()->user(), $date);

        unset($this->students, $this->stats);

        $this->dispatch('swal', icon: 'success', title: __(':name ditandai :status.', [
            'name' => $student->name,
            'status' => $newStatus->label(),
        ]));
    }

    /**
     * Mark every student without a record on the selected date as Alpha.
     */
    public function markAbsentees(): void
    {
        abort_unless($this->canManage(), 403);

        $date = $this->selectedDate();

        if ($date->isFuture()) {
            $this->dispatch('swal', icon: 'error', title: __('Tanggal belum berjalan.'));

            return;
        }

        $marked = app(RecordAttendance::class)->markAbsentees(auth()->user(), $date);

        unset($this->students, $this->stats);

        $this->dispatch('swal', icon: 'success', title: __(':count siswa ditandai Alpha.', ['count' => $marked]));
    }

    private function selectedDate(): CarbonInterface
    {
        return rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay(), now()->startOfDay(), false);
    }

    /**
     * Students scoped to what the signed-in role may monitor: wali kelas see
     * their homeroom, everyone else sees all (siswa/ortu use the history view).
     *
     * @return Builder<Student>
     */
    private function scopedStudents(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::WaliKelas => Student::query()->whereIn(
                'classroom_id',
                $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
            ),
            default => Student::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Absensi')"
        :subtitle="$this->isPersonal() ? __('Riwayat kehadiran.') : __('Monitoring kehadiran siswa harian.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="stats-chart-outline" :href="route('attendance.absensi.recap')" wire:navigate>
                {{ __('Rekap') }}
            </x-ui.button>
            @if ($this->canManage())
                <x-ui.button variant="secondary" icon="settings-outline" :href="route('attendance.absensi.settings')" wire:navigate>
                    {{ __('Pengaturan') }}
                </x-ui.button>
                <x-ui.button variant="primary" icon="scan-outline" :href="route('attendance.absensi.scan')" wire:navigate>
                    {{ __('Scan Absensi') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($this->isPersonal())
        {{-- ============ Personal history (Siswa / Orang Tua) ============ --}}
        <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                @if ($this->children()->count() > 1)
                    <select wire:model.live="childId"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @foreach ($this->children() as $child)
                            <option value="{{ $child->id }}">{{ $child->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="month" wire:model.live="month"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>

            <div class="rounded-2xl border overflow-auto">
                <table class="border-collapse min-w-full leading-normal">
                    <thead>
                        <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                            <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                            <th class="py-3 px-4">{{ __('Masuk') }}</th>
                            <th class="py-3 px-4">{{ __('Pulang') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Keterangan') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-gray-700">
                        @forelse ($this->history as $attendance)
                            <tr class="border-b last:border-0">
                                <td class="py-3 px-4">{{ $attendance->date->translatedFormat('d M Y') }}</td>
                                <td class="py-3 px-4 tabular-nums">{{ $attendance->checked_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-3 px-4 tabular-nums">{{ $attendance->checked_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-3 px-4">
                                    <x-attendance.status-badge :status="$attendance->status" />
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-500">{{ $attendance->note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-5 text-center text-gray-500">{{ __('Belum ada catatan kehadiran pada bulan ini.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->history->links() }}
            </div>
        </div>
    @else
        {{-- ============ Daily monitor (staff) ============ --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($this->statuses() as $case)
                <div class="rounded-xl bg-white p-4 drop-shadow-lg">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $case->label() }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-800">{{ $this->stats[$case->value] }}</p>
                </div>
            @endforeach
            <div class="rounded-xl bg-white p-4 drop-shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Belum Absen') }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-800">{{ $this->stats['none'] }}</p>
            </div>
        </div>

        <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <input type="date" wire:model.live="date"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <select wire:model.live="classroomId"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Kelas') }}</option>
                    @foreach ($this->classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach ($this->statuses() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                    <option value="none">{{ __('Belum Absen') }}</option>
                </select>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Cari nama / NIS…') }}"
                    class="w-full max-w-xs rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />

                @if ($this->canManage())
                    <button type="button" x-data
                        @click="confirmDelete(() => $wire.markAbsentees(), {
                            title: @js(__('Tandai Alpha?')),
                            text: @js(__('Semua siswa yang belum tercatat pada tanggal terpilih akan ditandai Alpha dan poinnya dikurangi otomatis.')),
                            confirmButtonText: @js(__('Ya, tandai')),
                        })"
                        class="ml-auto inline-flex items-center gap-2 rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                        <ion-icon name="person-remove-outline" class="text-lg"></ion-icon>
                        {{ __('Tandai Alpha') }}
                    </button>
                @endif
            </div>

            <div class="rounded-2xl border overflow-auto">
                <table class="border-collapse min-w-full leading-normal">
                    <thead>
                        <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                            <th class="py-3 px-4">No</th>
                            <th class="py-3 px-4">{{ __('Siswa') }}</th>
                            <th class="py-3 px-4">{{ __('Kelas') }}</th>
                            <th class="py-3 px-4">{{ __('Masuk') }}</th>
                            <th class="py-3 px-4">{{ __('Pulang') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            @if ($this->canManage())
                                <th class="py-3 px-4 text-center">{{ __('Ubah Status') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white text-gray-700">
                        @forelse ($this->students as $key => $student)
                            @php($attendance = $student->attendances->first())
                            <tr class="border-b last:border-0">
                                <td class="py-3 px-4">{{ $key + $this->students->firstItem() }}</td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <img class="h-9 w-9 rounded-full object-cover"
                                            src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}"
                                            alt="{{ $student->name }}" />
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ $student->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $student->nis }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">{{ $student->classroom?->name ?? '—' }}</td>
                                <td class="py-3 px-4 tabular-nums">{{ $attendance?->checked_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-3 px-4 tabular-nums">{{ $attendance?->checked_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="py-3 px-4">
                                    @if ($attendance !== null)
                                        <x-attendance.status-badge :status="$attendance->status" />
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                            {{ __('Belum Absen') }}
                                        </span>
                                    @endif
                                </td>
                                @if ($this->canManage())
                                    <td class="py-3 px-4 text-center">
                                        <select wire:change="markStatus({{ $student->id }}, $event.target.value)"
                                            class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                            <option value="" @selected($attendance === null)>{{ __('Pilih…') }}</option>
                                            @foreach ($this->statuses() as $case)
                                                <option value="{{ $case->value }}" @selected($attendance?->status === $case)>{{ $case->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $this->canManage() ? 7 : 6 }}" class="p-5 text-center text-gray-500">{{ __('Tidak ada siswa yang cocok dengan filter.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->students->links() }}
            </div>
        </div>
    @endif
</div>
