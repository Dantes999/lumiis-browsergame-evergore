@php
    $showBonusWorkshop = $inventoryItem->item?->isEquippable() === true;
    $canAddBonus = $inventoryItem->hasOpenBonusSlot();
    $canRerollBonuses = $inventoryItem->canRerollBonuses();
    $canUnlockFifthBonus = $inventoryItem->canUnlockFifthBonus();
    $lockableBonuses = $inventoryItem->bonuses ?? collect();
@endphp

@if ($showBonusWorkshop)
    <div class="mt-3 space-y-2">
        @if ($canAddBonus)
            <form method="POST" action="{{ route('game.actions.store', ['action' => 'add-item-bonus']) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="character_item_id" value="{{ $soulImprintStack?->id }}">
                <input type="hidden" name="target_character_item_id" value="{{ $inventoryItem->id }}">
                @if ($activeFocus)
                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                @endif

                <button type="submit" class="action-button w-full" @disabled($soulImprintCount < 1)>
                    {{ __('Add Bonus') }}
                </button>
            </form>

            @if ($soulImprintCount < 1)
                <p class="action-cost-warning">{{ __('Requires 1 Soul Imprint to add a random bonus.') }}</p>
            @endif
        @endif

        @if ($canRerollBonuses)
            <form method="POST" action="{{ route('game.actions.store', ['action' => 'reroll-item-bonuses']) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="character_item_id" value="{{ $soulReweaverStack?->id }}">
                <input type="hidden" name="target_character_item_id" value="{{ $inventoryItem->id }}">
                @if ($activeFocus)
                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                @endif

                @if ($lockableBonuses->count() > 1)
                    <div class="space-y-1">
                        <label class="text-[0.62rem] font-semibold uppercase tracking-[0.2em] text-stone-700" for="locked-bonus-{{ $inventoryItem->id }}">
                            {{ __('Locked Bonus') }}
                        </label>
                        <select id="locked-bonus-{{ $inventoryItem->id }}" name="locked_bonus_id" class="inventory-use-quantity w-full">
                            <option value="">{{ __('No lock') }}</option>
                            @foreach ($lockableBonuses as $bonus)
                                <option value="{{ $bonus->id }}">{{ $bonus->displayLine() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" class="action-button-muted w-full" @disabled($soulReweaverCount < 1)>
                    {{ __('Reroll Bonuses') }}
                </button>
            </form>

            @if ($soulReweaverCount < 1)
                <p class="action-cost-warning">{{ __('Requires 1 Soul Reweaver to reroll bonuses.') }}</p>
            @elseif ($lockableBonuses->count() > 1 && $soulAnchorCount < 1)
                <p class="action-copy">{{ __('Choose a locked bonus only if you also carry a Soul Anchor.') }}</p>
            @endif
        @endif

        @if ($canUnlockFifthBonus)
            <form method="POST" action="{{ route('game.actions.store', ['action' => 'unlock-item-bonus']) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="character_item_id" value="{{ $soulPrismStack?->id }}">
                <input type="hidden" name="target_character_item_id" value="{{ $inventoryItem->id }}">
                @if ($activeFocus)
                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                @endif

                <button type="submit" class="action-button w-full" @disabled($soulPrismCount < 1)>
                    {{ __('Unlock 5th Bonus') }}
                </button>
            </form>

            @if ($soulPrismCount < 1)
                <p class="action-cost-warning">{{ __('Requires 1 Soul Prism from a boss drop.') }}</p>
            @endif
        @endif
    </div>
@endif
