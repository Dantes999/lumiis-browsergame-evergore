<?php

namespace App\Http\Requests;

use App\Enums\GameActionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Central request validation for every in-game POST action.
 *
 * The route contains the action name, so this request builds a dynamic rule set
 * depending on which action the player is trying to perform.
 */
class PerformGameActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['message', 'recipient_name', 'skill_slug', 'encounter_request', 'redirect_focus', 'skill_group', 'monster_family', 'monster_difficulty', 'attribute'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }

        if ($this->input('locked_bonus_id') === '') {
            $this->merge([
                'locked_bonus_id' => null,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The action slug in the route decides which payload fields are legal or required.
        $action = GameActionType::from((string) $this->route('action'));

        return [
            // Every action must be idempotent so repeated submissions cannot duplicate rewards.
            'idempotency_key' => ['required', 'uuid'],
            'target_location_id' => [
                Rule::requiredIf($action === GameActionType::Travel),
                'nullable',
                'integer',
                Rule::exists('locations', 'id'),
            ],
            'location_area_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::FightMonsters,
                    GameActionType::SearchLumiis,
                    GameActionType::StartManualFight,
                    GameActionType::SubmitQuestItem,
                    GameActionType::BoostResearchSubmission,
                    GameActionType::SkipResearchCooldown,
                    GameActionType::BuyItem,
                    GameActionType::SellItem,
                    GameActionType::UpgradeItem,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('location_areas', 'id'),
            ],
            'character_quest_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::SubmitQuestItem,
                    GameActionType::BoostResearchSubmission,
                    GameActionType::SkipResearchCooldown,
                    GameActionType::ClaimQuest,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('character_quests', 'id'),
            ],
            'battle_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::BattleAttack,
                    GameActionType::BattleUseSkill,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('game_battles', 'id'),
            ],
            'battle_round' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::BattleAttack,
                    GameActionType::BattleUseSkill,
                ], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'enemy_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::BattleAttack,
                    GameActionType::BattleUseSkill,
                ], true)),
                'nullable',
                'string',
                'max:80',
            ],
            'character_item_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::UpgradeItem,
                    GameActionType::UseItem,
                    GameActionType::EquipItem,
                    GameActionType::UnequipItem,
                    GameActionType::MoveInventoryItem,
                    GameActionType::SellItem,
                    GameActionType::BoostResearchSubmission,
                    GameActionType::SkipResearchCooldown,
                    GameActionType::AddItemBonus,
                    GameActionType::RerollItemBonuses,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('character_items', 'id'),
            ],
            'target_character_item_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::AddItemBonus,
                    GameActionType::RerollItemBonuses,
                    GameActionType::UnlockItemBonus,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('character_items', 'id'),
            ],
            'locked_bonus_id' => [
                'nullable',
                'integer',
                Rule::exists('character_item_bonuses', 'id'),
            ],
            'quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],
            'use_until_full' => [
                'nullable',
                'boolean',
            ],
            'inventory_column' => [
                Rule::requiredIf($action === GameActionType::MoveInventoryItem),
                'nullable',
                'integer',
                'min:0',
                'max:4',
            ],
            'inventory_row' => [
                Rule::requiredIf($action === GameActionType::MoveInventoryItem),
                'nullable',
                'integer',
                'min:0',
                'max:8',
            ],
            'shop_item_id' => [
                Rule::requiredIf($action === GameActionType::BuyItem),
                'nullable',
                'integer',
                Rule::exists('shop_items', 'id'),
            ],
            'skill_id' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::LearnSkill,
                    GameActionType::AdvanceSkillMastery,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('skills', 'id'),
            ],
            'skill_group' => [
                Rule::requiredIf($action === GameActionType::ChooseSkillGroup),
                'nullable',
                'string',
                'max:40',
                'regex:/^[a-z-]+$/',
            ],
            'skill_slug' => [
                Rule::requiredIf($action === GameActionType::BattleUseSkill),
                'nullable',
                'string',
                'max:120',
            ],
            'encounter_request' => [
                Rule::requiredIf($action === GameActionType::StartManualFight),
                'nullable',
                'string',
                Rule::in(['monster', 'lumiis', 'boss']),
            ],
            'monster_family' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[a-z-]+$/',
            ],
            'monster_difficulty' => [
                'nullable',
                'string',
                Rule::in(['easy', 'medium', 'hard']),
            ],
            'attribute' => [
                Rule::requiredIf($action === GameActionType::AllocateStatusPoint),
                'nullable',
                'string',
                Rule::in(['vitality', 'intelligence', 'strength', 'dexterity']),
            ],
            'recipient_name' => [
                Rule::requiredIf($action === GameActionType::Whisper),
                'nullable',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9 _-]+$/',
            ],
            'message' => [
                Rule::requiredIf(in_array($action, [
                    GameActionType::GlobalChat,
                    GameActionType::Whisper,
                ], true)),
                'nullable',
                'string',
                'min:1',
                'max:280',
            ],
            'rest_minutes' => [
                Rule::requiredIf($action === GameActionType::Rest),
                'nullable',
                'integer',
                Rule::in([30, 60, 120]),
            ],
            'redirect_focus' => [
                'nullable',
                'string',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
            ],
        ];
    }

    /**
     * The UI is player-facing, so the validation messages stay written like gameplay errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_location_id.required' => 'Waehle ein gueltiges Reiseziel.',
            'location_area_id.required' => 'Waehle ein gueltiges Gebiet auf der Karte.',
            'character_quest_id.required' => 'Waehle einen gueltigen Forschungsauftrag.',
            'attribute.required' => 'Waehle ein gueltiges Statusattribut.',
            'character_item_id.required' => 'Waehle ein gueltiges Inventarobjekt.',
            'target_character_item_id.required' => 'Waehle ein gueltiges Zielobjekt fuer den Bonus.',
            'inventory_column.required' => 'Waehle ein gueltiges Inventarziel.',
            'inventory_row.required' => 'Waehle ein gueltiges Inventarziel.',
            'shop_item_id.required' => 'Waehle ein gueltiges Shop-Angebot.',
            'battle_id.required' => 'Waehle einen gueltigen Kampf.',
            'battle_round.required' => 'Waehle eine gueltige Kampfrunde.',
            'enemy_id.required' => 'Waehle ein gueltiges Ziel im Kampf.',
            'skill_id.required' => 'Waehle eine gueltige Fertigkeit.',
            'skill_group.required' => 'Waehle einen gueltigen Kampfstil.',
            'skill_slug.required' => 'Waehle eine gueltige Kampffertigkeit.',
            'encounter_request.required' => 'Waehle eine gueltige Kampfart.',
            'recipient_name.required' => 'Waehle einen gueltigen Empfaenger fuer das Fluestern.',
            'message.required' => 'Nachrichten duerfen nicht leer sein.',
            'rest_minutes.required' => 'Waehle eine gueltige Ruhezeit.',
        ];
    }
}
