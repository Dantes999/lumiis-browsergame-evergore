<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top-level world map node such as Yongan or another travel destination.
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'region',
        'description',
        'threat_level',
        'is_safe_zone',
    ];

    protected function casts(): array
    {
        return [
            'threat_level' => 'integer',
            'is_safe_zone' => 'boolean',
        ];
    }

    public function characters(): HasMany
    {
        return $this->hasMany(GameCharacter::class);
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(LocationConnection::class, 'from_location_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(LocationConnection::class, 'to_location_id');
    }

    public function areas(): HasMany
    {
        // Area order controls both hotspot rendering and detail panel ordering.
        return $this->hasMany(LocationArea::class)->orderBy('sort_order');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
