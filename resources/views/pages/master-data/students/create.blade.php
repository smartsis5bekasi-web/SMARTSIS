<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Tambah Siswa')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $nis = '';

    public ?string $nisn = null;

    public ?string $gender = null;

    public ?string $birth_date = null;

    public ?string $address = null;

    public ?int $classroom_id = null;

    public ?int $major_id = null;

    public ?int $teacher_id = null;

    public ?TemporaryUploadedFile $avatar = null;

    /**
     * @var array<int, array{parent_id: ?int, search: string, creatingNew: bool, name: string, email: string, phone: string, relationship: string}>
     */
    public array $parents = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $maxDate = now()->subYears(14)->format('Y-m-d');
        $minDate = now()->subYears(20)->format('Y-m-d');

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')],
            'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['required', 'date', "after_or_equal:{$minDate}", "before_or_equal:{$maxDate}"],
            'address' => ['nullable', 'string', 'max:255'],
            'classroom_id' => ['required', Rule::exists('classrooms', 'id')],
            'major_id' => ['nullable', Rule::exists('majors', 'id')],
            'teacher_id' => ['nullable', Rule::exists('teachers', 'id')],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'parents' => ['array'],
        ];

        foreach ($this->parents as $index => $row) {
            $isNew = blank($row['parent_id'] ?? null);

            $rules["parents.{$index}.relationship"] = ['required', Rule::in(self::relationshipOptions())];
            $rules["parents.{$index}.parent_id"] = ['nullable', Rule::exists('parents', 'id')];

            if ($isNew) {
                $rules["parents.{$index}.name"] = ['required', 'string', 'max:100'];
                $rules["parents.{$index}.email"] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
                $rules["parents.{$index}.phone"] = ['required', 'string', 'max:20'];
            }
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'birth_date.before_or_equal' => __('Umur siswa minimal harus 14 tahun.'),
            'birth_date.after_or_equal' => __('Umur siswa maksimal 20 tahun.'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function relationshipOptions(): array
    {
        return ['Ayah', 'Ibu', 'Wali'];
    }

    public function addParent(): void
    {
        $this->parents[] = [
            'parent_id' => null,
            'search' => '',
            'creatingNew' => false,
            'name' => '',
            'email' => '',
            'phone' => '',
            'relationship' => 'Ayah',
        ];
    }

    public function removeParent(int $index): void
    {
        unset($this->parents[$index]);
        $this->parents = array_values($this->parents);
        $this->resetErrorBag('parents.*');
    }

    /**
     * Orang tua yang cocok dengan pencarian pada baris tertentu (maks 5 hasil).
     *
     * @return Collection<int, ParentGuardian>
     */
    public function parentSearchResults(int $index): Collection
    {
        $term = trim($this->parents[$index]['search'] ?? '');

        if ($term === '') {
            return collect();
        }

        return ParentGuardian::query()
            ->with('user')
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $uq) => $uq->where('email', 'like', "%{$term}%")))
            ->limit(5)
            ->get();
    }

    public function selectParent(int $index, int $parentId): void
    {
        $parent = ParentGuardian::query()->with('user')->findOrFail($parentId);

        $this->parents[$index]['parent_id'] = $parent->id;
        $this->parents[$index]['name'] = $parent->name;
        $this->parents[$index]['email'] = $parent->user?->email ?? '';
        $this->parents[$index]['phone'] = $parent->phone ?? '';
        $this->parents[$index]['search'] = '';
    }

    public function clearParentSelection(int $index): void
    {
        $this->parents[$index]['parent_id'] = null;
        $this->parents[$index]['name'] = '';
        $this->parents[$index]['email'] = '';
        $this->parents[$index]['phone'] = '';
        $this->parents[$index]['search'] = '';
        $this->parents[$index]['creatingNew'] = false;
    }

    public function showCreateNewParent(int $index): void
    {
        $this->parents[$index]['creatingNew'] = true;
        $this->parents[$index]['search'] = '';
    }

    public function cancelCreateNewParent(int $index): void
    {
        $this->parents[$index]['creatingNew'] = false;
        $this->parents[$index]['name'] = '';
        $this->parents[$index]['email'] = '';
        $this->parents[$index]['phone'] = '';
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

    /**
     * Format password default akun orang tua: SMARTSIS-{5 karakter acak}-{no telp}
     */
    private function generateParentPassword(string $phone): string
    {
        return 'SMARTSIS-'.Str::lower(Str::random(5)).'-'.$phone;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->avatar) {
            $data['avatar_url'] = Storage::url($this->avatar->store('students', 'public'));
        }

        $parentRows = $data['parents'] ?? [];
        $email = $data['email'];
        $password = $data['password'];
        unset($data['avatar'], $data['parents'], $data['email'], $data['password']);

        DB::transaction(function () use ($data, $email, $password, $parentRows) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);
            $user->assignRole(UserRole::Siswa->value);

            $student = Student::create([...$data, 'user_id' => $user->id]);

            foreach ($parentRows as $row) {
                if (filled($row['parent_id'] ?? null)) {
                    $parent = ParentGuardian::query()->findOrFail($row['parent_id']);
                } else {
                    $parentUser = User::create([
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'password' => $this->generateParentPassword($row['phone']),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]);
                    $parentUser->assignRole(UserRole::OrangTua->value);

                    $parent = ParentGuardian::create([
                        'user_id' => $parentUser->id,
                        'name' => $row['name'],
                        'phone' => $row['phone'],
                    ]);
                }

                $student->parents()->attach($parent->id, ['relationship' => $row['relationship']]);
            }
        });

        toast(__('Siswa ditambahkan.'), 'success');

        $this->redirectRoute('master-data.students.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Siswa')" :subtitle="__('Isi data siswa.')">
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
                    src="{{ asset('assets/placeholder.png') }}"
                    x-bind:src="photoPreview ?? '{{ asset('assets/placeholder.png') }}'"
                    alt="{{ __('Foto Profil') }}">
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
                <input type="text" wire:model="name" placeholder="Nama Siswa"
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
            <label class="mb-1 font-semibold text-gray-600">{{ __('Tanggal Lahir') }} <span class="text-red-500">*</span></label>
            <input type="date" 
                wire:model="birth_date"
                min="{{ now()->subYears(20)->format('Y-m-d') }}"
                max="{{ now()->subYears(14)->format('Y-m-d') }}"
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

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email" placeholder="siswa@email.com"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('email')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Password') }} <span class="text-red-500">*</span></label>
                <input type="password" wire:model="password" placeholder="{{ __('Minimal 8 karakter') }}"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('password')
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

       <div class="mb-8 flex flex-col gap-4">
    <div class="flex items-center justify-between gap-4">
        <div class="flex flex-col">
            <label class="font-semibold text-gray-600">{{ __('Data Orang Tua / Wali') }}</label>
            <span class="text-xs text-gray-500">{{ __('Cari orang tua yang sudah terdaftar, atau buat akun baru.') }}</span>
        </div>
        <x-ui.button variant="secondary" icon="add-outline" wire:click="addParent">
            {{ __('Tambah Orang Tua') }}
        </x-ui.button>
    </div>

    @forelse ($parents as $index => $row)
        <div wire:key="parent-row-{{ $index }}" class="rounded-lg border border-gray-100 bg-gray-50 p-4">
            @if ($row['parent_id'])
                {{-- Mode: sudah dipilih --}}
                <div class="flex flex-col gap-4 md:flex-row md:items-start">
                    <div class="flex flex-1 items-center gap-3 rounded-md border border-primary-200 bg-primary-50 px-3 py-2.5">
                        <ion-icon name="checkmark-circle" class="text-lg text-primary-600"></ion-icon>
                        <div class="flex flex-col text-sm">
                            <span class="font-semibold text-gray-800">{{ $row['name'] }}</span>
                            <span class="text-xs text-gray-500">{{ $row['email'] }} &middot; {{ $row['phone'] ?: '—' }}</span>
                        </div>
                        <button type="button" wire:click="clearParentSelection({{ $index }})" class="ml-auto text-xs font-medium text-primary-600 hover:underline">
                            {{ __('Ganti') }}
                        </button>
                    </div>

                    <div class="flex flex-col md:w-[170px]">
                        <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('Hubungan') }} <span class="text-red-500">*</span></label>
                        <select wire:model="parents.{{ $index }}.relationship"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach (self::relationshipOptions() as $relationship)
                                <option value="{{ $relationship }}">{{ $relationship }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" wire:click="removeParent({{ $index }})" title="{{ __('Hapus') }}"
                        class="mt-6 inline-flex items-center justify-center rounded-md p-2 text-red-500 transition hover:bg-red-50">
                        <ion-icon name="trash-outline" class="text-xl"></ion-icon>
                    </button>
                </div>
            @elseif (! $row['creatingNew'])
                {{-- Mode: cari orang tua --}}
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1">
                            <input type="text" wire:model.live.debounce.300ms="parents.{{ $index }}.search"
                                placeholder="{{ __('Cari nama, email, atau telepon orang tua...') }}"
                                class="w-full rounded-md border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                            </div>
                        </div>
                        <x-ui.button variant="secondary" icon="person-add-outline" wire:click="showCreateNewParent({{ $index }})">
                            {{ __('Buat Baru') }}
                        </x-ui.button>
                        <button type="button" wire:click="removeParent({{ $index }})" title="{{ __('Hapus') }}"
                            class="inline-flex items-center justify-center rounded-md p-2 text-red-500 transition hover:bg-red-50">
                            <ion-icon name="trash-outline" class="text-xl"></ion-icon>
                        </button>
                    </div>

                    @if (trim($row['search']) !== '')
                        <div class="flex flex-col gap-1.5 rounded-md border border-gray-200 bg-white p-2">
                            @forelse ($this->parentSearchResults($index) as $result)
                                <button type="button" wire:key="result-{{ $index }}-{{ $result->id }}"
                                    wire:click="selectParent({{ $index }}, {{ $result->id }})"
                                    class="flex flex-col rounded-md px-3 py-2 text-left text-sm transition hover:bg-primary-50">
                                    <span class="font-medium text-gray-800">{{ $result->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $result->user?->email ?? '—' }} &middot; {{ $result->phone ?? '—' }}</span>
                                </button>
                            @empty
                                <p class="px-3 py-2 text-sm text-gray-400">{{ __('Tidak ditemukan. Klik "Buat Baru" untuk mendaftarkan.') }}</p>
                            @endforelse
                        </div>
                    @endif

                    <div class="flex flex-col md:w-[170px]">
                        <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('Hubungan') }} <span class="text-red-500">*</span></label>
                        <select wire:model="parents.{{ $index }}.relationship"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach (self::relationshipOptions() as $relationship)
                                <option value="{{ $relationship }}">{{ $relationship }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @else
                {{-- Mode: buat baru --}}
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">{{ __('Buat Akun Orang Tua Baru') }}</span>
                        <button type="button" wire:click="cancelCreateNewParent({{ $index }})" class="text-xs font-medium text-gray-500 hover:underline">
                            {{ __('Batal, cari lagi') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('Nama Orang Tua') }} <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="parents.{{ $index }}.name" placeholder="{{ __('Nama lengkap') }}"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @error('parents.'.$index.'.name')
                                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                            <input type="email" wire:model="parents.{{ $index }}.email" placeholder="ortu@email.com"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @error('parents.'.$index.'.email')
                                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('No. HP') }} <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="parents.{{ $index }}.phone" placeholder="08xxxxxxxxxx" inputmode="numeric"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <span class="mt-1 text-xs text-gray-400">{{ __('Dipakai sebagai bagian dari password default akun.') }}</span>
                            @error('parents.'.$index.'.phone')
                                <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex flex-col">
                            <label class="mb-1 text-xs font-semibold text-gray-600">{{ __('Hubungan') }} <span class="text-red-500">*</span></label>
                            <select wire:model="parents.{{ $index }}.relationship"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                @foreach (self::relationshipOptions() as $relationship)
                                    <option value="{{ $relationship }}">{{ $relationship }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <p class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
            {{ __('Belum ada data orang tua. Klik "Tambah Orang Tua" untuk menambahkan.') }}
        </p>
    @endforelse
</div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.students.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>