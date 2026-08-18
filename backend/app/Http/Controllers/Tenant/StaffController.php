<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStaffRequest;
use App\Http\Requests\Tenant\UpdateStaffRequest;
use App\Models\Branch;
use App\Models\CafeUser;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Cafe;
use App\Services\EntitlementService;

class StaffController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    public function index(): JsonResponse|InertiaResponse
    {
        Gate::authorize('permission', 'staff.view');

        $cafeId = app(TenantContext::class)->getCafeId();

        $staffMembers = CafeUser::where('cafe_id', $cafeId)
            ->with(['user', 'role', 'branch'])
            ->get();

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'staff' => $staffMembers->map(fn ($cu) => [
                    'id' => $cu->id,
                    'user_id' => $cu->user_id,
                    'name' => $cu->user?->name,
                    'email' => $cu->user?->email,
                    'role' => [
                        'id' => $cu->role?->id,
                        'name' => $cu->role?->name,
                        'slug' => $cu->role?->slug,
                    ],
                    'branch' => $cu->branch ? [
                        'id' => $cu->branch->id,
                        'name' => $cu->branch->name,
                        'slug' => $cu->branch->slug,
                    ] : null,
                    'status' => $cu->status,
                ]),
            ]);
        }

        $roles = Role::where(function ($q) use ($cafeId) {
            $q->where('cafe_id', $cafeId)
              ->orWhere(fn ($q2) => $q2->whereNull('cafe_id')->where('scope', 'platform')->where('slug', '!=', 'super-admin'));
        })->get();

        $branches = Branch::where('cafe_id', $cafeId)->get();

        return Inertia::render('Tenant/Staff', [
            'staff' => $staffMembers->map(fn ($cu) => [
                'id' => $cu->id,
                'user_id' => $cu->user_id,
                'name' => $cu->user?->name,
                'email' => $cu->user?->email,
                'role_id' => $cu->role_id,
                'role' => [
                    'id' => $cu->role?->id,
                    'name' => $cu->role?->name,
                    'slug' => $cu->role?->slug,
                ],
                'branch_id' => $cu->branch_id,
                'branch' => $cu->branch ? [
                    'id' => $cu->branch->id,
                    'name' => $cu->branch->name,
                    'slug' => $cu->branch->slug,
                ] : null,
                'status' => $cu->status,
            ]),
            'roles' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
            'branches' => $branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug]),
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'staff.create');

        $cafeId = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        $role = Role::find($validated['role_id'] ?? null);
        if ($role && ($role->slug === 'super-admin' || $role->name === 'Super Admin')) {
            return response()->json([
                'message' => 'Super Admin role cannot be assigned to tenant staff.',
                'errors'  => ['role_id' => ['Super Admin role cannot be assigned to tenant staff.']],
            ], 422);
        }

        $this->entitlementService->checkStaffLimit($cafeId);

        $membership = DB::transaction(function () use ($validated, $cafeId) {
            // Lock cafe record to prevent race conditions during limit validation
            Cafe::where('id', $cafeId)->lockForUpdate()->first();

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password']),
                    'status' => 'active',
                ]
            );

            return CafeUser::create([
                'cafe_id' => $cafeId,
                'user_id' => $user->id,
                'role_id' => $validated['role_id'],
                'branch_id' => $validated['branch_id'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        $membership->load(['user', 'role', 'branch']);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Staff member created successfully.',
                'staff' => [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'role_id' => $membership->role_id,
                    'branch_id' => $membership->branch_id,
                    'status' => $membership->status,
                ],
            ], Response::HTTP_CREATED);
        }

        return redirect()->back()->with('success', 'Staff member created successfully.');
    }

    public function update(UpdateStaffRequest $request, string $cafe_slug, int|string $staff_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'staff.update');

        $cafeId = app(TenantContext::class)->getCafeId();

        $membership = CafeUser::where('cafe_id', $cafeId)
            ->where('id', $staff_id)
            ->firstOrFail();

        $validated = $request->validated();

        $role = Role::find($validated['role_id'] ?? null);
        if ($role && ($role->slug === 'super-admin' || $role->name === 'Super Admin')) {
            return response()->json([
                'message' => 'Super Admin role cannot be assigned to tenant staff.',
                'errors'  => ['role_id' => ['Super Admin role cannot be assigned to tenant staff.']],
            ], 422);
        }

        DB::transaction(function () use ($membership, $validated) {
            if (isset($validated['name']) || isset($validated['email'])) {
                $userData = array_filter([
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                ]);
                $membership->user->update($userData);
            }

            $membershipData = array_filter([
                'role_id' => $validated['role_id'] ?? null,
                'branch_id' => array_key_exists('branch_id', $validated) ? $validated['branch_id'] : null,
                'status' => $validated['status'] ?? null,
            ], fn ($v) => $v !== null || array_key_exists('branch_id', $validated));

            $membership->update($membershipData);
        });

        $membership->load(['user', 'role', 'branch']);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Staff member updated successfully.',
                'staff' => [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'role_id' => $membership->role_id,
                    'branch_id' => $membership->branch_id,
                    'status' => $membership->status,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Staff member updated successfully.');
    }

    public function destroy(string $cafe_slug, int|string $staff_id): JsonResponse|RedirectResponse
    {
        Gate::authorize('permission', 'staff.delete');

        $cafeId = app(TenantContext::class)->getCafeId();

        $membership = CafeUser::where('cafe_id', $cafeId)
            ->where('id', $staff_id)
            ->firstOrFail();

        $membership->update(['status' => 'inactive']);

        if (request()->wantsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'message' => 'Staff member access revoked successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Staff member access revoked successfully.');
    }
}
