<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a super admin can open the admin settings page', function () {
    $this->actingAs(adminUser())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admins.edit'))
        ->assertSuccessful()
        ->assertSee('Tambah Admin');
});

test('the admin settings page asks for the password first', function () {
    $this->actingAs(adminUser())
        ->get(route('admins.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('only a super admin can open the admin settings page', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admins.edit'))
        ->assertForbidden();
})->with([
    'kepala sekolah' => UserRole::KepalaSekolah,
    'guru piket' => UserRole::GuruPiket,
]);

test('the admin link is only shown to a super admin', function () {
    $this->actingAs(adminUser())->get(route('profile.edit'))->assertSee(route('admins.edit'));
    $this->actingAs(userWithRole(UserRole::GuruPiket))->get(route('profile.edit'))->assertDontSee(route('admins.edit'));
});

test('a super admin can add another admin by email', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::settings.admins')
        ->set('name', 'Operator Sekolah')
        ->set('email', 'operator@sman5bekasi.sch.id')
        ->set('password', 'rahasia-sekolah')
        ->set('password_confirmation', 'rahasia-sekolah')
        ->call('addAdmin')
        ->assertHasNoErrors()
        ->assertSet('email', '')
        ->assertSee('operator@sman5bekasi.sch.id');

    $admin = User::query()->where('email', 'operator@sman5bekasi.sch.id')->sole();

    expect($admin->hasRole(UserRole::SuperAdmin->value))->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('rahasia-sekolah', $admin->password))->toBeTrue();
});

test('the new admin form is validated', function (array $input, string $error) {
    User::factory()->create(['email' => 'taken@sman5bekasi.sch.id']);
    $this->actingAs(adminUser());

    $component = Livewire::test('pages::settings.admins')
        ->set('name', 'Operator')
        ->set('email', 'operator@sman5bekasi.sch.id')
        ->set('password', 'rahasia-sekolah')
        ->set('password_confirmation', 'rahasia-sekolah');

    foreach ($input as $property => $value) {
        $component->set($property, $value);
    }

    $component->call('addAdmin')->assertHasErrors($error);
})->with([
    'email already used' => [['email' => 'taken@sman5bekasi.sch.id'], 'email'],
    'not an email' => [['email' => 'bukan-email'], 'email'],
    'short password' => [['password' => 'pendek', 'password_confirmation' => 'pendek'], 'password'],
    'password mismatch' => [['password_confirmation' => 'lain-sekali'], 'password'],
    'missing name' => [['name' => ''], 'name'],
]);

test('another admin can have their access removed', function () {
    $this->actingAs(adminUser());
    $other = adminUser();

    Livewire::test('pages::settings.admins')->call('removeAdmin', $other->id);

    expect(User::find($other->id))->toBeNull();
});

test('removing admin access keeps an account that still has another role', function () {
    $this->actingAs(adminUser());
    $teacher = userWithRole(UserRole::GuruPiket);
    $teacher->assignRole(UserRole::SuperAdmin->value);

    Livewire::test('pages::settings.admins')->call('removeAdmin', $teacher->id);

    expect($teacher->fresh()->hasRole(UserRole::SuperAdmin->value))->toBeFalse()
        ->and($teacher->fresh()->hasRole(UserRole::GuruPiket->value))->toBeTrue();
});

test('an admin cannot remove their own access', function () {
    $me = adminUser();
    adminUser();
    $this->actingAs($me);

    Livewire::test('pages::settings.admins')->call('removeAdmin', $me->id);

    expect($me->fresh()->hasRole(UserRole::SuperAdmin->value))->toBeTrue();
});

test('other admins can be removed while an active admin remains', function () {
    $this->actingAs(adminUser());
    $inactive = adminUser();
    $inactive->update(['is_active' => false]);
    $active = adminUser();

    $component = Livewire::test('pages::settings.admins');

    // Removing the other active admin leaves the signed-in one: allowed.
    $component->call('removeAdmin', $active->id);
    expect(User::find($active->id))->toBeNull();

    // An inactive admin can always go — the signed-in one is still active.
    $component->call('removeAdmin', $inactive->id);
    expect(User::find($inactive->id))->toBeNull();
});

test('the last active admin is protected when the signed-in account is not counted', function () {
    // A super admin who was deactivated mid-session must not be able to
    // strip the only remaining active admin.
    $me = adminUser();
    $me->update(['is_active' => false]);
    $onlyActive = adminUser();
    $this->actingAs($me);

    Livewire::test('pages::settings.admins')->call('removeAdmin', $onlyActive->id);

    expect($onlyActive->fresh()->hasRole(UserRole::SuperAdmin->value))->toBeTrue();
});

test('staff demo accounts still on the default password are flagged', function () {
    $this->seed(DemoAccountSeeder::class);
    $this->actingAs(adminUser());

    User::query()->where('email', UserRole::GuruBk->value.'@smartsis.test')->sole()->update(['password' => 'sudah-diganti']);

    Livewire::test('pages::settings.admins')
        ->assertSee('masih memakai password bawaan')
        ->assertSee(UserRole::SuperAdmin->value.'@smartsis.test')
        ->assertDontSee(UserRole::GuruBk->value.'@smartsis.test')
        ->assertSee('Akun demo');
});

test('no warning when no demo account uses the default password', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::settings.admins')->assertDontSee('masih memakai password bawaan');
});
