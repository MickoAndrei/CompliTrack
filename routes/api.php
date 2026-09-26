<?php

use App\Http\Controllers\Api\V1\{
    AuthController, UserController, DepartmentController,
    ArchiveController, ComplianceCheckController,
    NotificationController, AuditLogController, ReportController
};
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {

        // Super Admin + Admin: user & department management
        Route::middleware('role:0,1')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::apiResource('departments', DepartmentController::class);
        });

        // Archive module
        Route::post('/archives/upload', [ArchiveController::class, 'upload']);
        Route::get('/archives', [ArchiveController::class, 'index']);
        Route::get('/archives/{id}/download', [ArchiveController::class, 'download']);

        // Compliance checks (Auditor creates, all roles can view per scope)
        Route::apiResource('compliance-checks', ComplianceCheckController::class);
        Route::patch('/compliance-checks/{id}/status', [ComplianceCheckController::class, 'updateStatus']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Super Admin only
        Route::middleware('role:0')->group(function () {
            Route::get('/logs', [AuditLogController::class, 'index']);
        });

        // Admin reports
        Route::middleware('role:0,1')->group(function () {
            Route::get('/reports', [ReportController::class, 'generate']);
        });
    });
});
