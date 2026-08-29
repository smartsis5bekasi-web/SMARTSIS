    <?php

    use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\UserRole;
use App\Exports\PermitExport;
use App\Models\Permit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Perizinan')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $type = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Permit>
     */
    #[Computed]
    public function permits(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['student.classroom', 'decider'])
            ->when(trim($this->search) !== '', function (Builder $query) {
                $searchTerm = '%'.trim($this->search).'%';
                $query->whereHas('student', function (Builder $q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('nis', 'like', $searchTerm);
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->latest()
            ->paginate(10);
    }

    /**
     * Pending requests awaiting the signed-in approver (shown as a hint).
     */
    #[Computed]
    public function pendingCount(): int
    {
        return $this->canDecideAny() ? $this->scopedQuery()->where('status', PermitStatus::Pending)->count() : 0;
    }

    public function isStudent(): bool
    {
        return auth()->user()->primaryRole() === UserRole::Siswa;
    }

    /**
     * A siswa with a linked student record may submit a request (F-24).
     */
    public function canRequest(): bool
    {
        return auth()->user()->can(Permission::RequestPermit->value)
            && auth()->user()->student !== null;
    }

    /**
     * Guru Piket (Kelola) and Wali Kelas (kelas binaan) decide permits (F-25).
     */
    public function canDecideAny(): bool
    {
        return auth()->user()->can(Permission::ManagePermit->value)
            || auth()->user()->primaryRole() === UserRole::WaliKelas;
    }

    /**
     * Guru Piket / admin may record a walk-in permit on a student's behalf.
     */
    public function canCreateManual(): bool
    {
        return auth()->user()->can(Permission::ManagePermit->value);
    }

    /**
     * @return array<int, PermitStatus>
     */
    public function statuses(): array
    {
        return PermitStatus::cases();
    }

    /**
     * @return array<int, PermitType>
     */
    public function types(): array
    {
        return PermitType::cases();
    }

    /**
     * Permits scoped to what the signed-in role may see.
     *
     * @return Builder<Permit>
     */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Permit::query()->where('student_id', $user->student?->id ?? 0),
            UserRole::OrangTua => Permit::query()->whereIn(
                'student_id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            UserRole::WaliKelas => Permit::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            default => Permit::query(),
        };
    }

    public function exportExcel(): BinaryFileResponse
    {
        $export = new PermitExport(
            baseQuery: $this->scopedQuery(),
            status: $this->status,
            type: $this->type,
        );

        return Excel::download($export, 'perizinan-'.now()->format('Y-m-d').'.xlsx');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'type']);
        $this->resetPage();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Perizinan')"
            :subtitle="$this->isStudent() ? __('Ajukan izin dan pantau statusnya.') : __('Kelola & pantau pengajuan izin siswa.')">
            
        <x-slot:actions>
                @if ($this->canDecideAny())
                    <x-ui.button 
                        variant="secondary" 
                        icon="print-outline" 
                        :href="route('permits.print', [
                            'search' => $this->search,
                            'status' => $this->status,
                            'type' => $this->type,
                        ])" 
                        target="_blank">
                        {{ __('Cetak / Simpan PDF') }}
                    </x-ui.button>

                    <x-ui.button variant="secondary" icon="download-outline" wire:click="exportExcel">
                        {{ __('Export Excel') }}
                    </x-ui.button>
                @endif

                @if ($this->canCreateManual())
                    <x-ui.button variant="primary" icon="add-outline" :href="route('permits.manual')" wire:navigate>
                        {{ __('Input Izin Manual') }}
                    </x-ui.button>
                @endif

                @if ($this->canRequest())
                    <x-ui.button variant="primary" icon="add-outline" :href="route('permits.create')" wire:navigate>
                        {{ __('Ajukan Izin') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>

        </x-ui.page-header>

        <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[240px]">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="{{ __('Cari nama / NIS siswa...') }}"
                        class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                    </div>
                </div>
                <select wire:model.live="status"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach ($this->statuses() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="type"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Jenis') }}</option>
                    @foreach ($this->types() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>

                @if ($this->pendingCount > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                        <ion-icon name="time-outline"></ion-icon>
                        {{ __(':count pengajuan menunggu persetujuan', ['count' => $this->pendingCount]) }}
                    </span>
                @endif

                @if ($search !== '' || $status !== '' || $type !== '')
                <button type="button" wire:click="resetFilters" class="text-xs font-medium text-red-600 hover:underline">
                    {{ __('Reset Filter') }}
                </button>
                @endif

            </div>

            <div class="rounded-2xl border overflow-auto">
                <table class="border-collapse min-w-full leading-normal">
                    <thead>
                        <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                            <th class="py-3 px-4">No</th>
                            @unless ($this->isStudent())
                                <th class="py-3 px-4">{{ __('Siswa') }}</th>
                                <th class="py-3 px-4">{{ __('Kelas') }}</th>
                            @endunless
                            <th class="py-3 px-4">{{ __('Jenis') }}</th>
                            <th class="py-3 px-4">{{ __('Tanggal Izin') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Diputuskan Oleh') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-gray-700 whitespace-nowrap">
                        @forelse ($this->permits as $key => $permit)
                            <tr class="border-b last:border-0">
                                <td class="py-3 px-4">{{ $key + $this->permits->firstItem() }}</td>
                                @unless ($this->isStudent())
                                    <td class="py-3 px-4 font-medium">{{ $permit->student?->name ?? '—' }}</td>
                                    <td class="py-3 px-4">{{ $permit->student?->classroom?->name ?? '—' }}</td>
                                @endunless
                                <td class="py-3 px-4">{{ $permit->type->label() }}</td>
                                <td class="py-3 px-4">{{ $permit->date->translatedFormat('d M Y') }}</td>
                                <td class="py-3 px-4">
                                    <x-permit.status-badge :status="$permit->status" />
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-500">{{ $permit->decider?->name ?? '—' }}</td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('permits.show', $permit) }}" wire:navigate
                                        class="inline-flex items-center gap-1 text-primary-600 transition hover:text-primary-700">
                                        <ion-icon name="eye-outline" class="text-lg"></ion-icon>
                                        <span class="text-sm">{{ __('Detail') }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $this->isStudent() ? 6 : 8 }}" class="p-5 text-center text-gray-500">{{ __('Belum ada pengajuan izin.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->permits->links() }}
            </div>

        </div>
    </div>
