<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable audit trail of every point change ("Histori Poin", PRD
     * F-14). The polymorphic `source` ties each entry back to the record that
     * caused it (violation, achievement, attendance, …) so attendance can plug
     * in later without a schema change.
     */
    public function up(): void
    {
        Schema::create('point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->nullableMorphs('source');
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_logs');
    }
};
