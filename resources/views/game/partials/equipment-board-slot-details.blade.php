@php
    $equipmentDescription = $equippedItem
        ? (filled($equippedItem->description) ? $equippedItem->description : __('No description available.'))
        : __('Select an item in your bag to fill this slot.');
    $equipmentPriceLabel = $equippedItem && (int) $equippedItem->sell_price > 0
        ? number_format($equippedItem->sell_price).' '.__('Yang')
        : __('None');
    $primaryEquipmentStat = $equippedItem ? $equippedInventoryItem->primaryEquipmentStat() : null;
    $equipmentPrimaryStatSummary = $primaryEquipmentStat === null
        ? null
        : $primaryEquipmentStat['label'].' +'.$primaryEquipmentStat['value'].' '.$primaryEquipmentStat['suffix'];
@endphp

<div class="equipment-slot-popover-sheet">
    <div class="equipment-slot-popover-head">
        <div class="equipment-slot-popover-preview" aria-hidden="true">
            @if ($equippedItem)
                @if (filled($equippedInventoryItem->iconPath()))
                    <img
                        src="{{ asset($equippedInventoryItem->iconPath()) }}"
                        alt=""
                        class="equipment-board-icon"
                    >
                @else
                    <span class="equipment-board-fallback">{{ strtoupper(substr($equippedItem->name, 0, 1)) }}</span>
                @endif
            @else
                <span class="equipment-board-empty-mark">{{ $slotConfig['empty'] }}</span>
            @endif
        </div>

        <div class="equipment-slot-popover-copy">
            <p class="equipment-slot-popover-label">{{ $slotConfig['label'] }}</p>
            <h3 class="equipment-slot-popover-title">
                {{ $equippedItem ? $equippedInventoryItem->displayName() : __('Nothing equipped.') }}
            </h3>
            <p class="equipment-slot-popover-description">{{ $equipmentDescription }}</p>
        </div>

        <button type="button" class="equipment-slot-popover-close" @click.stop="closeInfo()">
            {{ __('Close') }}
        </button>
    </div>

    @if ($equippedItem)
        <div class="equipment-slot-popover-stats">
            <div class="equipment-slot-popover-stat">
                <span>{{ __('Level') }}</span>
                <strong>{{ $equippedItem->required_level }}</strong>
            </div>

            <div class="equipment-slot-popover-stat">
                <span>{{ __('Price') }}</span>
                <strong>{{ $equipmentPriceLabel }}</strong>
            </div>

            <div class="equipment-slot-popover-stat">
                <span>{{ __('Slots') }}</span>
                <strong>{{ $equippedInventoryItem->bonusCount() }}/{{ $equippedInventoryItem->bonus_slot_limit }}</strong>
            </div>
        </div>

        @if (filled($equipmentPrimaryStatSummary))
            <p class="equipment-slot-popover-meta">{{ $equipmentPrimaryStatSummary }}</p>
        @endif

        @if ($equippedInventoryItem->bonusLines() !== [])
            <div class="equipment-slot-popover-chips">
                @foreach ($equippedInventoryItem->bonusLines() as $bonusLine)
                    <span class="cozy-chip">{{ $bonusLine }}</span>
                @endforeach
            </div>
        @endif

        <div class="equipment-slot-popover-actions">
            <form method="POST" action="{{ route('game.actions.store', ['action' => 'unequip-item']) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="character_item_id" value="{{ $equippedSlot->character_item_id }}">
                @if ($activeFocus)
                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                @endif

                <button type="submit" class="action-button-muted w-full">
                    {{ __('Unequip') }}
                </button>
            </form>

            @include('game.partials.item-bonus-actions', ['inventoryItem' => $equippedInventoryItem])
        </div>
    @endif
</div>
