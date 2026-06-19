<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a minimal but complete data set: sample master data plus one demo
 * account per role, each wired to the appropriate profile record. Every demo
 * account uses the password "password".
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $majorIpa = Major::firstOrCreate(['code' => 'IPA'], ['name' => 'IPA']);
        Major::firstOrCreate(['code' => 'IPS'], ['name' => 'IPS']);

        $year = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['is_active' => true, 'started_on' => '2025-07-01', 'ended_on' => '2026-06-30'],
        );

        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI IPA 1'],
            ['major_id' => $majorIpa->id, 'academic_year_id' => $year->id],
        );

        // Roles backed by a teacher profile.
        $teacherRoles = [
            UserRole::KepalaSekolah,
            UserRole::WakasekKesiswaan,
            UserRole::GuruBk,
            UserRole::WaliKelas,
            UserRole::GuruPiket,
            UserRole::GuruMapel,
        ];

        foreach ($teacherRoles as $role) {
            $user = $this->createUser($role);
            $teacher = Teacher::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $role->label(), 'nip' => fake()->unique()->numerify('##################')],
            );

            if ($role === UserRole::WaliKelas) {
                $classroom->update(['homeroom_teacher_id' => $teacher->id]);
            }
        }

        // Super Admin (no domain profile).
        $this->createUser(UserRole::SuperAdmin);

        // Student account.
        $studentUser = $this->createUser(UserRole::Siswa);
        $student = Student::firstOrCreate(
            ['nis' => '2025001'],
            [
                'user_id' => $studentUser->id,
                'nisn' => '0098765432',
                'name' => 'Siswa Demo',
                'gender' => 'L',
                'classroom_id' => $classroom->id,
                'major_id' => $majorIpa->id,
                'year_in' => 2025,
                'current_point' => 100,
            ],
        );

        // Parent account linked to the student.
        $parentUser = $this->createUser(UserRole::OrangTua);
        $parent = ParentGuardian::firstOrCreate(
            ['user_id' => $parentUser->id],
            ['name' => 'Orang Tua Demo', 'phone' => '081234567890'],
        );
        $parent->students()->syncWithoutDetaching([$student->id => ['relationship' => 'Ayah']]);
    }

    /**
     * Create (or fetch) a demo user for the given role and assign that role.
     */
    private function createUser(UserRole $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $role->value.'@smartsis.test'],
            [
                'name' => $role->label(),
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }

        return $user;
    }
}
