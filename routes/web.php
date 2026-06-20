<?php

use App\Http\Controllers\AuditSessionController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\OltTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [OltController::class, 'index'])->name('home');

    Route::get('olt/onu-scan', [OltController::class, 'index'])->name('olt.onu-scan');
    Route::get('olt/audit-session', [OltController::class, 'auditSession'])->name('olt.audit-session');
    Route::post('olt/scan', [OltController::class, 'scan'])->name('olt.scan');
    Route::post('olt/run-command', [OltController::class, 'runCommand'])->name('olt.run-command');

    Route::get('olt/history/action', [OltController::class, 'actionHistory'])->name('olt.history.action');
    Route::get('olt/history/action/export', [OltController::class, 'export'])->name('olt.history.export');
    Route::delete('olt/history/action', [OltController::class, 'clearHistory'])->name('olt.history.clear');

    Route::get('olt/history/session', [OltController::class, 'sessionHistory'])->name('olt.history.session');

    Route::post('olt/templates', [OltTemplateController::class, 'store'])->name('olt.templates.store');
    Route::delete('olt/templates/{oltTemplate}', [OltTemplateController::class, 'destroy'])->name('olt.templates.destroy');
    Route::patch('olt/templates/{oltTemplate}/default', [OltTemplateController::class, 'setDefault'])->name('olt.templates.default');

    Route::post('olt/get-banner', [OltController::class, 'getBanner'])->name('olt.get-banner');
    Route::post('olt/onu-info', [OltController::class, 'getOnuInfo'])->name('olt.onu-info');
    Route::get('olt/settings', [OltController::class, 'settings'])->name('olt.settings');

    Route::post('olt/settings', [OltController::class, 'saveSettings'])->name('olt.settings.save');
    Route::delete('olt/settings/{olt}', [OltController::class, 'destroyOlt'])->name('olt.settings.destroy');

    // Audit Sessions
    Route::get('audit/sessions', [AuditSessionController::class, 'index'])->name('audit.sessions.index');
    Route::post('audit/sessions', [AuditSessionController::class, 'store'])->name('audit.sessions.store');
    Route::get('audit/sessions/active', [AuditSessionController::class, 'active'])->name('audit.sessions.active');
    Route::get('audit/sessions/{session}', [AuditSessionController::class, 'show'])->name('audit.sessions.show');
    Route::post('audit/sessions/{session}/save', [AuditSessionController::class, 'saveOnus'])->name('audit.sessions.save');
    Route::post('audit/sessions/{session}/complete', [AuditSessionController::class, 'complete'])->name('audit.sessions.complete');
    Route::delete('audit/sessions/{session}', [AuditSessionController::class, 'destroy'])->name('audit.sessions.destroy');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
