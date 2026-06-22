<?php

use App\Models\AcademicYear;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Tahun Ajaran')] class extends Component {
    public AcademicYear $year;

    public string $name = '';

    public ?string $started_on = null;

    public ?string $ended_on = null;

    public function mount(AcademicYear $year): void
    {
        $this->year = $year;
        $this->name = $year->name;
        $this->started_on = $year->started_on?->toDateString();
        $this->ended_on = $year->ended_on?->toDateString();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($this->year->id)],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->year->update($data);

        Flux::toast(variant: 'success', text: __('Tahun ajaran diperbarui.'));

        $this->redirectRoute('master-data.academic-years', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Edit Tahun Ajaran') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Perbarui detail tahun ajaran.') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('master-data.academic-years') }}" wire:navigate>
            {{ __('Kembali') }}
        </flux:button>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="2025/2026"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Mulai') }}</label>
                <input type="date" wire:model="started_on"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('started_on')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Selesai') }}</label>
                <input type="date" wire:model="ended_on"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('ended_on')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="filled" href="{{ route('master-data.academic-years') }}" wire:navigate>
                {{ __('Batal') }}
            </flux:button>
            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </div>
    </form>
</div>
