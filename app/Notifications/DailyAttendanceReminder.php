<?php

namespace App\Notifications;

use App\Models\AttendanceSetting;
use Illuminate\Notifications\Notification;

/**
 * The morning nudge for a student who has not checked in yet (PRD F-09).
 *
 * Delivered to the in-app bell only, never by mail: it is time-sensitive for
 * exactly one morning and an inbox full of daily reminders would be noise.
 * Sent by {@see App\Console\Commands\SendDailyAttendanceReminders}.
 */
class DailyAttendanceReminder extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload every notification in this app shares, so the bell can render
     * any of them without knowing which notification it is looking at.
     *
     * @return array{title: string, body: string, icon: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $lateAfter = substr(AttendanceSetting::current()->late_after, 0, 5);

        return [
            'title' => __('Jangan lupa absen hari ini'),
            'body' => __('Lakukan absensi sebelum pukul :time agar tidak tercatat terlambat.', [
                'time' => $lateAfter,
            ]),
            'icon' => 'alarm-outline',
            'url' => route('attendance.absensi.scan'),
        ];
    }
}
