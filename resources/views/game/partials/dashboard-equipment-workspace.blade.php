@php
    $articleId = $articleId ?? null;
    $panelClass = trim($panelClass ?? 'game-panel inventory-modern-panel inventory-loadout-panel equipment-panel-compact');
    $panelBadge = $panelBadge ?? __('Affects stats');
    $showPanelHead = $showPanelHead ?? true;
@endphp

<article @if ($articleId) id="{{ $articleId }}" @endif class="{{ $panelClass }}">
    @if ($showPanelHead)
        <div class="dashboard-section-head dashboard-section-head-compact">
            <div>
                <p class="panel-kicker">{{ __('Equipment') }}</p>
                <h2 class="dashboard-section-title">{{ __('Equipment Window') }}</h2>
            </div>
            <span class="dashboard-section-badge">
                {{ $panelBadge }}
            </span>
        </div>
    @endif

    <div
        class="{{ $showPanelHead ? 'equipment-board mt-6' : 'equipment-board' }}"
        x-data="equipmentBoard()"
        @keydown.escape.window="closeInfo()"
        @click.outside="closeInfo()"
    >
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
                            $detailSide = in_array($slot, ['offhand', 'mount'], true) ? 'left' : 'right';
                        @endphp

                        <div
                            class="equipment-board-slot-card {{ $slotConfig['class'] }}"
                            x-bind:class="{ 'equipment-board-slot-card-open': isInfoOpen('{{ $slot }}') }"
                        >
                            <button
                                type="button"
                                class="equipment-board-slot {{ $equippedItem ? 'equipment-board-slot-filled' : '' }}"
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

                            <button
                                type="button"
                                class="equipment-board-slot-info-toggle"
                                @click.stop="toggleInfo('{{ $slot }}')"
                                x-bind:aria-expanded="isInfoOpen('{{ $slot }}')"
                            >
                                {{ __('Info') }}
                            </button>

                            <div class="equipment-board-slot-popover equipment-board-slot-popover-{{ $detailSide }}">
                                @include('game.partials.equipment-board-slot-details', [
                                    'slot' => $slot,
                                    'slotConfig' => $slotConfig,
                                    'equippedSlot' => $equippedSlot,
                                    'equippedInventoryItem' => $equippedInventoryItem,
                                    'equippedItem' => $equippedItem,
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</article>
