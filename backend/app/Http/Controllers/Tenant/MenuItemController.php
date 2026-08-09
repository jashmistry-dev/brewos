<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMenuItemRequest;
use App\Http\Requests\Tenant\UpdateMenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('permission', 'menu.view');

        $query = MenuItem::with('category')->orderBy('sort_order', 'asc');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $items = $query->get();

        return response()->json([
            'menu_items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'image' => $item->image,
                'status' => $item->status,
                'is_available' => $item->isAvailable(),
                'sort_order' => $item->sort_order,
            ]),
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'menu.create');

        $validated = $request->validated();
        $imagePath = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu-items', 'public');
            $imagePath = Storage::url($path);
        }

        $item = MenuItem::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'image' => $imagePath,
            'status' => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $item->load('category');

        return response()->json([
            'message' => 'Menu item created successfully.',
            'menu_item' => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'name' => $item->name,
                'price' => (float) $item->price,
                'image' => $item->image,
                'status' => $item->status,
                'is_available' => $item->isAvailable(),
                'sort_order' => $item->sort_order,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateMenuItemRequest $request, string $cafe_slug, int|string $item_id): JsonResponse
    {
        Gate::authorize('permission', 'menu.update');

        $item = MenuItem::findOrFail($item_id);
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            if ($item->image) {
                $oldPath = str_replace('/storage/', '', $item->image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('menu-items', 'public');
            $validated['image'] = Storage::url($path);
        }

        $item->update($validated);
        $item->load('category');

        return response()->json([
            'message' => 'Menu item updated successfully.',
            'menu_item' => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'name' => $item->name,
                'price' => (float) $item->price,
                'image' => $item->image,
                'status' => $item->status,
                'is_available' => $item->isAvailable(),
                'sort_order' => $item->sort_order,
            ],
        ]);
    }

    public function toggleAvailability(string $cafe_slug, int|string $item_id): JsonResponse
    {
        Gate::authorize('permission', 'menu.update');

        $item = MenuItem::findOrFail($item_id);
        $newStatus = $item->isAvailable() ? 'unavailable' : 'active';
        $item->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Menu item availability toggled.',
            'status' => $item->status,
            'is_available' => $item->isAvailable(),
        ]);
    }

    public function destroy(string $cafe_slug, int|string $item_id): JsonResponse
    {
        Gate::authorize('permission', 'menu.delete');

        $item = MenuItem::findOrFail($item_id);
        $item->delete();

        return response()->json([
            'message' => 'Menu item deleted successfully.',
        ]);
    }
}
