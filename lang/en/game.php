<?php

return [
    'tutorial' => [
        'steps' => [
            1 => [
                'title' => 'Welcome to Lumiis',
                'body' => 'Lumiis stones have trapped the souls of this land. The Character button in the top bar opens your character window with health, stamina, stats and your current map.',
                'next_label' => 'Show the map',
                'previous_label' => null,
            ],
            2 => [
                'title' => 'Read the map first',
                'body' => 'For now the map only shows the inn and one beginner hunting area. This keeps the first session small and readable.',
                'next_label' => 'Go to the city',
                'previous_label' => 'Back to character view',
            ],
            3 => [
                'title' => 'This is your general store',
                'body' => 'The general store is your safe fallback. Potions come from here when you need to recover between fights.',
                'next_label' => 'Back to the map',
                'previous_label' => 'Back to map overview',
            ],
            4 => [
                'title' => 'Town and field each have a role',
                'body' => 'Town is for rest and supplies. Hunting areas are where you fight monster groups, crack Lumiis stones and build pressure toward the boss.',
                'next_label' => 'Inspect the first hunting area',
                'previous_label' => 'Back to the store',
            ],
            5 => [
                'title' => 'This is your first hunting ground',
                'body' => 'Monster groups are the fast loop. Lumiis stones release trapped souls in waves. Bosses appear only after you have stirred enough pressure in the area.',
                'next_label' => 'Prepare the first fight',
                'previous_label' => 'Back to the map',
            ],
            6 => [
                'title' => 'Equip your starter gear',
                'body' => 'Use the Inventory button in the top bar to open your equipment window, then equip your Recruit Blade and Cotton Armor before the first real fight.',
                'next_label' => 'Ready for the fight',
                'previous_label' => 'Back to the hunting area intro',
            ],
            7 => [
                'title' => 'Start the tutorial fight',
                'body' => 'We use a manual monster fight here so every important battle control can be introduced in a safe, guaranteed-win setup.',
                'next_label' => null,
                'previous_label' => 'Back to your gear',
            ],
            8 => [
                'title' => 'Read the fight window first',
                'body' => 'Before your first hit, read the battle UI: your stats are on the left, the active log is in the middle, and the enemy pack with attack buttons is on the right.',
                'next_label' => 'Now attack',
                'previous_label' => null,
            ],
            9 => [
                'title' => 'Attack one target',
                'body' => 'Pick one enemy and attack it. Your strike also splashes reduced damage onto the rest of the pack.',
                'next_label' => null,
                'previous_label' => null,
            ],
            10 => [
                'title' => 'Finish the fight',
                'body' => 'Keep attacking until every monster falls. The tutorial pack is weaker than normal so the first manual battle stays readable.',
                'next_label' => null,
                'previous_label' => null,
            ],
            11 => [
                'title' => 'This is how rewards arrive',
                'body' => 'Victory grants Yang and EXP immediately. The result window summarizes rounds, damage and item drops in one place.',
                'next_label' => 'Explain the result window',
                'previous_label' => null,
            ],
            12 => [
                'title' => 'Tutorial complete',
                'body' => 'Your character was healed in the background so you can start the real game flow immediately. From now on the map and menus open fully as you level up.',
                'next_label' => 'Enter the game',
                'previous_label' => 'Back to reward summary',
            ],
        ],
    ],
];
