import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';

const execFileAsync = promisify(execFile);
const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const outputDir = path.resolve(process.cwd(), 'screenshots');
const normalUser = {
    email: process.env.UI_USER_EMAIL ?? 'ui-proof@example.test',
    password: process.env.UI_USER_PASSWORD ?? 'TestPass123!',
};

const viewports = [
    { name: 'desktop', cfg: { width: 1440, height: 1024 }, device: 'Desktop Chrome' },
    { name: 'mobile-portrait', cfg: { width: 390, height: 844 }, device: 'iPhone 12' },
    { name: 'mobile-landscape', cfg: { width: 844, height: 390 }, device: 'iPhone 12' },
];

async function syncProofUsers() {
    await execFileAsync('php', ['artisan', 'game:sync-proof-users'], { cwd: process.cwd() });
}

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel(/e-mail|email/i).fill(normalUser.email);
    await page.getByLabel(/passwort|password|passwort/i).fill(normalUser.password);
    await page.getByRole('button', { name: /einloggen|log in|login/i }).click();
    await page.waitForLoadState('networkidle');
}

async function captureForViewport(browser, viewport) {
    const context = await browser.newContext({ ...devices[viewport.device] ?? {}, viewport: viewport.cfg, isMobile: viewport.name.startsWith('mobile'), hasTouch: viewport.name.startsWith('mobile') });
    const page = await context.newPage();
    // ensure we're authenticated in this context
    await login(page);

    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    // ensure the dashboard shell or portal root exists before dispatching events
    try {
        await page.waitForSelector('[data-dashboard-shell], .portal-stage.dashboard-portal', { timeout: 7000 });
    } catch (e) {
        // ignore — continue anyway
    }
    // open equipment panel by clicking the header control (fallback to event dispatch)
    let opened = false;
    try {
        const btn = page.getByRole('button', { name: /ausrüstung|equipment/i }).first();
        await btn.click({ force: true });
        opened = true;
    } catch (err) {
        // ignore and try link
    }

    if (!opened) {
        try {
            const link = page.getByRole('link', { name: /ausrüstung|equipment/i }).first();
            await link.click({ force: true });
            opened = true;
        } catch (err) {
            // ignore
        }
    }

    if (!opened) {
        await page.evaluate(() => {
            window.dispatchEvent(new CustomEvent('dashboard-panel-open', { detail: { panel: 'equipment' } }));
        });
    }

    // allow UI to settle
    await page.waitForTimeout(500);

    // wait for equipment overlay
    await page.waitForSelector('#dashboard-equipment-overlay', { state: 'visible', timeout: 20000 });
    // force-open the first equipment slot info by toggling the open class
    await page.evaluate(() => {
        const card = document.querySelector('.equipment-board-slot-card');

        if (card) {
            card.classList.add('equipment-board-slot-card-open');
        }
    });

    // capture the equipment overlay for inspection
    const outPath = path.join(outputDir, `equip-overlay-${viewport.name}.png`);
    await page.screenshot({ path: outPath, fullPage: true });

    // if mobile-landscape: scroll the overlay content to verify header remains visible
    if (viewport.name === 'mobile-landscape') {
        try {
            await page.evaluate(() => {
                const scrollEl = document.querySelector('#dashboard-equipment-overlay .dashboard-overlay-scroll') || document.querySelector('#dashboard-equipment-overlay .dashboard-overlay-scroll-inventory');

                if (scrollEl) {
                    scrollEl.scrollTop = scrollEl.scrollHeight;
                } else {
                    window.scrollTo(0, document.body.scrollHeight);
                }
            });

            await page.waitForTimeout(400);
            const scrolledPath = path.join(outputDir, `equip-overlay-${viewport.name}-scrolled.png`);
            await page.screenshot({ path: scrolledPath, fullPage: true });
        } catch (e) {
            // ignore
        }
    }

    await context.close();
}

(async () => {
    try {
        await syncProofUsers();
        const browser = await chromium.launch({ headless: true });

        for (const vp of viewports) {
            await captureForViewport(browser, vp);
        }

        await browser.close();
        console.log('Screenshots captured into', outputDir);
    } catch (err) {
        console.error(err);
        process.exitCode = 1;
    }
})();
