import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import 'bootstrap-icons/font/bootstrap-icons.css';

// Lightweight overlay toggles (vanilla JS) to replace simple Alpine interactions
document.addEventListener('DOMContentLoaded', () => {
    // Remove legacy Alpine `x-cloak` attributes so elements aren't permanently hidden
    document.querySelectorAll('[x-cloak]').forEach((el) => el.removeAttribute('x-cloak'));
    // ensure overlays are hidden by default
    document.querySelectorAll('.dashboard-overlay-panel').forEach((el) => {
        if (!el.classList.contains('d-none')) el.classList.add('d-none');
    });

    // compute and keep CSS var for header height so portaled popovers avoid being hidden
    function computeDashboardHeaderHeight() {
        try {
            const headerEl = document.querySelector('.dashboard-classic-header-wrapper') || document.querySelector('header') || document.querySelector('nav');

            if (!(headerEl instanceof HTMLElement)) {
                try { document.documentElement.style.removeProperty('--dashboard-header-height'); } catch (e) { }
                return;
            }

            const rect = headerEl.getBoundingClientRect();
            const headerHeight = rect ? Math.ceil(rect.height) : 0;

            try { document.documentElement.style.setProperty('--dashboard-header-height', `${headerHeight}px`); } catch (e) { /* ignore */ }
        } catch (e) {
            // ignore
        }
    }

    computeDashboardHeaderHeight();
    window.addEventListener('resize', computeDashboardHeaderHeight);
    window.addEventListener('load', computeDashboardHeaderHeight);
    try {
        const headerObserverTarget = document.querySelector('.dashboard-classic-header-wrapper') || document.querySelector('header') || document.querySelector('nav');
        if (headerObserverTarget) {
            const mo = new MutationObserver(() => computeDashboardHeaderHeight());
            mo.observe(headerObserverTarget, { attributes: true, childList: true, subtree: true });
        }
    } catch (e) { /* ignore */ }

    // open panels from elements with data-open-panel
    document.querySelectorAll('[data-open-panel]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const panel = btn.dataset.openPanel;
            if (!panel) return;

            // hide all overlays first
            document.querySelectorAll('.dashboard-overlay-panel').forEach((o) => o.classList.add('d-none'));

            // find overlays that match the requested panel
            const overlays = Array.from(document.querySelectorAll('.dashboard-overlay-panel')).filter((o) => {
                if (o.dataset.panel && o.dataset.panel === panel) return true;
                if (o.id && o.id.includes(`-${panel}-overlay`)) return true;
                return false;
            });

            if (overlays.length) {
                overlays.forEach((ov) => ov.classList.remove('d-none'));
            } else {
                // fallback to common id patterns
                const fallback = document.getElementById(`dashboard-${panel}-overlay`) || document.getElementById(`dashboard-${panel}-overlay-legacy`) || document.getElementById(`tutorial-${panel}-overlay`);
                if (fallback) fallback.classList.remove('d-none');
            }
            // when opening any overlay, hide mobile rails to avoid visual clutter
            document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.add('d-none'));
        });
    });

    // close buttons
    document.querySelectorAll('.dashboard-overlay-close').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const overlay = btn.closest('.dashboard-overlay-panel');
            if (overlay) overlay.classList.add('d-none');
        });
    });

    // backdrop click closes overlay
    document.querySelectorAll('.dashboard-overlay-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            const overlay = backdrop.closest('.dashboard-overlay-panel');
            if (overlay) overlay.classList.add('d-none');
        });
    });

    /* Modal handling (replacement for Alpine modal component) */
    const modals = Array.from(document.querySelectorAll('.js-modal'));

    function getFocusableElements(container) {
        const selector = 'a, button, input:not([type="hidden"]):not([disabled]), textarea, select, details, [tabindex]:not([tabindex="-1"])';
        return Array.from(container.querySelectorAll(selector)).filter((el) => !el.hasAttribute('disabled'));
    }

    function openModalByName(name) {
        const modal = document.querySelector(`.js-modal[data-modal-name="${name}"]`);
        if (!modal) return;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        const focusables = getFocusableElements(modal);
        if (focusables.length) focusables[0].focus();
        modal.__previouslyFocused = document.activeElement;
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
        if (modal.__previouslyFocused && typeof modal.__previouslyFocused.focus === 'function') {
            modal.__previouslyFocused.focus();
        }
    }

    // Listen for custom open/close modal events
    window.addEventListener('open-modal', (e) => {
        const name = e.detail;
        if (!name) return;
        openModalByName(name);
    });
    window.addEventListener('close-modal', (e) => {
        const name = e.detail;
        if (!name) return;
        const modal = document.querySelector(`.js-modal[data-modal-name="${name}"]`);
        if (modal) closeModal(modal);
    });

    // data-open-modal triggers
    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const name = btn.dataset.openModal;
            if (!name) return;
            openModalByName(name);
        });
    });

    // support legacy dashboard-panel-open custom events
    window.addEventListener('dashboard-panel-open', (e) => {
        const panel = (e.detail && (e.detail.panel || e.detail)) || null;
        if (!panel) return;
        // mimic click on matching control if present, otherwise open overlays directly
        const trigger = document.querySelector(`[data-open-panel="${panel}"]`);
        if (trigger) trigger.click();
        else {
            // hide other overlays and open matching overlays
            document.querySelectorAll('.dashboard-overlay-panel').forEach((o) => o.classList.add('d-none'));
            const overlays = Array.from(document.querySelectorAll('.dashboard-overlay-panel')).filter((o) => {
                if (o.dataset.panel && o.dataset.panel === panel) return true;
                if (o.id && o.id.includes(`-${panel}-overlay`)) return true;
                return false;
            });
            overlays.forEach((ov) => ov.classList.remove('d-none'));
            // also hide mobile rails to avoid visual clutter when opening via event fallback
            document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.add('d-none'));
        }
    });

    // data-close-modal triggers
    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = btn.closest('.js-modal');
            if (modal) closeModal(modal);
        });
    });

    // backdrop click closes modal
    document.querySelectorAll('.js-modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            const modal = backdrop.closest('.js-modal');
            if (modal) closeModal(modal);
        });
    });

    // Escape key closes topmost modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.js-modal[style*="display: block"]');
            if (openModal) closeModal(openModal);
        }
        if (e.key === 'Tab') {
            const openModal = document.querySelector('.js-modal[style*="display: block"]');
            if (!openModal) return;
            const focusables = getFocusableElements(openModal);
            if (!focusables.length) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Server message stack dismiss behavior: use delegated handlers so dynamically-inserted
    // messages are handled. Clicking the card (except on controls) dismisses it.
    document.addEventListener('click', (e) => {
        try {
            const card = e.target.closest('[data-dismiss-on-click]');
            if (!card) return;
            // ignore clicks on interactive controls inside the card
            if (e.target.closest('button, a, input, textarea, select')) return;
            card.remove();
        } catch (err) { /* ignore */ }
    });

    // dismiss buttons inside messages
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-dismiss]');
        if (!btn) return;
        e.preventDefault();
        const card = btn.closest('[data-dismiss-on-click]');
        if (card) card.remove();
    });

    // Mobile rail toggles (left/right/bottom)
    const railLeftToggle = document.querySelector('[data-toggle-left-rail]');
    const railRightToggle = document.querySelector('[data-toggle-right-rail]');
    const railFooterToggle = document.querySelector('[data-toggle-footer-strip]');
    const railLeft = document.querySelector('.dashboard-rail-icon-strip[data-rail="left"]');
    const railRight = document.querySelector('.dashboard-rail-icon-strip[data-rail="right"]');
    const railBottom = document.querySelector('.dashboard-rail-icon-strip[data-rail="bottom"]');

    function toggleRail(rail) {
        if (!rail) return;
        rail.classList.toggle('d-none');
    }

    if (railLeftToggle && railLeft) {
        railLeftToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleRail(railLeft); });
    }
    if (railRightToggle && railRight) {
        railRightToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleRail(railRight); });
    }
    if (railFooterToggle && railBottom) {
        railFooterToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleRail(railBottom); });
    }

    // close rail buttons
    document.querySelectorAll('[data-close-rail]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const which = btn.dataset.closeRail;
            if (!which) return;
            const rail = document.querySelector(`.dashboard-rail-icon-strip[data-rail="${which}"]`);
            if (rail) rail.classList.add('d-none');
        });
    });

    // click outside to close rails
    document.addEventListener('click', (e) => {
        if (railLeft && !railLeft.classList.contains('d-none')) {
            if (!railLeft.contains(e.target) && !(railLeftToggle && railLeftToggle.contains(e.target))) railLeft.classList.add('d-none');
        }
        if (railRight && !railRight.classList.contains('d-none')) {
            if (!railRight.contains(e.target) && !(railRightToggle && railRightToggle.contains(e.target))) railRight.classList.add('d-none');
        }
        if (railBottom && !railBottom.classList.contains('d-none')) {
            if (!railBottom.contains(e.target) && !(railFooterToggle && railFooterToggle.contains(e.target))) railBottom.classList.add('d-none');
        }
    });

    // Simple dropdowns (replace Alpine dropdown component)
    function closeAllDropdowns() {
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.classList.remove('show');
            menu.style.display = 'none';
            const parent = menu.closest('[data-dropdown]');
            if (parent) {
                const t = parent.querySelector('[data-dropdown-toggle]');
                if (t) t.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = menu.classList.contains('show');
            closeAllDropdowns();
            if (!isOpen) {
                menu.classList.add('show');
                menu.style.display = 'block';
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', () => closeAllDropdowns());

    // Equipment board popovers (replace Alpine equipmentBoard())
    function closeAllEquipmentInfos() {
        document.querySelectorAll('.equipment-board-slot-card.equipment-board-slot-card-open').forEach((card) => {
            card.classList.remove('equipment-board-slot-card-open');
            const btn = card.querySelector('[data-toggle-info]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('[data-toggle-info]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const slot = btn.dataset.toggleInfo;
            if (!slot) return;
            const card = document.querySelector(`.equipment-board-slot-card[data-slot="${slot}"]`);
            if (!card) return;
            const isOpen = card.classList.contains('equipment-board-slot-card-open');
            closeAllEquipmentInfos();
            if (!isOpen) {
                card.classList.add('equipment-board-slot-card-open');
                btn.setAttribute('aria-expanded', 'true');

                // Portal the popover to body to avoid being clipped by header or other stacking contexts
                const pop = card.querySelector('.equipment-board-slot-popover');
                if (pop) {
                    // save original container for restoring
                    if (!pop.__portal) pop.__portal = { parent: pop.parentNode, next: pop.nextSibling };
                    document.body.appendChild(pop);
                    pop.classList.add('equipment-board-slot-popover--portaled');

                    // position the popover next to the card
                    const rect = card.getBoundingClientRect();
                    const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--dashboard-header-height')) || 0;
                    const side = pop.classList.contains('equipment-board-slot-popover-left') ? 'left' : 'right';
                    // Ensure popover appears below the header even when page is scrolled
                    const desiredTop = rect.top + window.scrollY;
                    const minTop = window.scrollY + headerHeight + 8;
                    let top = Math.max(desiredTop, minTop);
                    let left;
                    // ensure the popover is visible to the side of the card
                    if (side === 'right') {
                        left = rect.right + window.scrollX + 8;
                    } else {
                        left = rect.left + window.scrollX - (pop.offsetWidth || 280) - 8;
                    }
                    pop.style.position = 'absolute';
                    pop.style.top = `${top}px`;
                    pop.style.left = `${left}px`;
                    pop.style.zIndex = '10050';
                    pop.style.display = 'block';
                }
            }
        });
    });

    // clicking outside equipment board closes popovers
    document.addEventListener('click', (e) => {
        if (e.target.closest('.equipment-board')) return;
        // restore any portaled popovers
        document.querySelectorAll('.equipment-board-slot-popover--portaled').forEach((pop) => {
            if (pop.__portal) {
                pop.style.position = '';
                pop.style.top = '';
                pop.style.left = '';
                pop.style.zIndex = '';
                pop.style.display = '';
                const originalParent = pop.__portal.parent;
                const next = pop.__portal.next;
                if (originalParent) {
                    if (next && next.parentNode === originalParent) originalParent.insertBefore(pop, next);
                    else originalParent.appendChild(pop);
                }
                delete pop.__portal;
            }
            pop.classList.remove('equipment-board-slot-popover--portaled');
        });
        closeAllEquipmentInfos();
    });

    // ESC closes equipment info as well
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllEquipmentInfos();
        }
    });

    // auto-dismiss elements (data-auto-dismiss in ms)
    document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
        const delay = parseInt(el.dataset.autoDismiss, 10) || 2000;
        setTimeout(() => {
            try { el.style.display = 'none'; } catch (e) { /* ignore */ }
        }, delay);
    });

    // Action lock (cooldown) replacement for Alpine actionLock
    document.querySelectorAll('[data-action-lock-seconds-remaining]').forEach((container) => {
        const secondsAttr = parseInt(container.dataset.actionLockSecondsRemaining, 10) || 0;
        const baseTitle = container.dataset.actionLockTitle || document.title || '';
        const syncDocumentTitle = container.dataset.actionLockSyncDocumentTitle === '1';
        const labels = {
            ready: container.dataset.actionLockReadyLabel || 'Ready',
            cooldownActive: container.dataset.actionLockCooldownLabel || 'Cooldown Active',
            actionLockOpen: container.dataset.actionLockOpenLabel || 'Action Lock Open',
        };

        const target = container.querySelector('[data-action-lock-countdown-target]');
        let secondsRemaining = secondsAttr;

        function fmtCountdown(sec) {
            if (!sec || sec <= 0) return labels.ready;
            const mm = Math.floor(sec / 60).toString().padStart(2, '0');
            const ss = (sec % 60).toString().padStart(2, '0');
            return `${mm}:${ss}`;
        }

        function updateActionLock() {
            if (target) target.textContent = fmtCountdown(secondsRemaining);
            if (syncDocumentTitle) {
                try { document.title = secondsRemaining > 0 ? `${baseTitle} · ${fmtCountdown(secondsRemaining)}` : baseTitle; } catch (e) { /* ignore */ }
            }
            try { window.dispatchEvent(new CustomEvent('lumiis:action-lock-update', { detail: { secondsRemaining } })); } catch (e) { /* ignore */ }
        }

        updateActionLock();
        const interval = setInterval(() => {
            if (secondsRemaining > 0) {
                secondsRemaining -= 1;
                updateActionLock();
            } else {
                clearInterval(interval);
            }
        }, 1000);
    });

    // Resource gauges (replacement for Alpine resourceGauge)
    document.querySelectorAll('[data-resource-gauge]').forEach((gauge) => {
        const key = gauge.dataset.resourceKey;
        let current = parseInt(gauge.dataset.current, 10) || 0;
        const maximum = parseInt(gauge.dataset.maximum, 10) || 0;
        let nextTick = gauge.dataset.nextTickSeconds === 'null' ? null : parseInt(gauge.dataset.nextTickSeconds, 10);
        const intervalSeconds = parseInt(gauge.dataset.intervalSeconds, 10) || 60;
        const pointsPerTick = parseInt(gauge.dataset.pointsPerTick, 10) || 1;
        const labels = {
            fullShort: gauge.dataset.labelsFullShort || 'Full',
            minutesShort: gauge.dataset.labelsMinutesShort || 'min',
        };

        const valueTarget = gauge.querySelector('[data-resource-value-target]');
        const fillEl = gauge.querySelector('[data-resource-fill]');
        const noteTarget = gauge.querySelector('[data-resource-nexttick-target]');

        function updateGaugeUI() {
            if (valueTarget) valueTarget.textContent = `${current}/${maximum}`;
            if (fillEl && maximum > 0) fillEl.style.width = `${Math.round((current / maximum) * 100)}%`;
            if (noteTarget) {
                if (nextTick === null) noteTarget.textContent = labels.fullShort;
                else noteTarget.textContent = `${Math.ceil(nextTick / 60)} ${labels.minutesShort}`;
            }
            if (gauge.classList && typeof gauge.classList.toggle === 'function') {
                gauge.classList.toggle('is-refilling', nextTick !== null && current < maximum);
            }
            try { window.dispatchEvent(new CustomEvent('lumiis:resource-update', { detail: { resourceKey: key, current, maximum } })); } catch (e) { /* ignore */ }
        }

        updateGaugeUI();

        if (nextTick !== null) {
            setInterval(() => {
                if (nextTick === null) return;
                nextTick -= 1;
                if (nextTick <= 0) {
                    current = Math.min(maximum, current + pointsPerTick);
                    nextTick = intervalSeconds;
                }
                updateGaugeUI();
            }, 1000);
        }
    });

    // Inventory workspace behaviours (category switching, pagination, info toggles, quantity steppers)
    document.querySelectorAll('[data-inventory-list]').forEach((workspace) => {
        const perPage = parseInt(workspace.dataset.perPage, 10) || 10;
        workspace.dataset.currentPage = workspace.dataset.currentPage || '1';

        // category tabs
        workspace.querySelectorAll('[data-inventory-tab]').forEach((tab) => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const cat = tab.dataset.inventoryTab;
                if (!cat) return;

                workspace.querySelectorAll('[data-inventory-tab]').forEach((t) => t.classList.remove('inventory-subinventory-tab-active'));
                tab.classList.add('inventory-subinventory-tab-active');

                workspace.querySelectorAll('[data-inventory-section]').forEach((sec) => {
                    if (sec.dataset.inventorySection === cat) sec.classList.remove('d-none');
                    else sec.classList.add('d-none');
                });

                renderInventoryPage(workspace, 1);
            });
        });

        // info toggles
        workspace.querySelectorAll('[data-toggle-inventory-info]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.dataset.toggleInventoryInfo;
                if (!id) return;
                const row = workspace.querySelector(`.inventory-list-row[data-inventory-item-id="${id}"]`);
                if (!row) return;
                const isOpen = row.classList.contains('inventory-list-row-open');
                const willOpen = !isOpen;
                row.classList.toggle('inventory-list-row-open', willOpen);
                btn.setAttribute('aria-expanded', String(willOpen));

                // when opening details, ensure the row is visible and not hidden under the fixed header
                if (willOpen) {
                    try {
                        const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--dashboard-header-height')) || 0;

                        // find nearest scrollable ancestor to scroll within overlays (falls back to window)
                        function getScrollParent(node) {
                            let el = node.parentElement;
                            while (el && el !== document.body) {
                                const style = getComputedStyle(el);
                                const overflowY = style.overflowY || '';
                                if (overflowY === 'auto' || overflowY === 'scroll') return el;
                                el = el.parentElement;
                            }
                            return document.scrollingElement || document.documentElement;
                        }

                        const scrollParent = getScrollParent(row);
                        const rowRect = row.getBoundingClientRect();
                        const parentRect = scrollParent.getBoundingClientRect ? scrollParent.getBoundingClientRect() : { top: 0 };
                        const offsetTop = rowRect.top - parentRect.top;
                        const desired = (scrollParent.scrollTop || 0) + offsetTop - headerHeight - 12;
                        // clamp
                        const finalTop = Math.max(0, Math.round(desired));
                        try { scrollParent.scrollTo({ top: finalTop, behavior: 'smooth' }); } catch (e) { scrollParent.scrollTop = finalTop; }
                    } catch (e) { /* ignore scroll errors */ }
                }
            });
        });

        // quantity steppers
        workspace.querySelectorAll('[data-quantity-stepper]').forEach((stepper) => {
            const input = stepper.querySelector('input[type="number"]');
            if (!input) return;
            const min = parseInt(stepper.dataset.min, 10) || parseInt(input.min, 10) || 1;
            const max = parseInt(stepper.dataset.max, 10) || parseInt(input.max, 10) || 1;
            const dec = stepper.querySelector('.stepper-decrement');
            const inc = stepper.querySelector('.stepper-increment');
            const mx = stepper.querySelector('.stepper-max');

            function updateButtons() {
                if (dec) dec.disabled = parseInt(input.value, 10) <= min;
                if (inc) inc.disabled = parseInt(input.value, 10) >= max;
                if (mx) mx.disabled = parseInt(input.value, 10) >= max;
            }

            if (dec) dec.addEventListener('click', (e) => { e.preventDefault(); input.value = Math.max(min, (parseInt(input.value, 10) || min) - 1); input.dispatchEvent(new Event('input', { bubbles: true })); updateButtons(); });
            if (inc) inc.addEventListener('click', (e) => { e.preventDefault(); input.value = Math.min(max, (parseInt(input.value, 10) || min) + 1); input.dispatchEvent(new Event('input', { bubbles: true })); updateButtons(); });
            if (mx) mx.addEventListener('click', (e) => { e.preventDefault(); input.value = max; input.dispatchEvent(new Event('input', { bubbles: true })); updateButtons(); });

            input.addEventListener('input', () => {
                let v = parseInt(input.value, 10);
                if (Number.isNaN(v)) v = min;
                v = Math.max(min, Math.min(max, v));
                input.value = v;
                updateButtons();
            });

            updateButtons();
        });

        // pagination controls
        const prevBtn = workspace.querySelector('[data-inventory-prev]');
        const nextBtn = workspace.querySelector('[data-inventory-next]');
        const currentPageEl = workspace.querySelector('[data-inventory-current]');
        const startEl = workspace.querySelector('[data-inventory-start]');
        const endEl = workspace.querySelector('[data-inventory-end]');
        const totalEl = workspace.querySelector('[data-inventory-total]');
        const pagesEl = workspace.querySelector('[data-inventory-pages]');

        function renderInventoryPage(ws, page) {
            const section = ws.querySelector('[data-inventory-section]:not(.d-none)');
            if (!section) return;
            const rows = Array.from(section.querySelectorAll('.inventory-list-row'));
            const per = parseInt(ws.dataset.perPage, 10) || 10;
            const total = rows.length;
            const pages = Math.max(1, Math.ceil(total / per));
            const p = Math.max(1, Math.min(page, pages));
            const startIdx = (p - 1) * per;
            const endIdx = Math.min(total, startIdx + per);
            rows.forEach((r, idx) => { r.style.display = (idx >= startIdx && idx < endIdx) ? '' : 'none'; });

            if (startEl) startEl.textContent = total === 0 ? '0' : String(startIdx + 1);
            if (endEl) endEl.textContent = String(endIdx);
            if (totalEl) totalEl.textContent = String(total);
            if (currentPageEl) currentPageEl.textContent = String(p);
            if (pagesEl) pagesEl.textContent = String(pages);

            if (prevBtn) prevBtn.disabled = p <= 1;
            if (nextBtn) nextBtn.disabled = p >= pages;

            ws.dataset.currentPage = String(p);
        }

        if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); renderInventoryPage(workspace, (parseInt(workspace.dataset.currentPage || '1', 10) || 1) - 1); });
        if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); renderInventoryPage(workspace, (parseInt(workspace.dataset.currentPage || '1', 10) || 1) + 1); });

        // initial render
        renderInventoryPage(workspace, 1);
    });

    // Manual encounter preview (replacement for Alpine.manualEncounterPreview)
    document.querySelectorAll('[data-manual-preview]').forEach((container) => {
        const jsonChar = container.dataset.manualCharacter || '{}';
        const jsonResources = container.dataset.manualResources || '{}';
        const jsonOptions = container.dataset.manualOptions || '{}';
        const jsonLabels = container.dataset.manualLabels || '{}';

        let character = {};
        let resources = { health: 0, sp: 0, stamina: 0 };
        let options = {};
        let labels = {};

        try { character = JSON.parse(jsonChar); } catch (e) { character = {}; }
        try { resources = JSON.parse(jsonResources); } catch (e) { /* ignore */ }
        try { options = JSON.parse(jsonOptions); } catch (e) { /* ignore */ }
        try { labels = JSON.parse(jsonLabels); } catch (e) { /* ignore */ }

        labels = Object.assign({ estimatedSuccess: 'Est. success', safe: 'Safe', fair: 'Fair', risky: 'Risky', dangerous: 'Dangerous' }, labels || {});

        function previewKey(familyKey, difficultyKey) { return `${familyKey}.${difficultyKey}`; }
        function previewOptionByKey(k) { return options[k] ?? null; }

        function calculateSuccessRateByKey(previewKey) {
            const preview = previewOptionByKey(previewKey);
            if (!preview) return 0;
            const currentHealth = Math.max(1, Number(resources.health ?? 0));
            if (currentHealth <= 12) return 5;
            const currentSp = Math.max(0, Number(resources.sp ?? 0));
            const packSize = Math.max(1, Number(preview.pack_size ?? 1));
            const pressureEnemyCount = Math.max(1, Number(preview.pressure_enemy_count ?? packSize));
            const maxHealth = Math.max(1, Number(character.maxHealth ?? 1));
            const effectiveAttack = Math.max(1, Number(character.effectiveAttack ?? 1));
            const effectiveDefense = Math.max(0, Number(character.effectiveDefense ?? 0));
            const effectiveSpeed = Math.max(1, Number(character.effectiveSpeed ?? 1));
            const effectiveMagicAttack = Math.max(0, Number(character.effectiveMagicAttack ?? 0));
            const averageDamageBonus = Number(character.averageDamageBonus ?? 0);
            const criticalChance = Number(character.criticalChance ?? 0);
            const skillDamageBonus = Number(character.skillDamageBonus ?? 0);
            const evadeChance = Number(character.evadeChance ?? 0);
            const skillCount = Math.max(0, Number(character.skillCount ?? 0));
            const healthRatio = Math.min(1, currentHealth / maxHealth);

            const enemyMinAttack = Math.max(1, Number(preview.enemy_min_attack ?? 1));
            const enemyMaxAttack = Math.max(enemyMinAttack + 1, Number(preview.enemy_max_attack ?? (enemyMinAttack + 1)));
            const enemyDefense = Math.max(0, Number(preview.enemy_defense ?? 0));
            const enemyAverageAttack = Math.floor((enemyMinAttack + enemyMaxAttack) / 2);
            const enemyTotalHealth = Math.max(1, Number(preview.total_enemy_health ?? 1));
            const monsterLevel = Math.max(1, Number(preview.monster_level ?? 1));
            const threatScore = Math.max(1, Number(preview.threat_score ?? 1));
            const controlBonus = Number(preview.control_bonus ?? 0);

            const playerBaseDamage = Math.max(
                1,
                Math.round(
                    (effectiveAttack + Math.trunc(effectiveMagicAttack / 4) + Math.max(0, Math.trunc(currentSp / 20)))
                    * (1 + Math.min(0.32, effectiveSpeed / 100))
                    * (1 + (averageDamageBonus / 100))
                    * (1 + (criticalChance / 280))
                    * (1 + (skillDamageBonus / 260))
                ) - enemyDefense,
            );
            const playerRoundsToWin = enemyTotalHealth / Math.max(1, playerBaseDamage);

            const enemyDamageReduction = Math.max(0, Math.trunc(effectiveDefense / 4));
            const enemyMinimumDamage = Math.max(1 + Math.trunc(Math.max(1, monsterLevel) / 10) + (pressureEnemyCount > 1 ? 1 : 0), 1);
            const enemyHitDamage = Math.max(enemyMinimumDamage, Math.ceil(enemyAverageAttack - enemyDamageReduction));
            const enemyHitChance = Math.max(0.58, 1 - ((evadeChance / 100) * 0.65));
            const enemyRoundDamage = Math.max(1, Math.ceil(enemyHitDamage * pressureEnemyCount * enemyHitChance));
            const survivalRounds = currentHealth / enemyRoundDamage;

            const combatRating = Math.max(1, Number(character.combatRating ?? 1));
            const ratingRatio = combatRating / Math.max(1, threatScore);
            const roundAdvantage = survivalRounds - playerRoundsToWin;
            const ratingContribution = Math.max(-24, Math.min(28, (ratingRatio - 1) * 34));
            const roundContribution = Math.max(-34, Math.min(28, roundAdvantage * 5.5));

            let chance = 50
                + ratingContribution
                + roundContribution
                + ((healthRatio - 0.72) * 34)
                + Math.min(8, skillCount * 2)
                + Math.min(5, Math.trunc(currentSp / 18));

            chance += controlBonus;

            if (pressureEnemyCount >= 3 && healthRatio < 0.55) {
                chance -= 6;
            }

            return Math.max(5, Math.min(98, Math.round(chance)));
        }

        function riskLabelForRate(successRate) {
            if (successRate >= 85) return labels.safe || 'Safe';
            if (successRate >= 65) return labels.fair || 'Fair';
            if (successRate >= 45) return labels.risky || 'Risky';
            return labels.dangerous || 'Dangerous';
        }

        function successRateLabelByKey(previewKey) {
            const successRate = calculateSuccessRateByKey(previewKey);
            return `${labels.estimatedSuccess || 'Est. success'} ${successRate}% · ${riskLabelForRate(successRate)}`;
        }

        function successToneClassByKey(previewKey) {
            const successRate = calculateSuccessRateByKey(previewKey);
            if (successRate >= 85) return 'text-emerald-50';
            if (successRate >= 65) return 'text-sky-50';
            if (successRate >= 45) return 'text-amber-50';
            return 'text-rose-50';
        }

        function encounterSummaryByKey(previewKey) { return String(previewOptionByKey(previewKey)?.encounter_summary ?? ''); }

        function updatePreviews() {
            container.querySelectorAll('[data-preview-key]').forEach((el) => {
                const key = el.dataset.previewKey;
                const kind = el.dataset.previewKind || 'rate';
                if (!key) return;

                if (kind === 'rate') {
                    try { el.textContent = successRateLabelByKey(key); } catch (e) { /* ignore */ }
                    // apply tone class
                    el.classList.remove('text-emerald-50', 'text-sky-50', 'text-amber-50', 'text-rose-50');
                    try { el.classList.add(successToneClassByKey(key)); } catch (e) { /* ignore */ }
                } else if (kind === 'summary') {
                    try { el.textContent = encounterSummaryByKey(key); } catch (e) { /* ignore */ }
                }
            });
        }

        const resourceHandler = (event) => {
            const rk = event?.detail?.resourceKey;
            if (!rk || !(rk in resources)) return;
            resources[rk] = Number(event.detail.current ?? resources[rk] ?? 0);
            updatePreviews();
        };

        window.addEventListener('lumiis:resource-update', resourceHandler);

        const actionLockHandler = (event) => {
            const sec = Number(event?.detail?.secondsRemaining ?? 0);
            const disabled = sec > 0;
            container.querySelectorAll('[data-manual-disable-on-lock]').forEach((btn) => {
                try { btn.disabled = disabled || btn.hasAttribute('data-server-disabled'); } catch (e) { /* ignore */ }
            });
        };

        window.addEventListener('lumiis:action-lock-update', actionLockHandler);

        // mark server-disabled controls so we can merge server and client state
        container.querySelectorAll('[disabled]').forEach((btn) => btn.setAttribute('data-server-disabled', '1'));

        // initial render
        updatePreviews();
    });

    // DashboardShell compatibility initializer: parse `data-dashboard-shell` payloads and
    // evaluate simple `x-show` expressions (===, !, ||, &&, optional chaining) so existing
    // templates using `x-show` continue to work while we migrate away from Alpine.
    function getStateValue(state, path) {
        if (!path) return undefined;
        // support optional chaining syntax by replacing '?.' with '.'
        const parts = String(path).replace(/\?\./g, '.').split('.');
        let cur = state;
        for (const p of parts) {
            if (cur == null) return undefined;
            cur = cur[p];
        }
        return cur;
    }

    function evaluateAtomic(cond, state) {
        if (!cond) return false;
        cond = cond.trim();
        let invert = false;
        if (cond.startsWith('!')) { invert = true; cond = cond.slice(1).trim(); }

        // string equality: var === 'value'
        const mStr = cond.match(/^([A-Za-z_][\w\.]*)\s*===\s*'([^']*)'$/);
        if (mStr) {
            const val = getStateValue(state, mStr[1]);
            const res = String(val) === mStr[2];
            return invert ? !res : res;
        }

        // numeric equality: var === 123
        const mNum = cond.match(/^([A-Za-z_][\w\.]*)\s*===\s*(\d+)$/);
        if (mNum) {
            const val = getStateValue(state, mNum[1]);
            const res = Number(val) === Number(mNum[2]);
            return invert ? !res : res;
        }

        // simple variable truthiness
        const mVar = cond.match(/^([A-Za-z_][\w\.]*)$/);
        if (mVar) {
            const val = getStateValue(state, mVar[1]);
            const res = Boolean(val);
            return invert ? !res : res;
        }

        return false;
    }

    function evaluateExpr(expr, state) {
        if (!expr) return false;
        // support OR (||) and AND (&&)
        const orParts = String(expr).split('||').map((s) => s.trim());
        for (const orPart of orParts) {
            const andParts = orPart.split('&&').map((s) => s.trim());
            let andRes = true;
            for (const a of andParts) {
                if (!evaluateAtomic(a, state)) { andRes = false; break; }
            }
            if (andRes) return true;
        }
        return false;
    }

    function initDashboardShell(root, payload) {
        const state = Object.assign({}, payload || {});
        const xShowEls = Array.from(root.querySelectorAll('[x-show]'));
        const xTextEls = Array.from(root.querySelectorAll('[x-text]'));

        // remove x-cloak so elements are controlled by our evaluator
        Array.from(root.querySelectorAll('[x-cloak]')).forEach((el) => el.removeAttribute('x-cloak'));

        function updateAll() {
            xShowEls.forEach((child) => {
                const expr = child.getAttribute('x-show') || '';
                try {
                    const visible = evaluateExpr(expr, state);
                    child.classList.toggle('d-none', !visible);
                } catch (e) { /* ignore evaluation errors */ }
            });

            xTextEls.forEach((el) => {
                const expr = (el.getAttribute('x-text') || '').trim();
                if (!expr) return;
                try {
                    if (expr.endsWith('()')) {
                        const fn = expr.slice(0, -2);
                        const maybeFn = state[fn];
                        if (typeof maybeFn === 'function') {
                            try { el.textContent = String(maybeFn.call(state)); } catch (e) { /* ignore */ }
                        } else {
                            const val = getStateValue(state, fn);
                            if (val !== undefined) el.textContent = String(val);
                        }
                    } else {
                        const val = getStateValue(state, expr);
                        if (val !== undefined) el.textContent = String(val);
                    }
                } catch (e) { /* ignore */ }
            });
        }

        // initial sync
        updateAll();

        // map internal open-panel controls to state changes
        root.querySelectorAll('[data-open-panel]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const panel = btn.dataset.openPanel;
                if (!panel) return;
                state.activePanel = panel;
                updateAll();
                // hide rails consistent with existing behavior
                document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.add('d-none'));
            });
        });

        // dashboard actions mapping for legacy Alpine callbacks (minimal replacements)
        const actionHandlers = {
            dismissActiveBattleCloseModal: () => { state.activeBattleCloseModalOpen = false; updateAll(); },
            proceedActiveBattleCloseModal: () => { state.activeBattleCloseModalOpen = false; state.activePanel = null; updateAll(); },
        };

        // delegate data-dashboard-action attributes to actionHandlers
        root.addEventListener('click', (e) => {
            try {
                const actionEl = e.target.closest('[data-dashboard-action]');
                if (!actionEl) return;
                const name = actionEl.getAttribute('data-dashboard-action');
                if (!name) return;
                const h = actionHandlers[name];
                if (typeof h === 'function') { e.preventDefault(); h(); }
            } catch (err) { /* ignore */ }
        });

        // listen for global dashboard-panel-open events
        window.addEventListener('dashboard-panel-open', (e) => {
            const panel = (e.detail && (e.detail.panel || e.detail)) || null;
            if (!panel) return;
            state.activePanel = panel;
            updateAll();
            document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.add('d-none'));
        });

        // add convenient helper methods expected by templates
        state.closePanel = function () {
            state.activePanel = null;
            try { document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.remove('d-none')); } catch (e) { /* ignore */ }
            updateAll();
        };

        state.openChatTab = function (tab) {
            try { state.chatTab = tab; state.activePanel = 'chat'; document.querySelectorAll('.dashboard-rail-icon-strip').forEach((r) => r.classList.add('d-none')); } catch (e) { /* ignore */ }
            updateAll();
        };

        state.confirmActiveBattleClose = function (event) {
            try {
                if (state.hasActiveBattle) {
                    try { event && event.preventDefault(); } catch (e) { /* ignore */ }
                    state.activeBattleCloseModalOpen = true;
                    updateAll();
                    return;
                }
            } catch (e) { /* ignore */ }
            // no active battle — allow default navigation
        };

        // Action lock helpers used by dashboard templates (formatted countdowns, flags)
        state.isActionLocked = function () {
            try {
                const secs = Number(state.actionLockSeconds || 0);
                const restingSecs = (state.resting && Number(state.resting.remaining_seconds || 0)) || 0;
                return secs > 0 || restingSecs > 0;
            } catch (e) { return false; }
        };

        state.formattedActionLockCountdown = function () {
            try {
                const sec = Number(state.actionLockSeconds || 0);
                if (!sec || sec <= 0) return (state.actionLockReadyAtLabel && typeof state.actionLockReadyAtLabel === 'function') ? state.actionLockReadyAtLabel() : 'Ready';
                const mm = Math.floor(sec / 60).toString().padStart(2, '0');
                const ss = (sec % 60).toString().padStart(2, '0');
                return `${mm}:${ss}`;
            } catch (e) { return '' + (state.actionLockSeconds || '0'); }
        };

        state.actionLockReadyAtLabel = function () {
            return (state.actionLockSeconds && state.actionLockSeconds > 0) ? (state.actionLockCooldownLabel || 'Cooldown Active') : (state.actionLockReadyLabel || 'Ready');
        };

        state.isResting = function () {
            try { return !!(state.resting && Number(state.resting.remaining_seconds || 0) > 0); } catch (e) { return false; }
        };

        // Keep dashboard state in sync with global action-lock updates and
        // disable any controls that opted into `data-manual-disable-on-lock`.
        window.addEventListener('lumiis:action-lock-update', (e) => {
            try {
                const sec = Number(e?.detail?.secondsRemaining ?? 0);
                state.actionLockSeconds = sec;
                document.querySelectorAll('[data-manual-disable-on-lock]').forEach((btn) => {
                    try { btn.disabled = sec > 0 || btn.hasAttribute('data-server-disabled'); } catch (err) { /* ignore */ }
                });
                updateAll();
            } catch (err) { /* ignore */ }
        });
        // wire lightweight `@` and `x-on:` handlers across the dashboard subtree so
        // template inline handlers like `@click="chatTab = 'global'"` and
        // `@click="confirmActiveBattleClose($event)"` work while migrating.
        try {
            const allEls = [root].concat(Array.from(root.querySelectorAll('*')));
            allEls.forEach((el2) => {
                Array.from(el2.attributes || []).forEach((attr) => {
                    const name = String(attr.name || '');
                    if (!name.startsWith('@') && !name.startsWith('x-on:')) return;
                    const spec = name.replace(/^@|^x-on:/, '');
                    const parts = spec.split('.');
                    const eventName = parts[0];
                    const modifiers = parts.slice(1);
                    const targetIsWindow = modifiers.includes('window');
                    const onlyKey = modifiers.find((m) => m !== 'window' && m !== 'prevent' && m !== 'stop');
                    const code = (attr.value || '').trim();
                    const attachTo = targetIsWindow ? window : el2;
                    const handler = (e) => {
                        try {
                            if (eventName === 'keydown' && onlyKey && String(onlyKey).toLowerCase() === 'escape' && e.key !== 'Escape') return;
                            if (modifiers.includes('prevent')) e.preventDefault();
                            if (modifiers.includes('stop')) e.stopPropagation();
                            const fn = new Function('state', 'event', 'var $event=event; var $el=(event&&event.currentTarget)?event.currentTarget:null; with(state){' + code + '}');
                            fn(state, e);
                            updateAll();
                        } catch (err) { /* ignore handler errors */ }
                    };
                    try { attachTo.addEventListener(eventName, handler); } catch (e) { /* ignore invalid events */ }
                });
            });
        } catch (e) { /* ignore event wiring errors */ }

        // expose simple API for interactive debugging or future use
        root.__dashboardShell = {
            getState: () => JSON.parse(JSON.stringify(state)),
            setState: (s) => { Object.assign(state, s); updateAll(); },
        };
    }

    document.querySelectorAll('[data-dashboard-shell]').forEach((el) => {
        try {
            const payload = JSON.parse(el.getAttribute('data-dashboard-shell') || '{}');
            initDashboardShell(el, payload);
        } catch (e) { /* ignore invalid JSON */ }
    });

    // General `x-data` compatibility initializer for small components.
    // Parses simple `x-data` object literals, evaluates common `x-show` expressions
    // and handles basic `x-init` setTimeout patterns used in small snippets.
    document.querySelectorAll('[x-data]').forEach((rootEl) => {
        try {
            let dataStr = rootEl.getAttribute('x-data') || '{}';
            let state = {};
            try {
                // Detect function-call forms like `moduleName({...})` and extract function name + inner object
                const callMatch = String(dataStr).match(/^([A-Za-z_$][\w$]*)\s*\(([\s\S]*)\)\s*$/);
                if (callMatch) {
                    const fnName = callMatch[1];
                    const inner = callMatch[2].trim();
                    try {
                        state = (new Function('return ' + inner))();
                    } catch (innerErr) {
                        // fallback to JSON-ish conversion for object-like payloads
                        try {
                            const jsonish = inner.replace(/([a-zA-Z0-9_]+)\s*:/g, '"$1":').replace(/'/g, '"');
                            state = JSON.parse(jsonish);
                        } catch (e2) { state = {}; }
                    }
                    // remember original function name to allow specialized augmentations
                    try { if (state && typeof state === 'object') state.__x_fn = fnName; } catch (e2) { /* ignore */ }
                } else {
                    // Try to evaluate JS object literal safely
                    state = (new Function('return ' + dataStr))();
                }
            } catch (e) {
                // fallback: attempt simple JSON-ish conversion
                try {
                    const jsonish = dataStr.replace(/([a-zA-Z0-9_]+)\s*:/g, '"$1":').replace(/'/g, '"');
                    state = JSON.parse(jsonish);
                } catch (e2) {
                    state = {};
                }
            }

            const xShowEls = Array.from(rootEl.querySelectorAll('[x-show]'));
            // remove x-cloak on this subtree
            Array.from(rootEl.querySelectorAll('[x-cloak]')).forEach((el) => el.removeAttribute('x-cloak'));

            function updateLocal() {
                xShowEls.forEach((child) => {
                    const expr = child.getAttribute('x-show') || '';
                    try {
                        const visible = evaluateExpr(expr, state);
                        child.classList.toggle('d-none', !visible);
                    } catch (e) { /* ignore */ }
                });

                // support simple x-text usage: either `var` or `func()` where func is on state
                Array.from(rootEl.querySelectorAll('[x-text]')).forEach((el) => {
                    const expr = (el.getAttribute('x-text') || '').trim();
                    if (!expr) return;
                    try {
                        if (expr.endsWith('()')) {
                            const fn = expr.slice(0, -2);
                            if (typeof state[fn] === 'function') {
                                try { el.textContent = String(state[fn].call(state)); } catch (e) { /* ignore */ }
                            }
                        } else {
                            const val = getStateValue(state, expr);
                            if (val !== undefined) el.textContent = String(val);
                        }
                    } catch (e) { /* ignore */ }
                });
            }

            // specialized augmentations for known component payloads
            try {
                const fnName = state && state.__x_fn ? state.__x_fn : null;
                if (fnName === 'battleResolution' || fnName === 'battleReplay') {
                    // open state
                    state.isOpen = !!state.autoOpen;
                    state.open = function () { state.isOpen = true; try { updateLocal(); } catch (e) { } };
                    state.close = function () { state.isOpen = false; try { updateLocal(); } catch (e) { } };

                    // labels helpers
                    state.translatedEncounterType = function (key) {
                        return (state.labels && state.labels.encounterTypes && state.labels.encounterTypes[key]) || key || '';
                    };
                    state.translatedCategory = function (cat) {
                        return (state.labels && state.labels.categories && state.labels.categories[cat]) || cat || '';
                    };

                    state.healthPercent = function (cur, max) {
                        const m = Math.max(1, Number(max) || 1);
                        return Math.max(0, Math.min(100, Math.round((Number(cur) || 0) / m * 100)));
                    };

                    // normalize result/replay object
                    if (!state.result && state.replay) state.result = state.replay;
                    state.enemies = (state.result && state.result.enemies) || (state.enemies || []);

                    // player current health fallback
                    try {
                        state.playerCurrentHealth = (state.result && ((state.result.player && (state.result.player.current_health ?? null)) ?? null)) ?? (state.result && state.result.player && (state.result.player.max_health - (state.result.summary?.health_lost ?? 0))) ?? 0;
                    } catch (e) { state.playerCurrentHealth = 0; }

                    state.resultStatusLabel = function () {
                        const r = state.result || {};
                        if (r.victory) return (state.labels && state.labels.victory) || 'Victory';
                        if (r.retreat) return (state.labels && state.labels.retreat) || 'Retreat';
                        return (state.labels && state.labels.defeat) || 'Defeat';
                    };

                    state.outcomeClass = function () {
                        const r = state.result || {};
                        if (r.victory) return 'battle-outcome-success';
                        if (r.retreat) return 'battle-outcome-retreat';
                        return 'battle-outcome-failure';
                    };

                    state.resultOutcomeBadge = function () {
                        const r = state.result || {};
                        if (r.victory) return (state.labels && state.labels.victory) || 'Victory';
                        if (r.retreat) return (state.labels && state.labels.retreat) || 'Retreat';
                        return (state.labels && state.labels.defeat) || 'Defeat';
                    };

                    state.resultOutcomeTitle = function () {
                        const r = state.result || {};
                        if (r.victory) {
                            if (r.encounter_type === 'boss' && state.labels && state.labels.titles && state.labels.titles.bossSoulRises) return state.labels.titles.bossSoulRises;
                            if (state.labels && state.labels.titles && state.labels.titles.fieldIsYours) return state.labels.titles.fieldIsYours;
                        }
                        if (r.retreat && state.labels && state.labels.titles && state.labels.titles.drivenBack) return state.labels.titles.drivenBack;
                        return '';
                    };

                    state.enemyAttackLabel = function (enemy) {
                        if (!enemy) return '';
                        return enemy.attack ?? enemy.attack_label ?? '';
                    };
                }
            } catch (e) { /* ignore augment errors */ }

            // attach lightweight `@` and `x-on:` handlers to call state methods where present
            try {
                const allEls = [rootEl].concat(Array.from(rootEl.querySelectorAll('*')));
                allEls.forEach((el) => {
                    Array.from(el.attributes || []).forEach((attr) => {
                        const name = String(attr.name || '');
                        if (!name.startsWith('@') && !name.startsWith('x-on:')) return;
                        const spec = name.replace(/^@|^x-on:/, '');
                        const parts = spec.split('.');
                        const eventName = parts[0];
                        const modifiers = parts.slice(1);
                        const targetIsWindow = modifiers.includes('window');
                        const onlyKey = modifiers.find((m) => m !== 'window' && m !== 'prevent' && m !== 'stop');
                        const code = (attr.value || '').trim();
                        const fnMatch = code.match(/^([A-Za-z0-9_$]+)\s*\(/);
                        const fnName = fnMatch ? fnMatch[1] : null;

                        const attachTo = targetIsWindow ? window : el;
                        const handler = (e) => {
                            try {
                                if (eventName === 'keydown' && onlyKey && String(onlyKey).toLowerCase() === 'escape' && e.key !== 'Escape') return;
                                if (modifiers.includes('prevent')) e.preventDefault();
                                if (modifiers.includes('stop')) e.stopPropagation();
                                if (fnName && typeof state[fnName] === 'function') {
                                    state[fnName].call(state, e);
                                    try { updateLocal(); } catch (err) { /* ignore */ }
                                }
                            } catch (err) { /* ignore handler errors */ }
                        };

                        try { attachTo.addEventListener(eventName, handler); } catch (e) { /* ignore invalid events */ }
                    });
                });
            } catch (e) { /* ignore event wiring errors */ }

            // initial sync
            updateLocal();

            // handle x-init simple setTimeout pattern: setTimeout(() => show = false, 2000)
            const initAttr = rootEl.getAttribute('x-init') || rootEl.querySelector('[x-init]')?.getAttribute('x-init');
            if (initAttr) {
                const m = initAttr.match(/setTimeout\(\s*\(\s*\)\s*=>\s*([\w$]+)\s*=\s*([^,\)]+)\s*,\s*(\d+)\s*\)/);
                if (m) {
                    const varName = m[1];
                    const valRaw = m[2].trim();
                    const ms = parseInt(m[3], 10) || 0;
                    let val;
                    try { val = (new Function('return ' + valRaw))(); } catch (e) { val = valRaw.replace(/^['"]|['"]$/g, ''); }
                    setTimeout(() => { state[varName] = val; updateLocal(); }, ms);
                }
            }

            // expose API for tests or interactive tweaks
            rootEl.__xState = state;
            rootEl.__xUpdate = updateLocal;
        } catch (err) { /* ignore per-element failures */ }
    });

    // Admin row editor initializer: handles add / duplicate / remove / drag & reindex
    document.querySelectorAll('[data-admin-row-editor]').forEach((root) => {
        try {
            const cfg = JSON.parse(root.getAttribute('data-admin-row-editor') || '{}');
            const rowsContainer = root.querySelector('[data-admin-rows]');
            const template = root.querySelector('template[data-admin-row-template]');
            if (!rowsContainer) return;

            function buildDataNameTemplateForInput(input) {
                const name = input.getAttribute('name') || '';
                if (!name) return;
                if (name.includes('__INDEX__')) {
                    input.setAttribute('data-name-template', name);
                    return;
                }
                const replaced = name.replace(/\[\d+\]/, '[__INDEX__]');
                if (replaced !== name) input.setAttribute('data-name-template', replaced);
            }

            function reindexRows() {
                const rows = Array.from(rowsContainer.querySelectorAll('[data-admin-row]'));
                rows.forEach((rowEl, i) => {
                    const label = rowEl.querySelector('.admin-row-index');
                    if (label) label.textContent = String(i + 1);
                    rowEl.querySelectorAll('input, select, textarea').forEach((inp) => {
                        if (!inp.hasAttribute('data-name-template')) buildDataNameTemplateForInput(inp);
                        const tmpl = inp.getAttribute('data-name-template');
                        if (tmpl) {
                            inp.setAttribute('name', tmpl.replace(/__INDEX__/g, String(i)));
                        } else {
                            const name = inp.getAttribute('name') || '';
                            const newName = name.replace(/\[\d+\]/, `[${i}]`);
                            inp.setAttribute('name', newName);
                        }
                    });
                });
            }

            function createRowFromTemplate() {
                let rowNode = null;
                if (template) {
                    const html = template.innerHTML.trim();
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    rowNode = wrapper.firstElementChild;
                } else {
                    const sample = rowsContainer.querySelector('[data-admin-row]');
                    if (sample) rowNode = sample.cloneNode(true);
                    else return null;
                    rowNode.querySelectorAll('input, select, textarea').forEach((inp) => {
                        if (inp.tagName === 'INPUT') inp.value = '';
                        else if (inp.tagName === 'SELECT') inp.selectedIndex = 0;
                        else inp.value = '';
                    });
                }

                // apply default values from cfg.defaultRow
                if (cfg.defaultRow && rowNode) {
                    const def = cfg.defaultRow;
                    Object.keys(def).forEach((key) => {
                        const el = rowNode.querySelector(`[name*="[${key}]"]`);
                        if (!el) return;
                        if (el.tagName === 'SELECT') {
                            const v = def[key];
                            Array.from(el.options).forEach((opt) => { opt.selected = String(opt.value) === String(v); });
                        } else {
                            el.value = def[key] ?? '';
                        }
                    });
                }

                return rowNode;
            }

            const addBtn = root.querySelector('[data-admin-add-row]');
            if (addBtn) {
                addBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const newRow = createRowFromTemplate();
                    if (!newRow) return;
                    rowsContainer.appendChild(newRow);
                    reindexRows();
                });
            }

            rowsContainer.addEventListener('click', (e) => {
                const dup = e.target.closest('[data-admin-duplicate-row]');
                if (dup) {
                    e.preventDefault();
                    const rowEl = dup.closest('[data-admin-row]');
                    if (!rowEl) return;
                    const clone = rowEl.cloneNode(true);
                    rowsContainer.insertBefore(clone, rowEl.nextSibling);
                    reindexRows();
                    return;
                }

                const rem = e.target.closest('[data-admin-remove-row]');
                if (rem) {
                    e.preventDefault();
                    const rowEl = rem.closest('[data-admin-row]');
                    if (!rowEl) return;
                    rowEl.remove();
                    reindexRows();
                    return;
                }
            });

            // drag & drop
            let dragged = null;
            rowsContainer.addEventListener('dragstart', (e) => {
                const r = e.target.closest('[data-admin-row]');
                if (!r) return;
                dragged = r;
                r.classList.add('admin-row-card-dragging');
                try { e.dataTransfer.setData('text/plain', 'drag'); } catch (err) { }
            });
            rowsContainer.addEventListener('dragend', () => {
                if (dragged) dragged.classList.remove('admin-row-card-dragging');
                dragged = null;
            });
            rowsContainer.addEventListener('dragover', (e) => e.preventDefault());
            rowsContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                const target = e.target.closest('[data-admin-row]');
                if (!target || !dragged) return;
                if (target === dragged) return;
                const rect = target.getBoundingClientRect();
                const after = e.clientY > rect.top + rect.height / 2;
                if (after) target.parentNode.insertBefore(dragged, target.nextSibling);
                else target.parentNode.insertBefore(dragged, target);
                reindexRows();
            });

            // initial sync
            reindexRows();
        } catch (e) { /* ignore per-editor failures */ }
    });

    // Initialize data-battle-resolution and data-battle-replay payloads
    document.querySelectorAll('[data-battle-resolution],[data-battle-replay]').forEach((el) => {
        try {
            const attr = el.hasAttribute('data-battle-resolution') ? 'data-battle-resolution' : 'data-battle-replay';
            const payload = JSON.parse(el.getAttribute(attr) || '{}');
            const fnName = attr === 'data-battle-resolution' ? 'battleResolution' : 'battleReplay';
            const state = Object.assign({}, payload || {});
            const xShowEls = Array.from(el.querySelectorAll('[x-show]'));
            const xTextEls = Array.from(el.querySelectorAll('[x-text]'));
            Array.from(el.querySelectorAll('[x-cloak]')).forEach((c) => c.removeAttribute('x-cloak'));

            function updateLocal() {
                xShowEls.forEach((child) => {
                    const expr = child.getAttribute('x-show') || '';
                    try {
                        const visible = evaluateExpr(expr, state);
                        child.classList.toggle('d-none', !visible);
                    } catch (e) { /* ignore */ }
                });

                xTextEls.forEach((el2) => {
                    const expr = (el2.getAttribute('x-text') || '').trim();
                    if (!expr) return;
                    try {
                        if (expr.endsWith('()')) {
                            const fn = expr.slice(0, -2);
                            const maybeFn = state[fn];
                            if (typeof maybeFn === 'function') {
                                try { el2.textContent = String(maybeFn.call(state)); } catch (e) { /* ignore */ }
                            } else {
                                const val = getStateValue(state, fn);
                                if (val !== undefined) el2.textContent = String(val);
                            }
                        } else {
                            const val = getStateValue(state, expr);
                            if (val !== undefined) el2.textContent = String(val);
                        }
                    } catch (e) { /* ignore */ }
                });
            }

            if (fnName === 'battleResolution' || fnName === 'battleReplay') {
                state.isOpen = !!state.autoOpen;
                state.open = function () { state.isOpen = true; try { updateLocal(); } catch (e) { /* ignore */ } };
                state.close = function () { state.isOpen = false; try { updateLocal(); } catch (e) { /* ignore */ } };
                state.translatedEncounterType = function (key) { return (state.labels && state.labels.encounterTypes && state.labels.encounterTypes[key]) || key || ''; };
                state.translatedCategory = function (cat) { return (state.labels && state.labels.categories && state.labels.categories[cat]) || cat || ''; };
                state.healthPercent = function (cur, max) { const m = Math.max(1, Number(max) || 1); return Math.max(0, Math.min(100, Math.round((Number(cur) || 0) / m * 100))); };
                if (!state.result && state.replay) state.result = state.replay;
                state.enemies = (state.result && state.result.enemies) || (state.enemies || []);
                try { state.playerCurrentHealth = state.playerCurrentHealth ?? ((state.result && state.result.player && (state.result.player.current_health ?? null)) ?? 0); } catch (e) { state.playerCurrentHealth = 0; }
                state.resultStatusLabel = function () { const r = state.result || {}; if (r.victory) return (state.labels && state.labels.victory) || 'Victory'; if (r.retreat) return (state.labels && state.labels.retreat) || 'Retreat'; return (state.labels && state.labels.defeat) || 'Defeat'; };
                state.outcomeClass = function () { const r = state.result || {}; if (r.victory) return 'battle-outcome-success'; if (r.retreat) return 'battle-outcome-retreat'; return 'battle-outcome-failure'; };
                state.resultOutcomeBadge = function () { const r = state.result || {}; if (r.victory) return (state.labels && state.labels.victory) || 'Victory'; if (r.retreat) return (state.labels && state.labels.retreat) || 'Retreat'; return (state.labels && state.labels.defeat) || 'Defeat'; };
                state.resultOutcomeTitle = function () { const r = state.result || {}; if (r.victory) { if (r.encounter_type === 'boss' && state.labels && state.labels.titles && state.labels.titles.bossSoulRises) return state.labels.titles.bossSoulRises; if (state.labels && state.labels.titles && state.labels.titles.fieldIsYours) return state.labels.titles.fieldIsYours; } if (r.retreat && state.labels && state.labels.titles && state.labels.titles.drivenBack) return state.labels.titles.drivenBack; return ''; };
                state.enemyAttackLabel = function (enemy) { if (!enemy) return ''; return enemy.attack ?? enemy.attack_label ?? ''; };
            }

            // initial sync
            updateLocal();

            // attach lightweight `@` and `x-on:` handlers to call state methods where present
            try {
                const allEls = [el].concat(Array.from(el.querySelectorAll('*')));
                allEls.forEach((el2) => {
                    Array.from(el2.attributes || []).forEach((attr) => {
                        const name = String(attr.name || '');
                        if (!name.startsWith('@') && !name.startsWith('x-on:')) return;
                        const spec = name.replace(/^@|^x-on:/, '');
                        const parts = spec.split('.');
                        const eventName = parts[0];
                        const modifiers = parts.slice(1);
                        const targetIsWindow = modifiers.includes('window');
                        const onlyKey = modifiers.find((m) => m !== 'window' && m !== 'prevent' && m !== 'stop');
                        const code = (attr.value || '').trim();
                        const fnMatch = code.match(/^([A-Za-z0-9_$]+)\s*\(/);
                        const fnNameAttr = fnMatch ? fnMatch[1] : null;

                        const attachTo = targetIsWindow ? window : el2;
                        const handler = (e) => {
                            try {
                                if (eventName === 'keydown' && onlyKey && String(onlyKey).toLowerCase() === 'escape' && e.key !== 'Escape') return;
                                if (modifiers.includes('prevent')) e.preventDefault();
                                if (modifiers.includes('stop')) e.stopPropagation();
                                if (fnNameAttr && typeof state[fnNameAttr] === 'function') {
                                    state[fnNameAttr].call(state, e);
                                    try { updateLocal(); } catch (err) { /* ignore */ }
                                }
                            } catch (err) { /* ignore handler errors */ }
                        };

                        try { attachTo.addEventListener(eventName, handler); } catch (e) { /* ignore invalid events */ }
                    });
                });
            } catch (e) { /* ignore event wiring errors */ }

            // expose state for debugging and tests
            el.__xState = state;
            el.__xUpdate = updateLocal;
        } catch (err) { /* ignore invalid JSON */ }
    });

});

