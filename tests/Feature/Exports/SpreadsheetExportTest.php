<?php

use App\Enums\AttendanceStatus;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Exports\AttendanceDailyExports;
use App\Exports\ParentsExport;
use App\Exports\PermitExport;
use App\Exports\PointMonitoringExport;
use App\Exports\StudentsExport;
use App\Exports\TeachersExport;
use App\Exports\ViolationExport;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\ParentGuardian;
use App\Models\Permit;
use App\Models\PointRule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

it('maps a student row onto the import template columns', function () {
    $classroom = Classroom::factory()->create(['name' => 'XII RPL 1']);
    $major = Major::factory()->create(['name' => 'Rekayasa Perangkat Lunak']);
    $student = Student::factory()->create([
        'name' => 'Budi Santoso',
        'nis' => '1234567890',
        'nisn' => '0987654321',
        'gender' => 'L',
        'birth_date' => '2008-04-17',
        'address' => 'Jl. Merdeka 10',
        'classroom_id' => $classroom->id,
        'major_id' => $major->id,
    ]);
    $parent = ParentGuardian::factory()->create(['name' => 'Slamet', 'phone' => '08123456789']);
    $student->parents()->attach($parent->id, ['relationship' => 'Ayah']);

    $export = new StudentsExport;
    $row = $export->collection()->firstWhere('nis', '1234567890');

    expect($export->headings())->toHaveCount(11)
        ->and($export->map($row))->toBe([
            'Budi Santoso',
            '1234567890',
            '0987654321',
            'L',
            '2008-04-17',
            'Jl. Merdeka 10',
            'XII RPL 1',
            'Rekayasa Perangkat Lunak',
            'Slamet',
            'Ayah',
            '08123456789',
        ]);
});

it('leaves the parent columns empty for a student without a guardian', function () {
    $student = Student::factory()->create(['nis' => '5555555555']);

    $export = new StudentsExport;
    $row = $export->collection()->firstWhere('nis', $student->nis);

    expect(array_slice($export->map($row), 8))->toBe([null, null, null]);
});

it('maps a teacher row with its account email and primary role', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['email' => 'guru@smartsis.test']);
    $user->assignRole(UserRole::GuruBk->value);
    Teacher::factory()->create([
        'user_id' => $user->id,
        'name' => 'Ibu Rina',
        'nip' => '198501012010012001',
        'phone' => '08987654321',
    ]);

    $export = new TeachersExport;
    $row = $export->collection()->firstWhere('nip', '198501012010012001');

    expect($export->map($row))->toBe([
        'Ibu Rina',
        '198501012010012001',
        'guru@smartsis.test',
        '08987654321',
        UserRole::GuruBk->value,
    ]);
});

it('reports the account status on a parent row', function () {
    $active = ParentGuardian::factory()->create([
        'name' => 'Aktif Wali',
        'phone' => '0811',
        'user_id' => User::factory()->create(['email' => 'wali@smartsis.test'])->id,
    ]);
    $orphan = ParentGuardian::factory()->create(['name' => 'Zeta Wali', 'user_id' => null]);

    $export = new ParentsExport;
    $rows = $export->collection();

    expect($export->map($rows->firstWhere('id', $active->id)))
        ->toBe(['Aktif Wali', 'wali@smartsis.test', '0811', 'Aktif'])
        ->and($export->map($rows->firstWhere('id', $orphan->id))[3])->toBe('Nonaktif');
});

it('maps a permit row including its decision trail', function () {
    $classroom = Classroom::factory()->create(['name' => 'XI TKJ 2']);
    $student = Student::factory()->create(['name' => 'Sari', 'nis' => '2222222222', 'classroom_id' => $classroom->id]);
    $decider = User::factory()->create(['name' => 'Guru Piket']);
    $permit = Permit::factory()->create([
        'student_id' => $student->id,
        'type' => PermitType::Terlambat,
        'date' => '2026-03-04',
        'reason' => 'Demam',
        'status' => PermitStatus::Approved,
        'decided_by' => $decider->id,
        'decided_at' => Carbon::parse('2026-03-04 08:30:00'),
        'decision_note' => 'Disetujui',
    ]);

    $export = new PermitExport(Permit::query()->whereKey($permit->id));

    expect($export->collection())->toHaveCount(1)
        ->and($export->map($export->collection()->first()))->toBe([
            'Sari',
            '2222222222',
            'XI TKJ 2',
            PermitType::Terlambat->label(),
            '04-03-2026',
            'Demam',
            PermitStatus::Approved->label(),
            'Guru Piket',
            '04-03-2026 08:30',
            'Disetujui',
        ]);
});

