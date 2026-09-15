<?php

use App\Enums\Permission;
use App\Livewire\Concerns\HandlesFaceScan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::kiosk')] #[Title('Kiosk Absensi')] class extends Component {
    use HandlesFaceScan;

    public function mount(): void
    {
        $this->bootFaceScan();
    }

    /**
     * Staff open the kiosk from the scan page and can step back to it; a
     * device kiosk account has nowhere else to go, so it can only sign out.
     */
    public function isStaff(): bool
    {
        return auth()->user()->can(Permission::ManageAttendance->value);
    }
}; ?>

<div class="flex min-h-screen flex-col">
    @vite('resources/js/face-attendance.js')

    <header class="flex flex-wrap items-center gap-4 border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
            <x-app-logo-icon class="h-10 w-10 fill-current text-primary-600" />
            <div class="flex flex-col">
                <span class="font-bold leading-tight text-gray-800">{{ __('Kiosk Absensi') }}</span>
                <span class="text-xs text-gray-500">{{ $this->selectedClassroom?->name ?? __('Semua kelas') }} · SMAN 5 Bekasi</span>
            </div>
        </div>

        <div x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)"
            class="ml-auto flex flex-col items-end">
            <span class="text-xl font-bold tabular-nums text-gray-800"
                x-text="now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })">{{ now()->format('H:i:s') }}</span>
            <span class="text-xs text-gray-500"
                x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })">{{ now()->translatedFormat('l, d F Y') }}</span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" x-data title="{{ __('Layar penuh') }}"
                x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50">
                <ion-icon name="expand-outline" class="text-xl"></ion-icon>
            </button>

            @if ($this->isStaff())
                <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi.scan', array_filter(['kelas' => $classroomId]))">
                    {{ __('Keluar Kiosk') }}
                </x-ui.button>
            @else
                <form method="POST" action="{{ route('logout') }}" x-data x-ref="logout">
                    @csrf
                    <x-ui.button variant="secondary" type="button" icon="log-out-outline"
                        x-on:click="confirmDelete(() => $refs.logout.submit(), { title: @js(__('Keluar dari akun kiosk?')), text: @js(__('Tablet ini harus login ulang untuk dipakai absensi.')), confirmButtonText: @js(__('Ya, keluar')) })">
                        {{ __('Keluar') }}
                    </x-ui.button>
                </form>
            @endif
        </div>
    </header>

    <main class="grid flex-1 grid-cols-1 gap-6 p-4 sm:p-6 lg:grid-cols-5">
        <div class="flex flex-col gap-4 rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-3">
            <x-attendance.classroom-picker :classrooms="$this->classrooms" />

            <x-attendance.face-scanner :mode="$mode" :templates-url="$this->templatesUrl()" :classroom-id="$classroomId" />
        </div>

        <div class="flex flex-col gap-6 lg:col-span-2">
            <x-attendance.scan-result :result="$lastResult" />

            <div class="rounded-xl bg-primary-600 p-6 text-white drop-shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-100">{{ __('Cara Absen') }}</p>
                <ol class="mt-3 flex list-decimal flex-col gap-1.5 pl-5 text-sm">
                    <li>{{ __('Berdiri di depan kamera, wajah di dalam bingkai.') }}</li>
                    <li>{{ __('Tunggu nama Anda muncul.') }}</li>
                    <li>{{ __('Kedipkan mata sekali untuk konfirmasi.') }}</li>
                </ol>
            </div>

            <x-attendance.scan-schedule :setting="$this->setting" />
        </div>
    </main>
</div>
