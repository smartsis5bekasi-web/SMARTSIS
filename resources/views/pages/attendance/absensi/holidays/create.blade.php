<?php

use App\Livewire\Concerns\EditsSchoolHoliday;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Hari Libur')] class extends Component {
    use EditsSchoolHoliday;

    public function mount(): void
    {
        $this->start_date = now()->toDateString();
        $this->end_date = $this->start_date;
    }

    public function save(): void
    {
        $released = $this->saveHoliday();

        toast($this->savedMessage(__('Hari libur ditambahkan.'), $released), 'success');

        $this->redirectRoute('attendance.absensi.holidays', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Tambah Hari Libur')" :subtitle="__('Tandai hari ketika siswa tidak wajib absen.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi.holidays')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-attendance.holiday-form :scope="$scope" :grade-options="$this->gradeOptions()" :classrooms-without-grade="$this->classroomsWithoutGrade()" />
</div>
