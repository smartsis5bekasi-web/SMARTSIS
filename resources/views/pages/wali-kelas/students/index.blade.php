<?php

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Siswa Kelas')] class extends Component {
    use WithPagination;

    /**
     * Classroom ids of the homerooms led by the signed-in wali kelas.
     *
     * @return array<int, int>
     */
    private function homeroomClassroomIds(): array
    {
        return auth()->user()->teacher?->homeroomClassrooms()->pluck('id')->all() ?? [];
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->whereIn('classroom_id', $this->homeroomClassroomIds())
            ->with(['classroom', 'major'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Student $student): void
    {
        abort_unless(in_array($student->classroom_id, $this->homeroomClassroomIds(), true), 403);

        $student->delete();

        $this->dispatch('swal', icon: 'success', title: __('Siswa dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Siswa Kelas')" :subtitle="__('Kelola siswa di kelas perwalian Anda.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('wali-kelas.students.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex-col rounded-xl bg-white p-8 drop-shadow-lg">
        <div class="overflow-auto rounded-2xl border bg-gray-100">
            <table class="min-w-full border-collapse rounded-2xl leading-normal">
                <thead>
                    <tr class="whitespace-nowrap text-left text-md font-normal text-gray-500">
                        <th class="px-4 py-2.5">No</th>
                        <th class="px-4 py-2.5">{{ __('Nama Siswa') }}</th>
                        <th class="px-4 py-2.5">NIS</th>
                        <th class="px-4 py-2.5">{{ __('Kelas') }}</th>
                        <th class="px-4 py-2.5">{{ __('Jenis Kelamin') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="rounded-2xl bg-white text-gray-700">
                    @forelse ($this->students as $key => $student)
                        <tr>
                            <td class="px-4 py-2.5">{{ $key + $this->students->firstItem() }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <img class="h-12 w-12 rounded-2xl object-cover" src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                                    <p class="max-w-xs">{{ $student->name }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">{{ $student->nis }}</td>
                            <td class="px-4 py-2.5">{{ $student->classroom?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                {{ $student->gender === 'L' ? __('Laki-laki') : ($student->gender === 'P' ? __('Perempuan') : '—') }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center justify-center gap-x-4">
                                    <a href="{{ route('wali-kelas.students.edit', $student) }}" wire:navigate class="text-primary-600 transition hover:text-primary-700" title="{{ __('Ubah') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$student->id" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-5 text-center text-gray-500">{{ __('Belum ada siswa di kelas Anda.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->students->links() }}
        </div>
    </div>
</div>
