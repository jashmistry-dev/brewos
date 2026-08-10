<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMenuItemRequest;
use App\Http\Requests\Tenant\UpdateMenuItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Cafe;
use App\Services\EntitlementService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'menu.view');

        $query = MenuItem::with('category')->orderBy('sort_order', 'asc');

        if ($request->has('category_id') && $request->query('category_id') !== null && $request->query('category_id') !== '') {
            $query->where('category_id', $request->query('category_id'));
        }

        $items = $query->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        $categories = Category::orderBy('sort_order', 'asc')->get();

        return Inertia::render('Tenant/MenuItems', [
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
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
            'selected_category_id' => $request->query('category_id') ? (int) $request->query('category_id') : null,
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'menu.create');

        $cafeId = app(TenantContext::class)->getCafeId();

        $validated = $request->validated();
        $imagePath = null;

        $uploadDisk = config('filesystems.uploads_disk', 'public');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu-items', $uploadDisk);
            $imagePath = Storage::disk($uploadDisk)->url($path);
        }

        $this->entitlementService->checkMenuItemLimit($cafeId);

        $item = DB::transaction(function () use ($validated, $imagePath, $cafeId) {
            // Lock cafe record to prevent race conditions during limit validation
            Cafe::where('id', $cafeId)->lockForUpdate()->first();

            return MenuItem::create([
                'category_id' => $validated['category_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price'       => $validated['price'],
                'image'       => $imagePath,
                'status'      => $validated['status'] ?? 'active',
                'sort_order'  => $validated['sort_order'] ?? 0,
            ]);
        });

        $item->load('category');

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->back()->with('success', 'Menu item created successfully.');
    }

    public function update(UpdateMenuItemRequest $request, string $cafe_slug, int|string $item_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'menu.update');

        $item = MenuItem::findOrFail($item_id);
        $validated = $request->validated();
        $uploadDisk = config('filesystems.uploads_disk', 'public');

        if ($request->hasFile('image')) {
            if ($item->image) {
                $oldPath = str_replace('/storage/', '', parse_url($item->image, PHP_URL_PATH) ?? $item->image);
                Storage::disk($uploadDisk)->delete($oldPath);
            }
            $path = $request->file('image')->store('menu-items', $uploadDisk);
            $validated['image'] = Storage::disk($uploadDisk)->url($path);
        }

        $item->update($validated);
        $item->load('category');

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        return redirect()->back()->with('success', 'Menu item updated successfully.');
    }

    public function toggleAvailability(string $cafe_slug, int|string $item_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'menu.update');

        $item = MenuItem::findOrFail($item_id);
        $newStatus = $item->isAvailable() ? 'unavailable' : 'active';
        $item->update(['status' => $newStatus]);

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'message' => 'Menu item availability toggled.',
                'status' => $item->status,
                'is_available' => $item->isAvailable(),
            ]);
        }

        return redirect()->back()->with('success', 'Menu item availability toggled.');
    }

    public function destroy(string $cafe_slug, int|string $item_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'menu.delete');

        $item = MenuItem::findOrFail($item_id);
        $item->delete();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'message' => 'Menu item deleted successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Menu item deleted successfully.');
    }
}
