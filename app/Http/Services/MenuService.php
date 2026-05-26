<?php

namespace App\Http\Services;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MenuService
{
    public function getMenuItems(int $businessId): Collection
    {
        return Menu::where('business_id', $businessId)->latest()->get();
    }

    public function getMenuIndexData(
        int $businessId,
        string $search = '',
        string $status = 'all',
        string $availability = 'all',
        int $categoryId = 0
    ): Collection {
        $query = Category::query()
            ->where('business_id', $businessId)
            ->with([
                'items' => function ($itemQuery) use ($search): void {
                    $itemQuery->when($search !== '', function ($filteredItemQuery) use ($search): void {
                        $filteredItemQuery->where(function ($nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhere('food_type', 'like', "%{$search}%");
                        });
                    });
                },
            ])
            ->when($availability === 'available', function ($categoryQuery): void {
                $categoryQuery->whereHas('items', function ($itemQuery): void {
                    $itemQuery->where('is_available', true);
                })->where('is_active', true);
            })
            ->when($availability === 'unavailable', function ($categoryQuery): void {
                $categoryQuery->where(function ($nestedCategoryQuery): void {
                    $nestedCategoryQuery
                        ->where('is_active', false)
                        ->orWhereHas('items', function ($itemQuery): void {
                            $itemQuery->where('is_available', false);
                        });
                });
            })
            ->when($status === 'active', function ($categoryQuery): void {
                $categoryQuery->where('is_active', true);
            })
            ->when($status === 'inactive', function ($categoryQuery): void {
                $categoryQuery->where('is_active', false);
            })
            ->when($categoryId > 0, function ($categoryQuery) use ($categoryId): void {
                $categoryQuery->where('id', $categoryId);
            })
            ->when($search !== '', function ($categoryQuery) use ($search): void {
                $categoryQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($itemQuery) use ($search): void {
                            $itemQuery->where(function ($deepNestedQuery) use ($search): void {
                                $deepNestedQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%")
                                    ->orWhere('food_type', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->latest();

        return $query->get()
            ->map(function ($category) use ($search, $availability) {
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
            })
            ->filter(function ($category): bool {
                return $category->items->isNotEmpty();
            })
            ->values();
    }

    public function flattenMenuIndexData(Collection $data): Collection
    {
        return $data->flatMap(function ($category) {
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
    }

    public function paginateFlattenedMenuIndexData(Collection $flattened, int $page, int $perPage): array
    {
        $total = $flattened->count();
        $paginated = $flattened->forPage($page, $perPage)->values();

        return [
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max((int) ceil($total / $perPage), 1),
            ],
        ];
    }
}
