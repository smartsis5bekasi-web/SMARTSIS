<?php

use App\Models\Classroom;
use App\Models\Major;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Edit Siswa')] class extends Component {
    use WithFileUploads;

    public Student $student;

    public string $name = '';

    public string $nis = '';

    public ?string $nisn = null;

    public ?string $gender = null;

    public ?string $birth_date = null;

    public ?string $address = null;

    public ?int $classroom_id = null;

    public ?int $major_id = null;

    public ?int $teacher_id = null;

    public ?TemporaryUploadedFile $avatar = null;

    public function mount(Student $student): void
    {
        $this->student = $student;
        $this->name = $student->name;
        $this->nis = $student->nis;
        $this->nisn = $student->nisn;
        $this->gender = $student->gender;
        $this->birth_date = $student->birth_date?->format('Y-m-d');
        $this->address = $student->address;
        $this->classroom_id = $student->classroom_id;
        $this->major_id = $student->major_id;
        $this->teacher_id = $student->teacher_id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($this->student->id)],
            'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')->ignore($this->student->id)],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')],
            'major_id' => ['nullable', Rule::exists('majors', 'id')],
            'teacher_id' => ['nullable', Rule::exists('teachers', 'id')],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classroomOptions(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
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

        if ($this->avatar) {
            $data['avatar_url'] = Storage::url($this->avatar->store('students', 'public'));
        }

        unset($data['avatar']);

        $this->student->update($data);

        session()->flash('swal', ['icon' => 'success', 'title' => __('Siswa diperbarui.')]);

        $this->redirectRoute('master-data.students.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Siswa')" :subtitle="__('Perbarui data siswa.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.students.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-8 flex flex-col gap-2" x-data="{ photoPreview: null }">
            <label class="font-semibold text-gray-600">{{ __('Foto Profil') }}</label>
            <span class="text-xs text-gray-500">{{ __('Keterangan Upload : (Maksimal: 2MB, Dimensi: 800x800 piksel)') }}</span>
            <div class="h-[200px] w-[200px] cursor-pointer rounded-2xl border-2 border-dashed border-primary-400 bg-gray-100"
                @click="$refs.avatar.click()">
                <img class="h-[196px] w-[196px] rounded-2xl object-cover"
                    src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}"
                    x-bind:src="photoPreview ?? '{{ $student->avatar_url ?? asset('assets/placeholder.png') }}'"
                    alt="{{ $student->name }}">
            </div>
            <input type="file" wire:model="avatar" x-ref="avatar" class="hidden" accept="image/*"
                x-on:change="
                    const file = $refs.avatar.files[0];
                    if (! file) { photoPreview = null; return; }
                    const reader = new FileReader();
                    reader.onload = (e) => { photoPreview = e.target.result; };
                    reader.readAsDataURL(file);
                ">
            <div wire:loading wire:target="avatar" class="text-xs text-gray-500">{{ __('Mengunggah…') }}</div>
            @error('avatar')
                <span class="text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="mb-8 grid grid-cols-1 gap-8 md:grid-cols-2">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="Muhamad Hafidz"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">NIS <span class="text-red-500">*</span></label>
                <input type="text" wire:model="nis" placeholder="0012345678"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('nis')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">NISN</label>
                <input type="text" wire:model="nisn" placeholder="0012345678"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('nisn')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Jenis Kelamin') }}</label>
                <x-slim-select wire:model="gender" placeholder="{{ __('Pilih jenis kelamin') }}">
                    <option value="">{{ __('Pilih jenis kelamin') }}</option>
                    <option value="L">{{ __('Laki-laki') }}</option>
                    <option value="P">{{ __('Perempuan') }}</option>
                </x-slim-select>
                @error('gender')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Lahir') }}</label>
                <input type="date" wire:model="birth_date"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('birth_date')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Kelas') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="classroom_id" placeholder="{{ __('Pilih kelas') }}">
                    <option value="">{{ __('Pilih kelas') }}</option>
                    @foreach ($this->classroomOptions as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </x-slim-select>
                @error('classroom_id')
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
                <label class="mb-1 font-semibold text-gray-600">{{ __('Guru') }}</label>
                <x-slim-select wire:model="teacher_id" placeholder="{{ __('Pilih guru') }}">
                    <option value="">{{ __('Pilih guru') }}</option>
                    @foreach ($this->teacherOptions as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                    @endforeach
                </x-slim-select>
                @error('teacher_id')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col md:col-span-2">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Alamat') }}</label>
                <textarea wire:model="address" rows="3" placeholder="{{ __('Alamat lengkap') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                @error('address')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.students.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
