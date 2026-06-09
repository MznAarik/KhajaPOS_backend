<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TableOrderController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/menu/{qrCode}', [TableOrderController::class, 'publicTableMenu']);
Route::post('/public/orders', [TableOrderController::class, 'publicPlaceOrder']);
Route::get('/public/tables/{qrCode}/orders', [TableOrderController::class, 'publicTableOrders']);
Route::get('/public/orders/{sessionToken}', [TableOrderController::class, 'publicTrackOrder']);
Route::patch('/public/orders/{sessionToken}/confirm', [TableOrderController::class, 'publicConfirmOrder']);
Route::patch('/public/orders/{sessionToken}/cancel', [TableOrderController::class, 'publicCancelOrder']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        $user = $request->user()?->load('business');

        return ApiResponse::success($user);
    });

    Route::put('/user', function (Request $request) {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('User not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'business_name' => ['required', 'string', 'max:100'],
            'business_type' => ['required', 'string', 'max:20'],
            'business_email' => ['required', 'email', 'max:255'],
            'business_phone' => ['required', 'string', 'max:20'],
            'business_address' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'updated_by' => $user->id,
            ]);

            $user->business()->update([
                'name' => $validated['business_name'],
                'business_type' => $validated['business_type'],
                'email' => $validated['business_email'],
                'phone' => $validated['business_phone'],
                'address' => $validated['business_address'],
                'updated_by' => $user->id,
            ]);
        });

        return ApiResponse::success($user->fresh()->load('business'), 'Profile updated successfully.');
    });
});

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
    Route::put('/{id}', [TableOrderController::class, 'adminOrderUpdate']);
    Route::patch('/{id}/status', [TableOrderController::class, 'adminOrderStatusUpdate']);
});
