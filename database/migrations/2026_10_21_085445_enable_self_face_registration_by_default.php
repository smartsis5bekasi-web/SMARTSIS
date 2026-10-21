<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students register their own face during onboarding again. With the
     * in-person-only default, students onboarded without a face and could
     * never be recognised by the classroom kiosk. Switching the existing
     * setting on also sends every onboarded student without a face back to
     * the face step ({@see Student::needsOnboarding()}).
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('allow_self_face_registration')->default(true)->change();
        });

        DB::table('attendance_settings')->update(['allow_self_face_registration' => true]);
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('allow_self_face_registration')->default(false)->change();
        });
    }
};
