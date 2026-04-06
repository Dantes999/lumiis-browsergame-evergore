<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sellable offer inside a shop, including local price and optional stock.
 */
class ShopItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'item_id',
        'price',
        'stock',
        'sort_order',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
