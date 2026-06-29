<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Student violations (Modul Pelanggaran, PRD F-15..F-17). Each violation
     * references the deduction point_rule that defines its category and point
     * weight; approving it (by Guru BK) drives the point engine. UI ships in a
     * later iteration — the schema is created now so its relations align with
     * the point system.
     */
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('chronology')->nullable();
            $table->string('evidence_path')->nullable();
            $table->date('occurred_on')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
