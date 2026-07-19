<?php

use App\Enums\UserRole;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Guru')] class extends Component {
    public Teacher $teacher;

    public string $name = '';

    public string $email = '';

    public ?string $nip = null;

    public ?string $phone = null;

    public string $role = '';

    public string $password = '';

    public function mount(Teacher $teacher): void
    {
        $teacher->loadMissing('user');

        $this->teacher = $teacher;
        $this->name = $teacher->name;
        $this->email = $teacher->user?->email ?? '';
        $this->nip = $teacher->nip;
        $this->phone = $teacher->phone;
        $this->role = $teacher->user?->primaryRole()?->value ?? '';
    }

    /**
     * Roles a teacher account may hold.
     *
     * @return array<int, UserRole>
     */
    public function roleOptions(): array
    {
        return UserRole::teacherRoles();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $userId = $this->teacher->user_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('teachers', 'nip')->ignore($this->teacher->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, $this->roleOptions()))],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $user = $this->teacher->user;

            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if (filled($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->syncRoles([$data['role']]);

            $this->teacher->update(['name' => $data['name'], 'nip' => $data['nip'], 'phone' => $data['phone']]);
        });

        toast(__('Data guru diperbarui.'), 'success');

        $this->redirectRoute('master-data.teachers', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Guru')" :subtitle="__('Perbarui data guru beserta akun login.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.teachers')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
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
                <label class="mb-1 font-semibold text-gray-600">{{ __('Password') }}</label>
                <input type="password" wire:model="password"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <span class="mt-1 text-sm text-gray-500">{{ __('Biarkan kosong jika tidak ingin mengubah.') }}</span>
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
