<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many school days a teacher has to confirm a "Menunggu Konfirmasi"
     * day before it becomes Alpha automatically (0 turns the rule off).
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('pending_alpha_after_days')->default(3)->after('ignore_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('pending_alpha_after_days');
        });
    }
};
