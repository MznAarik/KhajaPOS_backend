<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $search = trim((string) $request->query('search', ''));
            $status = trim((string) $request->query('status', 'all'));
            $availability = trim((string) $request->query('availability', 'all'));
            $categoryId = (int) $request->query('category_id', 0);
            $page = max((int) $request->query('page', 1), 1);
            $perPage = max(min((int) $request->query('per_page', 10), 1000), 1);

            $businessId = auth()->user()->business->id;
            $query = Category::where('business_id', $businessId)->
                with([
                    'items' => function ($itemQuery) use ($search) {
                        $itemQuery->when($search !== '', function ($filteredItemQuery) use ($search) {
                            $filteredItemQuery->where(function ($nestedQuery) use ($search) {
                                $nestedQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
                                    ->orWhere('food_type', 'like', "%{$search}%");
                            });
                        });
                    }
                ])
                ->when($availability === 'available', function ($categoryQuery) {
                    $categoryQuery->whereHas('items', function ($itemQuery) {
                        $itemQuery->where('is_available', true);
                    })->where('is_active', true);
                })
                ->when($availability === 'unavailable', function ($categoryQuery) {
                    $categoryQuery->where(function ($nestedCategoryQuery) {
                        $nestedCategoryQuery
                            ->where('is_active', false)
                            ->orWhereHas('items', function ($itemQuery) {
                                $itemQuery->where('is_available', false);
                            });
                    });
                })
                ->when($status === 'active', function ($categoryQuery) {
                    $categoryQuery->where('is_active', true);
                })
                ->when($status === 'inactive', function ($categoryQuery) {
                    $categoryQuery->where('is_active', false);
                })
                ->when($categoryId > 0, function ($categoryQuery) use ($categoryId) {
                    $categoryQuery->where('id', $categoryId);
                })
                ->when($search !== '', function ($categoryQuery) use ($search) {
                    $categoryQuery->where(function ($nestedQuery) use ($search) {
                        $nestedQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('items', function ($itemQuery) use ($search) {
                                $itemQuery->where(function ($deepNestedQuery) use ($search) {
                                    $deepNestedQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('description', 'like', "%{$search}%")
                                        ->orWhere('food_type', 'like', "%{$search}%");
                                });
                            });
                    });
                })
                ->latest();

            $data = $query->get()->map(function ($category) use ($search, $availability) {
                $filteredItems = $category->items;

                if ($availability === 'available') {
                    $filteredItems = $filteredItems->where('is_available', true);
                }

                if ($availability === 'unavailable') {
                    $filteredItems = $category->is_active
                        ? $filteredItems->where('is_available', false)
                        : $filteredItems;
                }

                if ($search !== '') {
                    $filteredItems = $filteredItems->values();
                }

                $category->setRelation('items', $filteredItems->values());

                return $category;
            })->filter(function ($category) {
                return $category->items->isNotEmpty();
            })->values();

            if ($request->has('page') || $request->has('per_page')) {
                $flattened = $data->flatMap(function ($category) {
                    return $category->items->map(function ($item) use ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'is_active' => $category->is_active,
                            'created_at' => $category->created_at,
                            'updated_at' => $category->updated_at,
                            'items' => [$item],
                        ];
                    });
                })->values();

                $total = $flattened->count();
                $paginated = $flattened->forPage($page, $perPage)->values();

                return response()->json([
                    'status' => 1,
                    'data' => $paginated,
                    'meta' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => max((int) ceil($total / $perPage), 1),
                    ],
                ]);
            }

            return response()->json([
                'status' => 1,
                'data' => $data
            ]);

        } catch (\Throwable $th) {
            \Log::error('Failed to get data:' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get data!',
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.price' => 'required_with:items|numeric',
            'items.*.food_type' => 'required_with:items|string|in:veg,non-veg,egg,vegan',
            'items.*.image_url' => 'nullable|string',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            $response = DB::transaction(function () use ($request) {

                $businessId = auth()->user()->business->id;

                $category = Category::create([
                    'name' => strtolower($request->name),
                    'business_id' => $businessId,
                    'is_active' => $request->is_active ?? true,
                    'created_by' => Auth::id() ?? 0,
                ]);

                $item = null;
                if ($request->filled('items')) {
                    foreach ($request->items as $index => $itemData) {
                        $imagePath = $itemData['image_url'] ?? null;

                        if ($request->hasFile("items.$index.image")) {
                            $file = $request->file("items.$index.image");
                            $imageName = time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                            $imagePath = $file->storeAs('images', $imageName, 'public');
                        }

                        $item = Menu::create([
                            'category_id' => $category->id ?? 0,
                            'business_id' => $businessId,
                            'name' => strtolower($itemData['name']),
                            'description' => $itemData['description'],
                            'price' => $itemData['price'],
                            'food_type' => strtolower($itemData['food_type']),
                            'image_url' => $imagePath ?? null,
                            'is_available' => $itemData['is_available'] ?? false,
                            'created_by' => Auth::id() ?? 0,
                        ]);
                    }
                }

                return response()->json([
                    'status' => 1,
                    'message' => 'Category saved sucessfully!',
                    'category' => $category->refresh(),
                    'items' => $item ? $item->refresh() : null,
                ]);
            });

            return $response;

        } catch (\Throwable $th) {
            \Log::error('Failed to save: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to save category',
            ]);
        }

    }

    public function storeItem(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'food_type' => 'required|string|in:veg,non-veg,egg,vegan',
            'image_url' => 'nullable|string',
            'is_available' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            $businessId = auth()->user()->business->id;
            $category = Category::where('business_id', $businessId)->find($request->category_id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            $imagePath = $request->image_url;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . '_menu.' . $file->getClientOriginalExtension();
                $imagePath = $file->storeAs('images', $imageName, 'public');
            }

            $item = Menu::create([
                'category_id' => $category->id,
                'business_id' => $businessId,
                'name' => strtolower(trim($request->name)),
                'description' => $request->description,
                'price' => $request->price,
                'food_type' => strtolower($request->food_type),
                'image_url' => $imagePath ?? null,
                'is_available' => $request->boolean('is_available', true),
                'created_by' => Auth::id() ?? 0,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Menu item created successfully!',
                'data' => $item->refresh(),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to create menu item: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to create menu item!',
            ], 500);
        }
    }

    public function updateItem(Request $request, string $id)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'food_type' => 'required|string|in:veg,non-veg,egg,vegan',
            'image_url' => 'nullable|string',
            'is_available' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            $businessId = auth()->user()->business->id;
            $category = Category::where('business_id', $businessId)->find($request->category_id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            $item = Menu::where('business_id', $businessId)->find($id);
            if (!$item) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Menu item not found!',
                ], 404);
            }

            $imagePath = $request->image_url ?? $item->image_url;
            if ($request->hasFile('image')) {
                if ($item->image_url && Storage::disk('public')->exists($item->image_url)) {
                    Storage::disk('public')->delete($item->image_url);
                }
                $file = $request->file('image');
                $imageName = time() . '_menu_update.' . $file->getClientOriginalExtension();
                $imagePath = $file->storeAs('images', $imageName, 'public');
            }

            $item->update([
                'category_id' => $category->id,
                'name' => strtolower(trim($request->name)),
                'description' => $request->description,
                'price' => $request->price,
                'food_type' => strtolower($request->food_type),
                'image_url' => $imagePath ?? null,
                'is_available' => $request->boolean('is_available', $item->is_available),
                'updated_by' => Auth::id() ?? 0,
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Menu item updated successfully!',
                'data' => $item->refresh(),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to update menu item: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to update menu item!',
            ], 500);
        }
    }

    public function destroyItem(string $id)
    {
        try {
            $businessId = auth()->user()->business->id;
            $item = Menu::where('business_id', $businessId)->find($id);

            if (!$item) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Menu item not found!',
                ], 404);
            }

            if ($item->image_url && Storage::disk('public')->exists($item->image_url)) {
                Storage::disk('public')->delete($item->image_url);
            }

            $item->updated_by = Auth::id() ?? 0;
            $item->save();
            $item->delete();

            return response()->json([
                'status' => 1,
                'message' => 'Menu item deleted successfully!',
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to delete menu item: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to delete menu item!',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Category::with('items')->findOrFail($id);

        return response()->json([
            'status' => 1,
            'data' => $data,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $id,
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer|exists:menu_items,id',
            'items.*.name' => 'required_with:items|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.price' => 'required_with:items|numeric',
            'items.*.food_type' => 'required_with:items|string|in:veg,non-veg,egg,vegan',
            'items.*.image_url' => 'nullable|string',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            $response = DB::transaction(function () use ($request, $id) {

                $businessId = auth()->user()->business->id;

                $category = Category::find($id);
                if (!$category) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Data not found!',
                    ]);
                }
                $category->update([
                    'name' => strtolower($request->name),
                    'description' => $request->description,
                    'is_active' => $request->is_active ?? $category->is_active,
                    'updated_by' => Auth::id() ?? 0,
                ]);

                $lastItem = null;
                if ($request->filled('items')) {
                    foreach ($request->items as $index => $itemData) {
                        $lastItem = null;

                        if (!empty($itemData['id'])) {
                            $lastItem = Menu::where('category_id', $category->id)
                                ->where('id', $itemData['id'])
                                ->first();
                        }

                        if (!$lastItem) {
                            $lastItem = Menu::where('category_id', $category->id)
                                ->where('name', strtolower($itemData['name']))
                                ->first();
                        }

                        $imagePath = $itemData['image_url'] ?? ($lastItem?->image_url);
                        if ($request->hasFile("items.$index.image")) {
                            if ($lastItem?->image_url && Storage::disk('public')->exists($lastItem->image_url)) {
                                Storage::disk('public')->delete($lastItem->image_url);
                            }
                            $file = $request->file("items.$index.image");
                            $imageName = time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                            $imagePath = $file->storeAs('images', $imageName, 'public');
                        }

                        if ($lastItem) {
                            $lastItem->update([
                                'category_id' => $category->id ?? 0,
                                'name' => strtolower($itemData['name']),
                                'description' => $itemData['description'],
                                'price' => $itemData['price'],
                                'food_type' => strtolower($itemData['food_type']),
                                'image_url' => $imagePath ?? null,
                                'is_available' => $itemData['is_available'] ?? false,
                                'updated_by' => Auth::id() ?? 0,
                            ]);
                        } else {
                            $lastItem = Menu::create([
                                'category_id' => $category->id ?? 0,
                                'business_id' => $businessId,
                                'name' => strtolower($itemData['name']),
                                'description' => $itemData['description'],
                                'price' => $itemData['price'],
                                'food_type' => strtolower($itemData['food_type']),
                                'image_url' => $imagePath ?? null,
                                'is_available' => $itemData['is_available'] ?? false,
                                'created_by' => Auth::id() ?? 0,
                            ]);
                        }
                    }
                }
                return response()->json([
                    'status' => 1,
                    'message' => 'Category saved sucessfully!',
                    'category' => $category->refresh(),
                    'items' => $lastItem ? $lastItem->refresh() : null,
                ]);
            });

            return $response;
        } catch (\Throwable $th) {
            \Log::error('Failed to update: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to save category',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Data not found',
                ]);
            }

            foreach ($category->items as $item) {
                if ($item->image) {
                    $imagePath = public_path('images/' . $item->image);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $item->delete();
                $item->update(['updated_by' => Auth::id()]);
            }

            if ($category->image) {
                $imagePath = public_path('images/' . $category->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $category->delete();
            $category->update(['updated_by' => Auth::id()]);

        } catch (\Throwable $th) {
            \Log::error('Failed to delete: ' . $th->getMessage());
            return response()->json([
                'status' => 0,
                'message' => 'Failed to save category',
            ]);
        }
    }
}
