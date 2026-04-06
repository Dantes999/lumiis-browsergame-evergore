<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Configurable smith upgrade rates and Yang costs per upgrade step.
 *
 * Keeping these rows in the database lets admins rebalance the refinement loop
 * without shipping code changes whenever success rates should be tuned.
 */
class SmithRefineRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'upgrade_level',
        'success_percent',
        'yang_cost',
        'lumiis_shard_cost',
        'destroy_on_fail',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'destroy_on_fail' => 'boolean',
        ];
    }

    public static function cachedMap(): Collection
    {
        // Refine rates are tiny, and live reads are safer than a static runtime cache because
        // tests, seeders and admin edits can update them mid-process. A fresh query keeps smithing
        // behavior aligned with the real database state.
        return self::query()
            ->orderBy('sort_order')
            ->orderBy('upgrade_level')
            ->get()
            ->keyBy('upgrade_level');
    }

    public static function forUpgradeLevel(int $upgradeLevel): ?self
    {
        /** @var self|null $rate */
        $rate = self::cachedMap()->get($upgradeLevel);

        return $rate;
    }
}
