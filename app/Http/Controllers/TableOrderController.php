<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Http\Requests\OrderStatusUpdateRequest;
use App\Http\Requests\PublicPlaceOrderRequest;
use App\Http\Requests\TableStoreRequest;
use App\Http\Requests\TableUpdateRequest;
use App\Http\Services\TableOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TableOrderController extends Controller
{
    public function __construct(private readonly TableOrderService $tableOrderService)
    {
    }

    public function adminTableIndex()
    {
        try {
            $businessId = auth()->user()->business->id;
            $tables = $this->tableOrderService->adminTableIndex($businessId);

            return response()->json([
                'status' => 1,
                'data' => $tables,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch tables: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch tables!',
            ], 500);
        }
    }

    public function adminTableStore(TableStoreRequest $request)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validated();

        try {
            $table = $this->tableOrderService->createTable($businessId, $validated);

            return response()->json([
                'status' => 1,
                'message' => 'Table created successfully.',
                'data' => $table,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to create table: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to create table!',
            ], 500);
        }
    }

    public function adminTableUpdate(TableUpdateRequest $request, string $id)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validated();

        try {
            $table = $this->tableOrderService->updateTable(
                RestaurantTable::where('business_id', $businessId)->findOrFail($id),
                $validated
            );

            return response()->json([
                'status' => 1,
                'message' => 'Table updated successfully.',
                'data' => $table->refresh(),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to update table: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to update table!',
            ], 500);
        }
    }

    public function adminTableDestroy(string $id)
    {
        try {
            $businessId = auth()->user()->business->id;
            $this->tableOrderService->deleteTable(
                RestaurantTable::where('business_id', $businessId)->findOrFail($id)
            );

            return response()->json([
                'status' => 1,
                'message' => 'Table deleted successfully.',
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to delete table: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to delete table!',
            ], 500);
        }
    }

    public function adminOrderIndex()
    {
        try {
            $businessId = auth()->user()->business->id;

            $orders = $this->tableOrderService->adminOrders($businessId);

            return response()->json([
                'status' => 1,
                'data' => $orders,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch orders: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch orders!',
            ], 500);
        }
    }

    public function adminOrderStatusUpdate(OrderStatusUpdateRequest $request, string $id)
    {
        try {
            $businessId = auth()->user()->business->id;

            $order = $this->tableOrderService->updateOrderStatus(
                Order::with(['table', 'items.menuItem'])->where('business_id', $businessId)->findOrFail($id),
                $request->order_status
            );

            return response()->json([
                'status' => 1,
                'message' => 'Order status updated successfully.',
                'data' => $order->refresh()->load(['table', 'items.menuItem']),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to update order status: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to update order status!',
            ], 500);
        }
    }

    public function publicTableMenu(string $qrCode)
    {
        try {
            $result = $this->tableOrderService->publicTableMenu($qrCode);

            return response()->json([
                'status' => 1,
                'data' => [
                    'table' => $result['table'],
                    'categories' => $result['categories'],
                ],
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch public table menu: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch menu!',
            ], 500);
        }
    }

    public function publicTrackOrder(string $sessionToken)
    {
        try {
            $order = $this->tableOrderService->trackOrder($sessionToken);

            return response()->json([
                'status' => 1,
                'data' => $order,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch public order: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch order!',
            ], 500);
        }
    }

    public function publicTableOrders(string $qrCode)
    {
        try {
            $orders = $this->tableOrderService->tableOrders($qrCode);

            return response()->json([
                'status' => 1,
                'data' => $orders,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch public table orders: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch table orders!',
            ], 500);
        }
    }

    public function publicConfirmOrder(string $sessionToken)
    {
        try {
            $order = $this->tableOrderService->confirmOrder($sessionToken, 'confirmed');
            if (!$order) {
                return response()->json([
                    'status' => 0,
                    'message' => 'This order can no longer be confirmed.',
                ], 422);
            }
            return response()->json(['status' => 1, 'message' => 'Order confirmed successfully.', 'data' => $order]);
        } catch (\Throwable $th) {
            \Log::error('Failed to confirm public order: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to confirm order!',
            ], 500);
        }
    }

    public function publicCancelOrder(string $sessionToken)
    {
        try {
            $order = $this->tableOrderService->confirmOrder($sessionToken, 'cancelled');
            if (!$order) {
                return response()->json([
                    'status' => 0,
                    'message' => 'This order can no longer be cancelled.',
                ], 422);
            }
            return response()->json(['status' => 1, 'message' => 'Order cancelled successfully.', 'data' => $order]);
        } catch (\Throwable $th) {
            \Log::error('Failed to cancel public order: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to cancel order!',
            ], 500);
        }
    }

    public function publicPlaceOrder(PublicPlaceOrderRequest $request)
    {
        try {
            $order = $this->tableOrderService->placeOrder($request->validated());

            return response()->json([
                'status' => 1,
                'message' => 'Order placed successfully.',
                'data' => $order,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to place order: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to place order!',
            ], 500);
        }
    }
}
