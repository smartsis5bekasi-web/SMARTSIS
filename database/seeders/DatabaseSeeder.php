<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. In production the demo seeder only
     * adds the staff accounts ({@see DemoAccountSeeder}).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DemoAccountSeeder::class,
            PointSeeder::class,
            AttendanceSeeder::class,
            WarningSeeder::class,
        ]);
    }
}
