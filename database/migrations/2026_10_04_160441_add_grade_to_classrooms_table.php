<?php

use App\Enums\GradeLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tingkat (10/11/12) of each classroom, so a holiday can cover whole
     * grades. Existing classrooms are filled in from their name ("XI IPA 1"
     * → 11); anything that cannot be guessed stays empty for an admin to set.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade')->nullable()->after('name');
        });

        DB::table('classrooms')->select(['id', 'name'])->orderBy('id')->each(function (object $classroom): void {
            $grade = GradeLevel::guessFromName($classroom->name);

            if ($grade !== null) {
                DB::table('classrooms')->where('id', $classroom->id)->update(['grade' => $grade->value]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('grade');
        });
    }
};
