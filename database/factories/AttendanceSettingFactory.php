<?php

namespace Database\Factories;

use App\Models\AttendanceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSetting>
 */
class AttendanceSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'check_in_start' => '07:00:00',
            'late_after' => '07:30:00',
            'check_out_after' => '15:00:00',
            'late_rule_id' => null,
            'alpha_rule_id' => null,
        ];
    }
}
