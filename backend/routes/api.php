<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PremisesController;
use App\Http\Controllers\Api\ServiceJobController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::get('/premises', [PremisesController::class, 'index']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::post('/tasks/{task}/action', [TaskController::class, 'action']);
        Route::get('/service-jobs', [ServiceJobController::class, 'index']);
        Route::get('/service-jobs/{service_job}', [ServiceJobController::class, 'show']);
        Route::post('/service-jobs/{service_job}/contact', [ServiceJobController::class, 'contact']);
        Route::post('/service-jobs/{service_job}/journey', [ServiceJobController::class, 'startJourney']);
        Route::post('/service-jobs/{service_job}/arrival', [ServiceJobController::class, 'arrive']);
        Route::post('/service-jobs/{service_job}/inspection', [ServiceJobController::class, 'startInspection']);
        Route::post('/service-jobs/{service_job}/post-inspection', [ServiceJobController::class, 'startPostInspection']);
        Route::put('/service-jobs/{service_job}/inspection-items/{inspection_item}', [ServiceJobController::class, 'saveInspectionItem']);
        Route::post('/service-jobs/{service_job}/inspection/complete', [ServiceJobController::class, 'completeInspection']);
        Route::post('/service-jobs/{service_job}/post-inspection/complete', [ServiceJobController::class, 'completePostInspection']);
        Route::post('/service-jobs/{service_job}/estimates', [ServiceJobController::class, 'storeEstimate']);
        Route::post('/service-jobs/{service_job}/estimates/{estimate}/decision', [ServiceJobController::class, 'decideEstimate']);
        Route::post('/service-jobs/{service_job}/service/start', [ServiceJobController::class, 'startService']);
        Route::post('/service-jobs/{service_job}/services', [ServiceJobController::class, 'storePerformedService']);
        Route::post('/service-jobs/{service_job}/materials', [ServiceJobController::class, 'storeMaterial']);
        Route::post('/service-jobs/{service_job}/photos', [ServiceJobController::class, 'uploadPhoto']);
        Route::post('/service-jobs/{service_job}/signatures', [ServiceJobController::class, 'storeSignature']);
        Route::post('/service-jobs/{service_job}/service/complete', [ServiceJobController::class, 'completeServiceWork']);
        Route::post('/service-jobs/{service_job}/payments', [ServiceJobController::class, 'recordPayment']);
        Route::post('/service-jobs/{service_job}/complete', [ServiceJobController::class, 'complete']);
    });
});
