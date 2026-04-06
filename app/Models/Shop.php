<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop metadata for a safe-zone location.
 */
class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'location_area_id',
        'slug',
        'name',
        'description',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function locationArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class);
    }

    public function shopItems(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }
}
