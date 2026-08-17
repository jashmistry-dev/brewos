<?php

namespace App\Http\Middleware;

use App\Models\Cafe;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cafeSlug = $request->route('cafe_slug');

        if (! $cafeSlug) {
            abort(Response::HTTP_BAD_REQUEST, 'Tenant identifier missing in route.');
        }

        $cafe = Cafe::where('slug', $cafeSlug)->first();

        if (! $cafe) {
            abort(Response::HTTP_NOT_FOUND, 'Cafe workspace not found.');
        }

        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $membership = $user->cafeUsers()
            ->where('cafe_id', $cafe->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied to this cafe tenant.');
        }

        $this->tenantContext->setCafe($cafe);

        if ($membership->branch_id) {
            $branch = $cafe->branches()->find($membership->branch_id);
            if ($branch) {
                $this->tenantContext->setBranch($branch);
            }
        }

        return $next($request);
    }
}
