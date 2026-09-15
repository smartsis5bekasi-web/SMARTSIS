<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ubah Akun Kiosk')] class extends Component {
    #[Locked]
    public int $accountId;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public function mount(User $user): void
    {
        abort_unless($user->hasRole(UserRole::Kiosk->value), 404);

        $this->accountId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->accountId)],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $account = User::role(UserRole::Kiosk->value)->findOrFail($this->accountId);

        $account->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(filled($data['password']) ? ['password' => $data['password']] : []),
        ]);

        toast(__('Akun kiosk diperbarui.'), 'success');

        $this->redirectRoute('master-data.kiosk-accounts', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Ubah Akun Kiosk')" :subtitle="$name">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.kiosk-accounts')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        @include('partials.kiosk-account-fields', ['passwordRequired' => false])

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.kiosk-accounts')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
