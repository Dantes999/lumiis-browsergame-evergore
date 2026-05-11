import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'path';

const execFileAsync = promisify(execFile);

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const storagePath = path.resolve(process.cwd(), 'tmp', 'playwright-storage', 'normal.json');

(async () => {
    // ensure proof users & loadout exist so equipment slots are present
    try {
        await execFileAsync('php', ['artisan', 'game:sync-proof-users'], { cwd: process.cwd() });
    } catch (e) {
        console.error('sync-proof-users failed', e?.message || e);
    }

    const browser = await chromium.launch({ headless: true });
    try {
        const ctx = await browser.newContext({ ...devices['Desktop Chrome'], storageState: storagePath, viewport: { width: 1440, height: 1024 } });
        const page = await ctx.newPage();
        await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });

        // open equipment panel
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('dashboard-panel-open', { detail: { panel: 'equipment' } })));
        try {
            await page.waitForSelector('#dashboard-equipment-overlay, #dashboard-equipment-overlay-legacy, .dashboard-overlay-panel-inventory', { state: 'visible', timeout: 5000 });
        } catch (e) {
            // continue
        }

        // try to find first equipment slot toggle (attribute or class fallback)
        try {
            await page.waitForSelector('[data-toggle-info], .equipment-board-slot-info-toggle', { state: 'visible', timeout: 3000 });
        } catch (e) {
            // continue — selector may not exist for this proof account
        }

        const btn = await page.$('[data-toggle-info], .equipment-board-slot-info-toggle');
        if (!btn) {
            console.log('no-toggle-found');
            await ctx.close();
            await browser.close();
            return;
        }

        await btn.click();
        await page.waitForTimeout(300);

        // grab popover and header positions
        const info = await page.evaluate(() => {
            const header = document.querySelector('.dashboard-classic-header-wrapper') || document.querySelector('header') || document.querySelector('nav');
            const pop = document.querySelector('.equipment-board-slot-popover, .equipment-board-slot-popover--portaled');
            const headerRect = header ? header.getBoundingClientRect() : null;
            const popRect = pop ? pop.getBoundingClientRect() : null;
            const headerHeightVar = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--dashboard-header-height')) || null;
            return { headerRect, popRect, headerHeightVar };
        });

        console.log(JSON.stringify(info, null, 2));

        await ctx.close();
    } catch (e) {
        console.error(e);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
