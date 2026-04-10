<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Refine Rates')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.refine-rates.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="overflow-x-auto theme-table-wrap">
                    <table class="theme-table">
                        <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                            <tr>
                                <th class="px-4 py-3">Upgrade From</th>
                                <th class="px-4 py-3">Success %</th>
                                <th class="px-4 py-3">Yang Cost</th>
                                <th class="px-4 py-3">Shard Cost</th>
                                <th class="px-4 py-3">Destroy On Fail</th>
                                <th class="px-4 py-3">Sort</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rates as $index => $rate)
                                <tr class="border-t border-stone-800">
                                    <td class="px-4 py-3">
                                        +{{ $rate->upgrade_level }}
                                        <input type="hidden" name="rates[{{ $index }}][id]" value="{{ $rate->id }}">
                                        <input type="hidden" name="rates[{{ $index }}][upgrade_level]" value="{{ $rate->upgrade_level }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="rates[{{ $index }}][success_percent]" type="number" :value="old('rates.'.$index.'.success_percent', $rate->success_percent)" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="rates[{{ $index }}][yang_cost]" type="number" :value="old('rates.'.$index.'.yang_cost', $rate->yang_cost)" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="rates[{{ $index }}][lumiis_shard_cost]" type="number" :value="old('rates.'.$index.'.lumiis_shard_cost', $rate->lumiis_shard_cost)" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="hidden" name="rates[{{ $index }}][destroy_on_fail]" value="0">
                                        <input type="checkbox" name="rates[{{ $index }}][destroy_on_fail]" value="1" class="theme-checkbox" @checked(old('rates.'.$index.'.destroy_on_fail', $rate->destroy_on_fail))>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-text-input name="rates[{{ $index }}][sort_order]" type="number" :value="old('rates.'.$index.'.sort_order', $rate->sort_order)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-primary-button>Save Refine Rates</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
