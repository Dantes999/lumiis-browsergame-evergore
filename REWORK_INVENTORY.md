# Rework Inventory — lumiis-new

Generated: 2026-05-08
Purpose: Full inventory of pages, templates, controllers, routes and frontend assets to guide the Bootstrap rework.

---

## Top-level summary
- Main public landing: `welcome.blade.php`
- Auth pages: login/register/forgot-password/reset/verify/confirm
- Game: `dashboard.blade.php` with many `game.partials/*` partials (map, overlays, equipment, inventory, quests, skills)
- Character selection: `characters/index.blade.php` + `CharacterSelectionController`
- Admin area: `resources/views/admin/**` + Admin controllers under `app/Http/Controllers/Admin/*`
- Frontend stack: TailwindCSS + Alpine.js; Vite build (see `package.json`)

---

## Blade views (resources/views)
(Complete list found in repository)

- resources/views/dashboard.blade.php
- resources/views/partials/metin2-header.blade.php
- resources/views/partials/metin2-footer.blade.php
- resources/views/auth/login.blade.php
- resources/views/auth/register.blade.php
- resources/views/auth/forgot-password.blade.php
- resources/views/partials/metin2-side-nav.blade.php
- resources/views/admin/users/index.blade.php
- resources/views/admin/users/edit.blade.php
- resources/views/profile/partials/delete-user-form.blade.php
- resources/views/profile/partials/update-profile-information-form.blade.php
- resources/views/profile/partials/update-password-form.blade.php
- resources/views/profile/edit.blade.php
- resources/views/admin/shops/index.blade.php
- resources/views/admin/shops/partials/shop-item-editor.blade.php
- resources/views/admin/shops/edit.blade.php
- resources/views/admin/locations/index.blade.php
- resources/views/admin/locations/edit.blade.php
- resources/views/admin/monsters/index.blade.php
- resources/views/admin/monsters/edit.blade.php
- resources/views/admin/connections/index.blade.php
- resources/views/admin/connections/edit.blade.php
- resources/views/admin/partials/nav.blade.php
- resources/views/admin/partials/flash.blade.php
- resources/views/admin/areas/index.blade.php
- resources/views/admin/areas/partials/monster-assignment-editor.blade.php
- resources/views/admin/areas/partials/loot-editor.blade.php
- resources/views/admin/areas/edit.blade.php
- resources/views/admin/bonus-balance/index.blade.php
- resources/views/admin/dashboard.blade.php
- resources/views/admin/characters/index.blade.php
- resources/views/admin/characters/edit.blade.php
- resources/views/admin/quests/index.blade.php
- resources/views/admin/quests/edit.blade.php
- resources/views/admin/logs/index.blade.php
- resources/views/admin/refine-rates/index.blade.php
- resources/views/welcome.blade.php
- resources/views/auth/partials/login-panel.blade.php
- resources/views/errors/403.blade.php
- resources/views/errors/404.blade.php
- resources/views/errors/503.blade.php
- resources/views/errors/500.blade.php
- resources/views/errors/game.blade.php
- resources/views/errors/419.blade.php
- resources/views/layouts/guest.blade.php
- resources/views/layouts/navigation.blade.php
- resources/views/layouts/app.blade.php
- resources/views/auth/verify-email.blade.php
- resources/views/auth/confirm-password.blade.php
- resources/views/components/dropdown-link.blade.php
- resources/views/components/application-logo.blade.php
- resources/views/components/nav-link.blade.php
- resources/views/components/dropdown.blade.php
- resources/views/components/danger-button.blade.php
- resources/views/components/page-heading.blade.php
- resources/views/components/secondary-button.blade.php
- resources/views/components/auth-session-status.blade.php
- resources/views/components/responsive-nav-link.blade.php
- resources/views/components/input-label.blade.php
- resources/views/components/primary-button.blade.php
- resources/views/components/input-error.blade.php
- resources/views/components/modal.blade.php
- resources/views/components/text-input.blade.php
- resources/views/game/partials/item-bonus-actions.blade.php
- resources/views/game/partials/dashboard-focus-service-panel.blade.php
- resources/views/game/partials/dashboard-equipment-workspace.blade.php
- resources/views/game/partials/equipment-board-slot-details.blade.php
- resources/views/game/partials/skill-panel.blade.php
- resources/views/game/partials/dashboard-server-messages.blade.php
- resources/views/game/partials/dashboard-map-system-overlays.blade.php
- resources/views/game/partials/dashboard-questlog-panel.blade.php
- resources/views/game/partials/rest-waiting-placeholder.blade.php
- resources/views/game/partials/dashboard-focus-hunting-panel.blade.php
- resources/views/game/partials/inventory-list-row-details.blade.php
- resources/views/auth/reset-password.blade.php
- resources/views/characters/index.blade.php

