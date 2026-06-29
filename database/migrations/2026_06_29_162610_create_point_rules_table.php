<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin-configurable catalogue of point-affecting activities
     * ("Konfigurasi Poin", PRD F-12). Each rule pairs a name with a type
     * (addition/deduction), a source module, and the magnitude awarded.
     */
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('source');
            $table->unsignedInteger('point');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['source', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
