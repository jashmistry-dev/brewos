<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

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
        'logo_path',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_number',
        'tax_rate',
        'business_hours',
        'timezone',
        'currency',
        'status',
        'notes',
        'onboarded_at',
        'qr_ordering_enabled',
        'require_location',
        'location_radius_meters',
        'latitude',
        'longitude',
        'pay_at_counter_enabled',
        'online_payment_enabled',
        'require_payment_before_kitchen',
        'allow_customer_reorder',
        'call_staff_enabled',
        'request_bill_enabled',
    ];

    protected $casts = [
        'tax_rate'       => 'float',
        'business_hours' => 'array',
        'onboarded_at'   => 'datetime',
    ];

    protected $appends = [
        'logo_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Keeps internal Super Admin notes completely hidden from default tenant JSON responses.
     */
    protected $hidden = [
        'notes',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $diskName = config('filesystems.default', 'public');
        return Storage::disk($diskName)->url($this->logo_path);
    }

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

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
