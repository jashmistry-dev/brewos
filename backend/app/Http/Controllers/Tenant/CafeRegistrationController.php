<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterCafeRequest;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\User;
use App\Services\DefaultTenantRolesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CafeRegistrationController extends Controller
{
    public function __construct(
        protected DefaultTenantRolesService $rolesService
    ) {}

    public function create(): InertiaResponse
    {
        return Inertia::render('Auth/RegisterCafe');
    }

    public function store(RegisterCafeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            $cafe = Cafe::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'status' => 'active',
            ]);

            $branch = Branch::create([
                'cafe_id' => $cafe->id,
                'name' => 'Main Branch',
                'slug' => 'main',
                'status' => 'active',
            ]);

            $roles = $this->rolesService->createDefaultRolesForCafe($cafe);
            $ownerRole = $roles['cafe-owner'];

            $user = User::create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);

            $membership = CafeUser::create([
                'cafe_id' => $cafe->id,
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'branch_id' => $branch->id,
                'status' => 'active',
            ]);

            return [
                'cafe' => $cafe,
                'branch' => $branch,
                'user' => $user,
                'membership' => $membership,
            ];
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe registered successfully.',
                'cafe' => [
                    'id' => $result['cafe']->id,
                    'name' => $result['cafe']->name,
                    'slug' => $result['cafe']->slug,
                ],
                'owner' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'default_branch' => [
                    'id' => $result['branch']->id,
                    'name' => $result['branch']->name,
                    'slug' => $result['branch']->slug,
                ],
            ], Response::HTTP_CREATED);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect()->route('tenant.dashboard', ['cafe_slug' => $result['cafe']->slug])
            ->with('success', 'Cafe registered successfully. Welcome to BrewOS!');
    }
}
