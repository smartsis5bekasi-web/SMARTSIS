<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'student.onboarded'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified', 'student.onboarded'])->group(function () {
    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');

    Route::livewire('settings/admin', 'pages::settings.admins')
        ->middleware(['role:'.UserRole::SuperAdmin->value, 'password.confirm'])
        ->name('admins.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
