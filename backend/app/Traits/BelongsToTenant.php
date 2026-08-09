<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Services\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            /** @var TenantContext $tenantContext */
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->hasTenant() && empty($model->cafe_id)) {
                $model->cafe_id = $tenantContext->getCafeId();
            }
        });
    }
}
