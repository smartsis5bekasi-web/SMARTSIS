<?php

use App\Actions\Attendance\RecordAttendance;
use App\Enums\Permission;
use App\Exceptions\AttendanceException;
use App\Models\AttendanceSetting;
use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Scan Absensi')] class extends Component {
    /** Either "masuk" (check-in, F-09) or "pulang" (check-out, F-10). */
    public string $mode = 'masuk';

    /**
     * Outcome of the last scan, rendered as the result card.
     *
     * @var array{ok: bool, name: string, classroom: string|null, avatar: string|null, status: string, time: string, message: string}|null
     */
    public ?array $lastResult = null;

    public function mount(): void
    {
        // The staffed kiosk lives here; a siswa self-scans from the Absensi
        // page, so old bookmarks land on the unified page instead of a 403.
        if (! auth()->user()->can(Permission::ManageAttendance->value)) {
            $this->redirectRoute('attendance.absensi', navigate: true);

            return;
        }

        // Default to check-out mode once the check-out window is open.
        if ($this->setting()->isCheckOutOpen(now())) {
            $this->mode = 'pulang';
        }
    }

    #[Computed]
    public function setting(): AttendanceSetting
    {
        return AttendanceSetting::current();
    }

    public function setMode(string $mode): void
    {
        abort_unless(in_array($mode, ['masuk', 'pulang'], true), 400);

        $this->mode = $mode;
        $this->lastResult = null;
    }

    /**
     * Record the matched student's attendance after the blink challenge.
     * Check-out for a student who has not checked in is rejected by the
     * engine ({@see RecordAttendance::checkOut}) and surfaces as an error
     * card.
     */
    public function record(int $studentId): void
    {
        $student = Student::query()->with('classroom')->findOrFail($studentId);
        $recorder = auth()->user();
        $engine = app(RecordAttendance::class);

        try {
            $attendance = $this->mode === 'pulang'
                ? $engine->checkOut($student, $recorder)
                : $engine->checkIn($student, $recorder);

            $this->lastResult = [
                'ok' => true,
                'name' => $student->name,
                'classroom' => $student->classroom?->name,
                'avatar' => $student->avatar_url,
                'status' => $attendance->status->label(),
                'time' => now()->format('H:i'),
                'message' => $this->mode === 'pulang'
                    ? __('Absensi pulang tercatat.')
                    : __('Absensi masuk tercatat: :status.', ['status' => $attendance->status->label()]),
            ];
        } catch (AttendanceException $exception) {
            $this->lastResult = [
                'ok' => false,
                'name' => $student->name,
                'classroom' => $student->classroom?->name,
                'avatar' => $student->avatar_url,
                'status' => '—',
                'time' => now()->format('H:i'),
                'message' => $exception->getMessage(),
            ];
        }
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
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- Camera + mode switch. --}}
        <div class="flex flex-col rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-3">
            <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1">
                <button type="button" wire:click="setMode('masuk')" @class([
                    'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                    'bg-primary-600 text-white shadow' => $mode === 'masuk',
                    'text-gray-600 hover:text-gray-800' => $mode !== 'masuk',
                ])>
                    <ion-icon name="log-in-outline" class="text-lg"></ion-icon>
                    {{ __('Absensi Masuk') }}
                </button>
                <button type="button" wire:click="setMode('pulang')" @class([
                    'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                    'bg-primary-600 text-white shadow' => $mode === 'pulang',
                    'text-gray-600 hover:text-gray-800' => $mode !== 'pulang',
                ])>
                    <ion-icon name="log-out-outline" class="text-lg"></ion-icon>
                    {{ __('Absensi Pulang') }}
                </button>
            </div>

            <div wire:ignore x-data
                x-init="window.SmartsisAttendance.start($el, $wire, { templatesUrl: @js(route('attendance.absensi.face-templates')) })"
                class="flex flex-col">
                <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary-400 bg-gray-900">
                    <video data-face-video playsinline muted autoplay class="aspect-[4/3] w-full -scale-x-100 object-cover"></video>
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="h-3/4 w-1/2 rounded-[50%] border-2 border-white/60"></div>
                    </div>
                </div>

                <p data-face-status class="mt-4 min-h-6 text-center text-sm text-gray-500">{{ __('Menyiapkan kamera…') }}</p>
            </div>
        </div>

        {{-- Result card + schedule info. --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            <div class="rounded-xl bg-white p-6 drop-shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Hasil Terakhir') }}</p>

                @if ($lastResult === null)
                    <div class="flex flex-col items-center gap-2 py-10 text-gray-400">
                        <ion-icon name="scan-outline" class="text-4xl"></ion-icon>
                        <span class="text-sm">{{ __('Belum ada siswa terekam pada sesi ini.') }}</span>
                    </div>
                @else
                    <div class="mt-4 flex items-center gap-4">
                        <img class="h-16 w-16 rounded-2xl object-cover"
                            src="{{ $lastResult['avatar'] ?? asset('assets/placeholder.png') }}"
                            alt="{{ $lastResult['name'] }}" />
                        <div class="flex flex-col">
                            <span class="font-bold text-gray-800">{{ $lastResult['name'] }}</span>
                            <span class="text-sm text-gray-500">{{ $lastResult['classroom'] ?? '—' }}</span>
                            <span class="text-sm text-gray-500">{{ $lastResult['time'] }} WIB</span>
                        </div>
                    </div>

                    <div @class([
                        'mt-4 flex items-start gap-2 rounded-lg px-3 py-2.5 text-sm font-medium',
                        'bg-green-50 text-green-700' => $lastResult['ok'],
                        'bg-red-50 text-red-700' => ! $lastResult['ok'],
                    ])>
                        <ion-icon name="{{ $lastResult['ok'] ? 'checkmark-circle-outline' : 'alert-circle-outline' }}" class="mt-0.5 shrink-0 text-lg"></ion-icon>
                        <span>{{ $lastResult['message'] }}</span>
                    </div>
                @endif
            </div>

            <div class="rounded-xl bg-white p-6 drop-shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Jadwal Absensi') }}</p>
                <dl class="mt-4 flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">{{ __('Absensi masuk mulai') }}</dt>
                        <dd class="font-semibold text-gray-800">{{ substr($this->setting->check_in_start, 0, 5) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">{{ __('Terlambat setelah') }}</dt>
                        <dd class="font-semibold text-gray-800">{{ substr($this->setting->late_after, 0, 5) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500">{{ __('Absensi pulang mulai') }}</dt>
                        <dd class="font-semibold text-gray-800">{{ substr($this->setting->check_out_after, 0, 5) }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-gray-500">
                    {{ __('Siswa yang tidak tercatat hingga akhir hari dapat ditandai Alpha dari halaman monitoring.') }}
                </p>
            </div>
        </div>
    </div>
</div>
