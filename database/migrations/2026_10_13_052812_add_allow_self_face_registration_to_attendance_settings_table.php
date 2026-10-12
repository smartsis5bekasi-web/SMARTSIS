<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether students may register their own face during onboarding. Off by
     * default: an unsupervised capture is how one student ends up enrolled on
     * a classmate's account, so faces are registered in person at the kiosk.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('allow_self_face_registration')->default(false)->after('ignore_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('allow_self_face_registration');
        });
    }
};
