import type {
    BattleSnapshot,
    CharacterSummary,
    CityDetail,
    CombatSnapshot,
    GameMap,
    GameStatePayload,
    PlayerSnapshot,
    RegionDetail,
    RegionPath,
} from '@/types/game';
import { api, USE_MOCKS } from './client';

const mockPlayer: PlayerSnapshot = {
    id: 1,
    name: 'UiProofHero',
    level: 2,
    experience: 212,
    nextLevelExperience: 600,
    yang: 200,
    health: 224,
    maxHealth: 224,
    stamina: 20,
    maxStamina: 20,
    sp: 54,
    maxSp: 54,
    attack: 23,
    defense: 16,
    location: {
        slug: 'yongan',
        name: 'Yongan',
    },
};

const mockMap: GameMap = {
    id: 1,
    name: 'Yongan',
    subtitle: 'Map 1 · Shinsoo',
    image: '/images/maps/map1_red.png',
    regions: [
        {
            slug: 'yongan',
            name: 'Yongan',
            kind: 'city',
            description: 'A fortified market town with a stable, smithy and safe message board.',
            levelRange: 'Safe zone',
            x: 50,
            y: 52,
            accent: '#d6a85a',
        },
        {
            slug: 'wildhund-wiese',
            name: 'Wildhund-Wiese',
            kind: 'hunting',
            description: 'Shallow water and reeds hide smaller monsters and weak Lumiis stones.',
            levelRange: 'Lv 1–5',
            x: 24,
            y: 58,
            accent: '#7ea66a',
        },
        {
            slug: 'eber-forst',
            name: 'Eber-Forst',
            kind: 'hunting',
            description: 'Dense undergrowth where boar packs root through the corrupted soil.',
            levelRange: 'Lv 3–7',
            x: 70,
            y: 47,
            accent: '#a77c55',
        },
        {
            slug: 'wolfsbach',
            name: 'Wolfsbach',
            kind: 'hunting',
            description: 'A cold stream cuts through wolf territory. The howls never fully stop.',
            levelRange: 'Lv 5–9',
            x: 38,
            y: 30,
            accent: '#6f8fa6',
        },
        {
            slug: 'pine-ridge',
            name: 'Pine Ridge',
            kind: 'hunting',
            description: 'A wooded ridge where harder patrols and denser Lumiis traces begin.',
            levelRange: 'Lv 7–12',
            x: 79,
            y: 70,
            accent: '#596f5a',
        },
    ],
};

const wildhundRegion: RegionDetail = {
    areaId: 1,
    slug: 'wildhund-wiese',
    mapSlug: 'yongan',
    name: 'Wildhund-Wiese',
    description: 'Open meadow at the edge of the river. The paths here are short, readable and ideal for the first real hunt.',
    levelRange: 'Lv 1–5',
    background: 'meadow',
    pressure: 18,
    pressureTarget: 100,
    paths: [
        {
            id: 'safe',
            name: 'Sicherer Pfad',
            difficulty: 'safe',
            description: 'A trampled trail near the river. Fewer monsters, predictable fights.',
            monsters: [
                { slug: 'wildhund', name: 'Wildhund', level: 3, category: 'monster', accent: '#9a7650' },
            ],
            lumiisChance: 6,
            lootModifier: 1,
            experienceModifier: 1,
            pressureGain: 8,
        },
        {
            id: 'medium',
            name: 'Jägerpfad',
            difficulty: 'medium',
            description: 'Fresh tracks lead deeper into the grass. Hungrier packs, better salvage.',
            monsters: [
                { slug: 'wildhund', name: 'Wildhund', level: 3, category: 'monster', accent: '#9a7650' },
                { slug: 'hungriger-wildhund', name: 'Hungriger Wildhund', level: 4, category: 'monster', accent: '#a85a3f' },
            ],
            lumiisChance: 12,
            lootModifier: 1.25,
            experienceModifier: 1.2,
            pressureGain: 14,
        },
        {
            id: 'hard',
            name: 'Rudelpfad',
            difficulty: 'hard',
            description: 'The pack’s hunting ground. High risk, but the strongest souls gather here.',
            monsters: [
                { slug: 'hungriger-wildhund', name: 'Hungriger Wildhund', level: 4, category: 'monster', accent: '#a85a3f' },
                { slug: 'alpha-wildwolf', name: 'Alpha-Wildwolf', level: 5, category: 'monster', accent: '#7e4d3a' },
                { slug: 'lumiis', name: 'Weathered Lumiis', level: 5, category: 'lumiis', accent: '#8fd4c9' },
            ],
            lumiisChance: 22,
            lootModifier: 1.6,
            experienceModifier: 1.45,
            pressureGain: 22,
        },
    ],
    boss: {
        slug: 'alpha-wildwolf-boss',
        name: 'Alpha of the Weeping Pack',
        level: 8,
        unlocked: false,
        pressure: 18,
        pressureTarget: 100,
    },
};

