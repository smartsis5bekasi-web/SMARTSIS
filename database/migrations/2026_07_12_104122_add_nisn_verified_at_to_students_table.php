<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('nisn_verified_at')->nullable()->after('current_point');
        });

        // Students who already finished onboarding have implicitly passed the
        // NISN step; don't force them through the wizard again.
        DB::table('students')
            ->whereNotNull('onboarded_at')
            ->update(['nisn_verified_at' => DB::raw('onboarded_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('nisn_verified_at');
        });
    }
};
