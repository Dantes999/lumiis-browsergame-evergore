<?php

namespace Tests\Unit;

use App\Support\Game\DamageTypeCatalog;
use PHPUnit\Framework\TestCase;

class DamageTypeCatalogTest extends TestCase
{
    public function test_skill_groups_map_to_expected_damage_types(): void
    {
        $this->assertSame('bow', DamageTypeCatalog::skillDamageType('ninja', 'archery', 'bow', false));
        $this->assertSame('weaponary_magic', DamageTypeCatalog::skillDamageType('sura', 'weaponary-magic', 'one_hand', true));
        $this->assertSame('black_magic', DamageTypeCatalog::skillDamageType('sura', 'black-magic', 'one_hand', true));
        $this->assertSame('dragon_force', DamageTypeCatalog::skillDamageType('shaman', 'dragon', 'bell', true));
        $this->assertSame('fan', DamageTypeCatalog::skillDamageType('shaman', 'healing', 'fan', true));
    }

    public function test_lumiis_profiles_are_weak_to_dragon_force_and_resist_bows(): void
    {
        $profile = DamageTypeCatalog::enemyResistanceProfile('lumiis', 'Lumiis Core');

        $this->assertGreaterThan(0, DamageTypeCatalog::resistancePercent($profile, 'bow'));
        $this->assertLessThan(0, DamageTypeCatalog::resistancePercent($profile, 'dragon_force'));
    }

    public function test_resistance_math_reduces_and_amplifies_damage(): void
    {
        $this->assertSame(80, DamageTypeCatalog::damageAfterResistance(100, 'bow', ['bow' => 20]));
        $this->assertSame(120, DamageTypeCatalog::damageAfterResistance(100, 'dragon_force', ['dragon_force' => -20]));
        $this->assertSame(0, DamageTypeCatalog::damageAfterResistance(0, 'physical', ['physical' => 20]));
    }
}
