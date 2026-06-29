<?php

use App\Enums\UserRole;
use App\Models\PointSetting;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Monitoring Poin')] class extends Component {
    use WithPagination;

    /**
     * Students scoped to what the signed-in role may monitor: wali kelas see
     * their homeroom, orang tua see their children, everyone else sees all.
     *
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['classroom', 'major'])
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function setting(): PointSetting
    {
        return PointSetting::current();
    }

    /**
     * @return Builder<Student>
     */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::WaliKelas => Student::query()->whereIn(
                'classroom_id',
                $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
            ),
            UserRole::OrangTua => Student::query()->whereIn(
                'id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            default => Student::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Monitoring Poin')" :subtitle="__('Pantau poin disiplin siswa.')">
        @can(App\Enums\Permission::ManagePoint->value)
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="settings-outline" :href="route('attendance.points')" wire:navigate>
                    {{ __('Pengaturan Poin') }}
                </x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">{{ __('Nama Siswa') }}</th>
                        <th class="py-3 px-4">{{ __('Kelas') }}</th>
                        <th class="py-3 px-4">{{ __('Poin') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700">
                    @forelse ($this->students as $key => $student)
                        @php($belowMin = $student->current_point < $this->setting->min_point)
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->students->firstItem() }}</td>
                            <td class="py-3 px-4 font-medium">{{ $student->name }}</td>
                            <td class="py-3 px-4">{{ $student->classroom?->name ?? '—' }}</td>
                            <td class="py-3 px-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums',
                                    'bg-green-100 text-green-700' => $student->current_point >= $this->setting->target_point * 0.75,
                                    'bg-amber-100 text-amber-700' => $student->current_point < $this->setting->target_point * 0.75 && ! $belowMin,
                                    'bg-red-100 text-red-700' => $belowMin,
                                ])>{{ $student->current_point }} / {{ $this->setting->target_point }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="{{ $belowMin ? 'text-red-600' : 'text-green-600' }} text-sm font-medium">
                                    {{ $belowMin ? __('Di Bawah Minimum') : __('Aman') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('attendance.points.show', $student) }}" wire:navigate
                                    class="inline-flex items-center gap-1 text-primary-600 transition hover:text-primary-700">
                                    <ion-icon name="eye-outline" class="text-lg"></ion-icon>
                                    <span class="text-sm">{{ __('Detail') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-5 text-center text-gray-500">{{ __('Belum ada data siswa.') }}</td>
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
