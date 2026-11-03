<?php

use App\Http\Controllers\ActionPlanController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// The app root is the sub-folder itself (/commission); a real path avoids directory-slash redirects.
Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
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

    Route::middleware('permission:targets.manage')->group(function () {
        Route::get('/targets', [TargetController::class, 'index'])->name('targets.index');
        Route::put('/targets/{period}/{user}', [TargetController::class, 'update'])->name('targets.update');
    });
    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('permission:action_plan.submit,action_plan.review')->group(function () {
        Route::get('/action-plans', [ActionPlanController::class, 'index'])->name('action-plans.index');
    });
    Route::post('/action-plans', [ActionPlanController::class, 'store'])->middleware('permission:action_plan.submit')->name('action-plans.store');
    Route::delete('/action-plans/{item}', [ActionPlanController::class, 'destroy'])->middleware('permission:action_plan.submit')->name('action-plans.destroy');
    Route::post('/action-plans/{item}/review', [ActionPlanController::class, 'review'])->middleware('permission:action_plan.review')->name('action-plans.review');

    Route::middleware('permission:activity.submit,activity.review')->group(function () {
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    });
    Route::post('/activity', [ActivityController::class, 'store'])->middleware('permission:activity.submit')->name('activity.store');
    Route::post('/activity/{entry}/review', [ActivityController::class, 'review'])->middleware('permission:activity.review')->name('activity.review');

    Route::middleware('permission:calc.view')->group(function () {
        Route::get('/calculations', [CalculationController::class, 'index'])->name('calculations.index');
        Route::get('/calculations/results/{result}', [CalculationController::class, 'show'])->name('calculations.show');
    });
    Route::post('/calculations', [CalculationController::class, 'store'])->middleware('permission:calc.run')->name('calculations.store');

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/verify', [AuditController::class, 'verify'])->name('audit.verify');
    });

    Route::middleware('permission:system.manage')->group(function () {
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
    });
});
