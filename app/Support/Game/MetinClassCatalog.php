<?php

namespace App\Support\Game;

use App\Models\CharacterSkill;
use App\Models\Skill;

/**
 * Central source of truth for playable races, their two Metin2-like combat schools and
 * the concrete skill profiles used by UI, seeding and combat resolution.
 *
 * Keeping this data in one file avoids spreading race/group names, registration options,
 * dashboard labels and battle tuning across controllers, seeders and Blade templates.
 */
class MetinClassCatalog
{
    private const SKILL_BOOK_MINIMUM_RANK = 5;

    private const SOUL_STONE_SLUG = 'soul-stone';

    public static function defaultRace(): string
    {
        return 'warrior';
    }

    public static function skillBookMinimumRank(): int
    {
        return self::SKILL_BOOK_MINIMUM_RANK;
    }

    public static function requiresSkillBookForNextRank(int $currentRank): bool
    {
        return $currentRank >= self::skillBookMinimumRank();
    }

    public static function skillBookCooldownMinutes(): int
    {
        return 30;
    }

    public static function skillBookSuccessChance(int $currentMasteryRank): int
    {
        return match (true) {
            $currentMasteryRank <= 2 => 100,
            $currentMasteryRank <= 5 => 85,
            $currentMasteryRank <= 8 => 70,
            default => 60,
        };
    }

    /**
     * Later mastery steps need repeated successful readings instead of a single lucky book.
     *
     * That is intentionally closer to Metin2's "keep reading until the next M stage sticks"
     * feeling than the original one-book-per-stage placeholder.
     */
    public static function skillBookSuccessesRequired(int $currentMasteryRank): int
    {
        return max(1, min(10, $currentMasteryRank + 1));
    }

    public static function soulStoneSlug(): string
    {
        return self::SOUL_STONE_SLUG;
    }

    /**
     * The first G breakthrough and the final P breakthrough stay deterministic so the feature
     * feels rewarding in a browser progression loop, while the middle G climb remains rarer.
     */
    public static function soulStoneSuccessChance(int $currentGrandRank): int
    {
        return match (true) {
            $currentGrandRank <= 0 => 100,
            $currentGrandRank <= 3 => 85,
            $currentGrandRank <= 6 => 70,
            $currentGrandRank <= 9 => 60,
            default => 100,
        };
    }

