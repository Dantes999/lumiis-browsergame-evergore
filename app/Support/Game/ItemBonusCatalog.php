<?php

namespace App\Support\Game;

use App\Models\Item;
use App\Models\ItemBonusDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Central registry for dynamic equipment bonuses.
 *
 * This keeps the first affix system explicit and readable:
 * - which bonus types exist
 * - which slots may roll them
 * - how strong they may roll
 * - how the UI should label them
 */
class ItemBonusCatalog
{
    /**
     * @var array<string, array{label: string, min: int, max: int, suffix: string, slots: list<string>}>
     */
    private const DEFINITIONS = [
        'vitality' => [
            'label' => 'VIT',
            'min' => 1,
            'max' => 4,
            'suffix' => '',
            'slots' => ['armor', 'offhand'],
        ],
        'intelligence' => [
            'label' => 'INT',
            'min' => 1,
            'max' => 4,
            'suffix' => '',
            'slots' => ['armor', 'offhand'],
        ],
        'strength' => [
            'label' => 'STR',
            'min' => 1,
            'max' => 4,
            'suffix' => '',
            'slots' => ['weapon'],
        ],
        'dexterity' => [
            'label' => 'DEX',
            'min' => 1,
            'max' => 4,
            'suffix' => '',
            'slots' => ['weapon', 'offhand', 'boots'],
        ],
        'max_health' => [
            'label' => 'HP',
            'min' => 18,
            'max' => 52,
            'suffix' => '',
            'slots' => ['armor', 'offhand'],
        ],
        'max_sp' => [
            'label' => 'SP',
            'min' => 6,
            'max' => 18,
            'suffix' => '',
            'slots' => ['armor', 'offhand'],
        ],
        'max_stamina' => [
            'label' => 'STA',
            'min' => 1,
            'max' => 3,
            'suffix' => '',
            'slots' => ['offhand', 'boots', 'mount'],
        ],
        'magic_attack' => [
            'label' => 'Magic ATK',
            'min' => 2,
            'max' => 7,
            'suffix' => '',
            'slots' => ['weapon'],
        ],
        'average_damage' => [
            'label' => 'DSS',
            'min' => 5,
            'max' => 18,
            'suffix' => '%',
            'slots' => ['weapon'],
        ],
        'skill_damage' => [
            'label' => 'FKS',
            'min' => 4,
            'max' => 15,
            'suffix' => '%',
            'slots' => ['weapon'],
        ],
        'crit_chance' => [
            'label' => 'Crit',
            'min' => 1,
            'max' => 4,
            'suffix' => '%',
            'slots' => ['weapon', 'offhand'],
        ],
        'evade_chance' => [
            'label' => 'Evade',
            'min' => 1,
            'max' => 4,
            'suffix' => '%',
            'slots' => ['armor', 'offhand', 'boots'],
        ],
        'monster_damage' => [
            'label' => 'vs Monster',
            'min' => 2,
            'max' => 9,
            'suffix' => '%',
            'slots' => ['weapon', 'mount'],
        ],
        'lumiis_damage' => [
            'label' => 'vs Lumiis',
            'min' => 2,
            'max' => 9,
            'suffix' => '%',
            'slots' => ['weapon', 'mount'],
        ],
        'experience_rate' => [
            'label' => 'EXP',
            'min' => 2,
            'max' => 8,
            'suffix' => '%',
            'slots' => ['boots', 'mount'],
        ],
        'yang_rate' => [
            'label' => 'Yang',
            'min' => 2,
            'max' => 8,
            'suffix' => '%',
            'slots' => ['boots', 'mount'],
        ],
        'loot_rate' => [
            'label' => 'Loot',
            'min' => 2,
            'max' => 8,
            'suffix' => '%',
            'slots' => ['boots', 'mount'],
        ],
    ];

    /**
     * @var list<string>
     */
    private const SUPPORTED_SLOTS = ['weapon', 'offhand', 'armor', 'boots', 'mount'];

    private static ?bool $definitionTableExists = null;

    /**
     * Static fallback definitions. These are seeded into the admin-editable table
     * and remain the safety net for tests, migrations and legacy data.
     *
     * @return array<string, array{label: string, min: int, max: int, suffix: string, slots: list<string>}>
     */
    public static function defaultDefinitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public static function supportedSlots(): array
    {
        return self::SUPPORTED_SLOTS;
    }

