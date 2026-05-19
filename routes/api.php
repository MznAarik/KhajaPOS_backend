<?php

use App\Http\Controllers\CategoryController;
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
Route::get('/public/orders/{sessionToken}', [TableOrderController::class, 'publicTrackOrder']);
Route::patch('/public/orders/{sessionToken}/confirm', [TableOrderController::class, 'publicConfirmOrder']);
Route::patch('/public/orders/{sessionToken}/cancel', [TableOrderController::class, 'publicCancelOrder']);

Route::middleware(['auth:api', 'check_admin'])->prefix('/admin/categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

Route::middleware(['auth:api', 'check_admin'])->prefix('/admin/menus')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::post('/', [MenuController::class, 'store']);
    Route::post('/items', [MenuController::class, 'storeItem']);
    Route::put('/items/{id}', [MenuController::class, 'updateItem']);
    Route::delete('/items/{id}', [MenuController::class, 'destroyItem']);
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
