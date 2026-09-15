import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import test from 'node:test';

const text = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const bytes = (path) => readFileSync(new URL(`../${path}`, import.meta.url));

test('manifest is a complete RTL standalone application', () => {
    const manifest = JSON.parse(text('public/manifest.webmanifest'));
    assert.equal(manifest.lang, 'fa');
    assert.equal(manifest.dir, 'rtl');
    assert.equal(manifest.display, 'standalone');
    assert.equal(manifest.scope, '/');
    assert.match(manifest.start_url, /^\/workspace/);
    assert.ok(manifest.icons.some((icon) => icon.sizes === '192x192' && icon.purpose === 'any'));
    assert.ok(manifest.icons.some((icon) => icon.sizes === '512x512' && icon.purpose === 'maskable'));
});

test('all declared PNG icons exist with their declared dimensions', () => {
    for (const [path, expected] of [
        ['public/icons/taskflow-192.png', 192],
        ['public/icons/taskflow-512.png', 512],
        ['public/icons/taskflow-maskable-192.png', 192],
        ['public/icons/taskflow-maskable-512.png', 512],
        ['public/icons/apple-touch-icon.png', 180],
    ]) {
        const image = bytes(path);
        assert.ok(statSync(new URL(`../${path}`, import.meta.url)).size > 1000);
        assert.equal(image.subarray(1, 4).toString(), 'PNG');
        assert.equal(image.readUInt32BE(16), expected);
        assert.equal(image.readUInt32BE(20), expected);
    }
});

test('service worker keeps private navigation and API responses out of caches', () => {
    const worker = text('public/sw.js');
    assert.match(worker, /request\.mode === 'navigate'/);
    assert.match(worker, /fetch\(request\)\.catch\(\(\) => caches\.match\(OFFLINE_URL\)\)/);
    assert.match(worker, /url\.pathname\.startsWith\('\/api\/'\)\) return/);
    assert.match(worker, /showNotification/);
    assert.match(worker, /notificationclick/);
});

test('install and push permission are user-driven and remember the device decision', () => {
    const pwa = text('public/assets/pwa.js');
    assert.match(pwa, /beforeinstallprompt/);
    assert.match(pwa, /taskflow_pwa_install_prompted_v2/);
    assert.match(pwa, /installButton\?\.addEventListener\('click'/);
    assert.match(pwa, /enable\?\.addEventListener\('click'/);
    assert.match(pwa, /Notification\.requestPermission\(\)/);
    assert.match(pwa, /taskflow_push_owner_v1/);
    assert.doesNotMatch(pwa, /Notification\.requestPermission\(\);?\s*}\s*setupPush/);
});

test('login and logout expose secure device lifecycle controls', () => {
    const login = text('views/login.php');
    const app = text('public/assets/app.js');
    assert.match(login, /name="remember_device"/);
    assert.match(app, /push_endpoint/);
    assert.match(app, /afterLogout/);
});
