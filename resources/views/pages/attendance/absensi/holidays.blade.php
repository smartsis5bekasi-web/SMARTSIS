<?php

use App\Models\SchoolHoliday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Hari Libur')] class extends Component {
    use WithPagination;

    public string $search = '';

    /** "upcoming" (still to come or under way), "past", or "all". */
    public string $period = 'upcoming';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'period'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Upcoming holidays read soonest first; past ones most recent first.
     *
     * @return LengthAwarePaginator<int, SchoolHoliday>
     */
    #[Computed]
    public function holidays(): LengthAwarePaginator
    {
        $today = now()->toDateString();

        return SchoolHoliday::query()
            ->when(filled($this->search), fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->period === 'upcoming', fn (Builder $query) => $query->whereDate('end_date', '>=', $today)->orderBy('start_date'))
            ->when($this->period === 'past', fn (Builder $query) => $query->whereDate('end_date', '<', $today)->orderByDesc('start_date'))
            ->when(! in_array($this->period, ['upcoming', 'past'], true), fn (Builder $query) => $query->orderByDesc('start_date'))
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * Whether the holiday is under way, still to come, or over.
     *
     * @return array{label: string, class: string}
     */
    public function stateOf(SchoolHoliday $holiday): array
    {
        $today = now()->startOfDay();

        return match (true) {
            $holiday->covers($today) => ['label' => __('Berlangsung'), 'class' => 'bg-green-100 text-green-700'],
            $holiday->start_date->gt($today) => ['label' => __('Akan datang'), 'class' => 'bg-blue-100 text-blue-700'],
            default => ['label' => __('Selesai'), 'class' => 'bg-gray-100 text-gray-600'],
        };
    }

    /**
     * Removing a holiday only reopens the days for absensi from now on;
     * a day already past is not swept again (run attendance:mark-pending
     * --date for that).
     */
    public function delete(SchoolHoliday $holiday): void
    {
        $holiday->delete();

        unset($this->holidays);

        $this->dispatch('swal', icon: 'success', title: __('Hari libur dihapus.'));
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Hari Libur')" :subtitle="__('Hari ketika seluruh atau sebagian siswa tidak wajib absen.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="add-outline" :href="route('attendance.absensi.holidays.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px] flex-1 sm:flex-none">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari keterangan...') }}"
                    class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                </div>
            </div>

            <select wire:model.live="period"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="upcoming">{{ __('Mendatang & berlangsung') }}</option>
                <option value="past">{{ __('Sudah lewat') }}</option>
                <option value="all">{{ __('Semua') }}</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Tanggal') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Keterangan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Berlaku Untuk') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Lama') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 whitespace-nowrap text-gray-700">
                    @forelse ($this->holidays as $holiday)
                        @php($state = $this->stateOf($holiday))
                        <tr wire:key="holiday-{{ $holiday->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $holiday->periodLabel() }}</td>
                            <td class="px-4 py-3">{{ $holiday->name }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-red-100 text-red-700' => $holiday->isSchoolWide(),
                                    'bg-amber-100 text-amber-800' => ! $holiday->isSchoolWide(),
                                ])>{{ $holiday->scopeLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">{{ __(':count hari', ['count' => $holiday->dayCount()]) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $state['class'] }}">{{ $state['label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('attendance.absensi.holidays.edit', $holiday) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$holiday->id" :title="__('Hapus hari libur ini?')"
                                        :text="__('Siswa kembali wajib absen pada tanggal ini. Hari yang sudah lewat tidak otomatis ditandai Menunggu Konfirmasi.')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada hari libur.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->holidays->links() }}</div>
    </div>
</div>