// Global inline `@` / `x-on:` wiring for elements outside instrumented roots
(function globalInlineWiring() {
    try {
        const skipSelector = '[data-dashboard-shell],[x-data],[data-battle-resolution],[data-battle-replay],[data-inventory-workspace],[data-inventory-list],[data-admin-row-editor]';
        const nodes = Array.from(document.querySelectorAll('*'));
        nodes.forEach((el) => {
            try {
                if (el.closest && el.closest(skipSelector)) return;
                if (el.dataset && el.dataset.__globalWired) return;
                Array.from(el.attributes || []).forEach((attr) => {
                    const name = String(attr.name || '');
                    if (!name.startsWith('@') && !name.startsWith('x-on:')) return;
                    const spec = name.replace(/^@|^x-on:/, '');
                    const parts = spec.split('.');
                    const eventName = parts[0];
                    const modifiers = parts.slice(1);
                    const targetIsWindow = modifiers.includes('window');
                    const onlyKey = modifiers.find((m) => m !== 'window' && m !== 'prevent' && m !== 'stop');
                    const code = (attr.value || '').trim();
                    const attachTo = targetIsWindow ? window : el;
                    const handler = (e) => {
                        try {
                            if (eventName === 'keydown' && onlyKey && String(onlyKey).toLowerCase() === 'escape' && e.key !== 'Escape') return;
                            if (modifiers.includes('prevent')) e.preventDefault();
                            if (modifiers.includes('stop')) e.stopPropagation();
                            const fn = new Function('event', 'var $event=event; var $el=(event&&event.currentTarget)?event.currentTarget:null; with(window){' + code + '}');
                            fn(e);
                        } catch (err) { /* ignore handler errors */ }
                    };
                    try { attachTo.addEventListener(eventName, handler); } catch (err) { /* ignore invalid events */ }
                });
                if (el.dataset) el.dataset.__globalWired = '1';
            } catch (err) { /* ignore per-element errors */ }
        });
    } catch (e) { /* ignore wiring errors */ }
})();

