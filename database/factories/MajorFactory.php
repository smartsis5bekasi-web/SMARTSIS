<?php

namespace Database\Factories;

use App\Models\Major;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Major>
 */
class MajorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'IPA', 'IPS', 'Bahasa', 'MIPA', 'TKJ', 'RPL',
        ]);

        return [
            'name' => $name,
            'code' => strtoupper($name),
        ];
    }
}
