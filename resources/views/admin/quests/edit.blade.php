<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Quest</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.quests.update', $quest) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <x-input-label for="slug" value="Slug" />
                        <x-text-input id="slug" name="slug" class="mt-2" :value="old('slug', $quest->slug)" required />
                    </div>
                    <div>
                        <x-input-label for="title" value="Title" />
                        <x-text-input id="title" name="title" class="mt-2" :value="old('title', $quest->title)" required />
                    </div>
                    <div>
                        <x-input-label for="location_id" value="Location" />
                        <select id="location_id" name="location_id" class="theme-input mt-2">
                            <option value="">Global</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id', $quest->location_id) === (string) $location->id)>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="required_level" value="Required Level" />
                        <x-text-input id="required_level" name="required_level" type="number" class="mt-2" :value="old('required_level', $quest->required_level)" required />
                    </div>
                </div>

                <div>
                    <x-input-label for="story_text" value="Story Text" />
                    <textarea id="story_text" name="story_text" rows="5" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400" required>{{ old('story_text', $quest->story_text) }}</textarea>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">{{ old('description', $quest->description) }}</textarea>
                </div>

                <div>
                    <x-input-label for="feature_unlock_hint" value="Unlock Hint" />
                    <x-text-input id="feature_unlock_hint" name="feature_unlock_hint" class="mt-2" :value="old('feature_unlock_hint', $quest->feature_unlock_hint)" />
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <x-input-label for="prerequisite_slug" value="Prerequisite Quest" />
                        <select id="prerequisite_slug" name="prerequisite_slug" class="theme-input mt-2">
                            <option value="">None</option>
                            @foreach ($prerequisiteOptions as $slug)
                                <option value="{{ $slug }}" @selected(old('prerequisite_slug', $quest->prerequisite_slug) === $slug)>
                                    {{ $slug }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="objective_type" value="Objective Type" />
                        <select id="objective_type" name="objective_type" class="theme-input mt-2">
                            @foreach ($objectiveTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('objective_type', $quest->objective_type) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="objective_target" value="Objective Target" />
                        <x-text-input id="objective_target" name="objective_target" type="number" class="mt-2" :value="old('objective_target', $quest->objective_target)" required />
                    </div>
                    <div>
                        <x-input-label for="objective_area_slug" value="Objective Area" />
                        <select id="objective_area_slug" name="objective_area_slug" class="theme-input mt-2">
                            <option value="">None</option>
                            @foreach ($areaSlugOptions as $areaSlug)
                                <option value="{{ $areaSlug }}" @selected(old('objective_area_slug', $quest->objective_area_slug) === $areaSlug)>
                                    {{ $areaSlug }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <x-input-label for="objective_item_slug" value="Objective Item" />
                        <select id="objective_item_slug" name="objective_item_slug" class="theme-input mt-2">
                            <option value="">None</option>
                            @foreach ($itemSlugOptions as $itemSlug)
                                <option value="{{ $itemSlug }}" @selected(old('objective_item_slug', $quest->objective_item_slug) === $itemSlug)>
                                    {{ $itemSlug }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="objective_item_quantity" value="Qty Per Turn-In" />
                        <x-text-input id="objective_item_quantity" name="objective_item_quantity" type="number" class="mt-2" :value="old('objective_item_quantity', $quest->objective_item_quantity ?? 1)" />
                    </div>
                    <div>
                        <x-input-label for="objective_submission_cooldown_minutes" value="Submit Cooldown (min)" />
                        <x-text-input id="objective_submission_cooldown_minutes" name="objective_submission_cooldown_minutes" type="number" class="mt-2" :value="old('objective_submission_cooldown_minutes', $quest->objective_submission_cooldown_minutes ?? 0)" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <x-input-label for="objective_submission_success_rate" value="Submit Success %" />
                        <x-text-input id="objective_submission_success_rate" name="objective_submission_success_rate" type="number" class="mt-2" :value="old('objective_submission_success_rate', $quest->objective_submission_success_rate ?? 100)" />
                    </div>
                    <div class="md:col-span-2 paper-note">
                        <p class="text-sm text-stone-800">
                            A failed Biologist turn-in still consumes the sample and starts the submission cooldown.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <x-input-label for="reward_experience" value="Reward EXP" />
                        <x-text-input id="reward_experience" name="reward_experience" type="number" class="mt-2" :value="old('reward_experience', $quest->reward_experience)" required />
                    </div>
                    <div>
                        <x-input-label for="reward_yang" value="Reward Yang" />
                        <x-text-input id="reward_yang" name="reward_yang" type="number" class="mt-2" :value="old('reward_yang', $quest->reward_yang)" required />
                    </div>
                    <div class="xl:col-span-2">
                        <x-input-label for="reward_item_slug" value="Reward Item" />
                        <select id="reward_item_slug" name="reward_item_slug" class="theme-input mt-2">
                            <option value="">None</option>
                            @foreach ($itemSlugOptions as $itemSlug)
                                <option value="{{ $itemSlug }}" @selected(old('reward_item_slug', $quest->reward_item_slug) === $itemSlug)>
                                    {{ $itemSlug }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="reward_item_quantity" value="Reward Qty" />
                        <x-text-input id="reward_item_quantity" name="reward_item_quantity" type="number" class="mt-2" :value="old('reward_item_quantity', $quest->reward_item_quantity)" required />
                    </div>
                    <div>
                        <x-input-label for="reward_bonus_type" value="Permanent Bonus" />
                        <select id="reward_bonus_type" name="reward_bonus_type" class="theme-input mt-2">
                            <option value="">None</option>
                            @foreach ($rewardBonusTypes as $bonusType => $bonusLabel)
                                <option value="{{ $bonusType }}" @selected(old('reward_bonus_type', $quest->reward_bonus_type) === $bonusType)>
                                    {{ $bonusLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="reward_bonus_value" value="Bonus Value" />
                        <x-text-input id="reward_bonus_value" name="reward_bonus_value" type="number" class="mt-2" :value="old('reward_bonus_value', $quest->reward_bonus_value ?? 0)" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <x-input-label for="sort_order" value="Sort Order" />
                        <x-text-input id="sort_order" name="sort_order" type="number" class="mt-2" :value="old('sort_order', $quest->sort_order)" required />
                    </div>
                    <div class="paper-note">
                        <label class="theme-checkbox-label flex items-center gap-3 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="theme-checkbox" @checked(old('is_active', $quest->is_active))>
                            Quest is active
                        </label>
                    </div>
                </div>

                <x-primary-button>Save Quest</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
