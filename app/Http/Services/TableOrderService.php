<?php

namespace App\Http\Services;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TableOrderService
{
    public function adminTableIndex(int $businessId)
    {
        return RestaurantTable::where('business_id', $businessId)->latest()->get();
    }

    public function createTable(int $businessId, array $data)
    {
        return RestaurantTable::create([
            'table_no' => trim($data['table_no']),
            'qr_code' => !empty($data['qr_code']) ? trim($data['qr_code']) : Str::slug(trim($data['table_no'])),
            'business_id' => $businessId,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => Auth::id() ?? 0,
        ]);
    }

    public function updateTable(RestaurantTable $table, array $data)
    {
        $table->update([
            'table_no' => trim($data['table_no']),
            'qr_code' => trim($data['qr_code']),
            'is_active' => $data['is_active'] ?? $table->is_active,
            'updated_by' => Auth::id() ?? 0,
        ]);

        return $table->refresh();
    }

    public function deleteTable(RestaurantTable $table): void
    {
        $table->delete();
    }

    public function adminOrders(int $businessId)
    {
        return Order::with(['table', 'items.menuItem'])->where('business_id', $businessId)->latest()->get();
    }

    public function updateOrderStatus(Order $order, string $status)
    {
        $order->update([
            'order_status' => $status,
            'updated_by' => Auth::id() ?? 0,
        ]);

        return $order->refresh()->load(['table', 'items.menuItem']);
    }

    public function publicTableMenu(string $qrCode)
    {
        $table = RestaurantTable::where('qr_code', $qrCode)->where('is_active', true)->firstOrFail();
        $categories = Category::where('business_id', $table->business_id)
            ->where('is_active', true)
            ->with(['items' => function ($query) use ($table): void {
                $query->where('business_id', $table->business_id)->where('is_available', true);
            }])
            ->whereHas('items', function ($query) use ($table): void {
                $query->where('business_id', $table->business_id)->where('is_available', true);
            })
            ->latest()
            ->get();

        return compact('table', 'categories');
    }

    public function trackOrder(string $sessionToken)
    {
        return Order::with(['table', 'items.menuItem'])
            ->where('session_token', $sessionToken)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->firstOrFail();
    }

    public function confirmOrder(string $sessionToken, string $status)
    {
        $order = $this->trackOrder($sessionToken);
        if ($order->order_status !== 'pending') {
            return null;
        }
        $order->update(['order_status' => $status, 'updated_by' => 0]);
        return $order->refresh()->load(['table', 'items.menuItem']);
    }

    public function placeOrder(array $data)
    {
        $table = RestaurantTable::where('id', $data['table_id'])->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($data, $table) {
            $requestedItems = collect($data['items']);
            $menuIds = $requestedItems->pluck('menu_item_id')->all();

            $menuItems = Menu::with('category')
                ->whereIn('id', $menuIds)
                ->where('business_id', $table->business_id)
                ->where('is_available', true)
                ->get()
                ->keyBy('id');

            $order = Order::create([
                'table_id' => $table->id,
                'business_id' => $table->business_id,
                'session_token' => (string) Str::uuid(),
                'order_status' => 'pending',
                'total_amount' => 0,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => 0,
            ]);

            $totalAmount = 0;
            foreach ($requestedItems as $item) {
                $menu = $menuItems->get($item['menu_item_id']);
                if (!$menu || !$menu->category || !$menu->category->is_active) {
                    abort(422, 'One or more selected menu items are unavailable.');
                }
                $totalAmount += (float) $menu->price * (int) $item['quantity'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menu->id,
                    'quantity' => (int) $item['quantity'],
                    'price' => $menu->price,
                    'created_by' => 0,
                ]);
            }

            $order->update(['total_amount' => $totalAmount]);
            return $order->refresh()->load(['table', 'items.menuItem']);
        });
    }
}
