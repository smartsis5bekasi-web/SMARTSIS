<?php

use App\Livewire\Concerns\EditsSchoolHoliday;
use App\Models\SchoolHoliday;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Hari Libur')] class extends Component {
    use EditsSchoolHoliday;

    public SchoolHoliday $holiday;

    public function mount(SchoolHoliday $holiday): void
    {
        $this->holiday = $holiday;
        $this->fillHolidayForm($holiday);
    }

    public function save(): void
    {
        $released = $this->saveHoliday($this->holiday);

        toast($this->savedMessage(__('Hari libur diperbarui.'), $released), 'success');

        $this->redirectRoute('attendance.absensi.holidays', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Hari Libur')" :subtitle="__('Perbarui tanggal atau tingkat yang libur.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi.holidays')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-attendance.holiday-form :scope="$scope" :grade-options="$this->gradeOptions()" :classrooms-without-grade="$this->classroomsWithoutGrade()" />
</div>
