<?php

use App\Models\ParentGuardian;
use App\Livewire\Concerns\TogglesUserActiveStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orang Tua / Wali')] class extends Component {
    use TogglesUserActiveStatus;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, ParentGuardian>
     */
    #[Computed]
    public function parents(): LengthAwarePaginator
    {
        return ParentGuardian::query()
            ->with('user')
            ->withCount('students')
            ->when(filled($this->search), function (Builder $query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('user', fn (Builder $uq) => $uq->where('email', 'like', $term));
                });
            })
            ->when($this->status !== '', function (Builder $query) {
                $isActive = $this->status === 'active';
                $query->whereHas('user', fn (Builder $uq) => $uq->where('is_active', $isActive));
            })
            ->orderByDesc('updated_at')
            ->paginate(10);
    }

    public function delete(ParentGuardian $parent): void
    {
        if ($parent->students()->exists()) {
            $this->dispatch('swal', icon: 'error', title: __('Data orang tua masih terhubung dengan siswa dan tidak dapat dihapus.'));

            return;
        }

        $parent->loadMissing('user');
        $parent->user?->delete();
        $parent->delete();

        $this->dispatch('swal', icon: 'success', title: __('Data orang tua dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Orang Tua / Wali')" :subtitle="__('Kelola akun orang tua/wali siswa.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.parents.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            {{-- Section Search & Filter --}}
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[240px] flex-1 sm:flex-none">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Cari nama, telepon, email...') }}"
                        class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                    </div>
                </div>

                <select wire:model.live="status"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="active">{{ __('Aktif') }}</option>
                    <option value="inactive">{{ __('Nonaktif') }}</option>
                </select>

                @if ($search !== '' || $status !== '')
                    <button type="button" wire:click="resetFilters" class="text-xs font-medium text-red-600 hover:underline">
                        {{ __('Reset Filter') }}
                    </button>
                @endif
            </div>

            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Telepon') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Anak') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->parents as $parent)
                        <tr wire:key="{{ $parent->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $parent->name }}</td>
                            <td class="px-4 py-3">{{ $parent->user?->email ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $parent->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $parent->students_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.parents.show', $parent) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Lihat') }}">
                                        <ion-icon name="eye-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <a href="{{ route('master-data.parents.edit', $parent) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$parent->id" :text="__('Akun login terkait juga akan dihapus dan tidak dapat dikembalikan.')" />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-ui.status-toggle :user="$parent->user" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data orang tua.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->parents->links() }}</div>
    </div>
</div>