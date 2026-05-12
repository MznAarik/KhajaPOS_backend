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

class TableOrderController extends Controller
{
    public function adminTableIndex()
    {
        $tables = RestaurantTable::latest()->get();

        return response()->json([
            'status' => 1,
            'data' => $tables,
        ]);
    }

    public function adminTableStore(Request $request)
    {
        $request->validate([
            'table_no' => 'required|string|max:100|unique:tables,table_no',
            'qr_code' => 'nullable|string|max:160|unique:tables,qr_code',
            'is_active' => 'nullable|boolean',
        ]);

        $table = RestaurantTable::create([
            'table_no' => trim($request->table_no),
            'qr_code' => $request->filled('qr_code')
                ? trim($request->qr_code)
                : Str::slug(trim($request->table_no)),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => Auth::id() ?? 0,
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Table created successfully.',
            'data' => $table,
        ]);
    }

    public function adminTableUpdate(Request $request, string $id)
    {
        $request->validate([
            'table_no' => 'required|string|max:100|unique:tables,table_no,' . $id,
            'qr_code' => 'required|string|max:160|unique:tables,qr_code,' . $id,
            'is_active' => 'nullable|boolean',
        ]);

        $table = RestaurantTable::findOrFail($id);
        $table->update([
            'table_no' => trim($request->table_no),
            'qr_code' => trim($request->qr_code),
            'is_active' => $request->boolean('is_active', $table->is_active),
            'updated_by' => Auth::id() ?? 0,
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Table updated successfully.',
            'data' => $table->refresh(),
        ]);
    }

    public function adminTableDestroy(string $id)
    {
        $table = RestaurantTable::findOrFail($id);
        $table->delete();

        return response()->json([
            'status' => 1,
            'message' => 'Table deleted successfully.',
        ]);
    }

    public function adminOrderIndex()
    {
        $orders = Order::with(['table', 'items.menuItem'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $orders,
        ]);
    }

    public function adminOrderStatusUpdate(Request $request, string $id)
    {
        $request->validate([
            'order_status' => 'required|in:pending,confirmed,preparing,ready,served,cancelled',
        ]);

        $order = Order::with(['table', 'items.menuItem'])->findOrFail($id);
        $order->update([
            'order_status' => $request->order_status,
            'updated_by' => Auth::id() ?? 0,
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Order status updated successfully.',
            'data' => $order->refresh()->load(['table', 'items.menuItem']),
        ]);
    }

    public function publicTableMenu(string $qrCode)
    {
        $table = RestaurantTable::where('qr_code', $qrCode)
            ->where('is_active', true)
            ->firstOrFail();

        $categories = Category::where('is_active', true)
            ->with(['items' => function ($query) {
                $query->where('is_available', true);
            }])
            ->whereHas('items', function ($query) {
                $query->where('is_available', true);
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 1,
            'data' => [
                'table' => $table,
                'categories' => $categories,
            ],
        ]);
    }

    public function publicPlaceOrder(Request $request)
    {
        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'remarks' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1|max:50',
        ]);

        $table = RestaurantTable::where('id', $request->table_id)
            ->where('is_active', true)
            ->firstOrFail();

        $order = DB::transaction(function () use ($request, $table) {
            $requestedItems = collect($request->items);
            $menuIds = $requestedItems->pluck('menu_item_id')->all();

            $menus = Menu::with('category')
                ->whereIn('id', $menuIds)
                ->where('is_available', true)
                ->get()
                ->keyBy('id');

            $totalAmount = 0;

            $order = Order::create([
                'table_id' => $table->id,
                'session_token' => (string) Str::uuid(),
                'order_status' => 'pending',
                'total_amount' => 0,
                'remarks' => $request->remarks,
                'created_by' => 0,
            ]);

            foreach ($requestedItems as $item) {
                $menu = $menus->get($item['menu_item_id']);

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
    }
}
