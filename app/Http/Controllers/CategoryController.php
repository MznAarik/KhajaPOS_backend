<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            $businessId = auth()->user()->business->id;

            $data = Category::where('business_id', $businessId)
                ->latest()
                ->get();

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

    public function store(Request $request)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $category = Category::create([
                'name' => strtolower(trim($validated['name'])),
                'business_id' => $businessId,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

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
            $category = Category::where('business_id', $businessId)->find($id);

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

    public function update(Request $request, string $id)
    {
        $businessId = auth()->user()->business->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('business_id', $businessId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $category = Category::where('business_id', $businessId)->find($id);

            if (!$category) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Category not found!',
                ], 404);
            }

            $category->update([
                'name' => strtolower(trim($validated['name'])),
                'is_active' => $validated['is_active'] ?? $category->is_active,
                'updated_by' => Auth::id(),
            ]);

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

            $category->updated_by = Auth::id();
            $category->save();
            $category->delete();

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
