<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipment slot binding between a character and one inventory item.
 */
class CharacterEquipment extends Model
{
    use HasFactory;

    protected $table = 'character_equipment';

    protected $fillable = [
        'game_character_id',
        'character_item_id',
        'slot',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function characterItem(): BelongsTo
    {
        return $this->belongsTo(CharacterItem::class);
    }
}
