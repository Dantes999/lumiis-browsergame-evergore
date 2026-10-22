<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAreaRequest;
use App\Models\Item;
use App\Models\LocationArea;
use App\Models\LocationAreaMonster;
use App\Models\Monster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Balancing editor for map hotspots and hunting area configuration.
 */
class AdminAreaController extends Controller
{
    public function index(): View
    {
        return view('admin.areas.index', [
            'areas' => LocationArea::query()
                ->with('location')
                ->orderBy('location_id')
                ->orderBy('sort_order')
                ->paginate(25),
        ]);
    }

    public function edit(LocationArea $area): View
    {
        return view('admin.areas.edit', [
            'area' => $area->load(['location', 'monsterAssignments.monster']),
            'itemSlugs' => Item::query()
                ->orderBy('slug')
                ->pluck('slug'),
            'monsterSlugs' => Monster::query()
                ->orderBy('sort_order')
                ->orderBy('slug')
                ->pluck('slug'),
            'monsterLootRows' => $this->lootRowsForView(old('monster_loot_rows'), $area->monster_loot_table ?? []),
            'lumiisLootRows' => $this->lootRowsForView(old('lumiis_loot_rows'), $area->lumiis_loot_table ?? []),
            'bossLootRows' => $this->lootRowsForView(old('boss_loot_rows'), $area->boss_loot_table ?? []),
            'monsterAssignmentRows' => $this->monsterAssignmentRowsForView(
                old('monster_assignment_rows'),
                $area->monsterAssignments->all(),
            ),
        ]);
    }

