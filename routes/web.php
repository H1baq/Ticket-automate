<?php

declare(strict_types=1);

use App\Http\Controllers\Dev\UiGalleryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('dev')->group(function (): void {
    Route::get('/ui', [UiGalleryController::class, 'show'])->name('dev.ui');
    Route::post('/ui', [UiGalleryController::class, 'submit'])->name('dev.ui.submit');
});
