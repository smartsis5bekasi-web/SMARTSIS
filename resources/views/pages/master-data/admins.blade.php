<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan Lanjut → Akun Admin (Super Admin only).
 *
 * Adds a Super Admin account from a name, e-mail and password, and lists who
 * has full access. Access can be taken away, but never from yourself or from
 * the last active admin, so the school cannot lock itself out.
 */
new #[Title('Akun Admin')] class extends Component {
    use PasswordValidationRules;
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Seeded staff accounts still signing in with "password". Checked once on
     * mount — hashing on every request would make the page slow.
     *
     * @var array<int, string>
     */
    public array $defaultPasswordAccounts = [];

    public function mount(): void
    {
        $this->defaultPasswordAccounts = User::query()
            ->whereIn('email', DemoAccountSeeder::staffEmails())
            ->where('is_active', true)
            ->get(['email', 'password'])
            ->filter(fn (User $user): bool => Hash::check('password', $user->password))
            ->pluck('email')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::role(UserRole::SuperAdmin->value)->orderBy('name')->get();
    }

    public function isDemoAccount(User $user): bool
    {
        return in_array($user->email, DemoAccountSeeder::emails(), true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('nama'),
            'email' => __('email'),
            'password' => __('password'),
        ];
    }

    public function addAdmin(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            // Created by an admin who vouches for the address, so the new
            // admin is not stopped at the verify-email screen.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(UserRole::SuperAdmin->value);
        });

        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        unset($this->admins);

        $this->dispatch('swal', icon: 'success', title: __('Admin :email ditambahkan.', ['email' => $data['email']]));
    }

    /**
     * Take Super Admin access away. An account that has nothing else left is
     * deleted, since it could no longer open any page.
     */
    public function removeAdmin(int $userId): void
    {
        $admin = User::role(UserRole::SuperAdmin->value)->findOrFail($userId);

        if ($admin->is(auth()->user())) {
            $this->dispatch('swal', icon: 'error', title: __('Anda tidak dapat mencabut akses admin Anda sendiri.'));

            return;
        }

        $otherActiveAdmins = User::role(UserRole::SuperAdmin->value)
            ->whereKeyNot($admin->getKey())
            ->where('is_active', true)
            ->exists();

        if (! $otherActiveAdmins) {
            $this->dispatch('swal', icon: 'error', title: __('Harus tersisa minimal satu admin aktif.'));

            return;
        }

        DB::transaction(function () use ($admin): void {
            $admin->removeRole(UserRole::SuperAdmin->value);

            if ($admin->roles()->doesntExist()) {
                $admin->delete();
            }
        });

        unset($this->admins);

        $this->dispatch('swal', icon: 'success', title: __('Akses admin :email dicabut.', ['email' => $admin->email]));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Akun Admin')" :subtitle="__('Tambah akun Super Admin yang memiliki seluruh akses aplikasi.')" />

    @if ($defaultPasswordAccounts !== [])
        <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <ion-icon name="warning-outline" class="mt-0.5 shrink-0 text-xl"></ion-icon>
            <div class="min-w-0">
                <p class="font-semibold">{{ __('Akun demo berikut masih memakai password bawaan "password":') }}</p>
                <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($defaultPasswordAccounts as $demoEmail)
                        <li wire:key="default-password-{{ $demoEmail }}" class="break-all">{{ $demoEmail }}</li>
                    @endforeach
                </ul>
                <p class="mt-2">{{ __('Siapa pun yang tahu email tersebut bisa masuk. Login dengan akun itu lalu ganti password di Settings → Security, atau cabut / nonaktifkan akunnya.') }}</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <form wire:submit="addAdmin" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-semibold text-gray-800">{{ __('Tambah Admin') }}</h2>

            <div class="mb-6 flex flex-col gap-5">
                <div class="flex flex-col">
                    <label for="admin-name" class="mb-1 font-semibold text-gray-600">{{ __('Nama') }} <span class="text-red-500">*</span></label>
                    <input id="admin-name" type="text" wire:model="name" autocomplete="off" placeholder="{{ __('Operator Sekolah') }}"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('name')
                        <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="admin-email" class="mb-1 font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                    <input id="admin-email" type="email" wire:model="email" autocomplete="off" placeholder="operator@sman5bekasi.sch.id"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <span class="mt-1 text-xs text-gray-400">{{ __('Dipakai untuk login. Harus belum terdaftar di akun lain.') }}</span>
                    @error('email')
                        <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="admin-password" class="mb-1 font-semibold text-gray-600">{{ __('Password') }} <span class="text-red-500">*</span></label>
                    <input id="admin-password" type="password" wire:model="password" autocomplete="new-password" placeholder="{{ __('Minimal 8 karakter') }}"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('password')
                        <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex flex-col">
                    <label for="admin-password-confirmation" class="mb-1 font-semibold text-gray-600">{{ __('Konfirmasi Password') }} <span class="text-red-500">*</span></label>
                    <input id="admin-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                </div>
            </div>

            <div class="flex justify-end">
                <x-ui.button variant="primary" type="submit" icon="add-outline" class="cursor-pointer" data-test="add-admin-button">
                    {{ __('Tambah Admin') }}
                </x-ui.button>
            </div>
        </form>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-3">
            <h2 class="mb-4 font-semibold text-gray-800">{{ __('Daftar Admin') }}</h2>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 whitespace-nowrap text-gray-700">
                        @foreach ($this->admins as $admin)
                            <tr wire:key="admin-{{ $admin->id }}" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <span class="flex items-center gap-2">
                                        {{ $admin->name }}
                                        @if ($admin->is(auth()->user()))
                                            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700">{{ __('Anda') }}</span>
                                        @endif
                                        @if ($this->isDemoAccount($admin))
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ __('Akun demo') }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $admin->email }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-700' => $admin->is_active,
                                        'bg-gray-100 text-gray-600' => ! $admin->is_active,
                                    ])>{{ $admin->is_active ? __('Aktif') : __('Nonaktif') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end">
                                        @unless ($admin->is(auth()->user()))
                                            <x-ui.delete-button :wire-id="$admin->id" method="removeAdmin"
                                                :title="__('Cabut akses admin :email?', ['email' => $admin->email])"
                                                :text="__('Akun tanpa peran lain akan dihapus.')" />
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
