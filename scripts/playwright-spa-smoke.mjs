import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const appUrl = (process.env.APP_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const spaUrl = (process.env.SPA_URL ?? `${appUrl}/play`).replace(/\/$/, '');
const credentials = {
    email: process.env.UI_USER_EMAIL ?? 'ui-proof@example.test',
    password: process.env.UI_USER_PASSWORD ?? 'TestPass123!',
};

await mkdir('screenshots', { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1024 } });
const failures = [];

page.on('console', (message) => {
    if (message.type() === 'error') {
        failures.push(`console: ${message.text()}`);
    }
});

try {
    await page.goto(`${appUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/e-mail|email/i).fill(credentials.email);
    await page.getByLabel(/passwort|password/i).fill(credentials.password);
    await page.getByRole('button', { name: /einloggen|log in|login/i }).click();
    await page.waitForLoadState('domcontentloaded');

    await page.goto(`${spaUrl}/map/1`, { waitUntil: 'networkidle' });
    await page.getByText('Aktuelle Karte', { exact: false }).waitFor({ timeout: 10_000 });

    const mapText = await page.locator('body').innerText();

    if (!mapText.includes('Yongan')) {
        failures.push('map does not contain Yongan');
    }

    for (const [button, expected] of [
        ['Charakter', 'Spielerfenster'],
        ['Inventar', 'Spielerfenster'],
        ['Quests', 'Spielerfenster'],
        ['Chat', 'Spielerfenster'],
    ]) {
        await page.getByRole('button', { name: button, exact: true }).click();
        await page.getByText(expected, { exact: false }).first().waitFor({ timeout: 5_000 });

        if ((await page.locator('body').innerText()).length < 100) {
            failures.push(`${button} window is empty`);
        }

        await page.getByRole('button', { name: 'Schließen' }).first().click();
    }

    await page.screenshot({
        path: path.resolve('screenshots', `spa-smoke-${Date.now()}.png`),
        fullPage: true,
    });

    if (failures.length > 0) {
        throw new Error(`SPA smoke test failed:\n- ${failures.join('\n- ')}`);
    }

    console.log('SPA smoke test passed.');
} finally {
    await browser.close();
}