// Manual-battle protections: prevent double-submit and respect action-lock
(function manualBattleForms() {
    try {
        const forms = Array.from(document.querySelectorAll('form')).filter((f) => f.querySelector('input[name="battle_id"]'));
        forms.forEach((form) => {
            try {
                if (form.dataset && form.dataset.__manualBattleWired) return;
                const submitHandler = (e) => {
                    try {
                        // If an action-lock indicator exists and shows remaining seconds, block submission
                        const lockEls = document.querySelectorAll('[data-action-lock-seconds-remaining]');
                        for (const c of lockEls) {
                            const secs = parseInt(c.dataset.actionLockSecondsRemaining || '0', 10) || 0;
                            if (secs > 0) {
                                e.preventDefault();
                                return;
                            }
                        }

                        // disable submit buttons to avoid double-submits
                        const submits = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
                        submits.forEach((b) => {
                            try { b.disabled = true; b.setAttribute('data-client-disabled', '1'); } catch (er) { /* ignore */ }
                        });

                        // safety re-enable after 5s if navigation didn't occur
                        setTimeout(() => {
                            submits.forEach((b) => {
                                try { if (b.hasAttribute('data-client-disabled')) { b.disabled = false; b.removeAttribute('data-client-disabled'); } } catch (er) { /* ignore */ }
                            });
                        }, 5000);
                    } catch (err) { /* ignore submission handler errors */ }
                };

                form.addEventListener('submit', submitHandler, { passive: false });
                if (form.dataset) form.dataset.__manualBattleWired = '1';
            } catch (err) { /* ignore per-form errors */ }
        });

        // Also guard click on buttons that opted into manual-disable-on-lock
        document.querySelectorAll('[data-manual-disable-on-lock]').forEach((btn) => {
            try {
                if (btn.dataset && btn.dataset.__manualDisableWired) return;
                btn.addEventListener('click', (e) => {
                    try {
                        const lockEls = document.querySelectorAll('[data-action-lock-seconds-remaining]');
                        for (const c of lockEls) {
                            const secs = parseInt(c.dataset.actionLockSecondsRemaining || '0', 10) || 0;
                            if (secs > 0) {
                                e.preventDefault();
                                e.stopImmediatePropagation && e.stopImmediatePropagation();
                                return;
                            }
                        }
                    } catch (err) { /* ignore */ }
                });
                if (btn.dataset) btn.dataset.__manualDisableWired = '1';
            } catch (err) { /* ignore per-button errors */ }
        });
    } catch (e) { /* ignore top-level errors */ }
})();

