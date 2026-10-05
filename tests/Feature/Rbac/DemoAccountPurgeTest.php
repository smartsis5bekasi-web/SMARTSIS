<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\DailyAttendanceReminder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoAccountSeeder::class);
});

test('without --force it only shows what would be deleted', function () {
    $this->artisan('demo:purge')
        ->expectsOutputToContain('siswa@smartsis.test')
        ->expectsOutputToContain('Siswa Demo')
        ->expectsOutputToContain('demo:purge --force')
        ->assertSuccessful();

    expect(User::query()->whereIn('email', DemoAccountSeeder::studentEmails())->count())
        ->toBe(count(DemoAccountSeeder::studentEmails()));
});

test('--force deletes the demo siswa and orang tua with their data and keeps the staff accounts', function () {
    $realStudent = Student::factory()->create(['user_id' => User::factory()->create()->id]);
    $demoStudent = Student::query()->where('nis', '2025001')->sole();
    $demoSiswa = $demoStudent->user;

    Attendance::factory()->for($demoStudent)->create();
    $demoSiswa->notify(new DailyAttendanceReminder);

    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

    expect(User::query()->whereIn('email', DemoAccountSeeder::studentEmails())->count())->toBe(0)
        ->and(Student::query()->whereIn('nis', ['2025001', '2025002', '2025003'])->count())->toBe(0)
        ->and(ParentGuardian::query()->count())->toBe(0)
        ->and(DB::table('parent_student')->count())->toBe(0)
        ->and(Attendance::query()->where('student_id', $demoStudent->id)->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $demoSiswa->id)->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0);

    // Every staff login survives, so the school keeps its way in.
    expect(User::query()->whereIn('email', DemoAccountSeeder::staffEmails())->count())
        ->toBe(count(DemoAccountSeeder::staffEmails()))
        ->and(User::query()->where('email', UserRole::SuperAdmin->value.'@smartsis.test')->sole()->hasRole(UserRole::SuperAdmin->value))->toBeTrue()
        ->and(Teacher::query()->count())->toBe(6)
        ->and($realStudent->fresh())->not->toBeNull()
        // Sample master data may already hold real students, so it stays.
        ->and(Classroom::query()->where('name', 'XI IPA 1')->exists())->toBeTrue();
});

test('nothing happens when the student demo accounts are already gone', function () {
    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

    $this->artisan('demo:purge', ['--force' => true])
        ->expectsOutputToContain('Tidak ada akun siswa / orang tua demo')
        ->assertSuccessful();
});

test('in production the seeder adds the staff accounts but no demo students', function () {
    User::query()->delete();
    Student::query()->delete();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->whereIn('email', DemoAccountSeeder::staffEmails())->count())
        ->toBe(count(DemoAccountSeeder::staffEmails()))
        ->and(User::query()->whereIn('email', DemoAccountSeeder::studentEmails())->count())->toBe(0)
        ->and(Student::query()->count())->toBe(0);
});

test('re-seeding never resets a staff password that was changed', function () {
    $admin = User::query()->where('email', UserRole::SuperAdmin->value.'@smartsis.test')->sole();
    $admin->update(['password' => 'a-much-better-password']);

    $this->seed(DemoAccountSeeder::class);

    expect(Hash::check('a-much-better-password', $admin->fresh()->password))->toBeTrue();
});
