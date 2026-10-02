<?php

use App\Enums\CounselingAction;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\CounselingRecord;
use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('three alpha days are enough to recommend konseling', function () {
    expect(CounselingAction::recommendedFor(3, 100))->toBe([CounselingAction::Konseling])
        ->and(CounselingAction::recommendedFor(2, 100))->toBe([])
        ->and(CounselingAction::recommendedFor(0, 69))->toBe([CounselingAction::Pembinaan])
        ->and(CounselingAction::recommendedFor(0, 49))->toBe([CounselingAction::PemanggilanOrtu])
        ->and(CounselingAction::recommendedFor(5, 40))->toBe([CounselingAction::Konseling, CounselingAction::PemanggilanOrtu]);
});

test('guru bk sees students with three alpha days as needing konseling', function () {
    $flagged = Student::factory()->create(['name' => 'Tiga Alpha']);
    $safe = Student::factory()->create(['name' => 'Dua Alpha']);

    foreach (range(1, 3) as $day) {
        Attendance::factory()->for($flagged)->alpha()->create(['date' => now()->subDays($day)->toDateString()]);
    }

    foreach (range(1, 2) as $day) {
        Attendance::factory()->for($safe)->alpha()->create(['date' => now()->subDays($day)->toDateString()]);
    }

    $this->actingAs(userWithRole(UserRole::GuruBk));

    $component = Livewire::test('pages::dashboard')
        ->assertSee('Tiga Alpha')
        ->assertDontSee('Dua Alpha')
        ->assertSee('Belum Dipanggil');

    expect(collect($component->instance()->stats)->firstWhere('label', 'Perlu Konseling'))
        ->toMatchArray(['value' => 1, 'detail' => ['Sudah dipanggil' => 0, 'Belum' => 1]]);
});

test('guru bk records a pemanggilan with notes', function () {
    $student = Student::factory()->create(['name' => 'Siswa Bermasalah', 'current_point' => 40]);
    $bk = userWithRole(UserRole::GuruBk);

    $this->actingAs($bk);

    $component = Livewire::test('pages::dashboard')
        ->call('openCounseling', $student->id)
        ->assertSet('counselingAction', CounselingAction::PemanggilanOrtu->value)
        ->set('counselingNote', 'Orang tua hadir, siswa berjanji memperbaiki kehadiran.')
        ->call('saveCounseling')
        ->assertHasNoErrors()
        ->assertSet('counselingStudentId', null)
        ->assertSee('Sudah Dipanggil')
        ->assertSee('Orang tua hadir, siswa berjanji memperbaiki kehadiran.');

    $record = CounselingRecord::sole();

    expect($record->student_id)->toBe($student->id)
        ->and($record->action)->toBe(CounselingAction::PemanggilanOrtu)
        ->and($record->called_on->isToday())->toBeTrue()
        ->and($record->recorded_by)->toBe($bk->id)
        ->and(collect($component->instance()->stats)->firstWhere('label', 'Pemanggilan Ortu')['detail'])
        ->toBe(['Sudah dipanggil' => 1, 'Belum' => 0]);
});

test('the pemanggilan history is listed in the modal', function () {
    $student = Student::factory()->create(['current_point' => 60]);
    CounselingRecord::factory()->for($student)->ofAction(CounselingAction::Pembinaan)->create([
        'note' => 'Pembinaan pertama sudah dilakukan.',
        'called_on' => now()->subWeek()->toDateString(),
    ]);

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::dashboard')
        ->call('openCounseling', $student->id)
        ->assertSee('Riwayat Pemanggilan')
        ->assertSee('Pembinaan pertama sudah dilakukan.');
});

test('a pemanggilan needs a note and a date that is not in the future', function () {
    $student = Student::factory()->create(['current_point' => 60]);

    $this->actingAs(userWithRole(UserRole::GuruBk));

    Livewire::test('pages::dashboard')
        ->call('openCounseling', $student->id)
        ->set('counselingNote', '')
        ->set('counselingDate', now()->addDay()->toDateString())
        ->call('saveCounseling')
        ->assertHasErrors(['counselingNote' => 'required', 'counselingDate' => 'before_or_equal']);

    expect(CounselingRecord::count())->toBe(0);
});

test('roles without the counseling permission cannot record a pemanggilan', function () {
    $student = Student::factory()->create(['current_point' => 40]);

    $this->actingAs(userWithRole(UserRole::WaliKelas));

    Livewire::test('pages::dashboard')
        ->call('openCounseling', $student->id)
        ->assertForbidden();

    expect(CounselingRecord::count())->toBe(0);
});
