<?php

namespace Tests\Feature;

use App\Models\LocationArea;
use App\Models\ShopItem;
use App\Models\SmithRefineRate;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlphaBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_alpha_general_store_prices_stay_in_a_readable_starter_band(): void
    {
        $prices = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->whereIn('slug', [
                'small-red-potion',
                'small-blue-potion',
                'medium-red-potion',
                'medium-blue-potion',
                'brown-horse-reins',
            ]))
            ->with('item')
            ->get()
            ->pluck('price', 'item.slug');

        $this->assertLessThanOrEqual(22, (int) $prices['small-red-potion']);
        $this->assertLessThanOrEqual(50, (int) $prices['small-red-potion'] * 2);
        $this->assertGreaterThan((int) $prices['small-red-potion'], (int) $prices['medium-red-potion']);
        $this->assertGreaterThan((int) $prices['small-blue-potion'], (int) $prices['medium-blue-potion']);
        $this->assertLessThanOrEqual(170, (int) $prices['brown-horse-reins']);
    }

    public function test_alpha_equipment_prices_step_up_cleanly_from_map_one_to_map_two(): void
    {
        $prices = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->whereIn('slug', [
                'recruit-blade',
                'frontier-blade',
                'valley-blade',
                'cotton-armor',
                'guard-jacket',
                'orc-mail',
            ]))
            ->with('item')
            ->get()
            ->pluck('price', 'item.slug');

        $this->assertGreaterThan((int) $prices['frontier-blade'], (int) $prices['valley-blade']);
        $this->assertGreaterThan((int) $prices['guard-jacket'], (int) $prices['orc-mail']);
        $this->assertLessThanOrEqual(180, (int) $prices['frontier-blade']);
        $this->assertLessThanOrEqual(340, (int) $prices['valley-blade']);
        $this->assertLessThanOrEqual(180, (int) $prices['guard-jacket']);
        $this->assertLessThanOrEqual(360, (int) $prices['orc-mail']);
        $this->assertGreaterThan((int) $prices['recruit-blade'], (int) $prices['cotton-armor']);
    }

    public function test_alpha_refine_curve_stays_accessible_through_the_early_weapon_band(): void
    {
        $rates = SmithRefineRate::query()
            ->orderBy('upgrade_level')
            ->get()
            ->keyBy('upgrade_level');

        $this->assertSame(25, (int) $rates[0]->yang_cost);
        $this->assertGreaterThanOrEqual(80, (int) $rates[3]->success_percent);
        $this->assertLessThanOrEqual(80, (int) $rates[3]->yang_cost);
        $this->assertGreaterThanOrEqual(70, (int) $rates[4]->success_percent);
        $this->assertLessThanOrEqual(120, (int) $rates[4]->yang_cost);
        $this->assertSame(1, (int) $rates[3]->lumiis_shard_cost);
        $this->assertSame(1, (int) $rates[4]->lumiis_shard_cost);
        $this->assertSame(2, (int) $rates[6]->lumiis_shard_cost);
    }

    public function test_alpha_map_two_and_dungeon_damage_bands_stay_inside_the_target_window(): void
    {
        $orcApproach = LocationArea::query()->where('slug', 'orc-approach')->firstOrFail();
        $stoneShelf = LocationArea::query()->where('slug', 'stone-shelf')->firstOrFail();
        $lowerHalls = LocationArea::query()->where('slug', 'lower-halls')->firstOrFail();
        $apeThrone = LocationArea::query()->where('slug', 'ape-throne')->firstOrFail();

        $this->assertLessThanOrEqual(18, (int) $orcApproach->monster_damage_max);
        $this->assertLessThanOrEqual(42, (int) $orcApproach->monster_cooldown_seconds);
        $this->assertLessThanOrEqual(23, (int) $stoneShelf->monster_damage_max);
        $this->assertLessThanOrEqual(48, (int) $stoneShelf->monster_cooldown_seconds);
        $this->assertLessThanOrEqual(26, (int) $lowerHalls->monster_damage_max);
        $this->assertLessThanOrEqual(50, (int) $lowerHalls->monster_cooldown_seconds);
        $this->assertLessThanOrEqual(30, (int) $apeThrone->monster_damage_max);
        $this->assertLessThanOrEqual(46, (int) $apeThrone->boss_damage_max);
        $this->assertLessThanOrEqual(56, (int) $apeThrone->monster_cooldown_seconds);
    }
}
