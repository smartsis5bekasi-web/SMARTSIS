<?php

namespace Database\Seeders;

use App\Enums\PointSource;
use App\Enums\PointType;
use App\Models\PointRule;
use App\Models\PointSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PointSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the point engine defaults: the singleton settings row and the
     * starter point rules from the PRD (F-12 example table).
     */
    public function run(): void
    {
        PointSetting::query()->firstOrCreate([], [
            'initial_point' => 100,
            'target_point' => 100,
            'min_point' => 40,
        ]);

        $rules = [
            ['name' => 'Terlambat', 'type' => PointType::Deduction, 'source' => PointSource::Attendance, 'point' => 2],
            ['name' => 'Alpha', 'type' => PointType::Deduction, 'source' => PointSource::Attendance, 'point' => 5],
            ['name' => 'Merokok', 'type' => PointType::Deduction, 'source' => PointSource::Violation, 'point' => 30],
            ['name' => 'Juara Lomba', 'type' => PointType::Addition, 'source' => PointSource::Achievement, 'point' => 20],
        ];

        foreach ($rules as $rule) {
            PointRule::query()->firstOrCreate(
                ['name' => $rule['name']],
                [...$rule, 'is_active' => true],
            );
        }
    }
}