    public static function skillBookSlug(string $skillSlug): string
    {
        return 'skill-book-'.$skillSlug;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function races(): array
    {
        return [
            'warrior' => [
                'label' => __('Warrior'),
                'description' => __('Frontline melee fighter with the most stable early-game defense and weapon pressure.'),
                'starting_stats' => [
                    'max_health' => 104,
                    'health' => 104,
                    'attack' => 9,
                    'defense' => 7,
                    'speed' => 5,
                    'max_sp' => 22,
                    'sp' => 22,
                    'vitality' => 5,
                    'intelligence' => 2,
                    'strength' => 5,
                    'dexterity' => 3,
                ],
                'skill_groups' => [
                    'body' => [
                        'label' => __('Body'),
                        'description' => __('Aggressive melee path with burst strikes, fast area swings and self-sustain.'),
                    ],
                    'mental' => [
                        'label' => __('Mental'),
                        'description' => __('Steadier control path with guard-heavy skills, impact strikes and battlefield resilience.'),
                    ],
                ],
            ],
            'ninja' => [
                'label' => __('Ninja'),
                'description' => __('Fast precision fighter with high evasion and burst windows.'),
                'starting_stats' => [
                    'max_health' => 96,
                    'health' => 96,
                    'attack' => 8,
                    'defense' => 5,
                    'speed' => 7,
                    'max_sp' => 24,
                    'sp' => 24,
                    'vitality' => 3,
                    'intelligence' => 3,
                    'strength' => 4,
                    'dexterity' => 6,
                ],
                'skill_groups' => [
                    'dagger' => [
                        'label' => __('Dagger'),
                        'description' => __('Close-range assassin route with poison pressure, evasive tools and fast finishers.'),
                    ],
                    'archery' => [
                        'label' => __('Archery'),
                        'description' => __('Ranged damage route with volley attacks, speed tools and repeated pressure.'),
                    ],
                ],
            ],
            'sura' => [
                'label' => __('Sura'),
                'description' => __('Hybrid fighter that mixes physical pressure with darker magic scaling.'),
                'starting_stats' => [
                    'max_health' => 100,
                    'health' => 100,
                    'attack' => 8,
                    'defense' => 6,
                    'speed' => 5,
                    'max_sp' => 28,
                    'sp' => 28,
                    'magic_attack' => 4,
                    'vitality' => 4,
                    'intelligence' => 5,
                    'strength' => 4,
                    'dexterity' => 3,
                ],
                'skill_groups' => [
                    'weaponary-magic' => [
                        'label' => __('Weaponary Magic'),
                        'description' => __('Blade-enhancing Sura school with close-range spells, fear tools and enchanted defense.'),
                    ],
                    'black-magic' => [
                        'label' => __('Black Magic'),
                        'description' => __('Pure spell pressure with heavier magic hits and darker battlefield control.'),
                    ],
                ],
            ],
            'shaman' => [
                'label' => __('Shaman'),
                'description' => __('Mystic caster with the highest SP base and supportive magic tools.'),
                'starting_stats' => [
                    'max_health' => 92,
                    'health' => 92,
                    'attack' => 7,
                    'defense' => 5,
                    'speed' => 6,
                    'max_sp' => 32,
                    'sp' => 32,
                    'magic_attack' => 6,
                    'vitality' => 3,
                    'intelligence' => 6,
                    'strength' => 2,
                    'dexterity' => 4,
                ],
                'skill_groups' => [
                    'dragon' => [
                        'label' => __('Dragon Force'),
                        'description' => __('Offensive shaman path with talismans, dragon bursts and empowering blessings.'),
                    ],
                    'healing' => [
                        'label' => __('Healing Force'),
                        'description' => __('Supportive shaman path with faster recovery, lightning pressure and survival tools.'),
                    ],
                ],
            ],
        ];
    }

    public static function hasRace(string $race): bool
    {
        return array_key_exists($race, self::races());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupsForRace(string $race): array
    {
        return (array) data_get(self::races(), $race.'.skill_groups', []);
    }

    public static function hasSkillGroup(string $race, ?string $group): bool
    {
        if ($group === null || $group === '') {
            return false;
        }

        return array_key_exists($group, self::groupsForRace($race));
    }

    /**
     * Registration uses these base values so race choice already matters before level 5.
     *
     * @return array<string, int>
     */
    public static function startingStatsForRace(string $race): array
    {
        return data_get(
            self::races(),
            $race.'.starting_stats',
            data_get(self::races(), self::defaultRace().'.starting_stats', []),
        );
    }

    public static function raceLabel(string $race): string
    {
        return (string) data_get(self::races(), $race.'.label', ucfirst($race));
    }

    public static function skillGroupLabel(string $race, ?string $group): ?string
    {
        if ($group === null) {
            return null;
        }

        $label = data_get(self::groupsForRace($race), $group.'.label');

        return is_string($label) ? $label : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedSkillDefinitions(): array
    {
        return collect(self::skillDefinitions())
            ->map(function (array $definition, string $slug): array {
                return [
                    'slug' => $slug,
                    'race' => $definition['race'],
                    'skill_group' => $definition['skill_group'],
                    'faction' => null,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'required_level' => $definition['required_level'],
                    'max_rank' => $definition['max_rank'] ?? 10,
                    'effect_type' => $definition['panel_type'],
                    'effect_value' => 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Skill books are content rows derived from the same central skill list.
     *
     * Keeping the mapping here avoids drifting item names, linked skill slugs and
     * "book starts after rank X" copy across seeders and UI helpers.
     *
     * @return list<array<string, mixed>>
     */
    public static function seedSkillBookDefinitions(): array
    {
        return collect(self::skillDefinitions())
            ->map(function (array $definition, string $slug): array {
                return [
                    'slug' => self::skillBookSlug($slug),
                    'skill_slug' => $slug,
                    'name' => $definition['name'].' Book',
                    'item_type' => 'skill-book',
                    'equipment_slot' => null,
                    'description' => __('Study this manual to deepen :skill once it reaches rank :rank or higher.', [
                        'skill' => $definition['name'],
                        'rank' => self::skillBookMinimumRank(),
                    ]),
                    'price' => 0,
                    'sell_price' => 24,
                    'required_level' => max(self::skillBookMinimumRank(), (int) $definition['required_level']),
                    'stackable' => true,
                    'max_stack' => 20,
                    'inventory_height' => 1,
                    'icon_path' => null,
                    'attack_bonus' => 0,
                    'defense_bonus' => 0,
                    'health_bonus' => 0,
                    'stamina_bonus' => 0,
                    'speed_bonus' => 0,
                    'travel_speed_percent_bonus' => 0,
                    'heal_amount' => 0,
                    'stamina_restore' => 0,
                    'sp_restore' => 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Generic skill progression items do not belong to one skill tree.
     * We still seed them here so skill content stays grouped in one place.
     *
     * @return list<array<string, mixed>>
     */
    public static function seedSkillSupportItems(): array
    {
        return [[
            'slug' => self::soulStoneSlug(),
            'skill_slug' => null,
            'name' => 'Soul Stone',
            'item_type' => 'material',
            'equipment_slot' => null,
            'description' => __('A condensed stone of will. Once a skill reaches M10, this stone can push it through the G stages and finally into P.'),
            'price' => 0,
            'sell_price' => 90,
            'required_level' => 10,
            'stackable' => true,
            'max_stack' => 20,
            'inventory_height' => 1,
            'icon_path' => null,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
            'heal_amount' => 0,
            'stamina_restore' => 0,
            'sp_restore' => 0,
        ]];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function skillDefinition(string $slug): ?array
    {
        return self::skillDefinitions()[$slug] ?? null;
    }

    /**
     * Converts a persisted skill plus rank into concrete battle values the engine can resolve.
     *
     * @return array<string, mixed>
     */
    public static function combatProfile(Skill $skill, int $rank): array
    {
        $definition = self::skillDefinition($skill->slug);

        if ($definition === null) {
            return [
                'slug' => $skill->slug,
                'name' => $skill->name,
                'rank' => $rank,
                'role_label' => __('Combat Skill'),
                'effect_type' => $skill->effect_type,
                'sp_cost' => 4 + $rank,
                'cooldown_rounds' => 0,
                'cooldown_remaining' => 0,
                'damage_bonus' => 4 + ($rank * 2),
                'splash_percent' => 70,
                'guard' => 0,
                'heal_flat' => 0,
                'heal_percent' => 0,
                'crit_bonus' => 0,
                'evade_bonus' => 0,
                'poison_chance' => 0,
                'poison_damage' => 0,
                'poison_rounds' => 0,
                'burn_chance' => 0,
                'burn_damage' => 0,
                'burn_rounds' => 0,
                'uses_magic_attack' => false,
                'damage_multiplier' => 1.0,
                'attack_buff' => 0,
                'attack_buff_rounds' => 0,
                'self_buff_only' => false,
            ];
        }

        $combat = $definition['combat'];

        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'rank' => $rank,
            'role_label' => $definition['role_label'],
            'effect_type' => $definition['panel_type'],
            'sp_cost' => max(1, (int) $combat['sp_cost_base'] + (($rank - 1) * (int) ($combat['sp_cost_per_rank'] ?? 0))),
            'cooldown_rounds' => max(0, (int) ($combat['cooldown_rounds'] ?? 0)),
            'cooldown_remaining' => 0,
            'damage_bonus' => max(0, (int) $combat['damage_bonus_base'] + (($rank - 1) * (int) ($combat['damage_bonus_per_rank'] ?? 0))),
            'splash_percent' => max(0, (int) ($combat['splash_percent'] ?? 0)),
            'guard' => max(0, (int) $combat['guard_base'] + (($rank - 1) * (int) ($combat['guard_per_rank'] ?? 0))),
            'heal_flat' => max(0, (int) $combat['heal_flat_base'] + (($rank - 1) * (int) ($combat['heal_flat_per_rank'] ?? 0))),
            'heal_percent' => max(0, (int) $combat['heal_percent_base'] + (($rank - 1) * (int) ($combat['heal_percent_per_rank'] ?? 0))),
            'crit_bonus' => max(0, (int) $combat['crit_bonus_base'] + (($rank - 1) * (int) ($combat['crit_bonus_per_rank'] ?? 0))),
            'evade_bonus' => max(0, (int) $combat['evade_bonus_base'] + (($rank - 1) * (int) ($combat['evade_bonus_per_rank'] ?? 0))),
            'poison_chance' => max(0, (int) $combat['poison_chance_base'] + (($rank - 1) * (int) ($combat['poison_chance_per_rank'] ?? 0))),
            'poison_damage' => max(0, (int) $combat['poison_damage_base'] + (($rank - 1) * (int) ($combat['poison_damage_per_rank'] ?? 0))),
            'poison_rounds' => max(0, (int) ($combat['poison_rounds'] ?? 0)),
            'burn_chance' => max(0, (int) $combat['burn_chance_base'] + (($rank - 1) * (int) ($combat['burn_chance_per_rank'] ?? 0))),
            'burn_damage' => max(0, (int) $combat['burn_damage_base'] + (($rank - 1) * (int) ($combat['burn_damage_per_rank'] ?? 0))),
            'burn_rounds' => max(0, (int) ($combat['burn_rounds'] ?? 0)),
            'uses_magic_attack' => (bool) ($combat['uses_magic_attack'] ?? false),
            'damage_multiplier' => max(0.4, (float) ($combat['damage_multiplier_base'] ?? 1) + (($rank - 1) * (float) ($combat['damage_multiplier_per_rank'] ?? 0))),
            'attack_buff' => max(0, (int) $combat['attack_buff_base'] + (($rank - 1) * (int) ($combat['attack_buff_per_rank'] ?? 0))),
            'attack_buff_rounds' => max(0, (int) ($combat['attack_buff_rounds'] ?? 0)),
            'self_buff_only' => (bool) ($combat['self_buff_only'] ?? false),
        ];
    }

    /**
     * Learned skills carry mastery progress on the character row, not on the static definition.
     * This helper combines both sources into the actual battle button profile.
     *
     * @return array<string, mixed>|null
     */
    public static function combatProfileForLearnedSkill(CharacterSkill $characterSkill): ?array
    {
        $skill = $characterSkill->skill;

        if ($skill === null || $characterSkill->rank < 1) {
            return null;
        }

        if ($characterSkill->hasReachedPerfectMastery()) {
            $profile = self::combatProfile($skill, 10);
            $profile['damage_bonus'] += 18;
            $profile['guard'] += 10;
            $profile['heal_flat'] += 14;
            $profile['crit_bonus'] += 8;
            $profile['evade_bonus'] += 8;
            $profile['poison_damage'] += $profile['poison_damage'] > 0 ? 6 : 0;
            $profile['burn_damage'] += $profile['burn_damage'] > 0 ? 6 : 0;
            $profile['damage_multiplier'] += 0.28;
            $profile['mastery_label'] = $characterSkill->stageLabel();

            return $profile;
        }

        if ((int) $characterSkill->grand_mastery_rank > 0) {
            $profile = self::combatProfile($skill, 10);
            $grandRank = (int) $characterSkill->grand_mastery_rank;

            $profile['damage_bonus'] += $grandRank * 3;
            $profile['guard'] += $grandRank * 2;
            $profile['heal_flat'] += $grandRank * 3;
            $profile['crit_bonus'] += (int) ceil($grandRank / 2);
            $profile['evade_bonus'] += (int) ceil($grandRank / 2);
            $profile['poison_damage'] += $profile['poison_damage'] > 0 ? $grandRank : 0;
            $profile['burn_damage'] += $profile['burn_damage'] > 0 ? $grandRank : 0;
            $profile['damage_multiplier'] += $grandRank * 0.03;
            $profile['mastery_label'] = $characterSkill->stageLabel();

            return $profile;
        }

        $basicRank = max(1, min(10, (int) $characterSkill->rank + min(5, (int) $characterSkill->mastery_rank)));
        $profile = self::combatProfile($skill, $basicRank);
        $masteryOverflow = max(0, (int) $characterSkill->mastery_rank - 5);

        if ($masteryOverflow > 0) {
            $profile['damage_bonus'] += $masteryOverflow * 2;
            $profile['guard'] += $masteryOverflow;
            $profile['heal_flat'] += $masteryOverflow * 2;
            $profile['crit_bonus'] += $masteryOverflow;
            $profile['evade_bonus'] += $masteryOverflow;
            $profile['poison_damage'] += $profile['poison_damage'] > 0 ? $masteryOverflow : 0;
            $profile['burn_damage'] += $profile['burn_damage'] > 0 ? $masteryOverflow : 0;
        }

        $profile['mastery_label'] = $characterSkill->stageLabel();

        return $profile;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function skillDefinitions(): array
    {
        return [
            'aura-of-the-sword' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Aura of the Sword',
                description: __('Wrap the blade in aura and raise your weapon damage for the next few rounds.'),
                panelType: __('Weapon Buff'),
                roleLabel: __('Weapon Buff'),
                requiredLevel: 5,
                combat: self::combat(
                    sp: 5,
                    cooldown: 3,
                    damageBonus: 0,
                    damagePerRank: 0,
                    splash: 0,
                    attackBuff: 8,
                    attackBuffPerRank: 2,
                    attackBuffRounds: 3,
                    selfBuffOnly: true,
                ),
            ),
            'berserk' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Berserk',
                description: __('Trade calm for a harder hit and a sharper critical window.'),
                panelType: __('Critical Strike'),
                roleLabel: __('Critical Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 6, damagePerRank: 2, splash: 58, critBonus: 6, critPerRank: 1),
            ),
            'dash' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Dash',
                description: __('Burst forward, slash through the pack and make the next counter harder to land.'),
                panelType: __('Evasive Strike'),
                roleLabel: __('Evasive Strike'),
                requiredLevel: 6,
                combat: self::combat(sp: 4, cooldown: 1, damageBonus: 5, damagePerRank: 1, splash: 70, evadeBonus: 8, evadePerRank: 1),
            ),
            'sword-spin' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Sword Spin',
                description: __('Wide circular slash built to tear through grouped enemies.'),
                panelType: __('Wide Cleave'),
                roleLabel: __('Wide Cleave'),
                requiredLevel: 7,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 7, damagePerRank: 2, splash: 88),
            ),
            'three-way-cut' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Three-Way Cut',
                description: __('Fast crossing slashes that ripple into nearby enemies.'),
                panelType: __('Cleave'),
                roleLabel: __('Cleave'),
                requiredLevel: 8,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 7, damagePerRank: 2, splash: 82),
            ),
            'life-force' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Life Force',
                description: __('Steal momentum from the hit and restore a bit of health in the same motion.'),
                panelType: __('Drain Strike'),
                roleLabel: __('Drain Strike'),
                requiredLevel: 9,
                combat: self::combat(sp: 7, cooldown: 3, damageBonus: 8, damagePerRank: 2, splash: 55, healFlat: 8, healFlatPerRank: 2),
            ),
            'earthquake-body' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Earthquake',
                description: __('Crush the ground and carry the shock through the whole enemy line.'),
                panelType: __('Heavy Cleave'),
                roleLabel: __('Heavy Cleave'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 10, damagePerRank: 2, splash: 92),
            ),
            'sword-spin-boost' => self::skill(
                race: 'warrior',
                skillGroup: 'body',
                name: 'Sword Spin Boost',
                description: __('Late body-warrior boost that hardens the next spinning opener.'),
                panelType: __('Burst Cleave'),
                roleLabel: __('Burst Cleave'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 12, damagePerRank: 3, splash: 96, critBonus: 4, critPerRank: 1),
            ),

            'bash' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Bash',
                description: __('Short, brutal impact meant to break the enemy line.'),
                panelType: __('Impact Strike'),
                roleLabel: __('Impact Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 54, guard: 4, guardPerRank: 1),
            ),
            'spirit-strike-warrior' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Spirit Strike',
                description: __('Condense power into one heavier follow-through.'),
                panelType: __('Heavy Strike'),
                roleLabel: __('Heavy Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 52),
            ),
            'strong-body' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Strong Body',
                description: __('Brace the body and absorb more of the next enemy exchange.'),
                panelType: __('Guard Skill'),
                roleLabel: __('Guard Skill'),
                requiredLevel: 6,
                combat: self::combat(sp: 4, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 48, guard: 9, guardPerRank: 2),
            ),
            'stump' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Stump',
                description: __('Ground the enemy pack with a sturdy sweeping impact.'),
                panelType: __('Control Cleave'),
                roleLabel: __('Control Cleave'),
                requiredLevel: 7,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 7, damagePerRank: 2, splash: 78, guard: 5, guardPerRank: 1),
            ),
            'sword-strike' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Sword Strike',
                description: __('A disciplined overhead strike with heavier direct impact.'),
                panelType: __('Heavy Strike'),
                roleLabel: __('Heavy Strike'),
                requiredLevel: 8,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 10, damagePerRank: 2, splash: 50),
            ),
            'sword-orb' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Sword Orb',
                description: __('Release stored blade force outward across the full pack.'),
                panelType: __('Wave Strike'),
                roleLabel: __('Wave Strike'),
                requiredLevel: 9,
                combat: self::combat(sp: 7, cooldown: 3, damageBonus: 8, damagePerRank: 2, splash: 90),
            ),
            'earthquake-mental' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Earthquake',
                description: __('Drive the pack back with a steadier but heavier shockwave.'),
                panelType: __('Heavy Cleave'),
                roleLabel: __('Heavy Cleave'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 11, damagePerRank: 2, splash: 94, guard: 4, guardPerRank: 1),
            ),
            'spirit-strike-boost-warrior' => self::skill(
                race: 'warrior',
                skillGroup: 'mental',
                name: 'Spirit Strike Boost',
                description: __('Late mental-warrior boost that sharpens the finishing blow.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 13, damagePerRank: 3, splash: 56, critBonus: 5, critPerRank: 1),
            ),

            'ambush' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Ambush',
                description: __('Fast close-range opener with strong single-target pressure.'),
                panelType: __('Assassin Strike'),
                roleLabel: __('Assassin Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 58, critBonus: 5, critPerRank: 1),
            ),
            'fast-attack' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Fast Attack',
                description: __('Rapid stab that keeps pressure up with a low cooldown.'),
                panelType: __('Quick Strike'),
                roleLabel: __('Quick Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 4, cooldown: 1, damageBonus: 5, damagePerRank: 1, splash: 52, critBonus: 3),
            ),
            'rolling-dagger' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Rolling Dagger',
                description: __('Rolling slash that cuts through the nearby pack.'),
                panelType: __('Cleave'),
                roleLabel: __('Cleave'),
                requiredLevel: 6,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 7, damagePerRank: 2, splash: 84),
            ),
            'stealth' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Stealth',
                description: __('Slip the next counter and answer with a sharper hit.'),
                panelType: __('Evasive Strike'),
                roleLabel: __('Evasive Strike'),
                requiredLevel: 7,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 6, damagePerRank: 1, splash: 50, evadeBonus: 10, evadePerRank: 1),
            ),
            'poisonous-cloud' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Poisonous Cloud',
                description: __('Spread venom through the whole enemy group.'),
                panelType: __('Poison Cleave'),
                roleLabel: __('Poison Cleave'),
                requiredLevel: 8,
                combat: self::combat(sp: 7, cooldown: 3, damageBonus: 8, damagePerRank: 2, splash: 90, evadeBonus: 4, poisonChance: 75, poisonChancePerRank: 2, poisonDamage: 3, poisonDamagePerRank: 1, poisonRounds: 2),
            ),
            'insidious-poison' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Insidious Poison',
                description: __('A nastier focused thrust for weakened targets.'),
                panelType: __('Burst Strike'),
                roleLabel: __('Burst Strike'),
                requiredLevel: 9,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 10, damagePerRank: 2, splash: 48, critBonus: 4, critPerRank: 1, poisonChance: 100, poisonDamage: 5, poisonDamagePerRank: 1, poisonRounds: 3),
            ),
            'astral-light' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Astral Light',
                description: __('Flash-step through the pack and leave wide cuts behind.'),
                panelType: __('Wide Cleave'),
                roleLabel: __('Wide Cleave'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 10, damagePerRank: 2, splash: 94, evadeBonus: 6, evadePerRank: 1),
            ),
            'ambush-boost' => self::skill(
                race: 'ninja',
                skillGroup: 'dagger',
                name: 'Ambush Boost',
                description: __('Late dagger boost that amplifies the assassination burst window.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 13, damagePerRank: 3, splash: 58, critBonus: 7, critPerRank: 1),
            ),

            'repetitive-shot' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Repetitive Shot',
                description: __('Rapid ranged pressure translated into quick pack damage.'),
                panelType: __('Volley'),
                roleLabel: __('Volley'),
                requiredLevel: 5,
                combat: self::combat(sp: 4, cooldown: 1, damageBonus: 5, damagePerRank: 1, splash: 64, critBonus: 3),
            ),
            'arrow-shower' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Arrow Shower',
                description: __('Rain arrows across the entire pack.'),
                panelType: __('Wide Volley'),
                roleLabel: __('Wide Volley'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 7, damagePerRank: 2, splash: 90),
            ),
            'fire-arrow' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Fire Arrow',
                description: __('A stronger direct hit for priority targets.'),
                panelType: __('Burst Shot'),
                roleLabel: __('Burst Shot'),
                requiredLevel: 6,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 54, burnChance: 65, burnChancePerRank: 2, burnDamage: 3, burnDamagePerRank: 1, burnRounds: 2),
            ),
            'feather-walk' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Feather Walk',
                description: __('Stay light on your feet and slip more return attacks.'),
                panelType: __('Evasive Volley'),
                roleLabel: __('Evasive Volley'),
                requiredLevel: 7,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 5, damagePerRank: 1, splash: 56, evadeBonus: 10, evadePerRank: 1),
            ),
            'poison-arrow' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Poison Arrow',
                description: __('Focused toxic shot with better finish pressure.'),
                panelType: __('Poison Shot'),
                roleLabel: __('Poison Shot'),
                requiredLevel: 8,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 48, critBonus: 4, poisonChance: 90, poisonChancePerRank: 2, poisonDamage: 4, poisonDamagePerRank: 1, poisonRounds: 3),
            ),
            'spark' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Spark',
                description: __('Sharp flash shot that clips nearby targets as it passes.'),
                panelType: __('Piercing Shot'),
                roleLabel: __('Piercing Shot'),
                requiredLevel: 9,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 76),
            ),
            'storm-shot' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Storm Shot',
                description: __('Large ranged burst built to punish clustered waves.'),
                panelType: __('Heavy Volley'),
                roleLabel: __('Heavy Volley'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 10, damagePerRank: 2, splash: 94),
            ),
            'fire-arrow-boost' => self::skill(
                race: 'ninja',
                skillGroup: 'archery',
                name: 'Fire Arrow Boost',
                description: __('Late archery boost that turns the next fire shot into a finisher.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 12, damagePerRank: 3, splash: 58, critBonus: 6, critPerRank: 1),
            ),

            'finger-strike' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Finger Strike',
                description: __('Close blade spell that mixes steel and magic in one hit.'),
                panelType: __('Hybrid Strike'),
                roleLabel: __('Hybrid Strike'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 58, usesMagic: true, damageMultiplier: 1.05, damageMultiplierPerRank: 0.02),
            ),
            'dragon-swirl' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Dragon Swirl',
                description: __('Arcane spin that lashes the full enemy line.'),
                panelType: __('Magic Cleave'),
                roleLabel: __('Magic Cleave'),
                requiredLevel: 5,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 90, usesMagic: true),
            ),
            'enchanted-blade' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Enchanted Blade',
                description: __('Empower the next exchange with a stronger magical cut.'),
                panelType: __('Empowered Strike'),
                roleLabel: __('Empowered Strike'),
                requiredLevel: 6,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 60, usesMagic: true, critBonus: 4),
            ),
            'fear' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Fear',
                description: __('Pressure the enemy line and soften the next counterattack.'),
                panelType: __('Guard Break'),
                roleLabel: __('Guard Break'),
                requiredLevel: 7,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 5, damagePerRank: 1, splash: 66, evadeBonus: 8, evadePerRank: 1, usesMagic: true),
            ),
            'enchanted-armour' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Enchanted Armour',
                description: __('Harden your next defensive cycle with arcane armour.'),
                panelType: __('Guard Skill'),
                roleLabel: __('Guard Skill'),
                requiredLevel: 8,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 44, guard: 10, guardPerRank: 2, usesMagic: true),
            ),
            'dispel' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Dispel',
                description: __('Tear through hostile pressure with a focused magical break.'),
                panelType: __('Focused Spell'),
                roleLabel: __('Focused Spell'),
                requiredLevel: 9,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 10, damagePerRank: 2, splash: 46, usesMagic: true),
            ),
            'hell-strike' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Hell Strike',
                description: __('A harder hybrid finisher for important targets.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 12, damagePerRank: 2, splash: 52, usesMagic: true, critBonus: 5),
            ),
            'finger-strike-boost' => self::skill(
                race: 'sura',
                skillGroup: 'weaponary-magic',
                name: 'Finger Strike Boost',
                description: __('Late weaponary boost that sharpens the hybrid burst pattern.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 13, damagePerRank: 3, splash: 60, usesMagic: true, damageMultiplier: 1.1, damageMultiplierPerRank: 0.02),
            ),

            'dark-strike' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Dark Strike',
                description: __('Pure dark blast for direct magical pressure.'),
                panelType: __('Magic Burst'),
                roleLabel: __('Magic Burst'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 50, usesMagic: true, damageMultiplier: 1.1, damageMultiplierPerRank: 0.02),
            ),
            'flame-strike' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Flame Strike',
                description: __('Scorch the whole wave with a stronger area spell.'),
                panelType: __('Magic Cleave'),
                roleLabel: __('Magic Cleave'),
                requiredLevel: 5,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 92, usesMagic: true, burnChance: 70, burnChancePerRank: 2, burnDamage: 3, burnDamagePerRank: 1, burnRounds: 2),
            ),
            'flame-spirit' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Flame Spirit',
                description: __('Wrap the strike in burning spirit force.'),
                panelType: __('Empowered Spell'),
                roleLabel: __('Empowered Spell'),
                requiredLevel: 6,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 62, usesMagic: true, critBonus: 5, burnChance: 100, burnDamage: 4, burnDamagePerRank: 1, burnRounds: 3),
            ),
            'dark-protection' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Dark Protection',
                description: __('Convert dark magic into a steadier defensive cycle.'),
                panelType: __('Ward'),
                roleLabel: __('Ward'),
                requiredLevel: 7,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 42, guard: 11, guardPerRank: 2, usesMagic: true),
            ),
            'spirit-strike-sura' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Spirit Strike',
                description: __('Focused spirit pressure with heavy magical impact.'),
                panelType: __('Heavy Spell'),
                roleLabel: __('Heavy Spell'),
                requiredLevel: 8,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 11, damagePerRank: 2, splash: 48, usesMagic: true, damageMultiplier: 1.12, damageMultiplierPerRank: 0.02),
            ),
            'dark-orb' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Dark Orb',
                description: __('Dense orb that clips the whole pack on release.'),
                panelType: __('Orb Wave'),
                roleLabel: __('Orb Wave'),
                requiredLevel: 9,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 88, usesMagic: true),
            ),
            'death-wave' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Death Wave',
                description: __('Large dark wave for mid-fight area control.'),
                panelType: __('Heavy Wave'),
                roleLabel: __('Heavy Wave'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 11, damagePerRank: 2, splash: 96, usesMagic: true),
            ),
            'dark-strike-boost' => self::skill(
                race: 'sura',
                skillGroup: 'black-magic',
                name: 'Dark Strike Boost',
                description: __('Late black-magic boost that sharpens your direct burst.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 13, damagePerRank: 3, splash: 56, usesMagic: true, damageMultiplier: 1.14, damageMultiplierPerRank: 0.02),
            ),

            'flying-talisman' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Flying Talisman',
                description: __('Fast dragon-force talisman for direct magical pressure.'),
                panelType: __('Magic Burst'),
                roleLabel: __('Magic Burst'),
                requiredLevel: 5,
                combat: self::combat(sp: 5, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 52, usesMagic: true),
            ),
            'shooting-dragon' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Shooting Dragon',
                description: __('Dragon projectile that carries pressure through nearby enemies.'),
                panelType: __('Dragon Volley'),
                roleLabel: __('Dragon Volley'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 86, usesMagic: true),
            ),
            'dragons-roar' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Dragon\'s Roar',
                description: __('Wide dragon-force burst for clustered fights.'),
                panelType: __('Wide Burst'),
                roleLabel: __('Wide Burst'),
                requiredLevel: 6,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 94, usesMagic: true),
            ),
            'blessing' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Blessing',
                description: __('Protective dragon blessing that steadies the next retaliation.'),
                panelType: __('Blessed Guard'),
                roleLabel: __('Blessed Guard'),
                requiredLevel: 7,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 44, guard: 10, guardPerRank: 2, usesMagic: true),
            ),
            'reflect' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Reflect',
                description: __('Turn incoming pressure aside and buy room for the next round.'),
                panelType: __('Ward'),
                roleLabel: __('Ward'),
                requiredLevel: 8,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 40, guard: 12, guardPerRank: 2, evadeBonus: 6, evadePerRank: 1, usesMagic: true),
            ),
            'dragons-strength' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Dragon\'s Strength',
                description: __('Heavier empowered strike through dragon-force focus.'),
                panelType: __('Empowered Spell'),
                roleLabel: __('Empowered Spell'),
                requiredLevel: 9,
                combat: self::combat(sp: 7, cooldown: 2, damageBonus: 10, damagePerRank: 2, splash: 60, usesMagic: true, critBonus: 4),
            ),
            'meteor' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Meteor',
                description: __('Call down a larger dragon-force impact into the full enemy pack.'),
                panelType: __('Meteor Wave'),
                roleLabel: __('Meteor Wave'),
                requiredLevel: 10,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 12, damagePerRank: 2, splash: 96, usesMagic: true),
            ),
            'shooting-dragon-boost' => self::skill(
                race: 'shaman',
                skillGroup: 'dragon',
                name: 'Shooting Dragon Boost',
                description: __('Late dragon-force boost that sharpens your signature projectile burst.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 9, cooldown: 3, damageBonus: 13, damagePerRank: 3, splash: 90, usesMagic: true),
            ),

            'lightning-throw' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Lightning Throw',
                description: __('Quick lightning pressure with better tempo control.'),
                panelType: __('Magic Burst'),
                roleLabel: __('Magic Burst'),
                requiredLevel: 5,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 7, damagePerRank: 2, splash: 54, usesMagic: true),
            ),
            'summon-lightning' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Summon Lightning',
                description: __('Broader lightning call that cracks through the whole enemy line.'),
                panelType: __('Wide Burst'),
                roleLabel: __('Wide Burst'),
                requiredLevel: 5,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 8, damagePerRank: 2, splash: 92, usesMagic: true),
            ),
            'lightning-claw' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Lightning Claw',
                description: __('Sharper spell-claw for focused magical damage.'),
                panelType: __('Focused Spell'),
                roleLabel: __('Focused Spell'),
                requiredLevel: 6,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 50, usesMagic: true),
            ),
            'cure' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Cure',
                description: __('Restore part of your own health while still sending magic through the target.'),
                panelType: __('Heal Strike'),
                roleLabel: __('Heal Strike'),
                requiredLevel: 7,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 5, damagePerRank: 1, splash: 40, usesMagic: true, healFlat: 10, healFlatPerRank: 3),
            ),
            'swiftness' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Swiftness',
                description: __('Raise your evasion tempo before the enemy turn lands.'),
                panelType: __('Speed Ward'),
                roleLabel: __('Speed Ward'),
                requiredLevel: 8,
                combat: self::combat(sp: 5, cooldown: 1, damageBonus: 4, damagePerRank: 1, splash: 42, evadeBonus: 12, evadePerRank: 1, usesMagic: true),
            ),
            'attack-up' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Attack Up',
                description: __('Supportive power surge expressed as a sharper damaging cast.'),
                panelType: __('Empowered Spell'),
                roleLabel: __('Empowered Spell'),
                requiredLevel: 9,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 9, damagePerRank: 2, splash: 62, critBonus: 4, usesMagic: true),
            ),
            'aether-ward' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Aether Ward',
                description: __('Barrier-like healing school ward for safer longer fights.'),
                panelType: __('Ward'),
                roleLabel: __('Ward'),
                requiredLevel: 10,
                combat: self::combat(sp: 6, cooldown: 2, damageBonus: 4, damagePerRank: 1, splash: 38, guard: 12, guardPerRank: 2, healFlat: 6, healFlatPerRank: 2, usesMagic: true),
            ),
            'summon-lightning-boost' => self::skill(
                race: 'shaman',
                skillGroup: 'healing',
                name: 'Summon Lightning Boost',
                description: __('Late healing-force boost that turns the lightning call into a bigger wave.'),
                panelType: __('Finisher'),
                roleLabel: __('Finisher'),
                requiredLevel: 14,
                combat: self::combat(sp: 8, cooldown: 3, damageBonus: 12, damagePerRank: 3, splash: 94, usesMagic: true),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $combat
     * @return array<string, mixed>
     */
    private static function skill(
        string $race,
        string $skillGroup,
        string $name,
        string $description,
        string $panelType,
        string $roleLabel,
        int $requiredLevel,
        array $combat,
    ): array {
        return [
            'race' => $race,
            'skill_group' => $skillGroup,
            'name' => $name,
            'description' => $description,
            'panel_type' => $panelType,
            'role_label' => $roleLabel,
            'required_level' => $requiredLevel,
            'max_rank' => 10,
            'combat' => $combat,
        ];
    }

    /**
     * @return array<string, int|float|bool>
     */
    private static function combat(
        int $sp,
        int $cooldown,
        int $damageBonus,
        int $damagePerRank,
        int $splash,
        int $guard = 0,
        int $guardPerRank = 0,
        int $healFlat = 0,
        int $healFlatPerRank = 0,
        int $healPercent = 0,
        int $healPercentPerRank = 0,
        int $critBonus = 0,
        int $critPerRank = 0,
        int $evadeBonus = 0,
        int $evadePerRank = 0,
        int $poisonChance = 0,
        int $poisonChancePerRank = 0,
        int $poisonDamage = 0,
        int $poisonDamagePerRank = 0,
        int $poisonRounds = 0,
        int $burnChance = 0,
        int $burnChancePerRank = 0,
        int $burnDamage = 0,
        int $burnDamagePerRank = 0,
        int $burnRounds = 0,
        bool $usesMagic = false,
        float $damageMultiplier = 1.0,
        float $damageMultiplierPerRank = 0.0,
        int $attackBuff = 0,
        int $attackBuffPerRank = 0,
        int $attackBuffRounds = 0,
        bool $selfBuffOnly = false,
    ): array {
        return [
            'sp_cost_base' => $sp,
            'sp_cost_per_rank' => 0,
            'cooldown_rounds' => $cooldown,
            'damage_bonus_base' => $damageBonus,
            'damage_bonus_per_rank' => $damagePerRank,
            'splash_percent' => $splash,
            'guard_base' => $guard,
            'guard_per_rank' => $guardPerRank,
            'heal_flat_base' => $healFlat,
            'heal_flat_per_rank' => $healFlatPerRank,
            'heal_percent_base' => $healPercent,
            'heal_percent_per_rank' => $healPercentPerRank,
            'crit_bonus_base' => $critBonus,
            'crit_bonus_per_rank' => $critPerRank,
            'evade_bonus_base' => $evadeBonus,
            'evade_bonus_per_rank' => $evadePerRank,
            'poison_chance_base' => $poisonChance,
            'poison_chance_per_rank' => $poisonChancePerRank,
            'poison_damage_base' => $poisonDamage,
            'poison_damage_per_rank' => $poisonDamagePerRank,
            'poison_rounds' => $poisonRounds,
            'burn_chance_base' => $burnChance,
            'burn_chance_per_rank' => $burnChancePerRank,
            'burn_damage_base' => $burnDamage,
            'burn_damage_per_rank' => $burnDamagePerRank,
            'burn_rounds' => $burnRounds,
            'uses_magic_attack' => $usesMagic,
            'damage_multiplier_base' => $damageMultiplier,
            'damage_multiplier_per_rank' => $damageMultiplierPerRank,
            'attack_buff_base' => $attackBuff,
            'attack_buff_per_rank' => $attackBuffPerRank,
            'attack_buff_rounds' => $attackBuffRounds,
            'self_buff_only' => $selfBuffOnly,
        ];
    }
}
