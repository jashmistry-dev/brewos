<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('permission', 'category.view');

        $categories = Category::orderBy('sort_order', 'asc')->get();

        return response()->json([
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
                'status' => $c->status,
            ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'category.create');

        $category = Category::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0),
            'status' => $request->validated('status', 'active'),
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'status' => $category->status,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateCategoryRequest $request, string $cafe_slug, int|string $category_id): JsonResponse
    {
        Gate::authorize('permission', 'category.update');

        $category = Category::findOrFail($category_id);
        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'status' => $category->status,
            ],
        ]);
    }

    public function destroy(string $cafe_slug, int|string $category_id): JsonResponse
    {
        Gate::authorize('permission', 'category.delete');

        $category = Category::findOrFail($category_id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
