<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cafe extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The slug reserved for the platform-level sentinel Cafe.
     * This row exists solely as the non-nullable FK anchor for the
     * Super Admin CafeUser record required by User::isSuperAdmin().
     * It is excluded from all Eloquent queries via the global scope below.
     */
    public const PLATFORM_SENTINEL_SLUG = 'brewos-platform';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Keeps internal Super Admin notes completely hidden from default tenant JSON responses.
     */
    protected $hidden = [
        'notes',
    ];

    /**
     * Boot the model and register the global scope that transparently hides
     * the platform sentinel from every Eloquent query on this model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('excludePlatformSentinel', function (Builder $builder) {
            $builder->where('slug', '!=', self::PLATFORM_SENTINEL_SLUG);
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function cafeUsers(): HasMany
    {
        return $this->hasMany(CafeUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cafe_users')
                    ->withPivot(['role_id', 'branch_id', 'status'])
                    ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
