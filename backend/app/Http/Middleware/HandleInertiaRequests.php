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

        if (! $cafe && $request->route()) {
            $slug = $request->route('cafe_slug') ?? ($request->segment(1) === 'cafes' ? $request->segment(2) : null);
            if ($slug) {
                $cafe = \App\Models\Cafe::where('slug', $slug)->first();
            }
        }

        $roles = [];
        $permissions = [];

        if ($user && $cafe) {
            $membership = CafeUser::where('user_id', $user->id)
                ->where('cafe_id', $cafe->id)
                ->with('role.permissions')
                ->first();

            if ($membership && $membership->role) {
                $roles[] = $membership->role->slug;
                $permissions = $membership->role->permissions->pluck('slug')->toArray();
            }

            if ($user->isSuperAdmin()) {
                if (! in_array('cafe-owner', $roles)) {
                    $roles[] = 'cafe-owner';
                }
                if (! in_array('super-admin', $roles)) {
                    $roles[] = 'super-admin';
                }
                $allPerms = \App\Models\Permission::pluck('slug')->toArray();
                $permissions = array_values(array_unique(array_merge($permissions, $allPerms)));
            } elseif (in_array('cafe-owner', $roles) || ($membership && $membership->role && $membership->role->slug === 'cafe-owner')) {
                $ownerPerms = \App\Services\DefaultTenantRolesService::$defaultRolesWithPermissions['cafe-owner']['permissions'] ?? [];
                $permissions = array_values(array_unique(array_merge($permissions, $ownerPerms)));
            }
        }

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user ? [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'is_super_admin' => $user->isSuperAdmin(),
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
