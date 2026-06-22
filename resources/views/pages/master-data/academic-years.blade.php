<?php

use App\Models\AcademicYear;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tahun Ajaran')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, AcademicYear>
     */
    #[Computed]
    public function years(): LengthAwarePaginator
    {
        return AcademicYear::query()
            ->withCount('classrooms')
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

        Flux::toast(variant: 'success', text: __('Tahun ajaran :name diaktifkan.', ['name' => $year->name]));
    }

    public function delete(AcademicYear $year): void
    {
        if ($year->is_active) {
            Flux::toast(variant: 'danger', text: __('Tahun ajaran aktif tidak dapat dihapus.'));

            return;
        }

        if ($year->classrooms()->exists()) {
            Flux::toast(variant: 'danger', text: __('Tahun ajaran memiliki kelas terkait dan tidak dapat dihapus.'));

            return;
        }

        $year->delete();
        Flux::toast(variant: 'success', text: __('Tahun ajaran dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Tahun Ajaran') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola periode tahun ajaran sekolah.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('master-data.academic-years.create') }}" wire:navigate>{{ __('Tambah') }}</flux:button>
    </div>

    <flux:table :paginate="$this->years">
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Mulai') }}</flux:table.column>
            <flux:table.column>{{ __('Selesai') }}</flux:table.column>
            <flux:table.column>{{ __('Kelas') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->years as $year)
                <flux:table.row :key="$year->id">
                    <flux:table.cell variant="strong">{{ $year->name }}</flux:table.cell>
                    <flux:table.cell>{{ $year->started_on?->translatedFormat('d M Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $year->ended_on?->translatedFormat('d M Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $year->classrooms_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$year->is_active ? 'green' : 'zinc'">
                            {{ $year->is_active ? __('Aktif') : __('Nonaktif') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            @unless ($year->is_active)
                                <flux:button size="xs" variant="ghost" icon="check-circle" wire:click="activate({{ $year->id }})">
                                    {{ __('Aktifkan') }}
                                </flux:button>
                            @endunless
                            <flux:button size="xs" variant="ghost" icon="pencil-square" href="{{ route('master-data.academic-years.edit', $year) }}" wire:navigate />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $year->id }})" wire:confirm="{{ __('Hapus tahun ajaran ini?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Belum ada data tahun ajaran.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</div>
