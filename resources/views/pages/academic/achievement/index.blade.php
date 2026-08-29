<?php

use App\Enums\Permission;
use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Models\Achievement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Prestasi')] class extends Component {
    use WithPagination;

    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Achievement>
     */
    #[Computed]
    public function achievements(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['student', 'pointRule'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }

    public function canSubmit(): bool
    {
        return auth()->user()->canAny([
            Permission::RequestAchievement->value,
            Permission::ManageAchievement->value,
        ]);
    }

    public function isStudent(): bool
    {
        return auth()->user()->primaryRole() === UserRole::Siswa;
    }

    /**
     * @return array<int, PointApprovalStatus>
     */
    public function statuses(): array
    {
        return PointApprovalStatus::cases();
    }

    /**
     * Achievements scoped to what the signed-in role may see.
     *
     * @return Builder<Achievement>
     */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Achievement::query()->where('student_id', $user->student?->id),
            UserRole::WaliKelas => Achievement::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            UserRole::OrangTua => Achievement::query()->whereIn(
                'student_id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            default => Achievement::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Prestasi')"
        :subtitle="$this->isStudent() ? __('Ajukan dan pantau prestasi Anda.') : __('Kelola & verifikasi prestasi siswa.')">
        @if ($this->canSubmit())
            <x-slot:actions>
                <x-ui.button variant="primary" icon="add-outline" :href="route('academic.achievements.create')" wire:navigate>
                    {{ $this->isStudent() ? __('Ajukan Prestasi') : __('Tambah Prestasi') }}
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="mb-4 flex items-center gap-2">
            <label class="text-sm text-gray-500">{{ __('Status') }}</label>
            <select wire:model.live="status"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua') }}</option>
                @foreach ($this->statuses() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        @unless ($this->isStudent())
                            <th class="py-3 px-4">{{ __('Siswa') }}</th>
                        @endunless
                        <th class="py-3 px-4">{{ __('Judul') }}</th>
                        <th class="py-3 px-4">{{ __('Tingkat') }}</th>
                        <th class="py-3 px-4">{{ __('Poin') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700 whitespace-nowrap">
                    @forelse ($this->achievements as $key => $achievement)
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->achievements->firstItem() }}</td>
                            @unless ($this->isStudent())
                                <td class="py-3 px-4">{{ $achievement->student?->name ?? '—' }}</td>
                            @endunless
                            {{-- Free text: cap it so one long title cannot stretch the whole table. --}}
                            <td class="max-w-[16rem] truncate py-3 px-4 font-medium" title="{{ $achievement->title }}">{{ $achievement->title }}</td>
                            <td class="py-3 px-4">{{ $achievement->level ?? '—' }}</td>
                            <td class="py-3 px-4 font-semibold text-green-600 tabular-nums">
                                +{{ $achievement->pointRule?->point ?? 0 }}
                            </td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-amber-100 text-amber-700' => $achievement->status === PointApprovalStatus::Pending,
                                    'bg-green-100 text-green-700' => $achievement->status === PointApprovalStatus::Approved,
                                    'bg-red-100 text-red-700' => $achievement->status === PointApprovalStatus::Rejected,
                                ])>{{ $achievement->status->label() }}</span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">{{ $achievement->achieved_on?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('academic.achievements.show', $achievement) }}" wire:navigate
                                    class="inline-flex items-center gap-1 text-primary-600 transition hover:text-primary-700">
                                    <ion-icon name="eye-outline" class="text-lg"></ion-icon>
                                    <span class="text-sm">{{ __('Detail') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-5 text-center text-gray-500">{{ __('Belum ada prestasi.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->achievements->links() }}
        </div>
    </div>
</div>
