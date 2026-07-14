<?php

use App\Enums\PointSource;
use App\Enums\PointType;
use App\Models\AttendanceSetting;
use App\Models\PointRule;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Absensi')] class extends Component {
    public AttendanceSetting $setting;

    public string $late_after = '07:00';

    public string $check_out_after = '15:00';

    public ?int $late_rule_id = null;

    public ?int $alpha_rule_id = null;

    public function mount(): void
    {
        $this->setting = AttendanceSetting::current();
        $this->late_after = substr($this->setting->late_after, 0, 5);
        $this->check_out_after = substr($this->setting->check_out_after, 0, 5);
        $this->late_rule_id = $this->setting->late_rule_id;
        $this->alpha_rule_id = $this->setting->alpha_rule_id;
    }

    /**
     * Active deduction rules from the Absensi source, selectable as the
     * automatic penalty for terlambat / alpha.
     *
     * @return Collection<int, PointRule>
     */
    #[Computed]
    public function pointRules(): Collection
    {
        return PointRule::query()
            ->active()
            ->deductions()
            ->where('source', PointSource::Attendance)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'late_after' => ['required', 'date_format:H:i'],
            'check_out_after' => ['required', 'date_format:H:i', 'after:late_after'],
            'late_rule_id' => ['nullable', 'integer', 'exists:point_rules,id'],
            'alpha_rule_id' => ['nullable', 'integer', 'exists:point_rules,id'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->setting->update([
            ...$data,
            'late_after' => $data['late_after'].':00',
            'check_out_after' => $data['check_out_after'].':00',
        ]);

        session()->flash('swal', ['icon' => 'success', 'title' => __('Pengaturan absensi disimpan.')]);

        $this->redirectRoute('attendance.absensi', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pengaturan Absensi')" :subtitle="__('Atur jam terlambat, jam pulang, dan aturan poin otomatis.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Terlambat Setelah') }} <span class="text-red-500">*</span></label>
                <input type="time" wire:model="late_after"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Absensi masuk setelah jam ini berstatus Terlambat.') }}</span>
                @error('late_after')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Absensi Pulang Mulai') }} <span class="text-red-500">*</span></label>
                <input type="time" wire:model="check_out_after"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Absensi pulang hanya diterima mulai jam ini.') }}</span>
                @error('check_out_after')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Aturan Poin Terlambat') }}</label>
                <select wire:model="late_rule_id"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Tanpa pengurangan poin') }}</option>
                    @foreach ($this->pointRules as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->name }} (-{{ $rule->point }})</option>
                    @endforeach
                </select>
                <span class="mt-1 text-xs text-gray-400">{{ __('Diterapkan otomatis saat siswa terlambat.') }}</span>
                @error('late_rule_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Aturan Poin Alpha') }}</label>
                <select wire:model="alpha_rule_id"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Tanpa pengurangan poin') }}</option>
                    @foreach ($this->pointRules as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->name }} (-{{ $rule->point }})</option>
                    @endforeach
                </select>
                <span class="mt-1 text-xs text-gray-400">{{ __('Diterapkan otomatis saat siswa ditandai Alpha.') }}</span>
                @error('alpha_rule_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
