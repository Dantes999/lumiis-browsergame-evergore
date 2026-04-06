<?php

namespace App\Models;

use App\Support\Game\ItemBonusCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rolled bonus attached to one owned equipment copy.
 *
 * Static item content still lives in the base `items` table. This model only stores the
 * individualized bonus lines that make one dropped sword different from the next one.
 */
class CharacterItemBonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_item_id',
        'slot_number',
        'bonus_type',
        'bonus_value',
    ];

    protected function casts(): array
    {
        return [
            'slot_number' => 'integer',
            'bonus_value' => 'integer',
        ];
    }

    public function characterItem(): BelongsTo
    {
        return $this->belongsTo(CharacterItem::class);
    }

    /**
     * Shared catalog formatting keeps Blade simple and guarantees identical wording everywhere.
     */
    public function displayLine(): string
    {
        return ItemBonusCatalog::displayLine($this->bonus_type, $this->bonus_value);
    }
}
