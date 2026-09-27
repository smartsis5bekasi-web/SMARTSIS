<?php

namespace App\Console\Commands;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * End-of-day sweep that puts every student with no attendance on the books as
 * "Menunggu Konfirmasi", so a student who never logged in or scanned is not
 * silently missing from the day. A teacher then confirms each one as alpha
 * (which applies the alpha point rule), sakit, or izin.
 *
 * Scheduled hourly on weekdays (see routes/console.php) and idempotent; it only
 * acts once the check-out window has opened, so students still on their way
 * in are not flagged. A day on which nobody recorded anything is treated as a
 * holiday and skipped — otherwise a tanggal merah would flag the whole school.
 */
class MarkPendingAttendances extends Command
{
    protected $signature = 'attendance:mark-pending
                            {--date= : Sweep this date instead of today (Y-m-d)}
                            {--force : Sweep even on a weekend or a day with no attendance recorded at all}';

    protected $description = 'Record students with no attendance for the day as pending, for a teacher to confirm';

    public function handle(RecordAttendance $engine): int
    {
        $date = $this->option('date') !== null
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        if ($date->isAfter(Carbon::today())) {
            $this->error(__('Tanggal :date belum berjalan.', ['date' => $date->toDateString()]));

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if (! $force && $date->isWeekend()) {
            $this->info(__(':date adalah akhir pekan — dilewati.', ['date' => $date->toDateString()]));

            return self::SUCCESS;
        }

        if (! $force && $date->isToday() && ! AttendanceSetting::current()->isCheckOutTimeReached(now())) {
            $this->info(__('Hari sekolah belum selesai — siswa yang belum absen belum ditandai.'));

            return self::SUCCESS;
        }

        $anyoneRecorded = Attendance::query()
            ->onDate($date)
            ->where('status', '!=', AttendanceStatus::Pending)
            ->exists();

        if (! $force && ! $anyoneRecorded) {
            $this->info(__('Tidak ada satu pun absensi pada :date — dianggap hari libur dan dilewati.', ['date' => $date->toDateString()]));

            return self::SUCCESS;
        }

        $written = $engine->markPending($date);

        $this->info(__(':count siswa tanpa absensi pada :date ditandai Menunggu Konfirmasi.', [
            'count' => $written,
            'date' => $date->toDateString(),
        ]));

        return self::SUCCESS;
    }
}
