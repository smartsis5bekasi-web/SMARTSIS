<?php

use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelas')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Classroom>
     */
    #[Computed]
    public function classrooms(): LengthAwarePaginator
    {
        return Classroom::query()
            ->with(['major', 'academicYear', 'homeroomTeacher'])
            ->withCount('students')
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Classroom $classroom): void
    {
        if ($classroom->students()->exists()) {
            $this->dispatch('swal', icon: 'error', title: __('Kelas masih memiliki siswa dan tidak dapat dihapus.'));

            return;
        }

        $classroom->delete();
        $this->dispatch('swal', icon: 'success', title: __('Kelas dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Kelas')" :subtitle="__('Kelola rombongan belajar dan wali kelas.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.classrooms.create')" wire:navigate>
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
                        <th class="px-4 py-3 font-medium">{{ __('Jurusan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Tahun Ajaran') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Wali Kelas') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Siswa') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->classrooms as $classroom)
                        <tr wire:key="{{ $classroom->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $classroom->name }}</td>
                            <td class="px-4 py-3">{{ $classroom->major?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $classroom->academicYear?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $classroom->homeroomTeacher?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $classroom->students_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.classrooms.edit', $classroom) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$classroom->id" :title="__('Hapus kelas ini?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data kelas.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->classrooms->links() }}</div>
    </div>
</div>
