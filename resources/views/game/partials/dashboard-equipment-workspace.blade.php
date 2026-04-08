@php
    $articleId = $articleId ?? null;
    $panelClass = trim($panelClass ?? 'game-panel inventory-modern-panel inventory-loadout-panel equipment-panel-compact');
    $panelBadge = $panelBadge ?? __('Affects stats');
@endphp

<article @if ($articleId) id="{{ $articleId }}" @endif class="{{ $panelClass }}">
    <div class="dashboard-section-head dashboard-section-head-compact">
        <div>
            <p class="panel-kicker">{{ __('Equipment') }}</p>
            <h2 class="dashboard-section-title">{{ __('Equipment Window') }}</h2>
        </div>
        <span class="dashboard-section-badge">
            {{ $panelBadge }}
        </span>
    </div>

    <div class="equipment-board mt-6">
        <div class="equipment-board-shell">
            <div class="equipment-board-window">
                <div class="equipment-board-window-head">
                    <span class="equipment-board-window-title">{{ __('Equipment') }}</span>
                    <span class="equipment-board-window-emblem" aria-hidden="true"></span>
                </div>

                <div class="equipment-board-frame">
                    <div class="equipment-board-silhouette" aria-hidden="true"></div>

                    @foreach ($equipmentBoardSlots as $slot => $slotConfig)
                        @php
                            $equippedSlot = $equipmentBySlot->get($slot);
                            $equippedInventoryItem = $equippedSlot?->characterItem;
                            $equippedItem = $equippedInventoryItem?->item;
                        @endphp

                        <button
                            type="button"
                            class="equipment-board-slot {{ $slotConfig['class'] }} {{ $equippedItem ? 'equipment-board-slot-filled' : '' }}"
                            @click="equipmentInspector = '{{ $slot }}'"
                            x-bind:class="{ 'equipment-board-slot-active': equipmentInspector === '{{ $slot }}' }"
                            x-bind:aria-pressed="equipmentInspector === '{{ $slot }}'"
                            aria-label="{{ $slotConfig['label'] }}: {{ $equippedItem ? $equippedInventoryItem->displayName() : __('Nothing equipped.') }}"
                            title="{{ $slotConfig['label'] }}"
                        >
                            <span class="equipment-board-slot-kicker">{{ $slotConfig['label'] }}</span>

                            <span class="equipment-board-art">
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
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="equipment-board-inspector">
                @foreach ($equipmentBoardSlots as $slot => $slotConfig)
                    @php
                        $equippedSlot = $equipmentBySlot->get($slot);
                        $equippedInventoryItem = $equippedSlot?->characterItem;
                        $equippedItem = $equippedInventoryItem?->item;
                    @endphp

                    <section
                        class="equipment-board-detail {{ $equippedItem ? 'equipment-board-detail-filled' : 'equipment-board-detail-empty' }}"
                        x-show="equipmentInspector === '{{ $slot }}'"
                        x-cloak
                    >
                        <div class="equipment-board-detail-head">
                            <div class="equipment-board-detail-preview">
                                @if ($equippedItem)
                                    @if (filled($equippedInventoryItem->iconPath()))
                                        <img
                                            src="{{ asset($equippedInventoryItem->iconPath()) }}"
                                            alt="{{ $equippedInventoryItem->displayName() }}"
                                            class="equipment-board-icon"
                                        >
                                    @else
                                        <span class="equipment-board-fallback">{{ strtoupper(substr($equippedItem->name, 0, 1)) }}</span>
                                    @endif
                                @else
                                    <span class="equipment-board-empty-mark">{{ $slotConfig['empty'] }}</span>
                                @endif
                            </div>

                            <div class="equipment-board-detail-copy">
                                <p class="equipment-board-detail-slot">{{ $slotConfig['label'] }}</p>
                                <p class="equipment-board-name">
                                    {{ $equippedItem ? $equippedInventoryItem->displayName() : __('Nothing equipped.') }}
                                </p>
                                <p class="equipment-board-bonus">
                                    @if ($equippedItem)
                                        @if ($equippedInventoryItem->effectiveBonus('attack_bonus')) +{{ $equippedInventoryItem->effectiveBonus('attack_bonus') }} ATK @endif
                                        @if ($equippedInventoryItem->effectiveBonus('defense_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('defense_bonus') }} DEF @endif
                                        @if ($equippedInventoryItem->effectiveBonus('speed_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('speed_bonus') }} SPD @endif
                                        @if ($equippedInventoryItem->effectiveBonus('health_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('health_bonus') }} HP @endif
                                    @else
                                        {{ __('Select an item in your bag to fill this slot.') }}
                                    @endif
                                </p>
                                @if ($equippedItem)
                                    <p class="equipment-board-detail-meta">
                                        {{ __('Bonuses :current/:slots', [
                                            'current' => $equippedInventoryItem->bonusCount(),
                                            'slots' => $equippedInventoryItem->bonus_slot_limit,
                                        ]) }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        @if ($equippedItem && $equippedInventoryItem->bonusLines() !== [])
                            <div class="equipment-board-detail-chips">
                                @foreach ($equippedInventoryItem->bonusLines() as $bonusLine)
                                    <span class="cozy-chip">{{ $bonusLine }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($equippedItem)
                            <div class="equipment-board-detail-actions">
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
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</article>
