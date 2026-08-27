<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
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

new #[Title('Rekap Absensi')] class extends Component {
    use WithPagination;

    public string $month = '';

    public string $search = '';

    public ?int $classroomId = null;
    

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['month', 'search', 'classroomId'], true)) {
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
     * Monthly per-student totals for every attendance status (rekap absensi).
     *
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function recap(): LengthAwarePaginator
    {
        $month = rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m', $this->month), now(), false);
        $range = [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];

        $counts = [];

        foreach (AttendanceStatus::cases() as $case) {
            $counts['attendances as '.$case->value.'_count'] = fn (Builder $query) => $query
                ->whereBetween('date', $range)
                ->where('status', $case->value);
        }

        return $this->scopedStudents()
            ->with('classroom')
            ->withCount($counts)
            ->when($this->classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('nis', 'like', '%'.$this->search.'%'),
            ))
            ->orderBy('name')
            ->paginate(10);
    }

    /**
     * Attendance rate: days present (hadir + terlambat) out of all recorded
     * days in the month. Null when the student has no record yet.
     */
    public function presenceRate(Student $student): ?int
    {
        $present = $student->hadir_count + $student->terlambat_count;
        $total = $present + $student->izin_count + $student->sakit_count + $student->alpha_count;

        return $total > 0 ? (int) round($present / $total * 100) : null;
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
            <x-ui.button variant="secondary" icon="list-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Monitoring') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input type="month" wire:model.live="month"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
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
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700">
                    @forelse ($this->recap as $key => $student)
                        @php($rate = $this->presenceRate($student))
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->recap->firstItem() }}</td>
                            <td class="py-3 px-4 font-medium">{{ $student->name }}</td>
                            <td class="py-3 px-4">{{ $student->classroom?->name ?? '—' }}</td>
                            @foreach ($this->statuses() as $case)
                                <td class="py-3 px-4 text-center tabular-nums">{{ $student->{$case->value.'_count'} }}</td>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($this->statuses()) }}" class="p-5 text-center text-gray-500">{{ __('Belum ada data siswa.') }}</td>
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
