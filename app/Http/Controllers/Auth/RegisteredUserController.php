<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Game\ActiveCharacterResolver;
use App\Services\Game\CharacterProvisioningService;
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
        $raceOptions = MetinClassCatalog::races();

        // Count existing characters per race and pick the least-used as default.
        $counts = DB::table('game_characters')
            ->select('race', DB::raw('count(*) as cnt'))
            ->groupBy('race')
            ->pluck('cnt', 'race')
            ->toArray();

        $defaultRace = collect(array_keys($raceOptions))
            ->sortBy(fn (string $race) => $counts[$race] ?? 0)
            ->values()
            ->first();

        return view('auth.register', [
            'raceOptions' => $raceOptions,
            'defaultRace' => $defaultRace,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        Request $request,
        CharacterProvisioningService $characterProvisioningService,
        ActiveCharacterResolver $activeCharacterResolver,
    ): RedirectResponse {
        $request->validate([
            'character_name' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/', 'unique:game_characters,name'],
            'race' => ['required', Rule::in(array_keys(MetinClassCatalog::races()))],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        [$user, $character] = DB::transaction(function () use ($request, $characterProvisioningService): array {
            $characterName = $request->string('character_name')->trim()->value();
            $race = $request->string('race')->value();

            // The account keeps a name field for Laravel conventions,
            // but the actual player-facing identity is the character name.
            $user = User::create([
                'name' => $characterName,
                'email' => $request->string('email')->lower()->value(),
                'password' => Hash::make($request->string('password')->value()),
                'locale' => $request->session()->get('app_locale') ?? null,
            ]);

            $character = $characterProvisioningService->create($user, $characterName, $race);

            return [$user, $character];
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $activeCharacterResolver->remember($request, $character);

        return redirect(route('dashboard', absolute: false));
    }
}
