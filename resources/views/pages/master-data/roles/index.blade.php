<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('Manajemen Peran')] class extends Component {
    /**
     * Every role in the PRD matrix, decorated with how many permissions it
     * currently holds and how many accounts are assigned to it.
     *
     * @return Collection<int, array{role: UserRole, permissions: int, users: int, customised: bool}>
     */
    #[Computed]
    public function roles(): Collection
    {
        $records = Role::query()
            ->withCount(['permissions', 'users'])
            ->get()
            ->keyBy('name');

        return collect(UserRole::cases())->map(function (UserRole $role) use ($records): array {
            $record = $records->get($role->value);

            $current = $record?->permissions->pluck('name')->sort()->values()->all() ?? [];
            $default = collect($role->defaultPermissions())->sort()->values()->all();

            return [
                'role' => $role,
                'permissions' => $record?->permissions_count ?? 0,
                'users' => $record?->users_count ?? 0,
                'customised' => $current !== $default,
            ];
        });
    }

    /**
     * The size of the permission catalogue, shown as the matrix denominator.
     */
    #[Computed]
    public function totalPermissions(): int
    {
        return count(PermissionEnum::cases());
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header
        :title="__('Manajemen Peran')"
        :subtitle="__('Atur hak akses setiap peran pengguna tanpa mengubah kode program.')"
    />

    <div class="flex items-start gap-3 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-primary-800">
        <span wire:ignore class="mt-0.5 inline-flex"><ion-icon name="information-circle-outline" class="text-lg"></ion-icon></span>
        <p>
            {{ __('Perubahan hak akses langsung berlaku pada login berikutnya pengguna terkait. Peran Super Admin dikunci karena selalu memiliki seluruh akses.') }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Peran') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Hak Akses') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Pengguna') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 whitespace-nowrap">
                    @foreach ($this->roles as $row)
                        @php($role = $row['role'])
                        <tr wire:key="{{ $role->value }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $role->label() }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $role->description() }}</p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($role->isLocked())
                                    <span class="text-gray-500">{{ __('Seluruh akses') }}</span>
                                @else
                                    <span class="font-medium text-gray-900">{{ $row['permissions'] }}</span>
                                    <span class="text-gray-400">/ {{ $this->totalPermissions }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row['users'] }}</td>
                            <td class="px-4 py-3">
                                @if ($role->isLocked())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                        <span wire:ignore class="inline-flex"><ion-icon name="lock-closed-outline"></ion-icon></span>
                                        {{ __('Terkunci') }}
                                    </span>
                                @elseif ($row['customised'])
                                    <span class="inline-flex rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-medium text-yellow-800">
                                        {{ __('Disesuaikan') }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800">
                                        {{ __('Bawaan') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($role->isLocked())
                                        <span class="text-xs text-gray-400">{{ __('Tidak dapat diubah') }}</span>
                                    @else
                                        <a href="{{ route('master-data.roles.edit', $role->value) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Atur Hak Akses') }}">
                                            <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
