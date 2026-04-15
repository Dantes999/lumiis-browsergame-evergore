<?php

namespace Tests\Unit;

use App\Support\Game\WeaponCombatProfileCatalog;
use Tests\TestCase;

class WeaponCombatProfileCatalogTest extends TestCase
{
    public function test_one_hand_range_is_tighter_while_two_hand_keeps_the_widest_burst_window(): void
    {
        $oneHand = WeaponCombatProfileCatalog::attackRollBounds('one_hand', 20, 10);
        $twoHand = WeaponCombatProfileCatalog::attackRollBounds('two_hand', 20, 10);

        $this->assertSame(['minimum' => 21, 'maximum' => 28], $oneHand);
        $this->assertSame(['minimum' => 18, 'maximum' => 33], $twoHand);
    }

    public function test_dagger_and_bow_receive_their_signature_aggression_bonuses(): void
    {
        $this->assertSame(5, WeaponCombatProfileCatalog::critChanceBonus('dagger'));
        $this->assertSame(10, WeaponCombatProfileCatalog::damagePercentBonus('bow', false, 1));
        $this->assertSame(0, WeaponCombatProfileCatalog::damagePercentBonus('bow', false, 2));
    }

    public function test_bell_and_fan_split_between_guard_and_skill_scaling(): void
    {
        $this->assertSame(2, WeaponCombatProfileCatalog::guardBonus('bell'));
        $this->assertSame(10, WeaponCombatProfileCatalog::damagePercentBonus('fan', true, 2));
        $this->assertSame(0, WeaponCombatProfileCatalog::damagePercentBonus('fan', false, 2));
    }
}
