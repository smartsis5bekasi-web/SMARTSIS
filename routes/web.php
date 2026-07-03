<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// First-login onboarding for Siswa (outside 'student.onboarded' to avoid a redirect loop).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('onboarding', 'pages::onboarding.index')->name('onboarding');
});

Route::middleware(['auth', 'verified', 'student.onboarded'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::middleware('permission:'.Permission::ManageMasterData->value)
        ->prefix('master-data')
        ->name('master-data.')
        ->group(function () {
            Route::livewire('tahun-ajaran', 'pages::master-data.academic-years')->name('academic-years');
            Route::livewire('tahun-ajaran/tambah', 'pages::master-data.academic-years.create')->name('academic-years.create');
            Route::livewire('tahun-ajaran/{year}/edit', 'pages::master-data.academic-years.edit')->name('academic-years.edit');
            Route::livewire('jurusan', 'pages::master-data.majors')->name('majors');
            Route::livewire('jurusan/tambah', 'pages::master-data.majors.create')->name('majors.create');
            Route::livewire('jurusan/{major}/edit', 'pages::master-data.majors.edit')->name('majors.edit');
            Route::livewire('kelas', 'pages::master-data.classrooms')->name('classrooms');
            Route::livewire('kelas/tambah', 'pages::master-data.classrooms.create')->name('classrooms.create');
            Route::livewire('kelas/{classroom}/edit', 'pages::master-data.classrooms.edit')->name('classrooms.edit');
            Route::livewire('guru', 'pages::master-data.teachers')->name('teachers');
            Route::livewire('guru/tambah', 'pages::master-data.teachers.create')->name('teachers.create');
            Route::livewire('guru/{teacher}/edit', 'pages::master-data.teachers.edit')->name('teachers.edit');
            Route::livewire('siswa', 'pages::master-data.students.index')->name('students.index');
            Route::livewire('siswa/tambah', 'pages::master-data.students.create')->name('students.create');
            Route::livewire('siswa/{student}/edit', 'pages::master-data.students.edit')->name('students.edit');
        });

    Route::middleware('role:'.UserRole::WaliKelas->value)
        ->prefix('wali-kelas')
        ->name('wali-kelas.')
        ->group(function () {
            Route::livewire('siswa', 'pages::wali-kelas.students.index')->name('students.index');
            Route::livewire('siswa/tambah', 'pages::wali-kelas.students.create')->name('students.create');
            Route::livewire('siswa/{student}/edit', 'pages::wali-kelas.students.edit')->name('students.edit');
        });

    Route::prefix('kehadiran')
        ->name('attendance.')
        ->group(function () {
            Route::livewire('absensi', 'pages::attendance.absensi.index')
                ->middleware('permission:'.Permission::ViewAttendance->value)
                ->name('absensi');
            Route::livewire('point', 'pages::attendance.point.index')
                ->middleware('permission:'.Permission::ViewPoint->value)
                ->name('points');
            Route::livewire('point/monitoring', 'pages::attendance.point.monitoring')
                ->middleware('permission:'.Permission::ViewPoint->value)
                ->name('points.monitoring');
            Route::livewire('point/{student}/detail', 'pages::attendance.point.show')
                ->middleware('permission:'.Permission::ViewPoint->value)
                ->name('points.show');
            Route::livewire('point/aturan/tambah', 'pages::attendance.point.create')
                ->middleware('permission:'.Permission::ManagePoint->value)
                ->name('points.create');
            Route::livewire('point/aturan/{pointRule}/edit', 'pages::attendance.point.edit')
                ->middleware('permission:'.Permission::ManagePoint->value)
                ->name('points.edit');
            Route::livewire('point/pengaturan', 'pages::attendance.point.settings')
                ->middleware('permission:'.Permission::ManagePoint->value)
                ->name('points.settings');
        });

    Route::prefix('academic')
        ->name('academic.')
        ->group(function () {
            Route::livewire('prestasi', 'pages::academic.achievement.index')
                ->middleware('permission:'.Permission::ViewAchievement->value)
                ->name('achievements');
            Route::livewire('prestasi/ajukan', 'pages::academic.achievement.create')
                ->middleware('permission:'.Permission::RequestAchievement->value.'|'.Permission::ManageAchievement->value)
                ->name('achievements.create');
            Route::livewire('prestasi/{achievement}/edit', 'pages::academic.achievement.edit')
                ->middleware('permission:'.Permission::RequestAchievement->value.'|'.Permission::ManageAchievement->value)
                ->name('achievements.edit');
            Route::livewire('prestasi/{achievement}', 'pages::academic.achievement.show')
                ->middleware('permission:'.Permission::ViewAchievement->value)
                ->name('achievements.show');
            Route::livewire('pelanggaran', 'pages::academic.violation.index')
                ->middleware('permission:'.Permission::ViewViolation->value)
                ->name('violations');
            Route::livewire('pelanggaran/catat', 'pages::academic.violation.create')
                ->middleware('permission:'.Permission::InputViolation->value.'|'.Permission::ManageViolation->value)
                ->name('violations.create');
            Route::livewire('pelanggaran/{violation}/edit', 'pages::academic.violation.edit')
                ->middleware('permission:'.Permission::InputViolation->value.'|'.Permission::ManageViolation->value)
                ->name('violations.edit');
            Route::livewire('pelanggaran/{violation}', 'pages::academic.violation.show')
                ->middleware('permission:'.Permission::ViewViolation->value)
                ->name('violations.show');
        });
});

require __DIR__.'/settings.php';
