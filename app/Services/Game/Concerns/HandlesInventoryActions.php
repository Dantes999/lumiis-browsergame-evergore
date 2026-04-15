<?php

namespace App\Services\Game\Concerns;

use App\Exceptions\GameActionException;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Models\Skill;
use App\Models\SmithRefineRate;
use App\Models\ShopItem;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use Illuminate\Support\Carbon;

/**
 * Inventory and equipment write operations.
 */
trait HandlesInventoryActions
{
    private const SMITH_SHARD_SLUG = 'lumiis-shard';

    private const ADD_BONUS_ITEM_SLUG = 'soul-imprint';

    private const REROLL_BONUS_ITEM_SLUG = 'soul-reweaver';

    private const LOCK_BONUS_ITEM_SLUG = 'soul-anchor';

    private const FIFTH_BONUS_ITEM_SLUG = 'soul-prism';

    /**
     * Store purchases stay lean and synchronous for now.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function buyItem(GameCharacter $character, array $payload): array
    {
        if (! $character->location->is_safe_zone) {
            throw new GameActionException(__('You can only buy supplies inside a safe town shop.'));
        }

        $serviceArea = $this->serviceArea(
            $character,
            (int) $payload['location_area_id'],
            ['shop', 'weapon-shop', 'armor-shop'],
            __('You can only buy supplies from a town vendor hotspot.'),
        );
        $shop = $serviceArea->shop;

        if ($shop === null) {
            throw new GameActionException(__('That vendor is not set up yet.'));
        }

        /** @var ShopItem|null $shopItem */
        $shopItem = ShopItem::query()
            ->with('item')
            ->where('shop_id', $shop->id)
            ->whereKey((int) $payload['shop_item_id'])
            ->lockForUpdate()
            ->first();

        if ($shopItem === null) {
            throw new GameActionException(__('That offer is not available in this store.'));
        }

        $requestedQuantity = max(1, (int) ($payload['quantity'] ?? 1));

        if ($character->level < $shopItem->item->required_level) {
            throw new GameActionException(__('Your level is too low for that item.'));
        }

        if ($shopItem->item->isEquippable() && ! $shopItem->item->canBeEquippedByRace($character->chosenRace())) {
            throw new GameActionException(__('Your race cannot buy that equipment.'));
        }

        if ($shopItem->stock !== null && $shopItem->stock < 1) {
            throw new GameActionException(__('That item is currently out of stock.'));
        }

        if ($shopItem->stock !== null && $requestedQuantity > (int) $shopItem->stock) {
            throw new GameActionException(__('Only :count x :item are currently in stock.', [
                'count' => number_format((int) $shopItem->stock),
                'item' => $shopItem->item->name,
            ]));
        }

        $totalPrice = (int) $shopItem->price * $requestedQuantity;

        if ($character->yang < $totalPrice) {
            throw new GameActionException($requestedQuantity > 1
                ? __('You do not have enough yang for :count x :item.', [
                    'count' => number_format($requestedQuantity),
                    'item' => $shopItem->item->name,
                ])
                : __('You do not have enough yang for that purchase.'));
        }

        $character->yang -= $totalPrice;
        $this->inventoryService->grant($character, $shopItem->item, $requestedQuantity);

        if ($shopItem->stock !== null) {
            // Stock is locked above so two players cannot both buy the final copy at once.
            $shopItem->decrement('stock', $requestedQuantity);
        }

