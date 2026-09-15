<?php

use App\Enums\UserRole;
use App\Livewire\Concerns\TogglesUserActiveStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Akun Kiosk')] class extends Component {
    use TogglesUserActiveStatus;
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function accounts(): LengthAwarePaginator
    {
        return User::role(UserRole::Kiosk->value)
            ->when(filled($this->search), fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('email', 'like', '%'.trim($this->search).'%'),
            ))
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(int $userId): void
    {
        $account = User::role(UserRole::Kiosk->value)->findOrFail($userId);
        $account->delete();

        $this->dispatch('swal', icon: 'success', title: __('Akun kiosk dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Akun Kiosk')" :subtitle="__('Akun perangkat untuk tablet absensi wajah di depan kelas.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.kiosk-accounts.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex items-start gap-3 rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-primary-800">
        <ion-icon name="tablet-landscape-outline" class="mt-0.5 shrink-0 text-xl"></ion-icon>
        <p>
            {{ __('Login di tablet kelas memakai akun kiosk, maka tablet langsung membuka layar scan wajah tanpa akses ke menu lain. Pilih kelas di layar kiosk agar pencocokan wajah hanya pada siswa kelas tersebut.') }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px] flex-1 sm:flex-none">
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Cari nama atau email...') }}"
                    class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama Perangkat') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Email Login') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 whitespace-nowrap text-gray-700">
                    @forelse ($this->accounts as $account)
                        <tr wire:key="{{ $account->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $account->name }}</td>
                            <td class="px-4 py-3">{{ $account->email }}</td>
                            <td class="px-4 py-3"><x-ui.status-toggle :user="$account" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.kiosk-accounts.edit', $account) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$account->id" :title="__('Hapus akun kiosk ini?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada akun kiosk.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->accounts->links() }}</div>
    </div>
</div>
