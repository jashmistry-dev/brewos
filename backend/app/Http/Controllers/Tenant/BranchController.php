<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreBranchRequest;
use App\Http\Requests\Tenant\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('permission', 'branch.view');

        $branches = Branch::all();

        return response()->json([
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'status' => $b->status,
            ]),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'branch.create');

        $branch = Branch::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'status' => $request->validated('status', 'active'),
        ]);

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

    public function update(UpdateBranchRequest $request, string $cafe_slug, int|string $branch_id): JsonResponse
    {
        Gate::authorize('permission', 'branch.update');

        $branch = Branch::findOrFail($branch_id);
        $branch->update($request->validated());

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
}
