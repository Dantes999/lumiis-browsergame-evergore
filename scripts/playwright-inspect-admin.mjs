import { chromium, devices } from 'playwright';
import fs from 'fs';
import path from 'path';

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const storagePath = path.resolve(process.cwd(), 'tmp', 'playwright-storage', 'admin.json');

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const ctx = await browser.newContext({ ...devices['Desktop Chrome'], storageState: storagePath, viewport: { width: 1440, height: 1024 } });
        const page = await ctx.newPage();
        await page.goto(`${baseUrl}/admin/users`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);

        const anchors = await page.$$eval('a', (els) => els.map((el) => ({ text: el.innerText.trim(), href: el.getAttribute('href'), roles: el.getAttribute('role') })).slice(0, 200));
        const buttons = await page.$$eval('button', (els) => els.map((el) => ({ text: el.innerText.trim(), type: el.getAttribute('type'), aria: el.getAttribute('aria-label') })).slice(0, 200));
        const linksByRole = await page.$$eval('[role="link"], a', (els) => els.map((el) => ({ text: el.innerText.trim(), href: el.getAttribute('href'), role: el.getAttribute('role') })).slice(0, 200));

        console.log('=== anchors ===');
        console.log(JSON.stringify(anchors, null, 2));
        console.log('=== buttons ===');
        console.log(JSON.stringify(buttons, null, 2));
        console.log('=== linksByRole ===');
        console.log(JSON.stringify(linksByRole, null, 2));

        // also dump snippet of page head title and H1s
        const title = await page.title();
        const h1s = await page.$$eval('h1', els => els.map(e => e.innerText.trim()));
        console.log({ title, h1s });

        await ctx.close();
    } catch (e) {
        console.error('inspect failed', e);
    } finally {
        await browser.close();
    }
})();
