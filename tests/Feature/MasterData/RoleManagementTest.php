<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests are redirected from the role manager', function () {
    $this->get(route('master-data.roles.index'))->assertRedirect(route('login'));
});

test('a master data admin without the role permission is forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::GuruBk->value);
    $user->givePermissionTo(PermissionEnum::ManageMasterData->value);

    $this->actingAs($user)->get(route('master-data.roles.index'))->assertForbidden();
    $this->actingAs($user)->get(route('master-data.roles.edit', UserRole::GuruPiket->value))->assertForbidden();
});

test('the super admin can open the role manager', function () {
    $this->actingAs(adminUser())->get(route('master-data.roles.index'))->assertOk();
    $this->actingAs(adminUser())->get(route('master-data.roles.edit', UserRole::GuruPiket->value))->assertOk();
});

test('the module toggle renders as a valid livewire expression', function () {
    $this->actingAs(adminUser())
        ->get(route('master-data.roles.edit', UserRole::GuruPiket->value))
        ->assertOk()
        // Group names contain spaces, so the attribute must stay double-quoted
        // around the single-quoted value @js emits.
        ->assertSee(<<<'HTML'
            wire:click="toggleGroup('Master Data')"
            HTML, escape: false);
});

test('an unknown role returns not found', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => 'tukang-kebun'])
        ->assertNotFound();
});

test('the super admin role cannot be edited', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::SuperAdmin->value])
        ->assertRedirect(route('master-data.roles.index'));
});

test('the editor is prefilled with the permissions the role currently holds', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruPiket->value])
        ->assertSet('selected', fn (array $selected): bool => in_array(PermissionEnum::ManageAttendance->value, $selected, true)
            && ! in_array(PermissionEnum::ManageMasterData->value, $selected, true));
});

test('granting a permission immediately opens the page it guards', function () {
    $guruPiket = userWithRole(UserRole::GuruPiket);

    // Guru Piket ships without master data access.
    $this->actingAs($guruPiket)->get(route('master-data.students.index'))->assertForbidden();

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruPiket->value])
        ->set('selected', [
            PermissionEnum::ViewDashboard->value,
            PermissionEnum::ManageMasterData->value,
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.roles.index'));

    expect(Role::findByName(UserRole::GuruPiket->value)->hasPermissionTo(PermissionEnum::ManageMasterData->value))->toBeTrue();

    $this->actingAs($guruPiket->fresh())->get(route('master-data.students.index'))->assertOk();
});

test('revoking a permission closes the page it guards', function () {
    $guruBk = userWithRole(UserRole::GuruBk);

    $this->actingAs($guruBk)->get(route('academic.violations'))->assertOk();

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruBk->value])
        ->set('selected', [PermissionEnum::ViewDashboard->value])
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($guruBk->fresh())->get(route('academic.violations'))->assertForbidden();
});

test('unknown permission names are rejected', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruMapel->value])
        ->set('selected', ['bukan.permission'])
        ->call('save')
        ->assertHasErrors(['selected.0']);

    expect(Role::findByName(UserRole::GuruMapel->value)->hasPermissionTo(PermissionEnum::ViewDashboard->value))->toBeTrue();
});

test('toggling a module ticks then unticks every permission in it', function () {
    $this->actingAs(adminUser());

    $component = Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruMapel->value])
        ->set('selected', [])
        ->call('toggleGroup', 'Perizinan');

    expect($component->get('selected'))->toEqualCanonicalizing([
        PermissionEnum::ViewPermit->value,
        PermissionEnum::RequestPermit->value,
        PermissionEnum::ManagePermit->value,
    ]);

    $component->call('toggleGroup', 'Perizinan');

    expect($component->get('selected'))->toBe([]);
});

test('the defaults button restores the shipped matrix without saving', function () {
    $role = Role::findByName(UserRole::GuruMapel->value);
    $role->syncPermissions([PermissionEnum::ManageMasterData->value]);

    $this->actingAs(adminUser());

    $component = Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::GuruMapel->value])
        ->call('applyDefaults');

    expect($component->get('selected'))->toEqualCanonicalizing(UserRole::GuruMapel->defaultPermissions());

    // Nothing is persisted until the form is submitted.
    expect($role->fresh()->permissions->pluck('name')->all())->toBe([PermissionEnum::ManageMasterData->value]);

    $component->call('save');

    expect($role->fresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(UserRole::GuruMapel->defaultPermissions());
});

test('an admin cannot revoke role management from their own role', function () {
    $wakasek = userWithRole(UserRole::WakasekKesiswaan);

    Role::findByName(UserRole::WakasekKesiswaan->value)
        ->givePermissionTo(PermissionEnum::ManageRole->value);

    $this->actingAs($wakasek);

    Livewire::test('pages::master-data.roles.edit', ['role' => UserRole::WakasekKesiswaan->value])
        ->set('selected', [PermissionEnum::ViewDashboard->value])
        ->call('save')
        ->assertHasErrors('selected');

    expect(Role::findByName(UserRole::WakasekKesiswaan->value)->hasPermissionTo(PermissionEnum::ManageRole->value))->toBeTrue();
});

test('the role manager lists every role with its permission count', function () {
    $this->actingAs(adminUser())
        ->get(route('master-data.roles.index'))
        ->assertOk()
        ->assertSee(UserRole::GuruPiket->label())
        ->assertSee(UserRole::OrangTua->label())
        ->assertSee('Terkunci');
});
