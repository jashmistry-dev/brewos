<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            $tenantContext = app(TenantContext::class);

            if ($user->isSuperAdmin() && ! $tenantContext->hasTenant()) {
                return true;
            }

            return null;
        });

        Gate::define('permission', function (User $user, string $permissionSlug) {
            return $user->hasPermissionTo($permissionSlug);
        });
    }
}
