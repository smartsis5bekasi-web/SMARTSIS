<?php

use App\Models\Classroom;
use Flux\Flux;
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
            Flux::toast(variant: 'danger', text: __('Kelas masih memiliki siswa dan tidak dapat dihapus.'));

            return;
        }

        $classroom->delete();
        Flux::toast(variant: 'success', text: __('Kelas dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Kelas') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola rombongan belajar dan wali kelas.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('master-data.classrooms.create') }}" wire:navigate>{{ __('Tambah') }}</flux:button>
    </div>

    <flux:table :paginate="$this->classrooms">
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Jurusan') }}</flux:table.column>
            <flux:table.column>{{ __('Tahun Ajaran') }}</flux:table.column>
            <flux:table.column>{{ __('Wali Kelas') }}</flux:table.column>
            <flux:table.column>{{ __('Siswa') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->classrooms as $classroom)
                <flux:table.row :key="$classroom->id">
                    <flux:table.cell variant="strong">{{ $classroom->name }}</flux:table.cell>
                    <flux:table.cell>{{ $classroom->major?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $classroom->academicYear?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $classroom->homeroomTeacher?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $classroom->students_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" href="{{ route('master-data.classrooms.edit', $classroom) }}" wire:navigate />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $classroom->id }})" wire:confirm="{{ __('Hapus kelas ini?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Belum ada data kelas.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</div>
