<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'category.view');

        $categories = Category::orderBy('sort_order', 'asc')->get();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
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

        return Inertia::render('Tenant/Categories', [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
                'status' => $c->status,
            ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'category.create');

        $category = Category::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'sort_order' => $request->validated('sort_order', 0),
            'status' => $request->validated('status', 'active'),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(UpdateCategoryRequest $request, string $cafe_slug, int|string $category_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'category.update');

        $category = Category::findOrFail($category_id);
        $category->update($request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(string $cafe_slug, int|string $category_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'category.delete');

        $category = Category::findOrFail($category_id);
        $category->delete();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'message' => 'Category deleted successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
