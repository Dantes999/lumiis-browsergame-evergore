import { chromium, devices } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const outputDir = path.resolve(
    process.cwd(),
    'screenshots',
    `ui-proof-${new Date().toISOString().replace(/[:.]/g, '-')}`,
);

const normalUser = {
    email: process.env.UI_USER_EMAIL ?? 'ui-proof@example.test',
    password: process.env.UI_USER_PASSWORD ?? 'TestPass123!',
};

const adminUser = {
    email: process.env.UI_ADMIN_EMAIL ?? 'audit-admin@example.test',
    password: process.env.UI_ADMIN_PASSWORD ?? 'TestPass123!',
};

const viewports = [
    {
        name: 'desktop',
        viewport: { width: 1440, height: 1024 },
    },
    {
        name: 'mobile-portrait',
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
    },
    {
        name: 'mobile-landscape',
        viewport: { width: 844, height: 390 },
        isMobile: true,
        hasTouch: true,
    },
];

const publicPages = [
    ['/', 'home'],
    ['/login', 'login'],
    ['/register', 'register'],
    ['/forgot-password', 'forgot-password'],
];

const memberPages = [
    ['/profile', 'profile'],
    ['/dashboard', 'dashboard'],
];

const adminPages = [
    ['/admin', 'admin'],
    ['/admin/users', 'admin-users'],
    ['/admin/locations', 'admin-locations'],
];

async function login(page, credentials) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel(/e-mail|email/i).fill(credentials.email);
    await page.getByLabel(/passwort|password/i).fill(credentials.password);
    await page.getByRole('button', { name: /einloggen|log in|login/i }).click();
    await page.waitForLoadState('networkidle');
}

async function capture(page, relativeUrl, fileName) {
    await page.goto(`${baseUrl}${relativeUrl}`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function captureDashboardFocus(page, fileName) {
    await page.goto(`${baseUrl}/dashboard?focus=riverbank`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: false });
}

async function captureDashboardPanel(page, panel, fileName) {
    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    await page.evaluate((panelName) => {
        window.dispatchEvent(new CustomEvent('dashboard-panel-open', {
            detail: { panel: panelName },
        }));
    }, panel);
    await page.waitForTimeout(250);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: false });
}

async function createContext(browser, viewportConfig) {
    return browser.newContext({
        ...devices['Desktop Chrome'],
        viewport: viewportConfig.viewport,
        isMobile: viewportConfig.isMobile ?? false,
        hasTouch: viewportConfig.hasTouch ?? false,
    });
}

async function main() {
    await mkdir(outputDir, { recursive: true });
    const browser = await chromium.launch({ headless: true });

    try {
        for (const viewportConfig of viewports) {
            const context = await createContext(browser, viewportConfig);
            const page = await context.newPage();

            for (const [relativeUrl, label] of publicPages) {
                await capture(page, relativeUrl, `${viewportConfig.name}-${label}.png`);
            }

            await login(page, normalUser);
            for (const [relativeUrl, label] of memberPages) {
                await capture(page, relativeUrl, `${viewportConfig.name}-${label}.png`);
            }
            await captureDashboardFocus(page, `${viewportConfig.name}-dashboard-riverbank.png`);
            await captureDashboardPanel(page, 'character', `${viewportConfig.name}-dashboard-character.png`);
            await captureDashboardPanel(page, 'inventory', `${viewportConfig.name}-dashboard-inventory.png`);
            await captureDashboardPanel(page, 'settings', `${viewportConfig.name}-dashboard-settings.png`);

            await context.close();

            const adminContext = await createContext(browser, viewportConfig);
            const adminPage = await adminContext.newPage();
            await login(adminPage, adminUser);
            for (const [relativeUrl, label] of adminPages) {
                await capture(adminPage, relativeUrl, `${viewportConfig.name}-${label}.png`);
            }
            await adminContext.close();
        }

        console.log(`Screenshots written to ${outputDir}`);
    } finally {
        await browser.close();
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
