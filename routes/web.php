<?php

use App\Enums\Permission;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
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
});

require __DIR__.'/settings.php';
