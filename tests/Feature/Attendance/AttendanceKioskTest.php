<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a kiosk account lands on the kiosk after login instead of the dashboard', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk))
        ->get(route('dashboard'))
        ->assertRedirect(route('attendance.absensi.kiosk'));
});

test('a kiosk account opens the kiosk but nothing else', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk));

    $this->get(route('attendance.absensi.kiosk'))
        ->assertOk()
        ->assertSee('SmartsisAttendance.start', false)
        ->assertDontSee('Keluar Kiosk');

    $this->get(route('attendance.absensi'))->assertForbidden();
    $this->get(route('master-data.students.index'))->assertForbidden();
    $this->get(route('attendance.absensi.settings'))->assertForbidden();
});

test('the kiosk never asks a student to pick a class first', function () {
    Classroom::factory()->create(['name' => 'XI IPA 1']);

    $this->actingAs(userWithRole(UserRole::Kiosk))
        ->get(route('attendance.absensi.kiosk'))
        ->assertOk()
        ->assertDontSee('Kelas yang discan')
        ->assertSee('data-face-identity', false);
});

test('a kiosk account opening the staffed scan page is sent to the kiosk', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk))
        ->get(route('attendance.absensi.scan'))
        ->assertRedirect(route('attendance.absensi.kiosk'));
});

test('attendance managers can open the kiosk and roles without scan access cannot', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('attendance.absensi.kiosk'))
        ->assertOk()
        ->assertSee('Keluar Kiosk');

    $this->actingAs(userWithRole(UserRole::KepalaSekolah))
        ->get(route('attendance.absensi.kiosk'))
        ->assertForbidden();
});

test('the kiosk announces the recorded scan with the student name and class', function () {
    $classroom = Classroom::factory()->create(['name' => 'XI IPA 1']);
    $student = Student::factory()->onboarded()->create([
        'name' => 'Budi Santoso',
        'classroom_id' => $classroom->id,
    ]);

    $this->actingAs(userWithRole(UserRole::Kiosk));

    Livewire::test('pages::attendance.absensi.kiosk')
        ->call('setMode', 'masuk')
        ->call('record', $student->id)
        ->assertDispatched(
            'attendance-recorded',
            ok: true,
            text: 'Selamat, absensi Anda tercatat dengan nama Budi Santoso - XI IPA 1. Silakan masuk.',
        );
});

test('a rejected scan is announced too, so the student is not left guessing', function () {
    $student = Student::factory()->onboarded()->create();

    $this->actingAs(userWithRole(UserRole::Kiosk));

    Livewire::test('pages::attendance.absensi.kiosk')
        ->call('setMode', 'masuk')
        ->call('record', $student->id)
        ->call('record', $student->id)
        ->assertDispatched('attendance-recorded', ok: false);
});

test('the staffed scan page stays quiet: its operator reads the result card', function () {
    $student = Student::factory()->onboarded()->create();

    $this->actingAs(adminUser());

    Livewire::test('pages::attendance.absensi.scan')
        ->call('record', $student->id)
        ->assertNotDispatched('attendance-recorded');
});

test('a kiosk account records attendance for a matched student', function () {
    $student = Student::factory()->onboarded()->create();

    $this->actingAs(userWithRole(UserRole::Kiosk));

    $component = Livewire::test('pages::attendance.absensi.kiosk')
        ->call('setMode', 'masuk')
        ->call('record', $student->id);

    expect($component->get('lastResult')['ok'])->toBeTrue()
        ->and($student->attendances()->count())->toBe(1);
});

test('the kiosk points the camera at the selected class templates', function () {
    $classroom = Classroom::factory()->create();

    $this->actingAs(userWithRole(UserRole::Kiosk));

    $component = Livewire::withQueryParams(['kelas' => $classroom->id])
        ->test('pages::attendance.absensi.kiosk')
        ->assertSet('classroomId', $classroom->id)
        ->assertSee($classroom->name);

    expect($component->instance()->templatesUrl())
        ->toBe(route('attendance.absensi.face-templates', ['classroom' => $classroom->id]));

    $component->set('classroomId', null);

    expect($component->instance()->templatesUrl())
        ->toBe(route('attendance.absensi.face-templates'));
});

test('a bookmarked class that no longer exists falls back to every class', function () {
    $this->actingAs(userWithRole(UserRole::Kiosk));

    Livewire::withQueryParams(['kelas' => 999999])
        ->test('pages::attendance.absensi.kiosk')
        ->assertSet('classroomId', null);
});

test('a class-scoped scan refuses a student from another class', function () {
    [$scanned, $other] = Classroom::factory()->count(2)->create();
    $outsider = Student::factory()->onboarded()->create(['classroom_id' => $other->id]);
    $member = Student::factory()->onboarded()->create(['classroom_id' => $scanned->id]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    $component = Livewire::test('pages::attendance.absensi.scan')
        ->set('classroomId', $scanned->id)
        ->call('setMode', 'masuk')
        ->call('record', $outsider->id);

    expect($component->get('lastResult')['ok'])->toBeFalse()
        ->and($outsider->attendances()->count())->toBe(0);

    $component->call('record', $member->id);

    expect($component->get('lastResult')['ok'])->toBeTrue()
        ->and($member->attendances()->count())->toBe(1);
});
