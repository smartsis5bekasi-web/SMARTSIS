<?php

use App\Models\Major;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Jurusan')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Major>
     */
    #[Computed]
    public function majors(): LengthAwarePaginator
    {
        return Major::query()
            ->withCount(['classrooms', 'students'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Major $major): void
    {
        if ($major->classrooms()->exists() || $major->students()->exists()) {
            $this->dispatch('swal', icon: 'error', title: __('Jurusan masih dipakai kelas atau siswa dan tidak dapat dihapus.'));

            return;
        }

        $major->delete();
        $this->dispatch('swal', icon: 'success', title: __('Jurusan dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Jurusan')" :subtitle="__('Kelola jurusan / peminatan siswa.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.majors.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Kode') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Kelas') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Siswa') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->majors as $major)
                        <tr wire:key="{{ $major->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $major->name }}</td>
                            <td class="px-4 py-3">{{ $major->code ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $major->classrooms_count }}</td>
                            <td class="px-4 py-3">{{ $major->students_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.majors.edit', $major) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$major->id" :title="__('Hapus jurusan ini?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data jurusan.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->majors->links() }}</div>
    </div>
</div>
