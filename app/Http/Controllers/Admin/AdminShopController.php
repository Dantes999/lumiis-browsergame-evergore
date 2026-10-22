<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShopRequest;
use App\Models\Item;
use App\Models\Shop;
use App\Models\ShopItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin editor for vendor inventories and shop pricing.
 */
class AdminShopController extends Controller
{
    public function index(): View
    {
        return view('admin.shops.index', [
            'shops' => Shop::query()
                ->with(['location', 'locationArea'])
                ->withCount('shopItems')
                ->orderBy('location_id')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function edit(Shop $shop): View
    {
        return view('admin.shops.edit', [
            'shop' => $shop->load(['location', 'locationArea', 'shopItems.item']),
            'itemSlugs' => Item::query()->orderBy('slug')->pluck('slug'),
            'shopItemRows' => $this->shopItemRowsForView(old('shop_item_rows'), $shop->shopItems->sortBy('sort_order')->values()->all()),
        ]);
    }

    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validated();

        $shop->fill(Arr::except($validated, ['shop_item_rows']));
        $shop->save();

        $this->syncShopItems($shop, $validated['shop_item_rows'] ?? []);

        return to_route('admin.shops.edit', $shop)->with('status', 'Shop updated.');
    }

    /**
     * @param  array<int, ShopItem>  $shopItems
     * @return array<int, array<string, mixed>>
     */
    private function shopItemRowsForView(mixed $oldRows, array $shopItems): array
    {
        if (is_array($oldRows)) {
            return collect($oldRows)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'item_slug' => (string) ($row['item_slug'] ?? ''),
                    'price' => max(0, (int) ($row['price'] ?? 0)),
                    'stock' => isset($row['stock']) && $row['stock'] !== ''
                        ? max(0, (int) $row['stock'])
                        : '',
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                ])
                ->values()
                ->all();
        }

        return collect($shopItems)
            ->map(fn (ShopItem $shopItem): array => [
                'item_slug' => (string) $shopItem->item?->slug,
                'price' => max(0, $shopItem->price),
                'stock' => $shopItem->stock ?? '',
                'sort_order' => max(0, $shopItem->sort_order),
            ])
            ->filter(fn (array $row): bool => $row['item_slug'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncShopItems(Shop $shop, array $items): void
    {
        $itemIds = Item::query()->pluck('id', 'slug');
        $keptItemIds = [];

        foreach ($items as $index => $shopItemData) {
            $itemSlug = (string) ($shopItemData['item_slug'] ?? '');
            $itemId = $itemIds[$itemSlug] ?? null;

            if ($itemId === null) {
                throw ValidationException::withMessages([
                    'shop_item_rows' => 'Unknown item_slug at row '.($index + 1).': '.$itemSlug,
                ]);
            }

            $keptItemIds[] = $itemId;

            ShopItem::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'item_id' => $itemId,
                ],
                [
                    'price' => max(0, (int) ($shopItemData['price'] ?? 0)),
                    'stock' => isset($shopItemData['stock']) && $shopItemData['stock'] !== ''
                        ? max(0, (int) $shopItemData['stock'])
                        : null,
                    'sort_order' => max(0, (int) ($shopItemData['sort_order'] ?? (($index + 1) * 10))),
                ],
            );
        }

        $shop->shopItems()
            ->when($keptItemIds !== [], fn ($query) => $query->whereNotIn('item_id', $keptItemIds))
            ->when($keptItemIds === [], fn ($query) => $query)
            ->delete();
    }
}
