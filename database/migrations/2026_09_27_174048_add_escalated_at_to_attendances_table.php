<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the system turned an unconfirmed "Menunggu Konfirmasi" day into
     * Alpha on its own. Kept so the record carries an "Alpha otomatis" label
     * until a teacher checks it, and cleared once a teacher changes the status.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('escalated_at');
        });
    }
};
