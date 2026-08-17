<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterCafeRequest;
use App\Models\Branch;
use App\Models\Cafe;
use App\Models\CafeUser;
use App\Models\Plan;
use App\Models\Subscription;
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

    public function create(): InertiaResponse|RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            if ($user->isSuperAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            $membership = CafeUser::where('user_id', $user->id)->with('cafe')->first();

            if ($membership && $membership->cafe) {
                return redirect()->route('tenant.dashboard', ['cafe_slug' => $membership->cafe->slug]);
            }
        }

        return Inertia::render('Auth/RegisterCafe');
    }

    public function store(RegisterCafeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            $ownerEmail = $validated['owner_email'];
            $cafeEmail  = $validated['email'] ?? $ownerEmail;

            $cafe = Cafe::create([
                'name'   => $validated['name'],
                'slug'   => $validated['slug'],
                'email'  => $cafeEmail,
                'phone'  => $validated['phone'] ?? null,
                'status' => 'active',
            ]);

            $branch = Branch::create([
                'cafe_id' => $cafe->id,
                'name'    => 'Main Branch',
                'slug'    => 'main',
                'status'  => 'active',
            ]);

            $roles = $this->rolesService->createDefaultRolesForCafe($cafe);
            $ownerRole = $roles['cafe-owner'];

            $user = User::create([
                'name'     => $validated['owner_name'],
                'email'    => $ownerEmail,
                'password' => Hash::make($validated['password']),
                'status'   => 'active',
            ]);

            $membership = CafeUser::create([
                'cafe_id'   => $cafe->id,
                'user_id'   => $user->id,
                'role_id'   => $ownerRole->id,
                'branch_id' => $branch->id,
                'status'    => 'active',
            ]);

            // Auto-assign 14-day Free Trial Subscription
            $trialPlan = Plan::where('slug', 'starter')
                ->orWhere('slug', 'free-trial')
                ->where('status', 'active')
                ->first() ?? Plan::where('status', 'active')->first();

            $subscription = null;
            if ($trialPlan) {
                $subscription = Subscription::create([
                    'cafe_id'                  => $cafe->id,
                    'plan_id'                  => $trialPlan->id,
                    'status'                   => 'trialing',
                    'starts_at'                => now(),
                    'trial_ends_at'            => now()->addDays(14),
                    'ends_at'                  => now()->addDays(14),
                    'provider'                 => 'system',
                    'provider_subscription_id' => 'trial_' . $cafe->slug . '_' . time(),
                ]);
            }

            return [
                'cafe'         => $cafe,
                'branch'       => $branch,
                'user'         => $user,
                'membership'   => $membership,
                'subscription' => $subscription,
            ];
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cafe registered successfully with 14-day Free Trial.',
                'cafe'    => [
                    'id'   => $result['cafe']->id,
                    'name' => $result['cafe']->name,
                    'slug' => $result['cafe']->slug,
                ],
                'owner'   => [
                    'id'    => $result['user']->id,
                    'name'  => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'default_branch' => [
                    'id'   => $result['branch']->id,
                    'name' => $result['branch']->name,
                    'slug' => $result['branch']->slug,
                ],
                'subscription' => $result['subscription'] ? [
                    'id'            => $result['subscription']->id,
                    'status'        => $result['subscription']->status,
                    'trial_ends_at' => $result['subscription']->trial_ends_at?->toIso8601String(),
                ] : null,
            ], Response::HTTP_CREATED);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect()->route('tenant.dashboard', ['cafe_slug' => $result['cafe']->slug])
            ->with('success', 'Cafe registered successfully! Your 14-day Free Trial is now active.');
    }
}
