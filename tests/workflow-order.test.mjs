import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const workspace = process.env.TEST_WORKSPACE_ROOT || path.resolve(import.meta.dirname, '..');
const source = fs.readFileSync(path.join(workspace, 'public/assets/workflow.js'), 'utf8');
const start = source.indexOf('    async function saveStageOrder(');
const end = source.indexOf("    root.addEventListener('dragstart'", start);

function harness(kind, ids, failure = false) {
    const calls = [];
    const items = ids.map(id => ({ dataset: { sortStep: String(id) }, hasAttribute: () => true }));
    const classes = new Set();
    const list = {
        dataset: { stageSort: kind, containerId: '42' }, children: [...items],
        classList: { add: key => classes.add(key), remove: key => classes.delete(key) },
        querySelector: selector => items.find(item => selector === `[data-sort-step="${item.dataset.sortStep}"]`),
        append: item => { list.children = list.children.filter(other => other !== item); list.children.push(item); },
    };
    const context = vm.createContext({
        api: async (url, options) => { calls.push({ url, options }); if (failure) throw new Error('Network failed'); },
        loadTemplates: async () => calls.push('templates reloaded'),
        showProject: async id => calls.push(`project ${id} reloaded`),
        toast: (message, tone) => calls.push({ message, tone }),
    });
    vm.runInContext('let stageSortBusy = false;\n' + source.slice(start, end) + '\nglobalThis.saveStageOrder = saveStageOrder; globalThis.busy = () => stageSortBusy;', context);
    return { list, classes, calls, context };
}

test('template reorder posts all stages in new order and reloads details', async () => {
    const h = harness('template', [3, 1, 2]);
    await h.context.saveStageOrder(h.list, [1, 2, 3]);
    assert.equal(h.calls[0].url, 'templates/42/steps/reorder');
    assert.equal(h.calls[0].options.method, 'POST');
    assert.deepEqual(Array.from(h.calls[0].options.body.step_ids), [3, 1, 2]);
    assert.ok(h.calls.includes('templates reloaded'));
    assert.equal(h.context.busy(), false);
    assert.equal(h.classes.size, 0);
});

test('project reorder persists through the project workflow endpoint', async () => {
    const h = harness('project', [2, 3, 1]);
    await h.context.saveStageOrder(h.list, [1, 2, 3]);
    assert.equal(h.calls[0].url, 'orders/42/steps/reorder');
    assert.ok(h.calls.includes('project 42 reloaded'));
});

test('failed save restores the original visual order and allows retry', async () => {
    const h = harness('project', [3, 1, 2], true);
    await h.context.saveStageOrder(h.list, [1, 2, 3]);
    assert.deepEqual(h.list.children.map(item => Number(item.dataset.sortStep)), [1, 2, 3]);
    assert.equal(h.calls[1].tone, 'error');
    assert.equal(h.context.busy(), false);
    assert.equal(h.classes.size, 0);
});

test('dropping a stage without moving it sends no write request', async () => {
    const h = harness('project', [1, 2, 3]);
    await h.context.saveStageOrder(h.list, [1, 2, 3]);
    assert.equal(h.calls.length, 0);
});
