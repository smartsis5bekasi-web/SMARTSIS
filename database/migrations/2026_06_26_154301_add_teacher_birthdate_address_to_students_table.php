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
            $table->string('avatar_url')->nullable()->after('name');
            $table->foreignId('teacher_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('address')->nullable()->after('birth_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'teacher_id')) {
                $table->dropConstrainedForeignId('teacher_id');
            }

            foreach (['avatar_url', 'birth_date', 'address'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
