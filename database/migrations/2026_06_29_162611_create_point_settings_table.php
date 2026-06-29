<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton configuration for the point engine: the starting balance every
     * student begins with, the progress-bar target ("X of target"), and the
     * minimum threshold used for status/risk indicators.
     */
    public function up(): void
    {
        Schema::create('point_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('initial_point')->default(100);
            $table->unsignedInteger('target_point')->default(100);
            $table->unsignedInteger('min_point')->default(40);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_settings');
    }
};
