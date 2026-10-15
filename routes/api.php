<?php

use App\Http\Controllers\Api\V1\{
    AuthController, UserController, DepartmentController,
    ArchiveController, ComplianceCheckController,
    NotificationController, AuditLogController, ReportController
};
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/auth/password', [AuthController::class, 'changePassword']);

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