    /**
     * Read-only access for consumers that need the currently active metadata.
     *
     * @return array<string, array{label: string, min: int, max: int, suffix: string, slots: list<string>, enabled?: bool, sort_order?: int}>
     */
    public static function definitions(): array
    {
        return self::resolvedDefinitions();
    }

    public static function isKnownType(string $type): bool
    {
        return array_key_exists($type, self::resolvedDefinitions());
    }

    public static function displayLine(string $type, int $value): string
    {
        $definition = self::resolvedDefinitions()[$type] ?? null;

        if ($definition === null) {
            return '+'.$value.' '.strtoupper(str_replace('_', ' ', $type));
        }

        return '+'.$value.$definition['suffix'].' '.$definition['label'];
    }

    /**
     * @return list<string>
     */
    public static function rollableTypesForItem(Item $item): array
    {
        if (! $item->isEquippable()) {
            return [];
        }

        return collect(self::resolvedDefinitions(true))
            ->filter(function (array $definition, string $type) use ($item): bool {
                if (! in_array((string) $item->equipment_slot, $definition['slots'], true)) {
                    return false;
                }

                if (in_array($type, ['average_damage', 'skill_damage'], true)) {
                    return $item->equipment_slot === 'weapon' && $item->required_level >= 30;
                }

                return true;
            })
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Level-30 weapons should feel recognizably Metin2-like on drop.
     *
     * We therefore reserve the first bonus picks for DSS/FKS whenever the weapon tier supports them.
     *
     * @return list<string>
     */
    public static function preferredTypesForItem(Item $item): array
    {
        if ($item->equipment_slot !== 'weapon' || $item->required_level < 30) {
            return [];
        }

        return array_values(array_intersect(
            ['average_damage', 'skill_damage'],
            self::rollableTypesForItem($item),
        ));
    }

    /**
     * Boss drops should feel more exciting than regular monsters, so the source affects how many
     * bonus lines are rolled and how strong each line may be.
     */
    public static function rollCountForSource(string $source): int
    {
        return match ($source) {
            'boss' => random_int(2, 4),
            'lumiis' => random_int(1, 3),
            default => random_int(0, 2),
        };
    }

    public static function rollValue(string $type, string $source): int
    {
        $definition = self::resolvedDefinitions()[$type] ?? null;

        if ($definition === null) {
            return 1;
        }

        $minimum = $definition['min'];
        $maximum = $definition['max'];

        if ($source === 'lumiis') {
            $minimum = min($maximum, $minimum + 1);
        }

        if ($source === 'boss') {
            $minimum = min($maximum, $minimum + 1);
            $maximum += max(1, (int) ceil($definition['max'] * 0.25));
        }

        return $maximum <= $minimum ? $minimum : random_int($minimum, $maximum);
    }

    /**
     * Merge DB-backed overrides into the static defaults.
     *
     * Disabled definitions still remain known for rendering existing bonus lines,
     * but they disappear from the roll pool when `$onlyEnabled` is true.
     *
     * @return array<string, array{label: string, min: int, max: int, suffix: string, slots: list<string>, enabled?: bool, sort_order?: int}>
     */
    private static function resolvedDefinitions(bool $onlyEnabled = false): array
    {
        $definitions = collect(self::DEFINITIONS)
            ->map(fn (array $definition): array => [
                ...$definition,
                'enabled' => true,
                'sort_order' => 0,
            ]);

        if (self::hasDefinitionTable()) {
            foreach (ItemBonusDefinition::cachedMap() as $bonusType => $definition) {
                $definitions[$bonusType] = [
                    'label' => (string) $definition->label,
                    'min' => max(0, (int) $definition->minimum_value),
                    'max' => max(0, (int) $definition->maximum_value),
                    'suffix' => (string) $definition->suffix,
                    'slots' => collect($definition->allowed_slots ?? [])
                        ->map(fn (mixed $slot): string => (string) $slot)
                        ->filter(fn (string $slot): bool => in_array($slot, self::SUPPORTED_SLOTS, true))
                        ->values()
                        ->all(),
                    'enabled' => (bool) $definition->enabled,
                    'sort_order' => (int) $definition->sort_order,
                ];
            }
        }

        if ($onlyEnabled) {
            $definitions = $definitions->filter(fn (array $definition): bool => (bool) ($definition['enabled'] ?? true));
        }

        return $definitions->all();
    }

    private static function hasDefinitionTable(): bool
    {
        if (self::$definitionTableExists === null) {
            self::$definitionTableExists = Schema::hasTable('item_bonus_definitions');
        }

        return self::$definitionTableExists;
    }
}
