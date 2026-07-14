<?php

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
