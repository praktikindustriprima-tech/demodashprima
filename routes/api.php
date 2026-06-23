<?php

use App\Http\Controllers\Api\AuditSessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OltController;
use App\Http\Controllers\Api\OltHistoryController;
use App\Http\Controllers\Api\OltPreferenceController;
use App\Http\Controllers\Api\OltScanController;
use App\Http\Controllers\Api\OltTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('v1', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'OLT Management System API',
        'version' => '1.0',
        'endpoints' => [
            'auth' => [
                'POST /api/v1/auth/login',
                'POST /api/v1/auth/logout',
                'GET /api/v1/auth/user',
            ],
            'olts' => [
                'GET /api/v1/olts',
                'POST /api/v1/olts',
                'GET /api/v1/olts/{id}',
                'PUT /api/v1/olts/{id}',
                'DELETE /api/v1/olts/{id}',
                'POST /api/v1/olts/{id}/scan',
                'POST /api/v1/olts/{id}/banner',
                'POST /api/v1/quick-scan',
            ],
            'templates' => [
                'GET /api/v1/templates',
                'POST /api/v1/templates',
                'GET /api/v1/templates/{id}',
                'PUT /api/v1/templates/{id}',
                'DELETE /api/v1/templates/{id}',
                'PATCH /api/v1/templates/{id}/default',
            ],
            'audit-sessions' => [
                'GET /api/v1/audit-sessions',
                'POST /api/v1/audit-sessions',
                'GET /api/v1/audit-sessions/{id}',
                'DELETE /api/v1/audit-sessions/{id}',
                'GET /api/v1/audit-sessions/active',
                'POST /api/v1/audit-sessions/{id}/onus',
                'POST /api/v1/audit-sessions/{id}/complete',
                'POST /api/v1/audit-sessions/{id}/temporary',
                'GET /api/v1/audit-sessions/{id}/temporary',
                'DELETE /api/v1/audit-sessions/{id}/temporary',
                'DELETE /api/v1/audit-sessions/{id}/temporary/{sn}',
            ],
            'commands' => [
                'POST /api/v1/run-command',
                'POST /api/v1/onu-info',
            ],
            'preferences' => [
                'GET /api/v1/preferences',
                'PUT /api/v1/preferences',
            ],
            'history' => [
                'GET /api/v1/history/actions',
                'DELETE /api/v1/history/actions',
                'GET /api/v1/history/actions/export',
            ],
        ],
    ]);
});

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

    // Protected resources
    Route::middleware('auth:sanctum')->group(function () {
        // OLTs
        Route::apiResource('olts', OltController::class);

        // Scan & commands
        Route::post('olts/{olt}/scan', [OltScanController::class, 'scan']);
        Route::post('olts/{olt}/banner', [OltScanController::class, 'banner']);
        Route::post('run-command', [OltScanController::class, 'runCommand']);
        Route::post('onu-info', [OltScanController::class, 'onuInfo']);
        Route::post('quick-scan', [OltScanController::class, 'quickScan']);

        // Templates
        Route::apiResource('templates', OltTemplateController::class);
        Route::patch('templates/{template}/default', [OltTemplateController::class, 'setDefault']);

        // Audit Sessions
        Route::get('audit-sessions/active', [AuditSessionController::class, 'active']);
        Route::apiResource('audit-sessions', AuditSessionController::class)
            ->parameters(['audit-sessions' => 'session'])
            ->except(['update']);
        Route::post('audit-sessions/{session}/onus', [AuditSessionController::class, 'saveOnus']);
        Route::post('audit-sessions/{session}/complete', [AuditSessionController::class, 'complete']);
        Route::get('audit-sessions/{session}/temporary', [AuditSessionController::class, 'loadTemporary']);
        Route::post('audit-sessions/{session}/temporary', [AuditSessionController::class, 'saveTemporary']);
        Route::delete('audit-sessions/{session}/temporary/{sn}', [AuditSessionController::class, 'removeOnu']);
        Route::delete('audit-sessions/{session}/temporary', [AuditSessionController::class, 'clearTemporary']);

        // Preferences
        Route::get('preferences', [OltPreferenceController::class, 'index']);
        Route::put('preferences', [OltPreferenceController::class, 'update']);

        // History
        Route::get('history/actions', [OltHistoryController::class, 'index']);
        Route::get('history/actions/export', [OltHistoryController::class, 'export']);
        Route::delete('history/actions', [OltHistoryController::class, 'clear']);
    });
});
