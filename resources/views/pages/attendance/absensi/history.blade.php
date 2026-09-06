<?php

use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Absen')] class extends Component {
    use WithPagination;

    /** Filters. Blank month means "every month". */
    public string $month = '';

    public string $status = '';

    public string $search = '';

    public ?int $classroomId = null;

    /** The record whose captured position is open in the map modal. */
    public ?int $locationAttendanceId = null;

    /** Which leg of the day the map modal is showing: "in" or "out". */
    public string $locationMoment = 'in';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['month', 'status', 'search', 'classroomId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Whether the signed-in account browses more than its own (or its
     * children's) records, which is what turns the class/search filters on.
     */
    public function isMonitoring(): bool
    {
        return ! in_array(auth()->user()->primaryRole(), [UserRole::Siswa, UserRole::OrangTua], true);
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageAttendance->value);
    }

    /**
     * @return array<int, AttendanceStatus>
     */
    public function statuses(): array
    {
        return AttendanceStatus::cases();
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, Attendance>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['student.classroom'])
            ->whereIn('student_id', $this->scopedStudents()->select('id'))
            ->when($this->month !== '', function (Builder $query): void {
                $month = $this->selectedMonth();

                $query->whereBetween('date', [
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ]);
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('nis', 'like', '%'.$this->search.'%'),
            ))
            ->when($this->classroomId !== null, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $inner) => $inner->where('classroom_id', $this->classroomId),
            ))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10);
    }

    /**
     * The record backing the open map modal, re-resolved through the same
     * scope as the table so a tampered id cannot reveal someone else's
     * position.
     */
    #[Computed]
    public function locationAttendance(): ?Attendance
    {
        if ($this->locationAttendanceId === null) {
            return null;
        }

        return Attendance::query()
            ->with('student')
            ->whereIn('student_id', $this->scopedStudents()->select('id'))
            ->find($this->locationAttendanceId);
    }

    public function showLocation(int $attendanceId): void
    {
        $this->locationAttendanceId = $attendanceId;

        unset($this->locationAttendance);

        // Open on whichever leg actually has a fix.
        $attendance = $this->locationAttendance;

        $this->locationMoment = $attendance?->location('in') !== null ? 'in' : 'out';
    }

    public function setLocationMoment(string $moment): void
    {
        $this->locationMoment = $moment === 'out' ? 'out' : 'in';
    }

    public function closeLocation(): void
    {
        $this->locationAttendanceId = null;

        unset($this->locationAttendance);
    }

    /**
     * Confirm a self-declared sakit/izin after reviewing its attachment.
     */
    public function verify(int $attendanceId): void
    {
        abort_unless($this->canManage(), 403);

        $attendance = Attendance::query()
            ->whereIn('student_id', $this->scopedStudents()->select('id'))
            ->findOrFail($attendanceId);

        app(\App\Actions\Attendance\RecordAttendance::class)->verify($attendance, auth()->user());

        unset($this->records);

        $this->dispatch('swal', icon: 'success', title: __('Absensi diverifikasi.'));
    }

    private function selectedMonth(): CarbonInterface
    {
        return rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m', $this->month)->startOfMonth(), now()->startOfMonth(), false);
    }

    /**
     * The students this account may read: a siswa sees itself, an orang tua
     * their children, a wali kelas their homeroom, everyone else the school.
     *
     * @return Builder<Student>
     */
    private function scopedStudents(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Student::query()->whereKey($user->student?->id ?? 0),
            UserRole::OrangTua => Student::query()->whereIn(
                'id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            UserRole::WaliKelas => Student::query()->whereIn(
                'classroom_id',
                $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
            ),
            default => Student::query(),
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Riwayat Absen')"
        :subtitle="__('Catatan absensi harian beserta foto dan lokasi saat absen direkam.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('attendance.absensi')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex-col rounded-xl bg-white p-6 drop-shadow-lg">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input type="month" wire:model.live="month"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />

            <select wire:model.live="status"
                class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">{{ __('Semua Status') }}</option>
                @foreach ($this->statuses() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>

            @if ($this->isMonitoring())
                <select wire:model.live="classroomId"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Kelas') }}</option>
                    @foreach ($this->classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>

                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Cari nama / NIS…') }}"
                    class="w-full max-w-xs rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            @endif
        </div>

        <div class="rounded-2xl border overflow-auto">
            <table class="border-collapse min-w-full leading-normal">
                <thead>
                    <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                        <th class="py-3 px-4">{{ __('Nama') }}</th>
                        <th class="py-3 px-4">{{ __('Kelas') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Waktu Masuk') }}</th>
                        <th class="py-3 px-4">{{ __('Waktu Keluar') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Verified') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Photo') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-gray-700 whitespace-nowrap">
                    @forelse ($this->records as $key => $attendance)
                        @php($photo = $attendance->evidencePhotoUrl())
                        <tr class="border-b last:border-0">
                            <td class="py-3 px-4">{{ $key + $this->records->firstItem() }}</td>
                            <td class="py-3 px-4">{{ $attendance->date->translatedFormat('l, d/m/Y') }}</td>
                            <td class="py-3 px-4 font-semibold">{{ $attendance->student->name }}</td>
                            <td class="py-3 px-4">{{ $attendance->student->classroom?->name ?? '—' }}</td>
                            <td class="py-3 px-4">
                                <x-attendance.status-badge :status="$attendance->status" />
                            </td>
                            <td class="py-3 px-4">
                                @if ($attendance->checked_in_at)
                                    <span class="tabular-nums">{{ $attendance->checked_in_at->format('H:i:s') }} WIB</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($attendance->checked_out_at)
                                    <span class="tabular-nums">{{ $attendance->checked_out_at->format('H:i:s') }} WIB</span>
                                @elseif ($attendance->needsCheckOut())
                                    <span class="text-gray-500">{{ __('Belum Absen Keluar') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('Tidak diperlukan') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <x-attendance.verified-badge :attendance="$attendance" />
                                    @if ($this->canManage() && ! $attendance->isVerified())
                                        <button type="button" wire:click="verify({{ $attendance->id }})"
                                            class="rounded-md bg-green-50 px-2 py-1 text-xs font-semibold text-green-700 transition hover:bg-green-100">
                                            {{ __('Verifikasi') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center">
                                    @if ($photo)
                                        <a href="{{ $photo }}" target="_blank" rel="noopener">
                                            <img src="{{ $photo }}" alt="{{ __('Foto absen :name', ['name' => $attendance->student->name]) }}"
                                                class="h-10 w-10 rounded-full object-cover ring-1 ring-gray-200" />
                                        </a>
                                    @elseif ($attendance->attachment_path)
                                        <a href="{{ $attendance->attachment_path }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline">
                                            <ion-icon name="document-text-outline" class="text-lg"></ion-icon>
                                            {{ __('Bukti') }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($attendance->hasLocation())
                                    <button type="button" wire:click="showLocation({{ $attendance->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700">
                                        <ion-icon name="location-outline" class="text-base"></ion-icon>
                                        {{ __('Lihat Posisi') }}
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('Tanpa lokasi') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-5 text-center text-gray-500">{{ __('Belum ada catatan absensi.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->records->links() }}
        </div>
    </div>

    {{-- ============ Lihat Posisi ============ --}}
    @php($located = $this->locationAttendance)
    @if ($located !== null)
        @php($fix = $located->location($locationMoment))
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeLocation()">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeLocation"></div>

            <div class="relative flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600">
                            <ion-icon name="location-outline" class="text-lg"></ion-icon>
                        </span>
                        {{ __('Lokasi Absen') }}
                    </h2>
                    <button type="button" wire:click="closeLocation" class="inline-flex text-gray-400 transition hover:text-gray-600">
                        <ion-icon name="close-outline" class="text-2xl"></ion-icon>
                    </button>
                </div>

                <div class="flex flex-col gap-4 px-6 py-5">
                    <p class="text-sm text-gray-500">
                        {{ $located->student->name }} · {{ $located->date->translatedFormat('l, d M Y') }}
                    </p>

                    <div class="grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1">
                        <button type="button" wire:click="setLocationMoment('in')" @disabled($located->location('in') === null) @class([
                            'flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow' => $locationMoment === 'in',
                            'text-gray-600 hover:text-gray-800' => $locationMoment !== 'in',
                            'cursor-not-allowed opacity-40' => $located->location('in') === null,
                        ])>
                            <ion-icon name="log-in-outline" class="text-lg"></ion-icon>
                            {{ __('Masuk') }}
                        </button>
                        <button type="button" wire:click="setLocationMoment('out')" @disabled($located->location('out') === null) @class([
                            'flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow' => $locationMoment === 'out',
                            'text-gray-600 hover:text-gray-800' => $locationMoment !== 'out',
                            'cursor-not-allowed opacity-40' => $located->location('out') === null,
                        ])>
                            <ion-icon name="log-out-outline" class="text-lg"></ion-icon>
                            {{ __('Pulang') }}
                        </button>
                    </div>

                    @if ($fix !== null)
                        <x-attendance.location-map :latitude="$fix['latitude']" :longitude="$fix['longitude']" :accuracy="$fix['accuracy']"
                            :label="__('Lokasi absen :name', ['name' => $located->student->name])" />
                    @else
                        <p class="rounded-lg border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500">
                            {{ __('Tidak ada lokasi yang terekam untuk sesi ini.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
