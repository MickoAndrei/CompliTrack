<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'auth.login');
Route::view('/login', 'auth.login')->name('login');

Route::middleware('throttle:5,1')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendCode'])->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset.form');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
    Route::view('/profile', 'auth.profile');

    Route::middleware('role:0')->prefix('super-admin')->group(function () {
        Route::view('/dashboard', 'super-admin.dashboard');
        Route::view('/users', 'super-admin.users');
        Route::view('/roles', 'super-admin.roles');
        Route::view('/departments', 'super-admin.departments');
        Route::view('/archives', 'super-admin.archives');
        Route::view('/logs', 'super-admin.logs');
        Route::view('/settings', 'super-admin.settings');
    });
    Route::middleware('role:1')->prefix('admin')->group(function () {
        Route::view('/dashboard', 'admin.dashboard');
        Route::view('/departments', 'admin.departments');
        Route::view('/users', 'admin.users');
        Route::view('/compliance-records', 'admin.compliance-records');
        Route::view('/archives', 'admin.archives');
        Route::view('/reports', 'admin.reports');
        Route::view('/notifications', 'admin.notifications');
    });
    Route::middleware('role:2')->prefix('auditor')->group(function () {
        Route::view('/dashboard', 'auditor.dashboard');
        Route::view('/checks', 'auditor.checks');
        Route::view('/checks/{id}', 'auditor.check-details');
        Route::view('/check-create', 'auditor.check-create');
        Route::view('/archives', 'auditor.archives');
        Route::view('/corrective-actions', 'auditor.corrective-actions');
        Route::view('/notifications', 'auditor.notifications');
    });
    Route::middleware('role:3')->prefix('auditee')->group(function () {
        Route::view('/dashboard', 'auditee.dashboard');
        Route::view('/my-findings', 'auditee.my-findings');
        Route::view('/finding-details/{id}', 'auditee.finding-details');
        Route::view('/upload', 'auditee.upload');
        Route::view('/archives', 'auditee.archives');
        Route::view('/notifications', 'auditee.notifications');
        Route::view('/help', 'auditee.help');
    });
});