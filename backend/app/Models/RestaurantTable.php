<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'branch_id',
        'name',
        'capacity',
        'status',
        'qr_token',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public static function generateQrToken(): string
    {
        return (string) Str::uuid();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
