<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Achievement;
use App\Models\PointRule;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the list offers an edit action for a pending achievement', function () {
    $achievement = Achievement::factory()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::academic.achievement.index')
        ->assertSee(route('academic.achievements.edit', $achievement), escape: false);
});

test('the list still offers the edit action to staff once the achievement is verified', function () {
    $achievement = Achievement::factory()->approved()->create();

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::academic.achievement.index')
        ->assertSee(route('academic.achievements.edit', $achievement), escape: false);
});

test('a student may not edit their own achievement once it is verified', function () {
    $user = userWithRole(UserRole::Siswa);
    $student = Student::factory()->onboarded()->create(['user_id' => $user->id]);
    $achievement = Achievement::factory()->approved()->create(['student_id' => $student->id]);

    $this->actingAs($user)
        ->get(route('academic.achievements.edit', $achievement))
        ->assertForbidden();
});

test('the edit form is prefilled from a verified achievement too', function () {
    $rule = PointRule::factory()->addition()->create();
    $achievement = Achievement::factory()->approved()->create([
        'point_rule_id' => $rule->id,
        'title' => 'Juara 1 LKS',
        'level' => 'Provinsi',
    ]);

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->assertSet('point_rule_id', $rule->id)
        ->assertSet('title', 'Juara 1 LKS')
        ->assertSet('level', 'Provinsi');
});

test('changing the rule on a verified achievement re-syncs the student points', function () {
    $verifier = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->onboarded()->create(['current_point' => 100]);
    $oldRule = PointRule::factory()->addition()->create(['point' => 10]);
    $newRule = PointRule::factory()->addition()->create(['point' => 25]);

    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $oldRule->id,
    ]);
    $achievement->approve($verifier);

    expect($student->fresh()->current_point)->toBe(110);

    $this->actingAs($verifier);

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->set('point_rule_id', $newRule->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(125)
        ->and($achievement->fresh()->pointLogs()->count())->toBe(3);
});

test('editing a verified achievement without touching the rule leaves the points alone', function () {
    $verifier = userWithRole(UserRole::GuruBk);
    $student = Student::factory()->onboarded()->create(['current_point' => 0]);
    $rule = PointRule::factory()->addition()->create(['point' => 15]);

    $achievement = Achievement::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
    ]);
    $achievement->approve($verifier);

    $this->actingAs($verifier);

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->set('title', 'Judul Dikoreksi')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->current_point)->toBe(15)
        ->and($achievement->fresh()->pointLogs()->count())->toBe(1);
});

test('the list hides the edit action from a view-only role', function () {
    $achievement = Achievement::factory()->create();

    $this->actingAs(userWithRole(UserRole::WakasekKesiswaan));

    Livewire::test('pages::academic.achievement.index')
        ->assertDontSee(route('academic.achievements.edit', $achievement), escape: false);
});

test('the edit form is prefilled from the existing achievement', function () {
    $rule = PointRule::factory()->addition()->create();
    $achievement = Achievement::factory()->create([
        'point_rule_id' => $rule->id,
        'title' => 'Juara 2 OSN',
        'level' => 'Nasional',
        'description' => 'Olimpiade Sains Nasional',
        'achieved_on' => now()->subWeek()->toDateString(),
    ]);

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->assertSet('point_rule_id', $rule->id)
        ->assertSet('title', 'Juara 2 OSN')
        ->assertSet('level', 'Nasional')
        ->assertSet('description', 'Olimpiade Sains Nasional')
        ->assertSet('achieved_on', $achievement->achieved_on->toDateString());
});

test('a role granted only the edit permission may update a pending achievement', function () {
    $user = userWithRole(UserRole::WakasekKesiswaan);
    $user->givePermissionTo(Permission::EditAchievement->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $achievement = Achievement::factory()->create(['title' => 'Judul Lama']);
    $rule = PointRule::factory()->addition()->create();

    $this->actingAs($user)->get(route('academic.achievements.edit', $achievement))->assertOk();

    Livewire::test('pages::academic.achievement.edit', ['achievement' => $achievement])
        ->set('point_rule_id', $rule->id)
        ->set('title', 'Judul Baru')
        ->set('level', 'Provinsi')
        ->set('achieved_on', now()->subDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('academic.achievements'));

    expect($achievement->fresh()->title)->toBe('Judul Baru');
});

test('the edit permission does not grant verification', function () {
    $user = userWithRole(UserRole::WakasekKesiswaan);
    $user->givePermissionTo(Permission::EditAchievement->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $achievement = Achievement::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::academic.achievement.show', ['achievement' => $achievement])
        ->call('approve')
        ->assertForbidden();
});

test('a student may only edit their own pending achievement', function () {
    $user = userWithRole(UserRole::Siswa);
    Student::factory()->onboarded()->create(['user_id' => $user->id]);
    $other = Achievement::factory()->create();

    $this->actingAs($user)
        ->get(route('academic.achievements.edit', $other))
        ->assertForbidden();
});

test('the role editor exposes the achievement edit permission', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::WaliKelas->value])
        ->assertSee(Permission::EditAchievement->value)
        ->set('selected', [Permission::ViewAchievement->value, Permission::EditAchievement->value])
        ->call('save')
        ->assertHasNoErrors();

    expect(
        Role::findByName(UserRole::WaliKelas->value)
            ->permissions
            ->pluck('name')
            ->all()
    )->toContain(Permission::EditAchievement->value);
});
