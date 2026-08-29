    <?php

    use App\Actions\Attendance\RecordAttendance;
    use App\Enums\AttendanceStatus;
    use App\Enums\Permission;
    use App\Enums\PermitType;
    use App\Enums\UserRole;
    use App\Exceptions\AttendanceException;
    use App\Models\Attendance;
    use App\Models\AttendanceSetting;
    use App\Models\Classroom;
    use App\Models\Permit;
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
    use App\Exports\AttendanceDailyExports;  
    use Maatwebsite\Excel\Facades\Excel;
    use Symfony\Component\HttpFoundation\BinaryFileResponse;

    new #[Title('Absensi')] class extends Component {
        use WithPagination;

        /** Daily monitoring filters (staff view). */
        public string $date = '';

        public string $status = '';

        public string $search = '';

        public ?int $classroomId = null;

        /** Personal history filters (siswa / orang tua view). */
        public string $month = '';

        public ?int $childId = null;

        /**
         * Outcome of the last self-service scan, rendered as the result card.
         *
         * @var array{ok: bool, status: string, time: string, message: string}|null
         */
        public ?array $lastResult = null;

        public function mount(): void
        {
            $this->date = now()->toDateString();
            $this->month = now()->format('Y-m');
            $this->childId = $this->children()->first()?->id;
        }

        public function updating(string $property): void
        {
            if (in_array($property, ['date', 'status', 'search', 'classroomId', 'month', 'childId'], true)) {
                $this->resetPage();
            }
        }

        /**
         * Siswa and Orang Tua get the personal history view instead of the
         * school-wide daily monitor.
         */
        public function isPersonal(): bool
        {
            return in_array(auth()->user()->primaryRole(), [UserRole::Siswa, UserRole::OrangTua], true);
        }

        public function canManage(): bool
        {
            return auth()->user()->can(Permission::ManageAttendance->value);
        }

        /**
         * A siswa records their own attendance from this page via the embedded
         * face scan; every other role only monitors or reads history.
         */
        public function isSelfService(): bool
        {
            return auth()->user()->primaryRole() === UserRole::Siswa
                && auth()->user()->student !== null;
        }

        #[Computed]
        public function setting(): AttendanceSetting
        {
            return AttendanceSetting::current();
        }

        /**
         * Today's attendance record of the signed-in siswa (self-service view).
         */
        #[Computed]
        public function todayAttendance(): ?Attendance
        {
            return auth()->user()->student?->attendances()->onDate(now())->first();
        }

        /**
         * The next scan step for the signed-in siswa: absensi masuk always comes
         * first — absensi pulang only unlocks after check-in is recorded. Null
         * when the day is complete or already covered by izin/sakit/alpha.
         */
        public function nextScanStep(): ?string
        {
            if (! $this->isSelfService()) {
                return null;
            }

            $attendance = $this->todayAttendance;

            if ($attendance === null) {
                return 'masuk';
            }

            if ($attendance->status->isPresent() && ! $attendance->isCheckedOut()) {
                return 'pulang';
            }

            return null;
        }

        /**
         * Whether the camera should run right now: check-in any time, check-out
         * only once the window opens (or an approved izin pulang awal).
         */
        public function isScannerOpen(): bool
        {
            return match ($this->nextScanStep()) {
                'masuk' => true,
                'pulang' => $this->setting->isCheckOutOpen(now())
                    || Permit::approvedFor(auth()->user()->student, PermitType::PulangAwal, now()),
                default => false,
            };
        }

        /**
         * Record the signed-in siswa's attendance after the blink challenge.
         * The step is decided server-side from today's record (check-in first,
         * then check-out) and the student id is pinned to the signed-in siswa,
         * so a tampered call can neither skip check-in nor record for someone
         * else.
         */
        public function record(int $studentId): void
        {
            abort_unless($this->isSelfService(), 403);

            $student = auth()->user()->student;
            $engine = app(RecordAttendance::class);
            $checkingOut = $this->todayAttendance !== null;

            try {
                $attendance = $checkingOut
                    ? $engine->checkOut($student, auth()->user())
                    : $engine->checkIn($student, auth()->user());

                $this->lastResult = [
                    'ok' => true,
                    'status' => $attendance->status->label(),
                    'time' => now()->format('H:i'),
                    'message' => $checkingOut
                        ? __('Absensi pulang tercatat.')
                        : __('Absensi masuk tercatat: :status.', ['status' => $attendance->status->label()]),
                ];
            } catch (AttendanceException $exception) {
                $this->lastResult = [
                    'ok' => false,
                    'status' => '—',
                    'time' => now()->format('H:i'),
                    'message' => $exception->getMessage(),
                ];
            }

            unset($this->todayAttendance, $this->history);
        }

        /**
         * @return array<int, AttendanceStatus>
         */
        public function statuses(): array
        {
            return AttendanceStatus::cases();
        }

        /**
         * The children selectable by an Orang Tua account (the personal view's
         * subject; a Siswa account always views itself).
         *
         * @return Collection<int, Student>
         */
        public function children(): Collection
        {
            $user = auth()->user();

            return match ($user->primaryRole()) {
                UserRole::OrangTua => $user->parentGuardian?->students()->orderBy('name')->get() ?? collect(),
                UserRole::Siswa => collect([$user->student])->filter(),
                default => collect(),
            };
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
         * Daily monitor rows: every scoped student with their attendance record
         * (if any) on the selected date.
         *
         * @return LengthAwarePaginator<int, Student>
         */
        #[Computed]
        public function students(): LengthAwarePaginator
        {
            $date = $this->selectedDate();

            return $this->scopedStudents()
                ->with(['classroom', 'attendances' => fn ($query) => $query->whereDate('date', $date->toDateString())])
                ->when($this->classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
                ->when($this->search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('nis', 'like', '%'.$this->search.'%'),
                ))
                ->when($this->status === 'none', fn (Builder $query) => $query->whereDoesntHave(
                    'attendances',
                    fn (Builder $inner) => $inner->whereDate('date', $date->toDateString()),
                ))
                ->when($this->status !== '' && $this->status !== 'none', fn (Builder $query) => $query->whereHas(
                    'attendances',
                    fn (Builder $inner) => $inner->whereDate('date', $date->toDateString())->where('status', $this->status),
                ))
                ->orderBy('name')
                ->paginate(10);
        }

        /**
         * Recorded/unrecorded counts per status for the selected date (F-11).
         *
         * @return array<string, int>
         */
        #[Computed]
        public function stats(): array
        {
            $studentIds = $this->scopedStudents()->pluck('id');

            $byStatus = Attendance::query()
                ->onDate($this->selectedDate())
                ->whereIn('student_id', $studentIds)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $stats = [];

            foreach (AttendanceStatus::cases() as $case) {
                $stats[$case->value] = (int) ($byStatus[$case->value] ?? 0);
            }

            $stats['none'] = max(0, $studentIds->count() - array_sum($stats));

            return $stats;
        }

        /**
         * Personal history rows for the selected month.
         *
         * @return LengthAwarePaginator<int, Attendance>
         */
        #[Computed]
        public function history(): LengthAwarePaginator
        {
            $month = rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m', $this->month), now(), false);
            $subject = $this->children()->firstWhere('id', $this->childId);

            return Attendance::query()
                ->where('student_id', $subject?->id ?? 0)
                ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderByDesc('date')
                ->paginate(10);
        }

        /**
         * Manually set a student's status on the selected date (izin/sakit/alpha
         * or a correction). Guru Piket / Super Admin only.
         */
        public function markStatus(int $studentId, string $status): void
        {
            abort_unless($this->canManage(), 403);

            $newStatus = AttendanceStatus::tryFrom($status);
            $date = $this->selectedDate();

            if ($newStatus === null || $date->isFuture()) {
                $this->dispatch('swal', icon: 'error', title: __('Status atau tanggal tidak valid.'));

                return;
            }

            $student = Student::query()->findOrFail($studentId);

            app(RecordAttendance::class)->markStatus($student, $newStatus, auth()->user(), $date);

            unset($this->students, $this->stats);

            $this->dispatch('swal', icon: 'success', title: __(':name ditandai :status.', [
                'name' => $student->name,
                'status' => $newStatus->label(),
            ]));
        }

        /**
         * Mark every student without a record on the selected date as Alpha.
         */
        public function markAbsentees(): void
        {
            abort_unless($this->canManage(), 403);

            $date = $this->selectedDate();

            if ($date->isFuture()) {
                $this->dispatch('swal', icon: 'error', title: __('Tanggal belum berjalan.'));

                return;
            }

            $marked = app(RecordAttendance::class)->markAbsentees(auth()->user(), $date);

            unset($this->students, $this->stats);

            $this->dispatch('swal', icon: 'success', title: __(':count siswa ditandai Alpha.', ['count' => $marked]));
        }

        private function selectedDate(): CarbonInterface
        {
            return rescue(fn (): CarbonInterface => Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay(), now()->startOfDay(), false);
        }

        /**
         * Students scoped to what the signed-in role may monitor: wali kelas see
         * their homeroom, everyone else sees all (siswa/ortu use the history view).
         *
         * @return Builder<Student>
         */
        private function scopedStudents(): Builder
        {
            $user = auth()->user();

            return match ($user->primaryRole()) {
                UserRole::WaliKelas => Student::query()->whereIn(
                    'classroom_id',
                    $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
                ),
                default => Student::query(),
            };
        }

        public function exportExcel(): BinaryFileResponse
        {
            abort_unless($this->canManage(), 403);

            $export = new AttendanceDailyExports(
                date: $this->selectedDate(),
                classroomId: $this->classroomId,
                status: $this->status,
                search: $this->search,
            );

            return Excel::download($export, 'absensi-'.$this->selectedDate()->format('Y-m-d').'.xlsx');
        }
    }; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <x-ui.page-header :title="__('Absensi')"
            :subtitle="$this->isSelfService()
                ? __('Scan wajah untuk absensi dan lihat riwayat kehadiran Anda.')
                : ($this->isPersonal() ? __('Riwayat kehadiran.') : __('Monitoring kehadiran siswa harian.'))">
        <x-slot:actions>
                @if ($this->canManage())
                    <x-ui.button variant="secondary" icon="download-outline" wire:click="exportExcel">
                        {{ __('Export Excel') }}
                    </x-ui.button>
                @endif
                <x-ui.button variant="secondary" icon="stats-chart-outline" :href="route('attendance.absensi.recap')" wire:navigate>
                    {{ __('Rekap') }}
                </x-ui.button>
                @if ($this->canManage())
                    <x-ui.button variant="secondary" icon="settings-outline" :href="route('attendance.absensi.settings')" wire:navigate>
                        {{ __('Pengaturan') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="scan-outline" :href="route('attendance.absensi.scan')" wire:navigate>
                        {{ __('Scan Absensi') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if ($this->isPersonal())
            @if ($this->isSelfService())
                {{-- ============ Self-service face scan (Siswa) ============ --}}
                @vite('resources/js/face-attendance.js')
                @php($step = $this->nextScanStep())
                @php($attendance = $this->todayAttendance)

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    <div class="flex flex-col rounded-xl bg-white p-6 drop-shadow-lg lg:col-span-3">
                        {{-- Step indicator: masuk always precedes pulang. --}}
                        <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1">
                            <div @class([
                                'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold',
                                'bg-primary-600 text-white shadow' => $step === 'masuk',
                                'bg-green-100 text-green-700' => $attendance?->checked_in_at !== null,
                                'text-gray-500' => $step !== 'masuk' && $attendance?->checked_in_at === null,
                            ])>
                                <ion-icon name="{{ $attendance?->checked_in_at !== null ? 'checkmark-circle-outline' : 'log-in-outline' }}" class="text-lg"></ion-icon>
                                {{ __('Absensi Masuk') }}
                                @if ($attendance?->checked_in_at !== null)
                                    <span class="tabular-nums">{{ $attendance->checked_in_at->format('H:i') }}</span>
                                @endif
                            </div>
                            <div @class([
                                'flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold',
                                'bg-primary-600 text-white shadow' => $step === 'pulang',
                                'bg-green-100 text-green-700' => $attendance?->checked_out_at !== null,
                                'text-gray-400' => $step !== 'pulang' && $attendance?->checked_out_at === null,
                            ])>
                                <ion-icon name="{{ $attendance?->checked_out_at !== null ? 'checkmark-circle-outline' : ($step === 'pulang' ? 'log-out-outline' : 'lock-closed-outline') }}" class="text-lg"></ion-icon>
                                {{ __('Absensi Pulang') }}
                                @if ($attendance?->checked_out_at !== null)
                                    <span class="tabular-nums">{{ $attendance->checked_out_at->format('H:i') }}</span>
                                @endif
                            </div>
                        </div>

                        @if ($this->isScannerOpen())
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
                        @else
                            <div class="flex flex-1 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-gray-50 py-14 text-center">
                                @if ($step === 'pulang')
                                    <ion-icon name="time-outline" class="text-4xl text-gray-400"></ion-icon>
                                    <p class="text-sm font-medium text-gray-600">
                                        {{ __('Absensi masuk sudah tercatat. Absensi pulang dibuka pukul :time.', ['time' => substr($this->setting->check_out_after, 0, 5)]) }}
                                    </p>
                                @elseif ($attendance?->isCheckedOut())
                                    <ion-icon name="checkmark-done-circle-outline" class="text-4xl text-green-500"></ion-icon>
                                    <p class="text-sm font-medium text-gray-600">{{ __('Absensi hari ini sudah selesai. Sampai jumpa besok!') }}</p>
                                @else
                                    <x-attendance.status-badge :status="$attendance->status" />
                                    <p class="text-sm font-medium text-gray-600">
                                        {{ __('Kehadiran hari ini tercatat sebagai :status.', ['status' => $attendance->status->label()]) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Result card + schedule info. --}}
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <div class="rounded-xl bg-white p-6 drop-shadow-lg">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Hasil Terakhir') }}</p>

                            @if ($lastResult === null)
                                <div class="flex flex-col items-center gap-2 py-10 text-gray-400">
                                    <ion-icon name="scan-outline" class="text-4xl"></ion-icon>
                                    <span class="text-sm">{{ __('Belum ada scan pada sesi ini.') }}</span>
                                </div>
                            @else
                                <div class="mt-4 flex items-center gap-4">
                                    <img class="h-16 w-16 rounded-2xl object-cover"
                                        src="{{ auth()->user()->student->avatar_url ?? asset('assets/placeholder.png') }}"
                                        alt="{{ auth()->user()->student->name }}" />
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800">{{ auth()->user()->student->name }}</span>
                                        <span class="text-sm text-gray-500">{{ auth()->user()->student->classroom?->name ?? '—' }}</span>
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
                                {{ __('Absensi pulang hanya dapat dilakukan setelah absensi masuk tercatat.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ============ Personal history (Siswa / Orang Tua) ============ --}}
            <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    @if ($this->children()->count() > 1)
                        <select wire:model.live="childId"
                            class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach ($this->children() as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <input type="month" wire:model.live="month"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>

                <div class="rounded-2xl border overflow-auto">
                    <table class="border-collapse min-w-full leading-normal">
                        <thead>
                            <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                                <th class="py-3 px-4">{{ __('Tanggal') }}</th>
                                <th class="py-3 px-4">{{ __('Masuk') }}</th>
                                <th class="py-3 px-4">{{ __('Pulang') }}</th>
                                <th class="py-3 px-4">{{ __('Status') }}</th>
                                <th class="py-3 px-4">{{ __('Keterangan') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-gray-700 whitespace-nowrap">
                            @forelse ($this->history as $attendance)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 px-4">{{ $attendance->date->translatedFormat('d M Y') }}</td>
                                    <td class="py-3 px-4 tabular-nums">{{ $attendance->checked_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="py-3 px-4 tabular-nums">{{ $attendance->checked_out_at?->format('H:i') ?? '—' }}</td>
                                    <td class="py-3 px-4">
                                        <x-attendance.status-badge :status="$attendance->status" />
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-500">{{ $attendance->note ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-5 text-center text-gray-500">{{ __('Belum ada catatan kehadiran pada bulan ini.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->history->links() }}
                </div>
            </div>
        @else
            {{-- ============ Daily monitor (staff) ============ --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($this->statuses() as $case)
                    <div class="rounded-xl bg-white p-4 drop-shadow-lg">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $case->label() }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gray-800">{{ $this->stats[$case->value] }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl bg-white p-4 drop-shadow-lg">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Belum Absen') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-800">{{ $this->stats['none'] }}</p>
                </div>
            </div>

            <div class="flex-col bg-white rounded-xl p-6 drop-shadow-lg">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <input type="date" wire:model.live="date"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    <select wire:model.live="classroomId"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">{{ __('Semua Kelas') }}</option>
                        @foreach ($this->classrooms as $classroom)
                            <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="status"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">{{ __('Semua Status') }}</option>
                        @foreach ($this->statuses() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                        <option value="none">{{ __('Belum Absen') }}</option>
                    </select>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Cari nama / NIS…') }}"
                        class="w-full max-w-xs rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500" />

                    @if ($this->canManage())
                        <button type="button" x-data
                            @click="confirmDelete(() => $wire.markAbsentees(), {
                                title: @js(__('Tandai Alpha?')),
                                text: @js(__('Semua siswa yang belum tercatat pada tanggal terpilih akan ditandai Alpha dan poinnya dikurangi otomatis.')),
                                confirmButtonText: @js(__('Ya, tandai')),
                            })"
                            class="ml-auto inline-flex items-center gap-2 rounded-md bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                            <ion-icon name="person-remove-outline" class="text-lg"></ion-icon>
                            {{ __('Tandai Alpha') }}
                        </button>
                    @endif
                </div>

                <div class="rounded-2xl border overflow-auto">
                    <table class="border-collapse min-w-full leading-normal">
                        <thead>
                            <tr class="text-gray-500 font-normal text-sm text-left whitespace-nowrap border-b">
                                <th class="py-3 px-4">No</th>
                                <th class="py-3 px-4">{{ __('Siswa') }}</th>
                                <th class="py-3 px-4">{{ __('Kelas') }}</th>
                                <th class="py-3 px-4">{{ __('Masuk') }}</th>
                                <th class="py-3 px-4">{{ __('Pulang') }}</th>
                                <th class="py-3 px-4">{{ __('Status') }}</th>
                                @if ($this->canManage())
                                    <th class="py-3 px-4 text-center">{{ __('Ubah Status') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white text-gray-700 whitespace-nowrap">
                            @forelse ($this->students as $key => $student)
                                @php($attendance = $student->attendances->first())
                                <tr class="border-b last:border-0">
                                    <td class="py-3 px-4">{{ $key + $this->students->firstItem() }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <img class="h-9 w-9 rounded-full object-cover"
                                                src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}"
                                                alt="{{ $student->name }}" />
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ $student->name }}</span>
                                                <span class="text-xs text-gray-400">{{ $student->nis }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">{{ $student->classroom?->name ?? '—' }}</td>
                                    <td class="py-3 px-4 tabular-nums">{{ $attendance?->checked_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="py-3 px-4 tabular-nums">{{ $attendance?->checked_out_at?->format('H:i') ?? '—' }}</td>
                                    <td class="py-3 px-4">
                                        @if ($attendance !== null)
                                            <x-attendance.status-badge :status="$attendance->status" />
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                                {{ __('Belum Absen') }}
                                            </span>
                                        @endif
                                    </td>
                                    @if ($this->canManage())
                                        <td class="py-3 px-4 text-center">
                                            <select wire:change="markStatus({{ $student->id }}, $event.target.value)"
                                                class="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                                <option value="" @selected($attendance === null)>{{ __('Pilih…') }}</option>
                                                @foreach ($this->statuses() as $case)
                                                    <option value="{{ $case->value }}" @selected($attendance?->status === $case)>{{ $case->label() }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->canManage() ? 7 : 6 }}" class="p-5 text-center text-gray-500">{{ __('Tidak ada siswa yang cocok dengan filter.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->students->links() }}
                </div>
            </div>
        @endif
    </div>
