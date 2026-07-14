<?php

use App\Actions\Warning\EvaluateWarningRecommendation;
use App\Models\WarningSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Surat Peringatan')] class extends Component {
    public WarningSetting $setting;

    public int $sp1_threshold = 80;

    public int $sp2_threshold = 60;

    public int $sp3_threshold = 40;

    public function mount(): void
    {
        $this->setting = WarningSetting::current();
        $this->sp1_threshold = $this->setting->sp1_threshold;
        $this->sp2_threshold = $this->setting->sp2_threshold;
        $this->sp3_threshold = $this->setting->sp3_threshold;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'sp1_threshold' => ['required', 'integer', 'min:1', 'max:1000'],
            'sp2_threshold' => ['required', 'integer', 'min:1', 'lt:sp1_threshold'],
            'sp3_threshold' => ['required', 'integer', 'min:0', 'lt:sp2_threshold'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->setting->update($data);

        // Thresholds changed — re-detect immediately so BK sees the impact.
        $created = app(EvaluateWarningRecommendation::class)->sweep();

        session()->flash('swal', [
            'icon' => 'success',
            'title' => $created > 0
                ? __('Pengaturan disimpan; :count rekomendasi SP baru ditemukan.', ['count' => $created])
                : __('Pengaturan SP disimpan.'),
        ]);

        $this->redirectRoute('warnings.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pengaturan Surat Peringatan')" :subtitle="__('Ambang poin penerbitan SP1, SP2, dan SP3.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('warnings.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Ambang SP1') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="sp1_threshold"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Poin ≤ nilai ini memicu rekomendasi SP1.') }}</span>
                @error('sp1_threshold')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Ambang SP2') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="sp2_threshold"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Harus lebih kecil dari ambang SP1.') }}</span>
                @error('sp2_threshold')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Ambang SP3') }} <span class="text-red-500">*</span></label>
                <input type="number" wire:model="sp3_threshold"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Harus lebih kecil dari ambang SP2.') }}</span>
                @error('sp3_threshold')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <p class="mb-8 rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800">
            {{ __('Setelah disimpan, sistem langsung memeriksa ulang seluruh siswa dan membuat rekomendasi SP baru bila ada yang memenuhi ambang.') }}
        </p>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('warnings.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
