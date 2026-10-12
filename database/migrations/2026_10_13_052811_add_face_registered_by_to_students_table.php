<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who captured the face template. A staff member here means the face was
     * registered in person (kiosk or admin page); the student's own account,
     * or null for templates stored before this column existed, means it was
     * self-registered and never checked by anyone.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('face_registered_by')->nullable()->after('face_registered_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('face_registered_by');
        });
    }
};
