<?php

namespace Database\Seeders;

use App\Models\WarningSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarningSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the Warning Letter defaults: the singleton thresholds row from the
     * PRD (F-20 — SP1 ≤80, SP2 ≤60, SP3 ≤40).
     */
    public function run(): void
    {
        WarningSetting::current();
    }
}
