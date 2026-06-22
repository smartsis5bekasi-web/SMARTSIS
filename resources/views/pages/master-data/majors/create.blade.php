<?php

use App\Models\Major;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Jurusan')] class extends Component {
    public string $name = '';

    public ?string $code = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('majors', 'name')],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('majors', 'code')],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        Major::create($data);

        Flux::toast(variant: 'success', text: __('Jurusan ditambahkan.'));

        $this->redirectRoute('master-data.majors', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Tambah Jurusan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Isi detail jurusan baru.') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('master-data.majors') }}" wire:navigate>
            {{ __('Kembali') }}
        </flux:button>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="Ilmu Pengetahuan Alam"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Kode') }}</label>
                <input type="text" wire:model="code" placeholder="IPA"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('code')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="filled" href="{{ route('master-data.majors') }}" wire:navigate>
                {{ __('Batal') }}
            </flux:button>
            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </div>
    </form>
</div>
