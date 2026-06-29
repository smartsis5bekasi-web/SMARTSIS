<?php

use App\Enums\PointSource;
use App\Enums\PointType;
use App\Models\PointRule;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Aturan Poin')] class extends Component {
    public string $name = '';

    public string $type = '';

    public string $source = '';

    public ?int $point = null;

    public bool $is_active = true;

    /**
     * The sources valid for the chosen type (Prestasi only adds, Pelanggaran
     * only deducts). Drives the "Cara" select reactively.
     *
     * @return array<int, PointSource>
     */
    #[Computed]
    public function availableSources(): array
    {
        $type = PointType::tryFrom($this->type);

        return array_values(array_filter(
            PointSource::cases(),
            fn (PointSource $source): bool => $type === null || in_array($type, $source->allowedTypes(), true),
        ));
    }

    /**
     * Clear an incompatible source whenever the type changes.
     */
    public function updatedType(): void
    {
        $allowed = array_map(fn (PointSource $source): string => $source->value, $this->availableSources());

        if (! in_array($this->source, $allowed, true)) {
            $this->source = '';
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $allowedSources = array_map(fn (PointSource $source): string => $source->value, $this->availableSources());

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('point_rules', 'name')],
            'type' => ['required', Rule::in(PointType::values())],
            'source' => ['required', Rule::in($allowedSources)],
            'point' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        PointRule::create($data);

        session()->flash('swal', ['icon' => 'success', 'title' => __('Aturan poin ditambahkan.')]);

        $this->redirectRoute('attendance.points', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Aturan Poin')" :subtitle="__('Tentukan cara penambahan atau pengurangan poin.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.points')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama Point') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="{{ __('mis. Terlambat, Juara Lomba') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tipe Point') }} <span class="text-red-500">*</span></label>
                <select wire:model.live="type"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Pilih tipe') }}</option>
                    @foreach (App\Enums\PointType::cases() as $pointType)
                        <option value="{{ $pointType->value }}">{{ $pointType->label() }}</option>
                    @endforeach
                </select>
                @error('type')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Cara Mendapatkan / Mengurangi') }} <span class="text-red-500">*</span></label>
                <select wire:model="source" @disabled($this->type === '')
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-400">
                    <option value="">{{ $this->type === '' ? __('Pilih tipe dahulu') : __('Pilih cara') }}</option>
                    @foreach ($this->availableSources as $pointSource)
                        <option value="{{ $pointSource->value }}">{{ $pointSource->label() }}</option>
                    @endforeach
                </select>
                @error('source')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jumlah Point') }} <span class="text-red-500">*</span></label>
                <input type="number" min="1" wire:model="point" placeholder="{{ __('mis. 20') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Masukkan angka positif; tanda + / − mengikuti tipe.') }}</span>
                @error('point')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Status') }}</label>
                <label class="inline-flex items-center gap-2 py-2.5">
                    <input type="checkbox" wire:model="is_active"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-gray-700">{{ __('Aktif') }}</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('attendance.points')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
