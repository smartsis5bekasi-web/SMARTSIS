<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can list kiosk accounts', function () {
    $kiosk = userWithRole(UserRole::Kiosk);
    $teacher = userWithRole(UserRole::GuruPiket);

    $this->actingAs(adminUser())
        ->get(route('master-data.kiosk-accounts'))
        ->assertOk()
        ->assertSee($kiosk->email)
        ->assertDontSee($teacher->email);
});

test('roles without master data access cannot manage kiosk accounts', function () {
    $this->actingAs(userWithRole(UserRole::GuruPiket))
        ->get(route('master-data.kiosk-accounts'))
        ->assertForbidden();
});

test('an admin creates a kiosk account that holds only the kiosk role', function () {
    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.kiosk-accounts.create')
        ->set('name', 'Tablet XI IPA 1')
        ->set('email', 'kiosk.xiipa1@smartsis.test')
        ->set('password', 'rahasia123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.kiosk-accounts'));

    $account = User::where('email', 'kiosk.xiipa1@smartsis.test')->firstOrFail();

    expect($account->getRoleNames()->all())->toBe([UserRole::Kiosk->value])
        ->and($account->is_active)->toBeTrue()
        ->and(Hash::check('rahasia123', $account->password))->toBeTrue();
});

test('creating a kiosk account validates its input', function () {
    $existing = userWithRole(UserRole::Kiosk);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.kiosk-accounts.create')
        ->set('name', '')
        ->set('email', $existing->email)
        ->set('password', 'short')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'password' => 'min']);
});

test('editing a kiosk account keeps the password when left blank', function () {
    $account = userWithRole(UserRole::Kiosk);
    $originalHash = $account->password;

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.kiosk-accounts.edit', ['user' => $account])
        ->set('name', 'Tablet XII IPS 2')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('master-data.kiosk-accounts'));

    expect($account->fresh()->name)->toBe('Tablet XII IPS 2')
        ->and($account->fresh()->password)->toBe($originalHash);

    Livewire::test('pages::master-data.kiosk-accounts.edit', ['user' => $account])
        ->set('password', 'passwordbaru')
        ->call('save');

    expect(Hash::check('passwordbaru', $account->fresh()->password))->toBeTrue();
});

test('the kiosk editor refuses accounts that are not kiosks', function () {
    $teacher = userWithRole(UserRole::GuruBk);

    $this->actingAs(adminUser())
        ->get(route('master-data.kiosk-accounts.edit', $teacher))
        ->assertNotFound();
});

test('an admin deletes a kiosk account but never another kind of account', function () {
    $kiosk = userWithRole(UserRole::Kiosk);
    $teacher = userWithRole(UserRole::GuruBk);

    $this->actingAs(adminUser());

    Livewire::test('pages::master-data.kiosk-accounts')
        ->call('delete', $kiosk->id);

    expect(User::find($kiosk->id))->toBeNull();

    expect(fn () => Livewire::test('pages::master-data.kiosk-accounts')->call('delete', $teacher->id))
        ->toThrow(ModelNotFoundException::class);

    expect(User::find($teacher->id))->not->toBeNull();
});
