<?php

use App\Enums\Permission;
use App\Enums\PointSource;
use App\Enums\UserRole;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Ajukan Prestasi')] class extends Component {
    use WithFileUploads;

    public ?int $student_id = null;

    public ?int $point_rule_id = null;

    public string $title = '';

    public string $level = '';

    public ?string $achieved_on = null;

    public ?string $description = null;

    public ?TemporaryUploadedFile $evidence = null;

    public function mount(): void
    {
        if ($this->isStudent()) {
            $student = auth()->user()->student;
            abort_if($student === null, 403, __('Akun Anda belum tertaut ke data siswa.'));
            $this->student_id = $student->id;
        }
    }

    public function isStudent(): bool
    {
        return auth()->user()->primaryRole() === UserRole::Siswa;
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageAchievement->value);
    }

    /**
     * Active achievement point rules a submitter may choose ("jenis prestasi").
     *
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
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        return Student::query()->orderBy('name')->get(['id', 'name', 'nis']);
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
            'student_id' => ['required', Rule::exists('students', 'id')],
            'point_rule_id' => ['required', Rule::exists('point_rules', 'id')->where('source', PointSource::Achievement->value)->where('is_active', true)],
            'title' => ['required', 'string', 'max:150'],
            'level' => ['required', Rule::in($this->levels())],
            'achieved_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence' => [$this->isStudent() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->evidence) {
            $data['evidence_path'] = Storage::url($this->evidence->store('achievements', 'public'));
        }
        unset($data['evidence']);

        $data['input_by'] = auth()->id();

        $achievement = Achievement::create($data);

        // Authorised staff input is trusted and applies points immediately;
        // student submissions stay pending for verification.
        if ($this->canManage()) {
            $achievement->approve(auth()->user());
            $message = __('Prestasi ditambahkan & disetujui.');
        } else {
            $message = __('Prestasi diajukan, menunggu verifikasi.');
        }

        session()->flash('swal', ['icon' => 'success', 'title' => $message]);

        $this->redirectRoute('academic.achievements', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="$this->isStudent() ? __('Ajukan Prestasi') : __('Tambah Prestasi')"
        :subtitle="__('Lengkapi detail prestasi dan unggah bukti.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('academic.achievements')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            @if (! $this->isStudent())
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
            @endif

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Judul Prestasi') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="title" placeholder="{{ __('mis. Juara 1 LKS Web Provinsi 2026') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('title')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jenis Prestasi') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="point_rule_id" placeholder="{{ __('Pilih jenis prestasi') }}">
                    <option value="">{{ __('Pilih jenis prestasi') }}</option>
                    @foreach ($this->ruleOptions as $rule)
                        <option value="{{ $rule->id }}">{{ $rule->name }} (+{{ $rule->point }})</option>
                    @endforeach
                </x-slim-select>
                <span class="mt-1 text-xs text-gray-400">{{ __('Poin mengikuti aturan yang dipilih.') }}</span>
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
                <textarea wire:model="description" rows="3" placeholder="{{ __('Keterangan tambahan (opsional).') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('description')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">
                    {{ __('Bukti') }}
                    @if ($this->isStudent()) <span class="text-red-500">*</span> @endif
                </label>
                <input type="file" wire:model="evidence" accept=".jpg,.jpeg,.png,.pdf"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-xs text-gray-400">{{ __('Format JPG/PNG/PDF, maks 4 MB.') }}</span>
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
            <x-ui.button variant="primary" type="submit">
                {{ $this->isStudent() ? __('Ajukan') : __('Simpan') }}
            </x-ui.button>
        </div>
    </form>
</div>
