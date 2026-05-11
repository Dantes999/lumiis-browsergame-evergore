@extends('layouts.bootstrap')

@section('content')
  <div class="portal-stage dashboard-portal">
    <style>
      /* Map-centered layout tweaks */
      .map-wrapper { background: linear-gradient(180deg,#e9f5ef,#cfe8dc); border: 1px solid #d0e2d6; height: 68vh; display:flex; align-items:center; justify-content:center; }
      .map-image { max-width:100%; max-height:100%; box-shadow: 0 6px 20px rgba(0,0,0,0.12); }

      /* Mobile: hide map in portrait, show only header */
      @media (orientation: portrait) {
        .dashboard-grid { display: none; }
        .mobile-map-note { display: block; }
      }

      @media (orientation: landscape) {
        .mobile-map-note { display: none; }
      }
    </style>

    <div class="mobile-map-note alert alert-info text-center">Rotate your device to landscape to see the map.</div>

    <div class="dashboard-grid row g-3">
      <aside class="col-12 col-md-2">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Player</h5>
            <p class="mb-1"><strong>{{ $character->name }}</strong></p>
            <p class="text-muted mb-1">{{ $character->location?->name ?? 'Unknown' }}</p>
            <div class="mt-2">
              <a href="{{ route('characters.index') }}" class="btn btn-sm btn-outline-secondary">Change Character</a>
            </div>
          </div>
        </div>
      </aside>

      <main class="col-12 col-md-8">
        <div class="map-wrapper">
          <img src="/images/map-placeholder.png" alt="Map" class="map-image" onerror="this.style.background='linear-gradient(180deg,#bfe6ff,#9fcfff)'; this.remove();" />
          <!-- If no image exists the wrapper provides a pleasant background. -->
        </div>
      </main>

      <aside class="col-12 col-md-2">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Quick Actions</h5>
              <div class="d-grid gap-2">
              <button type="button" class="btn btn-sm btn-outline-primary" data-open-panel="inventory">Inventory</button>
              <button type="button" class="btn btn-sm btn-outline-primary" data-open-panel="equipment">Equipment</button>
              <button type="button" class="btn btn-sm btn-outline-primary" data-open-panel="quests">Quests</button>
            </div>
          </div>
        </div>
      </aside>
    </div>
    {{-- Lightweight, Bootstrap-only overlay stubs. Hidden by default and toggled with simple JS. --}}
    <section id="dashboard-equipment-overlay" class="dashboard-overlay-panel dashboard-overlay-panel-inventory dashboard-map-overlay-panel dashboard-map-overlay-panel-inventory d-none">
      <div class="dashboard-overlay-backdrop"></div>

      <div class="dashboard-overlay-window dashboard-overlay-window-inventory dashboard-map-overlay-window">
        <div class="dashboard-overlay-head dashboard-overlay-head-inventory">
          <div>
            <p class="panel-kicker">Equipment</p>
            <h2 class="dashboard-overlay-title">Equipment</h2>
          </div>

          <div class="dashboard-overlay-head-actions">
            <button type="button" class="dashboard-overlay-close">Close</button>
          </div>
        </div>

        <div class="dashboard-overlay-scroll dashboard-overlay-scroll-inventory">
          <div class="inventory-workspace">
            <div class="card"><div class="card-body">Equipment workspace (stub)</div></div>
          </div>
        </div>
      </div>
    </section>

    <section id="tutorial-inventory-overlay" class="dashboard-overlay-panel dashboard-overlay-panel-inventory dashboard-map-overlay-panel dashboard-map-overlay-panel-inventory d-none">
      <div class="dashboard-overlay-backdrop"></div>

      <div class="dashboard-overlay-window dashboard-overlay-window-inventory dashboard-map-overlay-window">
        <div class="dashboard-overlay-head dashboard-overlay-head-inventory">
          <div>
            <p class="panel-kicker">Inventory</p>
            <h2 class="dashboard-overlay-title">Inventory</h2>
            <p class="dashboard-overlay-subtitle">Manage bag items, consumables and sell-ready drops.</p>
          </div>

          <div class="dashboard-overlay-head-actions">
            <button type="button" class="dashboard-overlay-close">Close</button>
          </div>
        </div>

        <div class="dashboard-overlay-scroll dashboard-overlay-scroll-inventory">
          <div class="card"><div class="card-body">Inventory list (stub)</div></div>
        </div>
      </div>
    </section>

  </div>

@endsection
