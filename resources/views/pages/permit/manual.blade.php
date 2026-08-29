<?php

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Models\Permit;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Input Izin Manual')] class extends Component
{
    use WithFileUploads;

    public ?int $student_id = null;

    public string $type = '';

    public ?string $date = null;

    public ?string $reason = null;

    public ?string $note = null;

    public ?TemporaryUploadedFile $attachment = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    /**
     * Students a Guru Piket / admin may record a walk-in permit for.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        return Student::query()->orderBy('name')->get(['id', 'name', 'nis']);
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
            'student_id' => ['required', Rule::exists('students', 'id')],
            'type' => ['required', Rule::enum(PermitType::class)],
            'date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        // Same guard as the student-facing form: one live permit per type per
        // day, so a walk-in entry cannot shadow an existing request.
        $duplicate = Permit::query()
            ->where('student_id', $data['student_id'])
            ->where('type', $data['type'])
            ->whereDate('date', $data['date'])
            ->whereIn('status', [PermitStatus::Pending, PermitStatus::Approved])
            ->exists();

        if ($duplicate) {
            $this->addError('type', __('Siswa ini sudah memiliki pengajuan :type untuk tanggal tersebut.', [
                'type' => PermitType::from($data['type'])->label(),
            ]));

            return;
        }

        if ($this->attachment) {
            $data['attachment_path'] = Storage::url($this->attachment->store('permits', 'public'));
        }

        unset($data['attachment'], $data['note']);

        // The recorder is the approver — the student handed the request over in
        // person, so the permit lands approved and counts for attendance.
        Permit::create([
            ...$data,
            'status' => PermitStatus::Approved,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
            'decision_note' => filled($this->note) ? $this->note : __('Diinput manual oleh :name.', ['name' => auth()->user()->name]),
        ]);

        toast(__('Izin dicatat & langsung disetujui.'), 'success');

        $this->redirectRoute('permits.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Input Izin Manual')"
        :subtitle="__('Catat izin siswa yang diajukan langsung. Izin tercatat sebagai disetujui oleh Anda.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('permits.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-8 grid grid-cols-1 gap-8 md:grid-cols-2">
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
                <label class="mb-1 font-semibold text-gray-600">{{ __('Siswa') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="student_id" placeholder="{{ __('Pilih siswa') }}">
                    <option value="">{{ __('Pilih siswa') }}</option>
                    @foreach ($this->students as $student)
                        <option value="{{ $student->id }}">{{ $student->name }} ({{ $student->nis }})</option>
                    @endforeach
                </x-slim-select>
                @error('student_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Izin') }} <span class="text-red-500">*</span></label>
                <input type="date" wire:model="date"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Boleh diisi mundur untuk izin yang terlambat dicatat.') }}</span>
                @error('date')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Alasan') }} <span class="text-red-500">*</span></label>
                <textarea wire:model="reason" rows="3" placeholder="{{ __('Alasan izin yang disampaikan siswa.') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('reason')
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

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Catatan Persetujuan') }}</label>
                <input type="text" wire:model="note" placeholder="{{ __('Mis. diantar orang tua, sudah dikonfirmasi wali kelas.') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Opsional. Kosongkan untuk memakai catatan otomatis.') }}</span>
                @error('note')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('permits.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