const eberRegion: RegionDetail = {
    ...wildhundRegion,
    slug: 'eber-forst',
    name: 'Eber-Forst',
    description: 'The forest floor is churned into mud. Boar packs are territorial and hit considerably harder.',
    levelRange: 'Lv 3–7',
    background: 'forest',
    pressure: 34,
    pressureTarget: 120,
    paths: wildhundRegion.paths.map((path, index) => ({
        ...path,
        id: `eber-${path.id}`,
        name: ['Waldrand', 'Wurzelpfad', 'Keilerhain'][index] ?? path.name,
        monsters: [
            { slug: 'keiler', name: 'Keiler', level: 4, category: 'monster', accent: '#8f6a4a' },
            { slug: 'roter-keiler', name: 'Roter Keiler', level: 6, category: 'monster', accent: '#9b4f3d' },
            ...(index === 2 ? [{ slug: 'lumiis', name: 'Cracked Battle Lumiis', level: 7, category: 'lumiis' as const, accent: '#8fd4c9' }] : []),
        ],
        lumiisChance: 8 + index * 7,
        pressureGain: 12 + index * 6,
    })),
    boss: {
        slug: 'keiler-fuerst',
        name: 'Keilerfürst',
        level: 10,
        unlocked: false,
        pressure: 34,
        pressureTarget: 120,
    },
};

const city: CityDetail = {
    slug: 'yongan',
    mapSlug: 'yongan',
    name: 'Yongan',
    description: 'A safe town. Rest, trade and prepare before heading back into the wild.',
    npcs: [
        {
            id: 'innkeeper',
            name: 'Wirtin Mira',
            role: 'Gasthaus',
            greeting: 'Ruh dich aus. Die Straßen draußen werden nicht freundlicher.',
            x: 16,
            y: 58,
            accent: '#c08a4e',
            action: 'dialog',
        },
        {
            id: 'general-store',
            name: 'Händler Boro',
            role: 'Allgemeiner Laden',
            greeting: 'Tränke, Werkzeug, Proviant. Alles, was ein angehender Krieger braucht.',
            x: 34,
            y: 52,
            accent: '#d6a85a',
            action: 'shop',
        },
        {
            id: 'smith',
            name: 'Schmied Han',
            role: 'Schmiede',
            greeting: 'Bring mir Erz und Yang. Der Rest ist Timing.',
            x: 55,
            y: 60,
            accent: '#b86d42',
            action: 'shop',
        },
        {
            id: 'biologist',
            name: 'Biologin Yuna',
            role: 'Forschung',
            greeting: 'Die Lumiis speichern mehr als nur Seelen. Ich will es verstehen.',
            x: 74,
            y: 48,
            accent: '#75a58f',
            action: 'quests',
        },
        {
            id: 'weapon-dealer',
            name: 'Waffenhändlerin Sora',
            role: 'Waffen',
            greeting: 'Eine Klinge ist nur so gut wie die Hand, die sie führt.',
            x: 87,
            y: 62,
            accent: '#9d815f',
            action: 'shop',
        },
    ],
};

const regions: Record<string, RegionDetail> = {
    'wildhund-wiese': wildhundRegion,
    'eber-forst': eberRegion,
    wolfsbach: {
        ...wildhundRegion,
        slug: 'wolfsbach',
        name: 'Wolfsbach',
        levelRange: 'Lv 5–9',
        background: 'river',
        pressure: 52,
        pressureTarget: 140,
    },
    'pine-ridge': {
        ...eberRegion,
        slug: 'pine-ridge',
        name: 'Pine Ridge',
        levelRange: 'Lv 7–12',
        background: 'forest',
        pressure: 64,
        pressureTarget: 160,
    },
};

// JSON cloning also accepts Vue reactive proxies, unlike structuredClone.
const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const wait = (ms = 120) => new Promise((resolve) => window.setTimeout(resolve, ms));

