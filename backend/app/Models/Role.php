<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'name',
        'slug',
        'scope',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function isPlatformRole(): bool
    {
        return $this->scope === 'platform' && $this->cafe_id === null;
    }

    public function isTenantRole(): bool
    {
        return $this->scope === 'tenant' && $this->cafe_id !== null;
    }
}
