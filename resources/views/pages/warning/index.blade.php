<?php

use App\Actions\Warning\EvaluateWarningRecommendation;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\WarningLevel;
use App\Enums\WarningStatus;
use App\Models\WarningLetter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Surat Peringatan')] class extends Component {
    use WithPagination;

    public string $status = '';

    public string $level = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'level'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, WarningLetter>
     */
    #[Computed]
    public function letters(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['student.classroom', 'decider'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->level !== '', fn (Builder $query) => $query->where('level', $this->level))
            ->latest()
            ->paginate(10);
    }

    /**
     * Recommendations awaiting Guru BK.
     */
    #[Computed]
    public function pendingCount(): int
    {
        return $this->canManage()
            ? WarningLetter::query()->where('status', WarningStatus::Pending)->count()
            : 0;
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageWarning->value);
    }

    /**
     * Siswa & Orang Tua only see letters that were actually issued; pending
     * recommendations are internal to the school (PRD F-30).
     */
    public function isPersonal(): bool
    {
        return in_array(auth()->user()->primaryRole(), [UserRole::Siswa, UserRole::OrangTua], true);
    }

    /**
     * Re-run the threshold detection across all students (F-21).
     */
    public function checkRecommendations(): void
    {
        abort_unless($this->canManage(), 403);

        $created = app(EvaluateWarningRecommendation::class)->sweep();

        unset($this->letters, $this->pendingCount);

        $this->dispatch('swal', icon: 'success', title: $created > 0
            ? __(':count rekomendasi SP baru ditemukan.', ['count' => $created])
            : __('Tidak ada rekomendasi baru.'));
    }

    /**
     * @return array<int, WarningStatus>
     */
    public function statuses(): array
    {
        return WarningStatus::cases();
    }

    /**
     * @return array<int, WarningLevel>
     */
    public function levels(): array
    {
        return WarningLevel::cases();
    }

    /**
     * Letters scoped to what the signed-in role may see.
     *
     * @return Builder<WarningLetter>
     */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => WarningLetter::query()
                ->where('student_id', $user->student?->id ?? 0)
                ->where('status', WarningStatus::Approved),
            UserRole::OrangTua => WarningLetter::query()
                ->whereIn('student_id', $user->parentGuardian?->students()->pluck('students.id') ?? collect())
                ->where('status', WarningStatus::Approved),
            UserRole::WaliKelas => WarningLetter::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            default => WarningLetter::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Surat Peringatan')"
        :subtitle="$this->isPersonal() ? __('Surat peringatan yang diterbitkan.') : __('Rekomendasi otomatis & penerbitan SP1–SP3.')">
        @if ($this->canManage())
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="settings-outline" :href="route('warnings.settings')" wire:navigate>
                    {{ __('Pengaturan SP') }}
                </x-ui.button>
                <x-ui.button variant="primary" icon="refresh-outline" wire:click="checkRecommendations">
                    {{ __('Periksa Rekomendasi') }}
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            @unless ($this->isPersonal())
                <select wire:model.live="status"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach ($this->statuses() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            @endunless
            <select wire:model.live="level"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua Level') }}</option>
                @foreach ($this->levels() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>

            @if ($this->pendingCount > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                    <ion-icon name="time-outline"></ion-icon>
                    {{ __(':count rekomendasi menunggu persetujuan', ['count' => $this->pendingCount]) }}
                </span>
            @endif
        </div>

        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        @unless ($this->isPersonal())
                            <th class="py-3 px-4">{{ __('Siswa') }}</th>
                            <th class="py-3 px-4">{{ __('Kelas') }}</th>
                        @endunless
                        <th class="py-3 px-4">{{ __('Level') }}</th>
                        <th class="py-3 px-4">{{ __('Poin') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Nomor Surat') }}</th>
                        <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700">
                    @forelse ($this->letters as $key => $letter)
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->letters->firstItem() }}</td>
                            @unless ($this->isPersonal())
                                <td class="py-3 px-4 font-medium">{{ $letter->student?->name ?? '—' }}</td>
                                <td class="py-3 px-4">{{ $letter->student?->classroom?->name ?? '—' }}</td>
                            @endunless
                            <td class="py-3 px-4"><x-warning.level-badge :level="$letter->level" /></td>
                            <td class="py-3 px-4 tabular-nums">{{ $letter->points_snapshot }} <span class="text-xs text-gray-400">/ ambang {{ $letter->threshold }}</span></td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-amber-100 text-amber-700' => $letter->status === WarningStatus::Pending,
                                    'bg-green-100 text-green-700' => $letter->status === WarningStatus::Approved,
                                    'bg-red-100 text-red-700' => $letter->status === WarningStatus::Rejected,
                                ])>{{ $letter->status->label() }}</span>
                            </td>
                            <td class="py-3 px-4 text-sm">{{ $letter->letter_number ?? '—' }}</td>
                            <td class="py-3 px-4 text-sm text-gray-500">{{ $letter->created_at?->translatedFormat('d M Y') }}</td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('warnings.show', $letter) }}" wire:navigate
                                    class="inline-flex items-center gap-1 text-primary-600 transition hover:text-primary-700">
                                    <ion-icon name="eye-outline" class="text-lg"></ion-icon>
                                    <span class="text-sm">{{ __('Detail') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->isPersonal() ? 7 : 9 }}" class="p-5 text-center text-gray-500">{{ __('Belum ada surat peringatan.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->letters->links() }}
        </div>
    </div>
</div>
