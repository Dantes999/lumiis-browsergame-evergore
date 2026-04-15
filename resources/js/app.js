import './bootstrap';

import Alpine from 'alpinejs';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const DASHBOARD_SHELL_STORAGE_KEY = 'lumiis.dashboard.shell';
const DEFAULT_TUTORIAL_TOTAL_STEPS = 12;
const LUMIIS_CORE_CATEGORY = 'Lumiis Core';

function submitFormById(formId) {
    if (!formId) {
        return false;
    }

    const form = document.getElementById(formId);

    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    form.requestSubmit();

    return true;
}

function findTutorialElement(selectors = []) {
    for (const selector of selectors) {
        const element = document.querySelector(selector);

        if (element) {
            return element;
        }
    }

    return undefined;
}

function tutorialStepConfig(step) {
    switch (Number(step)) {
        case 1:
            return {
                selectors: ['#tutorial-character-button'],
                disableActiveInteraction: false,
                nextFormId: 'tutorial-next-form',
                prevFormId: null,
                showButtons: ['next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 2:
            return {
                selectors: ['#tutorial-map-panel'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 3:
            return {
                selectors: ['#tutorial-general-store-card', '#tutorial-focus-panel'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 4:
            return {
                selectors: ['#tutorial-map-panel'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 5:
            return {
                selectors: ['#tutorial-hunting-overview-card', '#tutorial-focus-panel'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 6:
            return {
                selectors: ['#tutorial-inventory-overlay'],
                disableActiveInteraction: false,
                nextFormId: null,
                prevFormId: 'tutorial-previous-form',
                showButtons: [],
                prepareLayout(shell) {
                    shell.activePanel = 'inventory';
                },
            };
        case 7:
            return {
                selectors: ['#tutorial-start-fight-card', '#tutorial-focus-panel'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-start-fight-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 8:
            return {
                selectors: ['#tutorial-battle-window'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: null,
                showButtons: ['next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 9:
            return {
                selectors: ['#tutorial-attack-button', '#tutorial-battle-window'],
                disableActiveInteraction: false,
                nextFormId: null,
                prevFormId: null,
                showButtons: [],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 10:
            return {
                selectors: ['#tutorial-battle-window'],
                disableActiveInteraction: false,
                nextFormId: null,
                prevFormId: null,
                showButtons: [],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 11:
            return {
                selectors: ['#tutorial-result-banner', '#tutorial-result-window'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: null,
                showButtons: ['next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        case 12:
            return {
                selectors: ['#tutorial-result-window'],
                disableActiveInteraction: true,
                nextFormId: 'tutorial-next-form',
                prevFormId: 'tutorial-previous-form',
                showButtons: ['previous', 'next'],
                prepareLayout(shell) {
                    shell.activePanel = null;
                },
            };
        default:
            return null;
    }
}

function tutorialDescriptionHtml(tutorial) {
    const step = Number(tutorial?.step ?? 0);
    const body = tutorial?.body ?? '';
    const totalSteps = Number(tutorial?.totalSteps ?? DEFAULT_TUTORIAL_TOTAL_STEPS);
    const stepLabel = tutorial?.stepLabel ?? 'Tutorial Step';

    return `
        <div class="lumiis-driver-copy">
            <p class="lumiis-driver-step">${stepLabel} ${step}/${totalSteps}</p>
            <p class="lumiis-driver-body">${body}</p>
        </div>
    `;
}

function shouldStartTutorialDriver(step) {
    return Number(step ?? 0) !== 1;
}

function tutorialPopoverPlacement(step) {
    const currentStep = Number(step ?? 0);
    const isCompactViewport = window.matchMedia('(max-width: 639px)').matches;

    if (currentStep >= 7) {
        return {
            side: isCompactViewport ? 'bottom' : 'left',
            align: isCompactViewport ? 'center' : 'center',
        };
    }

    if (currentStep <= 2) {
        return {
            side: isCompactViewport ? 'bottom' : 'right',
            align: isCompactViewport ? 'center' : 'start',
        };
    }

    return {
        side: isCompactViewport ? 'bottom' : 'left',
        align: isCompactViewport ? 'center' : 'start',
    };
}

function isPinnedLumiisCore(enemy, encounterType) {
    return encounterType === 'lumiis' && enemy?.category === LUMIIS_CORE_CATEGORY;
}

function normalizeBattleEnemies(enemies, encounterType) {
    return (enemies ?? [])
        .map((enemy, index) => ({
            ...enemy,
            display_order: enemy?.display_order ?? index,
        }))
        .sort((left, right) => {
            const leftPinned = isPinnedLumiisCore(left, encounterType) ? 0 : 1;
            const rightPinned = isPinnedLumiisCore(right, encounterType) ? 0 : 1;

            if (leftPinned !== rightPinned) {
                return leftPinned - rightPinned;
            }

            return (left.display_order ?? 0) - (right.display_order ?? 0);
        });
}

function placeSpawnedEnemyForDisplay(enemies, enemy, encounterType) {
    if (encounterType === 'lumiis') {
        const coreIndex = enemies.findIndex((candidate) => isPinnedLumiisCore(candidate, encounterType));

        if (coreIndex !== -1) {
            enemies.splice(coreIndex + 1, 0, enemy);

            return;
        }
    }

    enemies.unshift(enemy);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeRegExp(value) {
    return String(value ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightBattleName(html, name, className) {
    if (!name) {
        return html;
    }

    return html.replace(new RegExp(escapeRegExp(escapeHtml(name)), 'gu'), `<span class="${className}">$&</span>`);
}

function formatBattleLogMessage(message, { playerName = '', enemyNames = [], actor = null, targetType = null } = {}) {
    let html = escapeHtml(message);

    html = html.replace(/^((Round|Runde)\s+\d+:)/u, '<span class="battle-log-round">$1</span>');
    html = highlightBattleName(html, playerName, 'battle-log-name-player');

    [...new Set(enemyNames.filter(Boolean))]
        .sort((left, right) => right.length - left.length)
        .forEach((enemyName) => {
            html = highlightBattleName(html, enemyName, 'battle-log-name-enemy');
        });

    const damageClass = actor === 'player' && targetType === 'enemy'
        ? 'battle-log-damage-dealt'
        : (actor === 'enemy' && targetType === 'player' ? 'battle-log-damage-taken' : null);

    if (damageClass) {
        html = html.replace(/(for\s+)(\d+)(\s+damage)/iu, `$1<span class="${damageClass}">$2</span>$3`);
        html = html.replace(/(suffers\s+)(\d+)(\s+splash damage)/iu, `$1<span class="${damageClass}">$2</span>$3`);
    }

    html = html.replace(/(recovers\s+)(\d+)(\s+HP)/iu, '$1<span class="battle-log-heal">$2</span>$3');

    return html;
}

let adminRowEditorSequence = 0;

function cloneAdminEditorValue(value) {
    return JSON.parse(JSON.stringify(value ?? {}));
}

function withAdminEditorKey(row) {
    return {
        ...cloneAdminEditorValue(row),
        _editorKey: `admin-row-${++adminRowEditorSequence}`,
    };
}

function hasSortableAdminKey(row, sortKey) {
    return Boolean(sortKey) && Object.prototype.hasOwnProperty.call(row ?? {}, sortKey);
}

Alpine.data('actionLock', ({ secondsRemaining = 0, title = 'Lumiis', syncDocumentTitle = true, labels = {} }) => ({
    secondsRemaining,
    baseTitle: title,
    syncDocumentTitle,
    labels: {
        ready: labels.ready ?? 'Ready',
        cooldownActive: labels.cooldownActive ?? 'Cooldown Active',
        actionLockOpen: labels.actionLockOpen ?? 'Action Lock Open',
    },
    intervalId: null,

    init() {
        this.updateDocumentTitle();

        if (this.secondsRemaining <= 0) {
            return;
        }

        this.intervalId = window.setInterval(() => {
            if (this.secondsRemaining <= 0) {
                this.stopCountdown();
                this.updateDocumentTitle();

                return;
            }

            this.secondsRemaining -= 1;
            this.updateDocumentTitle();

            if (this.secondsRemaining <= 0) {
                this.stopCountdown();
            }
        }, 1000);
    },

    formattedCountdown() {
        if (this.secondsRemaining <= 0) {
            return this.labels.ready;
        }

        const hours = Math.floor(this.secondsRemaining / 3600);
        const minutes = Math.floor((this.secondsRemaining % 3600) / 60);
        const seconds = this.secondsRemaining % 60;

        if (hours > 0) {
            return [hours, minutes, seconds]
                .map((value) => String(value).padStart(2, '0'))
                .join(':');
        }

        return [minutes, seconds]
            .map((value) => String(value).padStart(2, '0'))
            .join(':');
    },

    lockStateLabel() {
        return this.secondsRemaining > 0 ? this.labels.cooldownActive : this.labels.actionLockOpen;
    },

    updateDocumentTitle() {
        if (!this.syncDocumentTitle) {
            return;
        }

        const battleTitle = document.body?.dataset?.battleTitle;

        if (battleTitle) {
            document.title = `${battleTitle} | ${this.baseTitle}`;

            return;
        }

        document.title =
            this.secondsRemaining > 0
                ? `${this.formattedCountdown()} | ${this.baseTitle}`
                : `${this.labels.ready} | ${this.baseTitle}`;
    },

    stopCountdown() {
        if (this.intervalId === null) {
            return;
        }

        window.clearInterval(this.intervalId);
        this.intervalId = null;
    },
}));

Alpine.data(
    'resourceGauge',
    ({ current = 0, maximum = 0, nextTickSeconds = null, intervalSeconds = 60, pointsPerTick = 1, labels = {} } = {}) => ({
        current,
        maximum,
        nextTickSeconds,
        intervalSeconds,
        pointsPerTick,
        labels: {
            fullShort: labels.fullShort ?? 'Full',
            minutesShort: labels.minutesShort ?? 'min',
        },
        intervalId: null,
        refillTimeoutId: null,
        isRefilling: false,

        init() {
            if (this.nextTickSeconds === null || this.current >= this.maximum) {
                return;
            }

            this.intervalId = window.setInterval(() => {
                if (this.current >= this.maximum) {
                    this.current = this.maximum;
                    this.nextTickSeconds = null;
                    this.stop();

                    return;
                }

                if (this.nextTickSeconds === null) {
                    this.nextTickSeconds = this.intervalSeconds;
                }

                this.nextTickSeconds -= 1;

                if (this.nextTickSeconds > 0) {
                    return;
                }

                const nextValue = Math.min(this.maximum, this.current + this.pointsPerTick);

                if (nextValue !== this.current) {
                    this.current = nextValue;
                    this.pulseRefill();
                }

                if (this.current >= this.maximum) {
                    this.current = this.maximum;
                    this.nextTickSeconds = null;
                    this.stop();

                    return;
                }

                this.nextTickSeconds = this.intervalSeconds;
            }, 1000);
        },

        destroy() {
            this.stop();

            if (this.refillTimeoutId !== null) {
                window.clearTimeout(this.refillTimeoutId);
                this.refillTimeoutId = null;
            }
        },

        percent() {
            if (this.maximum <= 0) {
                return 0;
            }

            return Math.max(0, Math.min(100, (this.current / this.maximum) * 100));
        },

        fillStyle() {
            return { width: `${this.percent()}%` };
        },

        formattedValue() {
            return `${Number(this.current).toLocaleString()}/${Number(this.maximum).toLocaleString()}`;
        },

        compactNextTick() {
            if (this.nextTickSeconds === null || this.current >= this.maximum) {
                return this.labels.fullShort;
            }

            return `${Math.max(1, Math.ceil(this.nextTickSeconds / 60))} ${this.labels.minutesShort}`;
        },

        pulseRefill() {
            this.isRefilling = true;

            if (this.refillTimeoutId !== null) {
                window.clearTimeout(this.refillTimeoutId);
            }

            this.refillTimeoutId = window.setTimeout(() => {
                this.isRefilling = false;
                this.refillTimeoutId = null;
            }, 900);
        },

        stop() {
            if (this.intervalId === null) {
                return;
            }

            window.clearInterval(this.intervalId);
            this.intervalId = null;
        },
    }),
);

Alpine.data(
    'dashboardShell',
    ({
        activePanel = null,
        requestedPanel = null,
        chatTab = 'global',
        tutorial = null,
        actionLockSeconds = 0,
        resting = null,
        activeBattleRound = 0,
        hasActiveBattle = false,
        activeBattleCloseTitle = 'Manual Fight Still Active',
        activeBattleCloseConfirmation = 'Closing this panel does not abort the fight. Your manual fight stays active and can be resumed from the dashboard.',
        activeBattleCloseWarning = 'Use Abandon Fight only if you want to end it with no rewards.',
        activeBattleCloseStayLabel = 'Keep Fight Open',
        activeBattleCloseProceedLabel = 'Close View',
    } = {}) => ({
        activePanel,
        requestedPanel,
        chatTab,
        tutorial,
        actionLockSeconds,
        resting,
        activeBattleRound,
        hasActiveBattle,
        activeBattleCloseTitle,
        activeBattleCloseConfirmation,
        activeBattleCloseWarning,
        activeBattleCloseStayLabel,
        activeBattleCloseProceedLabel,
        tutorialDriver: null,
        tutorialDriverActive: false,
        actionLockIntervalId: null,
        hasAutoRefreshedAfterRest: false,
        beforeUnloadHandler: null,
        submitNavigationHandler: null,
        linkNavigationHandler: null,
        internalNavigationAway: false,
        internalNavigationResetId: null,
        activeBattleCloseModalOpen: false,
        activeBattleCloseAction: null,
        activeBattleCloseActionLabel: null,

        init() {
            this.syncBattleTitle();
            this.startActionLockCountdown();
            this.syncMobileChromeState();
            this.installUnloadProtection();

            if (this.tutorial?.active) {
                this.applyTutorialLayout();

                this.$nextTick(() => {
                    window.requestAnimationFrame(() => {
                        if (shouldStartTutorialDriver(this.tutorial?.step)) {
                            this.startTutorialDriver();
                        }
                    });
                });

                return;
            }

            this.restoreState();

            if (this.requestedPanel && this.canOpenPanel(this.requestedPanel)) {
                this.activePanel = this.requestedPanel;
            }

            this.syncMobileChromeState();

            this.$nextTick(() => {
                window.requestAnimationFrame(() => this.scrollMobileFocusIntoView());
            });

            this.$watch('activePanel', () => {
                this.persistState();
                this.syncMobileChromeState();
            });
            this.$watch('chatTab', () => this.persistState());
        },

        syncMobileChromeState() {
            const body = document.body;

            if (!(body instanceof HTMLElement)) {
                return;
            }

            const fullscreenPanels = ['character', 'skills', 'quests', 'inventory', 'equipment', 'settings', 'chat', 'mobile-info', 'focus'];
            const shouldHideHeader = window.innerWidth < 1024 && fullscreenPanels.includes(this.activePanel);

            body.classList.toggle('dashboard-mobile-panel-open', shouldHideHeader);
        },

        installUnloadProtection() {
            this.beforeUnloadHandler = (event) => {
                if (!this.hasActiveBattle || this.internalNavigationAway) {
                    return undefined;
                }

                event.preventDefault();
                event.returnValue = '';

                return '';
            };

            this.submitNavigationHandler = () => {
                this.markInternalNavigationAway();
            };

            this.linkNavigationHandler = (event) => {
                const target = event.target;

                if (!(target instanceof Element)) {
                    return;
                }

                const anchor = target.closest('a[href]');

                if (!(anchor instanceof HTMLAnchorElement)) {
                    return;
                }

                if (
                    anchor.target === '_blank'
                    || anchor.hasAttribute('download')
                    || event.defaultPrevented
                    || event.button !== 0
                    || event.metaKey
                    || event.ctrlKey
                    || event.shiftKey
                    || event.altKey
                ) {
                    return;
                }

                this.markInternalNavigationAway();
            };

            window.addEventListener('beforeunload', this.beforeUnloadHandler);
            document.addEventListener('submit', this.submitNavigationHandler, true);
            document.addEventListener('click', this.linkNavigationHandler, true);
        },

        markInternalNavigationAway() {
            this.internalNavigationAway = true;

            if (this.internalNavigationResetId !== null) {
                window.clearTimeout(this.internalNavigationResetId);
            }

            this.internalNavigationResetId = window.setTimeout(() => {
                this.internalNavigationAway = false;
                this.internalNavigationResetId = null;
            }, 4000);
        },

        resetInternalNavigationAway() {
            this.internalNavigationAway = false;

            if (this.internalNavigationResetId === null) {
                return;
            }

            window.clearTimeout(this.internalNavigationResetId);
            this.internalNavigationResetId = null;
        },

        startActionLockCountdown() {
            if (this.actionLockSeconds <= 0) {
                return;
            }

            this.actionLockIntervalId = window.setInterval(() => {
                if (this.actionLockSeconds <= 0) {
                    this.stopActionLockCountdown();

                    return;
                }

                this.actionLockSeconds -= 1;

                if (this.actionLockSeconds > 0) {
                    return;
                }

                this.stopActionLockCountdown();

                if (this.isResting() && !this.hasAutoRefreshedAfterRest) {
                    this.hasAutoRefreshedAfterRest = true;
                    window.setTimeout(() => window.location.reload(), 250);
                }
            }, 1000);
        },

        stopActionLockCountdown() {
            if (this.actionLockIntervalId === null) {
                return;
            }

            window.clearInterval(this.actionLockIntervalId);
            this.actionLockIntervalId = null;
        },

        openPanel(panel) {
            if (!this.canOpenPanel(panel)) {
                return;
            }

            this.activePanel = panel;
            this.syncMobileChromeState();
        },

        closePanel() {
            if (this.activePanel === null) {
                return;
            }

            if (this.hasActiveBattle) {
                this.openActiveBattleCloseModal(() => {
                    this.performPanelClose();
                }, 'Close Panel');

                return;
            }

            this.performPanelClose();
        },

        performPanelClose() {
            this.activePanel = null;
            this.syncMobileChromeState();
        },

        confirmActiveBattleClose(event = null) {
            if (!this.hasActiveBattle) {
                return true;
            }

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            this.resetInternalNavigationAway();

            const anchor = event?.currentTarget instanceof HTMLAnchorElement
                ? event.currentTarget
                : event?.target instanceof Element
                    ? event.target.closest('a[href]')
                    : null;
            const href = anchor instanceof HTMLAnchorElement ? anchor.href : null;

            this.openActiveBattleCloseModal(() => {
                if (typeof href === 'string' && href !== '') {
                    this.markInternalNavigationAway();
                    window.location.assign(href);
                }
            });

            return false;
        },

        confirmNavigation(href = null) {
            if (typeof href !== 'string' || href === '') {
                return;
            }

            if (!this.hasActiveBattle) {
                this.markInternalNavigationAway();
                window.location.assign(href);

                return;
            }

            this.resetInternalNavigationAway();
            this.openActiveBattleCloseModal(() => {
                this.markInternalNavigationAway();
                window.location.assign(href);
            }, 'Leave View');
        },

        openActiveBattleCloseModal(action = null, actionLabel = null) {
            this.activeBattleCloseAction = typeof action === 'function' ? action : null;
            this.activeBattleCloseActionLabel = actionLabel ?? this.activeBattleCloseProceedLabel;
            this.activeBattleCloseModalOpen = true;
        },

        dismissActiveBattleCloseModal() {
            this.activeBattleCloseModalOpen = false;
            this.activeBattleCloseAction = null;
            this.activeBattleCloseActionLabel = null;
            this.resetInternalNavigationAway();
        },

        proceedActiveBattleCloseModal() {
            const action = this.activeBattleCloseAction;

            this.activeBattleCloseModalOpen = false;
            this.activeBattleCloseAction = null;
            this.activeBattleCloseActionLabel = null;

            if (typeof action === 'function') {
                action();
            }
        },

        isActionLocked() {
            return this.actionLockSeconds > 0;
        },

        isResting() {
            return Boolean(this.resting) && this.isActionLocked();
        },

        syncBattleTitle() {
            if (this.activeBattleRound > 0) {
                document.body.dataset.battleTitle = `R${this.activeBattleRound}`;

                return;
            }

            delete document.body.dataset.battleTitle;
        },

        actionLockMinutesRemaining() {
            return Math.max(1, Math.ceil(this.actionLockSeconds / 60));
        },

        formattedActionLockCountdown() {
            if (this.actionLockSeconds <= 0) {
                return '00:00';
            }

            const hours = Math.floor(this.actionLockSeconds / 3600);
            const minutes = Math.floor((this.actionLockSeconds % 3600) / 60);
            const seconds = this.actionLockSeconds % 60;

            if (hours > 0) {
                return [hours, minutes, seconds]
                    .map((value) => String(value).padStart(2, '0'))
                    .join(':');
            }

            return [minutes, seconds]
                .map((value) => String(value).padStart(2, '0'))
                .join(':');
        },

        actionLockReadyAtLabel() {
            if (this.actionLockSeconds <= 0) {
                return '';
            }

            const locale = document.documentElement?.lang || undefined;
            const readyAt = new Date(Date.now() + (this.actionLockSeconds * 1000));

            return new Intl.DateTimeFormat(locale, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }).format(readyAt);
        },

        restingLockLabel() {
            if (!this.isResting()) {
                return '';
            }

            return `${this.resting.label} · ${this.actionLockMinutesRemaining()} min left`;
        },

        openChatTab(tab) {
            if (!this.canOpenPanel('chat')) {
                return;
            }

            this.chatTab = tab;
            this.activePanel = 'chat';
            this.syncMobileChromeState();
        },

        canOpenPanel(panel) {
            if (!this.tutorial?.active) {
                return true;
            }

            const step = Number(this.tutorial?.step ?? 0);

            if (panel === 'character') {
                return step === 1;
            }

            if (panel === 'inventory') {
                return step === 6;
            }

            if (panel === 'settings' || panel === 'chat' || panel === 'mobile-info') {
                return false;
            }

            return false;
        },

        restoreState() {
            if (this.tutorial?.active) {
                return;
            }

            try {
                const payload = window.localStorage?.getItem(DASHBOARD_SHELL_STORAGE_KEY);

                if (!payload) {
                    return;
                }

                const state = JSON.parse(payload);

                this.activePanel = ['character', 'skills', 'quests', 'inventory', 'equipment', 'chat', 'settings', 'mobile-info'].includes(state.activePanel)
                    ? state.activePanel
                    : null;
                this.chatTab = ['global', 'whisper'].includes(state.chatTab) ? state.chatTab : this.chatTab;
            } catch {
                // Broken local UI state should never block the game screen.
            }
        },

        persistState() {
            if (this.tutorial?.active) {
                return;
            }

            try {
                window.localStorage?.setItem(
                    DASHBOARD_SHELL_STORAGE_KEY,
                    JSON.stringify({
                        activePanel: this.activePanel === 'focus' ? null : this.activePanel,
                        chatTab: this.chatTab,
                    }),
                );
            } catch {
                // Storage availability varies across browsers and privacy modes.
            }
        },

        scrollMobileFocusIntoView() {
            if (window.innerWidth >= 1024) {
                return;
            }

            const focusPanel = document.querySelector('#tutorial-focus-panel.dashboard-focus-panel-mobile-open');

            if (!(focusPanel instanceof HTMLElement)) {
                return;
            }

            const top = focusPanel.getBoundingClientRect().top + window.scrollY - 8;

            if (!Number.isFinite(top)) {
                return;
            }

            window.scrollTo({
                top: Math.max(0, top),
            });
        },

        applyTutorialLayout() {
            const config = tutorialStepConfig(this.tutorial?.step);

            if (!config) {
                return;
            }

            config.prepareLayout?.(this);
        },

        startTutorialDriver() {
            if (!this.tutorial?.active) {
                return;
            }

            const config = tutorialStepConfig(this.tutorial.step);

            if (!config) {
                return;
            }

            this.applyTutorialLayout();
            this.tutorialDriver?.destroy();

            const placement = tutorialPopoverPlacement(this.tutorial?.step);

            this.tutorialDriver = driver({
                allowClose: false,
                allowKeyboardControl: false,
                overlayClickBehavior: () => { },
                overlayColor: 'rgba(22, 17, 15, 0.88)',
                smoothScroll: true,
                stagePadding: 14,
                stageRadius: 20,
                popoverClass: 'lumiis-driver-popover',
                disableActiveInteraction: config.disableActiveInteraction,
                showButtons: config.showButtons,
                nextBtnText: this.tutorial.nextLabel ?? 'Continue',
                prevBtnText: this.tutorial.previousLabel ?? 'Back',
                doneBtnText: this.tutorial.nextLabel ?? 'Continue',
                onDestroyed: () => {
                    this.tutorialDriverActive = false;
                },
            });

            this.tutorialDriverActive = true;
            this.tutorialDriver.highlight({
                element: () => {
                    this.applyTutorialLayout();

                    return findTutorialElement(config.selectors);
                },
                disableActiveInteraction: config.disableActiveInteraction,
                popover: {
                    title: this.tutorial.title ?? 'Tutorial',
                    description: tutorialDescriptionHtml(this.tutorial),
                    side: placement.side,
                    align: placement.align,
                    showButtons: config.showButtons,
                    nextBtnText: this.tutorial.nextLabel ?? 'Continue',
                    prevBtnText: this.tutorial.previousLabel ?? 'Back',
                    doneBtnText: this.tutorial.nextLabel ?? 'Continue',
                    onNextClick: () => {
                        submitFormById(config.nextFormId);
                    },
                    onPrevClick: () => {
                        submitFormById(config.prevFormId);
                    },
                },
            });
        },
    }),
);

Alpine.data(
    'staminaPreview',
    ({ current = 0, maximum = 0, nextTickSeconds = null, intervalSeconds = 600, labels = {} } = {}) => ({
        current,
        maximum,
        nextTickSeconds,
        intervalSeconds,
        labels: {
            fullyRecovered: labels.fullyRecovered ?? 'Fully recovered',
            nextPointIn: labels.nextPointIn ?? 'Next point in',
            minutesShort: labels.minutesShort ?? 'min',
            fullShort: labels.fullShort ?? 'Full',
        },
        intervalId: null,

        init() {
            if (this.nextTickSeconds === null || this.current >= this.maximum) {
                return;
            }

            this.intervalId = window.setInterval(() => {
                if (this.current >= this.maximum) {
                    this.nextTickSeconds = null;
                    this.stop();

                    return;
                }

                if (this.nextTickSeconds === null) {
                    this.nextTickSeconds = this.intervalSeconds;
                }

                this.nextTickSeconds -= 1;

                if (this.nextTickSeconds > 0) {
                    return;
                }

                this.current += 1;

                if (this.current >= this.maximum) {
                    this.current = this.maximum;
                    this.nextTickSeconds = null;
                    this.stop();

                    return;
                }

                this.nextTickSeconds = this.intervalSeconds;
            }, 1000);
        },

        formattedNextTick() {
            if (this.nextTickSeconds === null || this.current >= this.maximum) {
                return this.labels.fullyRecovered;
            }

            return `${this.labels.nextPointIn} ${Math.max(1, Math.ceil(this.nextTickSeconds / 60))} ${this.labels.minutesShort}`;
        },

        compactNextTick() {
            if (this.nextTickSeconds === null || this.current >= this.maximum) {
                return this.labels.fullShort;
            }

            return `${Math.max(1, Math.ceil(this.nextTickSeconds / 60))} ${this.labels.minutesShort}`;
        },

        stop() {
            if (this.intervalId === null) {
                return;
            }

            window.clearInterval(this.intervalId);
            this.intervalId = null;
        },
    }),
);

Alpine.data(
    'equipmentBoard',
    ({ initialInfoSlot = null } = {}) => ({
        infoSlot: initialInfoSlot,

        toggleInfo(slot) {
            this.infoSlot = this.infoSlot === slot ? null : slot;
        },

        closeInfo() {
            this.infoSlot = null;
        },

        isInfoOpen(slot) {
            return this.infoSlot === slot;
        },
    }),
);

Alpine.data(
    'adminRowEditor',
    ({ rows = [], defaultRow = {}, sortKey = null, sortStep = 10, itemSlugs = [], monsterSlugs = [] } = {}) => ({
        rows: (rows ?? []).map((row) => withAdminEditorKey(row)),
        defaultRow,
        sortKey,
        sortStep,
        itemSlugs,
        monsterSlugs,
        draggedIndex: null,

        init() {
            this.syncSortOrder();
        },

        addRow() {
            this.rows.push(this.makeRow());
            this.syncSortOrder();
        },

        duplicateRow(index) {
            const sourceRow = this.rows[index];

            if (!sourceRow) {
                return;
            }

            this.rows.splice(index + 1, 0, this.makeRow(sourceRow));
            this.syncSortOrder();
        },

        removeRow(index) {
            this.rows.splice(index, 1);
            this.syncSortOrder();
        },

        startDrag(index) {
            this.draggedIndex = index;
        },

        dropRow(index) {
            if (this.draggedIndex === null || this.draggedIndex === index) {
                this.clearDrag();

                return;
            }

            const [row] = this.rows.splice(this.draggedIndex, 1);
            const targetIndex = this.draggedIndex < index ? index - 1 : index;

            this.rows.splice(targetIndex, 0, row);
            this.syncSortOrder();
            this.clearDrag();
        },

        clearDrag() {
            this.draggedIndex = null;
        },

        rowLabel(index) {
            return `Row ${index + 1}`;
        },

        makeRow(seed = null) {
            const row = withAdminEditorKey(seed ?? this.defaultRow);

            if (hasSortableAdminKey(row, this.sortKey)) {
                row[this.sortKey] = (this.rows.length + 1) * this.sortStep;
            }

            return row;
        },

        syncSortOrder() {
            if (!this.sortKey) {
                return;
            }

            this.rows = this.rows.map((row, index) => {
                if (!hasSortableAdminKey(row, this.sortKey)) {
                    return row;
                }

                return {
                    ...row,
                    [this.sortKey]: (index + 1) * this.sortStep,
                };
            });
        },
    }),
);

Alpine.data('battleReplay', ({ replay = null, autoOpen = false, labels = {} } = {}) => ({
    replay,
    autoOpen,
    labels: {
        replayReady: labels.replayReady ?? 'Battle replay ready.',
        victory: labels.victory ?? 'Victory',
        defeat: labels.defeat ?? 'Defeat',
        retreat: labels.retreat ?? 'Retreat',
        playing: labels.playing ?? 'Playing',
        finished: labels.finished ?? 'Finished',
        ready: labels.ready ?? 'Ready',
        battleWon: labels.battleWon ?? 'Battle Won',
        battleLost: labels.battleLost ?? 'Battle Lost',
        fallen: labels.fallen ?? 'Fallen',
        encounterTypes: {
            monster: labels?.encounterTypes?.monster ?? 'Monster',
            boss: labels?.encounterTypes?.boss ?? 'Boss',
            lumiis: labels?.encounterTypes?.lumiis ?? 'Lumiis',
        },
        categories: {
            monster: labels?.categories?.monster ?? 'Monster',
            boss: labels?.categories?.boss ?? 'Boss',
            lumiisCore: labels?.categories?.lumiisCore ?? 'Lumiis Core',
            freedSoul: labels?.categories?.freedSoul ?? 'Freed Soul',
            tutorialPack: labels?.categories?.tutorialPack ?? 'Tutorial Pack',
        },
    },
    isOpen: false,
    isPlaying: false,
    isFinished: false,
    stepIndex: -1,
    currentRound: 1,
    currentMessage: '',
    displayedSteps: [],
    playerCurrentHealth: 0,
    enemies: [],
    intervalId: null,
    playerDamageEvent: null,
    enemyDamageEvents: {},
    damageTimeoutIds: [],
    damageEventCounter: 0,

    init() {
        this.resetState();

        if (this.autoOpen && this.replay) {
            this.open();
        }
    },

    resetState() {
        this.stop();
        this.clearDamageEvents();
        this.displayedSteps = [];
        this.stepIndex = -1;
        this.isFinished = false;
        this.currentRound = this.replay?.steps?.[0]?.round ?? 1;
        this.currentMessage = this.replay?.summary?.message ?? this.labels.replayReady;
        this.playerCurrentHealth = this.replay?.player?.health ?? 0;
        this.enemies = normalizeBattleEnemies(
            (this.replay?.enemies ?? []).map((enemy) => ({
                ...enemy,
                current_health: enemy.spawned === false ? 0 : enemy.health,
                spawned: enemy.spawned !== false,
            })),
            this.replay?.encounter_type,
        );
    },

    open() {
        if (!this.replay) {
            return;
        }

        this.isOpen = true;
        this.resetState();
        this.syncBattleTitle();
        this.start();
    },

    close() {
        this.isOpen = false;
        this.stop();
        this.clearBattleTitle();
    },

    restart() {
        this.open();
    },

    start() {
        if (!this.replay || this.isPlaying) {
            return;
        }

        this.isPlaying = true;

        if (!this.advanceStep()) {
            this.finish();

            return;
        }

        this.intervalId = window.setInterval(() => {
            if (!this.advanceStep()) {
                this.finish();
            }
        }, 1500);
    },

    advanceStep() {
        const steps = this.replay?.steps ?? [];
        const nextIndex = this.stepIndex + 1;

        if (nextIndex >= steps.length) {
            return false;
        }

        const step = steps[nextIndex];

        this.stepIndex = nextIndex;
        this.currentRound = step.round ?? this.currentRound;
        this.currentMessage = step.message ?? this.currentMessage;
        this.applyStep(step);
        this.syncBattleTitle();
        // Keep the newest line at the top of the log instead of relying on CSS reverse-flow.
        // That makes the scroll position predictable across mobile browsers.
        this.displayedSteps.unshift(step);

        this.$nextTick(() => {
            const log = this.$refs.log;

            if (log) {
                // New messages are inserted at the top, so we keep the viewport pinned there.
                log.scrollTop = 0;
            }
        });

        return true;
    },

    applyStep(step) {
        if (step.target_type === 'spawn') {
            const enemyIndex = this.enemies.findIndex((candidate) => candidate.id === step.target_id);

            if (enemyIndex !== -1) {
                const [enemy] = this.enemies.splice(enemyIndex, 1);

                enemy.spawned = true;
                enemy.current_health = step.remaining_health ?? enemy.health;
                placeSpawnedEnemyForDisplay(this.enemies, enemy, this.replay?.encounter_type);
            }

            return;
        }

        if (step.target_type === 'player') {
            this.playerCurrentHealth = step.remaining_health ?? this.playerCurrentHealth;

            if (step.damage) {
                this.triggerPlayerDamage(step.damage);
            }

            return;
        }

        if (step.target_type !== 'enemy') {
            return;
        }

        const enemy = this.enemies.find((candidate) => candidate.id === step.target_id);

        if (enemy) {
            enemy.current_health = step.remaining_health ?? enemy.current_health;

            if (step.damage) {
                this.triggerEnemyDamage(enemy.id, step.damage);
            }

            if (enemy.current_health <= 0) {
                this.moveEnemyToBack(enemy.id);
            }
        }
    },

    finish() {
        this.stop();
        this.isFinished = true;
        this.currentMessage = this.replay?.summary?.message ?? this.currentMessage;
        this.clearBattleTitle();
    },

    stop() {
        if (this.intervalId !== null) {
            window.clearInterval(this.intervalId);
            this.intervalId = null;
        }

        this.isPlaying = false;
    },

    syncBattleTitle() {
        if (!document.body) {
            return;
        }

        if (!this.isOpen || this.isFinished) {
            this.clearBattleTitle();

            return;
        }

        document.body.dataset.battleTitle = `R${this.currentRound}`;
    },

    clearBattleTitle() {
        if (!document.body?.dataset) {
            return;
        }

        delete document.body.dataset.battleTitle;
    },

    healthPercent(current, maximum) {
        if (!maximum || maximum <= 0) {
            return 0;
        }

        return Math.max(0, Math.min(100, Math.round((current / maximum) * 100)));
    },

    translatedCategory(category) {
        return {
            Monster: this.labels.categories.monster,
            Boss: this.labels.categories.boss,
            'Lumiis Core': this.labels.categories.lumiisCore,
            'Freed Soul': this.labels.categories.freedSoul,
            'Tutorial Pack': this.labels.categories.tutorialPack,
        }[category] ?? category;
    },

    translatedEncounterType(encounterType) {
        return {
            monster: this.labels.encounterTypes.monster,
            boss: this.labels.encounterTypes.boss,
            lumiis: this.labels.encounterTypes.lumiis,
        }[encounterType] ?? encounterType;
    },

    replayStatusLabel() {
        // The replay already knows the final outcome, but we only surface
        // Victory/Defeat once the animated playback has actually reached the end.
        if (this.isFinished) {
            return this.replay?.victory ? this.labels.victory : this.labels.retreat;
        }

        return this.isPlaying ? this.labels.playing : this.labels.replayReady;
    },

    outcomeClass() {
        return this.replay?.victory ? 'battle-outcome-victory' : 'battle-outcome-defeat';
    },

    outcomeBadgeLabel() {
        return this.replay?.victory ? this.labels.victory : this.labels.defeat;
    },

    outcomeTitleLabel() {
        return this.replay?.victory ? this.labels.battleWon : this.labels.battleLost;
    },

    enemyAttackLabel(enemy) {
        return enemy?.current_health <= 0 ? this.labels.fallen : enemy?.attack;
    },

    moveEnemyToBack(enemyId) {
        const enemyIndex = this.enemies.findIndex((candidate) => candidate.id === enemyId);

        if (enemyIndex === -1) {
            return;
        }

        const [enemy] = this.enemies.splice(enemyIndex, 1);

        if (isPinnedLumiisCore(enemy, this.replay?.encounter_type)) {
            this.enemies.unshift(enemy);

            return;
        }

        this.enemies.push(enemy);
    },

    triggerPlayerDamage(damage) {
        if (!damage || damage <= 0) {
            return;
        }

        const key = `player-${++this.damageEventCounter}`;

        this.playerDamageEvent = {
            key,
            value: damage,
        };

        this.queueDamageClear(() => {
            if (this.playerDamageEvent?.key === key) {
                this.playerDamageEvent = null;
            }
        });
    },

    triggerEnemyDamage(enemyId, damage) {
        if (!damage || damage <= 0) {
            return;
        }

        const key = `enemy-${enemyId}-${++this.damageEventCounter}`;

        this.enemyDamageEvents = {
            ...this.enemyDamageEvents,
            [enemyId]: {
                key,
                value: damage,
            },
        };

        this.queueDamageClear(() => {
            if (this.enemyDamageEvents?.[enemyId]?.key === key) {
                const nextEvents = { ...this.enemyDamageEvents };
                delete nextEvents[enemyId];
                this.enemyDamageEvents = nextEvents;
            }
        });
    },

    enemyDamageEvent(enemyId) {
        return this.enemyDamageEvents?.[enemyId] ?? null;
    },

    queueDamageClear(callback) {
        const timeoutId = window.setTimeout(() => {
            callback();
            this.damageTimeoutIds = this.damageTimeoutIds.filter((id) => id !== timeoutId);
        }, 1050);

        this.damageTimeoutIds.push(timeoutId);
    },

    clearDamageEvents() {
        for (const timeoutId of this.damageTimeoutIds) {
            window.clearTimeout(timeoutId);
        }

        this.damageTimeoutIds = [];
        this.playerDamageEvent = null;
        this.enemyDamageEvents = {};
    },

    formattedCurrentMessage() {
        return formatBattleLogMessage(this.currentMessage, {
            playerName: this.replay?.player?.name ?? '',
            enemyNames: (this.replay?.enemies ?? []).flatMap((enemy) => [enemy?.label, enemy?.name]).filter(Boolean),
            actor: this.replay?.steps?.[this.stepIndex]?.actor ?? null,
            targetType: this.replay?.steps?.[this.stepIndex]?.target_type ?? null,
        });
    },

    formattedStepMessage(step) {
        return formatBattleLogMessage(step?.message ?? '', {
            playerName: this.replay?.player?.name ?? '',
            enemyNames: (this.replay?.enemies ?? []).flatMap((enemy) => [enemy?.label, enemy?.name]).filter(Boolean),
            actor: step?.actor ?? null,
            targetType: step?.target_type ?? null,
        });
    },
}));

Alpine.data('battleResolution', ({ result = null, autoOpen = false, labels = {} } = {}) => ({
    result,
    autoOpen,
    labels: {
        victory: labels?.victory ?? 'Victory',
        retreat: labels?.retreat ?? 'Retreat',
        defeat: labels?.defeat ?? 'Defeat',
        fallen: labels?.fallen ?? 'Fallen',
        encounterTypes: {
            monster: labels?.encounterTypes?.monster ?? 'Monster',
            boss: labels?.encounterTypes?.boss ?? 'Boss',
            lumiis: labels?.encounterTypes?.lumiis ?? 'Lumiis',
        },
        titles: {
            bossSoulRises: labels?.titles?.bossSoulRises ?? 'A soul rises into the sky.',
            fieldIsYours: labels?.titles?.fieldIsYours ?? 'The field is yours.',
            drivenBack: labels?.titles?.drivenBack ?? 'You were driven back.',
        },
        categories: {
            monster: labels?.categories?.monster ?? 'Monster',
            boss: labels?.categories?.boss ?? 'Boss',
            lumiisCore: labels?.categories?.lumiisCore ?? 'Lumiis Core',
            freedSoul: labels?.categories?.freedSoul ?? 'Freed Soul',
            tutorialPack: labels?.categories?.tutorialPack ?? 'Tutorial Pack',
        },
    },
    isOpen: false,
    playerCurrentHealth: 0,
    enemies: [],

    init() {
        this.prepareState();

        if (this.autoOpen && this.result) {
            this.open();
        }
    },

    prepareState() {
        if (!this.result) {
            return;
        }

        this.playerCurrentHealth = this.result?.player?.health ?? 0;
        this.enemies = normalizeBattleEnemies(
            (this.result?.enemies ?? []).map((enemy) => ({
                ...enemy,
                current_health: enemy.health,
                is_dead: false,
            })),
            this.result?.encounter_type,
        );

        for (const step of this.result?.steps ?? []) {
            if (step.target_type === 'player') {
                this.playerCurrentHealth = step.remaining_health ?? this.playerCurrentHealth;

                continue;
            }

            if (step.target_type !== 'enemy') {
                continue;
            }

            const enemy = this.enemies.find((candidate) => candidate.id === step.target_id);

            if (!enemy) {
                continue;
            }

            enemy.current_health = step.remaining_health ?? enemy.current_health;
            enemy.is_dead = enemy.current_health <= 0;

            if (enemy.is_dead) {
                this.moveEnemyToBack(enemy.id);
            }
        }
    },

    open() {
        if (!this.result) {
            return;
        }

        this.isOpen = true;
    },

    close() {
        this.isOpen = false;
    },

    healthPercent(current, maximum) {
        if (!maximum || maximum <= 0) {
            return 0;
        }

        return Math.max(0, Math.min(100, Math.round((current / maximum) * 100)));
    },

    outcomeClass() {
        return this.result?.victory ? 'battle-outcome-victory' : 'battle-outcome-defeat';
    },

    resultStatusLabel() {
        return this.result?.victory ? this.labels.victory : this.labels.retreat;
    },

    resultOutcomeBadge() {
        return this.result?.victory ? this.labels.victory : this.labels.defeat;
    },

    resultOutcomeTitle() {
        if (!this.result?.victory) {
            return this.labels.titles.drivenBack;
        }

        return this.result?.encounter_type === 'boss'
            ? this.labels.titles.bossSoulRises
            : this.labels.titles.fieldIsYours;
    },

    translatedCategory(category) {
        return {
            Monster: this.labels.categories.monster,
            Boss: this.labels.categories.boss,
            'Lumiis Core': this.labels.categories.lumiisCore,
            'Freed Soul': this.labels.categories.freedSoul,
            'Tutorial Pack': this.labels.categories.tutorialPack,
        }[category] ?? category;
    },

    translatedEncounterType(encounterType) {
        return {
            monster: this.labels.encounterTypes.monster,
            boss: this.labels.encounterTypes.boss,
            lumiis: this.labels.encounterTypes.lumiis,
        }[encounterType] ?? encounterType;
    },

    enemyAttackLabel(enemy) {
        return enemy?.is_dead ? this.labels.fallen : enemy?.attack;
    },

    moveEnemyToBack(enemyId) {
        const enemyIndex = this.enemies.findIndex((candidate) => candidate.id === enemyId);

        if (enemyIndex === -1) {
            return;
        }

        const [enemy] = this.enemies.splice(enemyIndex, 1);

        if (isPinnedLumiisCore(enemy, this.result?.encounter_type)) {
            this.enemies.unshift(enemy);

            return;
        }

        this.enemies.push(enemy);
    },
}));

Alpine.data(
    'quantityStepper',
    ({ min = 1, max = 1, value = 1 } = {}) => ({
        min: Math.max(1, Number(min) || 1),
        max: Math.max(1, Number(max) || 1),
        value: Number(value) || 1,

        init() {
            this.sync();
        },

        clamp(candidate) {
            const parsed = Math.trunc(Number(candidate));

            if (Number.isNaN(parsed)) {
                return this.min;
            }

            return Math.max(this.min, Math.min(this.max, parsed));
        },

        sync() {
            this.value = this.clamp(this.value);

            if (this.$refs.input) {
                this.$refs.input.value = this.value;
            }
        },

        decrement() {
            this.value = this.clamp(this.value - 1);
            this.sync();
        },

        increment() {
            this.value = this.clamp(this.value + 1);
            this.sync();
        },

        setMax() {
            this.value = this.max;
            this.sync();
        },
    }),
);

Alpine.data(
    'inventoryList',
    ({ totalItems = 0, perPage = 10, categories = [], defaultCategory = null } = {}) => ({
        storageKey: 'dashboard.inventory.activeCategory',
        totalItems,
        perPage,
        categories,
        activeCategory: defaultCategory,
        currentPage: 1,
        infoItemId: null,

        init() {
            const savedCategory = window.localStorage?.getItem(this.storageKey);

            if (savedCategory && this.categories.some((candidate) => candidate.key === savedCategory)) {
                this.activeCategory = savedCategory;
            }

            if (!this.activeCategory && this.categories.length > 0) {
                this.activeCategory = this.categories[0].key;
            }
        },

        categoryCount(categoryKey) {
            const category = this.categories.find((candidate) => candidate.key === categoryKey);

            return Number(category?.count ?? 0);
        },

        filteredTotalItems() {
            if (!this.activeCategory) {
                return this.totalItems;
            }

            return this.categoryCount(this.activeCategory);
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.filteredTotalItems() / this.perPage));
        },

        startIndex() {
            return (this.currentPage - 1) * this.perPage;
        },

        endIndex() {
            return Math.min(this.filteredTotalItems(), this.startIndex() + this.perPage);
        },

        visibleStartNumber() {
            return this.filteredTotalItems() === 0 ? 0 : this.startIndex() + 1;
        },

        setCategory(categoryKey) {
            if (!this.categories.some((candidate) => candidate.key === categoryKey)) {
                return;
            }

            this.activeCategory = categoryKey;
            window.localStorage?.setItem(this.storageKey, categoryKey);
            this.goToPage(1);
        },

        isVisible(categoryKey, index) {
            if (this.activeCategory && this.activeCategory !== categoryKey) {
                return false;
            }

            return index >= this.startIndex() && index < this.startIndex() + this.perPage;
        },

        goToPage(page) {
            const nextPage = Math.max(1, Math.min(this.totalPages(), Number(page) || 1));

            this.currentPage = nextPage;
            this.infoItemId = null;
        },

        previousPage() {
            this.goToPage(this.currentPage - 1);
        },

        nextPage() {
            this.goToPage(this.currentPage + 1);
        },

        toggleInfo(itemId) {
            this.infoItemId = this.infoItemId === itemId ? null : itemId;
        },

        isInfoOpen(itemId) {
            return this.infoItemId === itemId;
        },
    }),
);

window.Alpine = Alpine;

Alpine.start();
