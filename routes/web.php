<?php

use App\Http\Controllers\CallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('templates', TemplateController::class);
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])
        ->name('templates.duplicate');

    Route::get('calls/create', [CallController::class, 'create'])->name('calls.create');
    Route::post('calls', [CallController::class, 'store'])->name('calls.store');
    Route::get('calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::get('calls/{call}/audio', [CallController::class, 'audio'])->name('calls.audio');
    Route::get('calls/{call}/status', [CallController::class, 'status'])->name('calls.status');
    Route::post('calls/{call}/retry', [CallController::class, 'retry'])->name('calls.retry');
});

require __DIR__.'/auth.php';
