<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a user without a role still sees the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('belum memiliki peran');
});

test('leadership roles see school-wide stats and recent students', function (UserRole $role) {
    Student::factory()->create(['name' => 'Budi Santoso']);
    $this->actingAs(userWithRole($role));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee($role->label())
        ->assertSee('Total Siswa')
        ->assertSee('Total Guru')
        ->assertSee('Siswa Terbaru')
        ->assertSee('Budi Santoso');
})->with([
    UserRole::SuperAdmin,
    UserRole::KepalaSekolah,
    UserRole::WakasekKesiswaan,
]);

test('guru bk sees the discipline-focused stats', function () {
    Student::factory()->create(['current_point' => 30]);
    $this->actingAs(userWithRole(UserRole::GuruBk));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Rata-rata Poin')
        ->assertSee('Perlu Pembinaan');
});

test('wali kelas only sees their own homeroom class students', function () {
    $year = AcademicYear::factory()->create();
    $user = userWithRole(UserRole::WaliKelas);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $myClass = Classroom::factory()->create(['name' => 'XI IPA 1', 'academic_year_id' => $year->id, 'homeroom_teacher_id' => $teacher->id]);
    $otherClass = Classroom::factory()->create(['name' => 'XII IPS 2', 'academic_year_id' => $year->id]);

    Student::factory()->create(['name' => 'Anak Kelasku', 'classroom_id' => $myClass->id]);
    Student::factory()->create(['name' => 'Bukan Kelasku', 'classroom_id' => $otherClass->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Siswa Kelas XI IPA 1')
        ->assertSee('Anak Kelasku')
        ->assertDontSee('Bukan Kelasku');
});

test('a student sees their own profile and point', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id, 'nis' => '2025999', 'current_point' => 80]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Profil Siswa')
        ->assertSee('2025999')
        ->assertSee('80');
});

test('a parent sees only their linked children', function () {
    $user = userWithRole(UserRole::OrangTua);
    $parent = ParentGuardian::factory()->create(['user_id' => $user->id]);

    $child = Student::factory()->create(['name' => 'Anak Saya']);
    $stranger = Student::factory()->create(['name' => 'Anak Orang Lain']);
    $parent->students()->attach($child->id, ['relationship' => 'Ayah']);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Anak / Siswa Terkait')
        ->assertSee('Anak Saya')
        ->assertDontSee('Anak Orang Lain');
});
