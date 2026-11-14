<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:web')->group(function () {
    Route::view('/', 'auth.login');
    Route::view('/login', 'auth.login')->name('login');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendCode'])->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset.form');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', \App\Http\Middleware\SystemAccess::class])->group(function () {
    Route::get('/audit-plans', function (\Illuminate\Http\Request $request) {
        abort_unless($request->user()->isActive && ((int) $request->user()->roleId === 1
            || app(\App\Services\AuditPlanPermissions::class)->allows($request->user())), 403);
        return view('audit-plans.index');
    });
    Route::middleware('role:1,2')->group(function () {
        Route::post('/audits/{id}/requests', [\App\Http\Controllers\Web\AuditeeController::class, 'createRequest']);
        Route::post('/audits/{id}/requests/{requestId}/review', [\App\Http\Controllers\Web\AuditeeController::class, 'review']);
        Route::post('/audits/{id}/requests/{requestId}/publish', [\App\Http\Controllers\Web\AuditeeController::class, 'publish']);
        Route::get('/audits', [\App\Http\Controllers\Web\AuditController::class, 'index']);
        Route::post('/audits/{id}/assignment', [\App\Http\Controllers\Web\AuditController::class, 'amend']);
        Route::post('/audits', [\App\Http\Controllers\Web\AuditController::class, 'store']);
        Route::get('/audits/{id}', [\App\Http\Controllers\Web\AuditController::class, 'show']);
        Route::post('/audits/{id}', [\App\Http\Controllers\Web\AuditController::class, 'update']);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::patch('/profile', [ProfileController::class, 'updateProfile'])
        ->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
    Route::get('/profile', [ProfileController::class, 'redirectToRoleProfile']);

    Route::middleware('role:0')->prefix('super-admin')->group(function () {
        Route::view('/help', 'help.index');
        Route::view('/profile', 'auth.profile')->name('super-admin.profile');
        Route::view('/dashboard', 'super-admin.dashboard');
        Route::view('/users', 'super-admin.users');
        Route::view('/roles', 'super-admin.roles');
        Route::view('/departments', 'super-admin.departments');
        Route::view('/archives', 'super-admin.archives');
        Route::view('/logs', 'super-admin.logs');
        Route::view('/notifications', 'super-admin.notifications');
        Route::view('/settings', 'super-admin.settings');
    });
    Route::middleware('role:1')->prefix('admin')->group(function () {
        Route::view('/help', 'help.index');
        Route::view('/profile', 'auth.profile')->name('admin.profile');
        Route::view('/dashboard', 'admin.dashboard');
        Route::view('/departments', 'admin.departments');
        Route::view('/users', 'admin.users');
        Route::get('/compliance-records', [\App\Http\Controllers\Web\AtlWorkspaceController::class, 'records']);
        Route::view('/archives', 'admin.archives');
        Route::view('/reports', 'admin.reports');
        Route::view('/notifications', 'admin.notifications');
    });
    Route::middleware('role:2')->prefix('auditor')->group(function () {
        Route::view('/help', 'help.index');
        Route::view('/profile', 'auth.profile')->name('auditor.profile');
        Route::view('/dashboard', 'auditor.dashboard');
        Route::view('/historical-records', 'auditor.checks')->middleware('EnsureUserIsActive')->name('auditor.historical-records');
        Route::get('/checks', fn (\Illuminate\Http\Request $request) => redirect()->route('auditor.historical-records', $request->query()));
        Route::get('/checks/{id}', [\App\Http\Controllers\Web\HistoricalComplianceController::class, 'show']);
        Route::get('/check-create', fn (\Illuminate\Http\Request $request) => redirect('/audits'.($request->has('_tab') ? '?'.http_build_query($request->only('_tab')) : '')));
        Route::view('/archives', 'auditor.archives');
        Route::view('/corrective-actions', 'auditor.corrective-actions');
        Route::view('/notifications', 'auditor.notifications');
    });
    Route::middleware('role:3')->prefix('auditee')->group(function () {
        Route::view('/profile', 'auth.profile')->name('auditee.profile');
        Route::get('/dashboard', [\App\Http\Controllers\Web\AuditeeController::class, 'dashboard']);
        Route::get('/evidence-requests', [\App\Http\Controllers\Web\AuditeeController::class, 'requests']);
        Route::post('/evidence-requests/{id}/{requestId}', [\App\Http\Controllers\Web\AuditeeController::class, 'respond']);
        Route::post('/finding-details/{id}/response', [\App\Http\Controllers\Web\AuditeeController::class, 'correctiveResponse']);
        Route::get('/my-findings', [\App\Http\Controllers\Web\AuditeeController::class, 'results']);
        Route::get('/finding-details/{id}', [\App\Http\Controllers\Web\AuditeeController::class, 'finding']);
        Route::view('/upload', 'auditee.upload');
        Route::get('/archives', [\App\Http\Controllers\Web\AuditeeController::class, 'archives']);
        Route::view('/notifications', 'auditee.notifications');
        Route::view('/help', 'auditee.help');
    });
});
