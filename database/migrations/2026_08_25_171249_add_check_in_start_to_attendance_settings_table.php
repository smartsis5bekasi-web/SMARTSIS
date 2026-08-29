<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Until now check-in was accepted at any hour, so a student could record
     * the morning attendance the night before. This closes the window at the
     * front the same way `check_out_after` closes it at the back.
     *
     * The school day the defaults describe is 07:00–15:00 with a 30 minute
     * grace period, so opening check-in at 07:00 leaves the old 07:00 late
     * threshold with no room in front of it — it moves to 07:30. Only rows
     * still sitting on that old default are touched; a school that already
     * tuned its own threshold keeps it.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->time('check_in_start')->default('07:00:00')->after('id');
        });

        DB::table('attendance_settings')
            ->where('late_after', '07:00:00')
            ->update(['late_after' => '07:30:00']);
    }

    public function down(): void
    {
        DB::table('attendance_settings')
            ->where('late_after', '07:30:00')
            ->update(['late_after' => '07:00:00']);

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('check_in_start');
        });
    }
};
