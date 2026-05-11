import { chromium, devices } from 'playwright';
import { execFile } from 'node:child_process';
import { mkdir } from 'node:fs/promises';
import { promisify } from 'node:util';
import path from 'node:path';

const baseUrl = process.env.UI_BASE_URL ?? 'http://127.0.0.1:8003';
const execFileAsync = promisify(execFile);
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
    [`/reset-password/proof-token?email=${encodeURIComponent(normalUser.email)}`, 'reset-password'],
    ['/missing-proof-page', 'not-found'],
];

const memberPages = [
    ['/profile', 'profile'],
    ['/confirm-password', 'confirm-password'],
    ['/dashboard', 'dashboard'],
];

const adminPages = [
    ['/admin', 'admin'],
    ['/admin/users', 'admin-users'],
    ['/admin/characters', 'admin-characters'],
    ['/admin/locations', 'admin-locations'],
    ['/admin/areas', 'admin-areas'],
    ['/admin/monsters', 'admin-monsters'],
    ['/admin/quests', 'admin-quests'],
    ['/admin/shops', 'admin-shops'],
    ['/admin/connections', 'admin-connections'],
    ['/admin/refine-rates', 'admin-refine-rates'],
    ['/admin/bonus-balance', 'admin-bonus-balance'],
    ['/admin/logs', 'admin-logs'],
];

const adminEditPages = [
    ['/admin/users', 'admin-user-edit'],
    ['/admin/characters', 'admin-character-edit'],
    ['/admin/locations', 'admin-location-edit'],
    ['/admin/areas', 'admin-area-edit'],
    ['/admin/monsters', 'admin-monster-edit'],
    ['/admin/quests', 'admin-quest-edit'],
    ['/admin/shops', 'admin-shop-edit'],
    ['/admin/connections', 'admin-connection-edit'],
];

function normalizeText(value) {
    return value.replace(/\s+/g, ' ').trim();
}

async function syncProofUsers() {
    await execFileAsync('php', ['artisan', 'game:sync-proof-users'], {
        cwd: process.cwd(),
    });
}

