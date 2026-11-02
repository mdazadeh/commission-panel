<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // The app root is the sub-folder itself (/commission); a real path avoids directory-slash redirects.
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });

    Route::middleware('permission:imports.view')->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/template/sales', [ImportController::class, 'template'])->name('imports.template');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    });
    Route::middleware('permission:imports.manage')->group(function () {
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::post('/imports/{import}/commit', [ImportController::class, 'commit'])->name('imports.commit');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/verify', [AuditController::class, 'verify'])->name('audit.verify');
    });

    Route::middleware('permission:system.manage')->group(function () {
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
    });
});
