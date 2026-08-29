<?php

use App\Enums\UserRole;
use App\Models\ParentGuardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Tambah Orang Tua')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public string $password = '';

    public ?TemporaryUploadedFile $avatar = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $avatarUrl = null;

        if ($this->avatar) {
            $avatarUrl = Storage::url($this->avatar->store('parents', 'public'));
        }

        DB::transaction(function () use ($data, $avatarUrl): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole(UserRole::OrangTua->value);

            ParentGuardian::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'avatar_url' => $avatarUrl,
                'phone' => $data['phone'],
            ]);
        });

        toast(__('Data orang tua ditambahkan.'), 'success');

        $this->redirectRoute('master-data.parents.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Orang Tua')" :subtitle="__('Isi data orang tua beserta akun login.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.parents.index')" wire:navigate>
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
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama Lengkap') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name" placeholder="Siti Aminah"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
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
                <label class="mb-1 font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email" placeholder="siti@email.com"
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
            <x-ui.button variant="secondary" :href="route('master-data.parents.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>