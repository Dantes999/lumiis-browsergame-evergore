import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import fs from 'fs';
import path from 'path';

const execFileAsync = promisify(execFile);
const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const adminUser = { email: process.env.UI_ADMIN_EMAIL ?? 'audit-admin@example.test', password: process.env.UI_ADMIN_PASSWORD ?? 'TestPass123!' };
const storageDir = path.resolve(process.cwd(), 'tmp', 'playwright-storage');
const storagePath = path.join(storageDir, 'admin.json');

async function syncProofUsers() {
    await execFileAsync('php', ['artisan', 'game:sync-proof-users'], { cwd: process.cwd() });
}

async function createState() {
    await syncProofUsers();
    const browser = await chromium.launch({ headless: true });
    try {
        const context = await browser.newContext({ ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1024 } });
        const page = await context.newPage();
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });

        try {
            await page.getByLabel(/e-mail|email/i).fill(adminUser.email);
        } catch (e) {
            await page.evaluate((value) => {
                const el = document.querySelector('input[name="email"], input#email');
                if (el) { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); }
            }, adminUser.email);
        }
        try {
            await page.getByLabel(/passwort|password/i).fill(adminUser.password);
        } catch (e) {
            await page.evaluate((value) => {
                const el = document.querySelector('input[name="password"], input#password');
                if (el) { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); }
            }, adminUser.password);
        }

        try {
            await page.getByRole('button', { name: /einloggen|log in|login/i }).click();
        } catch (e) {
            try { await page.click('button[type="submit"]'); } catch (e2) { await page.evaluate(() => { const f = document.querySelector('form'); if (f) f.submit(); }); }
        }

        try {
            await page.waitForLoadState('networkidle', { timeout: 8000 });
        } catch (e) {
            // ignore
        }

        // ensure storage dir
        fs.mkdirSync(storageDir, { recursive: true });
        await context.storageState({ path: storagePath });
        console.log('saved storage state to', storagePath);
        await context.close();
    } finally {
        await browser.close();
    }
}

createState().catch((e) => { console.error(e); process.exit(1); });
