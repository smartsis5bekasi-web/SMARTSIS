<?php

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Siswa')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->with(['classroom', 'major', 'teacher', 'parents'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Student $student): void
    {
        $student->delete();

        $this->dispatch('swal', icon: 'success', title: __('Siswa dihapus.'));
    }
}; ?>

<div class="justify-center max-xl:w-full">
    <x-ui.page-header :title="__('Siswa')" :subtitle="__('Kelola data siswa.')">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.students.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
     <div class="flex-col bg-white rounded-xl p-8 drop-shadow-lg mt-5">
           <div class="rounded-2xl border bg-gray-100 overflow-auto">
            <table class="border-collapse rounded-2xl min-w-full leading-normal">
                <thead class="rounded-2xl">
                    <tr class="rounded-2xl text-gray-500 font-normal text-md text-left whitespace-nowrap">
                        <th class="py-2.5 px-4">No</th>
                        <th class="py-2.5 px-4">Nama Siswa</th>
                        <th class="py-2.5 px-4">Email Siswa</th>
                        <th class="py-2.5 px-4">Kelas</th>
                        <th class="py-2.5 px-4">Jurusan</th>
                        <th class="py-2.5 px-4">Jenis Kelamin</th>
                        <th class="py-2.5 px-4">Orang Tua</th>
                            <th class="py-2.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="rounded-2xl bg-white text-gray-700">
                     @forelse ($this->students as $key => $student)
                        <tr>
                            <td class="py-2.5 px-4">
                                
                                {{ $key + $this->students->firstItem() }}
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="flex flex-col items-left gap-6 px-2">
                                    <img class="w-16 object-fill rounded-2xl" src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                                    <p class="max-w-xs">{{ $student->name }}</p>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-left">
                              <p class="max-w-xs">{{ $student->user->email ?? '-' }}</p>
                            </td>
                            <td class="py-2.5 px-4">
                                {{ $student->classroom?->name ?? '—' }}
                            </td>
                            <td class="py-2.5 px-4">
                                {{ $student->major?->name ?? '-' }}
                            </td>
                            <td class="py-2.5 px-4">
                              {{ $student->gender === 'L' ? __('Laki-laki') : ($student->gender === 'P' ? __('Perempuan') : '—') }}
                            </td>
                            <td class="py-2.5 px-4">
                                @forelse ($student->parents as $parent)
                                    <p class="max-w-xs whitespace-nowrap">{{ $parent->name }} <span class="text-xs text-gray-400">({{ $parent->pivot->relationship ?? '—' }})</span></p>
                                @empty
                                    —
                                @endforelse
                            </td>
                            
                                <td class="py-2.5 px-4">
                                    <div class="flex flex-col items-center gap-y-2">
                                    <div class="flex flex-row items-center gap-x-4">
                                          <a href="#" title="Lihat" target="_blank"  title="Lihat"><ion-icon name="eye-outline" class="text-2xl text-primary"></ion-icon></a>
                                        {{-- <a href="{{ route('umkm.detail', [$mentor->hasCollaborator->slug, 'type' => 'mentors']) }}" title="Lihat" target="_blank"  title="Lihat"><ion-icon name="eye-outline" class="text-2xl text-primary"></ion-icon></a> --}}
                                        <a href="#" class="text-primary" title="Ubah">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M8 3H3C1.89543 3 1 3.89543 1 5V16C1 17.1046 1.89543 18 3 18H14C15.1046 18 16 17.1046 16 16V11M14.5858 1.58579C15.3668 0.804738 16.6332 0.804738 17.4142 1.58579C18.1953 2.36683 18.1953 3.63316 17.4142 4.41421L8.82842 13H6L6 10.1716L14.5858 1.58579Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                        <form method="POST" class="m-0 flex items-center form-delete-mentor" action="#">
                                            <button type="submit" class="text-primary" title="Hapus">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M16 5L15.1327 17.1425C15.0579 18.1891 14.187 19 13.1378 19H4.86224C3.81296 19 2.94208 18.1891 2.86732 17.1425L2 5M7 9V15M11 9V15M12 5V2C12 1.44772 11.5523 1 11 1H7C6.44772 1 6 1.44772 6 2V5M1 5H17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                    </div>
                                </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-5 text-center">Belum ada data Mentor yang dapat ditampilkan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
           </div>
     </div>
     </div>
</div>