async function auditPage(page, label, options = {}) {
    const { minimumFontSizePx = 12, forbidVerticalScrollIn = null } = options;
    const viewport = page.viewportSize();
    const resolvedMinimumFontSizePx = viewport && viewport.width < 900 ? minimumFontSizePx : 11.5;
    const issues = await page.evaluate(({ minimumFontSizePx: minFontSize, forbidVerticalScrollIn: scrollScopeSelector }) => {
        const normalize = (value) => value.replace(/\s+/g, ' ').trim();
        const skipReadabilityAudit = (element) => element.matches('.scene-hotspot');
        const skipOverlapAudit = (element) => element.matches('.scene-hotspot') || element.closest('.equipment-board-slot-card');
        const skipScrollAudit = (element) => ['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName);
        const parseColor = (value) => {
            const match = value.match(/rgba?\(([^)]+)\)/i);

            if (!match) {
                return null;
            }

            const [red = '0', green = '0', blue = '0', alpha = '1'] = match[1].split(',').map((part) => part.trim());

            return [
                Number.parseFloat(red),
                Number.parseFloat(green),
                Number.parseFloat(blue),
                Number.parseFloat(alpha),
            ];
        };
        const relativeLuminance = ([red, green, blue]) => {
            const convert = (channel) => {
                const normalized = channel / 255;

                return normalized <= 0.03928
                    ? normalized / 12.92
                    : ((normalized + 0.055) / 1.055) ** 2.4;
            };

            return (0.2126 * convert(red)) + (0.7152 * convert(green)) + (0.0722 * convert(blue));
        };
        const contrastRatio = (foreground, background) => {
            const lighter = Math.max(relativeLuminance(foreground), relativeLuminance(background));
            const darker = Math.min(relativeLuminance(foreground), relativeLuminance(background));

            return (lighter + 0.05) / (darker + 0.05);
        };

        const visibleOnScreen = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && Number.parseFloat(style.opacity || '1') > 0
                && rect.width > 0
                && rect.height > 0
                && rect.bottom > 0
                && rect.top < window.innerHeight
                && rect.right > 0
                && rect.left < window.innerWidth;
        };

        const topLayerAtPoint = (element, probeX, probeY) => {
            const topElement = document.elementFromPoint(probeX, probeY);

            return topElement instanceof Element
                && (topElement === element || topElement.contains(element) || element.contains(topElement));
        };

        const isTopLayer = (element) => {
            const rect = element.getBoundingClientRect();
            const probeX = Math.min(Math.max(rect.left + Math.min(rect.width / 2, 24), 1), window.innerWidth - 1);
            const probeY = Math.min(Math.max(rect.top + Math.min(rect.height / 2, 24), 1), window.innerHeight - 1);

            return topLayerAtPoint(element, probeX, probeY);
        };

        const describe = (element) => {
            const rect = element.getBoundingClientRect();
            const text = (element.innerText || element.value || element.getAttribute('aria-label') || element.placeholder || '')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 60);
            const label = text || element.className || element.tagName.toLowerCase();

            return `${element.tagName.toLowerCase()} "${label}" @ ${Math.round(rect.left)},${Math.round(rect.top)} ${Math.round(rect.width)}x${Math.round(rect.height)}`;
        };
        const nearestOpaqueBackground = (element, stopAt = null) => {
            let current = element;

            while (current instanceof HTMLElement) {
                const style = window.getComputedStyle(current);
                const background = parseColor(style.backgroundColor || '');

                if (background && background[3] > 0.92) {
                    return background;
                }

                if (current === stopAt) {
                    break;
                }

                current = current.parentElement;
            }

            return null;
        };

        const issues = [];
        const root = document.documentElement;

        if (root.scrollWidth > window.innerWidth + 2) {
            issues.push(`Horizontal overflow: ${root.scrollWidth}px content width on ${window.innerWidth}px viewport.`);
        }

        const navbarWrapper = document.querySelector('.dashboard-classic-header-wrapper');
        const navbarLinks = document.querySelector('.dashboard-classic-header .portal-navbar-links');

        if (window.innerWidth < 1024 && navbarWrapper instanceof HTMLElement && navbarLinks instanceof HTMLElement && visibleOnScreen(navbarLinks)) {
            const blockedNavbarControl = Array.from(navbarLinks.querySelectorAll('a[href], button, [role="button"]'))
                .filter(visibleOnScreen)
                .find((element) => {
                    const rect = element.getBoundingClientRect();
                    const probeX = Math.min(Math.max(rect.left + (rect.width / 2), 1), window.innerWidth - 1);
                    const probeY = Math.min(Math.max(rect.top + (rect.height / 2), 1), window.innerHeight - 1);

                    return !topLayerAtPoint(element, probeX, probeY);
                });

            if (blockedNavbarControl) {
                issues.push(`Blocked navbar control: ${describe(blockedNavbarControl)}`);
            }
        }

        const textCandidates = Array.from(document.querySelectorAll('a[href], button, label, input, select, textarea, h1, h2, h3, p'))
            .filter(visibleOnScreen)
            .filter(isTopLayer)
            .filter((element) => !skipReadabilityAudit(element));

        for (const element of textCandidates) {
            const style = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            const fontSize = Number.parseFloat(style.fontSize || '0');
            const text = (element.innerText || element.value || element.getAttribute('aria-label') || element.placeholder || '')
                .replace(/\s+/g, ' ')
                .trim();
            const isFormControl = ['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName);

            if ((text.length >= 2 || isFormControl) && fontSize < minFontSize) {
                issues.push(`Unreadable text size (${fontSize}px): ${describe(element)}`);
            }

            if (rect.left < -1 || rect.right > window.innerWidth + 1) {
                issues.push(`Element leaves viewport: ${describe(element)}`);
            }

            if (issues.length >= 8) {
                break;
            }
        }

        const overlapCandidates = Array.from(document.querySelectorAll(
            'a[href], button, input, select, textarea, [role="button"], .portal-navbar-stat, .dashboard-map-chipbar > *, .scene-hotspot-list-inner > *',
        ))
            .filter(visibleOnScreen)
            .filter(isTopLayer)
            .filter((element) => !skipOverlapAudit(element))
            .map((element) => ({
                element,
                rect: element.getBoundingClientRect(),
            }));

        overlapLoop:
        for (let index = 0; index < overlapCandidates.length; index += 1) {
            const current = overlapCandidates[index];

            for (let otherIndex = index + 1; otherIndex < overlapCandidates.length; otherIndex += 1) {
                const other = overlapCandidates[otherIndex];

                if (current.element.contains(other.element) || other.element.contains(current.element)) {
                    continue;
                }

                const overlapWidth = Math.min(current.rect.right, other.rect.right) - Math.max(current.rect.left, other.rect.left);
                const overlapHeight = Math.min(current.rect.bottom, other.rect.bottom) - Math.max(current.rect.top, other.rect.top);

                if (overlapWidth > 10 && overlapHeight > 10) {
                    issues.push(`Overlap: ${describe(current.element)} with ${describe(other.element)}`);
                    break overlapLoop;
                }
            }
        }

        const paperScopes = Array.from(document.querySelectorAll(
            '.simple-ui .paper-note, .simple-ui .theme-paper-panel, .simple-ui .theme-empty-state, .simple-ui .stat-card, .simple-ui .scene-stat-pill, .simple-ui .admin-row-card, .simple-ui .inventory-summary-card',
        )).filter(visibleOnScreen);

        contrastLoop:
        for (const scope of paperScopes) {
            const background = nearestOpaqueBackground(scope, scope.parentElement);

            if (!background) {
                continue;
            }

            const scopedTextCandidates = Array.from(scope.querySelectorAll('p, h1, h2, h3, h4, label, span, strong, a[href], button'))
                .filter(visibleOnScreen)
                .filter(isTopLayer)
                .filter((element) => {
                    const text = normalize(element.innerText || element.textContent || '');

                    return text.length >= 2
                        && !element.closest('button, .cozy-chip, .theme-button, .theme-button-primary, .theme-button-secondary, .theme-button-danger, .action-button, .action-button-muted, .nav-pill, .dashboard-overlay-pill')
                        && !element.matches('.cozy-chip');
                });

            for (const element of scopedTextCandidates) {
                const style = window.getComputedStyle(element);
                const foreground = parseColor(style.color || '');

                if (!foreground) {
                    continue;
                }

                const fontSize = Number.parseFloat(style.fontSize || '0');
                const fontWeight = Number.parseInt(style.fontWeight || '400', 10);
                const minimumContrast = fontSize >= 18 || (fontSize >= 14 && fontWeight >= 600) ? 3 : 4.5;
                const ratio = contrastRatio(foreground, background);

                if (ratio < minimumContrast) {
                    issues.push(`Low contrast (${ratio.toFixed(2)} fg ${style.color} bg rgba(${background.map((channel) => channel.toFixed(2)).join(', ')})): ${describe(element)}`);
                    break contrastLoop;
                }
            }
        }

        const wrappedBattleChip = Array.from(document.querySelectorAll('.battle-enemy-card .cozy-chip'))
            .filter(visibleOnScreen)
            .find((element) => {
                const style = window.getComputedStyle(element);
                const lineHeight = Number.parseFloat(style.lineHeight || '0');
                const paddingTop = Number.parseFloat(style.paddingTop || '0');
                const paddingBottom = Number.parseFloat(style.paddingBottom || '0');
                const contentHeight = element.getBoundingClientRect().height - paddingTop - paddingBottom;

                if (style.whiteSpace === 'nowrap') {
                    return element.scrollWidth > element.clientWidth + 1;
                }

                return element.getClientRects().length > 1
                    || (lineHeight > 0 && contentHeight > (lineHeight * 1.45));
            });

        if (wrappedBattleChip) {
            issues.push(`Wrapped battle chip: ${describe(wrappedBattleChip)}`);
        }

        if (scrollScopeSelector) {
            const scrollScope = document.querySelector(scrollScopeSelector);

            if (scrollScope instanceof HTMLElement) {
                const scrollCandidates = [scrollScope, ...scrollScope.querySelectorAll('*')]
                    .filter((element) => element instanceof HTMLElement)
                    .filter((element) => !skipScrollAudit(element))
                    .filter((element) => {
                        const style = window.getComputedStyle(element);
                        const rect = element.getBoundingClientRect();

                        return style.display !== 'none'
                            && style.visibility !== 'hidden'
                            && Number.parseFloat(style.opacity || '1') > 0
                            && rect.width > 0
                            && rect.height > 0
                            && ['auto', 'scroll'].includes(style.overflowY)
                            && element.scrollHeight > element.clientHeight + 2;
                    });

                if (scrollCandidates.length > 0) {
                    issues.push(`Scrollable panel: ${describe(scrollCandidates[0])}`);
                }
            }
        }

        return issues;
    }, { minimumFontSizePx: resolvedMinimumFontSizePx, forbidVerticalScrollIn });

    if (issues.length > 0) {
        throw new Error(`${label} UI audit failed:\n- ${issues.join('\n- ')}`);
    }
}

