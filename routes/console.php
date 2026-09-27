<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Needs `* * * * * php artisan schedule:run` on the server. Times are in the
| application timezone (Asia/Jakarta).
|
*/

// Nudge students who have not checked in yet, an hour before the default late
// threshold (07:00) so there is still time to act on it.
Schedule::command('attendance:remind')
    ->weekdays()
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Put every student who ended the school day with no attendance at all on the
// books as "Menunggu Konfirmasi" for a teacher to confirm (alpha/sakit/izin).
// Hourly so it catches the day as soon as the configured check-out time
// passes; the command itself waits for that time and skips holidays.
Schedule::command('attendance:mark-pending')
    ->weekdays()
    ->hourly()
    ->withoutOverlapping();

// A pending day no teacher confirmed within the configured number of school
// days (Pengaturan Absensi, default 3) becomes Alpha and loses the alpha
// points. Hourly and idempotent, so a missed run is simply caught up.
Schedule::command('attendance:escalate-pending')
    ->hourly()
    ->withoutOverlapping();

// One reminder per student per school day adds up fast, and a read one is of
// no use after the day it arrived. Drop anything read over a month ago.
Schedule::call(function (): void {
    DatabaseNotification::query()
        ->whereNotNull('read_at')
        ->where('read_at', '<', now()->subMonth())
        ->delete();
})->dailyAt('01:00')->name('prune-read-notifications');
