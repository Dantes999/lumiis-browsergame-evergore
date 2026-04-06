<?php

namespace Tests\Unit;

use App\Models\LocationArea;
use PHPUnit\Framework\TestCase;

class LocationAreaTest extends TestCase
{
    public function test_encounter_helpers_resolve_names_and_loot_tables(): void
    {
        $area = new LocationArea([
            'area_kind' => 'hunting',
            'monster_name' => 'Wild Dog Pack',
            'lumiis_name' => 'Weathered Lumiis',
            'boss_name' => 'Alpha Wild Dog',
            'monster_loot_table' => [['slug' => 'wolf-pelt']],
            'lumiis_loot_table' => [['slug' => 'lumiis-shard']],
            'boss_loot_table' => [['slug' => 'recruit-blade']],
        ]);

        $this->assertTrue($area->isHunting());
        $this->assertFalse($area->isService());
        $this->assertSame('Wild Dog Pack', $area->encounterName('monster'));
        $this->assertSame('Weathered Lumiis', $area->encounterName('lumiis'));
        $this->assertSame('Alpha Wild Dog', $area->encounterName('boss'));
        $this->assertSame([['slug' => 'wolf-pelt']], $area->lootTable('monster'));
        $this->assertSame([['slug' => 'lumiis-shard']], $area->lootTable('lumiis'));
        $this->assertSame([['slug' => 'recruit-blade']], $area->lootTable('boss'));
    }

    public function test_boss_readiness_percent_is_zero_for_service_nodes_and_caps_at_hundred(): void
    {
        $serviceArea = new LocationArea([
            'area_kind' => 'service',
            'boss_pressure_threshold' => 100,
        ]);

        $huntingArea = new LocationArea([
            'area_kind' => 'hunting',
            'boss_pressure_threshold' => 100,
        ]);

        $this->assertSame(0.0, $serviceArea->bossChancePercent(50));
        $this->assertSame(35.0, $huntingArea->bossChancePercent(35));
        $this->assertSame(100.0, $huntingArea->bossChancePercent(140));
    }
}
