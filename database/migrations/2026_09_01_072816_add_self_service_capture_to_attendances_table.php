<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capture the evidence a self-service absensi produces: the selfie taken
     * at the moment of the scan, the GPS fix the browser reported, and — for
     * the sakit/izin shortcuts — the uploaded proof plus its reason.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('check_in_photo_path')->nullable()->after('checked_in_at');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_photo_path');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            // Radius in meters the browser reported for the fix (GPS accuracy).
            $table->unsignedInteger('check_in_accuracy')->nullable()->after('check_in_longitude');

            $table->string('check_out_photo_path')->nullable()->after('checked_out_at');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_photo_path');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->unsignedInteger('check_out_accuracy')->nullable()->after('check_out_longitude');

            // Sakit/izin shortcut: the uploaded surat and the student's reason.
            $table->string('attachment_path')->nullable()->after('note');
            $table->text('reason')->nullable()->after('attachment_path');

            // A camera scan verifies itself; a self-declared sakit/izin waits
            // for a Guru Piket / Wali Kelas to confirm the attachment.
            $table->timestamp('verified_at')->nullable()->after('reason');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });

        // Everything recorded before this migration came from the staffed
        // kiosk or a manual staff mark, both of which are verified by
        // definition — leaving them null would flag the whole archive.
        DB::table('attendances')->update(['verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'check_in_photo_path',
                'check_in_latitude',
                'check_in_longitude',
                'check_in_accuracy',
                'check_out_photo_path',
                'check_out_latitude',
                'check_out_longitude',
                'check_out_accuracy',
                'attachment_path',
                'reason',
                'verified_at',
            ]);
        });
    }
};