// Initialize stamina preview widgets
document.querySelectorAll('[data-stamina-preview]').forEach((el) => {
    try {
        const payload = JSON.parse(el.getAttribute('data-stamina-preview') || '{}');
        const state = Object.assign({}, payload || {});
        const xTextEls = Array.from(el.querySelectorAll('[x-text]'));

        // ensure numbers
        state.current = Number(state.current || 0);
        state.maximum = Number(state.maximum || 0);
        state.nextTickSeconds = state.nextTickSeconds === null ? null : Number(state.nextTickSeconds);
        state.labels = state.labels || {};

        state.currentLocaleString = function () { try { return Number(state.current).toLocaleString(); } catch (e) { return String(state.current || '0'); } };
        state.formattedNextTick = function () {
            if (state.current >= state.maximum) return state.labels.fullyRecovered || 'Fully recovered';
            if (state.nextTickSeconds == null) return state.labels.fullyRecovered || 'Fully recovered';
            const mins = Math.ceil((Number(state.nextTickSeconds) || 0) / 60);
            return (state.labels.nextPointIn ? state.labels.nextPointIn + ' ' : '') + mins + ' ' + (state.labels.minutesShort || 'min');
        };

        function updateLocal() {
            xTextEls.forEach((el2) => {
                const expr = (el2.getAttribute('x-text') || '').trim();
                if (!expr) return;
                try {
                    if (expr === 'current.toLocaleString()') {
                        el2.textContent = state.currentLocaleString();
                    } else if (expr === 'formattedNextTick()') {
                        el2.textContent = state.formattedNextTick();
                    } else if (expr.endsWith('()')) {
                        const fn = expr.slice(0, -2);
                        if (typeof state[fn] === 'function') el2.textContent = String(state[fn].call(state));
                    } else {
                        const val = getStateValue(state, expr);
                        if (val !== undefined) el2.textContent = String(val);
                    }
                } catch (e) { /* ignore */ }
            });
        }

        // tick handling
        let timerId = null;
        function startTimer() {
            if (timerId) return;
            if (state.nextTickSeconds == null) return;
            timerId = setInterval(() => {
                try {
                    state.nextTickSeconds = Math.max(0, (Number(state.nextTickSeconds) || 0) - 1);
                    if (state.nextTickSeconds <= 0) {
                        if (state.current < state.maximum) {
                            state.current = Number(state.current || 0) + 1;
                            // assume fixed 60s tick after first
                            state.nextTickSeconds = 60;
                        } else {
                            state.nextTickSeconds = null;
                            clearInterval(timerId);
                            timerId = null;
                        }
                    }
                } catch (e) { /* ignore */ }
                try { updateLocal(); } catch (e) { /* ignore */ }
            }, 1000);
        }

        updateLocal();
        startTimer();

        el.__xState = state;
        el.__xUpdate = updateLocal;
    } catch (e) { /* ignore invalid JSON */ }
});

