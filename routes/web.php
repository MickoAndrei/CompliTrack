<?php

use Illuminate\Support\Facades\Route;

// Blade SPA-style entry points per role dashboard (Guide §10)
Route::view('/login', 'auth.login')->name('login');
Route::view('/forgot-password', 'auth.forgot-password');

Route::middleware(['auth'])->group(function () {
    Route::view('/profile', 'auth.profile');

    Route::middleware('role:0')->prefix('super-admin')->group(function () {
        Route::view('/dashboard', 'super-admin.dashboard');
        Route::view('/users', 'super-admin.users');
        Route::view('/roles', 'super-admin.roles');
        Route::view('/departments', 'super-admin.departments');
        Route::view('/logs', 'super-admin.logs');
        Route::view('/settings', 'super-admin.settings');
    });

    Route::middleware('role:1')->prefix('admin')->group(function () {
        Route::view('/dashboard', 'admin.dashboard');
        Route::view('/departments', 'admin.departments');
        Route::view('/users', 'admin.users');
        Route::view('/archives', 'admin.archives');
        Route::view('/reports', 'admin.reports');
        Route::view('/notifications', 'admin.notifications');
    });

    Route::middleware('role:2')->prefix('auditor')->group(function () {
        Route::view('/dashboard', 'auditor.dashboard');
        Route::view('/checks', 'auditor.checks');
        Route::view('/archives', 'auditor.archives');
        Route::view('/corrective-actions', 'auditor.corrective-actions');
        Route::view('/notifications', 'auditor.notifications');
    });

    Route::middleware('role:3')->prefix('auditee')->group(function () {
        Route::view('/dashboard', 'auditee.dashboard');
        Route::view('/my-findings', 'auditee.my-findings');
        Route::view('/upload', 'auditee.upload');
        Route::view('/archives', 'auditee.archives');
        Route::view('/notifications', 'auditee.notifications');
        Route::view('/help', 'auditee.help');
    });
});
