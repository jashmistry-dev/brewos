<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'business_name',
        'address',
        'gst_number',
        'logo',
        'footer_text',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }
}
