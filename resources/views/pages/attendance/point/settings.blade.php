<?php

use App\Models\PointSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Poin')] class extends Component {
    public PointSetting $setting;

    public int $initial_point = 100;

    public int $target_point = 100;

    public int $min_point = 40;

    public function mount(): void
    {
        $this->setting = PointSetting::current();
        $this->initial_point = $this->setting->initial_point;
        $this->target_point = $this->setting->target_point;
        $this->min_point = $this->setting->min_point;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'initial_point' => ['required', 'integer', 'min:0', 'max:1000'],
            'target_point' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_point' => ['required', 'integer', 'min:0', 'lte:target_point'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->setting->update($data);

        session()->flash('swal', ['icon' => 'success', 'title' => __('Pengaturan poin disimpan.')]);

        $this->redirectRoute('attendance.points', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pengaturan Poin')" :subtitle="__('Atur poin awal, target, dan ambang minimum siswa.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.points')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Poin Awal') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="initial_point"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Poin yang dimiliki siswa baru.') }}</span>
                @error('initial_point')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Target Poin') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="target_point"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Penyebut progress bar (mis. 50 of 120).') }}</span>
                @error('target_point')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Poin Minimum') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="min_point"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Ambang batas status / bahaya.') }}</span>
                @error('min_point')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
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
