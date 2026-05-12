<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TableOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/public/menu/{qrCode}', [TableOrderController::class, 'publicTableMenu']);
Route::post('/public/orders', [TableOrderController::class, 'publicPlaceOrder']);

Route::middleware(['auth:api', 'check_admin'])->prefix('/admin/menus')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::post('/', [MenuController::class, 'store']);
    Route::get('/{id}', [MenuController::class, 'show']);
    Route::put('/{id}', [MenuController::class, 'update']);
    Route::delete('/{id}', [MenuController::class, 'destroy']);
});

Route::middleware(['auth:api', 'check_admin'])->prefix('/admin/tables')->group(function () {
    Route::get('/', [TableOrderController::class, 'adminTableIndex']);
    Route::post('/', [TableOrderController::class, 'adminTableStore']);
    Route::put('/{id}', [TableOrderController::class, 'adminTableUpdate']);
    Route::delete('/{id}', [TableOrderController::class, 'adminTableDestroy']);
});

Route::middleware(['auth:api', 'check_admin'])->prefix('/admin/orders')->group(function () {
    Route::get('/', [TableOrderController::class, 'adminOrderIndex']);
    Route::patch('/{id}/status', [TableOrderController::class, 'adminOrderStatusUpdate']);
});
