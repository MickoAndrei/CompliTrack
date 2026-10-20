<?php

use App\Http\Controllers\Api\V1\ArchiveController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ComplianceCheckController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/avatar', [AuthController::class, 'avatar']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/auth/password', [AuthController::class, 'changePassword']);
        Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);

        Route::middleware('role:0,1,2')->get('/directory/auditees', [UserController::class, 'auditees']);
        Route::middleware('role:0,1,2,3')->get('/directory/departments', [DepartmentController::class, 'index']);

        // Super Admin + Admin: user & department management
        Route::middleware('role:0,1')->group(function () {
            Route::apiResource('users', UserController::class)->parameters(['users' => 'id']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::apiResource('departments', DepartmentController::class)->parameters(['departments' => 'id']);
            Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast']);
        });

        // Archive module
        Route::post('/archives/upload', [ArchiveController::class, 'upload']);
        Route::get('/archives', [ArchiveController::class, 'index']);
        Route::get('/archives/{id}/download', [ArchiveController::class, 'download']);
        Route::get('/archives/{id}/verify-checksum', [ArchiveController::class, 'verifyChecksum']);

        // Compliance checks (Auditor creates, all roles can view per scope)
        Route::get('/compliance-checks', [ComplianceCheckController::class, 'index']);
        Route::post('/compliance-checks', [ComplianceCheckController::class, 'store']);
        Route::get('/compliance-checks/{id}', [ComplianceCheckController::class, 'show']);
        Route::patch('/compliance-checks/{id}', [ComplianceCheckController::class, 'update']);
        Route::delete('/compliance-checks/{id}', [ComplianceCheckController::class, 'destroy']);
        Route::patch('/compliance-checks/{id}/status', [ComplianceCheckController::class, 'updateStatus']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Super Admin only
        Route::middleware('role:0')->group(function () {
            Route::get('/logs', [AuditLogController::class, 'index']);
        });

        Route::middleware('role:0,1')->group(function () {
            Route::get('/reports', [ReportController::class, 'generate']);
        });
    });
});
