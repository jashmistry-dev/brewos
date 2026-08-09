<?php

namespace App\Http\Middleware;

use App\Models\CafeUser;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;
        $cafe = $tenantContext?->getCafe();

        $roles = [];
        $permissions = [];

        if ($user && $cafe) {
            $membership = CafeUser::where('user_id', $user->id)
                ->where('cafe_id', $cafe->id)
                ->with('role.permissions')
                ->first();

            if ($membership && $membership->role) {
                $roles[] = $membership->role->slug;
                $permissions = $membership->role->permissions->pluck('name')->toArray();
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'is_super_admin' => (bool) $user->is_super_admin,
                ] : null,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
            'tenant' => [
                'cafe' => $cafe ? [
                    'id'       => $cafe->id,
                    'name'     => $cafe->name,
                    'slug'     => $cafe->slug,
                    'timezone' => $cafe->timezone,
                    'logo_url' => $cafe->logo_url,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
