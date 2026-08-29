<?php

use App\Enums\PermitStatus;
use App\Models\Attendance;
use App\Models\Permit;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DailyAttendanceReminder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A student with a login account, which is the only kind the sweep can reach.
 */
function studentWithAccount(bool $active = true): Student
{
    $user = User::factory()->create(['is_active' => $active]);

    return Student::factory()->create(['user_id' => $user->id]);
}

test('the sweep reminds a student who has not checked in yet', function () {
    Notification::fake();

    $student = studentWithAccount();

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertSentTo($student->user, DailyAttendanceReminder::class);
});

test('a student who already checked in today is left alone', function () {
    Notification::fake();

    $student = studentWithAccount();
    Attendance::factory()->create(['student_id' => $student->id, 'date' => today()]);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('yesterdays attendance does not count as todays', function () {
    Notification::fake();

    $student = studentWithAccount();
    Attendance::factory()->create(['student_id' => $student->id, 'date' => today()->subDay()]);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertSentTo($student->user, DailyAttendanceReminder::class);
});

test('a student with an approved permit for today is left alone', function () {
    Notification::fake();

    $student = studentWithAccount();
    Permit::factory()->create([
        'student_id' => $student->id,
        'date' => today(),
        'status' => PermitStatus::Approved,
    ]);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a permit still waiting for approval does not excuse the reminder', function () {
    Notification::fake();

    $student = studentWithAccount();
    Permit::factory()->create([
        'student_id' => $student->id,
        'date' => today(),
        'status' => PermitStatus::Pending,
    ]);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertSentTo($student->user, DailyAttendanceReminder::class);
});

test('a disabled account is not reminded', function () {
    Notification::fake();

    studentWithAccount(active: false);

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a student without a login account is skipped rather than crashing the sweep', function () {
    Notification::fake();

    Student::factory()->create(['user_id' => null]);
    $reachable = studentWithAccount();

    $this->artisan('attendance:remind')->assertSuccessful();

    Notification::assertSentTo($reachable->user, DailyAttendanceReminder::class);
    Notification::assertCount(1);
});

test('the sweep can be run for a past date', function () {
    Notification::fake();

    $student = studentWithAccount();
    // Present yesterday, absent today: only the back-dated run stays quiet.
    Attendance::factory()->create(['student_id' => $student->id, 'date' => today()->subDay()]);

    $this->artisan('attendance:remind', ['--date' => today()->subDay()->toDateString()])->assertSuccessful();

    Notification::assertNothingSent();
});

test('the reminder lands in the database with the payload the bell renders', function () {
    $student = studentWithAccount();

    $this->artisan('attendance:remind')->assertSuccessful();

    $notification = $student->user->notifications()->sole();

    expect($notification->data)
        ->toHaveKeys(['title', 'body', 'icon', 'url'])
        ->and($notification->data['title'])->toBe('Jangan lupa absen hari ini')
        ->and($notification->data['url'])->toBe(route('attendance.absensi.scan'))
        ->and($notification->read_at)->toBeNull();
});

test('the bell shows the unread count and the newest notifications', function () {
    $student = studentWithAccount();
    $this->artisan('attendance:remind');

    Livewire::actingAs($student->user)
        ->test('pages::notifications.bell')
        ->assertSet('unreadCount', 1)
        ->assertSee('Jangan lupa absen hari ini');
});

test('opening a notification marks it read and follows its link', function () {
    $student = studentWithAccount();
    $this->artisan('attendance:remind');
    $notification = $student->user->notifications()->sole();

    Livewire::actingAs($student->user)
        ->test('pages::notifications.bell')
        ->call('openNotification', $notification->id)
        ->assertRedirect(route('attendance.absensi.scan'));

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('a notification belonging to someone else cannot be opened', function () {
    $student = studentWithAccount();
    $other = studentWithAccount();
    $this->artisan('attendance:remind');

    $theirNotification = $other->user->notifications()->sole();

    Livewire::actingAs($student->user)
        ->test('pages::notifications.bell')
        ->call('openNotification', $theirNotification->id)
        ->assertNoRedirect();

    expect($theirNotification->refresh()->read_at)->toBeNull();
});

test('marking all as read empties the bubble', function () {
    $student = studentWithAccount();
    $this->artisan('attendance:remind');
    $this->artisan('attendance:remind', ['--date' => today()->subDay()->toDateString()]);

    Livewire::actingAs($student->user)
        ->test('pages::notifications.bell')
        ->assertSet('unreadCount', 2)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);
});

test('the bell renders in the topbar for a signed-in user', function () {
    $this->actingAs(adminUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('notifications-outline', false);
});
