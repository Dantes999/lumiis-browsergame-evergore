import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const normalUser = { email: process.env.UI_USER_EMAIL ?? 'ui-proof@example.test', password: process.env.UI_USER_PASSWORD ?? 'TestPass123!' };

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

(async () => {
    try {
        await syncProofUsers();
        const browser = await chromium.launch({ headless: true });
        const context = await browser.newContext({ ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1024 } });
        const page = await context.newPage();
        await login(page);
        await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
        await page.evaluate(() => window.dispatchEvent(new CustomEvent('dashboard-panel-open', { detail: { panel: 'equipment' } })));
        await page.waitForSelector('#dashboard-equipment-overlay', { state: 'visible', timeout: 10000 });
        await page.waitForTimeout(400);

        // capture a quick screenshot to verify visual stacking
        try {
            await page.screenshot({ path: 'screenshots/equip-overlay-inspect-desktop.png', fullPage: true });
        } catch (e) {
            // ignore screenshot failures
        }

        const info = await page.evaluate(() => {
            const qs = (s) => document.querySelector(s);
            const header = qs('.dashboard-classic-header-wrapper');
            const overlay = qs('#dashboard-equipment-overlay');
            const overlayWindow = qs('#dashboard-equipment-overlay .dashboard-overlay-window-inventory');
            const firstSlot = qs('.equipment-board-slot');

            function desc(el) {
                if (!el) return null;
                const style = window.getComputedStyle(el);
                const rect = el.getBoundingClientRect();
                return {
                    tag: el.tagName.toLowerCase(),
                    cls: el.className,
                    zIndex: style.zIndex,
                    position: style.position,
                    rect: { left: rect.left, top: rect.top, width: rect.width, height: rect.height },
                };
            }

            const headerDesc = desc(header);
            const overlayDesc = desc(overlay);
            const overlayWindowDesc = desc(overlayWindow);
            const slotDesc = desc(firstSlot);

            let probeResult = null;

            if (overlay) {
                const orect = overlay.getBoundingClientRect();
                const px = Math.round(orect.left + Math.min(24, orect.width / 4));
                const py = Math.round(orect.top + Math.min(24, orect.height / 8));
                const topEl = document.elementFromPoint(px, py);

                probeResult = {
                    probeX: px,
                    probeY: py,
                    topTag: topEl ? topEl.tagName.toLowerCase() : null,
                    topClass: topEl ? topEl.className : null,
                };
            }

            return { header: headerDesc, overlay: overlayDesc, overlayWindow: overlayWindowDesc, slot: slotDesc, probe: probeResult };
        });

        console.log(JSON.stringify(info, null, 2));
        // Try clicking the close button and verify the overlay hides
        try {
            const closeBtn = await page.$('#dashboard-equipment-overlay .dashboard-overlay-close');

            if (closeBtn) {
                await closeBtn.click({ force: true });
                // wait for overlay to be hidden by Alpine/x-show
                try {
                    await page.waitForSelector('#dashboard-equipment-overlay', { state: 'hidden', timeout: 5000 });
                    console.log('overlay-closed: true');
                } catch (e) {
                    console.log('overlay-closed: false');
                }

                try {
                    await page.screenshot({ path: 'screenshots/equip-overlay-after-close.png', fullPage: true });
                } catch (e) {
                    // ignore
                }
            } else {
                console.log('close-button-not-found');
            }
        } catch (e) {
            console.error('close-click-error', e);
        }

        await browser.close();
    } catch (err) {
        console.error(err);
        process.exitCode = 1;
    }
})();
