<?php

namespace App\Http\Controllers\Auth;

use App\Enums\GameCharacterState;
use App\Http\Controllers\Controller;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\Game\CharacterInventoryService;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registration creates both the user account and the initial game character.
 *
 * The browser flow still feels like a normal Laravel auth screen, but this controller
 * also provisions the MMO-side starting state in the same transaction.
 */
class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'raceOptions' => MetinClassCatalog::races(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CharacterInventoryService $inventoryService): RedirectResponse
    {
        $request->validate([
            'character_name' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/', 'unique:game_characters,name'],
            'race' => ['required', Rule::in(array_keys(MetinClassCatalog::races()))],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request, $inventoryService): User {
            $characterName = $request->string('character_name')->trim()->value();

            // The account keeps a name field for Laravel conventions,
            // but the actual player-facing identity is the character name.
            $user = User::create([
                'name' => $characterName,
                'email' => $request->string('email')->lower()->value(),
                'password' => Hash::make($request->string('password')->value()),
            ]);

            // Alpha currently exposes a single public start path:
            // Map 1 -> Map 2 -> Monkey Dungeon.
            // We therefore place every new player into the same starter town and keep
            // the old realm split out of the visible registration flow.
            $starterLocation = Location::query()->firstOrCreate(
                ['slug' => 'yongan'],
                [
                    'name' => 'Yongan',
                    'region' => 'Shinsoo',
                    'description' => 'Automatically provisioned fallback starter zone.',
                    'threat_level' => 1,
                    'is_safe_zone' => true,
                ],
            );

            // The very first playable character starts with enough baseline stats and Yang
            // to immediately use the text-MMO loop.
            $race = $request->string('race')->value();
            $startingStats = MetinClassCatalog::startingStatsForRace($race);
            $startingVitality = (int) ($startingStats['vitality'] ?? 0);
            $startingIntelligence = (int) ($startingStats['intelligence'] ?? 0);
            $startingHealth = (int) ($startingStats['max_health'] ?? 100) + ($startingVitality * 10);
            $startingSp = (int) ($startingStats['max_sp'] ?? 24) + ($startingIntelligence * 6);

            $character = GameCharacter::create([
                'user_id' => $user->id,
                'location_id' => $starterLocation->id,
                'name' => $characterName,
                // Faction stays as an internal compatibility field for now.
                'faction' => 'Shinsoo',
                'race' => $race,
                'skill_group' => null,
                'game_state' => GameCharacterState::Tutorial,
                'tutorial_step' => 1,
                'level' => 1,
                'experience' => 0,
                'yang' => 150,
                'health' => $startingHealth,
                'max_health' => $startingStats['max_health'] ?? 100,
                'stamina' => 15,
                'max_stamina' => 15,
                'attack' => $startingStats['attack'] ?? 8,
                'defense' => $startingStats['defense'] ?? 6,
                'speed' => $startingStats['speed'] ?? 5,
                'magic_attack' => $startingStats['magic_attack'] ?? 0,
                'vitality' => $startingStats['vitality'] ?? 0,
                'intelligence' => $startingStats['intelligence'] ?? 0,
                'strength' => $startingStats['strength'] ?? 0,
                'dexterity' => $startingStats['dexterity'] ?? 0,
                'sp' => $startingSp,
                'max_sp' => $startingStats['max_sp'] ?? 24,
                'last_active_at' => now(),
                'stamina_updated_at' => now(),
                'health_updated_at' => now(),
                'sp_updated_at' => now(),
                'version' => 1,
            ]);

            $starterItems = Item::query()
                ->whereIn('slug', ['small-red-potion', 'small-blue-potion', 'recruit-blade', 'cotton-armor'])
                ->pluck('id', 'slug');

            if ($starterItems->isNotEmpty()) {
                // Starter loadout is optional. We only grant it when the seeded items exist.
                $inventoryService->grant($character, 'small-red-potion', 3);
                $inventoryService->grant($character, 'recruit-blade');
                $inventoryService->grant($character, 'cotton-armor');
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }
}
