<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted chat entry for global chat or whispers.
 */
class GameMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_character_id',
        'recipient_character_id',
        'channel',
        'body',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'sender_character_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'recipient_character_id');
    }
}
