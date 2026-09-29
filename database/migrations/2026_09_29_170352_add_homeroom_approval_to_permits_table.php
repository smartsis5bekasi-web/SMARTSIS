<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every permit now also needs the student's wali kelas to sign off, next
     * to the Guru Piket decision already held in `decided_by`/`decided_at`.
     *
     * Permits a wali kelas approved under the old single-approval flow carry
     * that approval over, so they do not reappear as waiting on the wali kelas.
     */
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->foreignId('homeroom_approved_by')->nullable()->after('decision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('homeroom_approved_at')->nullable()->after('homeroom_approved_by');
            $table->string('homeroom_note')->nullable()->after('homeroom_approved_at');
        });

        $approvedByWaliKelas = DB::table('permits')
            ->join('students', 'students.id', '=', 'permits.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->join('teachers', 'teachers.id', '=', 'classrooms.homeroom_teacher_id')
            ->whereColumn('teachers.user_id', 'permits.decided_by')
            ->where('permits.status', 'approved')
            ->pluck('permits.id');

        DB::table('permits')->whereIn('id', $approvedByWaliKelas)->update([
            'homeroom_approved_by' => DB::raw('decided_by'),
            'homeroom_approved_at' => DB::raw('decided_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homeroom_approved_by');
            $table->dropColumn(['homeroom_approved_at', 'homeroom_note']);
        });
    }
};
