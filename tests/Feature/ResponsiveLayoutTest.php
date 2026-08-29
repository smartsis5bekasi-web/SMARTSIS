<?php

use App\Models\Student;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('page header actions wrap instead of widening the page', function () {
    // Four actions in a non-wrapping row pushed the whole layout wider than a
    // phone screen, which scrolled the body sideways and cut off the left edge.
    $markup = file_get_contents(resource_path('views/components/ui/page-header.blade.php'));

    expect($markup)->toContain('flex flex-wrap items-center gap-2');
});

test('the attendance page fits a phone without a sideways body scroll', function () {
    Student::factory()->create();

    $html = $this->actingAs(adminUser())
        ->get(route('attendance.absensi'))
        ->assertOk()
        ->getContent();

    // Header actions wrap...
    expect($html)->toContain('flex flex-wrap items-center gap-2')
        // ...and the wide table scrolls inside its own box rather than
        // stretching the page.
        ->and($html)->toContain('overflow-auto');
});

test('every list table scrolls in its own container and keeps its columns readable', function (string $page) {
    $markup = file_get_contents(resource_path("views/pages/{$page}.blade.php"));

    $tables = substr_count($markup, '<table');
    $scrollers = substr_count($markup, 'overflow-auto') + substr_count($markup, 'overflow-x-auto');

    expect($scrollers)->toBeGreaterThanOrEqual($tables)
        // Without this the browser squeezes columns to fit a phone and the
        // rows become unreadable instead of scrolling.
        ->and($markup)->toContain('whitespace-nowrap');
})->with([
    'attendance/absensi/index',
    'attendance/absensi/recap',
    'attendance/point/index',
    'attendance/point/monitoring',
    'wali-kelas/students/index',
    'academic/violation/index',
    'academic/achievement/index',
    'permit/index',
    'warning/index',
    'master-data/students/index',
    'master-data/teachers',
    'master-data/classrooms',
    'master-data/majors',
    'master-data/academic-years',
]);
