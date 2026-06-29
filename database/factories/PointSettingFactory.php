<?php

namespace Database\Factories;

use App\Models\PointSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointSetting>
 */
class PointSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initial_point' => 100,
            'target_point' => 100,
            'min_point' => 40,
        ];
    }
}
