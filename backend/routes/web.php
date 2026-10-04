<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDraftController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PremisesController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceChecklistController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store']);
    Route::post('/customers/draft', [CustomerDraftController::class, 'store'])->name('customers.draft.store');
    Route::put('/customers/draft/{uuid}', [CustomerDraftController::class, 'update'])->name('customers.draft.update');
    Route::get('/customers/draft/{uuid}', [CustomerDraftController::class, 'show'])->name('customers.draft.show');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/product-categories', [ProductController::class, 'storeCategory'])->name('product-categories.store');
    Route::get('/service-checklists', [ServiceChecklistController::class, 'index'])->name('checklists.index');
    Route::post('/service-checklists', [ServiceChecklistController::class, 'store'])->name('checklists.store');
    Route::delete('/service-checklists/{checklist}', [ServiceChecklistController::class, 'destroy'])->name('checklists.destroy');
    Route::get('/attendance', [AdminAttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/{attendance}/review', [AdminAttendanceController::class, 'review'])->name('attendance.review');
    Route::post('/premises', [PremisesController::class, 'store'])->name('premises.store');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('/tasks/{task}/action', [TaskController::class, 'action'])->name('tasks.action');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
