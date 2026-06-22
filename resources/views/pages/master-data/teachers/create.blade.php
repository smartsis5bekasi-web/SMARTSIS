<?php

use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;
use Flux\Flux;
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

        Flux::toast(variant: 'success', text: __('Guru ditambahkan.'));

        $this->redirectRoute('master-data.teachers', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Tambah Guru') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Isi data guru beserta akun login.') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('master-data.teachers') }}" wire:navigate>
            {{ __('Kembali') }}
        </flux:button>
    </div>

    <form wire:submit="save">
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
            <flux:button variant="filled" href="{{ route('master-data.teachers') }}" wire:navigate>
                {{ __('Batal') }}
            </flux:button>
            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </div>
    </form>
</div>
