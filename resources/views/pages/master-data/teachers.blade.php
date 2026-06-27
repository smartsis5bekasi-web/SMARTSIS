<?php

use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guru')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Teacher>
     */
    #[Computed]
    public function teachers(): LengthAwarePaginator
    {
        return Teacher::query()
            ->with('user.roles')
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Teacher $teacher): void
    {
        // Deleting the user cascades to the teacher row, which in turn nulls
        // any classroom it was the homeroom teacher of.
        $teacher->loadMissing('user');
        $teacher->user?->delete();
        $teacher->delete();

        $this->dispatch('swal', icon: 'success', title: __('Guru dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Guru')" :subtitle="__('Kelola data guru beserta akun & peran login.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.teachers.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 font-medium">NIP</th>
                        <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Peran') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Telepon') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->teachers as $teacher)
                        <tr wire:key="{{ $teacher->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $teacher->name }}</td>
                            <td class="px-4 py-3">{{ $teacher->nip ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $teacher->user?->email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($teacher->user?->primaryRole())
                                    <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">
                                        {{ $teacher->user->primaryRole()->label() }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $teacher->phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.teachers.edit', $teacher) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$teacher->id" :text="__('Akun login terkait juga akan dihapus dan tidak dapat dikembalikan.')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data guru.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->teachers->links() }}</div>
    </div>
</div>