const mockState: GameStatePayload = {
    player: mockPlayer,
    map: mockMap,
    characters: [
        {
            id: mockPlayer.id,
            name: mockPlayer.name,
            level: mockPlayer.level,
            race: 'warrior',
            location: mockPlayer.location.name,
            ongoingBattle: false,
        },
    ],
    inventory: [],
    equipment: {},
    quests: null,
    biologist: null,
    skills: [],
    skillGroups: [],
    battle: null,
    messages: { global: [], whispers: [] },
    rest: null,
    tutorial: { active: false },
    features: [],
};

export async function fetchState(): Promise<GameStatePayload> {
    if (USE_MOCKS) {
        await wait();

        return clone(mockState);
    }

    return api.get<GameStatePayload>('/state');
}

export async function fetchPlayer(): Promise<PlayerSnapshot> {
    const state = await fetchState();

    if (!state.player) {
        throw new Error('Kein aktiver Charakter.');
    }

    return state.player;
}

export async function fetchMap(mapId: number): Promise<GameMap> {
    if (USE_MOCKS) {
        await wait();

        return clone(mockMap);
    }

    const state = await fetchState();

    if (!state.map) {
        throw new Error('Karte konnte nicht geladen werden.');
    }

    // mapId is kept for route compatibility; the API returns the current map.
    void mapId;

    return state.map;
}

export async function fetchCharacters(): Promise<{ characters: CharacterSummary[]; activeCharacterId: number | null; maxCharactersPerAccount: number; races: Array<{ key: string; label: string; description: string }> }> {
    if (USE_MOCKS) {
        await wait();

        return {
            characters: mockState.characters,
            activeCharacterId: mockPlayer.id,
            maxCharactersPerAccount: 4,
            races: [{ key: 'warrior', label: 'Krieger', description: 'Stark und direkt.' }],
        };
    }

    return api.get('/characters');
}

export async function createCharacter(name: string, race: string): Promise<{ message: string; state: GameStatePayload }> {
    if (USE_MOCKS) {
        await wait();

        return { message: 'Charakter erstellt.', state: clone(mockState) };
    }

    return api.post('/characters', { new_character_name: name, new_race: race });
}

export async function selectCharacter(id: number): Promise<{ message: string; state: GameStatePayload }> {
    if (USE_MOCKS) {
        await wait();

        return { message: 'Charakter ausgewählt.', state: clone(mockState) };
    }

    return api.post(`/characters/${id}/select`);
}

export async function updateCharacter(id: number, name: string): Promise<{ message: string; state: GameStatePayload }> {
    if (USE_MOCKS) {
        await wait();

        return { message: 'Charakter umbenannt.', state: clone(mockState) };
    }

    return api.patch(`/characters/${id}`, { character_name: name });
}

export async function deleteCharacter(id: number): Promise<{ message: string; state: GameStatePayload }> {
    if (USE_MOCKS) {
        await wait();

        return { message: 'Charakter gelöscht.', state: clone(mockState) };
    }

    return api.delete(`/characters/${id}`);
}

export interface GameActionResponse {
    message: string;
    outcome?: Record<string, unknown> | null;
    state?: GameStatePayload;
}

export async function runGameAction(action: string, payload: Record<string, unknown> = {}): Promise<GameActionResponse> {
    if (USE_MOCKS) {
        await wait();

        return { message: 'Aktion ausgeführt.', state: clone(mockState) };
    }

    return api.post<GameActionResponse>(`/actions/${action}`, {
        idempotency_key: crypto.randomUUID(),
        ...payload,
    });
}

export async function fetchRegion(slug: string): Promise<RegionDetail> {
    if (USE_MOCKS) {
        await wait();
        const region = regions[slug];

        if (!region) {
            throw new Error(`Unknown region: ${slug}`);
        }

        return clone(region);
    }

    return api.get<RegionDetail>(`/regions/${slug}`);
}

export async function fetchCity(slug: string): Promise<CityDetail> {
    if (USE_MOCKS) {
        await wait();

        if (slug !== city.slug) {
            throw new Error(`Unknown city: ${slug}`);
        }

        return clone(city);
    }

    return api.get<CityDetail>(`/cities/${slug}`);
}

