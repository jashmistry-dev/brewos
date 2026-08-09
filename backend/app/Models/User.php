<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cafeUsers(): HasMany
    {
        return $this->hasMany(CafeUser::class);
    }

    public function cafes(): BelongsToMany
    {
        return $this->belongsToMany(Cafe::class, 'cafe_users')
                    ->withPivot(['role_id', 'branch_id', 'status'])
                    ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->cafeUsers()
            ->whereHas('role', function ($query) {
                $query->where('scope', 'platform')
                      ->where('slug', 'super-admin');
            })
            ->exists();
    }

    public function hasPermissionTo(string $permissionSlug, ?int $cafeId = null): bool
    {
        $targetCafeId = $cafeId ?? app(TenantContext::class)->getCafeId();

        if ($this->isSuperAdmin() && $targetCafeId === null) {
            return true;
        }

        if (! $targetCafeId) {
            return false;
        }

        $membership = $this->cafeUsers()
            ->where('cafe_id', $targetCafeId)
            ->where('status', 'active')
            ->with(['role.permissions'])
            ->first();

        if (! $membership || ! $membership->role) {
            return false;
        }

        return $membership->role->permissions->contains('slug', $permissionSlug);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
