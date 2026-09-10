(function () {
    'use strict';

    const sets = {
        lowercase: 'abcdefghijkmnpqrstuvwxyz',
        uppercase: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        digits: '23456789',
        special: '!@#$%^&*()-_=+[]{}:,.?',
    };

    function randomIndex(max) {
        const ceiling = Math.floor(0x100000000 / max) * max;
        const data = new Uint32Array(1);
        do {
            window.crypto.getRandomValues(data);
        } while (data[0] >= ceiling);
        return data[0] % max;
    }

    function generate(form) {
        const options = form.querySelector('.plugin-secret-generator-options');
        const lengthInput = form.querySelector('.plugin-secret-length');
        const length = Math.max(8, Math.min(256, Number(lengthInput?.value || options?.dataset.length) || 20));
        const enabled = Object.keys(sets).filter((key) => {
            const checkbox = form.querySelector('.plugin-secret-generator-' + key);
            return checkbox ? checkbox.checked : options?.dataset[key] === '1';
        });
        if (enabled.length === 0) {
            return;
        }

        const excludeInput = form.querySelector('.plugin-secret-generator-exclude-ambiguous');
        const excludeAmbiguous = excludeInput ? excludeInput.checked : options?.dataset.excludeAmbiguous === '1';
        const ambiguous = {lowercase: 'lo', uppercase: 'IO', digits: '01', special: ''};
        const categories = enabled.map((key) => sets[key] + (excludeAmbiguous ? '' : ambiguous[key]));
        const chars = categories.map((set) => set[randomIndex(set.length)]);
        const pool = categories.join('');
        while (chars.length < length) {
            chars.push(pool[randomIndex(pool.length)]);
        }
        for (let index = chars.length - 1; index > 0; index--) {
            const swap = randomIndex(index + 1);
            [chars[index], chars[swap]] = [chars[swap], chars[index]];
        }
        const target = form.querySelector('.plugin-secret-value-input');
        if (target) {
            target.value = chars.join('');
        }
        if (options) {
            options.dataset.generated = '1';
        }
    }

    async function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }
        const field = document.createElement('textarea');
        field.value = value;
        field.readOnly = true;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        let copied;
        try { copied = document.execCommand('copy'); } finally { field.value = ''; field.remove(); }
        if (!copied) {
            throw new Error(__('Copy was refused by the browser.', 'secret'));
        }
    }

    function refreshConditionalFields(form) {
        const visibility = form.querySelector('.plugin-secret-visibility')?.value;
        const groupField = form.querySelector('.plugin-secret-group-field');
        if (groupField) {
            groupField.hidden = visibility !== 'group';
            const select = groupField.querySelector('select');
            if (select) {
                select.required = visibility === 'group';
            }
        }
        const expiration = form.querySelector('.plugin-secret-expiration')?.value;
        const customField = form.querySelector('.plugin-secret-custom-expiration');
        if (customField) {
            customField.hidden = expiration !== 'custom';
            const input = customField.querySelector('input');
            if (input) {
                input.required = expiration === 'custom';
            }
        }
    }

    function enforceCreateEndpoint(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const root = typeof CFG_GLPI === 'object' && typeof CFG_GLPI.root_doc === 'string'
            ? CFG_GLPI.root_doc.replace(/\/$/, '')
            : '';
        form.action = `${root}/plugins/secret/Itil/Secret`;
        form.method = 'post';
        const csrf = typeof getAjaxCsrfToken === 'function' ? getAjaxCsrfToken() : null;
        const csrfInput = form.querySelector('input[name="_glpi_csrf_token"]');
        if (csrf && csrfInput) {
            // The timeline can remain open while other native forms rotate
            // their shared token. GLPI's standalone page token remains valid.
            csrfInput.value = csrf;
        }
    }

    async function reveal(button) {
        button.disabled = true;
        try {
            const body = new FormData();
            body.set('itemtype', button.dataset.itemtype);
            body.set('items_id', button.dataset.itemsId);
            body.set('action', button.dataset.action);
            const csrf = (typeof getAjaxCsrfToken === 'function' ? getAjaxCsrfToken() : null)
                || button.dataset.csrf;
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': csrf,
                    'Accept': 'application/json',
                },
                cache: 'no-store',
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const payload = await response.json();
            if (typeof payload.value !== 'string') {
                throw new Error(__('Invalid secret response.', 'secret'));
            }

            if (button.dataset.action === 'copy') {
                await copyText(payload.value);
                if (typeof glpi_toast_info === 'function') {
                    glpi_toast_info(__('Secret copied to the clipboard.', 'secret'));
                }
                return;
            }
            const container = button.closest('td, .plugin-secret-timeline-content');
            const target = container?.querySelector('.plugin-secret-revealed');
            if (!target) {
                throw new Error(__('Reveal area not found.', 'secret'));
            }
            target.replaceChildren();
            const group = document.createElement('div');
            group.className = 'input-group input-group-sm';
            const input = document.createElement('input');
            input.type = 'password';
            input.readOnly = true;
            input.className = 'form-control font-monospace';
            input.value = payload.value;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'btn btn-outline-secondary';
            toggle.textContent = '👁';
            toggle.addEventListener('click', () => {
                input.type = input.type === 'password' ? 'text' : 'password';
            });
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn btn-outline-secondary';
            close.textContent = __('Hide and clear', 'secret');
            const clear = () => {
                input.value = '';
                group.remove();
                if (!target.children.length) target.hidden = true;
            };
            const timer = window.setTimeout(clear, 60000);
            close.addEventListener('click', () => { window.clearTimeout(timer); clear(); });
            window.addEventListener('pagehide', clear, {once: true});
            group.append(input, toggle, close);
            target.append(group);
            target.hidden = false;
        } finally {
            button.disabled = false;
        }
    }

    function reportActionFailure(error) {
        const message = error instanceof Error ? error.message : __('Unknown error.', 'secret');
        if (typeof glpi_toast_error === 'function') {
            glpi_toast_error(__('Secret action failed: %s', 'secret').replace('%s', message));
        }
    }

    async function audit(button) {
        button.disabled = true;
        try {
            const csrf = (typeof getAjaxCsrfToken === 'function' ? getAjaxCsrfToken() : null)
                || button.dataset.csrf;
            const body = new FormData();
            body.set('itemtype', button.dataset.itemtype);
            body.set('items_id', button.dataset.itemsId);
            body.set('before', button.dataset.before || '0');
            const response = await fetch(button.dataset.url, {
                method: 'POST', body, credentials: 'same-origin', cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': csrf,
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            const target = button.closest('td').querySelector('.plugin-secret-audit-result');
            if (!button.dataset.before) target.replaceChildren();
            target.querySelector('.plugin-secret-audit-more')?.remove();
            const list = document.createElement('ul');
            list.className = 'list-unstyled small mb-0';
            (payload.entries || []).forEach((entry) => {
                const line = document.createElement('li');
                const identity = entry.user_login
                    ? `${entry.user_login} (ID ${entry.users_id})`
                    : `ID ${entry.users_id}`;
                line.textContent = `${entry.date_creation} — ${entry.action} — ${identity}`;
                list.append(line);
            });
            target.append(list);
            if (payload.next_before) {
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'btn btn-sm btn-outline-secondary plugin-secret-audit-more';
                more.textContent = __('Load older entries', 'secret');
                more.addEventListener('click', () => {
                    button.dataset.before = String(payload.next_before);
                    audit(button).catch(reportActionFailure);
                });
                target.append(more);
            }
            target.hidden = false;
        } finally {
            button.disabled = false;
        }
    }

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('.plugin-secret-toggle');
        if (toggle) {
            const input = document.getElementById(toggle.dataset.target);
            if (input) {
                input.type = input.type === 'password' ? 'text' : 'password';
            }
            return;
        }
        const generateButton = event.target.closest('.plugin-secret-generate');
        if (generateButton) {
            generate(generateButton.closest('form'));
            return;
        }
        const copyInput = event.target.closest('.plugin-secret-copy-input');
        if (copyInput) {
            const input = document.getElementById(copyInput.dataset.target);
            if (input) {
                copyText(input.value).catch(reportActionFailure);
            }
            return;
        }
        const action = event.target.closest('.plugin-secret-action');
        if (action) {
            reveal(action).catch(reportActionFailure);
            return;
        }
        const auditButton = event.target.closest('.plugin-secret-audit');
        if (auditButton) {
            delete auditButton.dataset.before;
            audit(auditButton).catch(reportActionFailure);
        }
    });

    document.addEventListener('submit', (event) => {
        if (event.target.matches('.plugin-secret-create-form')) {
            enforceCreateEndpoint(event.target);
        }
        if (event.target.matches('.plugin-secret-delete-form')
            && !window.confirm(__('Permanently delete this secret?', 'secret'))) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('change', (event) => {
        const form = event.target.closest('.plugin-secret-create-form');
        if (form && (event.target.matches('.plugin-secret-visibility') || event.target.matches('.plugin-secret-expiration'))) {
            refreshConditionalFields(form);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.plugin-secret-create-form').forEach((form) => {
            enforceCreateEndpoint(form);
            refreshConditionalFields(form);
        });

        const answerBlock = document.getElementById('new-PluginSecretSecret-block');
        if (answerBlock) {
            answerBlock.addEventListener('shown.bs.collapse', () => {
                answerBlock.scrollIntoView({block: 'start', behavior: 'smooth'});
                answerBlock.querySelector('input[name="name"]')?.focus({preventScroll: true});
            });
        }
    });

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) {
                return;
            }
            const forms = node.matches('.plugin-secret-create-form')
                ? [node]
                : node.querySelectorAll('.plugin-secret-create-form');
            forms.forEach((form) => {
                enforceCreateEndpoint(form);
                refreshConditionalFields(form);
            });
        }));
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
