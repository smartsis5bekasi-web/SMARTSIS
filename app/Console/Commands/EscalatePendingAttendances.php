<?php

namespace App\Console\Commands;

use App\Actions\Attendance\RecordAttendance;
use App\Models\AttendanceSetting;
use Illuminate\Console\Command;

/**
 * Turns "Menunggu Konfirmasi" days that no teacher confirmed within the
 * configured number of school days into Alpha, applying the alpha point rule
 * (PRD F-13 — alpha deducts points automatically). Each one is labelled
 * "Alpha otomatis" and can still be corrected; a correction gives the points
 * back.
 *
 * Scheduled hourly (see routes/console.php) and idempotent.
 */
class EscalatePendingAttendances extends Command
{
    protected $signature = 'attendance:escalate-pending';

    protected $description = 'Turn pending attendance no teacher confirmed in time into Alpha';

    public function handle(RecordAttendance $engine): int
    {
        $setting = AttendanceSetting::current();

        if (! $setting->autoAlphaEnabled()) {
            $this->info(__('Alpha otomatis dimatikan di Pengaturan Absensi.'));

            return self::SUCCESS;
        }

        if (! $setting->deductsForAlpha()) {
            $this->warn(__('Aturan Poin Alpha belum dipilih atau nonaktif — Alpha otomatis tidak akan memotong poin.'));
        }

        $escalated = $engine->escalatePending();

        $this->info(__(':count absensi yang tidak dikonfirmasi dalam :days hari sekolah menjadi Alpha.', [
            'count' => $escalated,
            'days' => $setting->pending_alpha_after_days,
        ]));

        return self::SUCCESS;
    }
}
