<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OverrideController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('templates', TemplateController::class);
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])
        ->name('templates.duplicate');

    Route::get('calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('calls/create', [CallController::class, 'create'])->name('calls.create');
    Route::post('calls', [CallController::class, 'store'])->name('calls.store');
    Route::get('calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::delete('calls/{call}', [CallController::class, 'destroy'])->name('calls.destroy');
    Route::get('calls/{call}/audio', [CallController::class, 'audio'])->name('calls.audio');
    Route::get('calls/{call}/status', [CallController::class, 'status'])->name('calls.status');
    Route::post('calls/{call}/retry', [CallController::class, 'retry'])->name('calls.retry');
    Route::post('calls/{call}/rescore', [CallController::class, 'rescore'])->name('calls.rescore');
    Route::post('calls/{call}/swap-speaker', [CallController::class, 'swapSpeaker'])->name('calls.swap_speaker');
    Route::post('criterion-results/{criterionResult}/overrides', [OverrideController::class, 'store'])->name('overrides.store');

    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::get('agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
});

require __DIR__.'/auth.php';
