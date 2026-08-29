<?php

use App\Enums\UserRole;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot read face templates', function () {
    $this->get(route('attendance.absensi.face-templates'))->assertRedirect(route('login'));
});

test('a role without attendance access cannot read face templates', function () {
    $this->actingAs(userWithRole(UserRole::GuruMapel))
        ->getJson(route('attendance.absensi.face-templates'))
        ->assertForbidden();
});

test('the staffed kiosk receives every registered template', function () {
    $registered = Student::factory()->onboarded()->count(3)->create();
    // A student who has not registered a face has nothing to match against.
    Student::factory()->create();

    $response = $this->actingAs(adminUser())
        ->getJson(route('attendance.absensi.face-templates'))
        ->assertOk();

    expect($response->json())->toHaveCount(3)
        ->and(collect($response->json())->pluck('id')->sort()->values()->all())
        ->toBe($registered->pluck('id')->sort()->values()->all())
        ->and($response->json('0'))->toHaveKeys(['id', 'name', 'descriptors']);
});

test('a siswa receives only their own template', function () {
    $siswa = userWithRole(UserRole::Siswa);
    $own = Student::factory()->onboarded()->create(['user_id' => $siswa->id]);
    Student::factory()->onboarded()->count(2)->create();

    $response = $this->actingAs($siswa)
        ->getJson(route('attendance.absensi.face-templates'))
        ->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.id'))->toBe($own->id);
});

test('an unchanged template set comes back as a 304 instead of the payload', function () {
    Student::factory()->onboarded()->count(2)->create();

    $this->actingAs(adminUser());

    $etag = $this->getJson(route('attendance.absensi.face-templates'))
        ->assertOk()
        ->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('attendance.absensi.face-templates'))
        ->assertStatus(304);
});

test('registering another face invalidates the cached template set', function () {
    Student::factory()->onboarded()->count(2)->create();

    $this->actingAs(adminUser());

    $etag = $this->getJson(route('attendance.absensi.face-templates'))->headers->get('ETag');

    Student::factory()->onboarded()->create();

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('attendance.absensi.face-templates'))
        ->assertOk()
        ->assertJsonCount(3);
});

test('the scanner pages no longer inline the descriptors', function () {
    Student::factory()->onboarded()->create();

    $html = $this->actingAs(adminUser())
        ->get(route('attendance.absensi.scan'))
        ->assertOk()
        ->getContent();

    // Descriptors used to be embedded here, and were re-sent on every scan.
    // `@js()` escapes the slashes in the URL, so match on the path segment.
    expect($html)->toContain('face-templates')
        ->and($html)->not->toContain('descriptors');
});
