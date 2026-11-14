<?php

use App\Http\Controllers\Api\V1\ArchiveController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ComplianceCheckController;
use App\Http\Controllers\Api\V1\DatabaseStatsController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Capability URLs are short-lived; save callbacks additionally require the server JWT.
Route::get('/document-editor/{session}/file', [\App\Http\Controllers\Api\V1\DocumentEditorController::class, 'file'])->middleware('throttle:120,1');
Route::post('/document-editor/{session}/callback', [\App\Http\Controllers\Api\V1\DocumentEditorController::class, 'callback'])->middleware('throttle:120,1');

Route::prefix('v1')->group(function () {
    Route::get('/version', fn () => response()->json([
        'version' => config('app.version'),
    ]));

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['throttle:5,1', \App\Http\Middleware\SystemAccess::class]);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/password-reset-status', [AuthController::class, 'resetStatus'])->middleware('throttle:60,1');

    Route::middleware(['auth:sanctum,api', EnsureUserIsActive::class, \App\Http\Middleware\SystemAccess::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/avatar', [AuthController::class, 'avatar']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/auth/password', [AuthController::class, 'changePassword']);
        Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);

        Route::middleware('role:0,1,2')->get('/directory/auditees', [UserController::class, 'auditees']);
        Route::middleware('role:0,1,2,3')->get('/directory/departments', [DepartmentController::class, 'index']);
        Route::middleware('role:0')->get('/directory/organization', [DepartmentController::class, 'organizationSummary']);

        // Admin/ATL directories are read-only; account and department writes belong to Super Admin.
        Route::middleware('role:1')->get('/admin/workspace', [\App\Http\Controllers\Web\AtlWorkspaceController::class, 'summary']);
        Route::middleware('role:0')->get('/users/options', [UserController::class, 'options']);
        Route::middleware('role:0')->get('/roles', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'index']);
        Route::middleware(['role:0', 'throttle:10,1'])->patch('/roles/{id}/permissions', [\App\Http\Controllers\Api\V1\RolePermissionController::class, 'update']);
        Route::get('/audit-plans', [\App\Http\Controllers\Api\V1\AuditPlanController::class, 'index']);
        Route::post('/audit-plans/{id}', [\App\Http\Controllers\Api\V1\AuditPlanController::class, 'update']);
        Route::middleware('role:0,1')->group(function () {
            Route::apiResource('users', UserController::class)->only(['index', 'show'])->parameters(['users' => 'id']);
            Route::apiResource('departments', DepartmentController::class)->only(['index', 'show'])->parameters(['departments' => 'id']);
            Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast']);
        });
        Route::middleware('role:0')->group(function () {
            Route::apiResource('users', UserController::class)->only(['store', 'update', 'destroy'])->parameters(['users' => 'id']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy'])->parameters(['departments' => 'id']);
        });

        // Archive module
        Route::post('/archives/upload', [ArchiveController::class, 'upload']);
        Route::get('/archives', [ArchiveController::class, 'index']);
        Route::get('/archives/tree', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'tree']);
        Route::get('/archives/organization', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'organization']);
        Route::patch('/archives/organization/{id}', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'assign']);
        Route::patch('/archives/categories/{id}', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'updateCategory']);
        Route::get('/archives/{id}', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'show']);
        Route::post('/archives/{id}/versions', [ArchiveController::class, 'version']);
        Route::post('/archives/{id}/editor', [\App\Http\Controllers\Api\V1\DocumentEditorController::class, 'start']);
        Route::get('/document-editor/{session}/status', [\App\Http\Controllers\Api\V1\DocumentEditorController::class, 'status']);
        Route::patch('/archives/{id}/permissions', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'permissions']);
        Route::post('/archives/{id}/share', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'share']);
        Route::delete('/archives/{id}/share/{auditId}', [\App\Http\Controllers\Api\V1\QmsArchiveController::class, 'revokeShare']);
        Route::get('/archives/{id}/download', [ArchiveController::class, 'download']);
        Route::get('/archives/{id}/verify-checksum', [ArchiveController::class, 'verifyChecksum']);

        // Compliance checks (Auditor creates, all roles can view per scope)
        Route::middleware('role:2')->get('/auditor/metrics', [\App\Http\Controllers\Web\AuditController::class, 'metrics']);
        Route::get('/compliance-checks', [ComplianceCheckController::class, 'index']);
        Route::post('/compliance-checks', [ComplianceCheckController::class, 'store']);
        Route::get('/compliance-checks/{id}', [ComplianceCheckController::class, 'show']);
        Route::patch('/compliance-checks/{id}', [ComplianceCheckController::class, 'update']);
        Route::delete('/compliance-checks/{id}', [ComplianceCheckController::class, 'destroy']);
        Route::patch('/compliance-checks/{id}/status', [ComplianceCheckController::class, 'updateStatus']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}/open', [NotificationController::class, 'open']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Super Admin only
        Route::middleware('role:0')->group(function () {
            Route::get('/settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'show']);
            Route::patch('/settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'update']);
            Route::post('/settings/backup', [\App\Http\Controllers\Api\V1\SettingsController::class, 'backup']);
            Route::post('/settings/rotate-key', [\App\Http\Controllers\Api\V1\SettingsController::class, 'rotate'])->middleware('throttle:5,1');
            Route::post('/settings/wipe', [\App\Http\Controllers\Api\V1\SettingsController::class, 'wipe'])->middleware('throttle:3,1');
            Route::get('/system/dashboard', [\App\Http\Controllers\Api\V1\SuperAdminDashboardController::class, 'summary']);
            Route::get('/system/dashboard/health', [\App\Http\Controllers\Api\V1\SuperAdminDashboardController::class, 'health']);
            Route::get('/dashboard/health', [\App\Http\Controllers\Api\V1\SuperAdminDashboardController::class, 'health']);
            Route::get('/logs', [AuditLogController::class, 'index']);
            Route::get('/logs/{id}', [AuditLogController::class, 'show']);
            Route::get('/system/database-stats', DatabaseStatsController::class);
            Route::get('/system/health', function () {
                try {
                    DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);

                    return response()->json(['database' => 'ok']);
                } catch (\Throwable $exception) {
                    report($exception);

                    return response()->json(['database' => 'unavailable'], 503);
                }
            });
            Route::post('/users/{id}/reactivate', [UserController::class, 'reactivate']);
            Route::post('/departments/{id}/reactivate', [DepartmentController::class, 'reactivate']);
        });

        Route::middleware('role:0,1')->group(function () {
            Route::get('/reports', [ReportController::class, 'generate']);
        });
    });
});
