import { chromium, devices } from 'playwright';
import path from 'path';

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const storagePath = path.resolve(process.cwd(), 'tmp', 'playwright-storage', 'admin.json');

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const ctx = await browser.newContext({ ...devices['Desktop Chrome'], storageState: storagePath, viewport: { width: 1440, height: 1024 } });
        const page = await ctx.newPage();
        await page.goto(`${baseUrl}/admin/users`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(300);
        const bodyText = await page.evaluate(() => document.body.innerText.slice(0, 8000));
        console.log(bodyText);
        await ctx.close();
    } catch (e) {
        console.error(e);
    } finally {
        await browser.close();
    }
})();