export async function startEncounter(
    areaId: number,
    encounter: RegionPath['encounter'],
    regionSlug: string,
    pathId: string,
): Promise<CombatSnapshot> {
    if (!USE_MOCKS) {
        const response = await runGameAction('start-manual-fight', {
            location_area_id: areaId,
            encounter_request: encounter?.request ?? 'monster',
            monster_family: encounter?.family ?? null,
            monster_difficulty: encounter?.difficulty ?? 'medium',
        });

        if (!response.state?.battle) {
            throw new Error(response.message || 'Kampf konnte nicht gestartet werden.');
        }

        return mapBattleToCombat(response.state.battle, regionSlug, pathId);
    }

    await wait(180);
    const region = regions[regionSlug] ?? wildhundRegion;
    const path = region.paths.find((candidate) => candidate.id === pathId) ?? region.paths[0];
    const monsters = path.monsters.filter((monster) => monster.category !== 'lumiis');
    const enemies = monsters.map((monster, index) => ({
        id: `enemy-${index + 1}`,
        name: monster.name,
        level: monster.level,
        health: 42 + monster.level * 8,
        maxHealth: 42 + monster.level * 8,
        category: monster.category,
        accent: monster.accent,
    }));

    return {
        id: Date.now(),
        regionSlug,
        pathId: path.id,
        player: {
            name: mockPlayer.name,
            health: mockPlayer.health,
            maxHealth: mockPlayer.maxHealth,
            sp: mockPlayer.sp,
            maxSp: mockPlayer.maxSp,
        },
        enemies,
        log: [
            `Du betrittst den ${path.name}.`,
            `Gegner: ${enemies.map((enemy) => enemy.name).join(', ')}.`,
        ],
        round: 1,
        finished: false,
        victory: false,
    };
}

export function mapBattleToCombat(battle: BattleSnapshot, regionSlug: string, pathId: string): CombatSnapshot {
    return {
        id: battle.id,
        regionSlug,
        pathId,
        player: {
            ...battle.player,
            name: battle.player.name ?? 'Player',
        },
        enemies: battle.enemies.map((enemy) => ({
            ...enemy,
            category: enemy.category === 'lumiis' ? 'lumiis' : 'monster',
        })),
        log: battle.log,
        round: battle.round,
        finished: battle.status !== 'ongoing',
        victory: battle.status === 'victory',
        skills: battle.skills as CombatSnapshot['skills'],
    };
}

export async function submitCombatAction(
    combat: CombatSnapshot,
    action: 'attack' | 'skill',
    skillSlug?: string,
): Promise<CombatSnapshot> {
    if (!USE_MOCKS) {
        const target = combat.enemies.find((enemy) => enemy.health > 0);
        const response = await runGameAction(action === 'skill' ? 'battle-use-skill' : 'battle-attack', {
            battle_id: combat.id,
            battle_round: combat.round,
            enemy_id: target?.id,
            ...(action === 'skill' && skillSlug ? { skill_slug: skillSlug } : {}),
        });

        if (!response.state?.battle) {
            const replay = response.outcome?.battle_replay as { victory?: boolean } | undefined;

            return {
                ...combat,
                finished: true,
                victory: replay?.victory ?? combat.enemies.every((enemy) => enemy.health <= 0),
                log: [...combat.log, response.message],
            };
        }

        return mapBattleToCombat(response.state.battle, combat.regionSlug, combat.pathId);
    }

    await wait(140);
    const next = clone(combat);

    if (next.finished) {
        return next;
    }

    const target = next.enemies.find((enemy) => enemy.health > 0);

    if (target) {
        const damage = action === 'skill' ? 24 + Math.floor(Math.random() * 9) : 14 + Math.floor(Math.random() * 7);
        target.health = Math.max(0, target.health - damage);
        next.log.push(
            action === 'skill'
                ? `Runde ${next.round}: Du nutzt eine Fertigkeit gegen ${target.name} (${damage} Schaden).`
                : `Runde ${next.round}: Du greifst ${target.name} an (${damage} Schaden).`,
        );
    }

    if (next.enemies.every((enemy) => enemy.health <= 0)) {
        next.finished = true;
        next.victory = true;
        next.log.push('Sieg. Die Monster sind besiegt.');
    } else {
        const attacker = next.enemies.find((enemy) => enemy.health > 0);

        if (attacker) {
            const damage = 4 + Math.floor(Math.random() * 6);
            next.player.health = Math.max(1, next.player.health - damage);
            next.log.push(`${attacker.name} trifft dich (${damage} Schaden).`);
        }

        next.round += 1;
    }

    return next;
}

