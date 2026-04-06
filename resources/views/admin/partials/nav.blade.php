<div class="admin-hub-nav mb-6">
    <a href="{{ route('admin.dashboard') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.dashboard')])>
        Overview
    </a>
    <a href="{{ route('admin.users.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.users.*')])>
        Users
    </a>
    <a href="{{ route('admin.characters.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.characters.*')])>
        Characters
    </a>
    <a href="{{ route('admin.locations.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.locations.*')])>
        Locations
    </a>
    <a href="{{ route('admin.areas.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.areas.*')])>
        Areas
    </a>
    <a href="{{ route('admin.monsters.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.monsters.*')])>
        Monsters
    </a>
    <a href="{{ route('admin.quests.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.quests.*')])>
        Quests
    </a>
    <a href="{{ route('admin.shops.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.shops.*')])>
        Shops
    </a>
    <a href="{{ route('admin.refine-rates.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.refine-rates.*')])>
        Refine Rates
    </a>
    <a href="{{ route('admin.bonus-balance.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.bonus-balance.*')])>
        Bonus Balance
    </a>
    <a href="{{ route('admin.connections.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.connections.*')])>
        Routes
    </a>
    <a href="{{ route('admin.logs.index') }}" @class(['theme-button theme-button-secondary theme-button-compact', 'theme-button-active' => request()->routeIs('admin.logs.*')])>
        Logs
    </a>
</div>
