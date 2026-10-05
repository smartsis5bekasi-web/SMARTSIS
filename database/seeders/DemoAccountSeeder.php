<?php

namespace Database\Seeders;

use App\Enums\GradeLevel;
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
 * Seeds one account per staff role (Super Admin, Kepala Sekolah, guru, …),
 * each wired to its teacher profile, so every part of the app can be reached
 * on a fresh install. Every account starts with the password "password" and
 * an existing account is never touched, so a changed password survives a
 * re-seed.
 *
 * Outside production it also seeds sample master data and the student side:
 * a demo siswa, their orang tua, and a few named student accounts for walking
 * through onboarding and the face kiosk. In production those would be swept
 * by the daily attendance jobs like real students, so they are left out —
 * and `php artisan demo:purge` removes them from a server they already reached.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = $this->seedStaff();

        if (app()->isProduction()) {
            return;
        }

        $this->seedStudents($teachers[UserRole::WaliKelas->value]);
    }

    /**
     * The staff logins, kept in every environment.
     *
     * @return array<int, string>
     */
    public static function staffEmails(): array
    {
        return array_map(fn (UserRole $role): string => self::emailFor($role), [
            ...self::teacherRoles(),
            UserRole::SuperAdmin,
        ]);
    }

    /**
     * The student-side logins (siswa and orang tua), seeded outside
     * production only — the list `demo:purge` deletes.
     *
     * @return array<int, string>
     */
    public static function studentEmails(): array
    {
        return [
            self::emailFor(UserRole::Siswa),
            self::emailFor(UserRole::OrangTua),
            ...array_column(self::namedStudents(), 'email'),
        ];
    }

    /**
     * Every login this seeder can create.
     *
     * @return array<int, string>
     */
    public static function emails(): array
    {
        return [...self::staffEmails(), ...self::studentEmails()];
    }

    /**
     * @return array<string, Teacher> keyed by role value
     */
    private function seedStaff(): array
    {
        $teachers = [];

        foreach (self::teacherRoles() as $role) {
            $user = $this->createUser($role);
            $teachers[$role->value] = Teacher::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $role->label(), 'nip' => fake()->unique()->numerify('##################')],
            );
        }

        // Super Admin (no domain profile).
        $this->createUser(UserRole::SuperAdmin);

        return $teachers;
    }

    private function seedStudents(Teacher $homeroomTeacher): void
    {
        $majorIpa = Major::firstOrCreate(['code' => 'IPA'], ['name' => 'IPA']);
        Major::firstOrCreate(['code' => 'IPS'], ['name' => 'IPS']);

        $year = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['is_active' => true, 'started_on' => '2025-07-01', 'ended_on' => '2026-06-30'],
        );

        $classroom = Classroom::firstOrCreate(
            ['name' => 'XI IPA 1'],
            ['grade' => GradeLevel::Eleven, 'major_id' => $majorIpa->id, 'academic_year_id' => $year->id],
        );

        $classroom->update(['homeroom_teacher_id' => $homeroomTeacher->id]);

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

        // Named student accounts, left un-onboarded so they go through NISN
        // verification and face registration on first login.
        foreach (self::namedStudents() as $data) {
            $user = $this->createAccount($data['email'], $data['name'], UserRole::Siswa);

            Student::firstOrCreate(
                ['nis' => $data['nis']],
                [
                    'user_id' => $user->id,
                    'nisn' => $data['nisn'],
                    'name' => $data['name'],
                    'gender' => 'L',
                    'classroom_id' => $classroom->id,
                    'major_id' => $majorIpa->id,
                    'year_in' => 2025,
                    'current_point' => 100,
                ],
            );
        }
    }

    /**
     * Roles backed by a teacher profile.
     *
     * @return array<int, UserRole>
     */
    private static function teacherRoles(): array
    {
        return [
            UserRole::KepalaSekolah,
            UserRole::WakasekKesiswaan,
            UserRole::GuruBk,
            UserRole::WaliKelas,
            UserRole::GuruPiket,
            UserRole::GuruMapel,
        ];
    }

    /**
     * @return array<int, array{name: string, email: string, nis: string, nisn: string}>
     */
    private static function namedStudents(): array
    {
        return [
            ['name' => 'Safrudin', 'email' => 'safrudin@smartsis.test', 'nis' => '2025002', 'nisn' => '0098765433'],
            ['name' => 'Hafidz', 'email' => 'hafidz@smartsis.test', 'nis' => '2025003', 'nisn' => '0098765434'],
        ];
    }

    private static function emailFor(UserRole $role): string
    {
        return $role->value.'@smartsis.test';
    }

    /**
     * Create (or fetch) a demo user for the given role and assign that role.
     */
    private function createUser(UserRole $role): User
    {
        return $this->createAccount(self::emailFor($role), $role->label(), $role);
    }

    /**
     * Create (or fetch) a demo user by email and assign it the given role.
     */
    private function createAccount(string $email, string $name, UserRole $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
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
