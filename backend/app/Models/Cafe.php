<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cafe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'status',
    ];

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
}
