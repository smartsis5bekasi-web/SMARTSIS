<?php

use App\Enums\Permission;
use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Models\Violation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pelanggaran')] class extends Component {
    use WithPagination;

    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Violation>
     */
    #[Computed]
    public function violations(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['student', 'pointRule'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }

    /**
     * Guru BK (Kelola) and Guru Piket (Input) may record violations.
     */
    public function canRecord(): bool
    {
        return auth()->user()->canAny([
            Permission::InputViolation->value,
            Permission::ManageViolation->value,
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
     * Violations scoped to what the signed-in role may see.
     *
     * @return Builder<Violation>
     */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Violation::query()->where('student_id', $user->student?->id),
            UserRole::WaliKelas => Violation::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            UserRole::OrangTua => Violation::query()->whereIn(
                'student_id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            default => Violation::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pelanggaran')"
        :subtitle="$this->isStudent() ? __('Riwayat pelanggaran Anda.') : __('Catat & verifikasi pelanggaran siswa.')">
        @if ($this->canRecord())
            <x-slot:actions>
                <x-ui.button variant="primary" icon="add-outline" :href="route('academic.violations.create')" wire:navigate>
                    {{ __('Catat Pelanggaran') }}
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
                        <th class="py-3 px-4">{{ __('Jenis Pelanggaran') }}</th>
                        <th class="py-3 px-4">{{ __('Poin') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700">
                    @forelse ($this->violations as $key => $violation)
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->violations->firstItem() }}</td>
                            @unless ($this->isStudent())
                                <td class="py-3 px-4">{{ $violation->student?->name ?? '—' }}</td>
                            @endunless
                            <td class="py-3 px-4 font-medium">{{ $violation->pointRule?->name ?? '—' }}</td>
                            <td class="py-3 px-4 font-semibold text-red-600 tabular-nums">
                                -{{ $violation->pointRule?->point ?? 0 }}
                            </td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-amber-100 text-amber-700' => $violation->status === PointApprovalStatus::Pending,
                                    'bg-green-100 text-green-700' => $violation->status === PointApprovalStatus::Approved,
                                    'bg-red-100 text-red-700' => $violation->status === PointApprovalStatus::Rejected,
                                ])>{{ $violation->status->label() }}</span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">{{ $violation->occurred_on?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('academic.violations.show', $violation) }}" wire:navigate
                                    class="inline-flex items-center gap-1 text-primary-600 transition hover:text-primary-700">
                                    <ion-icon name="eye-outline" class="text-lg"></ion-icon>
                                    <span class="text-sm">{{ __('Detail') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-5 text-center text-gray-500">{{ __('Belum ada pelanggaran.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->violations->links() }}
        </div>
    </div>
</div>
