<?php

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Models\Permit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Ajukan Izin')] class extends Component {
    use WithFileUploads;

    public string $type = '';

    public ?string $date = null;

    public ?string $reason = null;

    public ?TemporaryUploadedFile $attachment = null;

    public function mount(): void
    {
        // Only accounts linked to a student record can submit (F-24).
        abort_unless(auth()->user()->student !== null, 403);

        $this->date = now()->toDateString();
    }

    /**
     * @return array<int, PermitType>
     */
    public function types(): array
    {
        return PermitType::cases();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PermitType::class)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $student = auth()->user()->student;

        // One live request per type per day: a pending or approved permit for
        // the same date blocks a duplicate submission.
        $duplicate = $student->permits()
            ->where('type', $data['type'])
            ->whereDate('date', $data['date'])
            ->whereIn('status', [PermitStatus::Pending, PermitStatus::Approved])
            ->exists();

        if ($duplicate) {
            $this->addError('type', __('Anda sudah memiliki pengajuan :type untuk tanggal tersebut.', [
                'type' => PermitType::from($data['type'])->label(),
            ]));

            return;
        }

        if ($this->attachment) {
            $data['attachment_path'] = Storage::url($this->attachment->store('permits', 'public'));
        }

        unset($data['attachment']);

        $student->permits()->create($data);

        toast(__('Izin diajukan, menunggu persetujuan.'), 'success');

        $this->redirectRoute('permits.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Ajukan Izin')"
        :subtitle="__('Pengajuan akan diverifikasi oleh Guru Piket atau Wali Kelas Anda.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('permits.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jenis Izin') }} <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ($this->types() as $case)
                        <label @class([
                            'flex cursor-pointer flex-col gap-1 rounded-xl border p-4 transition',
                            'border-primary-500 bg-primary-50 ring-1 ring-primary-500' => $type === $case->value,
                            'border-gray-200 hover:border-gray-300' => $type !== $case->value,
                        ])>
                            <input type="radio" wire:model.live="type" value="{{ $case->value }}" class="sr-only" />
                            <span class="font-semibold text-gray-800">{{ $case->label() }}</span>
                            <span class="text-xs text-gray-500">{{ $case->description() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('type')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Izin') }} <span class="text-red-500">*</span></label>
                <input type="date" wire:model="date" min="{{ now()->toDateString() }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Tanggal berlakunya izin; tidak dapat mundur.') }}</span>
                @error('date')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Lampiran') }}</label>
                <input type="file" wire:model="attachment" accept=".jpg,.jpeg,.png,.pdf"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Opsional. Surat dokter/keterangan. JPG/PNG/PDF, maks 4 MB.') }}</span>
                <div wire:loading wire:target="attachment" class="mt-1 text-xs text-gray-500">{{ __('Mengunggah…') }}</div>
                @error('attachment')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Alasan') }} <span class="text-red-500">*</span></label>
                <textarea wire:model="reason" rows="3" placeholder="{{ __('Jelaskan alasan pengajuan izin Anda.') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('reason')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('permits.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Ajukan') }}</x-ui.button>
        </div>
    </form>
</div>
