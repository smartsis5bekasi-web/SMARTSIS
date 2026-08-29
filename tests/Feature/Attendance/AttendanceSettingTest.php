<?php

use App\Enums\AttendanceStatus;
use App\Enums\PointSource;
use App\Enums\PointType;
use App\Enums\UserRole;
use App\Models\AttendanceSetting;
use App\Models\PointRule;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a manager can update the attendance settings', function () {
    $rule = PointRule::factory()->create([
        'type' => PointType::Deduction,
        'source' => PointSource::Attendance,
        'point' => 2,
    ]);

    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.settings')
        ->set('late_after', '07:30')
        ->set('check_out_after', '15:30')
        ->set('late_rule_id', $rule->id)
        ->set('alpha_rule_id', null)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('attendance.absensi'));

    $setting = AttendanceSetting::current()->fresh();

    expect($setting->late_after)->toBe('07:30:00')
        ->and($setting->check_out_after)->toBe('15:30:00')
        ->and($setting->late_rule_id)->toBe($rule->id)
        ->and($setting->alpha_rule_id)->toBeNull();
});

test('the check-out time must come after the late threshold', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.settings')
        ->set('late_after', '15:00')
        ->set('check_out_after', '07:00')
        ->call('save')
        ->assertHasErrors('check_out_after');
});

test('the check-in window can be set from the admin page', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.settings')
        ->assertSet('check_in_start', '07:00')
        ->set('check_in_start', '05:30')
        ->set('late_after', '07:15')
        ->set('check_out_after', '15:30')
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendanceSetting::current()->fresh()->check_in_start)->toBe('05:30:00');
});

test('the three times have to run in order', function (string $field, string $value) {
    $this->actingAs(userWithRole(UserRole::GuruPiket));

    Livewire::test('pages::attendance.absensi.settings')
        ->set('check_in_start', '06:00')
        ->set('late_after', '07:00')
        ->set('check_out_after', '15:00')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field => 'after']);
})->with([
    'check-in cannot open after the late threshold' => ['late_after', '05:00'],
    'check-out cannot open before the late threshold' => ['check_out_after', '06:30'],
]);

test('a fresh install gets the 07:00 to 15:00 school day', function () {
    $setting = AttendanceSetting::current();

    expect($setting->check_in_start)->toBe('07:00:00')
        ->and($setting->late_after)->toBe('07:30:00')
        ->and($setting->check_out_after)->toBe('15:00:00');

    // 07:10 is inside the grace period, 07:45 is not.
    expect($setting->isCheckInOpen(now()->setTime(6, 55)))->toBeFalse()
        ->and($setting->isCheckInOpen(now()->setTime(7, 0)))->toBeTrue()
        ->and($setting->checkInStatus(now()->setTime(7, 10)))->toBe(AttendanceStatus::Hadir)
        ->and($setting->checkInStatus(now()->setTime(7, 45)))->toBe(AttendanceStatus::Terlambat)
        ->and($setting->isCheckOutOpen(now()->setTime(15, 0)))->toBeTrue();
});
