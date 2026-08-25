<?php

use App\Models\AcademicYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tahun Ajaran')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AcademicYear>
     */
    #[Computed]
    public function years(): LengthAwarePaginator
    {
        return AcademicYear::query()
            ->withCount('classrooms')
            ->when(filled($this->search), fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderByDesc('is_active')
            ->orderByDesc('name')
            ->paginate(10);
    }

    public function activate(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            AcademicYear::query()->whereKeyNot($year->id)->update(['is_active' => false]);
            $year->update(['is_active' => true]);
        });

        $this->dispatch('swal', icon: 'success', title: __('Tahun ajaran :name diaktifkan.', ['name' => $year->name]));
    }

    public function delete(AcademicYear $year): void
    {
        if ($year->is_active) {
            $this->dispatch('swal', icon: 'error', title: __('Tahun ajaran aktif tidak dapat dihapus.'));

            return;
        }

        if ($year->classrooms()->exists()) {
            $this->dispatch('swal', icon: 'error', title: __('Tahun ajaran memiliki kelas terkait dan tidak dapat dihapus.'));

            return;
        }

        $year->delete();
        $this->dispatch('swal', icon: 'success', title: __('Tahun ajaran dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tahun Ajaran')" :subtitle="__('Kelola periode tahun ajaran sekolah.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.academic-years.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
             <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px] flex-1 sm:flex-none">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Cari nama tahun ajaran...') }}"
                    class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                </div>
            </div>

            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-xs font-medium text-red-600 hover:underline">
                    {{ __('Reset Pencarian') }}
                </button>
            @endif
        </div>
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Mulai') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Selesai') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Kelas') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->years as $year)
                        <tr wire:key="{{ $year->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $year->name }}</td>
                            <td class="px-4 py-3">{{ $year->started_on?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $year->ended_on?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $year->classrooms_count }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-700' => $year->is_active,
                                    'bg-gray-100 text-gray-500' => ! $year->is_active,
                                ])>
                                    {{ $year->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @unless ($year->is_active)
                                        <button type="button" wire:click="activate({{ $year->id }})" class="inline-flex text-green-600 transition hover:text-green-700" title="{{ __('Aktifkan') }}">
                                            <ion-icon name="checkmark-circle-outline" class="text-xl"></ion-icon>
                                        </button>
                                    @endunless
                                    <a href="{{ route('master-data.academic-years.edit', $year) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$year->id" :title="__('Hapus tahun ajaran ini?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data tahun ajaran.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->years->links() }}</div>
    </div>
</div>
