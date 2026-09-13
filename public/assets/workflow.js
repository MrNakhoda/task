(() => {
    'use strict';

    const root = document.querySelector('[data-workspace]');
    if (!root) return;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = { reference: {}, templates: [], orders: [], tasks: [], notifications: [] };
    const labels = {
        draft: 'پیش‌نویس', active: 'فعال', completed: 'تکمیل‌شده', open: 'باز',
        in_progress: 'در حال انجام', pending: 'منتظر', disabled: 'غیرفعال', cancelled: 'لغوشده'
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
    const apiUrl = path => new URL(`api/v1/workflow/${path.replace(/^\//, '')}`, document.baseURI).toString();

    async function api(path, options = {}) {
        const config = { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', ...(options.headers || {}) } };
        if (config.method && config.method !== 'GET') config.headers['X-CSRF-Token'] = csrf();
        if (config.body && !(config.body instanceof FormData)) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(config.body);
        }
        const response = await fetch(apiUrl(path), config);
        const payload = await response.json().catch(() => ({ ok: false, error: 'پاسخ سرور معتبر نبود.' }));
        if (!response.ok || !payload.ok) throw new Error(payload.error || 'عملیات انجام نشد.');
        return payload;
    }

    function message(text, type = 'success') {
        const box = root.querySelector('[data-message]');
        box.textContent = text;
        box.className = `workspace-alert is-${type}`;
        box.hidden = false;
        window.clearTimeout(message.timer);
        message.timer = window.setTimeout(() => { box.hidden = true; }, 5000);
    }

    function formData(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            if (data[key] !== undefined) data[key] = Array.isArray(data[key]) ? [...data[key], value] : [data[key], value];
            else data[key] = value;
        });
        form.querySelectorAll('select[multiple]').forEach(select => { data[select.name] = [...select.selectedOptions].map(option => option.value); });
        form.querySelectorAll('input[type="checkbox"]').forEach(input => { data[input.name] = input.checked; });
        return data;
    }

    function optionLabel(type, item) {
        if (type === 'priorities' || type === 'task_types') return item.name;
        if (type === 'users') return `${item.name} — ${item.email}`;
        if (type === 'projects') return item.code ? `${item.name} (${item.code})` : item.name;
        return item.name || item.display_name || item.key_name;
    }

    function fillOptions() {
        root.querySelectorAll('[data-options]').forEach(select => {
            const type = select.dataset.options;
            const current = select.value;
            const placeholders = [...select.options].filter(option => option.value === '').map(option => option.outerHTML).join('');
            select.innerHTML = placeholders + (state.reference[type] || []).map(item => `<option value="${Number(item.id)}">${escapeHtml(optionLabel(type, item))}</option>`).join('');
            if ([...select.options].some(option => option.value === current)) select.value = current;
        });
    }

    async function loadReference() {
        const payload = await api('reference');
        state.reference = payload.reference;
        fillOptions();
    }

    async function loadOverview() {
        const payload = await api('overview');
        Object.entries(payload.overview).forEach(([key, value]) => {
            const element = root.querySelector(`[data-metric="${key}"]`);
            if (element) element.textContent = Number(value).toLocaleString('fa-IR');
        });
    }

    function renderTasks() {
        const list = root.querySelector('[data-task-list]');
        if (!state.tasks.length) {
            list.innerHTML = '<div class="empty-state">وظیفه‌ای با این فیلتر پیدا نشد.</div>';
            return;
        }
        list.innerHTML = state.tasks.map(task => `
            <article class="task-card status-${escapeHtml(task.status)}">
                <div class="task-card-top"><span class="type-dot" style="--task-color:${escapeHtml(task.task_type_color)}"></span><span>${escapeHtml(task.task_type_name)}</span><span class="status-badge">${escapeHtml(labels[task.status] || task.status)}</span></div>
                <h3>${escapeHtml(task.title)}</h3>
                <p>${escapeHtml(task.order_number)} · ${escapeHtml(task.order_title)}</p>
                <div class="progress"><span style="width:${Math.max(0, Math.min(100, Number(task.progress_percent)))}%"></span></div>
                <small>مسئولان: ${escapeHtml(task.assignee_names || 'تعیین نشده')}</small>
                <div class="task-actions">
                    ${task.status === 'open' ? `<button class="btn btn-secondary btn-small" data-task-start="${Number(task.id)}">شروع</button>` : ''}
                    ${['open', 'in_progress'].includes(task.status) ? `<button class="btn btn-secondary btn-small" data-task-report="${Number(task.id)}">ثبت گزارش</button><button class="btn btn-small" data-task-complete="${Number(task.id)}">تکمیل</button>` : ''}
                </div>
            </article>`).join('');
    }

    async function loadTasks(params = '') {
        const payload = await api(`tasks${params ? `?${params}` : ''}`);
        state.tasks = payload.tasks;
        renderTasks();
    }

    function renderOrders() {
        const list = root.querySelector('[data-order-list]');
        if (!state.orders.length) {
            list.innerHTML = '<div class="empty-state">سفارشی با این فیلتر پیدا نشد.</div>';
            return;
        }
        list.innerHTML = state.orders.map(order => `
            <article class="order-card">
                <div class="order-main"><div><div class="order-meta"><span class="priority-dot" style="--priority-color:${escapeHtml(order.priority_color || '#94a3b8')}"></span>${escapeHtml(order.order_number)} · ${escapeHtml(order.priority_name || 'بدون اولویت')}</div><h3>${escapeHtml(order.title)}</h3><p>${escapeHtml(order.customer_names || 'بدون مشتری')} ${order.project_name ? `· ${escapeHtml(order.project_name)}` : ''}</p></div><span class="status-badge">${escapeHtml(labels[order.status] || order.status)}</span></div>
                <div class="progress-row"><div class="progress"><span style="width:${Number(order.progress_percent)}%"></span></div><strong>${Number(order.progress_percent).toLocaleString('fa-IR')}٪</strong></div>
                <div class="task-actions">${order.status === 'draft' ? `<button class="btn btn-small" data-order-activate="${Number(order.id)}">فعال‌سازی</button>` : ''}<button class="btn btn-secondary btn-small" data-order-detail="${Number(order.id)}">جزئیات</button></div>
                <div class="order-detail" data-order-detail-box="${Number(order.id)}" hidden></div>
            </article>`).join('');
    }

    async function loadOrders(params = '') {
        const payload = await api(`orders${params ? `?${params}` : ''}`);
        state.orders = payload.orders;
        renderOrders();
    }

    function renderOrderDetail(order) {
        const stepOptions = (order.steps || []).filter(step => step.status !== 'disabled').map(step => `<option value="${Number(step.id)}">${escapeHtml(step.name)}</option>`).join('');
        return `<div class="detail-grid">
            <section><h4>مراحل</h4><ol class="step-list">${(order.steps || []).map(step => `<li class="step-${escapeHtml(step.status)}"><span>${escapeHtml(step.name)}</span><b>${escapeHtml(labels[step.status] || step.status)}</b>${step.status !== 'completed' && step.status !== 'disabled' ? `<button type="button" data-step-disable="${Number(step.id)}">غیرفعال</button>` : ''}</li>`).join('')}</ol></section>
            <section><h4>تاریخچه تغییرناپذیر</h4><div class="history-list">${(order.history || []).slice(0, 12).map(item => `<p><b>${escapeHtml(item.message)}</b><small>${escapeHtml(item.actor_name || 'سیستم')} · ${escapeHtml(item.created_at)}</small></p>`).join('') || '<p>رویدادی ثبت نشده.</p>'}</div></section>
        </div>
        <form class="inline-form" data-add-order-step="${Number(order.id)}"><input name="name" required placeholder="نام مرحله جدید"><select name="task_type_id" required>${(state.reference.task_types || []).map(item => `<option value="${Number(item.id)}">${escapeHtml(item.name)}</option>`).join('')}</select><select name="placement"><option value="end">انتهای جریان</option><option value="before">قبل از مرحله</option><option value="after">بعد از مرحله</option></select><select name="anchor_step_id"><option value="">مرحله مرجع</option>${stepOptions}</select><input name="dependency_ids" placeholder="شناسه پیش‌نیازها"><select name="user_ids" multiple>${(state.reference.users || []).map(item => `<option value="${Number(item.id)}">${escapeHtml(item.name)}</option>`).join('')}</select><button class="btn btn-small">افزودن مرحله</button></form>
        <form class="inline-form" data-upload-order="${Number(order.id)}" enctype="multipart/form-data"><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required><input name="caption" placeholder="عنوان تصویر"><button class="btn btn-secondary btn-small">پیوست تصویر</button></form>
        ${(order.attachments || []).length ? `<div class="attachment-strip">${order.attachments.map(file => `<a href="${escapeHtml(new URL(file.path, document.baseURI).toString())}" target="_blank" rel="noopener"><img src="${escapeHtml(new URL(file.path, document.baseURI).toString())}" alt="${escapeHtml(file.caption || file.original_name)}"></a>`).join('')}</div>` : ''}`;
    }

    function renderTemplates() {
        const list = root.querySelector('[data-template-list]');
        list.innerHTML = state.templates.map(template => `<article class="template-card"><div><h3>${escapeHtml(template.name)}</h3><p>${escapeHtml(template.description || '')}</p></div><span>${Number(template.step_count).toLocaleString('fa-IR')} مرحله · نسخه ${Number(template.version).toLocaleString('fa-IR')}</span><ol>${(template.steps || []).map(step => `<li><b>#${Number(step.id)} ${escapeHtml(step.name)}</b><small>${escapeHtml(step.task_type_name)}${step.dependencies.length ? ` · بعد از ${step.dependencies.map(Number).join('، ')}` : ' · بدون پیش‌نیاز'}</small></li>`).join('') || '<li>هنوز مرحله‌ای ندارد.</li>'}</ol></article>`).join('') || '<div class="empty-state">اول یک قالب گردش کار بسازید.</div>';
    }

    async function loadTemplates() {
        const payload = await api('templates');
        state.templates = payload.templates;
        renderTemplates();
    }

    function renderNotifications() {
        const list = root.querySelector('[data-notification-list]');
        list.innerHTML = state.notifications.map(item => `<article class="notification ${item.read_at ? '' : 'is-unread'}"><div><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.body || '')}</p></div><time>${escapeHtml(item.created_at)}</time></article>`).join('') || '<div class="empty-state">اعلانی ندارید.</div>';
    }

    async function loadNotifications() {
        const payload = await api('notifications');
        state.notifications = payload.notifications;
        renderNotifications();
    }

    async function refreshAll() {
        await loadReference();
        await Promise.all([loadOverview(), loadTasks(), loadOrders(), loadTemplates(), loadNotifications()]);
    }

    root.addEventListener('click', async event => {
        const tab = event.target.closest('[data-tab]');
        if (tab) {
            root.querySelectorAll('[data-tab]').forEach(item => item.classList.toggle('is-active', item === tab));
            root.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.panel === tab.dataset.tab));
            return;
        }
        const toggle = event.target.closest('[data-toggle-form]');
        if (toggle) {
            const form = root.querySelector(`[data-form="${toggle.dataset.toggleForm}"]`);
            form.hidden = !form.hidden;
            return;
        }
        const action = event.target.closest('button[data-task-start],button[data-task-report],button[data-task-complete],button[data-order-activate],button[data-order-detail],button[data-step-disable],[data-refresh],[data-read-notifications]');
        if (!action) return;
        action.disabled = true;
        try {
            if (action.matches('[data-refresh]')) await refreshAll();
            else if (action.matches('[data-read-notifications]')) { await api('notifications/read', { method: 'POST' }); await Promise.all([loadNotifications(), loadOverview()]); }
            else if (action.dataset.taskStart) { await api(`tasks/${action.dataset.taskStart}/start`, { method: 'POST' }); await loadTasks(); }
            else if (action.dataset.taskReport) {
                const report = window.prompt('گزارش کار را بنویسید:');
                if (report) await api(`tasks/${action.dataset.taskReport}/reports`, { method: 'POST', body: { report_text: report } });
            } else if (action.dataset.taskComplete) {
                const report = window.prompt('گزارش نهایی (اختیاری):') || '';
                await api(`tasks/${action.dataset.taskComplete}/complete`, { method: 'POST', body: { report_text: report } });
                await Promise.all([loadTasks(), loadOrders(), loadOverview(), loadNotifications()]);
            } else if (action.dataset.orderActivate) {
                if (window.confirm('سفارش فعال و وظایف مراحل آماده ساخته شوند؟')) {
                    await api(`orders/${action.dataset.orderActivate}/activate`, { method: 'POST' });
                    await Promise.all([loadOrders(), loadTasks(), loadOverview(), loadNotifications()]);
                }
            } else if (action.dataset.orderDetail) {
                const box = root.querySelector(`[data-order-detail-box="${action.dataset.orderDetail}"]`);
                if (!box.hidden) box.hidden = true;
                else { box.innerHTML = '<div class="loading">دریافت جزئیات…</div>'; box.hidden = false; const payload = await api(`orders/${action.dataset.orderDetail}`); box.innerHTML = renderOrderDetail(payload.order); }
            } else if (action.dataset.stepDisable && window.confirm('این مرحله غیرفعال شود؟ سابقه حذف نمی‌شود.')) {
                await api(`order-steps/${action.dataset.stepDisable}/disable`, { method: 'POST' });
                const orderCard = action.closest('.order-card');
                orderCard?.querySelector('[data-order-detail]')?.click();
                await loadOrders();
            }
            message('عملیات با موفقیت انجام شد.');
        } catch (error) { message(error.message, 'error'); }
        finally { action.disabled = false; }
    });

    root.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            if (form.matches('[data-filter-tasks]')) await loadTasks(new URLSearchParams(formData(form)).toString());
            else if (form.matches('[data-filter-orders]')) await loadOrders(new URLSearchParams(formData(form)).toString());
            else if (form.matches('[data-create-order]')) {
                const data = formData(form);
                data.customers = data.customer_id ? [{ customer_id: Number(data.customer_id), weight: data.weight || null, weight_unit: 'gram' }] : [];
                data.workflow_template_id = Number(data.workflow_template_id);
                data.details = { description: data.details || '' };
                delete data.customer_id; delete data.weight;
                await api('orders', { method: 'POST', body: data }); form.reset(); await Promise.all([loadOrders(), loadOverview()]);
            } else if (form.matches('[data-create-template-step]')) {
                const data = formData(form); const id = data.template_id; delete data.template_id;
                data.dependency_ids = String(data.dependency_ids || '').split(',').filter(Boolean).map(Number);
                await api(`templates/${id}/steps`, { method: 'POST', body: data }); form.reset(); await Promise.all([loadTemplates(), loadReference()]);
            } else if (form.matches('[data-simple-create]')) {
                await api(form.dataset.simpleCreate, { method: 'POST', body: formData(form) }); form.reset(); await loadReference(); if (form.dataset.simpleCreate === 'templates') await loadTemplates();
            } else if (form.matches('[data-create-user]')) {
                await api('users', { method: 'POST', body: formData(form) }); form.reset(); await loadReference();
            } else if (form.matches('[data-create-role]')) {
                const data = formData(form); data.permissions = String(data.permissions || '').split(',').map(value => value.trim()).filter(Boolean);
                await api('roles', { method: 'POST', body: data }); form.reset(); await loadReference();
            } else if (form.matches('[data-add-member]')) {
                const data = formData(form); const teamId = data.team_id; delete data.team_id;
                await api(`teams/${teamId}/members`, { method: 'POST', body: data }); form.reset();
            } else if (form.matches('[data-add-order-step]')) {
                const data = formData(form); data.dependency_ids = String(data.dependency_ids || '').split(',').filter(Boolean).map(Number);
                await api(`orders/${form.dataset.addOrderStep}/steps`, { method: 'POST', body: data });
                const payload = await api(`orders/${form.dataset.addOrderStep}`); form.closest('.order-detail').innerHTML = renderOrderDetail(payload.order); await Promise.all([loadOrders(), loadTasks()]);
            } else if (form.matches('[data-upload-order]')) {
                await api(`orders/${form.dataset.uploadOrder}/attachments`, { method: 'POST', body: new FormData(form) });
                const payload = await api(`orders/${form.dataset.uploadOrder}`); form.closest('.order-detail').innerHTML = renderOrderDetail(payload.order);
            }
            message('ذخیره شد.');
        } catch (error) { message(error.message, 'error'); }
        finally { if (submit) submit.disabled = false; }
    });

    refreshAll().catch(error => message(error.message, 'error'));
})();
