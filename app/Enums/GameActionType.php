<?php

namespace App\Enums;

/**
 * Canonical list of every player action accepted by the game action endpoint.
 */
enum GameActionType: string
{
    case Hunt = 'hunt';
    case FightMonsters = 'fight-monsters';
    case SearchLumiis = 'search-lumiis';
    case StartManualFight = 'start-manual-fight';
    case BattleAttack = 'battle-attack';
    case BattleUseSkill = 'battle-use-skill';
    case AllocateStatusPoint = 'allocate-status-point';
    case ClaimQuest = 'claim-quest';
    case Rest = 'rest';
    case Travel = 'travel';
    case SubmitQuestItem = 'submit-quest-item';
    case BoostResearchSubmission = 'boost-research-submission';
    case SkipResearchCooldown = 'skip-research-cooldown';
    case BuyItem = 'buy-item';
    case SellItem = 'sell-item';
    case UpgradeItem = 'upgrade-item';
    case UseItem = 'use-item';
    case EquipItem = 'equip-item';
    case UnequipItem = 'unequip-item';
    case MoveInventoryItem = 'move-inventory-item';
    case AddItemBonus = 'add-item-bonus';
    case RerollItemBonuses = 'reroll-item-bonuses';
    case UnlockItemBonus = 'unlock-item-bonus';
    case ChooseSkillGroup = 'choose-skill-group';
    case LearnSkill = 'learn-skill';
    case AdvanceSkillMastery = 'advance-skill-mastery';
    case GlobalChat = 'global-chat';
    case Whisper = 'whisper';
    case TutorialNext = 'tutorial-next';
    case TutorialPrevious = 'tutorial-previous';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Hunt => 'Hunt',
            self::FightMonsters => 'Fight Monsters',
            self::SearchLumiis => 'Search Lumiis',
            self::StartManualFight => 'Start Manual Fight',
            self::BattleAttack => 'Battle Attack',
            self::BattleUseSkill => 'Battle Use Skill',
            self::AllocateStatusPoint => 'Allocate Status Point',
            self::ClaimQuest => 'Claim Quest',
            self::Rest => 'Rest',
            self::Travel => 'Travel',
            self::SubmitQuestItem => 'Submit Quest Item',
            self::BoostResearchSubmission => 'Boost Research Submission',
            self::SkipResearchCooldown => 'Skip Research Cooldown',
            self::BuyItem => 'Buy Item',
            self::SellItem => 'Sell Item',
            self::UpgradeItem => 'Upgrade Item',
            self::UseItem => 'Use Item',
            self::EquipItem => 'Equip Item',
            self::UnequipItem => 'Unequip Item',
            self::MoveInventoryItem => 'Move Inventory Item',
            self::AddItemBonus => 'Add Item Bonus',
            self::RerollItemBonuses => 'Reroll Item Bonuses',
            self::UnlockItemBonus => 'Unlock Item Bonus',
            self::ChooseSkillGroup => 'Choose Skill Group',
            self::LearnSkill => 'Learn Skill',
            self::AdvanceSkillMastery => 'Advance Skill Mastery',
            self::GlobalChat => 'Global Chat',
            self::Whisper => 'Whisper',
            self::TutorialNext => 'Tutorial Next',
            self::TutorialPrevious => 'Tutorial Previous',
        };
    }

    public function usesCharacterCooldown(): bool
    {
        // Only actions that represent meaningful time commitment should lock the character.
        return match ($this) {
            self::Hunt,
            self::FightMonsters,
            self::SearchLumiis,
            self::StartManualFight,
            self::Rest,
            self::Travel => true,
            default => false,
        };
    }

    public function isChatAction(): bool
    {
        return match ($this) {
            self::GlobalChat,
            self::Whisper => true,
            default => false,
        };
    }

    public function isBattleTurnAction(): bool
    {
        // Manual battle turns are special because they are allowed during an active battle state.
        return match ($this) {
            self::BattleAttack,
            self::BattleUseSkill => true,
            default => false,
        };
    }
}
