<?php

use App\Enums\Permission;
use App\Enums\PointSource;
use App\Models\PointRule;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Edit Pelanggaran')] class extends Component {
    use WithFileUploads;

    public Violation $violation;

    public ?int $point_rule_id = null;

    public ?string $occurred_on = null;

    public ?string $chronology = null;

    public ?TemporaryUploadedFile $evidence = null;

    public function mount(Violation $violation): void
    {
        abort_unless($this->canEdit($violation), 403);

        $this->violation = $violation->load('student');
        $this->point_rule_id = $violation->point_rule_id;
        $this->occurred_on = $violation->occurred_on?->toDateString();
        $this->chronology = $violation->chronology;
    }

    /**
     * @return Collection<int, PointRule>
     */
    #[Computed]
    public function ruleOptions(): Collection
    {
        return PointRule::query()
            ->where('source', PointSource::Violation)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'point_rule_id' => ['required', Rule::exists('point_rules', 'id')->where('source', PointSource::Violation->value)->where('is_active', true)],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'chronology' => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function save(): void
    {
        abort_unless($this->canEdit($this->violation), 403);

        $data = $this->validate();

        if ($this->evidence) {
            $data['evidence_path'] = Storage::url($this->evidence->store('violations', 'public'));
        }
        unset($data['evidence']);

        $this->violation->update($data);

        toast(__('Pelanggaran diperbarui.'), 'success');

        $this->redirectRoute('academic.violations', navigate: true);
    }

    /**
     * Only pending records may be edited, by their reporter or a manager (Guru BK).
     */
    private function canEdit(Violation $violation): bool
    {
        if (! $violation->isPending()) {
            return false;
        }

        $user = auth()->user();

        return $user->can(Permission::ManageViolation->value)
            || $user->id === $violation->reported_by;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Pelanggaran')" :subtitle="$violation->student?->name">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('academic.violations')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jenis Pelanggaran') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="point_rule_id">
                    @foreach ($this->ruleOptions as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->name }} (-{{ $rule->point }})</option>
                    @endforeach
                </x-slim-select>
                @error('point_rule_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Kejadian') }} <span class="text-red-500">*</span></label>
                <input type="date" wire:model="occurred_on" max="{{ now()->toDateString() }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('occurred_on')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Kronologi') }} <span class="text-red-500">*</span></label>
                <textarea wire:model="chronology" rows="3"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('chronology')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Ganti Bukti') }}</label>
                @if ($violation->evidence_path)
                    <a href="{{ $violation->evidence_path }}" target="_blank" class="mb-2 text-sm text-primary-600 hover:text-primary-700">{{ __('Lihat bukti saat ini') }}</a>
                @endif
                <input type="file" wire:model="evidence" accept=".jpg,.jpeg,.png,.pdf"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Kosongkan bila tidak diganti. JPG/PNG/PDF, maks 4 MB.') }}</span>
                <div wire:loading wire:target="evidence" class="mt-1 text-xs text-gray-500">{{ __('Mengunggah…') }}</div>
                @error('evidence')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('academic.violations')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
