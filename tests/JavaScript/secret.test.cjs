const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {webcrypto} = require('node:crypto');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/js/secret.js'), 'utf8');
function generate(dataset, checkboxes = {}) {
    const callbacks = {};
    const output = {value: ''};
    const form = {querySelector(q) {
        if (q === '.plugin-secret-generator-options') return {dataset};
        if (q === '.plugin-secret-value-input') return output;
        const key = q.replace('.plugin-secret-generator-', '');
        return Object.hasOwn(checkboxes, key) ? {checked: checkboxes[key]} : null;
    }};
    const context = {
        document: {addEventListener(k, callback) {callbacks[k] = callback;}, documentElement: {}},
        MutationObserver: class {observe() {}},
        window: {crypto: webcrypto}, Uint32Array,
    };
    vm.runInNewContext(source, context);
    callbacks.click({target: {closest(q) {
        return q === '.plugin-secret-generate' ? {closest() {return form;}} : null;
    }}});
    return output.value;
}
test('replacement honors dataset-only ambiguous exclusion', () => {
    for (let i = 0; i < 20; i++) {
        const result = generate({length:'256', lowercase:'1', uppercase:'1', digits:'1', special:'1', excludeAmbiguous:'1'});
        assert.equal(result.length, 256);
        assert.doesNotMatch(result, /[Il1O0o]/);
    }
});
test('ambiguous characters cannot enable disabled categories', () => {
    for (const [category, regex] of [['digits', /^[0-9]+$/], ['lowercase', /^[a-z]+$/], ['uppercase', /^[A-Z]+$/], ['special', /^[^a-zA-Z0-9]+$/]]) {
        for (let i = 0; i < 10; i++) {
            const result = generate({length:'256', [category]:'1', excludeAmbiguous:'0'});
            assert.equal(result.length, 256);
            assert.match(result, regex);
        }
    }
});
test('visible controls override defaults without enabling other categories', () => {
    const result = generate({length:'32',lowercase:'1',uppercase:'1',digits:'1',special:'1',excludeAmbiguous:'1'},
        {lowercase:false, uppercase:false, special:false, digits:true, 'exclude-ambiguous':false});
    assert.match(result, /^[0-9]{32}$/);
});

test('type selection switches credential and sensitive-information fields safely', () => {
    const callbacks = {};
    const type = {value: 'password'};
    const username = {disabled: false, value: 'must be cleared'};
    const usernameField = {hidden: false, querySelector() {return username;}};
    const passwordInput = {disabled: false, required: true, value: 'password draft'};
    const passwordValue = {hidden: false, querySelector() {return passwordInput;}};
    const sensitiveInput = {hidden: true, disabled: true, required: false, value: ''};
    const generator = {hidden: false};
    class Form {
        querySelector(q) {
            return ({
                '.plugin-secret-type': type,
                '.plugin-secret-username-field': usernameField,
                '.plugin-secret-password-value': passwordValue,
                '.plugin-secret-sensitive-value': sensitiveInput,
                '.plugin-secret-generator-options': generator,
            })[q] || null;
        }
    }
    const form = new Form();
    const context = {
        document: {
            addEventListener(k, callback) {callbacks[k] = callback;},
            querySelectorAll() {return [form];},
            getElementById() {return null;},
            documentElement: {},
        },
        MutationObserver: class {observe() {}},
        HTMLFormElement: Form,
        CFG_GLPI: {root_doc: ''},
        window: {crypto: webcrypto},
        Uint32Array,
    };
    vm.runInNewContext(source, context);
    callbacks.DOMContentLoaded();
    assert.equal(usernameField.hidden, true);
    assert.equal(username.disabled, true);

    type.value = 'credential';
    callbacks.change({target: {
        closest() {return form;},
        matches(q) {return q === '.plugin-secret-type';},
    }});
    assert.equal(usernameField.hidden, false);
    assert.equal(username.disabled, false);

    type.value = 'other';
    callbacks.change({target: {
        closest() {return form;},
        matches(q) {return q === '.plugin-secret-type';},
    }});
    assert.equal(passwordValue.hidden, true);
    assert.equal(passwordInput.disabled, true);
    assert.equal(passwordInput.value, '');
    assert.equal(sensitiveInput.hidden, false);
    assert.equal(sensitiveInput.disabled, false);
    assert.equal(sensitiveInput.required, true);
    assert.equal(generator.hidden, true);
});
