<?php

use App\Enums\Permission;
use App\Livewire\Concerns\HandlesFaceScan;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Scan Absensi')] class extends Component {
    use HandlesFaceScan;

    public function mount(): void
    {
        $user = auth()->user();

        // The staffed kiosk lives here. A device kiosk account only ever runs
        // the full-screen kiosk, and a siswa self-scans from the Absensi page,
        // so old bookmarks land on the right page instead of a 403.
        if (! $user->can(Permission::ManageAttendance->value)) {
            if ($user->can(Permission::UseAttendanceKiosk->value)) {
                $this->redirectRoute('attendance.absensi.kiosk', navigate: true);

                return;
            }

            $this->redirectRoute('attendance.absensi', navigate: true);

            return;
        }

        $this->bootFaceScan();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @vite('resources/js/face-attendance.js')

    <x-ui.page-header :title="__('Scan Absensi')"
        :subtitle="__('Arahkan wajah siswa ke kamera, lalu kedipkan mata untuk konfirmasi.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="list-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Monitoring') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="tablet-landscape-outline" :href="route('attendance.absensi.kiosk', array_filter(['kelas' => $classroomId]))">
                {{ __('Mode Kiosk') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="flex flex-col gap-4 rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-3">
            <x-attendance.classroom-picker :classrooms="$this->classrooms" />

            <x-attendance.face-scanner :mode="$mode" :templates-url="$this->templatesUrl()" :classroom-id="$classroomId" />
        </div>

        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-attendance.scan-result :result="$lastResult" />

            <x-attendance.scan-schedule :setting="$this->setting">
                <p class="mt-4 text-xs text-gray-500">
                    {{ __('Siswa yang tidak tercatat hingga akhir hari dapat ditandai Alpha dari halaman monitoring.') }}
                </p>
            </x-attendance.scan-schedule>
        </div>
    </div>
</div>