it('maps a violation row with a negative point column', function () {
    $classroom = Classroom::factory()->create(['name' => 'X MM 1']);
    $student = Student::factory()->create(['name' => 'Andi', 'nis' => '3333333333', 'classroom_id' => $classroom->id]);
    $rule = PointRule::factory()->deduction()->create(['name' => 'Terlambat', 'point' => 5]);
    $violation = Violation::factory()->create([
        'student_id' => $student->id,
        'point_rule_id' => $rule->id,
        'status' => PointApprovalStatus::Approved,
        'occurred_on' => '2026-02-11',
        'note' => 'Masuk jam ke-2',
    ]);

    $export = new ViolationExport(Violation::query()->whereKey($violation->id));

    expect($export->map($export->collection()->first()))->toBe([
        'Andi',
        '3333333333',
        'X MM 1',
        'Terlambat',
        '-5',
        PointApprovalStatus::Approved->label(),
        '11-02-2026',
        'Masuk jam ke-2',
    ]);
});

it('labels each point band on the monitoring export', function () {
    Student::factory()->create(['name' => 'Aman Siswa', 'current_point' => 90]);
    Student::factory()->create(['name' => 'Peringatan Siswa', 'current_point' => 70]);
    Student::factory()->create(['name' => 'Minimum Siswa', 'current_point' => 40]);

    $export = new PointMonitoringExport(Student::query());
    $labels = $export->collection()->mapWithKeys(
        fn (Student $student): array => [$student->name => $export->map($student)[4]],
    );

    expect($labels)->toMatchArray([
        'Aman Siswa' => 'Aman',
        'Peringatan Siswa' => 'Peringatan',
        'Minimum Siswa' => 'Di Bawah Minimum',
    ]);
});

it('narrows the monitoring export by search, classroom and status', function () {
    $classroom = Classroom::factory()->create();
    $other = Classroom::factory()->create();
    Student::factory()->create(['name' => 'Dewi', 'nis' => '4444444444', 'classroom_id' => $classroom->id, 'current_point' => 30]);
    Student::factory()->create(['name' => 'Dewa', 'nis' => '4444444445', 'classroom_id' => $other->id, 'current_point' => 30]);
    Student::factory()->create(['name' => 'Dewi Lain', 'nis' => '4444444446', 'classroom_id' => $classroom->id, 'current_point' => 95]);

    $filtered = (new PointMonitoringExport(
        baseQuery: Student::query(),
        search: 'Dewi',
        classroomId: (string) $classroom->id,
        status: 'below_minimum',
    ))->collection();

    expect($filtered->pluck('name')->all())->toBe(['Dewi']);
});

it('falls back to Belum Absen when a student has no attendance for the day', function () {
    $date = Carbon::parse('2026-05-20');
    $classroom = Classroom::factory()->create(['name' => 'XII AK 1']);
    $present = Student::factory()->create(['name' => 'Hadir Siswa', 'nis' => '6666666661', 'classroom_id' => $classroom->id]);
    Student::factory()->create(['name' => 'Tanpa Absen', 'nis' => '6666666662', 'classroom_id' => $classroom->id]);
    Attendance::factory()->create([
        'student_id' => $present->id,
        'date' => $date->toDateString(),
        'status' => AttendanceStatus::Terlambat,
        'checked_in_at' => $date->copy()->setTime(7, 30),
        'checked_out_at' => $date->copy()->setTime(15, 0),
        'note' => 'Ban bocor',
    ]);

    $export = new AttendanceDailyExports($date, $classroom->id);
    $rows = $export->collection();

    expect($export->map($rows->firstWhere('nis', '6666666661')))
        ->toBe(['Hadir Siswa', '6666666661', 'XII AK 1', '07:30', '15:00', AttendanceStatus::Terlambat->label(), 'Ban bocor'])
        ->and($export->map($rows->firstWhere('nis', '6666666662')))
        ->toBe(['Tanpa Absen', '6666666662', 'XII AK 1', null, null, 'Belum Absen', null]);
});

it('filters the daily attendance export to students with no record', function () {
    $date = Carbon::parse('2026-05-21');
    $present = Student::factory()->create(['name' => 'Sudah Absen', 'nis' => '7777777771']);
    Student::factory()->create(['name' => 'Belum Absen', 'nis' => '7777777772']);
    Attendance::factory()->create(['student_id' => $present->id, 'date' => $date->toDateString()]);

    $rows = (new AttendanceDailyExports($date, null, 'none'))->collection();

    expect($rows->pluck('nis')->all())->toBe(['7777777772']);
});