> Note: some components are shared UI primitives under `resources/views/components`.

---

## Routes
- routes/web.php — main application routes (landing `/`, dashboard `/dashboard`, game `/game`, characters `/characters`, admin routes prefixed `/admin/...`)
- routes/auth.php — authentication routes: register/login/logout/password reset/email verification
- routes/api.php — API routes for 3rd-party or internal API
- routes/console.php — artisan console routes

---

## Controllers (app/Http/Controllers)
- app/Http/Controllers/GameDashboardController.php — dashboard/game handler
- app/Http/Controllers/CharacterSelectionController.php — character list/select/create/delete
- app/Http/Controllers/GameActionController.php — central game action endpoint
- app/Http/Controllers/ProfileController.php — profile edit/update
- app/Http/Controllers/LocaleController.php — locale update for guests
- app/Http/Controllers/Controller.php — base controller

- Auth controllers (laravel-style): RegisteredUserController, AuthenticatedSessionController, PasswordResetLinkController, NewPasswordController, EmailVerificationNotificationController, VerifyEmailController, ConfirmablePasswordController, PasswordController, etc.

- Admin controllers: app/Http/Controllers/Admin/* (AdminDashboardController, AdminUserController, AdminAreaController, AdminMonsterController, AdminQuestController, AdminShopController, AdminCharacterController, AdminSmithRefineRateController, AdminActionLogController, AdminLocationController, AdminLocationConnectionController, AdminBonusBalanceController)

- API simulation: app/Http/Controllers/Api/SimulationController.php

---

## Frontend assets & build
- resources/js/app.js — Alpine code + app bootstrapping (overlay portaling, equipment popovers)
- resources/js/bootstrap.js — Vite bootstrap entry
- resources/css/app.css — main compiled Tailwind CSS + custom styles
- tailwind.config.js, postcss.config.js — Tailwind/PostCSS configs
- package.json — devDependencies include tailwindcss, @tailwindcss/forms, @tailwindcss/vite, alpinejs, vite, postcss, autoprefixer, playwright
- scripts/ — Playwright scripts and utility scripts
  - scripts/playwright-inspect-overlay.mjs
  - scripts/playwright-equip-screenshot.mjs
  - scripts/playwright-ui-proof.mjs
  - scripts/ui-proof-router.php

---

## Notes & recommendations before removal
1. Create a new Git branch (e.g. `rework/bootstrap`) before removing or moving files.
2. Archive the current UI by moving `resources/views` into `resources/views/legacy/` and `resources/css/app.css` → `resources/css/legacy-app.css` (or copy them). This preserves history and gives a quick rollback.
3. Remove Tailwind devDependencies from `package.json` only after verifying the archive and branch creation.
4. Install `bootstrap@latest` and optionally `bootstrap-icons` and `sass`/`sass-loader` (or use `bootstrap` CSS from CDN initially for speed).
5. Start by scaffolding a minimal Blade layout using Bootstrap (`resources/views/layouts/bootstrap.blade.php`) and minimal JS in `resources/js/bootstrap-app.js`.

---

## Minimal first deliverables (what you asked)
1. Landing page (public) with Login + Register pages (no password-forget for now).
2. Character selection page (post-login).
3. Logged-in landing page: centered map image, left + right simplified sidebars, header (no footer), mobile: map hidden in portrait, visible in landscape.
4. Simple info windows overlayed on map (Bootstrap modals or positioned elements).

---

If you'd like I can now:
- create a new git branch and archive current `resources/views` and `resources/css` (safe, reversible), then scaffold the Bootstrap layout and the first landing + auth pages; or
- simply scaffold the Bootstrap layout in `resources/views/layouts/bootstrap.blade.php` and a small JS/CSS entry so you can review before archiving.

Which of these two actions do you want me to perform now? (I recommend creating the branch + archive first.)
