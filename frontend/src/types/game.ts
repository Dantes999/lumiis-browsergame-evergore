export type RegionKind = 'city' | 'hunting' | 'dungeon';

export type PathDifficulty = 'safe' | 'medium' | 'hard';

export interface PlayerSnapshot {
    id: number;
    name: string;
    level: number;
    experience: number;
    nextLevelExperience: number;
    yang: number;
    health: number;
    maxHealth: number;
    stamina: number;
    maxStamina: number;
    sp: number;
    maxSp: number;
    attack: number;
    defense: number;
    magicAttack?: number;
    unspentSkillPoints?: number;
    unspentStatusPoints?: number;
    race?: string;
    skillGroup?: string | null;
    gameState?: string;
    tutorialStep?: number | null;
    cooldownSeconds?: number;
    actionAvailableAt?: string | null;
    location: {
        slug: string;
        name: string;
    };
}

export interface CharacterSummary {
    id: number;
    name: string;
    level: number;
    race: string;
    location: string | null;
    ongoingBattle: boolean;
}

export interface InventoryItem {
    id: number;
    itemId: number;
    slug: string;
    name: string;
    description: string | null;
    type: string;
    slot: string | null;
    requiredLevel: number;
    stackable: boolean;
    quantity: number;
    sellPrice: number;
    icon: string | null;
    equipped: boolean;
    column: number | null;
    row: number | null;
    upgradeLevel: number;
    bonusSlotLimit: number;
    bonuses: Array<{ id: number; type: string; value: number; label: string }>;
    stats: {
        attack: number;
        defense: number;
        health: number;
        sp: number;
        heal: number;
        stamina: number;
        speed: number;
    };
}

export interface QuestEntry {
    title: string;
    story_text: string;
    description: string;
    feature_unlock_hint: string;
    objective_target: number;
    progress: number;
    progress_percent: number;
    status: string;
    required_level: number;
    character_quest_id: number | null;
    reward_experience: number;
    reward_yang: number;
    reward_item_label: string | null;
    reward_item_quantity: number;
    reward_bonus_description: string | null;
    completed_at_label: string | null;
}

export interface Questline {
    title: string;
    active: QuestEntry[];
    ready_to_claim: QuestEntry[];
    completed: QuestEntry[];
    upcoming: QuestEntry[];
    ready_count: number;
}

export interface SkillOption {
    id: number;
    slug: string;
    name: string;
    description: string;
    requiredLevel: number;
    learned: boolean;
    rank: number;
    stage: string | null;
    canLearn: boolean;
    canAdvance: boolean;
}

export interface ChatMessage {
    id: number;
    sender: string | null;
    recipient?: string | null;
    body: string;
    createdAt: string | null;
    incoming?: boolean;
}

export interface BattleSnapshot {
    id: number;
    round: number;
    encounterName: string;
    encounterType: string;
    status: string;
    player: {
        name: string | null;
        health: number;
        maxHealth: number;
        sp: number;
        maxSp: number;
    };
    enemies: CombatEnemy[];
    log: string[];
    skills: Array<Record<string, unknown>>;
}

export interface GameStatePayload {
    player: PlayerSnapshot | null;
    map: GameMap | null;
    characters: CharacterSummary[];
    inventory: InventoryItem[];
    equipment: Record<string, InventoryItem | null>;
    quests: Questline | null;
    biologist: Record<string, unknown> | null;
    skills: SkillOption[];
    skillGroups: Array<{ key: string; label: string; description: string }>;
    battle: BattleSnapshot | null;
    messages: {
        global: ChatMessage[];
        whispers: ChatMessage[];
    };
    rest: Record<string, unknown> | null;
    tutorial: Record<string, unknown>;
    features: Array<Record<string, unknown>>;
}

export interface MonsterPreview {
    slug: string;
    name: string;
    level: number;
    category: 'monster' | 'lumiis' | 'boss';
    accent: string;
}

export interface RegionPath {
    id: string;
    name: string;
    difficulty: PathDifficulty;
    description: string;
    monsters: MonsterPreview[];
    lumiisChance: number;
    lootModifier: number;
    experienceModifier: number;
    pressureGain: number;
    encounter?: {
        request: 'monster' | 'lumiis' | 'boss';
        family?: string | null;
        difficulty?: 'easy' | 'medium' | 'hard';
    };
}

export interface BossPreview {
    slug: string;
    name: string;
    level: number;
    unlocked: boolean;
    pressure: number;
    pressureTarget: number;
}

export interface MapRegion {
    slug: string;
    name: string;
    kind: RegionKind;
    description: string;
    levelRange: string;
    x: number;
    y: number;
    accent: string;
}

export interface GameMap {
    id: number;
    name: string;
    subtitle: string;
    image: string;
    regions: MapRegion[];
    services?: Array<{ id: number; slug: string; name: string; serviceType: string | null }>;
}

export interface RegionDetail {
    areaId: number;
    slug: string;
    mapSlug: string;
    name: string;
    description: string;
    levelRange: string;
    background: string;
    pressure: number;
    pressureTarget: number;
    paths: RegionPath[];
    families?: Array<{ key: string; label: string; monsters: MonsterPreview[] }>;
    lumiis?: {
        name: string;
        preview: Record<string, unknown> | null;
    };
    previews?: Record<string, unknown>;
    boss: BossPreview;
}

export interface CityNpc {
    id: string;
    areaId?: number;
    slug?: string;
    name: string;
    role: string;
    greeting: string;
    x: number;
    y: number;
    accent: string;
    action: 'shop' | 'dialog' | 'quests';
    shopItems?: Array<{
        id: number;
        name: string;
        slug: string;
        price: number;
        stock: number | null;
        requiredLevel: number;
    }>;
}

export interface CityDetail {
    slug: string;
    mapSlug: string;
    name: string;
    description: string;
    npcs: CityNpc[];
}

export interface CombatEnemy {
    id: string;
    name: string;
    level: number;
    health: number;
    maxHealth: number;
    category: 'monster' | 'lumiis' | 'boss';
    accent: string;
}

export interface CombatSnapshot {
    id: number;
    regionSlug: string;
    pathId: string;
    player: {
        name: string;
        health: number;
        maxHealth: number;
        sp: number;
        maxSp: number;
    };
    enemies: CombatEnemy[];
    log: string[];
    round: number;
    finished: boolean;
    victory: boolean;
    skills?: Array<{ slug?: string; name?: string; sp_cost?: number; cooldown_remaining?: number }>;
}
