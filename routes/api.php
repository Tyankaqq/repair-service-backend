<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RepairRequestController;
Route::post('/requests', [RepairRequestController::class, 'store']);
// ── Публичные маршруты ─────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// Создание заявки — без авторизации (клиент создаёт сам)
Route::post('/requests', [RepairRequestController::class, 'store']);

// ── Авторизованные маршруты ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── Диспетчер ──────────────────────────────────────────────────────────
    Route::middleware('role:dispatcher')->group(function () {
        Route::get('/requests', [RepairRequestController::class, 'index']);
        Route::get('/masters', [AuthController::class, 'masters']);
        Route::patch('/requests/{id}/assign', [RepairRequestController::class, 'assign']);
        Route::patch('/requests/{id}/cancel', [RepairRequestController::class, 'cancel']);
    });

    // ── Мастер ─────────────────────────────────────────────────────────────
    Route::middleware('role:master')->group(function () {
        Route::get('/my-requests', [RepairRequestController::class, 'myRequests']);
        Route::patch('/requests/{id}/take', [RepairRequestController::class, 'takeInProgress']);
        Route::patch('/requests/{id}/complete', [RepairRequestController::class, 'complete']);
    });
});