// Initialize inventory workspace payloads
document.querySelectorAll('[data-inventory-workspace]').forEach((el) => {
    try {
        const payload = JSON.parse(el.getAttribute('data-inventory-workspace') || '{}');
        const state = Object.assign({}, payload || {});
        const xShowEls = Array.from(el.querySelectorAll('[x-show]'));
        const xTextEls = Array.from(el.querySelectorAll('[x-text]'));
        Array.from(el.querySelectorAll('[x-cloak]')).forEach((c) => c.removeAttribute('x-cloak'));

        function updateLocal() {
            xShowEls.forEach((child) => {
                const expr = child.getAttribute('x-show') || '';
                try {
                    const visible = evaluateExpr(expr, state);
                    child.classList.toggle('d-none', !visible);
                } catch (e) { /* ignore */ }
            });

            xTextEls.forEach((el2) => {
                const expr = (el2.getAttribute('x-text') || '').trim();
                if (!expr) return;
                try {
                    if (expr.endsWith('()')) {
                        const fn = expr.slice(0, -2);
                        const maybeFn = state[fn];
                        if (typeof maybeFn === 'function') {
                            try { el2.textContent = String(maybeFn.call(state)); } catch (e) { /* ignore */ }
                        } else {
                            const val = getStateValue(state, fn);
                            if (val !== undefined) el2.textContent = String(val);
                        }
                    } else {
                        const val = getStateValue(state, expr);
                        if (val !== undefined) el2.textContent = String(val);
                    }
                } catch (e) { /* ignore */ }
            });
        }

        // helpers
        state.selectItem = function (id) { state.inventorySelection = id; try { updateLocal(); } catch (e) { /* ignore */ } };
        state.clearSelection = function () { state.inventorySelection = null; try { updateLocal(); } catch (e) { /* ignore */ } };

        // initial sync
        updateLocal();

        // attach lightweight `@` and `x-on:` handlers to call state methods or evaluate inline assignments
        try {
            const allEls = [el].concat(Array.from(el.querySelectorAll('*')));
            allEls.forEach((el2) => {
                Array.from(el2.attributes || []).forEach((attr) => {
                    const name = String(attr.name || '');
                    if (!name.startsWith('@') && !name.startsWith('x-on:')) return;
                    const spec = name.replace(/^@|^x-on:/, '');
                    const parts = spec.split('.');
                    const eventName = parts[0];
                    const modifiers = parts.slice(1);
                    const targetIsWindow = modifiers.includes('window');
                    const onlyKey = modifiers.find((m) => m !== 'window' && m !== 'prevent' && m !== 'stop');
                    const code = (attr.value || '').trim();
                    const attachTo = targetIsWindow ? window : el2;
                    const handler = (e) => {
                        try {
                            if (eventName === 'keydown' && onlyKey && String(onlyKey).toLowerCase() === 'escape' && e.key !== 'Escape') return;
                            if (modifiers.includes('prevent')) e.preventDefault();
                            if (modifiers.includes('stop')) e.stopPropagation();
                            const fn = new Function('state', 'event', 'var $event=event; var $el=(event&&event.currentTarget)?event.currentTarget:null; with(state){' + code + '}');
                            fn(state, e);
                            updateLocal();
                        } catch (err) { /* ignore handler errors */ }
                    };
                    try { attachTo.addEventListener(eventName, handler); } catch (e) { /* ignore invalid events */ }
                });
            });
        } catch (e) { /* ignore event wiring errors */ }

        el.__xState = state;
        el.__xUpdate = updateLocal;
    } catch (err) { /* ignore invalid JSON */ }
});

