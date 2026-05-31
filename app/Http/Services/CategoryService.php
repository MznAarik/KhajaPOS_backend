<?php

namespace App\Http\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CategoryService
{
    public function index(int $businessId): Collection
    {
        return Category::where('business_id', $businessId)->latest()->get();
    }

    public function findForBusiness(int $businessId, string $id): ?Category
    {
        return Category::where('business_id', $businessId)->find($id);
    }

    public function create(int $businessId, array $data): Category
    {
        return Category::create([
            'name' => strtolower(trim($data['name'])),
            'business_id' => $businessId,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => Auth::id(),
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update([
            'name' => strtolower(trim($data['name'])),
            'is_active' => $data['is_active'] ?? $category->is_active,
            'updated_by' => Auth::id(),
        ]);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->updated_by = Auth::id();
        $category->save();
        $category->delete();
    }
}
