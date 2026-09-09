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
        const length = Math.max(8, Math.min(256, Number(form.querySelector('.plugin-secret-length').value) || 20));
        const enabled = Object.keys(sets).filter((key) => form.querySelector('.plugin-secret-generator-' + key)?.checked);
        if (enabled.length === 0) {
            return;
        }

        const chars = enabled.map((key) => sets[key][randomIndex(sets[key].length)]);
        let pool = enabled.map((key) => sets[key]).join('');
        if (!form.querySelector('.plugin-secret-generator-exclude-ambiguous')?.checked) {
            pool += 'Il1O0o';
        }
        while (chars.length < length) {
            chars.push(pool[randomIndex(pool.length)]);
        }
        for (let index = chars.length - 1; index > 0; index--) {
            const swap = randomIndex(index + 1);
            [chars[index], chars[swap]] = [chars[swap], chars[index]];
        }
        form.querySelector('#secret-value').value = chars.join('');
        options.dataset.generated = '1';
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

    async function reveal(button) {
        button.disabled = true;
        try {
            const body = new FormData();
            body.set('itemtype', button.dataset.itemtype);
            body.set('items_id', button.dataset.itemsId);
            body.set('action', button.dataset.action);
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {'X-Glpi-Csrf-Token': button.dataset.csrf, 'Accept': 'application/json'},
                cache: 'no-store',
            });
            if (!response.ok) {
                throw new Error('Secret request denied');
            }
            const payload = await response.json();
            if (typeof payload.value !== 'string') {
                throw new Error('Invalid secret response');
            }

            if (button.dataset.action === 'copy') {
                await navigator.clipboard.writeText(payload.value);
                return;
            }
            const target = button.closest('td').querySelector('.plugin-secret-revealed');
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
            group.append(input, toggle);
            target.append(group);
            target.hidden = false;
        } finally {
            button.disabled = false;
        }
    }

    async function audit(button) {
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'X-Glpi-Csrf-Token': button.dataset.csrf, 'Accept': 'application/json'},
            });
            if (!response.ok) throw new Error('Audit request denied');
            const payload = await response.json();
            const target = button.closest('td').querySelector('.plugin-secret-audit-result');
            target.replaceChildren();
            const list = document.createElement('ul');
            list.className = 'list-unstyled small mb-0';
            (payload.entries || []).forEach((entry) => {
                const line = document.createElement('li');
                line.textContent = `${entry.date_creation} — ${entry.action} — utilisateur #${entry.users_id}`;
                list.append(line);
            });
            target.append(list);
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
                navigator.clipboard.writeText(input.value).catch(() => {});
            }
            return;
        }
        const action = event.target.closest('.plugin-secret-action');
        if (action) {
            reveal(action).catch(() => {});
            return;
        }
        const auditButton = event.target.closest('.plugin-secret-audit');
        if (auditButton) {
            audit(auditButton).catch(() => {});
        }
    });

    document.addEventListener('submit', (event) => {
        if (event.target.matches('.plugin-secret-delete-form') && !window.confirm('Supprimer définitivement ce secret ?')) {
            event.preventDefault();
        }
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest('.plugin-secret-create-form');
        if (form && (event.target.matches('.plugin-secret-visibility') || event.target.matches('.plugin-secret-expiration'))) {
            refreshConditionalFields(form);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.plugin-secret-create-form').forEach(refreshConditionalFields);

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
            forms.forEach(refreshConditionalFields);
        }));
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
