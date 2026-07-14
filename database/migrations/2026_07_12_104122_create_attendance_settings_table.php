<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            // Check-ins after this time are recorded as Terlambat.
            $table->time('late_after')->default('07:00:00');
            // Check-outs are only accepted from this time onwards.
            $table->time('check_out_after')->default('15:00:00');
            // Point rules applied automatically for late arrivals / absences.
            $table->foreignId('late_rule_id')->nullable()->constrained('point_rules')->nullOnDelete();
            $table->foreignId('alpha_rule_id')->nullable()->constrained('point_rules')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