        return [
            'message' => $requestedQuantity > 1
                ? __('You buy :count x :item from :shop.', [
                    'count' => number_format($requestedQuantity),
                    'item' => $shopItem->item->name,
                    'shop' => $shop->name,
                ])
                : __('You buy :item from :shop.', [
                    'item' => $shopItem->item->name,
                    'shop' => $shop->name,
                ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'yang' => -$totalPrice,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Selling intentionally happens only at town vendors so farming still routes
     * back through the city economy loop instead of becoming a field-side convert button.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function sellItem(GameCharacter $character, array $payload): array
    {
        if (! $character->location->is_safe_zone) {
            throw new GameActionException(__('You can only sell items inside a safe town.'));
        }

        $serviceArea = $this->serviceArea(
            $character,
            (int) $payload['location_area_id'],
            ['shop', 'weapon-shop', 'armor-shop'],
            __('You can only sell items to a town vendor hotspot.'),
        );
        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);
        $item = $inventoryItem->item;

        if ($inventoryItem->isEquipped()) {
            throw new GameActionException(__('Unequip that item before selling it.'));
        }

        $requestedQuantity = max(1, (int) ($payload['quantity'] ?? 1));
        $sellQuantity = $item->stackable
            ? min((int) $inventoryItem->quantity, $requestedQuantity)
            : 1;

        if ($sellQuantity < 1 || $requestedQuantity > (int) $inventoryItem->quantity) {
            throw new GameActionException(__('You cannot sell more copies than you currently carry.'));
        }

        $sellPrice = max(0, (int) $item->sell_price);

        if ($sellPrice < 1) {
            throw new GameActionException(__('That item has no vendor resale value.'));
        }

        $yangEarned = $sellPrice * $sellQuantity;
        $character->yang += $yangEarned;
        $this->inventoryService->consume($inventoryItem, $sellQuantity);

        return [
            'message' => $sellQuantity > 1
                ? __('You sell :count x :item at :vendor for :yang Yang.', [
                    'count' => $sellQuantity,
                    'item' => $item->name,
                    'vendor' => $serviceArea->name,
                    'yang' => number_format($yangEarned),
                ])
                : __('You sell :item at :vendor for :yang Yang.', [
                    'item' => $item->name,
                    'vendor' => $serviceArea->name,
                    'yang' => number_format($yangEarned),
                ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'yang' => $yangEarned,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Smith upgrades are intentionally simple in the first pass:
     * one owned weapon, one Yang cost, one success roll, destruction on failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function upgradeItem(GameCharacter $character, array $payload): array
    {
        if (! $character->location->is_safe_zone) {
            throw new GameActionException(__('Weapon upgrades are only possible at a town smith.'));
        }

        $this->serviceArea(
            $character,
            (int) $payload['location_area_id'],
            ['smith'],
            __('You must stand at the town smith to upgrade equipment.'),
        );

        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);
        $item = $inventoryItem->item;

        if (! $inventoryItem->canBeSmithUpgraded()) {
            throw new GameActionException(__('The smith only upgrades weapons in the current build.'));
        }

        if ($inventoryItem->upgrade_level >= 9) {
            throw new GameActionException(__('That weapon is already at the maximum upgrade level.'));
        }

        $refineRate = SmithRefineRate::forUpgradeLevel($inventoryItem->upgrade_level);

        if ($refineRate === null) {
            throw new GameActionException(__('No smith refine rate is configured for that upgrade step.'));
        }

        $upgradeCost = (int) $refineRate->yang_cost;
        $shardCost = (int) ($refineRate->lumiis_shard_cost ?? 0);

        if ($character->yang < $upgradeCost) {
            throw new GameActionException(__('You do not have enough yang for that upgrade attempt.'));
        }

        $shardStack = $shardCost > 0
            ? $this->inventoryService->stackBySlug($character, self::SMITH_SHARD_SLUG)
            : null;

        if ($shardCost > 0 && ($shardStack === null || (int) $shardStack->quantity < $shardCost)) {
            throw new GameActionException(__('You need :count Lumiis Shards for that upgrade attempt.', [
                'count' => $shardCost,
            ]));
        }

        $character->yang -= $upgradeCost;

        if ($shardCost > 0 && $shardStack !== null) {
            $this->inventoryService->consume($shardStack, $shardCost);
        }

        $successPercent = (int) $refineRate->success_percent;
        $itemLabel = $inventoryItem->displayName();

        if ($this->smithUpgradeSucceeds($successPercent)) {
            $inventoryItem->increment('upgrade_level');
            $inventoryItem->refresh();
            $this->reloadDerivedRelations($character);

            return [
                'message' => __('The smith upgrades :from to :to.', [
                    'from' => $itemLabel,
                    'to' => $inventoryItem->displayName(),
                ]),
                'cooldown_seconds' => 0,
                'rewards' => [
                    'yang' => -$upgradeCost,
                    'lumiis_shards' => -$shardCost,
                ],
                'target_location_id' => $character->location_id,
            ];
        }

        if (! $refineRate->destroy_on_fail) {
            return [
                'message' => __(':item survives the failed smithing attempt, but the upgrade does not hold.', [
                    'item' => $itemLabel,
                ]),
                'cooldown_seconds' => 0,
                'rewards' => [
                    'yang' => -$upgradeCost,
                    'lumiis_shards' => -$shardCost,
                ],
                'target_location_id' => $character->location_id,
            ];
        }

        if ($inventoryItem->equipment !== null) {
            $inventoryItem->equipment->delete();
        }

        $inventoryItem->delete();
        $this->reloadDerivedRelations($character);

        return [
            'message' => __(':item breaks during the smithing attempt and is lost.', [
                'item' => $itemLabel,
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'yang' => -$upgradeCost,
                'lumiis_shards' => -$shardCost,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Consumables are immediate and intentionally bypass the action cooldown.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function useItem(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);
        $item = $inventoryItem->item;

        if ($item->isSkillBook()) {
            return $this->useSkillBook($character, $inventoryItem, $processedAt);
        }

        if (! $item->isConsumable()) {
            throw new GameActionException(__('Only consumables can be used directly from the inventory.'));
        }

        $requestedQuantity = max(1, (int) ($payload['quantity'] ?? 1));
        $useUntilFull = filter_var($payload['use_until_full'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $useUntilFull && $requestedQuantity > (int) $inventoryItem->quantity) {
            throw new GameActionException(__('You cannot use more copies than you currently carry.'));
        }

        [$quantityUsed, $healthRecovered, $spRecovered] = $this->resolveConsumableUse(
            $character,
            $inventoryItem,
            $requestedQuantity,
            $useUntilFull,
        );

        if ($quantityUsed === 0 || ($healthRecovered === 0 && $spRecovered === 0)) {
            throw new GameActionException(__('That consumable would have no effect right now.'));
        }

        $character->health += $healthRecovered;
        $character->sp += $spRecovered;
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        $this->inventoryService->consume($inventoryItem, $quantityUsed);

        $effectSummary = collect([
            $healthRecovered > 0 ? '+'.$healthRecovered.' HP' : null,
            $spRecovered > 0 ? '+'.$spRecovered.' SP' : null,
        ])->filter()->implode(', ');

        $message = $quantityUsed > 1
            ? __('You use :count x :item. :effects', [
                'count' => $quantityUsed,
                'item' => $item->name,
                'effects' => $effectSummary,
            ])
            : __('You use :item. :effects', [
                'item' => $item->name,
                'effects' => $effectSummary,
            ]);

        return [
            'message' => $message,
            'cooldown_seconds' => 0,
            'rewards' => [
                'health' => $healthRecovered,
                'sp' => $spRecovered,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Skill books handle the later half of skill progression.
     *
      * Skill points raise a skill through rank 16 and the last point opens M1.
      * After that, each successful book read advances one mastery stage until the G1 breakthrough.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function useSkillBook(GameCharacter $character, CharacterItem $inventoryItem, Carbon $processedAt): array
    {
        $item = $inventoryItem->item;
        $skillSlug = (string) ($item->skill_slug ?? '');

        /** @var Skill|null $skill */
        $skill = Skill::query()->where('slug', $skillSlug)->first();

        if ($skill === null) {
            throw new GameActionException(__('That skill book is no longer linked to a valid combat skill.'));
        }

        if (! $character->skillsUnlocked()) {
            throw new GameActionException(__('Skill books only make sense after skills unlock at level 5.'));
        }

        if (! $character->hasChosenSkillGroup()) {
            throw new GameActionException(__('Choose your combat school before studying skill books.'));
        }

        if ($skill->race !== $character->chosenRace()) {
            throw new GameActionException(__('That book belongs to another race.'));
        }

        if ($skill->skill_group !== $character->skill_group) {
            throw new GameActionException(__('That book belongs to the other combat school of your race.'));
        }

        /** @var CharacterSkill|null $learnedSkill */
        $learnedSkill = $character->learnedSkills->firstWhere('skill_id', $skill->id);

        if ($learnedSkill === null) {
            throw new GameActionException(__('Learn :skill with skill points before studying its book.', [
                'skill' => $skill->name,
            ]));
        }

        if ($learnedSkill->hasReachedPerfectMastery()) {
            throw new GameActionException(__('That skill already reached perfect mastery.'));
        }

        if ($learnedSkill->needsSoulStoneProgression()) {
            throw new GameActionException(__('The book phase for :skill is complete. Use Soul Stones from G1 onward.', [
                'skill' => $skill->name,
            ]));
        }

        if (! $learnedSkill->needsSkillBookProgression()) {
            throw new GameActionException(__('Use skill points until :skill reaches M1 after rank :rank before this book can help.', [
                'skill' => $skill->name,
                'rank' => MetinClassCatalog::skillBookMinimumRank(),
            ]));
        }

        $cooldownMinutes = MetinClassCatalog::skillBookCooldownMinutes();
        $remainingCooldownMinutes = $learnedSkill->bookCooldownRemainingMinutes($processedAt);

        if ($remainingCooldownMinutes > 0) {
            throw new GameActionException(__('You need to wait :minutes minutes before studying this skill again.', [
                'minutes' => $remainingCooldownMinutes,
            ]));
        }

        $successChance = $learnedSkill->nextBookSuccessChance();
        $success = random_int(1, 100) <= $successChance;
        $previousMasteryRank = (int) $learnedSkill->mastery_rank;
        $learnedSkill->book_available_at = $processedAt->copy()->addMinutes($cooldownMinutes);

        if ($success) {
            $learnedSkill->mastery_progress = min(
                $learnedSkill->requiredBookSuccessesForNextMastery(),
                (int) $learnedSkill->mastery_progress + 1,
            );

            if ((int) $learnedSkill->mastery_progress >= $learnedSkill->requiredBookSuccessesForNextMastery()) {
                if ((int) $learnedSkill->mastery_rank >= 10) {
                    $learnedSkill->grand_mastery_rank = max(1, (int) $learnedSkill->grand_mastery_rank);
                } else {
                    $learnedSkill->mastery_rank = min(10, (int) $learnedSkill->mastery_rank + 1);
                }

                $learnedSkill->mastery_progress = 0;
            }
        }

        $learnedSkill->save();
        $this->inventoryService->consume($inventoryItem, 1);
        $this->reloadDerivedRelations($character);

        /** @var CharacterSkill|null $updatedSkill */
        $updatedSkill = $character->learnedSkills->firstWhere('skill_id', $skill->id);

        return [
            'message' => match (true) {
                ! $success => __('You study :skill, but the lesson does not settle. Try again after the reading cooldown.', [
                    'skill' => $skill->name,
                ]),
                (int) ($updatedSkill?->mastery_rank ?? 0) > $previousMasteryRank => __('You study :skill successfully. It reaches :rank.', [
                    'skill' => $skill->name,
                    'rank' => $updatedSkill?->stageLabel() ?? 'M1',
                ]),
                default => __('You study :skill successfully. It reaches :rank.', [
                    'skill' => $skill->name,
                    'rank' => $updatedSkill?->stageLabel() ?? 'G1',
                ]),
            },
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Boss-drop consumable that promotes one fully-rolled equipment piece from 4 slots to 5.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function unlockItemBonus(GameCharacter $character, array $payload): array
    {
        $targetItem = $this->characterItem($character, (int) $payload['target_character_item_id']);
        $prismStack = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($prismStack->item->slug !== self::FIFTH_BONUS_ITEM_SLUG || ! $prismStack->item->isConsumable()) {
            throw new GameActionException(__('Choose a valid soul prism from your inventory.'));
        }

        if ($prismStack->quantity < 1) {
            throw new GameActionException(__('You no longer carry that soul prism.'));
        }

        $bonus = $this->inventoryService->unlockAdditionalBonus($targetItem, 'boss');
        $this->inventoryService->consume($prismStack, 1);
        $this->reloadDerivedRelations($character);

        return [
            'message' => __('The soul prism unlocks a fifth bonus on :item: :bonus.', [
                'item' => $targetItem->displayName(),
                'bonus' => $bonus?->displayLine() ?? __('new bonus applied'),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'soul_prisms' => -1,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Fills the next empty bonus slot on an owned piece of equipment.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function addItemBonus(GameCharacter $character, array $payload): array
    {
        $targetItem = $this->characterItem($character, (int) $payload['target_character_item_id']);
        $imprintStack = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($imprintStack->item->slug !== self::ADD_BONUS_ITEM_SLUG || ! $imprintStack->item->isConsumable()) {
            throw new GameActionException(__('Choose a valid soul imprint from your inventory.'));
        }

        $bonus = $this->inventoryService->addRandomBonus($targetItem, 'lumiis');
        $this->inventoryService->consume($imprintStack, 1);
        $this->reloadDerivedRelations($character);

        return [
            'message' => __('The soul imprint adds a new bonus to :item: :bonus.', [
                'item' => $targetItem->displayName(),
                'bonus' => $bonus->displayLine(),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'soul_imprints' => -1,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Bonus rerolls always consume one reroll item. Locking one line during the reroll
     * consumes one additional soul anchor and keeps that selected line intact.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function rerollItemBonuses(GameCharacter $character, array $payload): array
    {
        $targetItem = $this->characterItem($character, (int) $payload['target_character_item_id']);
        $rerollStack = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($rerollStack->item->slug !== self::REROLL_BONUS_ITEM_SLUG || ! $rerollStack->item->isConsumable()) {
            throw new GameActionException(__('Choose a valid soul reweaver from your inventory.'));
        }

        $lockedBonusId = filled($payload['locked_bonus_id'] ?? null)
            ? (int) $payload['locked_bonus_id']
            : null;
        $lockStack = null;

        if ($lockedBonusId !== null) {
            $lockStack = $this->inventoryService->stackBySlug($character, self::LOCK_BONUS_ITEM_SLUG);

            if ($lockStack === null || $lockStack->quantity < 1) {
                throw new GameActionException(__('You need a Soul Anchor to lock one bonus during rerolling.'));
            }
        }

        $this->inventoryService->rerollBonuses($targetItem, $lockedBonusId, 'lumiis');
        $this->inventoryService->consume($rerollStack, 1);

        if ($lockStack !== null) {
            $this->inventoryService->consume($lockStack, 1);
        }

        $this->reloadDerivedRelations($character);

        return [
            'message' => $lockedBonusId !== null
                ? __('The soul reweaver rerolls :item while one chosen bonus remains anchored.', [
                    'item' => $targetItem->displayName(),
                ])
                : __('The soul reweaver rerolls all bonuses on :item.', [
                    'item' => $targetItem->displayName(),
                ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'soul_reweavers' => -1,
                'soul_anchors' => $lockStack !== null ? -1 : 0,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Multi-use consumables still resolve fully on the server so the browser cannot over-consume
     * a stack or exceed HP/SP caps by sending crafted quantities.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function resolveConsumableUse(
        GameCharacter $character,
        CharacterItem $inventoryItem,
        int $requestedQuantity,
        bool $useUntilFull,
    ): array {
        $item = $inventoryItem->item;
        $remainingHealth = max(0, $character->effectiveMaxHealth() - $character->health);
        $remainingSp = max(0, $character->effectiveMaxSp() - $character->effectiveCurrentSp());
        $allowedUses = $useUntilFull
            ? (int) $inventoryItem->quantity
            : min((int) $inventoryItem->quantity, $requestedQuantity);
        $usedQuantity = 0;
        $healthRecovered = 0;
        $spRecovered = 0;

        while ($usedQuantity < $allowedUses) {
            $healthThisUse = min((int) $item->heal_amount, $remainingHealth);
            $spThisUse = min((int) ($item->sp_restore ?? 0), $remainingSp);

            if ($healthThisUse === 0 && $spThisUse === 0) {
                break;
            }

            $usedQuantity++;
            $healthRecovered += $healthThisUse;
            $spRecovered += $spThisUse;
            $remainingHealth -= $healthThisUse;
            $remainingSp -= $spThisUse;

            if (! $useUntilFull && $usedQuantity >= $requestedQuantity) {
                break;
            }
        }

        return [$usedQuantity, $healthRecovered, $spRecovered];
    }

    /**
     * Equipment actions reload derived relations immediately because many character stats depend on them.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function equipItem(GameCharacter $character, array $payload): array
    {
        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);
        $item = $inventoryItem->item;

        if (! $item->isEquippable()) {
            throw new GameActionException(__('That inventory item cannot be equipped.'));
        }

        if ($character->level < $item->required_level) {
            throw new GameActionException(__('Your level is too low to equip that item.'));
        }

        if (! $item->canBeEquippedByRace($character->chosenRace())) {
            throw new GameActionException(__('Your race cannot equip that weapon.'));
        }

        if ($inventoryItem->equipment !== null) {
            throw new GameActionException(__('That item is already equipped.'));
        }

        /** @var CharacterEquipment|null $currentlyEquipped */
        $currentlyEquipped = $character->equipment()
            ->with('characterItem.item', 'characterItem.equipment')
            ->where('slot', $item->equipment_slot)
            ->first();

        // The equipped board lives outside the bag grid, so the incoming item must leave its
        // inventory cells before we try to return any older item from that slot.
        $this->inventoryService->detachFromGrid($inventoryItem);

        if ($currentlyEquipped !== null && $currentlyEquipped->characterItem !== null) {
            $this->inventoryService->storeInFirstFreeSlot($character, $currentlyEquipped->characterItem);
            $currentlyEquipped->delete();
        }

        CharacterEquipment::query()->create([
            'game_character_id' => $character->id,
            'character_item_id' => $inventoryItem->id,
            'slot' => $item->equipment_slot,
        ]);

        $this->reloadDerivedRelations($character);

        return [
            'message' => __('You equip :item in the :slot slot.', [
                'item' => $item->name,
                'slot' => $item->equipment_slot,
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function unequipItem(GameCharacter $character, array $payload): array
    {
        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($inventoryItem->equipment === null) {
            throw new GameActionException(__('That item is not currently equipped.'));
        }

        $itemName = $inventoryItem->item->name;
        $this->inventoryService->storeInFirstFreeSlot($character, $inventoryItem);
        $inventoryItem->equipment->delete();

        $this->reloadDerivedRelations($character);

        return [
            'message' => __('You unequip :item and return it to your inventory.', [
                'item' => $itemName,
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function moveInventoryItem(GameCharacter $character, array $payload): array
    {
        $inventoryItem = $this->characterItem($character, (int) $payload['character_item_id']);
        $this->inventoryService->move(
            $character,
            $inventoryItem,
            (int) $payload['inventory_column'],
            (int) $payload['inventory_row'],
        );

        return [
            'message' => __('You rearrange :item inside your inventory.', [
                'item' => $inventoryItem->displayName(),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Central ownership check for inventory-driven actions.
     */
    private function characterItem(GameCharacter $character, int $characterItemId): CharacterItem
    {
        $inventoryItem = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->with(['item', 'equipment'])
            ->find($characterItemId);

        if ($inventoryItem === null) {
            throw new GameActionException(__('That inventory item does not belong to your character.'));
        }

        return $inventoryItem;
    }

    /**
     * Validates that a clicked service hotspot belongs to the current map and supports the requested action.
     *
     * @param  list<string>  $allowedServiceTypes
     */
    private function serviceArea(
        GameCharacter $character,
        int $locationAreaId,
        array $allowedServiceTypes,
        string $failureMessage,
    ): LocationArea {
        /** @var LocationArea|null $serviceArea */
        $serviceArea = $character->location->areas->firstWhere('id', $locationAreaId);

        if ($serviceArea === null || ! $serviceArea->isService()) {
            throw new GameActionException($failureMessage);
        }

        if (! in_array((string) $serviceArea->service_type, $allowedServiceTypes, true)) {
            throw new GameActionException($failureMessage);
        }

        if (! TutorialFlow::areaIsVisible($character, $serviceArea)) {
            throw new GameActionException(__('That town service is still locked for this character.'));
        }

        return $serviceArea;
    }

    /**
     * Isolated for testability and future balancing tweaks.
     */
    protected function smithUpgradeSucceeds(int $successPercent): bool
    {
        if ($successPercent >= 100) {
            return true;
        }

        if ($successPercent <= 0) {
            return false;
        }

        return random_int(1, 100) <= $successPercent;
    }
}
