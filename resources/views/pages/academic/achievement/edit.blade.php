<?php

use App\Enums\PointSource;
use App\Models\Achievement;
use App\Models\PointRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Edit Prestasi')] class extends Component {
    use WithFileUploads;

    public Achievement $achievement;

    public ?int $point_rule_id = null;

    public string $title = '';

    public string $level = '';

    public ?string $achieved_on = null;

    public ?string $description = null;

    public ?TemporaryUploadedFile $evidence = null;

    public function mount(Achievement $achievement): void
    {
        abort_unless($this->canEdit($achievement), 403);

        $this->achievement = $achievement->load('student');
        $this->point_rule_id = $achievement->point_rule_id;
        $this->title = $achievement->title;
        $this->level = (string) $achievement->level;
        $this->achieved_on = $achievement->achieved_on?->toDateString();
        $this->description = $achievement->description;
    }

    /**
     * @return Collection<int, PointRule>
     */
    #[Computed]
    public function ruleOptions(): Collection
    {
        return PointRule::query()
            ->where('source', PointSource::Achievement)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function levels(): array
    {
        return ['Sekolah', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'point_rule_id' => ['required', Rule::exists('point_rules', 'id')->where('source', PointSource::Achievement->value)->where('is_active', true)],
            'title' => ['required', 'string', 'max:150'],
            'level' => ['required', Rule::in($this->levels())],
            'achieved_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function save(): void
    {
        abort_unless($this->canEdit($this->achievement), 403);

        $data = $this->validate();

        if ($this->evidence) {
            $data['evidence_path'] = Storage::url($this->evidence->store('achievements', 'public'));
        }
        unset($data['evidence']);

        $ruleChanged = (int) $this->point_rule_id !== (int) $this->achievement->point_rule_id;

        $this->achievement->update($data);

        // A verified record already wrote a point log; correcting its rule has
        // to reverse the old award and grant the new one, or the balance drifts.
        if ($ruleChanged) {
            $this->achievement->resyncApprovedPoints(auth()->user());
        }

        toast(__('Prestasi diperbarui.'), 'success');

        $this->redirectRoute('academic.achievements', navigate: true);
    }

    private function canEdit(Achievement $achievement): bool
    {
        return $achievement->isEditableBy(auth()->user());
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Prestasi')"
        :subtitle="$achievement->student?->name . ' · ' . $achievement->status->label()">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('academic.achievements')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Judul Prestasi') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="title"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('title')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jenis Prestasi') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="point_rule_id">
                    @foreach ($this->ruleOptions as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->name }} (+{{ $rule->point }})</option>
                    @endforeach
                </x-slim-select>
                @error('point_rule_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tingkat') }} <span class="text-red-500">*</span></label>
                <select wire:model="level"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Pilih tingkat') }}</option>
                    @foreach ($this->levels() as $levelOption)
                        <option value="{{ $levelOption }}">{{ $levelOption }}</option>
                    @endforeach
                </select>
                @error('level')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Prestasi') }} <span class="text-red-500">*</span></label>
                <input type="date" wire:model="achieved_on" max="{{ now()->toDateString() }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('achieved_on')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Deskripsi') }}</label>
                <textarea wire:model="description" rows="3"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('description')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Ganti Bukti') }}</label>
                @if ($achievement->evidence_path)
                    <a href="{{ $achievement->evidence_path }}" target="_blank" class="mb-2 text-sm text-primary-600 hover:text-primary-700">{{ __('Lihat bukti saat ini') }}</a>
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
            <x-ui.button variant="secondary" :href="route('academic.achievements')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
