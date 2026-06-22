<?php

use App\Models\Classroom;
use App\Models\Major;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());
});

test('a major can be created', function () {
    Livewire::test('pages::master-data.majors')
        ->set('name', 'Ilmu Pengetahuan Alam')
        ->set('code', 'IPA')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $this->assertDatabaseHas('majors', ['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA']);
});

test('the major name must be unique', function () {
    Major::factory()->create(['name' => 'IPA']);

    Livewire::test('pages::master-data.majors')
        ->set('name', 'IPA')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('a major in use cannot be deleted', function () {
    $major = Major::factory()->create();
    Classroom::factory()->create(['major_id' => $major->id]);

    Livewire::test('pages::master-data.majors')
        ->call('delete', $major->id);

    $this->assertDatabaseHas('majors', ['id' => $major->id]);
});

test('an unused major can be deleted', function () {
    $major = Major::factory()->create();

    Livewire::test('pages::master-data.majors')
        ->call('delete', $major->id);

    $this->assertDatabaseMissing('majors', ['id' => $major->id]);
});
