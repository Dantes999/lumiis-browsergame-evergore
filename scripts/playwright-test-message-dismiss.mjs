import { chromium, devices } from 'playwright';
import path from 'path';

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const storagePath = path.resolve(process.cwd(), 'tmp', 'playwright-storage', 'normal.json');

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const ctx = await browser.newContext({ ...devices['Desktop Chrome'], storageState: storagePath, viewport: { width: 1440, height: 1024 } });
        const page = await ctx.newPage();
        await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });

        // ensure message stack exists
        await page.evaluate(() => {
            let stack = document.querySelector('.dashboard-server-message-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.className = 'dashboard-server-message-stack';
                document.body.appendChild(stack);
            }
        });

        // inject a test message without interactive controls
        await page.evaluate(() => {
            const stack = document.querySelector('.dashboard-server-message-stack');
            const msg = document.createElement('section');
            msg.className = 'theme-status-flash dashboard-server-message-card theme-status-info';
            msg.setAttribute('data-dismiss-on-click', '');
            msg.innerHTML = `
                <p class="dashboard-server-message-kicker">Test Message</p>
                <div class="dashboard-server-message-lines"><p>Click me to dismiss</p></div>
            `;
            stack.appendChild(msg);
        });

        // click inside the message content (not the close button)
        await page.waitForSelector('.dashboard-server-message-card');
        await page.click('.dashboard-server-message-card .dashboard-server-message-lines p');
        await page.waitForTimeout(200);
        const remaining = await page.$$eval('.dashboard-server-message-card', (els) => els.length);
        console.log('remaining_after_click:', remaining);

        // inject a message with an anchor (should not be dismissed when clicking the link)
        await page.evaluate(() => {
            const stack = document.querySelector('.dashboard-server-message-stack');
            const msg = document.createElement('section');
            msg.className = 'theme-status-flash dashboard-server-message-card theme-status-info';
            msg.setAttribute('data-dismiss-on-click', '');
            msg.innerHTML = `
                <p class="dashboard-server-message-kicker">Link Message</p>
                <div class="dashboard-server-message-lines"><p><a href="#" id="msg-link">Do not dismiss</a></p></div>
            `;
            stack.appendChild(msg);
        });

        await page.waitForSelector('#msg-link');
        await page.click('#msg-link');
        await page.waitForTimeout(200);
        const remainingAfterLink = await page.$$eval('.dashboard-server-message-card', (els) => els.length);
        console.log('remaining_after_link_click:', remainingAfterLink);

        await ctx.close();
    } catch (e) {
        console.error(e);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
