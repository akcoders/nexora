<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDraftController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoDataController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PremisesController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceChecklistController;
use App\Http\Controllers\ServiceDocumentController;
use App\Http\Controllers\ServiceFeedbackController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\ServiceMasterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/service-feedback/{token}', [ServiceFeedbackController::class, 'show'])->name('service-feedback.show');
Route::post('/service-feedback/{token}', [ServiceFeedbackController::class, 'store'])->middleware('throttle:10,1')->name('service-feedback.store');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/customers/{customer}/floor-plans', [CustomerController::class, 'storeFloorPlan'])->name('customers.floor-plans.store');
    Route::post('/customers/{customer}/equipment', [CustomerController::class, 'storeEquipment'])->name('customers.equipment.store');
    Route::resource('customer-groups', CustomerGroupController::class)->only(['index', 'store', 'update', 'destroy']);
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
    Route::get('/service-masters', [ServiceMasterController::class, 'index'])->name('service-masters.index');
    Route::post('/service-masters/service-types', [ServiceMasterController::class, 'storeServiceType'])->name('service-masters.service-types.store');
    Route::post('/service-masters/catalog', [ServiceMasterController::class, 'storeCatalogItem'])->name('service-masters.catalog.store');
    Route::post('/service-masters/conditions', [ServiceMasterController::class, 'storeCondition'])->name('service-masters.conditions.store');
    Route::post('/service-masters/options', [ServiceMasterController::class, 'storeOption'])->name('service-masters.options.store');
    Route::patch('/service-masters/{group}/{id}/toggle', [ServiceMasterController::class, 'toggle'])->name('service-masters.toggle');
    Route::resource('service-jobs', ServiceJobController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::get('/service-jobs/{service_job}/report', [ServiceDocumentController::class, 'report'])->name('service-jobs.report');
    Route::get('/service-jobs/{service_job}/proforma', [ServiceDocumentController::class, 'proforma'])->name('service-jobs.proforma');
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
    Route::post('/settings/demo-data', [DemoDataController::class, 'store'])->name('settings.demo-data.store');
    Route::delete('/settings/demo-data', [DemoDataController::class, 'destroy'])->name('settings.demo-data.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
