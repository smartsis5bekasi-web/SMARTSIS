<?php

namespace App\Console\Commands;

use App\Enums\PermitStatus;
use App\Models\Student;
use App\Notifications\DailyAttendanceReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Morning sweep that nudges students who still have not checked in.
 *
 * Scheduled on weekday mornings ahead of the late threshold (see
 * routes/console.php). Students who already checked in, who hold an approved
 * permit for the day, or whose account is disabled are left alone — a reminder
 * they cannot act on is worse than no reminder.
 */
class SendDailyAttendanceReminders extends Command
{
    protected $signature = 'attendance:remind
                            {--date= : Run the sweep for this date instead of today (Y-m-d)}';

    protected $description = 'Remind students who have not recorded their attendance yet today';

    public function handle(): int
    {
        $date = $this->option('date') !== null
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $students = Student::query()
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $date->toDateString()))
            ->whereDoesntHave('permits', fn (Builder $query) => $query
                ->where('status', PermitStatus::Approved)
                ->whereDate('date', $date->toDateString()))
            ->with('user')
            ->get();

        $recipients = $students->pluck('user')->filter();

        if ($recipients->isEmpty()) {
            $this->info(__('Semua siswa sudah absen atau sedang izin — tidak ada pengingat yang dikirim.'));

            return self::SUCCESS;
        }

        Notification::send($recipients, new DailyAttendanceReminder);

        $this->info(__('Pengingat absensi dikirim ke :count siswa.', ['count' => $recipients->count()]));

        return self::SUCCESS;
    }
}
