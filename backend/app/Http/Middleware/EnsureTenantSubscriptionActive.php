<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected EntitlementService $entitlementService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super Admin bypasses subscription restriction for administrative operations
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        $cafe = $this->tenantContext->getCafe();

        if (! $cafe) {
            return $next($request);
        }

        // Allow access to subscription management endpoints so tenant can upgrade/renew
        if ($request->is('cafes/*/subscription*')) {
            return $next($request);
        }

        if (! $this->entitlementService->isSubscriptionValid($cafe->id)) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message'    => 'Your subscription has expired or is inactive. Please renew or upgrade your plan to continue accessing operational features.',
                    'error_code' => 'SUBSCRIPTION_EXPIRED',
                    'status'     => 'expired',
                ], Response::HTTP_FORBIDDEN);
            }

            abort(Response::HTTP_FORBIDDEN, 'Subscription expired or inactive. Please renew your subscription.');
        }

        return $next($request);
    }
}
