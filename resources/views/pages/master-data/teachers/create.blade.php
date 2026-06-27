<?php

use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Guru')] class extends Component {
    public string $name = '';

    public string $email = '';

    public ?string $nip = null;

    public ?string $phone = null;

    public string $role = '';

    public string $password = '';

    /**
     * Roles a teacher account may hold.
     *
     * @return array<int, UserRole>
     */
    public function roleOptions(): array
    {
        return [
            UserRole::KepalaSekolah,
            UserRole::WakasekKesiswaan,
            UserRole::GuruBk,
            UserRole::WaliKelas,
            UserRole::GuruPiket,
            UserRole::GuruMapel,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('teachers', 'nip')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, $this->roleOptions()))],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($data['role']);

            Teacher::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'nip' => $data['nip'],
                'phone' => $data['phone'],
            ]);
        });

        session()->flash('swal', ['icon' => 'success', 'title' => __('Guru ditambahkan.')]);

        $this->redirectRoute('master-data.teachers', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Guru')" :subtitle="__('Isi data guru beserta akun login.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.teachers')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <div class="flex mb-4">
                    <div class="flex flex-col mb-4 gap-2">
                        <label class="font-semibold mt-3 mb-1">Foto Profil</label>
                        <span for="avatar" class="text-xs block my-1">Keterangan Upload : (Maksimal: 2MB, Dimensi: 800x800 piksel)</span>
                        <div id="image-preview-placeholder" class="h-[200px] w-[200px] rounded-2xl border-dashed border-2 border-primary bg-gray-300 cursor-pointer" onclick="document.getElementById('image-input').click()">
                        <img id="preview-image" class="w-[195px] h-[195px] object-cover rounded-2xl" src="{{asset('assets/placeholder.png')}}" alt="Avatar Placeholder">
                        </div>
                        <input type="file" name="avatar" id="image-input" style="display: none;" onchange="previewImage(event)">
                        @error('avatar')    
                            <span class="mt-1 text-red-500">{{ $errors->first('avatar') }}</span>
                        @enderror
                    </div>
                </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
           
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama Lengkap') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="Budi Santoso"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">NIP</label>
                <input type="text" wire:model="nip" placeholder="198001012005011001"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('nip')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Telepon') }}</label>
                <input type="text" wire:model="phone" placeholder="08123456789"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('phone')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Peran') }} <span class="text-red-500">*</span></label>
                <x-slim-select wire:model="role" placeholder="{{ __('Pilih peran') }}">
                    <option value="">{{ __('Pilih peran') }}</option>
                    @foreach ($this->roleOptions() as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endforeach
                </x-slim-select>
                @error('role')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email" placeholder="budi@sekolah.sch.id"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('email')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Password') }} <span class="text-red-500">*</span></label>
                <input type="password" wire:model="password"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('password')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.teachers')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>

<script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const previewImage = document.getElementById('preview-image');
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block'; // Tampilkan gambar yang dipilih

                    // Membuat objek gambar dengan dimensi 600x400 piksel
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const maxWidth = 600;
                        const maxHeight = 400;

                        let width = img.width;
                        let height = img.height;

                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }

                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }

                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);
                        previewImage.src = canvas.toDataURL(); // Mengganti gambar dengan dimensi baru
                    };
                };
                reader.readAsDataURL(file);
            } else {
                const previewImage = document.getElementById('preview-image');
                previewImage.src = "{{ asset('/assets/placeholder.png') }}"; // Kembalikan ke gambar placeholder
                previewImage.style.display = 'none'; // Sembunyikan gambar yang dipilih
            }
        }
</script>