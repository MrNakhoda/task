(() => {
    'use strict';

    const authenticated = document.querySelector('meta[name="app-authenticated"]')?.content === '1';
    const userId = document.querySelector('meta[name="app-user-id"]')?.content || '0';
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const mobile = window.matchMedia('(max-width: 820px)').matches || /android|iphone|ipad|ipod/i.test(navigator.userAgent);
    const installCard = document.querySelector('[data-pwa-install-card]');
    const installGuide = installCard?.querySelector('[data-pwa-ios-guide]');
    const installCopy = installCard?.querySelector('[data-pwa-install-copy]');
    const installButton = installCard?.querySelector('[data-pwa-install]');
    const openInstallButtons = [...document.querySelectorAll('[data-pwa-open-install]')];
    const installPromptKey = 'taskflow_pwa_install_prompted_v2';
    const pushOwnerKey = 'taskflow_push_owner_v1';
    let deferredInstallPrompt = null;
    let registration = null;
    let autoInstallTimer = null;

    const stored = (key) => { try { return localStorage.getItem(key); } catch { return null; } };
    const store = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
    const removeStored = (key) => { try { localStorage.removeItem(key); } catch {} };

    const pwaApi = async (path, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (options.body) headers.set('Content-Type', 'application/json');
        if ((options.method || 'GET').toUpperCase() !== 'GET') headers.set('X-CSRF-Token', csrf());
        const response = await fetch(new URL(`api/v1/pwa/${path}`, document.baseURI), { credentials: 'same-origin', ...options, headers });
        const result = await response.json().catch(() => ({ ok: false, error: 'پاسخ سرور معتبر نبود.' }));
        if (!response.ok || !result.ok) throw new Error(result.error || 'عملیات اعلان انجام نشد.');
        return result;
    };

    const showInstall = (automatic = false) => {
        if (!installCard || standalone()) return;
        if (automatic) store(installPromptKey, '1');
        installGuide.hidden = true;
        installButton.hidden = false;
        installButton.textContent = deferredInstallPrompt ? 'نصب برنامه' : (ios ? 'نمایش راهنمای نصب' : 'راهنمای نصب');
        installCopy.textContent = 'دسترسی سریع‌تر، نمایش تمام‌صفحه و اعلان وظایف را فعال کنید.';
        installCard.hidden = false;
    };

    const scheduleInstallIntro = () => {
        if (!authenticated || !mobile || standalone() || stored(installPromptKey) || autoInstallTimer) return;
        if (!deferredInstallPrompt && !ios) return;
        autoInstallTimer = window.setTimeout(() => showInstall(true), 1600);
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        scheduleInstallIntro();
        openInstallButtons.forEach((button) => { button.hidden = standalone(); });
    });
    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        store(installPromptKey, '1');
        if (installCard) installCard.hidden = true;
        openInstallButtons.forEach((button) => { button.hidden = true; });
    });
    installButton?.addEventListener('click', async () => {
        if (deferredInstallPrompt) {
            await deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice.catch(() => null);
            deferredInstallPrompt = null;
            installCard.hidden = true;
            return;
        }
        installGuide.hidden = !ios;
        installCopy.textContent = ios
            ? 'در Safari از منوی اشتراک‌گذاری، TaskFlow را به صفحه اصلی اضافه کنید.'
            : 'از منوی مرورگر گزینه Install app یا Add to Home screen را انتخاب کنید.';
        installButton.hidden = true;
    });
    installCard?.querySelector('[data-pwa-install-dismiss]')?.addEventListener('click', () => {
        store(installPromptKey, '1');
        installCard.hidden = true;
    });
    openInstallButtons.forEach((button) => button.addEventListener('click', () => showInstall(false)));

    const updateCard = document.querySelector('[data-pwa-update]');
    const watchWorker = (worker) => worker?.addEventListener('statechange', () => {
        if (worker.state === 'installed' && navigator.serviceWorker.controller && updateCard) updateCard.hidden = false;
    });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            try {
                const installedRegistration = await navigator.serviceWorker.register(new URL('sw.js', document.baseURI));
                if (installedRegistration.waiting && updateCard) updateCard.hidden = false;
                installedRegistration.addEventListener('updatefound', () => watchWorker(installedRegistration.installing));
                registration = await navigator.serviceWorker.ready;
                await setupPush();
            } catch {
                setPushStatus('مرورگر نتوانست سرویس برنامه و اعلان را فعال کند.', 'error');
            }
        });
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) { refreshing = true; location.reload(); }
        });
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'TASKFLOW_NOTIFICATION') window.dispatchEvent(new CustomEvent('taskflow:notification'));
        });
    }
    document.querySelector('[data-pwa-update-apply]')?.addEventListener('click', () => registration?.waiting?.postMessage({ type: 'SKIP_WAITING' }));

    const offline = document.querySelector('[data-pwa-offline]');
    const syncConnection = () => { if (offline) offline.hidden = navigator.onLine; };
    window.addEventListener('online', syncConnection);
    window.addEventListener('offline', syncConnection);
    syncConnection();

    const status = document.querySelector('[data-push-status]');
    const enable = document.querySelector('[data-push-enable]');
    const disable = document.querySelector('[data-push-disable]');
    const test = document.querySelector('[data-push-test]');
    const setPushStatus = (text, type = '') => {
        if (!status) return;
        status.textContent = text;
        status.dataset.state = type;
    };
    const applicationKey = (value) => {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
    };
    const subscriptionPayload = (subscription) => ({
        ...subscription.toJSON(),
        contentEncoding: PushManager.supportedContentEncodings?.[0] || 'aes128gcm',
    });
    const renderPush = (active, message) => {
        if (!status) return;
        setPushStatus(message, active ? 'success' : '');
        if (enable) enable.hidden = active || Notification.permission === 'denied';
        if (disable) disable.hidden = !active;
        if (test) test.hidden = !active;
    };

    async function setupPush() {
        if (!authenticated || !status) return;
        openInstallButtons.forEach((button) => { button.hidden = standalone(); });
        if (!('PushManager' in window) || !('Notification' in window) || !registration) {
            renderPush(false, 'این مرورگر از اعلان تحت وب پشتیبانی نمی‌کند.');
            return;
        }
        const config = await pwaApi('config');
        if (!config.push_enabled || !config.vapid_public_key) {
            renderPush(false, 'اعلان گوشی هنوز از سمت سرور فعال نشده است.');
            return;
        }
        enable.dataset.vapidKey = config.vapid_public_key;
        const current = await registration.pushManager.getSubscription();
        const sameOwner = stored(pushOwnerKey) === userId;
        if (Notification.permission === 'denied') {
            renderPush(false, 'مجوز اعلان در تنظیمات مرورگر مسدود شده است.');
            return;
        }
        if (current && sameOwner) {
            await pwaApi('subscriptions', { method: 'POST', body: JSON.stringify(subscriptionPayload(current)) });
            renderPush(true, 'اعلان این دستگاه فعال است.');
            return;
        }
        renderPush(false, ios && !standalone() ? 'در iPhone ابتدا TaskFlow را روی صفحه اصلی نصب کنید.' : 'برای دریافت تغییرات مهم وظایف، اعلان این دستگاه را فعال کنید.');
    }

    enable?.addEventListener('click', async () => {
        enable.disabled = true;
        try {
            if (ios && !standalone()) {
                showInstall(false);
                throw new Error('در iPhone ابتدا برنامه را به Home Screen اضافه و از همان آیکن باز کنید.');
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('مجوز اعلان داده نشد؛ می‌توانید آن را از تنظیمات مرورگر فعال کنید.');
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: applicationKey(enable.dataset.vapidKey) });
            }
            await pwaApi('subscriptions', { method: 'POST', body: JSON.stringify(subscriptionPayload(subscription)) });
            store(pushOwnerKey, userId);
            renderPush(true, 'اعلان این دستگاه فعال شد. برای اطمینان یک اعلان آزمایشی بفرستید.');
        } catch (error) {
            setPushStatus(error.message, 'error');
        } finally { enable.disabled = false; }
    });
    disable?.addEventListener('click', async () => {
        disable.disabled = true;
        try {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await pwaApi('subscriptions/remove', { method: 'POST', body: JSON.stringify({ endpoint: subscription.endpoint }) });
                await subscription.unsubscribe();
            }
            removeStored(pushOwnerKey);
            renderPush(false, 'اعلان این دستگاه غیرفعال شد؛ اعلان‌های داخل سامانه همچنان باقی می‌مانند.');
        } catch (error) { setPushStatus(error.message, 'error'); }
        finally { disable.disabled = false; }
    });
    test?.addEventListener('click', async () => {
        test.disabled = true;
        try {
            await pwaApi('test', { method: 'POST', body: '{}' });
            setPushStatus('اعلان آزمایشی در صف ارسال قرار گرفت.', 'success');
        } catch (error) { setPushStatus(error.message, 'error'); }
        finally { test.disabled = false; }
    });

    window.TaskFlowPwa = {
        pushEndpoint: async () => (await registration?.pushManager?.getSubscription())?.endpoint || '',
        afterLogout: async () => {
            const subscription = await registration?.pushManager?.getSubscription();
            if (subscription) await subscription.unsubscribe().catch(() => false);
            removeStored(pushOwnerKey);
        },
    };

    scheduleInstallIntro();
})();
