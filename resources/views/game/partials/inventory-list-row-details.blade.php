@php
    $priceLabel = (int) $item->sell_price > 0
        ? number_format($item->sell_price).' '.__('Yang')
        : __('None');
    $primaryEquipmentStat = $inventoryItem->primaryEquipmentStat();
    $detailMetaParts = collect([
        __('Type').': '.strtoupper((string) $item->item_type),
        $item->isWeapon() && $item->weaponTypeLabel() !== null ? __('Weapon').': '.$item->weaponTypeLabel() : null,
        $item->isSkillBook() && $linkedSkill !== null ? __('Skill').': '.$linkedSkill['name'] : null,
        $item->isSkillBook() ? __('Book Phase').' M1+' : null,
        $item->isEquippable() ? __('Bonuses :current/:slots', [
            'current' => $inventoryItem->bonusCount(),
            'slots' => $inventoryItem->bonus_slot_limit,
        ]) : null,
    ])->filter()->values();
@endphp

<div class="inventory-list-row-details-grid">
    <div class="inventory-list-row-details-copy">
        <p class="inventory-list-row-details-label">{{ __('Description') }}</p>
        <p class="inventory-list-row-details-text">
            {{ filled($item->description) ? $item->description : __('No description available.') }}
        </p>
    </div>

    <div class="inventory-list-row-details-stats">
        <div class="inventory-list-row-details-stat">
            <span>{{ __('Level') }}</span>
            <strong>{{ $item->required_level }}</strong>
        </div>

        <div class="inventory-list-row-details-stat">
            <span>{{ __('Price') }}</span>
            <strong>{{ $priceLabel }}</strong>
        </div>

        <div class="inventory-list-row-details-stat">
            <span>{{ __('Count') }}</span>
            <strong>{{ number_format($inventoryItem->quantity) }}</strong>
        </div>

        @if ($primaryEquipmentStat !== null)
            <div class="inventory-list-row-details-stat">
                <span>{{ $primaryEquipmentStat['label'] }}</span>
                <strong>+{{ $primaryEquipmentStat['value'] }} {{ $primaryEquipmentStat['suffix'] }}</strong>
            </div>
        @endif
    </div>

    @if ($detailMetaParts->isNotEmpty())
        <p class="inventory-list-row-details-meta">
            {{ $detailMetaParts->implode(' · ') }}
        </p>
    @endif

    @if ($inventoryItem->bonusLines() !== [])
        <div class="inventory-list-row-details-chips">
            @foreach ($inventoryItem->bonusLines() as $bonusLine)
                <span class="cozy-chip">{{ $bonusLine }}</span>
            @endforeach
        </div>
    @endif

    <div class="inventory-list-row-details-notes">
        @if ($isBiologistSupportItem)
            <p class="action-copy">{{ __('Use this item from the Biologist panel in town.') }}</p>
        @elseif ($isSkillSupportMaterial)
            <p class="action-copy">{{ __('Use this item from the skill panel once a skill reaches G1.') }}</p>
        @endif

        @if ($item->isEquippable() && $character->level < $item->required_level)
            <p class="action-cost-warning">{{ __('Required level not reached yet.') }}</p>
        @endif

        @if ($inventorySellEnabled && ! $inventoryItem->isEquipped() && (int) $item->sell_price > 0)
            <p class="action-copy">{{ __('Selling here goes through :vendor.', ['vendor' => $inventorySellVendorArea->name]) }}</p>
        @endif
    </div>

    @if ($item->isConsumable() && ! $item->isSkillBook() && ! $isBiologistSupportItem)
        <div class="inventory-list-row-secondary-actions">
            <form method="POST" action="{{ route('game.actions.store', ['action' => 'use-item']) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                <input type="hidden" name="use_until_full" value="1">
                @if ($activeFocus)
                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                @endif

                <button type="submit" class="action-button-muted w-full">
                    {{ __('Use Until Full') }}
                </button>
            </form>
        </div>
    @endif

    @if ($item->isEquippable())
        <div class="inventory-list-row-workshop">
            @include('game.partials.item-bonus-actions', ['inventoryItem' => $inventoryItem])
        </div>
    @endif
</div>
