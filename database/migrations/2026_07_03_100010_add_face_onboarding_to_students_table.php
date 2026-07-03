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
        Schema::table('students', function (Blueprint $table) {
            $table->json('face_descriptors')->nullable()->after('current_point');
            $table->timestamp('face_registered_at')->nullable()->after('face_descriptors');
            $table->timestamp('onboarded_at')->nullable()->after('face_registered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['face_descriptors', 'face_registered_at', 'onboarded_at']);
        });
    }
};
