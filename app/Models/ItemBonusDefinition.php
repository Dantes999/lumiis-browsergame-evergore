<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Editable admin-backed definition for one rollable item bonus type.
 *
 * Static fallback defaults still exist in the catalog, but once these rows
 * are seeded the game reads from here so balancing no longer requires code edits.
 */
class ItemBonusDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonus_type',
        'label',
        'minimum_value',
        'maximum_value',
        'suffix',
        'allowed_slots',
        'enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allowed_slots' => 'array',
            'enabled' => 'boolean',
            'minimum_value' => 'integer',
            'maximum_value' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    private static ?Collection $cachedDefinitions = null;

    public static function cachedMap(bool $includeDisabled = true): Collection
    {
        if (self::$cachedDefinitions === null) {
            self::$cachedDefinitions = self::query()
                ->orderBy('sort_order')
                ->orderBy('bonus_type')
                ->get()
                ->keyBy('bonus_type');
        }

        if ($includeDisabled) {
            return self::$cachedDefinitions;
        }

        return self::$cachedDefinitions
            ->filter(fn (self $definition): bool => $definition->enabled)
            ->values()
            ->keyBy('bonus_type');
    }

    public static function flushRuntimeCache(): void
    {
        self::$cachedDefinitions = null;
    }
}
