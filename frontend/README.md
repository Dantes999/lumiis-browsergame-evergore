# Lumiis Frontend

Eigenständige Vue-3-SPA für das Spiel. Laravel bleibt die autoritative API und behält Admin/Auth.

## Start

```bash
cd frontend
npm install
npm run dev
```

Öffnet `http://127.0.0.1:5174`.

Laravel muss parallel auf `http://127.0.0.1:8000` laufen:

```bash
composer dev
```

Vite proxyt `/api` und `/images` nach Laravel.

## Login

Die SPA nutzt die bestehende Laravel-Session. Beim ersten Aufruf ohne Session erscheint
ein Login-Link. Nach dem Login leitet Laravel auf die SPA weiter.

## API

Die SPA spricht ausschließlich JSON:

```text
GET    /api/v1/state
GET    /api/v1/characters
POST   /api/v1/characters
PATCH  /api/v1/characters/{id}
DELETE /api/v1/characters/{id}
POST   /api/v1/characters/{id}/select
GET    /api/v1/regions/{slug}
GET    /api/v1/cities/{slug}
POST   /api/v1/actions/{action}
```

`/actions/{action}` nutzt denselben `ProcessGameAction`-Service wie das alte Blade-Frontend.
Die SPA berechnet keine Kampf-, Loot- oder Wirtschaftslogik.

## Umgesetzte Spielfunktionen

- Charakterauswahl, Erstellung, Umbenennen, Löschen
- Map-first Regionsansicht
- Auto Fight, Lumiis-Suche, manueller Lumiis-Kampf
- Manuelle Pfade + Boss-Kampf
- Kampf: Angriff, Skill, Flucht/Abandon, Log
- Inventar: Benutzen, Ausrüsten, Ablegen, Verkaufen, Schmieden, Bewegen
- Item-Boni: Hinzufügen, Reroll, Lock, 5. Slot
- Quests: Claim, Biologist-Abgabe, Katalysator, Report
- Stadt-Shops
- Charakterwerte, Statuspunkte, Skillgruppen, Skills lernen/verbessern
- Global-Chat und Flüstern
- Rasten (30/60/120 Minuten)
- Tutorial-Schritte vor/zurück

## Altes Frontend

Die alten Spielseiten `/dashboard`, `/game` und `/characters` leiten außerhalb der
Testumgebung auf die SPA um. Login/Registrierung/Profil und das Admin-Panel bleiben
zunächst in Laravel.

## Build

```bash
npm run build
```

Das Ergebnis liegt unter `frontend/dist`.

Für die Auslieferung über Laravel:

```bash
bash scripts/build-frontend.sh
```

Das baut die SPA mit `VITE_BASE_PATH=/play/` und kopiert sie nach `public/play`.
Danach ist sie unter `http://127.0.0.1:8000/play` erreichbar. Produktion nutzt
`FRONTEND_URL=https://deine-domain/play`.
