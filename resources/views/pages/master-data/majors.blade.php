<?php

use App\Models\Major;
use Flux\Flux;
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
            Flux::toast(variant: 'danger', text: __('Jurusan masih dipakai kelas atau siswa dan tidak dapat dihapus.'));

            return;
        }

        $major->delete();
        Flux::toast(variant: 'success', text: __('Jurusan dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Jurusan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola jurusan / peminatan siswa.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('master-data.majors.create') }}" wire:navigate>{{ __('Tambah') }}</flux:button>
    </div>

    <flux:table :paginate="$this->majors">
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Kode') }}</flux:table.column>
            <flux:table.column>{{ __('Kelas') }}</flux:table.column>
            <flux:table.column>{{ __('Siswa') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->majors as $major)
                <flux:table.row :key="$major->id">
                    <flux:table.cell variant="strong">{{ $major->name }}</flux:table.cell>
                    <flux:table.cell>{{ $major->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $major->classrooms_count }}</flux:table.cell>
                    <flux:table.cell>{{ $major->students_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" href="{{ route('master-data.majors.edit', $major) }}" wire:navigate />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $major->id }})" wire:confirm="{{ __('Hapus jurusan ini?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Belum ada data jurusan.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</div>
