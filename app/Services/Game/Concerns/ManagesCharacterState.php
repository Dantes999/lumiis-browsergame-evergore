<?php

namespace App\Services\Game\Concerns;

use App\Models\GameCharacter;
use App\Support\Game\ExperienceCurve;
use Illuminate\Support\Carbon;

/**
 * Shared character post-processing helpers such as level-ups, caps and passive recovery.
 */
trait ManagesCharacterState
{
    /**
     * Applies all derived level-up changes in one place.
     * This keeps progression rules consistent regardless of which action granted the EXP.
     */
    private function applyLevelProgression(GameCharacter $character): void
    {
        while ($character->experience >= $this->requiredExperienceForLevel($character->level + 1)) {
            $character->level++;
            $character->max_health += 12;
            $character->max_sp += 4;
            $character->max_stamina += 1;
            $character->stamina = $character->level <= 10
                ? $character->effectiveMaxStamina()
                : min($character->max_stamina, $character->stamina + 1);
            $character->attack += 1;
            $character->defense += 1;

            if ($character->level % 2 === 0) {
                $character->speed += 1;
            }

            $character->unspent_status_points += 1;

            if ($character->level >= 5) {
                $character->unspent_skill_points += 1;
            }

            // A level-up is a full recovery moment for the player.
            // We heal to the effective cap, not just the raw base stat, so equipped health bonuses
            // are respected as well. SP follows the same rule so newly unlocked skills are usable
            // immediately after the level-up moment.
            $character->health = $character->effectiveMaxHealth();
            $character->sp = $character->effectiveMaxSp();
        }
    }

    /**
     * EXP thresholds stay intentionally fast in the first three levels, then
     * ramp upward so midgame fights no longer award almost a full level each time.
     */
    private function requiredExperienceForLevel(int $level): int
    {
        return ExperienceCurve::requiredForLevel($level);
    }

    /**
     * Final safety net after action resolution.
     * Equipment or consumables can change effective caps, so we clamp values before saving.
     */
    private function syncResourceCaps(GameCharacter $character): void
    {
        $character->health = min($character->health, $character->effectiveMaxHealth());
        $character->stamina = min($character->stamina, $character->effectiveMaxStamina());
        $character->sp = min($character->sp, $character->effectiveMaxSp());
    }

    /**
     * HP, SP and stamina all regenerate outside active combat.
     * We apply the preview state up front before validating a new action so every handler sees
     * the same up-to-date character snapshot.
     */
    private function syncPassiveRecovery(GameCharacter $character, Carbon $processedAt): void
    {
        $staminaRecoveryState = $character->staminaRecoveryState($processedAt);
        $healthRecoveryState = $character->healthRecoveryState($processedAt);
        $spRecoveryState = $character->spRecoveryState($processedAt);

        $character->stamina = $staminaRecoveryState['stamina'];
        $character->stamina_updated_at = $staminaRecoveryState['applied_at'];
        $character->health = $healthRecoveryState['health'];
        $character->health_updated_at = $healthRecoveryState['applied_at'];
        $character->sp = $spRecoveryState['sp'];
        $character->sp_updated_at = $spRecoveryState['applied_at'];
    }

    /**
     * Active actions should restart passive recovery from "now".
     * That keeps natural regeneration from stacking across fights, travel and consumable use.
     */
    private function resetPassiveRecoveryClocks(GameCharacter $character, Carbon $processedAt): void
    {
        $character->stamina_updated_at = $processedAt->copy();
        $character->health_updated_at = $processedAt->copy();
        $character->sp_updated_at = $processedAt->copy();
    }

    /**
     * Several actions mutate inventory, equipment or learned skills.
     * Reloading those relations keeps the in-memory character model trustworthy for follow-up logic.
     */
    private function reloadDerivedRelations(GameCharacter $character): void
    {
        $character->load([
            'inventoryItems.item',
            'inventoryItems.bonuses',
            'inventoryItems.equipment',
            'equipment.characterItem.item',
            'equipment.characterItem.bonuses',
            'learnedSkills.skill',
        ]);
    }
}
