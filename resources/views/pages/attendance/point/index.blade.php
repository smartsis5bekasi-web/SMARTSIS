<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\PointRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengaturan Poin')] class extends Component {
    use WithPagination;

    /**
     * This route is the rule-configuration surface for managers. Students are
     * sent to their own Development Point page; other viewers to monitoring.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user->can(Permission::ManagePoint->value)) {
            return;
        }

        if ($user->primaryRole() === UserRole::Siswa && $user->student) {
            $this->redirectRoute('attendance.points.show', $user->student, navigate: true);

            return;
        }

        $this->redirectRoute('attendance.points.monitoring', navigate: true);
    }

    /**
     * @return LengthAwarePaginator<int, PointRule>
     */
    #[Computed]
    public function rules(): LengthAwarePaginator
    {
        return PointRule::query()
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(PointRule $rule): void
    {
        $rule->delete();

        $this->dispatch('swal', icon: 'success', title: __('Aturan poin dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Pengaturan Poin')" :subtitle="__('Atur cara penambahan & pengurangan poin disiplin siswa.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="people-outline" :href="route('attendance.points.monitoring')" wire:navigate>
                {{ __('Monitoring Siswa') }}
            </x-ui.button>
            <x-ui.button variant="secondary" icon="settings-outline" :href="route('attendance.points.settings')" wire:navigate>
                {{ __('Pengaturan') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="add-outline" :href="route('attendance.points.create')" wire:navigate>
                {{ __('Tambah Aturan') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">{{ __('Nama Point') }}</th>
                        <th class="py-3 px-4">{{ __('Tipe') }}</th>
                        <th class="py-3 px-4">{{ __('Cara') }}</th>
                        <th class="py-3 px-4">{{ __('Point') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700 whitespace-nowrap">
                    @forelse ($this->rules as $key => $rule)
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->rules->firstItem() }}</td>
                            <td class="py-3 px-4 font-medium">{{ $rule->name }}</td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-green-100 text-green-700' => $rule->type->value === 'addition',
                                    'bg-red-100 text-red-700' => $rule->type->value === 'deduction',
                                ])>{{ $rule->type->label() }}</span>
                            </td>
                            <td class="py-3 px-4">{{ $rule->source->label() }}</td>
                            <td class="py-3 px-4 font-semibold tabular-nums">
                                <span class="{{ $rule->type->value === 'addition' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $rule->signedPoint() > 0 ? '+' : '' }}{{ $rule->signedPoint() }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-primary-50 text-primary-700' => $rule->is_active,
                                    'bg-gray-100 text-gray-500' => ! $rule->is_active,
                                ])>{{ $rule->is_active ? __('Aktif') : __('Nonaktif') }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-row items-center justify-center gap-x-4">
                                    <a href="{{ route('attendance.points.edit', $rule) }}" wire:navigate title="{{ __('Ubah') }}"
                                        class="text-primary-600 transition hover:text-primary-700">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$rule->id"
                                        :text="__('Aturan poin ini akan dihapus.')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-5 text-center text-gray-500">{{ __('Belum ada aturan poin.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->rules->links() }}
        </div>
    </div>
</div>
