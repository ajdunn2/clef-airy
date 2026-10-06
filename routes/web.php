<?php

use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\SavedCallController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ConfigurationController::class, 'edit'])->name('configuration.edit');
Route::put('/configuration', [ConfigurationController::class, 'update'])->name('configuration.update');

Route::get('/run', [RunController::class, 'create'])->name('run.create');
Route::get('/run/example', [RunController::class, 'create'])->name('run.example');
Route::post('/run/example', [RunController::class, 'store'])->name('run.example.store');
Route::get('/run/example-2', [RunController::class, 'create'])->name('run.example2');
Route::post('/run/example-2', [RunController::class, 'store'])->name('run.example2.store');
Route::get('/run/example-3', [RunController::class, 'create'])->name('run.example3');
Route::post('/run/example-3', [RunController::class, 'store'])->name('run.example3.store');
Route::post('/run', [RunController::class, 'store'])->name('run.store');
Route::post('/run/download', [RunController::class, 'download'])->name('run.download');

Route::get('/calls/{savedCall}', [RunController::class, 'show'])->name('calls.show');
Route::post('/calls', [SavedCallController::class, 'store'])->name('calls.store');
Route::post('/calls/{savedCall}', [SavedCallController::class, 'update'])->name('calls.update');
Route::delete('/calls/{savedCall}', [SavedCallController::class, 'destroy'])->name('calls.destroy');
