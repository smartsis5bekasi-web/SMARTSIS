<?php

namespace Database\Seeders;

use App\Enums\PointSource;
use App\Models\AttendanceSetting;
use App\Models\PointRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the Smart Attendance defaults: the singleton settings row wired to
     * the starter "Terlambat" and "Alpha" point rules from {@see PointSeeder}.
     */
    public function run(): void
    {
        $setting = AttendanceSetting::current();

        $setting->update([
            'late_rule_id' => $setting->late_rule_id
                ?? PointRule::query()->where('source', PointSource::Attendance)->where('name', 'Terlambat')->value('id'),
            'alpha_rule_id' => $setting->alpha_rule_id
                ?? PointRule::query()->where('source', PointSource::Attendance)->where('name', 'Alpha')->value('id'),
        ]);
    }
}