async function login(page, credentials) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel(/e-mail|email/i).fill(credentials.email);
    await page.getByLabel(/passwort|password/i).fill(credentials.password);
    await page.getByRole('button', { name: /einloggen|log in|login/i }).click();
    await page.waitForLoadState('networkidle');
}

async function capture(page, relativeUrl, fileName) {
    await page.goto(`${baseUrl}${relativeUrl}`, { waitUntil: 'networkidle' });
    await auditPage(page, fileName);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function captureFirstEditPage(page, indexUrl, fileName) {
    await page.goto(`${baseUrl}${indexUrl}`, { waitUntil: 'networkidle' });
    const editHref = await page.getByRole('link', { name: /edit/i }).first().getAttribute('href');

    if (!editHref) {
        throw new Error(`No edit link found on ${indexUrl}.`);
    }

    await page.goto(editHref, { waitUntil: 'networkidle' });
    await auditPage(page, fileName);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function captureDashboardFocus(page, fileName) {
    await page.goto(`${baseUrl}/dashboard?focus=riverbank`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    await auditPage(page, fileName, page.viewportSize()?.width >= 1024
        ? { forbidVerticalScrollIn: '.dashboard-map-stage' }
        : {});
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function captureDashboardPanel(page, panel, fileName) {
    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    await page.evaluate((panelName) => {
        window.dispatchEvent(new CustomEvent('dashboard-panel-open', {
            detail: { panel: panelName },
        }));
    }, panel);
    await page.waitForTimeout(250);
    await auditPage(page, fileName, page.viewportSize()?.width >= 1024
        ? { forbidVerticalScrollIn: '.dashboard-map-stage' }
        : {});
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function startManualBattle(page) {
    await page.goto(`${baseUrl}/dashboard?focus=riverbank`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: /manual fight|manueller kampf/i }).first().click();
    await page.waitForLoadState('networkidle');
    await page.locator('#tutorial-battle-window').waitFor({ state: 'visible' });
}

async function captureBattleState(page, fileName) {
    await startManualBattle(page);
    await auditPage(page, fileName);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
}

async function captureBattleAfterAttack(page, fileName) {
    const battleWindow = page.locator('#tutorial-battle-window');

    if (!(await battleWindow.isVisible())) {
        await startManualBattle(page);
    }

    await battleWindow.locator('form[action*="battle-attack"] button').first().click();
    await page.waitForLoadState('networkidle');
    await auditPage(page, fileName);
    await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
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
            await syncProofUsers();
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
            await captureDashboardPanel(page, 'quests', `${viewportConfig.name}-dashboard-quests.png`);
            await captureDashboardPanel(page, 'inventory', `${viewportConfig.name}-dashboard-inventory.png`);
            await captureDashboardPanel(page, 'equipment', `${viewportConfig.name}-dashboard-equipment.png`);
            await captureDashboardPanel(page, 'settings', `${viewportConfig.name}-dashboard-settings.png`);
            await captureBattleState(page, `${viewportConfig.name}-dashboard-battle-live.png`);
            await captureBattleAfterAttack(page, `${viewportConfig.name}-dashboard-battle-attack.png`);

            await context.close();

            const adminContext = await createContext(browser, viewportConfig);
            const adminPage = await adminContext.newPage();
            await login(adminPage, adminUser);
            for (const [relativeUrl, label] of adminPages) {
                await capture(adminPage, relativeUrl, `${viewportConfig.name}-${label}.png`);
            }
            for (const [relativeUrl, label] of adminEditPages) {
                await captureFirstEditPage(adminPage, relativeUrl, `${viewportConfig.name}-${label}.png`);
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
