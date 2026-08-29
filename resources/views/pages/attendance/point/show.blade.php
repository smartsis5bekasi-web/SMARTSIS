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

    #[Computed]
    public function belowMin(): bool
    {
        return $this->student->current_point < $this->setting->min_point;
    }

    /**
     * Position of the minimum-threshold marker on the progress bar (0–100%).
     */
    #[Computed]
    public function minMarker(): int
    {
        $target = max(1, $this->setting->target_point);

        return (int) min(100, round($this->setting->min_point / $target * 100));
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
            ->with(['pointRule', 'source'])
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

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
        {{-- Identitas siswa + status --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img class="h-16 w-16 rounded-2xl object-cover"
                    src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                <div class="flex flex-col">
                    <p class="text-lg font-bold text-gray-800">{{ $student->name }}</p>
                    <p class="text-sm text-gray-500">
                        NIS {{ $student->nis }}
                        <span class="text-gray-300">·</span>
                        {{ $student->classroom?->name ?? '—' }}
                        @if ($this->activeYear)
                            <span class="text-gray-300">·</span> {{ $this->activeYear->name }}
                        @endif
                    </p>
                </div>
            </div>

            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold',
                'bg-red-100 text-red-700' => $this->belowMin,
                'bg-green-100 text-green-700' => ! $this->belowMin,
            ])>
                <ion-icon name="{{ $this->belowMin ? 'alert-circle-outline' : 'shield-checkmark-outline' }}" class="text-base"></ion-icon>
                {{ $this->belowMin ? __('Di Bawah Minimum') : __('Aman') }}
            </span>
        </div>

        {{-- Progress poin --}}
        <div class="mt-8">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <p class="font-semibold text-gray-700">{{ __('Poin Anda') }}</p>
                <p class="text-3xl font-bold tabular-nums {{ $this->belowMin ? 'text-red-600' : 'text-gray-800' }}">
                    {{ $student->current_point }}
                    <span class="text-base font-normal text-gray-400">/ {{ $this->setting->target_point }}</span>
                </p>
            </div>

            <div class="relative mt-4">
                <div class="h-4 w-full overflow-hidden rounded-full bg-gray-100">
                    <div @class([
                        'h-full rounded-full transition-all',
                        'bg-red-500' => $this->belowMin,
                        'bg-amber-400' => ! $this->belowMin && $this->progress < 75,
                        'bg-primary-600' => ! $this->belowMin && $this->progress >= 75,
                    ]) style="width: {{ $this->progress }}%"></div>
                </div>
                {{-- Penanda ambang minimum --}}
                <div class="absolute inset-y-0 w-0.5 bg-red-400" style="left: {{ $this->minMarker }}%" title="{{ __('Poin Minimum') }}: {{ $this->setting->min_point }}"></div>
            </div>

            <div class="mt-2 flex justify-between text-xs text-gray-400">
                <span>0</span>
                <span class="text-red-400">{{ __('Min') }} {{ $this->setting->min_point }}</span>
                <span>{{ $this->setting->target_point }}</span>
            </div>
        </div>

        {{-- Ringkasan aturan poin --}}
        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Poin Awal') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-800">{{ $this->setting->initial_point }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Target Poin') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-800">{{ $this->setting->target_point }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Poin Minimum') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-800">{{ $this->setting->min_point }}</p>
            </div>
        </div>
    </div>

    <p class="font-semibold text-gray-700">{{ __('Aktivitas Anda') }}</p>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
</div>
