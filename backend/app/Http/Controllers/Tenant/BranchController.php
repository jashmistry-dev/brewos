<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreBranchRequest;
use App\Http\Requests\Tenant\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Cafe;
use App\Services\EntitlementService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    public function index(): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'branch.view');

        $branches = Branch::all();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'branches' => $branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'status' => $b->status,
                ]),
            ]);
        }

        return Inertia::render('Tenant/Branches', [
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'address' => $b->address,
                'phone' => $b->phone,
                'status' => $b->status,
            ]),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'branch.create');

        $cafeId = app(TenantContext::class)->getCafeId();

        $this->entitlementService->checkBranchLimit($cafeId);

        $branch = DB::transaction(function () use ($request, $cafeId) {
            // Lock cafe record to prevent race conditions during concurrent limit checks
            Cafe::where('id', $cafeId)->lockForUpdate()->first();

            return Branch::create([
                'name'    => $request->validated('name'),
                'slug'    => $request->validated('slug'),
                'address' => $request->validated('address'),
                'phone'   => $request->validated('phone'),
                'status'  => $request->validated('status', 'active'),
            ]);
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Branch created successfully.',
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'status' => $branch->status,
                ],
            ], Response::HTTP_CREATED);
        }

        return redirect()->back()->with('success', 'Branch created successfully.');
    }

    public function update(UpdateBranchRequest $request, string $cafe_slug, int|string $branch_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'branch.update');

        $branch = Branch::findOrFail($branch_id);
        $branch->update($request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Branch updated successfully.',
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'status' => $branch->status,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Branch updated successfully.');
    }
}
