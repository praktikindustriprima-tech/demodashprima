<?php

use App\Http\Controllers\OltController;
use App\Http\Controllers\OltTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OltController::class, 'index'])->name('home');

Route::get('olt/onu-scan', [OltController::class, 'index'])->name('olt.onu-scan');
Route::post('olt/scan', [OltController::class, 'scan'])->name('olt.scan');
Route::post('olt/run-command', [OltController::class, 'runCommand'])->name('olt.run-command');

Route::get('olt/history', [OltController::class, 'history'])->name('olt.history');
Route::get('olt/history/export', [OltController::class, 'export'])->name('olt.history.export');
Route::delete('olt/history', [OltController::class, 'clearHistory'])->name('olt.history.clear');

Route::post('olt/templates', [OltTemplateController::class, 'store'])->name('olt.templates.store');
Route::delete('olt/templates/{oltTemplate}', [OltTemplateController::class, 'destroy'])->name('olt.templates.destroy');
Route::patch('olt/templates/{oltTemplate}/default', [OltTemplateController::class, 'setDefault'])->name('olt.templates.default');

Route::post('olt/get-banner', [OltController::class, 'getBanner'])->name('olt.get-banner');
Route::post('olt/onu-info', [OltController::class, 'getOnuInfo'])->name('olt.onu-info');
Route::get('olt/settings', [OltController::class, 'settings'])->name('olt.settings');

Route::post('olt/settings', [OltController::class, 'saveSettings'])->name('olt.settings.save');
Route::delete('olt/settings/{olt}', [OltController::class, 'destroyOlt'])->name('olt.settings.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
