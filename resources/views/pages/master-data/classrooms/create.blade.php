<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Kelas')] class extends Component {
    public string $name = '';

    public ?int $major_id = null;

    public ?int $academic_year_id = null;

    public ?int $homeroom_teacher_id = null;

    public function mount(): void
    {
        $this->academic_year_id = AcademicYear::query()->where('is_active', true)->value('id');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('classrooms', 'name')
                    ->where(fn ($query) => $query->where('academic_year_id', $this->academic_year_id)),
            ],
            'major_id' => ['nullable', Rule::exists('majors', 'id')],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')],
            'homeroom_teacher_id' => ['nullable', Rule::exists('teachers', 'id')],
        ];
    }

    /**
     * @return Collection<int, Major>
     */
    #[Computed]
    public function majorOptions(): Collection
    {
        return Major::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    #[Computed]
    public function yearOptions(): Collection
    {
        return AcademicYear::query()->orderByDesc('name')->get();
    }

    /**
     * @return Collection<int, Teacher>
     */
    #[Computed]
    public function teacherOptions(): Collection
    {
        return Teacher::query()->orderBy('name')->get();
    }

    public function save(): void
    {
        $data = $this->validate();

        Classroom::create($data);

        toast(__('Kelas ditambahkan.'), 'success');

        $this->redirectRoute('master-data.classrooms', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Kelas')" :subtitle="__('Isi detail kelas baru.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.classrooms')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama Kelas') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="XI IPA 1"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tahun Ajaran') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="academic_year_id" placeholder="{{ __('Pilih tahun ajaran') }}">
                    <option value="">{{ __('Pilih tahun ajaran') }}</option>
                    @foreach ($this->yearOptions as $year)
                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                    @endforeach
                </x-slim-select>
                @error('academic_year_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jurusan') }}</label>
                <x-slim-select wire:model="major_id" placeholder="{{ __('Pilih jurusan') }}">
                    <option value="">{{ __('Pilih jurusan') }}</option>
                    @foreach ($this->majorOptions as $major)
                        <option value="{{ $major->id }}">{{ $major->name }}</option>
                    @endforeach
                </x-slim-select>
                @error('major_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Wali Kelas') }}</label>
                <x-slim-select wire:model="homeroom_teacher_id" placeholder="{{ __('Pilih wali kelas') }}">
                    <option value="">{{ __('Pilih wali kelas') }}</option>
                    @foreach ($this->teacherOptions as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                    @endforeach
                </x-slim-select>
                @error('homeroom_teacher_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.classrooms')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
