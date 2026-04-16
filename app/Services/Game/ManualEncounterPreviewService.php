<?php

namespace App\Services\Game;

use App\Exceptions\GameActionException;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Models\LocationAreaMonster;
use App\Models\Monster;
use App\Services\Game\Concerns\ResolvesEncounterData;
use Illuminate\Support\Collection;

class ManualEncounterPreviewService
{
    use ResolvesEncounterData;

    private const LUMIIS_EXPECTED_SOUL_COUNT = 6;

    /**
     * @return Collection<int, array{key: string, label: string, examples: list<string>, options: array<string, array<string, int|string>>}>
     */
    public function buildForArea(GameCharacter $character, ?LocationArea $area): Collection
    {
        if (! $area?->isHunting()) {
            return collect();
        }

        $assignments = $this->monsterAssignments($area)
            ->filter(fn (LocationAreaMonster $assignment): bool => $assignment->monster !== null)
            ->values();

        if ($assignments->isEmpty()) {
            return collect();
        }

        return $assignments
            ->groupBy(fn (LocationAreaMonster $assignment): string => $assignment->monster->manualFamilyKey())
            ->map(function (Collection $familyAssignments, string $familyKey) use ($character, $area): array {
                return [
                    'key' => $familyKey,
                    'label' => Monster::manualFamilyLabelFor($familyKey),
                    'examples' => $familyAssignments
                        ->map(fn (LocationAreaMonster $assignment): ?string => $assignment->monster?->name)
                        ->filter()
                        ->unique()
                        ->take(3)
                        ->values()
                        ->all(),
                    'options' => collect(['easy', 'medium', 'hard'])
                        ->mapWithKeys(fn (string $difficulty): array => [
                            $difficulty => $this->buildDifficultyPreview($character, $area, $familyKey, $difficulty),
                        ])
                        ->all(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function buildActionPreviews(GameCharacter $character, ?LocationArea $area): array
    {
        if (! $area?->isHunting()) {
            return [];
        }

        $assignments = $this->monsterAssignments($area)
            ->filter(fn (LocationAreaMonster $assignment): bool => $assignment->monster !== null)
            ->values();

        if ($assignments->isEmpty()) {
            return [];
        }

        try {
            $monsterAssignment = $this->resolveMonsterAssignmentForCharacter($area, $character);
        } catch (GameActionException) {
            return [];
        }

        return [
            'monster_auto' => $this->buildAssignmentPreview(
                $character,
                $monsterAssignment,
                enemyStatMultiplier: 1.0,
                controlBonus: 0,
            ),
            'lumiis_auto' => $this->buildLumiisPreview($character, $area, false),
            'lumiis_manual' => $this->buildLumiisPreview($character, $area, true),
        ];
    }

    /**
     * @return array{success_rate_percent: int, encounter_summary: string, encounter_name: string, enemy_level: int, pack_size: int, live_preview: array<string, int|float|string>}
     */
    private function buildDifficultyPreview(
        GameCharacter $character,
        LocationArea $area,
        string $familyKey,
        string $difficulty,
    ): array {
        $assignment = $this->resolveMonsterAssignmentForCharacter($area, $character, $familyKey, $difficulty);
        $difficultyProfile = $this->manualPreviewDifficultyProfile($difficulty);

        return $this->buildAssignmentPreview(
            $character,
            $assignment,
            enemyStatMultiplier: (float) $difficultyProfile['enemy_stat_multiplier'],
            controlBonus: 0,
        );
    }

    private function buildAssignmentPreview(
        GameCharacter $character,
        LocationAreaMonster $assignment,
        float $enemyStatMultiplier,
        int $controlBonus,
    ): array {
        $monster = $assignment->monster;

        if (! $monster instanceof Monster) {
            return [
                'success_rate_percent' => 0,
                'risk_label' => $this->riskLabel(0),
                'encounter_summary' => '',
                'encounter_name' => '',
                'enemy_level' => 0,
                'pack_size' => max(1, (int) $assignment->pack_size),
                'live_preview' => [],
            ];
        }

        $packSize = max(1, (int) $assignment->pack_size);
        $enemyHealth = max(1, (int) round((int) $monster->maximum_health * $enemyStatMultiplier));
        $enemyMinAttack = max(1, (int) round((int) $monster->minimum_attack * $enemyStatMultiplier));
        $enemyMaxAttack = max($enemyMinAttack + 1, (int) round((int) $monster->maximum_attack * $enemyStatMultiplier));
        $enemyDefense = max(0, (int) round((int) $monster->defense * $enemyStatMultiplier));
        $summary = __('Lv :level · :pack x :monster', [
            'level' => (int) $monster->level,
            'pack' => $packSize,
            'monster' => $monster->name,
        ]);

        $livePreview = [
            'kind' => 'monster',
            'monster_level' => (int) $monster->level,
            'pack_size' => $packSize,
            'pressure_enemy_count' => $packSize,
            'total_enemy_health' => $enemyHealth * $packSize,
            'enemy_min_attack' => $enemyMinAttack,
            'enemy_max_attack' => $enemyMaxAttack,
            'enemy_defense' => $enemyDefense,
            'threat_score' => max(1, (int) round($this->publicAssignmentThreatScore($assignment) * $enemyStatMultiplier)),
            'control_bonus' => $controlBonus,
            'encounter_summary' => $summary,
        ];

        $successRate = $this->evaluateLivePreview(
            $character,
            $livePreview,
            max(1, (int) $character->health),
            max(0, $character->effectiveCurrentSp()),
        );

        return [
            'success_rate_percent' => $successRate,
            'risk_label' => $this->riskLabel($successRate),
            'encounter_summary' => $summary,
            'encounter_name' => $monster->name,
            'enemy_level' => (int) $monster->level,
            'pack_size' => $packSize,
            'live_preview' => $livePreview,
        ];
    }

    private function buildLumiisPreview(GameCharacter $character, LocationArea $area, bool $manualControl): array
    {
        $assignment = $this->resolveMonsterAssignmentForCharacter($area, $character);
        $monster = $assignment->monster;
        $areaDifficulty = max(1, (int) ($area->recommended_level ?: max(1, $character->location->threat_level)));
        $encounterName = $area->encounterName('lumiis') ?? $area->name;

        if (! $monster instanceof Monster) {
            return [
                'success_rate_percent' => 0,
                'risk_label' => $this->riskLabel(0),
                'encounter_summary' => '',
                'encounter_name' => $encounterName,
                'enemy_level' => $areaDifficulty,
                'pack_size' => 1,
                'live_preview' => [],
            ];
        }

        $coreHealth = 88 + ($areaDifficulty * 12);
        $soulHealth = max(8, (int) floor(((int) $monster->maximum_health) * 0.75));
        $soulMinimumAttack = max(1, (int) floor(((int) $monster->minimum_attack) * 0.8));
        $soulMaximumAttack = max($soulMinimumAttack + 1, (int) floor(((int) $monster->maximum_attack) * 0.8));
        $soulDefense = max(0, (int) floor(((int) $monster->defense) * 0.75));
        $pressureEnemyCount = 3;
        $summary = __('Lv :level · core + soul waves', [
            'level' => $areaDifficulty,
        ]);

        $livePreview = [
            'kind' => 'lumiis',
            'monster_level' => $areaDifficulty,
            'pack_size' => 1,
            'pressure_enemy_count' => $pressureEnemyCount,
            'total_enemy_health' => $coreHealth + ($soulHealth * self::LUMIIS_EXPECTED_SOUL_COUNT),
            'enemy_min_attack' => $soulMinimumAttack,
            'enemy_max_attack' => $soulMaximumAttack,
            'enemy_defense' => $soulDefense,
            'threat_score' => max(1, (int) round(
                ($this->publicAssignmentThreatScore($assignment) * 1.55)
                + ($coreHealth * 1.1)
                + ($soulHealth * 2)
                + ($areaDifficulty * 14)
            )),
            'control_bonus' => $manualControl ? 6 : 0,
            'encounter_summary' => $summary,
        ];

        $successRate = $this->evaluateLivePreview(
            $character,
            $livePreview,
            max(1, (int) $character->health),
            max(0, $character->effectiveCurrentSp()),
        );

        return [
            'success_rate_percent' => $successRate,
            'risk_label' => $this->riskLabel($successRate),
            'encounter_summary' => $summary,
            'encounter_name' => $encounterName,
            'enemy_level' => $areaDifficulty,
            'pack_size' => 1,
            'live_preview' => $livePreview,
        ];
    }

    /**
     * @param  array<string, int|float|string>  $preview
     */
    private function evaluateLivePreview(
        GameCharacter $character,
        array $preview,
        int $currentHealth,
        int $currentSp,
    ): int {
        $currentHealth = max(1, $currentHealth);

        if ($currentHealth <= 12) {
            return 5;
        }

        $packSize = max(1, (int) ($preview['pack_size'] ?? 1));
        $pressureEnemyCount = max(1, (int) ($preview['pressure_enemy_count'] ?? $packSize));
        $effectiveAttack = max(1, $character->effectiveAttack());
        $effectiveDefense = max(0, $character->effectiveDefense());
        $effectiveSpeed = max(1, $character->effectiveSpeed());
        $effectiveMagicAttack = max(0, $character->effectiveMagicAttack());
        $currentSp = max(0, $currentSp);
        $maxHealth = max(1, $character->effectiveMaxHealth());
        $healthRatio = min(1, $currentHealth / $maxHealth);
        $skillCount = count($character->relationLoaded('learnedSkills') ? $character->learnedSkills : $character->learnedSkills()->get());

        $enemyTotalHealth = max(1, (int) ($preview['total_enemy_health'] ?? 1));
        $enemyMinAttack = max(1, (int) ($preview['enemy_min_attack'] ?? 1));
        $enemyMaxAttack = max($enemyMinAttack + 1, (int) ($preview['enemy_max_attack'] ?? ($enemyMinAttack + 1)));
        $enemyDefense = max(0, (int) ($preview['enemy_defense'] ?? 0));
        $enemyAverageAttack = (int) floor(($enemyMinAttack + $enemyMaxAttack) / 2);
        $monsterLevel = max(1, (int) ($preview['monster_level'] ?? 1));
        $threatScore = max(1, (int) ($preview['threat_score'] ?? 1));
        $controlBonus = (int) ($preview['control_bonus'] ?? 0);

        $playerBaseDamage = max(1, (int) round(
            ($effectiveAttack + intdiv($effectiveMagicAttack, 4) + max(0, intdiv($currentSp, 20)))
            * (1 + min(0.32, $effectiveSpeed / 100))
            * (1 + ($character->averageDamagePercentBonus() / 100))
            * (1 + ($character->criticalChancePercent() / 280))
            * (1 + ($character->skillDamagePercentBonus() / 260))
        ) - $enemyDefense);
        $playerRoundsToWin = $enemyTotalHealth / max(1, $playerBaseDamage);

        $enemyDamageReduction = max(0, intdiv($effectiveDefense, 4));
        $enemyMinimumDamage = max(1 + intdiv($monsterLevel, 10) + ($pressureEnemyCount > 1 ? 1 : 0), 1);
        $enemyHitDamage = max($enemyMinimumDamage, (int) ceil($enemyAverageAttack - $enemyDamageReduction));
        $enemyHitChance = max(0.58, 1 - (($character->evadeChancePercent() / 100) * 0.65));
        $enemyRoundDamage = max(1, (int) ceil($enemyHitDamage * $pressureEnemyCount * $enemyHitChance));
        $survivalRounds = $currentHealth / $enemyRoundDamage;

        $ratingRatio = $this->publicCharacterCombatRating($character) / $threatScore;
        $roundAdvantage = $survivalRounds - $playerRoundsToWin;
        $ratingContribution = max(-24, min(28, ($ratingRatio - 1) * 34));
        $roundContribution = max(-34, min(28, $roundAdvantage * 5.5));

        $chance = 50
            + $ratingContribution
            + $roundContribution
            + (($healthRatio - 0.72) * 34)
            + min(8, $skillCount * 2)
            + min(5, intdiv($currentSp, 18));

        $chance += $controlBonus;

        if ($pressureEnemyCount >= 3 && $healthRatio < 0.55) {
            $chance -= 6;
        }

        return max(5, min(98, (int) round($chance)));
    }

    /**
     * @return array{enemy_stat_multiplier: float}
     */
    private function manualPreviewDifficultyProfile(?string $difficulty): array
    {
        return match ($difficulty) {
            'easy' => ['enemy_stat_multiplier' => 0.88],
            'hard' => ['enemy_stat_multiplier' => 1.24],
            default => ['enemy_stat_multiplier' => 1.0],
        };
    }

    public function publicAssignmentThreatScore(LocationAreaMonster $assignment): int
    {
        return $this->assignmentThreatScore($assignment);
    }

    public function publicCharacterCombatRating(GameCharacter $character): int
    {
        return $this->characterCombatRating($character);
    }

    public function riskLabel(int $successRate): string
    {
        return match (true) {
            $successRate >= 85 => __('Safe'),
            $successRate >= 65 => __('Fair'),
            $successRate >= 45 => __('Risky'),
            default => __('Dangerous'),
        };
    }

    /**
     * @return Collection<int, LocationAreaMonster>
     */
    private function monsterAssignments(LocationArea $area): Collection
    {
        $area->loadMissing('monsterAssignments.monster');

        return $area->monsterAssignments;
    }
}