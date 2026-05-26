<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Http\Services\CategoryService;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }
    public function index()
    {
        try {
            $businessId = auth()->user()->business->id;
            $data = $this->categoryService->index($businessId);

            return response()->json([
                'status' => 1,
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch categories: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch categories!',
            ], 500);
        }
    }

    public function store(CategoryStoreRequest $request)
    {
        $businessId = auth()->user()->business->id;

        try {
            $category = $this->categoryService->create($businessId, $request->validated());

            return response()->json([
                'status' => 1,
                'message' => 'Category saved successfully!',
                'category' => $category->refresh(),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to save category: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to save category!',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $businessId = auth()->user()->business->id;
            $category = $this->categoryService->findForBusiness($businessId, $id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            return response()->json([
                'status' => 1,
                'data' => $category,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to fetch category: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch category!',
            ], 500);
        }
    }

    public function update(CategoryUpdateRequest $request, string $id)
    {
        $businessId = auth()->user()->business->id;

        try {
            $category = $this->categoryService->findForBusiness($businessId, $id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            $category = $this->categoryService->update($category, $request->validated());

            return response()->json([
                'status' => 1,
                'message' => 'Category updated successfully!',
                'category' => $category->refresh(),
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to update category: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to update category!',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $businessId = auth()->user()->business->id;
            $category = Category::where('business_id', $businessId)->find($id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            $this->categoryService->delete($category);

            return response()->json([
                'status' => 1,
                'message' => 'Category deleted successfully!',
            ]);
        } catch (\Throwable $th) {
            \Log::error('Failed to delete category: ' . $th->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to delete category!',
            ], 500);
        }
    }
}
