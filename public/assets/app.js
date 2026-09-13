(() => {
    'use strict';

    const csrfMeta = () => document.querySelector('meta[name="csrf-token"]');
    const csrfToken = () => csrfMeta()?.getAttribute('content') || '';
    const setCsrfToken = (token) => {
        if (typeof token === 'string' && token !== '' && csrfMeta()) csrfMeta().setAttribute('content', token);
    };

    const dialog = document.querySelector('[data-app-dialog]');
    const dialogForm = dialog?.querySelector('[data-app-dialog-form]');
    const dialogTitle = dialog?.querySelector('[data-app-dialog-title]');
    const dialogMessage = dialog?.querySelector('[data-app-dialog-message]');
    const dialogIcon = dialog?.querySelector('[data-app-dialog-icon]');
    const dialogField = dialog?.querySelector('[data-app-dialog-field]');
    const dialogLabel = dialog?.querySelector('[data-app-dialog-label]');
    const dialogInput = dialog?.querySelector('[data-app-dialog-input]');
    const dialogHelp = dialog?.querySelector('[data-app-dialog-help]');
    const dialogSubmit = dialog?.querySelector('[data-app-dialog-submit]');
    const dialogFooterCancel = dialog?.querySelector('footer [data-app-dialog-cancel]');
    let pendingDialog = null;

    const settleDialog = (value) => {
        if (!pendingDialog) return;
        const resolve = pendingDialog.resolve;
        pendingDialog = null;
        if (dialog?.open) dialog.close();
        resolve(value);
    };

    const dismissDialog = () => {
        if (!pendingDialog) return;
        settleDialog(pendingDialog.mode === 'prompt' ? null : false);
    };

    const showDialog = (config) => new Promise((resolve) => {
        if (!dialog || !dialogForm || !dialogTitle || !dialogMessage || !dialogIcon || !dialogField || !dialogLabel || !dialogInput || !dialogHelp || !dialogSubmit || !dialogFooterCancel) {
            resolve(config.mode === 'prompt' ? null : false);
            return;
        }
        if (pendingDialog) dismissDialog();
        const mode = config.mode || 'confirm';
        pendingDialog = { resolve, mode, required: Boolean(config.required), expectedValue: config.expectedValue ?? null, invalidText: config.invalidText || 'مقدار واردشده صحیح نیست.' };
        dialog.classList.toggle('is-danger', config.tone === 'danger');
        dialog.classList.toggle('is-success', config.tone === 'success');
        dialogTitle.textContent = config.title || (mode === 'alert' ? 'پیام سیستم' : 'تأیید عملیات');
        dialogMessage.textContent = config.message || '';
        dialogIcon.textContent = config.icon || (config.tone === 'danger' ? '!' : (config.tone === 'success' ? '✓' : '?'));
        dialogField.hidden = mode !== 'prompt';
        dialogLabel.textContent = config.inputLabel || 'توضیحات';
        dialogInput.value = config.value || '';
        dialogInput.placeholder = config.placeholder || '';
        dialogInput.rows = config.multiline === false ? 2 : 5;
        dialogHelp.hidden = true;
        dialogHelp.textContent = '';
        dialogSubmit.textContent = config.confirmText || (mode === 'alert' ? 'متوجه شدم' : 'تأیید');
        dialogFooterCancel.hidden = mode === 'alert';
        dialog.showModal();
        requestAnimationFrame(() => (mode === 'prompt' ? dialogInput : dialogSubmit).focus());
    });

    dialogForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!pendingDialog) return;
        if (pendingDialog.mode !== 'prompt') {
            settleDialog(true);
            return;
        }
        const value = dialogInput.value.trim();
        if (pendingDialog.required && value === '') {
            dialogHelp.textContent = 'تکمیل این فیلد الزامی است.';
            dialogHelp.hidden = false;
            dialogInput.focus();
            return;
        }
        if (pendingDialog.expectedValue !== null && value !== pendingDialog.expectedValue) {
            dialogHelp.textContent = pendingDialog.invalidText;
            dialogHelp.hidden = false;
            dialogInput.focus();
            return;
        }
        settleDialog(value);
    });
    dialog?.querySelectorAll('[data-app-dialog-cancel]').forEach((button) => button.addEventListener('click', dismissDialog));
    dialog?.addEventListener('cancel', (event) => { event.preventDefault(); dismissDialog(); });
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dismissDialog(); });
    dialogInput?.addEventListener('input', () => { if (dialogHelp) dialogHelp.hidden = true; });

    window.AppModal = {
        alert: (message, options = {}) => showDialog({ ...options, mode: 'alert', message }),
        confirm: (message, options = {}) => showDialog({ ...options, mode: 'confirm', message }),
        prompt: (message, options = {}) => showDialog({ ...options, mode: 'prompt', message }),
    };

    const api = async (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
        if ((options.method || 'GET').toUpperCase() !== 'GET') headers.set('X-CSRF-Token', csrfToken());

        const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
        let data = {};
        try { data = await response.json(); } catch { data = { ok: false, error: 'پاسخ سرور معتبر نبود.' }; }
        if (data.csrf_token) setCsrfToken(data.csrf_token);
        if (!response.ok) throw new Error(data.error || 'انجام درخواست ناموفق بود.');
        return data;
    };

    const formPayload = (form) => {
        const result = {};
        new FormData(form).forEach((value, key) => {
            if (!(value instanceof File)) result[key] = value;
        });
        return result;
    };

    document.querySelectorAll('[data-api-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = form.parentElement?.querySelector('[data-form-error]');
            const submit = form.querySelector('[type="submit"]');
            if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
            if (submit) submit.disabled = true;
            try {
                await api(form.dataset.endpoint, { method: 'POST', body: JSON.stringify(formPayload(form)) });
                window.location.assign(form.dataset.redirect || '/dashboard');
            } catch (error) {
                if (errorBox) { errorBox.textContent = error.message; errorBox.hidden = false; }
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-logout]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await api(new URL('api/v1/auth/logout', document.baseURI).toString(), { method: 'POST', body: '{}' });
                window.location.assign(new URL('.', document.baseURI).toString());
            } catch (error) {
                await window.AppModal.alert(error.message, { title: 'خروج انجام نشد', tone: 'danger', icon: '!' });
                button.disabled = false;
            }
        });
    });

    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    toggle?.addEventListener('click', () => {
        const open = nav?.classList.toggle('is-open') || false;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    const latinDigits = (value) => String(value ?? '')
        .replace(/[۰-۹]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
        .replace(/[٠-٩]/g, (digit) => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit));

    document.querySelectorAll('[data-money]').forEach((input) => {
        input.addEventListener('input', () => {
            const digits = latinDigits(input.value).replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '');
            input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
    });

    document.querySelectorAll('input[type="file"][accept*="image"]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const max = Number(input.dataset.maxBytes || 5_000_000);
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            const error = !file ? '' : file.size > max ? 'حجم تصویر بیشتر از حد مجاز است.' : !allowed.includes(file.type) ? 'فقط JPG، PNG و WEBP مجاز است.' : '';
            input.setCustomValidity(error);
            if (error) input.reportValidity();
        });
    });
})();
