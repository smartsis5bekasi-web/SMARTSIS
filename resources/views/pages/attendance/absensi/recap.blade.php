<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Exports\AttendanceRecapExport;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\AttendanceRecap;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Rekap Absensi')] class extends Component {
    use WithPagination;

    /** Period (Y-m-d), defaulting to the current month. */
    public string $from = '';

    public string $to = '';

    public string $search = '';

    public ?int $classroomId = null;

    /** "Minimal N kali" filter: the status to count, blank for no filter. */
    public string $thresholdStatus = '';

    public ?int $minCount = 3;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['from', 'to', 'search', 'classroomId', 'thresholdStatus', 'minCount'], true)) {
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

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
    }

    /**
     * Per-student totals for every attendance status in the period (rekap
     * absensi), narrowed by the active filters.
     *
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function recap(): LengthAwarePaginator
    {
        return $this->recapFilter()
            ->apply($this->scopedStudents())
            ->with('classroom')
            ->paginate(10);
    }

    public function presenceRate(Student $student): ?int
    {
        return AttendanceRecap::presenceRate($student);
    }

    /**
     * Shortcut chips for the most common "minimal N kali" questions.
     */
    public function applyPreset(string $status, int $minCount): void
    {
        $this->thresholdStatus = AttendanceStatus::tryFrom($status)?->value ?? '';
        $this->minCount = max(1, $minCount);
        $this->resetPage();
    }

    public function clearThreshold(): void
    {
        $this->thresholdStatus = '';
        $this->resetPage();
    }

    /**
     * Download the rekap with every filter the table is showing.
     */
    public function exportExcel(): BinaryFileResponse
    {
        abort_if($this->isPersonal(), 403);

        $filter = $this->recapFilter();

        return Excel::download(
            new AttendanceRecapExport($this->scopedStudents(), $filter),
            'rekap-absensi-'.$filter->from->format('Ymd').'-'.$filter->to->format('Ymd').'.xlsx',
        );
    }

    private function recapFilter(): AttendanceRecap
    {
        $from = $this->parseDate($this->from) ?? now()->startOfMonth();
        $to = $this->parseDate($this->to) ?? now()->endOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return new AttendanceRecap(
            from: $from,
            to: $to,
            classroomId: $this->isPersonal() ? null : $this->classroomId,
            search: $this->isPersonal() ? '' : $this->search,
            thresholdStatus: $this->isPersonal() ? null : AttendanceStatus::tryFrom($this->thresholdStatus),
            minCount: max(1, $this->minCount ?? 1),
        );
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        return rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m-d', $value)->startOfDay(), null, false);
    }

    /**
     * Students scoped to what the signed-in role may see.
     *
     * @return Builder<Student>
     */
    private function scopedStudents(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Student::query()->where('id', $user->student?->id ?? 0),
            UserRole::OrangTua => Student::query()->whereIn(
                'id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            UserRole::WaliKelas => Student::query()->whereIn(
                'classroom_id',
                $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
            ),
            default => Student::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Rekap Absensi')" :subtitle="__('Rekapitulasi kehadiran per siswa dalam satu bulan.')">
        <x-slot:actions>
            @unless ($this->isPersonal())
                <x-ui.button variant="secondary" icon="download-outline" wire:click="exportExcel">
                    {{ __('Export Excel') }}
                </x-ui.button>
            @endunless
            <x-ui.button variant="secondary" icon="list-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Monitoring') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="from" aria-label="{{ __('Dari tanggal') }}"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <span class="text-sm text-gray-400">{{ __('s/d') }}</span>
                <input type="date" wire:model.live="to" aria-label="{{ __('Sampai tanggal') }}"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>
            @unless ($this->isPersonal())
                <select wire:model.live="classroomId"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Kelas') }}</option>
                    @foreach ($this->classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Cari nama / NIS…') }}"
                    class="w-full max-w-xs rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            @endunless
        </div>

        @unless ($this->isPersonal())
            {{-- "Minimal N kali" filter, e.g. students late 3 times or more. --}}
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                <span class="text-sm font-semibold text-gray-600">{{ __('Filter jumlah:') }}</span>
                <select wire:model.live="thresholdStatus" aria-label="{{ __('Status yang dihitung') }}"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Tidak difilter') }}</option>
                    @foreach ($this->statuses() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                <span class="text-sm text-gray-600">{{ __('minimal') }}</span>
                <input type="number" min="1" wire:model.live.debounce.400ms="minCount" aria-label="{{ __('Jumlah minimal') }}"
                    class="w-20 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <span class="text-sm text-gray-600">{{ __('kali') }}</span>

                <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                    @foreach ([['terlambat', 2, 'Terlambat > 1x'], ['terlambat', 3, 'Terlambat ≥ 3x'], ['alpha', 3, 'Alpha ≥ 3x']] as [$presetStatus, $presetCount, $presetLabel])
                        <button type="button" wire:click="applyPreset('{{ $presetStatus }}', {{ $presetCount }})" wire:key="preset-{{ $presetStatus }}-{{ $presetCount }}"
                            @class([
                                'rounded-full border px-3 py-1 text-xs font-semibold transition',
                                'border-primary-600 bg-primary-600 text-white' => $thresholdStatus === $presetStatus && $minCount === $presetCount,
                                'border-gray-200 bg-white text-gray-600 hover:bg-gray-100' => ! ($thresholdStatus === $presetStatus && $minCount === $presetCount),
                            ])>
                            {{ $presetLabel }}
                        </button>
                    @endforeach
                    @if ($thresholdStatus !== '')
                        <button type="button" wire:click="clearThreshold" class="text-xs font-semibold text-red-600 hover:underline">
                            {{ __('Reset') }}
                        </button>
                    @endif
                </div>
            </div>
        @endunless

        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">{{ __('Siswa') }}</th>
                        <th class="py-3 px-4">{{ __('Kelas') }}</th>
                        @foreach ($this->statuses() as $case)
                            <th class="py-3 px-4 text-center">{{ $case->label() }}</th>
                        @endforeach
                        <th class="py-3 px-4 text-center">{{ __('Kehadiran') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700 whitespace-nowrap">
                    @forelse ($this->recap as $key => $student)
                        @php($rate = $this->presenceRate($student))
                        <tr wire:key="recap-{{ $student->id }}" class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->recap->firstItem() }}</td>
                            <td class="py-3 px-4">
                                <a href="{{ route('attendance.absensi.student', $student) }}" wire:navigate class="font-medium hover:text-primary-600 hover:underline">{{ $student->name }}</a>
                                <span class="block text-xs text-gray-400">{{ $student->nis }}</span>
                            </td>
                            <td class="py-3 px-4">{{ $student->classroom?->name ?? '—' }}</td>
                            @foreach ($this->statuses() as $case)
                                <td @class([
                                    'py-3 px-4 text-center tabular-nums',
                                    'bg-primary-50 font-bold text-primary-700' => $thresholdStatus === $case->value,
                                ])>{{ AttendanceRecap::count($student, $case) }}</td>
                            @endforeach
                            <td class="py-3 px-4 text-center">
                                @if ($rate === null)
                                    <span class="text-sm text-gray-400">—</span>
                                @else
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums',
                                        'bg-green-100 text-green-700' => $rate >= 90,
                                        'bg-amber-100 text-amber-700' => $rate >= 75 && $rate < 90,
                                        'bg-red-100 text-red-700' => $rate < 75,
                                    ])>{{ $rate }}%</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('attendance.absensi.student', $student) }}" wire:navigate
                                    class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700">
                                    <ion-icon name="eye-outline" class="text-base"></ion-icon>
                                    {{ __('Detail') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + count($this->statuses()) }}" class="p-5 text-center text-gray-500">
                                {{ $thresholdStatus !== '' ? __('Tidak ada siswa yang cocok dengan filter.') : __('Belum ada data siswa.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->recap->links() }}
        </div>
    </div>
</div>
