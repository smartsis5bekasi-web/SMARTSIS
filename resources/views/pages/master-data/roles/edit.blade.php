<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

new #[Title('Atur Hak Akses Peran')] class extends Component {
    /**
     * The backed value of the role being edited, taken from the route.
     */
    public string $roleName = '';

    /**
     * The permission values ticked on the form.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public function mount(string $role): void
    {
        $resolved = UserRole::tryFrom($role);

        abort_if($resolved === null, 404);

        $this->roleName = $resolved->value;

        // Super Admin bypasses every gate through AppServiceProvider's
        // Gate::before rule, so editing its list would only give a false sense
        // of restriction.
        if ($resolved->isLocked()) {
            toast(__('Hak akses Super Admin tidak dapat diubah.'), 'error');

            $this->redirectRoute('master-data.roles.index', navigate: true);

            return;
        }

        $this->selected = $this->roleRecord()->permissions->pluck('name')->all();
    }

    #[Computed]
    public function role(): UserRole
    {
        return UserRole::from($this->roleName);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'selected' => ['array'],
            'selected.*' => ['string', Rule::in(PermissionEnum::values())],
        ];
    }

    /**
     * The permission catalogue bucketed per module.
     *
     * @return array<string, array<int, PermissionEnum>>
     */
    #[Computed]
    public function groups(): array
    {
        return PermissionEnum::grouped();
    }

    #[Computed]
    public function totalPermissions(): int
    {
        return count(PermissionEnum::cases());
    }

    /**
     * Tick or untick every permission in a module at once.
     */
    public function toggleGroup(string $group): void
    {
        $values = array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            PermissionEnum::grouped()[$group] ?? [],
        );

        $allSelected = array_diff($values, $this->selected) === [];

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $values))
            : array_values(array_unique([...$this->selected, ...$values]));
    }

    /**
     * Load the PRD default matrix into the form. Nothing is persisted until
     * the admin submits, so this stays reversible.
     */
    public function applyDefaults(): void
    {
        $this->selected = $this->role->defaultPermissions();

        $this->dispatch('swal', icon: 'info', title: __('Hak akses bawaan dimuat. Tekan Simpan untuk menerapkan.'));
    }

    public function save(): void
    {
        $this->validate();

        $selected = array_values(array_unique($this->selected));

        if ($this->wouldLockOutCurrentUser($selected)) {
            $this->addError('selected', __('Anda tidak dapat mencabut hak "Kelola Peran & Hak Akses" dari peran Anda sendiri.'));

            return;
        }

        // Resolve through findOrCreate so a permission introduced by a newer
        // release can be granted even before the seeder has been re-run.
        $permissions = array_map(
            fn (string $value): Permission => Permission::findOrCreate($value),
            $selected,
        );

        $this->roleRecord()->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        toast(__('Hak akses :role diperbarui.', ['role' => $this->role->label()]), 'success');

        $this->redirectRoute('master-data.roles.index', navigate: true);
    }

    /**
     * Guard against an admin removing their own ability to reach this page.
     *
     * @param  array<int, string>  $selected
     */
    protected function wouldLockOutCurrentUser(array $selected): bool
    {
        $user = auth()->user();

        return $user !== null
            && ! $user->hasRole(UserRole::SuperAdmin->value)
            && $user->hasRole($this->role->value)
            && ! in_array(PermissionEnum::ManageRole->value, $selected, true);
    }

    protected function roleRecord(): Role
    {
        return Role::findOrCreate($this->role->value);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header
        :title="__('Hak Akses :role', ['role' => $this->role->label()])"
        :subtitle="$this->role->description()"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.roles.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white px-6 py-4 shadow-sm">
            <p class="text-sm text-gray-600">
                {{ __(':count dari :total hak akses dipilih.', ['count' => count($selected), 'total' => $this->totalPermissions]) }}
            </p>

            <x-ui.button variant="secondary" icon="refresh-outline" wire:click="applyDefaults">
                {{ __('Kembalikan ke Bawaan') }}
            </x-ui.button>
        </div>

        @error('selected')
            <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($this->groups as $group => $permissions)
                @php
                    $values = array_map(fn ($permission) => $permission->value, $permissions);
                    $allSelected = array_diff($values, $selected) === [];
                @endphp

                <div wire:key="group-{{ $loop->index }}" class="flex flex-col rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                        <h2 class="font-semibold text-gray-800">{{ $group }}</h2>

                        <button
                            type="button"
                            wire:click="toggleGroup(@js($group))"
                            class="text-xs font-medium text-primary-600 transition hover:underline"
                        >
                            {{ $allSelected ? __('Kosongkan') : __('Pilih Semua') }}
                        </button>
                    </div>

                    <div class="flex flex-col gap-3">
                        @foreach ($permissions as $permission)
                            <label wire:key="perm-{{ $permission->value }}" class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 transition hover:bg-gray-50">
                                <input
                                    type="checkbox"
                                    value="{{ $permission->value }}"
                                    wire:model="selected"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="flex flex-col">
                                    <span class="text-sm font-medium text-gray-800">{{ $permission->label() }}</span>
                                    <span class="text-xs text-gray-500">{{ $permission->description() }}</span>
                                    <code class="mt-0.5 text-[11px] text-gray-400">{{ $permission->value }}</code>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.roles.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>
