<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the verification note ("alasan tolak / revisi", PRD F-16) to
     * violations, mirroring the achievements schema.
     */
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->string('note')->nullable()->after('chronology');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
