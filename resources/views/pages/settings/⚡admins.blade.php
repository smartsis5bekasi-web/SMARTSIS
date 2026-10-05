<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → Admin (Super Admin only).
 *
 * Adds a Super Admin account from a name, e-mail and password, and lists who
 * has full access. Access can be taken away, but never from yourself or from
 * the last active admin, so the school cannot lock itself out.
 */
new #[Title('Admin settings')] class extends Component {
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

        Flux::toast(variant: 'success', text: __('Admin :email ditambahkan.', ['email' => $data['email']]));
    }

    /**
     * Take Super Admin access away. An account that has nothing else left is
     * deleted, since it could no longer open any page.
     */
    public function removeAdmin(int $userId): void
    {
        $admin = User::role(UserRole::SuperAdmin->value)->findOrFail($userId);

        if ($admin->is(auth()->user())) {
            Flux::toast(variant: 'danger', text: __('Anda tidak dapat mencabut akses admin Anda sendiri.'));

            return;
        }

        $otherActiveAdmins = User::role(UserRole::SuperAdmin->value)
            ->whereKeyNot($admin->getKey())
            ->where('is_active', true)
            ->exists();

        if (! $otherActiveAdmins) {
            Flux::toast(variant: 'danger', text: __('Harus tersisa minimal satu admin aktif.'));

            return;
        }

        DB::transaction(function () use ($admin): void {
            $admin->removeRole(UserRole::SuperAdmin->value);

            if ($admin->roles()->doesntExist()) {
                $admin->delete();
            }
        });

        unset($this->admins);

        Flux::toast(variant: 'success', text: __('Akses admin :email dicabut.', ['email' => $admin->email]));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Admin settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Admin')" :subheading="__('Tambah akun Super Admin yang memiliki seluruh akses aplikasi')">
        @if ($defaultPasswordAccounts !== [])
            <div class="mt-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <ion-icon name="warning-outline" class="mt-0.5 shrink-0 text-lg"></ion-icon>
                <div>
                    <p class="font-semibold">{{ __('Akun demo berikut masih memakai password bawaan "password":') }}</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($defaultPasswordAccounts as $demoEmail)
                            <li wire:key="default-password-{{ $demoEmail }}">{{ $demoEmail }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2">{{ __('Siapa pun yang tahu email tersebut bisa masuk. Login dengan akun itu lalu ganti password di Pengaturan → Security, atau nonaktifkan akunnya.') }}</p>
                </div>
            </div>
        @endif

        <form wire:submit="addAdmin" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Nama')" type="text" required autocomplete="off" />

            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off"
                :description="__('Dipakai untuk login. Harus belum terdaftar di akun lain.')" />

            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" viewable />

            <flux:input wire:model="password_confirmation" :label="__('Konfirmasi Password')" type="password" required autocomplete="new-password" viewable />

            <flux:button variant="primary" type="submit" data-test="add-admin-button">{{ __('Tambah Admin') }}</flux:button>
        </form>

        <flux:separator variant="subtle" />

        <div class="mt-6">
            <flux:heading>{{ __('Daftar Admin') }}</flux:heading>

            <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
                @foreach ($this->admins as $admin)
                    <li wire:key="admin-{{ $admin->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="flex flex-wrap items-center gap-2 font-medium text-gray-900">
                                {{ $admin->name }}
                                @if ($admin->is(auth()->user()))
                                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700">{{ __('Anda') }}</span>
                                @endif
                                @if ($this->isDemoAccount($admin))
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ __('Akun demo') }}</span>
                                @endif
                                @unless ($admin->is_active)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ __('Nonaktif') }}</span>
                                @endunless
                            </span>
                            <span class="truncate text-sm text-gray-500">{{ $admin->email }}</span>
                        </div>

                        @unless ($admin->is(auth()->user()))
                            <flux:button size="sm" variant="subtle" icon="trash"
                                wire:click="removeAdmin({{ $admin->id }})"
                                wire:confirm="{{ __('Cabut akses admin :email?', ['email' => $admin->email]) }}">
                                {{ __('Cabut') }}
                            </flux:button>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    </x-pages::settings.layout>
</section>
