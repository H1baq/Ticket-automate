<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\UiGalleryController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('tickets')->name('tickets.')->group(function (): void {
        Route::middleware('role:user')->group(function (): void {
            Route::get('/', [TicketController::class, 'index'])->name('index');
        });
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    });
});

Route::prefix('dev')->group(function (): void {
    Route::get('/ui', [UiGalleryController::class, 'show'])->name('dev.ui');
    Route::post('/ui', [UiGalleryController::class, 'submit'])->name('dev.ui.submit');
});
