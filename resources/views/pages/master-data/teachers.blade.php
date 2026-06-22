<?php

use App\Models\Teacher;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guru')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Teacher>
     */
    #[Computed]
    public function teachers(): LengthAwarePaginator
    {
        return Teacher::query()
            ->with('user.roles')
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Teacher $teacher): void
    {
        // Deleting the user cascades to the teacher row, which in turn nulls
        // any classroom it was the homeroom teacher of.
        $teacher->loadMissing('user');
        $teacher->user?->delete();
        $teacher->delete();

        Flux::toast(variant: 'success', text: __('Guru dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Guru') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola data guru beserta akun & peran login.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('master-data.teachers.create') }}" wire:navigate>{{ __('Tambah') }}</flux:button>
    </div>

    <flux:table :paginate="$this->teachers">
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>NIP</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Peran') }}</flux:table.column>
            <flux:table.column>{{ __('Telepon') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->teachers as $teacher)
                <flux:table.row :key="$teacher->id">
                    <flux:table.cell variant="strong">{{ $teacher->name }}</flux:table.cell>
                    <flux:table.cell>{{ $teacher->nip ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $teacher->user?->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($teacher->user?->primaryRole())
                            <flux:badge size="sm" color="violet">{{ $teacher->user->primaryRole()->label() }}</flux:badge>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $teacher->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" href="{{ route('master-data.teachers.edit', $teacher) }}" wire:navigate />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $teacher->id }})" wire:confirm="{{ __('Hapus guru ini? Akun login terkait juga akan dihapus.') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Belum ada data guru.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</div>
