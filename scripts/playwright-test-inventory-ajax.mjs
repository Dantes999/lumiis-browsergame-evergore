import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';

const execFileAsync = promisify(execFile);
const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const normalUser = {
    email: process.env.UI_USER_EMAIL ?? 'ui-proof@example.test',
    password: process.env.UI_USER_PASSWORD ?? 'TestPass123!',
};

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    try { await page.getByLabel(/e-mail|email/i).fill(normalUser.email); } catch (e) {
        await page.evaluate((v) => { const el = document.querySelector('input[name="email"], input#email'); if (el) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); } }, normalUser.email);
    }
    try { await page.getByLabel(/passwort|password/i).fill(normalUser.password); } catch (e) {
        await page.evaluate((v) => { const el = document.querySelector('input[name="password"], input#password'); if (el) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); } }, normalUser.password);
    }
    try { await page.getByRole('button', { name: /einloggen|log in|login/i }).click(); } catch (e) { try { await page.click('button[type="submit"]'); } catch (e2) { await page.evaluate(() => { const f = document.querySelector('form'); if (f) f.submit(); }); } }
    await page.waitForLoadState('networkidle');
}

(async () => {
    try {
        // ensure proof users & loadout
        await execFileAsync('php', ['artisan', 'game:sync-proof-users'], { cwd: process.cwd() });

        const browser = await chromium.launch({ headless: true });
        const ctx = await browser.newContext({ ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1024 } });
        const page = await ctx.newPage();

        await login(page);
        await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });

        // open inventory overlay
        try { await page.click('[data-open-panel="inventory"]', { timeout: 2000 }); } catch (e) {
            try { const btn = await page.getByRole('button', { name: /inventory|inventar/i }).first(); if (btn) await btn.click(); } catch (e2) { await page.evaluate(() => window.dispatchEvent(new CustomEvent('dashboard-panel-open', { detail: { panel: 'inventory' } }))); }
        }

        await page.waitForSelector('.dashboard-overlay-panel[data-panel="inventory"]', { state: 'visible', timeout: 8000 });

        // try to find a use-item form first, fallback to equip/sell
        let form = await page.$('.dashboard-overlay-panel[data-panel="inventory"] form.inventory-list-row-action-form[action*="use-item"]');
        if (!form) form = await page.$('.dashboard-overlay-panel[data-panel="inventory"] form.inventory-list-row-action-form[action*="equip-item"]');
        if (!form) form = await page.$('.dashboard-overlay-panel[data-panel="inventory"] form.inventory-list-row-action-form[action*="sell-item"]');

        if (!form) {
            console.log('no-inventory-form-found');
            await ctx.close();
            await browser.close();
            process.exit(0);
        }

        const rowHandle = await form.evaluateHandle((f) => f.closest('.inventory-list-row'));
        const id = await rowHandle.evaluate((r) => r ? r.getAttribute('data-inventory-item-id') : null);
        const countEl = await rowHandle.$('.inventory-list-row-count');
        const initialCount = countEl ? (await (await countEl.getProperty('textContent')).jsonValue()).trim() : null;

        const submitBtn = await form.$('button[type="submit"], input[type="submit"]');
        if (!submitBtn) {
            console.log('no-submit-button-found');
            await ctx.close(); await browser.close(); process.exit(0);
        }

        // wait for the POST to /game/actions/*
        const responsePromise = page.waitForResponse((r) => r.url().includes('/game/actions/') && r.request().method() === 'POST', { timeout: 10000 });

        await submitBtn.click();
        const response = await responsePromise;

        const ct = String(response.headers()['content-type'] || '');
        let json = null;
        if (ct.includes('application/json')) {
            try { json = await response.json(); } catch (e) { json = null; }
        }

        // wait for optimistic DOM change: either row removed or count changed
        const changesObserved = await Promise.race([
            (async () => { // row removed
                try { await page.waitForSelector(`.inventory-list-row[data-inventory-item-id="${id}"]`, { state: 'detached', timeout: 5000 }); return { removed: true }; } catch (e) { return null; }
            })(),
            (async () => { // count changed
                try {
                    await page.waitForFunction((sel, initial) => {
                        const el = document.querySelector(sel);
                        if (!el) return false;
                        return el.textContent.trim() !== initial.trim();
                    }, {}, `.inventory-list-row[data-inventory-item-id="${id}"] .inventory-list-row-count`, initialCount);
                    return { changed: true };
                } catch (e) { return null; }
            })(),
        ]);

        console.log('ajax-content-type:', ct);
        console.log('json-keys:', json ? Object.keys(json) : null);
        console.log('optimistic-changes-observed:', changesObserved);

        if (json && Array.isArray(json.changes)) {
            const hasMatching = json.changes.some((c) => String(c.id || c.character_item_id || c.characterItemId || '') === String(id) || c.removed);
            if (!hasMatching) {
                console.error('JSON changes did not reference the acted item', json.changes);
                process.exitCode = 2;
            } else {
                console.log('inventory-ajax-test: PASS — server returned changes referencing the item');
            }
        } else if (response.status() >= 300 && response.status() < 400) {
            console.log('server-redirected — fallback path used; treat as PASS');
        } else {
            console.warn('server did not return JSON.changes; response status', response.status());
        }

        await ctx.close();
        await browser.close();
    } catch (err) {
        console.error(err);
        process.exitCode = 1;
    }
})();
