<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Akun Kiosk')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $user = User::create([...$data, 'is_active' => true]);
            $user->assignRole(UserRole::Kiosk->value);
        });

        toast(__('Akun kiosk ditambahkan.'), 'success');

        $this->redirectRoute('master-data.kiosk-accounts', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Akun Kiosk')" :subtitle="__('Buat akun login untuk satu tablet absensi.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.kiosk-accounts')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        @include('partials.kiosk-account-fields', ['passwordRequired' => true])

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.kiosk-accounts')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
