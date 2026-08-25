<?php

use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Major;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


new #[Title('Kelas')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $majorId = '';

    public string $homeroomTeacherId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMajorId(): void
    {
        $this->resetPage();
    }

    public function updatedHomeroomTeacherId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'majorId', 'homeroomTeacherId']);
        $this->resetPage();
    }

        /**
     * @return Collection<int, Major>
     */
    #[Computed]

    public function majorOptions(): Collection
    {
        return Major::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Teacher>
     */
    #[Computed]

    public function teacherOptions(): Collection
    {
        return Teacher::query()->orderBy('name')->get();
    }

    #[Computed]
    public function classrooms(): LengthAwarePaginator
    {
        return Classroom::query()
            ->with(['major', 'academicYear', 'homeroomTeacher'])
            ->withCount('students')
            ->when(filled($this->search), fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->majorId !== '', fn (Builder $query) => $query->where('major_id', $this->majorId))
            ->when($this->homeroomTeacherId !== '', fn (Builder $query) => $query->where('homeroom_teacher_id', $this->homeroomTeacherId))
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
        {{-- Section Search & Filter --}}
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px] flex-1 sm:flex-none">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Cari nama kelas...') }}"
                    class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                </div>
            </div>

            <select wire:model.live="majorId"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua Jurusan') }}</option>
                @foreach ($this->majorOptions as $major)
                    <option value="{{ $major->id }}">{{ $major->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="homeroomTeacherId"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua Wali Kelas') }}</option>
                @foreach ($this->teacherOptions as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>

            @if ($search !== '' || $majorId !== '' || $homeroomTeacherId !== '')
                <button type="button" wire:click="resetFilters" class="text-xs font-medium text-red-600 hover:underline">
                    {{ __('Reset Filter') }}
                </button>
            @endif
        </div>

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
