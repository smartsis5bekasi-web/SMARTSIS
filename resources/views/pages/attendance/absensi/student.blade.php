<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Exports\StudentAttendanceExport;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Detail Absensi Siswa')] class extends Component {
    use WithPagination;

    public Student $student;

    /** Optional period (Y-m-d); blank means every record. */
    public string $from = '';

    public string $to = '';

    public string $status = '';

    public function mount(Student $student): void
    {
        abort_unless($this->canView($student), 403);

        $this->student = $student->load('classroom');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['from', 'to', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function isPersonal(): bool
    {
        return in_array(auth()->user()->primaryRole(), [UserRole::Siswa, UserRole::OrangTua], true);
    }

    /**
     * @return array<int, AttendanceStatus>
     */
    public function statuses(): array
    {
        return AttendanceStatus::cases();
    }

    #[Computed]
    public function setting(): AttendanceSetting
    {
        return AttendanceSetting::current();
    }

    /**
     * Totals per status in the selected period (ignores the status filter so
     * the cards always show the full picture).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function summary(): array
    {
        $byStatus = $this->periodQuery()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = ['total' => (int) $byStatus->sum()];

        foreach (AttendanceStatus::cases() as $case) {
            $summary[$case->value] = (int) ($byStatus[$case->value] ?? 0);
        }

        return $summary;
    }

    /**
     * Every attendance record of the student, newest first.
     *
     * @return LengthAwarePaginator<int, Attendance>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return $this->periodQuery()
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function resetFilters(): void
    {
        $this->reset(['from', 'to', 'status']);
        $this->resetPage();
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_if($this->isPersonal(), 403);

        return Excel::download(
            new StudentAttendanceExport($this->student, $this->parseDate($this->from), $this->parseDate($this->to), $this->status),
            'absensi-'.$this->student->nis.'-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @return HasMany<Attendance, Student>
     */
    private function periodQuery(): HasMany
    {
        $from = $this->parseDate($this->from);
        $to = $this->parseDate($this->to);

        return $this->student->attendances()
            ->when($from !== null, fn ($query) => $query->whereDate('date', '>=', $from->toDateString()))
            ->when($to !== null, fn ($query) => $query->whereDate('date', '<=', $to->toDateString()));
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        return rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m-d', $value)->startOfDay(), null, false);
    }

    /**
     * Enforce the per-role scoping rules (PRD section 3.1): a siswa sees
     * itself, an orang tua their children, a wali kelas their homeroom.
     */
    private function canView(Student $student): bool
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => $user->student?->id === $student->id,
            UserRole::OrangTua => (bool) $user->parentGuardian?->students()->whereKey($student->id)->exists(),
            UserRole::WaliKelas => $student->classroom_id !== null
                && (bool) $user->teacher?->homeroomClassrooms()->whereKey($student->classroom_id)->exists(),
            default => true,
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Detail Absensi Siswa')" :subtitle="__('Seluruh catatan absensi masuk & pulang siswa.')">
        <x-slot:actions>
            @unless ($this->isPersonal())
                <x-ui.button variant="secondary" icon="download-outline" wire:click="exportExcel">
                    {{ __('Export Excel') }}
                </x-ui.button>
            @endunless
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi.recap')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Identitas siswa --}}
    <div class="flex flex-wrap items-center gap-4 rounded-xl bg-white p-6 drop-shadow-lg">
        <img class="h-16 w-16 rounded-2xl object-cover"
            src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
        <div class="flex min-w-0 flex-col">
            <p class="text-lg font-bold text-gray-800">{{ $student->name }}</p>
            <p class="text-sm text-gray-500">
                NIS {{ $student->nis }}
                <span class="text-gray-300">·</span>
                {{ $student->classroom?->name ?? '—' }}
            </p>
        </div>
        <div class="ml-auto text-right">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Total Catatan') }}</p>
            <p class="text-2xl font-bold tabular-nums text-gray-800">{{ $this->summary['total'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($this->statuses() as $case)
            <button type="button" wire:key="summary-{{ $case->value }}"
                wire:click="$set('status', '{{ $status === $case->value ? '' : $case->value }}')"
                @class([
                    'rounded-xl bg-white p-4 text-left drop-shadow-lg transition',
                    'ring-2 ring-primary-500' => $status === $case->value,
                ])>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $case->label() }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-800">{{ $this->summary[$case->value] }}</p>
            </button>
        @endforeach
    </div>

    <div class="flex-col rounded-xl bg-white p-6 drop-shadow-lg">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="from" aria-label="{{ __('Dari tanggal') }}"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <span class="text-sm text-gray-400">{{ __('s/d') }}</span>
                <input type="date" wire:model.live="to" aria-label="{{ __('Sampai tanggal') }}"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>
            <select wire:model.live="status"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua Status') }}</option>
                @foreach ($this->statuses() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
            @if ($from !== '' || $to !== '' || $status !== '')
                <button type="button" wire:click="resetFilters" class="text-sm font-semibold text-red-600 hover:underline">
                    {{ __('Reset filter') }}
                </button>
            @endif
        </div>

        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Jam Datang') }}</th>
                        <th class="py-3 px-4">{{ __('Jam Pulang') }}</th>
                        <th class="py-3 px-4">{{ __('Keterangan') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700 whitespace-nowrap">
                    @forelse ($this->records as $key => $attendance)
                        <tr wire:key="attendance-{{ $attendance->id }}" class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->records->firstItem() }}</td>
                            <td class="py-3 px-4">{{ $attendance->date->translatedFormat('l, d/m/Y') }}</td>
                            <td class="py-3 px-4">
                                <div class="flex flex-col items-start gap-1">
                                    <x-attendance.status-badge :status="$attendance->status" />
                                    <x-attendance.confirmation-hint :attendance="$attendance" :setting="$this->setting" />
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($attendance->checked_in_at)
                                    <span class="tabular-nums">{{ $attendance->checked_in_at->format('H:i:s') }} WIB</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($attendance->checked_out_at)
                                    <span class="tabular-nums">{{ $attendance->checked_out_at->format('H:i:s') }} WIB</span>
                                @elseif ($attendance->needsCheckOut())
                                    <span class="text-gray-500">{{ __('Belum Absen Pulang') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('Tidak diperlukan') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500 whitespace-normal">{{ $attendance->note ?? $attendance->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-5 text-center text-gray-500">{{ __('Belum ada catatan absensi.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->records->links() }}
        </div>
    </div>
</div>