    public function update(UpdateAreaRequest $request, LocationArea $area): RedirectResponse
    {
        $validated = $request->validated();

        $area->fill(Arr::except($validated, [
            'monster_loot_rows',
            'lumiis_loot_rows',
            'boss_loot_rows',
            'monster_assignment_rows',
        ]));
        $area->monster_loot_table = $this->resolveLootRows($validated['monster_loot_rows'] ?? []);
        $area->lumiis_loot_table = $this->resolveLootRows($validated['lumiis_loot_rows'] ?? []);
        $area->boss_loot_table = $this->resolveLootRows($validated['boss_loot_rows'] ?? []);
        $area->save();

        $this->syncMonsterAssignments(
            $area,
            $this->resolveMonsterAssignmentRows($validated['monster_assignment_rows'] ?? []),
        );

        return to_route('admin.areas.edit', $area)->with('status', 'Area configuration updated.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{slug: string, chance: int, min: int, max: int}>
     */
    private function resolveLootRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $entry): array => [
                'slug' => (string) ($entry['slug'] ?? $entry['item_slug'] ?? ''),
                'chance' => max(0, min(10000, (int) ($entry['chance'] ?? 0))),
                'min' => max(1, (int) ($entry['min'] ?? $entry['quantity_min'] ?? 1)),
                'max' => max(
                    max(1, (int) ($entry['min'] ?? $entry['quantity_min'] ?? 1)),
                    (int) ($entry['max'] ?? $entry['quantity_max'] ?? ($entry['min'] ?? $entry['quantity_min'] ?? 1)),
                ),
            ])
            ->filter(fn (array $row): bool => $row['slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $table
     * @return array<int, array{item_slug: string, chance: int, quantity_min: int, quantity_max: int}>
     */
    private function lootRowsForView(mixed $oldRows, array $table): array
    {
        if (is_array($oldRows)) {
            return collect($oldRows)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'item_slug' => (string) ($row['item_slug'] ?? ''),
                    'chance' => max(0, (int) ($row['chance'] ?? 0)),
                    'quantity_min' => max(1, (int) ($row['quantity_min'] ?? 1)),
                    'quantity_max' => max(
                        max(1, (int) ($row['quantity_min'] ?? 1)),
                        (int) ($row['quantity_max'] ?? ($row['quantity_min'] ?? 1)),
                    ),
                ])
                ->values()
                ->all();
        }

        return collect($table)
            ->map(fn (array $row): array => [
                'item_slug' => (string) ($row['slug'] ?? $row['item_slug'] ?? ''),
                'chance' => max(0, (int) ($row['chance'] ?? 0)),
                'quantity_min' => max(1, (int) ($row['min'] ?? $row['quantity_min'] ?? 1)),
                'quantity_max' => max(
                    max(1, (int) ($row['min'] ?? $row['quantity_min'] ?? 1)),
                    (int) ($row['max'] ?? $row['quantity_max'] ?? ($row['min'] ?? $row['quantity_min'] ?? 1)),
                ),
            ])
            ->filter(fn (array $row): bool => $row['item_slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function resolveMonsterAssignmentRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'monster_slug' => (string) ($row['monster_slug'] ?? ''),
                'weight' => max(1, (int) ($row['weight'] ?? 1)),
                'minimum_character_level' => max(1, (int) ($row['minimum_character_level'] ?? 1)),
                'maximum_character_level' => isset($row['maximum_character_level']) && $row['maximum_character_level'] !== ''
                    ? max(1, (int) $row['maximum_character_level'])
                    : null,
                'pack_size' => max(1, (int) ($row['pack_size'] ?? 1)),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            ])
            ->filter(fn (array $row): bool => $row['monster_slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, LocationAreaMonster>  $assignments
     * @return array<int, array<string, mixed>>
     */
    private function monsterAssignmentRowsForView(mixed $oldRows, array $assignments): array
    {
        if (is_array($oldRows)) {
            return collect($oldRows)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'monster_slug' => (string) ($row['monster_slug'] ?? ''),
                    'weight' => max(1, (int) ($row['weight'] ?? 1)),
                    'minimum_character_level' => max(1, (int) ($row['minimum_character_level'] ?? 1)),
                    'maximum_character_level' => isset($row['maximum_character_level']) && $row['maximum_character_level'] !== ''
                        ? max(1, (int) $row['maximum_character_level'])
                        : '',
                    'pack_size' => max(1, (int) ($row['pack_size'] ?? 1)),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                ])
                ->values()
                ->all();
        }

        return collect($assignments)
            ->map(fn (LocationAreaMonster $assignment): array => [
                'monster_slug' => (string) $assignment->monster?->slug,
                'weight' => max(1, $assignment->weight),
                'minimum_character_level' => max(1, $assignment->minimum_character_level),
                'maximum_character_level' => $assignment->maximum_character_level ?? '',
                'pack_size' => max(1, $assignment->pack_size),
                'sort_order' => max(0, $assignment->sort_order),
            ])
            ->filter(fn (array $row): bool => $row['monster_slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignments
     */
    private function syncMonsterAssignments(LocationArea $area, array $assignments): void
    {
        $monsterIds = Monster::query()->pluck('id', 'slug');
        $keptMonsterIds = [];

        foreach ($assignments as $index => $assignment) {
            $monsterSlug = (string) ($assignment['monster_slug'] ?? '');
            $monsterId = $monsterIds[$monsterSlug] ?? null;

            if ($monsterId === null) {
                throw ValidationException::withMessages([
                    'monster_assignment_rows' => 'Unknown monster_slug at row '.($index + 1).': '.$monsterSlug,
                ]);
            }

            $keptMonsterIds[] = $monsterId;

            LocationAreaMonster::query()->updateOrCreate(
                [
                    'location_area_id' => $area->id,
                    'monster_id' => $monsterId,
                ],
                [
                    'weight' => max(1, (int) ($assignment['weight'] ?? 1)),
                    'minimum_character_level' => max(1, (int) ($assignment['minimum_character_level'] ?? 1)),
                    'maximum_character_level' => isset($assignment['maximum_character_level']) && $assignment['maximum_character_level'] !== ''
                        ? max(1, (int) $assignment['maximum_character_level'])
                        : null,
                    'pack_size' => max(1, (int) ($assignment['pack_size'] ?? 1)),
                    'sort_order' => max(0, (int) ($assignment['sort_order'] ?? (($index + 1) * 10))),
                ],
            );
        }

        $area->monsterAssignments()
            ->when($keptMonsterIds !== [], fn ($query) => $query->whereNotIn('monster_id', $keptMonsterIds))
            ->when($keptMonsterIds === [], fn ($query) => $query)
            ->delete();
    }
}
