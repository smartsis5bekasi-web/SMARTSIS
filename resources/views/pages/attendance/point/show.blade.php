<?php

use App\Enums\PointType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\PointSetting;
use App\Models\Student;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Development Point')] class extends Component {
    public Student $student;

    public bool $showAllAdditions = false;

    public bool $showAllDeductions = false;

    public function mount(Student $student): void
    {
        abort_unless($this->canView($student), 403);

        $this->student = $student;
    }

    #[Computed]
    public function setting(): PointSetting
    {
        return PointSetting::current();
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    /**
     * Progress toward the configured target, capped at 100%.
     */
    #[Computed]
    public function progress(): int
    {
        $target = max(1, $this->setting->target_point);

        return (int) min(100, round($this->student->current_point / $target * 100));
    }

    /**
     * @return Collection<int, \App\Models\PointLog>
     */
    #[Computed]
    public function additions(): Collection
    {
        return $this->logsOfType(PointType::Addition, $this->showAllAdditions);
    }

    /**
     * @return Collection<int, \App\Models\PointLog>
     */
    #[Computed]
    public function deductions(): Collection
    {
        return $this->logsOfType(PointType::Deduction, $this->showAllDeductions);
    }

    /**
     * @return Collection<int, \App\Models\PointLog>
     */
    private function logsOfType(PointType $type, bool $all): Collection
    {
        return $this->student->pointLogs()
            ->with('pointRule')
            ->where('type', $type)
            ->latest()
            ->when(! $all, fn ($query) => $query->limit(5))
            ->get();
    }

    /**
     * Enforce the per-role scoping rules (PRD section 3.1).
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
    <x-ui.page-header :title="__('Development Point')" :subtitle="$student->name">
        <x-slot:actions>
            @can(App\Enums\Permission::ManagePoint->value)
                <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.points.monitoring')" wire:navigate>
                    {{ __('Kembali') }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        {{-- Periode Kelas --}}
        <div class="rounded-lg border border-gray-200 px-4 py-3">
            <p class="text-sm text-gray-500">{{ __('Periode Kelas') }}</p>
            <p class="font-semibold text-gray-800">
                {{ $student->classroom?->name ?? '—' }}
                @if ($this->activeYear)
                    <span class="text-gray-400">·</span> {{ $this->activeYear->name }}
                @endif
            </p>
        </div>

        {{-- Poin anda --}}
        <div class="mt-4 rounded-lg border border-gray-200 px-4 py-4">
            <div class="flex items-end justify-between">
                <p class="font-semibold text-gray-700">{{ __('Poin Anda') }}</p>
                <p class="text-lg font-bold text-gray-800 tabular-nums">
                    {{ $student->current_point }} <span class="text-sm font-normal text-gray-400">{{ __('of') }} {{ $this->setting->target_point }}</span>
                </p>
            </div>
            <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $this->progress }}%"></div>
            </div>
        </div>
    </div>

    <p class="font-semibold text-gray-700">{{ __('Aktivitas Anda') }}</p>

    {{-- Penambahan --}}
    <x-attendance.point-activity-list
        :title="__('Penambahan')"
        :logs="$this->additions"
        :showing-all="$showAllAdditions"
        toggle="$toggle('showAllAdditions')"
        :empty="__('Belum ada penambahan poin.')" />

    {{-- Pengurangan --}}
    <x-attendance.point-activity-list
        :title="__('Pengurangan')"
        :logs="$this->deductions"
        :showing-all="$showAllDeductions"
        toggle="$toggle('showAllDeductions')"
        :empty="__('Belum ada pengurangan poin.')" />
</div>
