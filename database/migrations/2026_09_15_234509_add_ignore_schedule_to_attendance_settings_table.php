<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin escape hatch that accepts check-in and check-out at any hour,
     * so the absensi flow can be walked end to end outside the school day
     * (demos, and testing a deployment in the evening).
     *
     * It only opens the two windows; the Hadir/Terlambat threshold still
     * follows `late_after`, so a record made while this is on stays honest
     * about the time it was taken.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('ignore_schedule')->default(false)->after('check_out_after');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('ignore_schedule');
        });
    }
};