// Inventory actions: AJAX submit with graceful fallback and simple optimistic UI applier
(function inventoryActions() {
    try {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const csrf = meta ? String(meta.getAttribute('content') || '') : '';

        function showServerMessage({ title = '', lines = [], tone = 'info' } = {}) {
            try {
                let stack = document.querySelector('.dashboard-server-message-stack');
                if (!stack) {
                    stack = document.createElement('div');
                    stack.className = 'dashboard-server-message-stack';
                    stack.setAttribute('aria-live', 'polite');
                    stack.setAttribute('aria-atomic', 'true');
                    const anchor = document.querySelector('#dashboard') || document.body;
                    anchor.insertBefore(stack, anchor.firstChild);
                }

                const section = document.createElement('section');
                section.className = `theme-status-flash dashboard-server-message-card theme-status-${tone}`;
                section.setAttribute('data-dismiss-on-click', '');

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dashboard-server-message-close';
                btn.setAttribute('data-dismiss', '');
                btn.setAttribute('aria-label', 'Dismiss message');
                btn.innerHTML = '<span aria-hidden="true">&times;</span>';
                section.appendChild(btn);

                if (title) {
                    const h = document.createElement('p');
                    h.className = 'dashboard-server-message-kicker';
                    h.textContent = title;
                    section.appendChild(h);
                }

                const linesWrap = document.createElement('div');
                linesWrap.className = 'dashboard-server-message-lines';
                (lines || []).forEach((l) => {
                    const p = document.createElement('p');
                    p.textContent = l;
                    linesWrap.appendChild(p);
                });
                section.appendChild(linesWrap);

                stack.insertBefore(section, stack.firstChild);
            } catch (e) { /* ignore message errors */ }
        }

        function applyInventoryChanges(changes) {
            try {
                if (!Array.isArray(changes)) return;
                changes.forEach((c) => {
                    try {
                        const id = String(c.id || c.character_item_id || c.characterItemId || '');
                        if (!id) return;
                        const row = document.querySelector(`.inventory-list-row[data-inventory-item-id="${id}"]`);
                        if (!row) return;
                        if (typeof c.quantity !== 'undefined') {
                            const count = row.querySelector('.inventory-list-row-count');
                            if (count) count.textContent = 'x' + Number(c.quantity || 0).toLocaleString();
                        }
                        if (c.removed) {
                            row.remove();
                            return;
                        }
                        if (typeof c.equipped !== 'undefined') {
                            row.classList.toggle('inventory-list-row-equipped', !!c.equipped);
                            const equipForm = row.querySelector('form[action*="equip-item"]');
                            const unequipForm = row.querySelector('form[action*="unequip-item"]');
                            if (equipForm) {
                                const btn = equipForm.querySelector('button[type="submit"]');
                                if (btn) {
                                    btn.textContent = c.equipped ? (btn.getAttribute('data-equipped-label') || 'Equipped') : (btn.getAttribute('data-equip-label') || 'Equip');
                                    btn.disabled = !!c.equipped;
                                }
                            }
                            if (unequipForm) {
                                const btn = unequipForm.querySelector('button[type="submit"]');
                                if (btn) btn.disabled = !c.equipped;
                            }
                        }
                    } catch (e) { /* per-change ignore */ }
                });
            } catch (e) { /* ignore */ }
        }

        document.querySelectorAll('.inventory-list-row-action-form').forEach((form) => {
            try {
                if (form.dataset && form.dataset.__inventoryAjaxWired) return;
                form.addEventListener('submit', (ev) => {
                    try {
                        ev.preventDefault();
                        // block when action-lock active
                        const lockEls = document.querySelectorAll('[data-action-lock-seconds-remaining]');
                        for (const c of lockEls) {
                            const secs = parseInt(c.dataset.actionLockSecondsRemaining || '0', 10) || 0;
                            if (secs > 0) return;
                        }

                        const submits = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
                        submits.forEach((b) => { try { b.disabled = true; b.setAttribute('data-client-disabled', '1'); } catch (e) { } });

                        const action = form.getAttribute('action') || window.location.href;
                        const method = (form.getAttribute('method') || 'POST').toUpperCase();
                        const fd = new FormData(form);

                        // apply optimistic UI changes so the inventory feels responsive
                        try {
                            const m = String(action).match(/game\/actions\/([-a-zA-Z0-9_]+)/);
                            const actionName = m ? m[1] : null;
                            (function performOptimisticUpdate(actionName, formData) {
                                try {
                                    if (!actionName) return;
                                    const id = String(formData.get('character_item_id') || formData.get('characterItemId') || '');
                                    if (!id) return;
                                    const row = document.querySelector(`.inventory-list-row[data-inventory-item-id="${id}"]`);
                                    if (!row) return;

                                    if (actionName === 'equip-item') {
                                        applyInventoryChanges([{ id, equipped: true }]);
                                    } else if (actionName === 'unequip-item') {
                                        applyInventoryChanges([{ id, equipped: false }]);
                                    } else if (actionName === 'use-item' || actionName === 'sell-item') {
                                        const qtyRaw = String(formData.get('quantity') || formData.get('qty') || '1');
                                        const qty = Math.max(1, parseInt(qtyRaw, 10) || 1);
                                        const countEl = row.querySelector('.inventory-list-row-count');
                                        let current = 0;
                                        if (countEl) {
                                            const txt = String(countEl.textContent || '').replace(/^x/, '').replace(/,/g, '').trim();
                                            current = parseInt(txt, 10) || 0;
                                        }
                                        const next = Math.max(0, current - qty);
                                        if (next <= 0) applyInventoryChanges([{ id, removed: true }]);
                                        else applyInventoryChanges([{ id, quantity: next }]);
                                    }
                                } catch (e) { /* ignore optimistic errors */ }
                            }(actionName, fd));
                        } catch (e) { /* ignore optimistic update errors */ }

                        fetch(action, {
                            method,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'text/html, application/json'
                            },
                            body: fd,
                            credentials: 'same-origin',
                            redirect: 'follow'
                        }).then(async (res) => {
                            try {
                                if (res.redirected) {
                                    // follow server redirect to let backend decide final UI
                                    window.location = res.url;
                                    return;
                                }

                                const ct = String(res.headers.get('content-type') || '');
                                if (res.ok && ct.includes('application/json')) {
                                    const json = await res.json().catch(() => ({}));
                                    if (json.status || json.message) {
                                        showServerMessage({ title: json.status || '', lines: Array.isArray(json.lines) ? json.lines : [json.message || ''], tone: json.tone || 'info' });
                                    }
                                    if (json.changes) applyInventoryChanges(json.changes);
                                    if (json.reload) window.location.reload();
                                } else {
                                    // fallback to full reload so server can render canonical state
                                    window.location.reload();
                                }
                            } catch (err) { window.location.reload(); }
                        }).catch(() => {
                            // on network error, submit the original form as fallback
                            try { form.submit(); } catch (e) { window.location.reload(); }
                        }).finally(() => {
                            setTimeout(() => { submits.forEach((b) => { try { if (b.hasAttribute('data-client-disabled')) { b.disabled = false; b.removeAttribute('data-client-disabled'); } } catch (e) { } }); }, 4000);
                        });
                    } catch (e) { /* ignore */ }
                }, { passive: false });

                if (form.dataset) form.dataset.__inventoryAjaxWired = '1';
            } catch (e) { /* ignore per-form errors */ }
        });
    } catch (e) { /* ignore top-level */ }
})();
