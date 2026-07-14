<?php

namespace Database\Factories;

use App\Models\WarningSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarningSetting>
 */
class WarningSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sp1_threshold' => 80,
            'sp2_threshold' => 60,
            'sp3_threshold' => 40,
        ];
    }
}
