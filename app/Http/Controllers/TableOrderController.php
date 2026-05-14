<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TableOrderController extends Controller
{
    public function adminTableIndex()
    {
        try {
            $businessId = auth()->user()->business->id;

            $tables = RestaurantTable::where('business_id', $businessId)
                ->latest()
                ->get();

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

    public function adminTableStore(Request $request)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validate([
            'table_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tables', 'table_no')->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'qr_code' => [
                'nullable',
                'string',
                'max:160',
                Rule::unique('tables', 'qr_code')->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $table = RestaurantTable::create([
                'table_no' => trim($validated['table_no']),
                'qr_code' => !empty($validated['qr_code'])
                    ? trim($validated['qr_code'])
                    : Str::slug(trim($validated['table_no'])),
                'business_id' => $businessId,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id() ?? 0,
            ]);

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

    public function adminTableUpdate(Request $request, string $id)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validate([
            'table_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tables', 'table_no')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'qr_code' => [
                'required',
                'string',
                'max:160',
                Rule::unique('tables', 'qr_code')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $table = RestaurantTable::where('business_id', $businessId)->findOrFail($id);

            $table->update([
                'table_no' => trim($validated['table_no']),
                'qr_code' => trim($validated['qr_code']),
                'is_active' => $request->boolean('is_active', $table->is_active),
                'updated_by' => Auth::id() ?? 0,
            ]);

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
            $table = RestaurantTable::where('business_id', $businessId)->findOrFail($id);

            $table->delete();

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

            $orders = Order::with(['table', 'items.menuItem'])
                ->where('business_id', $businessId)
                ->latest()
                ->get();

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

    public function adminOrderStatusUpdate(Request $request, string $id)
    {
        $request->validate([
            'order_status' => 'required|in:pending,confirmed,preparing,ready,served,cancelled',
        ]);

        try {
            $businessId = auth()->user()->business->id;

            $order = Order::with(['table', 'items.menuItem'])
                ->where('business_id', $businessId)
                ->findOrFail($id);

            $order->update([
                'order_status' => $request->order_status,
                'updated_by' => Auth::id() ?? 0,
            ]);

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
            $table = RestaurantTable::where('qr_code', $qrCode)
                ->where('is_active', true)
                ->firstOrFail();

            $categories = Category::where('business_id', $table->business_id)
                ->where('is_active', true)
                ->with([
                    'items' => function ($query) use ($table) {
                        $query->where('business_id', $table->business_id)
                            ->where('is_available', true);
                    }
                ])
                ->whereHas('items', function ($query) use ($table) {
                    $query->where('business_id', $table->business_id)
                        ->where('is_available', true);
                })
                ->latest()
                ->get();

            return response()->json([
                'status' => 1,
                'data' => [
                    'table' => $table,
                    'categories' => $categories,
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
            $order = Order::with(['table', 'items.menuItem'])
                ->where('session_token', $sessionToken)
                ->firstOrFail();

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

    public function publicConfirmOrder(string $sessionToken)
    {
        try {
            $order = Order::with(['table', 'items.menuItem'])
                ->where('session_token', $sessionToken)
                ->firstOrFail();

            if ($order->order_status !== 'pending') {
                return response()->json([
                    'status' => 0,
                    'message' => 'This order can no longer be confirmed.',
                ], 422);
            }

            $order->update([
                'order_status' => 'confirmed',
                'updated_by' => 0,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Order confirmed successfully.',
                'data' => $order->refresh()->load(['table', 'items.menuItem']),
            ]);
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
            $order = Order::with(['table', 'items.menuItem'])
                ->where('session_token', $sessionToken)
                ->firstOrFail();

            if ($order->order_status !== 'pending') {
                return response()->json([
                    'status' => 0,
                    'message' => 'This order can no longer be cancelled.',
                ], 422);
            }

            $order->update([
                'order_status' => 'cancelled',
                'updated_by' => 0,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Order cancelled successfully.',
                'data' => $order->refresh()->load(['table', 'items.menuItem']),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to cancel public order: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to cancel order!',
            ], 500);
        }
    }

    public function publicPlaceOrder(Request $request)
    {
        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'remarks' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:50',
        ]);

        try {
            $table = RestaurantTable::where('id', $request->table_id)
                ->where('is_active', true)
                ->firstOrFail();

            $order = DB::transaction(function () use ($request, $table) {
                $requestedItems = collect($request->items);
                $menuIds = $requestedItems->pluck('menu_item_id')->all();

                $menuItems = Menu::with('category')
                    ->whereIn('id', $menuIds)
                    ->where('business_id', $table->business_id)
                    ->where('is_available', true)
                    ->get()
                    ->keyBy('id');

                $totalAmount = 0;

                $order = Order::create([
                    'table_id' => $table->id,
                    'business_id' => $table->business_id,
                    'session_token' => (string) Str::uuid(),
                    'order_status' => 'pending',
                    'total_amount' => 0,
                    'remarks' => $request->remarks,
                    'created_by' => 0,
                ]);

                foreach ($requestedItems as $item) {
                    $menu = $menuItems->get($item['menu_item_id']);

                    if (!$menu || !$menu->category || !$menu->category->is_active) {
                        abort(422, 'One or more selected menu items are unavailable.');
                    }

                    $lineTotal = (float) $menu->price * (int) $item['quantity'];
                    $totalAmount += $lineTotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menu->id,
                        'quantity' => (int) $item['quantity'],
                        'price' => $menu->price,
                        'created_by' => 0,
                    ]);
                }

                $order->update([
                    'total_amount' => $totalAmount,
                ]);

                return $order->refresh()->load(['table', 'items.menuItem']);
            });

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
