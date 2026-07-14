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
        Schema::create('warning_settings', function (Blueprint $table) {
            $table->id();
            // A student whose points fall to (or below) a threshold qualifies
            // for that SP level (F-20 defaults: SP1 ≤80, SP2 ≤60, SP3 ≤40).
            $table->unsignedInteger('sp1_threshold')->default(80);
            $table->unsignedInteger('sp2_threshold')->default(60);
            $table->unsignedInteger('sp3_threshold')->default(40);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warning_settings');
    }
};
