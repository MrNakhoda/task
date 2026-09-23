(() => {
    'use strict';

    const root = document.querySelector('[data-workspace]');
    if (!root) return;

    const state = { reference: {}, capabilities: {}, projects: [], tasks: [], archivedProjects: [], archivedTasks: [], templates: [], notifications: [], selectedTemplate: null, selectedProject: null, selectedTask: null, taskQuery: '' };
    const labels = { draft: 'پیش‌نویس', active: 'فعال', completed: 'تکمیل‌شده', open: 'آماده شروع', in_progress: 'در حال انجام', pending: 'منتظر پیش‌نیاز', disabled: 'غیرفعال', cancelled: 'لغوشده' };
    const viewTitles = { dashboard: 'نمای کلی', projects: 'پروژه‌ها', 'project-detail': 'جزئیات پروژه', tasks: 'وظایف', archives: 'آرشیوها', workflows: 'قالب‌های گردش‌کار', 'task-types': 'انواع وظیفه', customers: 'مشتری‌ها', teams: 'تیم‌ها', users: 'کاربران و نقش‌ها', guide: 'راهنمای سیستم', notifications: 'اعلان‌ها' };
    const permissionLabels = { 'system.admin': 'مدیریت کامل سیستم', 'users.manage': 'مدیریت کاربران', 'roles.manage': 'مدیریت نقش‌ها و دسترسی‌ها', 'templates.manage': 'مدیریت قالب و نوع وظیفه', 'orders.manage': 'مدیریت همه پروژه‌ها', 'tasks.manage': 'مدیریت همه تسک‌ها', 'tasks.work': 'انجام تسک‌های تخصیص‌یافته', 'teams.manage': 'مدیریت تیم‌ها', 'orders.read': 'مشاهده پروژه‌های عضو', 'catalog.manage': 'مدیریت کاتالوگ', 'crm.manage': 'مدیریت مشتری‌ها', 'hr.manage': 'مدیریت منابع انسانی', 'workflow.admin': 'مدیریت کامل گردش‌کار' };
    const permissionGroups = [
        ['مدیریت سامانه', ['system.admin', 'workflow.admin']],
        ['افراد و ساختار', ['users.manage', 'roles.manage', 'teams.manage']],
        ['پروژه و وظایف', ['orders.read', 'orders.manage', 'tasks.work', 'tasks.manage', 'templates.manage']],
        ['سایر بخش‌ها', ['catalog.manage', 'crm.manage', 'hr.manage']],
    ];
    const taskAdvancedFilterLabels = { task_type_id: 'نوع وظیفه', order_id: 'پروژه', customer: 'مشتری', assignee_user_id: 'مسئول', assignee_team_id: 'تیم' };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
    const endpoint = path => new URL(`api/v1/workflow/${path.replace(/^\//, '')}`, document.baseURI).toString();
    const fileUrl = path => new URL(String(path || '').replace(/^\//, ''), document.baseURI).toString();
    const numericIds = value => String(value || '').split(',').filter(Boolean).map(Number);
    const dateTimeValue = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';

    async function api(path, options = {}) {
        const config = { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', ...(options.headers || {}) } };
        if (config.method && config.method !== 'GET') config.headers['X-CSRF-Token'] = csrf();
        if (config.body && !(config.body instanceof FormData)) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(config.body);
        }
        const response = await fetch(endpoint(path), config);
        const payload = await response.json().catch(() => ({ ok: false, error: 'پاسخ سرور معتبر نیست.' }));
        if (response.status === 401) {
            window.location.assign(new URL('login', document.baseURI));
            throw new Error('نشست شما پایان یافته است؛ دوباره وارد شوید.');
        }
        if (!response.ok || !payload.ok) throw new Error(payload.error || 'عملیات انجام نشد.');
        return payload;
    }

    function toast(text, type = 'success') {
        const box = root.querySelector('[data-message]');
        box.textContent = text;
        box.className = `tf-toast is-${type}`;
        box.hidden = false;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => { box.hidden = true; }, 4500);
    }

    function values(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            data[key] = data[key] === undefined ? value : (Array.isArray(data[key]) ? [...data[key], value] : [data[key], value]);
        });
        form.querySelectorAll('select[multiple]').forEach(select => { data[select.name] = [...select.selectedOptions].map(option => select.dataset.options === 'permissions' ? option.value : Number(option.value)); });
        form.querySelectorAll('input[type="checkbox"][name]').forEach(input => { data[input.name] = input.checked; });
        if (form.querySelector('[data-role-options]')) data.role_ids = [...form.querySelectorAll('[data-role-choice]:checked')].map(input => Number(input.value));
        if (form.querySelector('[data-permission-options]')) data.permissions = [...form.querySelectorAll('[data-permission-choice]:checked')].map(input => input.value);
        return data;
    }

    function setForm(form, data) {
        Object.entries(data).forEach(([name, value]) => {
            const field = form.elements[name];
            if (!field) return;
            if (field instanceof RadioNodeList) return;
            if (field.type === 'checkbox') field.checked = Boolean(Number(value)) || value === true;
            else if (field.multiple) {
                const selected = (Array.isArray(value) ? value : numericIds(value)).map(String);
                [...field.options].forEach(option => { option.selected = selected.includes(option.value); });
            } else field.value = value ?? '';
        });
    }

    function optionTitle(type, item) {
        if (type === 'users') return `${item.name} — ${item.email}`;
        if (type === 'roles') return item.display_name;
        if (type === 'permissions') return permissionLabels[item.key_name] || item.display_name || item.key_name;
        return item.name || item.key_name;
    }

    function fillOptions(scope = root) {
        scope.querySelectorAll('[data-options]').forEach(select => {
            const type = select.dataset.options;
            const selected = [...select.selectedOptions].map(option => option.value);
            const blank = [...select.options].filter(option => option.value === '').map(option => option.outerHTML).join('');
            let items = state.reference[type] || [];
            if (type === 'users') items = items.filter(item => item.status === 'active');
            if (['task_types', 'templates', 'teams', 'roles'].includes(type)) items = items.filter(item => Number(item.is_active ?? 1) === 1);
            select.innerHTML = blank + items.map(item => `<option value="${type === 'permissions' ? esc(item.key_name) : Number(item.id)}">${esc(optionTitle(type, item))}</option>`).join('');
            [...select.options].forEach(option => { option.selected = selected.includes(option.value); });
        });
    }

    function serializeTaskFilters(form) {
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            const normalized = String(value).trim();
            if (normalized !== '') params.set(key, normalized);
        });
        return params.toString();
    }

    function restoreTaskFilterForm() {
        const form = root.querySelector('[data-task-filters]');
        if (!form) return;
        const params = new URLSearchParams(state.taskQuery);
        form.reset();
        ['search', 'scope', 'status', ...Object.keys(taskAdvancedFilterLabels)].forEach(name => {
            const field = form.elements[name];
            if (field) field.value = params.get(name) || '';
        });
        syncTaskProjectFilters(false);
    }

    function syncTaskProjectFilters(clearHidden = false) {
        const form = root.querySelector('[data-task-filters]');
        if (!form) return;
        const standalone = form.elements.scope?.value === 'standalone';
        form.querySelectorAll('[data-project-task-filter]').forEach(label => {
            label.hidden = standalone;
            if (standalone && clearHidden) label.querySelectorAll('input,select').forEach(field => { field.value = ''; });
        });
    }

    function taskFilterValue(form, name, value) {
        const field = form.elements[name];
        if (field instanceof HTMLSelectElement) return field.selectedOptions[0]?.textContent?.trim() || value;
        return value;
    }

    function renderTaskFilterUi() {
        const form = root.querySelector('[data-task-filters]');
        if (!form) return;
        syncTaskProjectFilters(false);
        const params = new URLSearchParams(state.taskQuery);
        const active = Object.entries(taskAdvancedFilterLabels).flatMap(([name, label]) => {
            const value = params.get(name) || '';
            return value ? [[name, label, taskFilterValue(form, name, value)]] : [];
        });
        const count = form.querySelector('[data-task-filter-count]');
        const toggle = form.querySelector('[data-toggle-task-filters]');
        const chips = form.querySelector('[data-task-filter-chips]');
        count.textContent = active.length.toLocaleString('fa-IR');
        count.hidden = active.length === 0;
        toggle.classList.toggle('has-active-filters', active.length > 0);
        chips.hidden = active.length === 0;
        chips.innerHTML = active.length ? `<span>فیلترهای فعال:</span>${active.map(([name, label, value]) => `<button class="tf-task-filter-chip" type="button" data-remove-task-filter="${esc(name)}"><span>${esc(label)}: ${esc(value)}</span><i aria-hidden="true">×</i></button>`).join('')}<button class="tf-task-filter-clear" type="button" data-reset-task-filters>پاک‌کردن همه</button>` : '';
    }

    function openTaskFilters() {
        const form = root.querySelector('[data-task-filters]');
        const panel = form?.querySelector('[data-task-filter-panel]');
        if (!form || !panel) return;
        panel.hidden = false;
        form.classList.add('is-open');
        form.querySelector('[data-task-filter-backdrop]').hidden = false;
        form.querySelector('[data-toggle-task-filters]').setAttribute('aria-expanded', 'true');
        document.body.classList.add('task-filters-open');
        requestAnimationFrame(() => panel.querySelector('select,input')?.focus());
    }

    function closeTaskFilters(restore = true) {
        const form = root.querySelector('[data-task-filters]');
        const panel = form?.querySelector('[data-task-filter-panel]');
        if (!form || !panel || panel.hidden) return;
        if (restore) restoreTaskFilterForm();
        panel.hidden = true;
        form.classList.remove('is-open');
        form.querySelector('[data-task-filter-backdrop]').hidden = true;
        const toggle = form.querySelector('[data-toggle-task-filters]');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('task-filters-open');
        renderTaskFilterUi();
        if (restore) toggle.focus();
    }

    function permissionKeys(role) {
        return String(role?.permission_keys || '').split(',').filter(Boolean);
    }

    function renderEffectivePermissions(form) {
        const target = form.querySelector('[data-effective-permissions]');
        if (!target) return;
        if (!state.capabilities.roles_manage) {
            target.innerHTML = '<small>نمایش جزئیات دسترسی‌ها به مجوز مدیریت نقش‌ها نیاز دارد.</small>';
            return;
        }
        const selectedRoleIds = [...form.querySelectorAll('[data-role-choice]:checked')].map(input => Number(input.value));
        const selectedRoles = (state.reference.roles || []).filter(role => selectedRoleIds.includes(Number(role.id)));
        const keys = [...new Set(selectedRoles.flatMap(permissionKeys))];
        if (keys.includes('system.admin')) {
            target.innerHTML = '<strong>دسترسی مؤثر</strong><div class="tf-permission-chips"><span class="is-critical">مدیریت کامل سیستم؛ شامل همه دسترسی‌ها</span></div>';
            return;
        }
        target.innerHTML = `<strong>دسترسی مؤثر</strong><div class="tf-permission-chips">${keys.map(key => `<span>${esc(permissionLabels[key] || key)}</span>`).join('') || '<em>نقشی انتخاب نشده است.</em>'}</div>`;
    }

    function renderRoleOptions(form, selectedIds = []) {
        const target = form.querySelector('[data-role-options]');
        if (!target) return;
        const selected = selectedIds.map(Number);
        const roles = (state.reference.roles || []).filter(role => Number(role.is_active ?? 1) === 1);
        target.innerHTML = roles.map(role => `<label class="tf-choice-option"><input type="checkbox" value="${Number(role.id)}" data-role-choice ${selected.includes(Number(role.id)) ? 'checked' : ''}><span><strong>${esc(role.display_name)}</strong><small>${esc(role.key_name)}</small></span></label>`).join('') || '<p class="tf-muted">نقش فعالی وجود ندارد.</p>';
        renderEffectivePermissions(form);
    }

    function renderPermissionOptions(form, selectedKeys = [], lockSystemAdmin = false) {
        const target = form.querySelector('[data-permission-options]');
        if (!target) return;
        const selected = new Set(selectedKeys);
        const permissions = state.reference.permissions || [];
        const known = new Set(permissionGroups.flatMap(([, keys]) => keys));
        const groups = [...permissionGroups, ['سایر دسترسی‌های ثبت‌شده', permissions.map(item => item.key_name).filter(key => !known.has(key))]];
        target.innerHTML = groups.map(([title, keys]) => {
            const items = keys.map(key => permissions.find(item => item.key_name === key)).filter(Boolean);
            if (!items.length) return '';
            return `<section><h3>${esc(title)}</h3>${items.map(item => { const locked = lockSystemAdmin && item.key_name === 'system.admin'; return `<label class="tf-choice-option"><input type="checkbox" value="${esc(item.key_name)}" data-permission-choice ${(selected.has(item.key_name) || locked) ? 'checked' : ''} ${locked ? 'disabled' : ''}><span><strong>${esc(permissionLabels[item.key_name] || item.display_name || item.key_name)}</strong><small>${esc(item.key_name)}</small></span></label>`; }).join('')}</section>`;
        }).join('');
    }

    function hasAnyCapability(value = '') {
        return String(value).split(',').map(item => item.trim()).filter(Boolean).some(key => Boolean(state.capabilities[key]));
    }

    function canView(view) {
        const section = root.querySelector(`[data-view="${view}"]`);
        return !section?.dataset.viewRequires || hasAnyCapability(section.dataset.viewRequires);
    }

    function applyCapabilities() {
        root.querySelectorAll('[data-requires]').forEach(element => { element.hidden = !state.capabilities[element.dataset.requires]; });
        root.querySelectorAll('[data-requires-any]').forEach(element => { element.hidden = !hasAnyCapability(element.dataset.requiresAny); });
        root.querySelectorAll('[data-view-requires]').forEach(element => { element.hidden = !hasAnyCapability(element.dataset.viewRequires); });
        root.querySelectorAll('[data-admin-only]').forEach(element => { element.hidden = !state.capabilities.system_admin; });
    }

    function go(view, silent = false) {
        if (!canView(view)) {
            if (!silent) toast('به این بخش دسترسی ندارید.', 'error');
            view = 'dashboard';
        }
        root.querySelectorAll('[data-view]').forEach(section => section.classList.toggle('is-active', section.dataset.view === view));
        root.querySelectorAll('[data-nav-view]').forEach(button => button.classList.toggle('is-active', button.dataset.navView === view));
        root.querySelectorAll('[data-current-view-title]').forEach(node => { node.textContent = viewTitles[view] || 'فضای کار'; });
        root.classList.remove('sidebar-open');
        if (view !== 'project-detail') location.hash = view;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function openModal(name) {
        const dialog = root.querySelector(`[data-modal="${name}"]`);
        if (dialog && !dialog.open) dialog.showModal();
    }

    function closeModal(element) {
        const dialog = element.closest('dialog');
        if (dialog?.open) dialog.close();
    }

    function resetModalForm(form) {
        form.reset();
        [...form.querySelectorAll('input[type="hidden"]')].forEach(input => { input.value = ''; });
    }

    function statusBadge(status) {
        return `<span class="tf-status status-${esc(status)}">${esc(labels[status] || status)}</span>`;
    }

    function progress(value) {
        const percent = Math.max(0, Math.min(100, Number(value || 0)));
        return `<div class="tf-progress"><span style="width:${percent}%"></span></div><b>${percent.toLocaleString('fa-IR')}٪</b>`;
    }

    function projectDescription(project) {
        try { return JSON.parse(project.details_json || '{}').description || ''; }
        catch (_) { return ''; }
    }

    async function loadReference() {
        const payload = await api('reference');
        state.reference = payload.reference;
        state.capabilities = payload.capabilities || {};
        fillOptions();
        applyCapabilities();
        renderManagement();
        renderSetup();
    }

    async function loadOverview() {
        const payload = await api('overview');
        Object.entries(payload.overview).forEach(([key, value]) => {
            const node = root.querySelector(`[data-metric="${key}"]`);
            if (node) node.textContent = Number(value).toLocaleString('fa-IR');
        });
        const count = Number(payload.overview.notifications_unread || 0);
        root.querySelectorAll('[data-nav-notifications]').forEach(badge => {
            badge.textContent = count ? count.toLocaleString('fa-IR') : '';
            badge.hidden = count === 0;
        });
        if ('setAppBadge' in navigator) {
            if (count > 0) navigator.setAppBadge(count).catch(() => null);
            else navigator.clearAppBadge().catch(() => null);
        }
    }

    async function loadProjects(query = '') {
        if (!state.capabilities.orders_read) {
            state.projects = [];
            renderProjects(); renderDashboard();
            return;
        }
        const payload = await api(`projects${query ? `?${query}` : ''}`);
        state.projects = payload.projects;
        root.querySelectorAll('[data-task-project-filter]').forEach(select => {
            const selected = select.value;
            select.innerHTML = '<option value="">همه پروژه‌ها</option>' + state.projects.map(project => `<option value="${Number(project.id)}">${esc(project.title)}</option>`).join('');
            select.value = selected;
        });
        renderProjects(); renderDashboard(); renderSetup();
    }

    async function loadTasks(query = null) {
        if (query !== null) state.taskQuery = query;
        const payload = await api(`tasks${state.taskQuery ? `?${state.taskQuery}` : ''}`);
        state.tasks = payload.tasks;
        renderTasks(); renderDashboard(); renderTaskFilterUi();
    }

    async function loadArchives() {
        const [projects, tasks] = await Promise.all([state.capabilities.orders_read ? api('projects?archived=only') : Promise.resolve({ projects: [] }), api('tasks?archived=only')]);
        state.archivedProjects = projects.projects || [];
        state.archivedTasks = tasks.tasks || [];
        renderArchives();
    }

    async function loadTemplates() {
        const payload = await api('templates');
        state.templates = payload.templates;
        renderTemplates(); renderSetup();
        if (state.selectedTemplate) selectTemplate(state.selectedTemplate.id);
    }

    async function loadNotifications() {
        const payload = await api('notifications');
        state.notifications = payload.notifications;
        renderNotifications();
    }

    function renderSetup() {
        const box = root.querySelector('[data-setup-checklist]');
        if (!box) return;
        const items = [];
        if (state.capabilities.users_manage || state.capabilities.roles_manage) items.push(['users', (state.reference.users || []).length > 1, 'کاربران', 'عضوهای شرکت را بساز']);
        if (state.capabilities.templates_manage) {
            items.push(['task-types', (state.reference.task_types || []).length > 0, 'انواع وظیفه', 'دسته‌های کار را تعریف کن']);
            items.push(['workflows', state.templates.some(template => Number(template.step_count) > 0), 'قالب گردش‌کار', 'مراحل و پیش‌نیازها را بچین']);
        }
        if (state.capabilities.projects_manage) items.push(['projects', state.projects.length > 0, 'اولین پروژه', 'قالب و اعضا را روی پروژه اجرا کن']);
        box.hidden = items.length === 0;
        if (!items.length) {
            box.innerHTML = '';
            return;
        }
        const done = items.filter(item => item[1]).length;
        box.innerHTML = `<header><div><span>شروع سریع</span><strong>${done === items.length ? 'سیستم برای کار آماده است' : `${done.toLocaleString('fa-IR')} از ${items.length.toLocaleString('fa-IR')} مرحله راه‌اندازی انجام شده`}</strong></div><button data-go="guide">راهنمای کامل</button></header><div>${items.map(([view, complete, title, help], index) => `<button class="${complete ? 'done' : ''}" data-go="${view}"><b>${complete ? '✓' : (index + 1).toLocaleString('fa-IR')}</b><span><strong>${title}</strong><small>${help}</small></span></button>`).join('')}</div>`;
    }

    function projectCard(project) {
        return `<article class="tf-project-card ${project.archived_at ? 'is-archived' : ''}" data-project-open="${Number(project.id)}"><header><span class="tf-project-icon">${esc(String(project.title || 'پ').slice(0, 1))}</span>${project.archived_at ? '<span class="tf-status status-disabled">آرشیوشده</span>' : statusBadge(project.status)}</header><h3>${esc(project.title)}</h3><p class="tf-code">${esc(project.order_number)}</p><div class="tf-progress-row">${progress(project.progress_percent)}</div><footer><span>♟ ${Number(project.member_count || 0).toLocaleString('fa-IR')} عضو</span><span>⌘ ${Number(project.stage_count || 0).toLocaleString('fa-IR')} مرحله</span>${project.archived_at ? `<span>◷ ${esc(String(project.archived_at).slice(0, 10))}</span>` : (project.due_at ? `<span>◷ ${esc(String(project.due_at).slice(0, 10))}</span>` : '')}</footer></article>`;
    }

    function renderProjects() {
        const list = root.querySelector('[data-project-list]');
        list.innerHTML = state.projects.length ? state.projects.map(projectCard).join('') : '<div class="tf-empty span-all"><strong>هنوز پروژه‌ای ساخته نشده</strong><p>ابتدا نوع وظیفه و قالب را آماده کن، سپس پروژه بساز.</p><button class="tf-button" data-go="guide">از کجا شروع کنم؟</button></div>';
    }

    function taskCard(task) {
        const standalone = Number(task.is_standalone) === 1;
        const canWork = Number(task.can_work) === 1;
        const workActions = canWork && task.status === 'open' ? `<button class="tf-button tiny secondary" data-task-start="${Number(task.id)}">شروع</button>` : (canWork && task.status === 'in_progress' ? `<button class="tf-link" data-task-report="${Number(task.id)}">ثبت گزارش</button><button class="tf-link" data-task-complete="${Number(task.id)}">تکمیل</button>` : statusBadge(task.status));
        const archiveAction = state.capabilities.tasks_manage && task.status === 'completed' && !task.archived_at ? `<button class="tf-link" data-task-archive="${Number(task.id)}">آرشیو</button>` : '';
        const restoreAction = state.capabilities.tasks_manage && task.archived_at ? `<button class="tf-link" data-task-restore="${Number(task.id)}">بازگردانی</button>` : '';
        return `<article class="tf-task-card ${task.archived_at ? 'is-archived' : ''}"><header><span class="tf-type" style="--type-color:${esc(task.task_type_color)}">${esc(task.task_type_name)}</span>${standalone ? '<span class="tf-independent">مستقل</span>' : ''}</header><h3>${esc(task.title)}</h3><p>${standalone ? 'بدون پروژه' : `پروژه: ${esc(task.order_title)}`}</p><small>مسئول: ${esc(task.assignee_names || 'تعیین نشده')}</small>${task.due_at ? `<small>موعد: ${esc(String(task.due_at).slice(0, 16))}</small>` : ''}<footer>${task.archived_at ? statusBadge('completed') : workActions}${archiveAction}${restoreAction}<button class="tf-link push" data-task-open="${Number(task.id)}">${task.status === 'completed' ? 'مشاهده گزارش‌ها' : 'جزئیات و مسئولان'}</button></footer></article>`;
    }

    function renderTasks() {
        const board = root.querySelector('[data-task-board]');
        const selectedStatus = root.querySelector('[data-task-filters]')?.elements.status.value || '';
        const allColumns = [['open', 'آماده شروع'], ['in_progress', 'در حال انجام'], ['completed', 'انجام‌شده']];
        const columns = selectedStatus ? allColumns.filter(([status]) => status === selectedStatus) : allColumns;
        board.innerHTML = columns.map(([status, title]) => {
            const tasks = state.tasks.filter(task => task.status === status);
            return `<section class="tf-task-column" data-task-column="${status}"><header><h2>${title}</h2><span>${tasks.length.toLocaleString('fa-IR')}</span></header><div>${tasks.map(taskCard).join('') || '<p class="tf-column-empty">موردی نیست</p>'}</div></section>`;
        }).join('');
    }

    function renderArchives() {
        const projects = root.querySelector('[data-archived-project-list]');
        const tasks = root.querySelector('[data-archived-task-list]');
        if (projects) projects.innerHTML = state.archivedProjects.length ? state.archivedProjects.map(projectCard).join('') : '<div class="tf-empty span-all"><strong>پروژه آرشیوشده‌ای وجود ندارد</strong><p>پروژه تکمیل‌شده را از صفحه جزئیات به آرشیو منتقل کن.</p></div>';
        if (tasks) tasks.innerHTML = state.archivedTasks.length ? state.archivedTasks.map(taskCard).join('') : '<div class="tf-empty"><strong>وظیفه آرشیوشده‌ای وجود ندارد</strong><p>وظیفه تکمیل‌شده را از ستون انجام‌شده به آرشیو منتقل کن.</p></div>';
    }

    function renderDashboard() {
        const projects = root.querySelector('[data-dashboard-projects]');
        const tasks = root.querySelector('[data-dashboard-tasks]');
        if (!projects || !tasks) return;
        projects.innerHTML = state.projects.slice(0, 4).map(project => `<button class="tf-row" data-project-open="${Number(project.id)}"><span class="tf-project-icon small">${esc(String(project.title).slice(0, 1))}</span><span><strong>${esc(project.title)}</strong><small>${esc(project.order_number)}</small></span><span class="tf-row-progress">${Number(project.progress_percent).toLocaleString('fa-IR')}٪</span></button>`).join('') || '<div class="tf-empty small">پروژه‌ای وجود ندارد.</div>';
        const openTasks = state.tasks.filter(task => ['open', 'in_progress'].includes(task.status)).slice(0, 5);
        tasks.innerHTML = openTasks.map(task => `<button class="tf-row" data-task-open="${Number(task.id)}"><i class="tf-check-dot"></i><span><strong>${esc(task.title)}</strong><small>${Number(task.is_standalone) === 1 ? 'تسک مستقل' : esc(task.order_title)}</small></span>${statusBadge(task.status)}</button>`).join('') || '<div class="tf-empty small">وظیفه بازی وجود ندارد.</div>';
    }

    function assignmentLabel(step) {
        const parts = [];
        numericIds(step.user_ids).forEach(id => { const item = state.reference.users?.find(user => Number(user.id) === id); if (item) parts.push(item.name); });
        numericIds(step.team_ids).forEach(id => { const item = state.reference.teams?.find(team => Number(team.id) === id); if (item) parts.push(`تیم ${item.name}`); });
        numericIds(step.role_ids).forEach(id => { const item = state.reference.roles?.find(role => Number(role.id) === id); if (item) parts.push(`نقش ${item.display_name}`); });
        return parts.length ? parts.join('، ') : 'همه اعضای پروژه';
    }

    function stageTaskActions(stage, readOnly = false) {
        if (!stage.task_id || stage.status === 'pending') return '';
        const taskId = Number(stage.task_id);
        if (stage.task_status === 'completed') return `<button class="tf-button tiny secondary" data-task-open="${taskId}">مشاهده گزارش‌ها</button>`;
        if (readOnly) return `<button class="tf-link" data-task-open="${taskId}">مشاهده جزئیات</button>`;
        if (Number(stage.task_can_work) !== 1) return `<button class="tf-link" data-task-open="${taskId}">مشاهده جزئیات</button>`;
        if (stage.task_status === 'open') return `<button class="tf-button tiny" data-task-start="${taskId}">شروع وظیفه</button>`;
        if (stage.task_status === 'in_progress') return `<button class="tf-button tiny secondary" data-task-report="${taskId}">ثبت گزارش</button><button class="tf-button tiny" data-task-complete="${taskId}">تکمیل</button>`;
        return `<button class="tf-link" data-task-open="${taskId}">مشاهده جزئیات</button>`;
    }

    async function showProject(projectId) {
        go('project-detail');
        const target = root.querySelector('[data-project-detail]');
        target.innerHTML = '<div class="tf-loading">در حال دریافت پروژه…</div>';
        const payload = await api(`projects/${projectId}`);
        const project = state.selectedProject = payload.project;
        const archived = Boolean(project.archived_at);
        const stageNames = Object.fromEntries((project.steps || []).map(stage => [Number(stage.id), stage.name]));
        const customers = (project.customers || []).map(customer => `${customer.name}${customer.weight ? ` · ${customer.weight} ${customer.weight_unit}` : ''}`).join('، ');
        const actions = state.capabilities.projects_manage ? `${archived ? `<button class="tf-button" data-project-restore="${Number(project.id)}">بازگردانی از آرشیو</button>` : `${project.status === 'draft' ? `<button class="tf-button" data-project-activate="${Number(project.id)}">شروع پروژه و ساخت تسک‌ها</button>` : ''}${project.status === 'active' ? `<button class="tf-button secondary" data-open-project-stage="${Number(project.id)}">+ مرحله جدید</button>` : ''}${project.status === 'completed' ? `<button class="tf-button secondary" data-project-archive="${Number(project.id)}">آرشیو پروژه</button>` : ''}<button class="tf-button secondary" data-edit-project="${Number(project.id)}">ویرایش پروژه</button>`}` : '';
        target.innerHTML = `<header class="tf-project-hero"><div><div class="tf-project-kicker">${esc(project.order_number)} · ${esc(project.priority_name || 'اولویت عادی')}</div><h1>${esc(project.title)}</h1><p>${esc(customers || projectDescription(project) || 'بدون مشتری')}</p></div><div class="tf-project-score"><strong>${Number(project.progress_percent).toLocaleString('fa-IR')}٪</strong><span>پیشرفت پروژه</span></div></header>
            <div class="tf-project-toolbar">${archived ? '<span class="tf-status status-disabled">آرشیوشده</span>' : statusBadge(project.status)}${actions}${state.capabilities.system_admin ? `<button class="tf-button danger push" data-delete-project="${Number(project.id)}">حذف پروژه</button>` : ''}</div>
            <section class="tf-card tf-members"><div class="tf-card-head"><div><h2>۱. اعضای پروژه</h2><p>تسک‌های مراحل فقط بین افراد این پروژه توزیع می‌شوند.</p></div></div><div class="tf-member-pills">${(project.members || []).map(member => `<span><i>${esc(String(member.name).slice(0, 1))}</i>${esc(member.name)}<small>${esc(member.role_label || '')}</small>${state.capabilities.projects_manage && !archived ? `<button title="حذف عضو" data-remove-project-member="${Number(member.id)}">×</button>` : ''}</span>`).join('') || '<em>عضوی انتخاب نشده است.</em>'}</div>${state.capabilities.projects_manage && !archived ? `<form data-project-member="${Number(project.id)}" class="tf-inline-form"><select name="user_id" required>${(state.reference.users || []).filter(user => user.status === 'active').map(user => `<option value="${Number(user.id)}">${esc(user.name)}</option>`).join('')}</select><input name="role_label" placeholder="نقش در این پروژه؛ اختیاری"><button class="tf-button tiny">افزودن عضو</button></form>` : ''}</section>
            <section class="tf-card tf-stages"><div class="tf-card-head"><div><h2>۲. مراحل و پیش‌نیازها</h2><p>وظیفه خودت را بدون خروج از پروژه شروع، گزارش یا تکمیل کن.</p></div></div><div class="tf-stage-flow">${(project.steps || []).map((stage, index) => { const dependencies = String(stage.dependency_ids || '').split(',').filter(Boolean).map(id => stageNames[Number(id)] || `#${id}`); return `<article class="tf-stage status-${esc(stage.status)}"><header><span>${(index + 1).toLocaleString('fa-IR')}</span>${statusBadge(stage.status)}</header><h3>${esc(stage.name)}</h3><p>${esc(stage.task_type_name)}</p><div class="tf-dependency">${dependencies.length ? `پیش‌نیاز: ${esc(dependencies.join('، '))}` : 'بدون پیش‌نیاز؛ شروع هم‌زمان'}</div>${stage.task_id ? `<div class="tf-stage-task"><strong>${esc(stage.assignee_names || 'بدون مسئول')}</strong>${statusBadge(stage.task_status)}</div><div class="tf-stage-work-actions">${stageTaskActions(stage, archived)}</div>` : '<div class="tf-stage-task muted">تسک هنوز ساخته نشده</div>'}${state.capabilities.projects_manage && !archived && !['completed', 'disabled'].includes(stage.status) ? `<div class="tf-stage-actions"><button data-edit-project-dependencies="${Number(stage.id)}">ویرایش پیش‌نیاز</button><button class="danger-text" data-disable-project-stage="${Number(stage.id)}">غیرفعال‌کردن</button></div>` : ''}</article>`; }).join('') || '<div class="tf-empty span-all"><strong>مرحله‌ها پس از شروع پروژه ساخته می‌شوند</strong><p>قبل از شروع، قالب و اعضای پروژه را بررسی کن.</p></div>'}</div></section>
            <section class="tf-card tf-attachments"><div class="tf-card-head"><div><h2>۳. تصاویر و پیوست‌ها</h2><p>تصاویر مربوط به همین پروژه</p></div></div><div class="tf-attachment-grid">${(project.attachments || []).map(item => `<a href="${esc(fileUrl(item.path))}" target="_blank"><img src="${esc(fileUrl(item.path))}" alt="${esc(item.caption || item.original_name || 'پیوست پروژه')}"><span>${esc(item.caption || item.original_name || 'تصویر')}</span></a>`).join('') || '<div class="tf-empty small">هنوز تصویری اضافه نشده است.</div>'}</div>${state.capabilities.projects_manage && !archived ? `<form class="tf-inline-form tf-upload" data-project-attachment="${Number(project.id)}" enctype="multipart/form-data"><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required><input name="caption" placeholder="توضیح تصویر؛ اختیاری"><button class="tf-button tiny">آپلود تصویر</button></form>` : ''}</section>
            <section class="tf-card"><div class="tf-card-head"><div><h2>۴. تاریخچه پروژه</h2><p>چه کاری، توسط چه کسی و چه زمانی انجام شده است.</p></div></div><div class="tf-timeline">${(project.history || []).map(item => `<article><i></i><div><strong>${esc(item.message)}</strong><small>${esc(item.actor_name || 'سیستم')} · ${esc(item.created_at)}</small></div></article>`).join('') || '<div class="tf-empty small">رویدادی ثبت نشده.</div>'}</div></section>`;
    }

    function renderTemplates() {
        const list = root.querySelector('[data-template-list]');
        list.innerHTML = `<header><h2>قالب‌ها</h2><span>${state.templates.length.toLocaleString('fa-IR')}</span></header>` + (state.templates.map(template => `<button class="tf-template-item ${state.selectedTemplate?.id == template.id ? 'is-active' : ''}" data-template-select="${Number(template.id)}"><span><strong>${esc(template.name)}</strong><small>${Number(template.step_count).toLocaleString('fa-IR')} مرحله فعال · نسخه ${Number(template.version).toLocaleString('fa-IR')}</small></span><b>‹</b></button>`).join('') || '<div class="tf-empty small">قالبی وجود ندارد؛ ابتدا یک قالب بساز.</div>');
    }

    function selectTemplate(templateId) {
        const template = state.templates.find(item => Number(item.id) === Number(templateId));
        if (!template) return;
        state.selectedTemplate = template; renderTemplates();
        const editor = root.querySelector('[data-template-editor]');
        const names = Object.fromEntries((template.steps || []).map(step => [Number(step.id), step.name]));
        const manage = state.capabilities.templates_manage;
        editor.innerHTML = `<header class="tf-editor-head"><div><span>قالب گردش‌کار</span><h2>${esc(template.name)}</h2><p>${esc(template.description || 'بدون توضیحات')}</p></div><div class="tf-head-actions">${manage ? `<button class="tf-button secondary" data-edit-template="${Number(template.id)}">ویرایش مشخصات</button><button class="tf-button" data-open-template-stage="${Number(template.id)}">+ مرحله</button>` : ''}${state.capabilities.system_admin ? `<button class="tf-button danger" data-delete-template="${Number(template.id)}">حذف قالب</button>` : ''}</div></header><div class="tf-template-stages">${(template.steps || []).map((step, index) => `<article class="${Number(step.is_active) ? '' : 'is-disabled'}"><span>${(index + 1).toLocaleString('fa-IR')}</span><div><h3>${esc(step.name)} ${Number(step.is_active) ? '' : '· غیرفعال'}</h3><p>${esc(step.task_type_name)} · سهم پیشرفت ${Number(step.progress_weight).toLocaleString('fa-IR')}</p><small>${step.dependencies.length ? `بعد از: ${step.dependencies.map(id => esc(names[Number(id)] || `#${id}`)).join('، ')}` : 'بدون پیش‌نیاز؛ هم‌زمان با شروع پروژه'}</small><small>مسئول پیش‌فرض: ${esc(assignmentLabel(step))}</small>${manage ? `<div class="tf-stage-actions"><button data-edit-template-stage="${Number(step.id)}">ویرایش</button>${Number(step.is_active) ? `<button class="danger-text" data-disable-template-stage="${Number(step.id)}">غیرفعال‌کردن</button>` : ''}<button class="danger-text" data-delete-template-stage="${Number(step.id)}">حذف از قالب</button></div>` : ''}</div></article>`).join('') || '<div class="tf-empty"><strong>قالب هنوز مرحله‌ای ندارد</strong><p>اولین مرحله را اضافه کن.</p></div>'}</div>`;
    }

    function renderTaskTypes() {
        const list = root.querySelector('[data-task-type-list]');
        const items = state.reference.task_types || [];
        list.innerHTML = `<div class="tf-table-row tf-table-head"><span>نوع وظیفه</span><span>کلید</span><span>استفاده</span><span>وضعیت</span><span>عملیات</span></div>${items.map(item => `<div class="tf-table-row"><span><i class="tf-color" style="background:${esc(item.color)}"></i><strong>${esc(item.name)}</strong><small>${esc(item.description || '')}</small></span><code>${esc(item.slug)}</code><span>${Number(item.usage_count || 0).toLocaleString('fa-IR')} مورد</span>${statusBadge(Number(item.is_active) ? 'active' : 'disabled')}<span class="tf-actions">${state.capabilities.templates_manage ? `<button data-edit-task-type="${Number(item.id)}">ویرایش</button><button class="danger-text" data-delete-task-type="${Number(item.id)}">حذف</button>` : '—'}</span></div>`).join('') || '<div class="tf-empty"><strong>نوع وظیفه‌ای وجود ندارد</strong><p>برای ساخت تسک و مرحله حداقل یک نوع وظیفه بساز.</p></div>'}`;
    }

    function renderCustomers() {
        const list = root.querySelector('[data-customer-list]');
        const items = state.reference.customers || [];
        list.innerHTML = `<div class="tf-table-row tf-table-head customer"><span>مشتری</span><span>تماس</span><span>پروژه‌ها</span><span>عملیات</span></div>${items.map(item => `<div class="tf-table-row customer"><span><strong>${esc(item.name)}</strong><small>${esc(item.notes || '')}</small></span><span>${esc(item.phone || item.email || '—')}</span><span>${Number(item.order_count || 0).toLocaleString('fa-IR')} پروژه</span><span class="tf-actions">${state.capabilities.projects_manage ? `<button data-edit-customer="${Number(item.id)}">ویرایش</button><button class="danger-text" data-delete-customer="${Number(item.id)}">حذف</button>` : '—'}</span></div>`).join('') || '<div class="tf-empty"><strong>مشتری‌ای ثبت نشده</strong><p>ساخت مشتری اختیاری است و از بالای همین صفحه انجام می‌شود.</p></div>'}`;
    }

    function renderManagement() {
        renderTaskTypes(); renderCustomers();
        const teams = root.querySelector('[data-team-list]');
        const users = root.querySelector('[data-user-list]');
        const roles = root.querySelector('[data-role-list]');
        if (teams) teams.innerHTML = (state.reference.teams || []).map(team => `<article><i>♟</i><span><strong>${esc(team.name)} · ${Number(team.member_count || 0).toLocaleString('fa-IR')} نفر</strong><small>${esc(team.member_names || team.description || 'هنوز عضوی ندارد')}</small></span>${statusBadge(Number(team.is_active) ? 'active' : 'disabled')}${state.capabilities.teams_manage ? `<button class="tf-link" type="button" data-edit-team="${Number(team.id)}">مدیریت تیم</button>` : ''}</article>`).join('') || '<div class="tf-empty small">گروهی وجود ندارد.</div>';
        if (users) users.innerHTML = (state.reference.users || []).map(user => `<article><i>${esc(String(user.name).slice(0, 1))}</i><span><strong>${esc(user.name)}</strong><small>${esc(user.email)} · ${esc(user.role_names || 'بدون نقش')}</small><small>${Number(user.open_task_count || 0).toLocaleString('fa-IR')} تسک باز · آخرین ورود ${esc(user.last_login_at || 'ثبت نشده')}</small></span>${statusBadge(user.status)}${state.capabilities.users_manage ? `<button class="tf-link" type="button" data-edit-user="${Number(user.id)}">مدیریت کاربر</button>` : ''}</article>`).join('') || '<div class="tf-empty small">کاربری وجود ندارد.</div>';
        if (roles) roles.innerHTML = (state.reference.roles || []).map(role => { const protectedRole = ['admin', 'manager', 'user'].includes(role.key_name); const keys = permissionKeys(role); const details = state.capabilities.roles_manage ? `<small>${Number(role.user_count || 0).toLocaleString('fa-IR')} کاربر${role.user_names ? ` · ${esc(role.user_names)}` : ''}</small><div class="tf-permission-chips">${keys.map(key => `<span>${esc(permissionLabels[key] || key)}</span>`).join('') || '<em>بدون دسترسی</em>'}</div>` : '<small>نقش فعال قابل تخصیص به کاربران</small>'; return `<article><i>◎</i><span><strong>${esc(role.display_name)}</strong><small>${esc(role.key_name)}</small>${details}</span>${statusBadge(Number(role.is_active) ? 'active' : 'disabled')}${state.capabilities.roles_manage ? `<div class="tf-actions"><button type="button" data-edit-role="${Number(role.id)}">ویرایش دسترسی‌ها</button><button type="button" data-clone-role="${Number(role.id)}">کپی نقش</button>${protectedRole ? '' : `<button type="button" class="danger-text" data-delete-role="${Number(role.id)}">حذف</button>`}</div>` : ''}</article>`; }).join('') || '<div class="tf-empty small">نقشی وجود ندارد.</div>';
    }

    function renderNotifications() {
        const list = root.querySelector('[data-notification-list]');
        list.innerHTML = state.notifications.map(item => `<button type="button" class="tf-notification ${item.read_at ? '' : 'unread'}" data-notification-id="${Number(item.id)}" data-notification-link="${esc(item.link_url || '/workspace#notifications')}"><i>◉</i><div><strong>${esc(item.title)}</strong><p>${esc(item.body || '')}</p><small>${esc(item.created_at)}</small></div></button>`).join('') || '<div class="tf-empty">اعلانی وجود ندارد.</div>';
    }

    async function showTask(taskId) {
        const target = root.querySelector('[data-task-detail]');
        target.innerHTML = '<div class="tf-loading">در حال دریافت تسک…</div>';
        openModal('task-detail');
        const payload = await api(`tasks/${taskId}`);
        const task = state.selectedTask = payload.task;
        const assigneeIds = (task.assignees || []).map(user => Number(user.id));
        const canManage = state.capabilities.tasks_manage;
        const canWork = Number(task.can_work) === 1;
        const taskArchived = Boolean(task.archived_at);
        const readOnly = taskArchived || Boolean(task.project_archived_at);
        const editable = canManage && !readOnly && Number(task.is_standalone) === 1 && !['completed', 'cancelled'].includes(task.status);
        target.innerHTML = `<header><div><span class="tf-type" style="--type-color:${esc(task.task_type_color)}">${esc(task.task_type_name)}</span><h2>${esc(task.title)}</h2><p>${Number(task.is_standalone) ? 'تسک مستقل' : `پروژه: ${esc(task.order_title)}`}</p></div><button type="button" data-close-modal>×</button></header>
            <div class="tf-task-summary">${statusBadge(task.status)}${readOnly ? '<span class="tf-status status-disabled">فقط‌خواندنی / آرشیوشده</span>' : ''}<span>موعد: ${esc(task.due_at ? String(task.due_at).slice(0, 16) : 'ندارد')}</span><span>مسئولان: ${esc((task.assignees || []).map(user => user.name).join('، ') || 'تعیین نشده')}</span></div>
            ${editable ? `<form class="tf-form-grid tf-subform" data-edit-task><input type="hidden" name="task_id" value="${Number(task.id)}"><label class="span-2">عنوان<input name="title" required value="${esc(task.title)}"></label><label>نوع<select name="task_type_id">${(state.reference.task_types || []).filter(item => Number(item.is_active)).map(item => `<option value="${Number(item.id)}" ${Number(item.id) === Number(task.task_type_id) ? 'selected' : ''}>${esc(item.name)}</option>`).join('')}</select></label><label>موعد<input type="datetime-local" name="due_at" value="${esc(dateTimeValue(task.due_at))}"></label><label class="span-2">توضیحات<textarea name="description">${esc(task.description || '')}</textarea></label><button class="tf-button secondary span-2">ذخیره مشخصات تسک</button></form>` : `<p class="tf-task-description">${esc(task.description || 'توضیحی ثبت نشده است.')}</p>`}
            ${canManage && !readOnly && !['completed', 'cancelled'].includes(task.status) ? `<form class="tf-assignment-box" data-task-assignees><input type="hidden" name="task_id" value="${Number(task.id)}"><label><strong>تغییر مسئولان</strong><small>${task.order_id ? 'فقط اعضای فعال همین پروژه؛' : 'یک یا چند کاربر فعال؛'} انجام توسط یک نفر کافی است.</small><select name="user_ids" multiple required>${(task.eligible_assignees || []).map(user => `<option value="${Number(user.id)}" ${assigneeIds.includes(Number(user.id)) ? 'selected' : ''}>${esc(user.name)} — ${esc(user.email)}</option>`).join('')}</select></label><button class="tf-button">ذخیره مسئولان</button></form>` : ''}
            <section class="tf-report-list"><h3>گزارش‌های انجام کار</h3>${(task.reports || []).map(report => `<article><strong>${esc(report.user_name)}</strong><p>${esc(report.report_text)}</p><small>${esc(report.created_at)}</small></article>`).join('') || '<p class="tf-muted">هنوز گزارشی ثبت نشده است.</p>'}</section>
            <footer>${canWork && !readOnly && task.status === 'open' ? `<button class="tf-button secondary" data-task-start="${Number(task.id)}">شروع</button>` : ''}${canWork && !readOnly && task.status === 'in_progress' ? `<button class="tf-button secondary" data-task-report="${Number(task.id)}">ثبت گزارش</button><button class="tf-button" data-task-complete="${Number(task.id)}">تکمیل تسک</button>` : ''}${canManage && !readOnly && task.status === 'completed' ? `<button class="tf-button secondary" data-task-archive="${Number(task.id)}">آرشیو وظیفه</button>` : ''}${canManage && taskArchived && !task.project_archived_at ? `<button class="tf-button" data-task-restore="${Number(task.id)}">بازگردانی از آرشیو</button>` : ''}${canManage && !readOnly && Number(task.is_standalone) ? `<button class="tf-button danger push" data-delete-task="${Number(task.id)}">حذف تسک مستقل</button>` : ''}</footer>`;
    }

    function prepareUser(user) {
        const form = root.querySelector('[data-user-edit-form]'); resetModalForm(form);
        setForm(form, { ...user, user_id: user.id });
        renderRoleOptions(form, numericIds(user.role_ids));
        root.querySelector('[data-user-edit-summary]').textContent = user.email;
        root.querySelector('[data-user-last-login]').textContent = user.last_login_at || 'ثبت نشده';
        root.querySelector('[data-user-teams]').textContent = user.team_names || 'عضو تیمی نیست';
        root.querySelector('[data-user-projects]').textContent = user.project_names || 'عضو پروژه‌ای نیست';
        root.querySelector('[data-user-open-tasks]').textContent = Number(user.open_task_count || 0).toLocaleString('fa-IR');
        openModal('user-edit');
    }

    function prepareRole(role = null, clone = false) {
        const form = root.querySelector('[data-save-role]'); resetModalForm(form);
        form.elements.is_active.checked = true;
        form.elements.is_active.disabled = false;
        form.elements.key_name.disabled = Boolean(role) && !clone;
        root.querySelector('[data-role-modal-title]').textContent = clone ? 'کپی نقش' : (role ? 'ویرایش نقش' : 'تعریف نقش جدید');
        root.querySelector('[data-role-modal-help]').textContent = clone ? 'یک کلید تازه وارد کن؛ دسترسی‌ها از نقش مبدأ کپی شده‌اند.' : 'دسترسی‌های نقش و وضعیت استفاده آن را مدیریت کن.';
        if (role) setForm(form, { role_id: clone ? '' : role.id, key_name: clone ? '' : role.key_name, display_name: clone ? `${role.display_name} - کپی` : role.display_name, is_active: clone ? 1 : role.is_active });
        if (role && !clone && ['admin', 'manager', 'user'].includes(role.key_name)) form.elements.is_active.disabled = true;
        renderPermissionOptions(form, permissionKeys(role), Boolean(role && !clone && role.key_name === 'admin'));
        if (clone) form.dataset.cloneFrom = String(role.id); else delete form.dataset.cloneFrom;
        openModal('role');
    }

    function prepareTaskType(item = null) {
        const form = root.querySelector('[data-save-task-type]'); resetModalForm(form);
        form.elements.is_active.checked = true;
        root.querySelector('[data-task-type-title]').textContent = item ? 'ویرایش نوع وظیفه' : 'نوع وظیفه جدید';
        if (item) setForm(form, { ...item, task_type_id: item.id });
        openModal('task-type');
    }

    function prepareCustomer(item = null) {
        const form = root.querySelector('[data-save-customer]'); resetModalForm(form);
        root.querySelector('[data-customer-title]').textContent = item ? 'ویرایش مشتری' : 'مشتری جدید';
        if (item) setForm(form, { ...item, customer_id: item.id });
        openModal('customer');
    }

    function prepareTeam(team = null) {
        const form = root.querySelector('[data-save-team]'); resetModalForm(form);
        form.elements.is_active.checked = true;
        root.querySelector('[data-team-modal-title]').textContent = team ? 'مدیریت تیم' : 'ساخت گروه';
        root.querySelector('[data-team-modal-help]').textContent = team ? 'نام، وضعیت و اعضای تیم را مدیریت کن.' : 'مثلاً تیم طراحی';
        const memberSection = root.querySelector('[data-team-member-section]');
        memberSection.hidden = !team;
        if (team) {
            setForm(form, { ...team, team_id: team.id });
            const memberIds = numericIds(team.member_ids);
            const leadIds = numericIds(team.lead_ids);
            root.querySelector('[data-team-members]').innerHTML = memberIds.map(userId => {
                const user = (state.reference.users || []).find(item => Number(item.id) === userId);
                const lead = leadIds.includes(userId);
                return `<span><i>${esc(String(user?.name || '?').slice(0, 1))}</i><strong>${esc(user?.name || `کاربر #${userId}`)}</strong><small>${lead ? 'سرگروه' : 'عضو'}</small><button type="button" class="tf-link" data-toggle-team-lead="${Number(team.id)}" data-user-id="${userId}" data-is-lead="${lead ? 1 : 0}">${lead ? 'حذف سرگروهی' : 'تبدیل به سرگروه'}</button><button type="button" class="danger-text" data-remove-team-member="${Number(team.id)}" data-user-id="${userId}">حذف عضو</button></span>`;
            }).join('') || '<p class="tf-muted">هنوز عضوی در این تیم نیست.</p>';
        } else {
            root.querySelector('[data-team-members]').innerHTML = '';
        }
        openModal('team');
    }

    function prepareTemplate(template = null) {
        const form = root.querySelector('[data-save-template]'); resetModalForm(form);
        form.elements.is_active.checked = true;
        root.querySelector('[data-template-modal-title]').textContent = template ? 'ویرایش قالب گردش‌کار' : 'قالب گردش‌کار جدید';
        if (template) setForm(form, { ...template, template_id: template.id });
        openModal('template');
    }

    function prepareStage(template, step = null) {
        const form = root.querySelector('[data-save-stage]'); resetModalForm(form); fillOptions(form);
        form.elements.team_ids.closest('label').hidden = false; form.elements.role_ids.closest('label').hidden = false;
        root.querySelector('[data-stage-active-row]').hidden = false;
        form.dataset.mode = step ? 'template-edit' : 'template-create';
        form.elements.template_id.value = template.id;
        form.elements.is_active.checked = true;
        root.querySelector('[data-stage-modal-title]').textContent = step ? 'ویرایش مرحله قالب' : 'افزودن مرحله به قالب';
        root.querySelector('[data-stage-template-name]').textContent = template.name;
        root.querySelector('[data-stage-dependencies]').innerHTML = (template.steps || []).filter(item => Number(item.id) !== Number(step?.id) && Number(item.is_active)).map(item => `<option value="${Number(item.id)}">${esc(item.name)}</option>`).join('');
        if (step) setForm(form, { ...step, step_id: step.id, dependency_ids: step.dependencies });
        openModal('stage');
    }

    async function refreshAll() {
        await loadReference();
        if (!state.capabilities.templates_manage) state.templates = [];
        const requests = [loadOverview(), loadProjects(), loadTasks(), loadArchives(), loadNotifications()];
        if (state.capabilities.templates_manage) requests.push(loadTemplates());
        await Promise.all(requests);
    }

    root.addEventListener('click', async event => {
        const nav = event.target.closest('[data-nav-view],[data-go]');
        if (nav) { go(nav.dataset.navView || nav.dataset.go); return; }
        if (event.target.closest('[data-sidebar-toggle]')) {
            const open = root.classList.toggle('sidebar-open');
            root.querySelectorAll('[data-sidebar-toggle]').forEach(button => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
            return;
        }
        const close = event.target.closest('[data-close-modal]');
        if (close) { closeModal(close); return; }
        if (event.target.closest('[data-open-project]')) { if (!(state.reference.templates || []).some(item => Number(item.is_active))) { toast(canView('workflows') ? 'ابتدا یک قالب گردش‌کار بساز.' : 'قالب گردش‌کار فعالی وجود ندارد؛ با مدیر سامانه هماهنگ کنید.', 'error'); if (canView('workflows')) go('workflows'); return; } const form = root.querySelector('[data-create-project]'); resetModalForm(form); fillOptions(form); openModal('project'); return; }
        if (event.target.closest('[data-open-quick-task]')) { if (!(state.reference.task_types || []).some(item => Number(item.is_active))) { toast(canView('task-types') ? 'ابتدا یک نوع وظیفه بساز.' : 'نوع وظیفه فعالی وجود ندارد؛ با مدیر سامانه هماهنگ کنید.', 'error'); if (canView('task-types')) go('task-types'); return; } const form = root.querySelector('[data-create-quick-task]'); resetModalForm(form); fillOptions(form); openModal('quick-task'); return; }
        if (event.target.closest('[data-open-template]')) { prepareTemplate(); return; }
        if (event.target.closest('[data-open-task-type]')) { prepareTaskType(); return; }
        if (event.target.closest('[data-open-customer]')) { prepareCustomer(); return; }
        if (event.target.closest('[data-open-team]')) { prepareTeam(); return; }
        if (event.target.closest('[data-open-user]')) { const form = root.querySelector('[data-create-user]'); resetModalForm(form); renderRoleOptions(form); openModal('user'); return; }
        if (event.target.closest('[data-open-role]')) { prepareRole(); return; }

        const projectLink = event.target.closest('[data-project-open]');
        if (projectLink) { await showProject(projectLink.dataset.projectOpen).catch(error => toast(error.message, 'error')); return; }
        const taskLink = event.target.closest('[data-task-open]');
        if (taskLink) { await showTask(taskLink.dataset.taskOpen).catch(error => toast(error.message, 'error')); return; }
        const notificationLink = event.target.closest('[data-notification-link]');
        if (notificationLink) {
            await api(`notifications/${notificationLink.dataset.notificationId}/read`, { method: 'POST' }).catch(() => null);
            const target = new URL(notificationLink.dataset.notificationLink, location.origin);
            const taskId = Number(target.searchParams.get('task') || 0);
            await Promise.all([loadNotifications(), loadOverview()]);
            if (target.origin !== location.origin) return;
            if (taskId > 0) { go('tasks'); await showTask(taskId).catch(error => toast(error.message, 'error')); return; }
            location.assign(target.href);
            return;
        }
        const templateLink = event.target.closest('[data-template-select]');
        if (templateLink) { selectTemplate(templateLink.dataset.templateSelect); return; }

        const editTaskType = event.target.closest('[data-edit-task-type]');
        if (editTaskType) { prepareTaskType(state.reference.task_types.find(item => Number(item.id) === Number(editTaskType.dataset.editTaskType))); return; }
        const editCustomer = event.target.closest('[data-edit-customer]');
        if (editCustomer) { prepareCustomer(state.reference.customers.find(item => Number(item.id) === Number(editCustomer.dataset.editCustomer))); return; }
        const editTeam = event.target.closest('[data-edit-team]');
        if (editTeam) { const team = state.reference.teams.find(item => Number(item.id) === Number(editTeam.dataset.editTeam)); if (team) prepareTeam(team); return; }
        const editTemplate = event.target.closest('[data-edit-template]');
        if (editTemplate) { prepareTemplate(state.templates.find(item => Number(item.id) === Number(editTemplate.dataset.editTemplate))); return; }
        const addTemplateStage = event.target.closest('[data-open-template-stage]');
        if (addTemplateStage) { prepareStage(state.templates.find(item => Number(item.id) === Number(addTemplateStage.dataset.openTemplateStage))); return; }
        const editTemplateStage = event.target.closest('[data-edit-template-stage]');
        if (editTemplateStage) { const template = state.selectedTemplate; prepareStage(template, template.steps.find(item => Number(item.id) === Number(editTemplateStage.dataset.editTemplateStage))); return; }
        const editUser = event.target.closest('[data-edit-user]');
        if (editUser) { const user = state.reference.users.find(item => Number(item.id) === Number(editUser.dataset.editUser)); if (user) prepareUser(user); return; }
        const editRole = event.target.closest('[data-edit-role]');
        if (editRole) { const role = state.reference.roles.find(item => Number(item.id) === Number(editRole.dataset.editRole)); if (role) prepareRole(role); return; }
        const cloneRole = event.target.closest('[data-clone-role]');
        if (cloneRole) { const role = state.reference.roles.find(item => Number(item.id) === Number(cloneRole.dataset.cloneRole)); if (role) prepareRole(role, true); return; }
        const toggleTaskFilters = event.target.closest('[data-toggle-task-filters]');
        if (toggleTaskFilters) {
            const panel = toggleTaskFilters.closest('form')?.querySelector('[data-task-filter-panel]');
            if (panel?.hidden) openTaskFilters(); else closeTaskFilters();
            return;
        }
        if (event.target.closest('[data-close-task-filters]')) { closeTaskFilters(); return; }
        const removeTaskFilter = event.target.closest('[data-remove-task-filter]');
        if (removeTaskFilter) {
            const params = new URLSearchParams(state.taskQuery);
            params.delete(removeTaskFilter.dataset.removeTaskFilter);
            state.taskQuery = params.toString();
            restoreTaskFilterForm();
            await loadTasks(state.taskQuery).catch(error => toast(error.message, 'error'));
            return;
        }
        const resetTaskFilters = event.target.closest('[data-reset-task-filters]');
        if (resetTaskFilters) {
            resetTaskFilters.closest('form')?.reset();
            syncTaskProjectFilters(true);
            closeTaskFilters(false);
            await loadTasks('').catch(error => toast(error.message, 'error'));
            return;
        }

        const editProject = event.target.closest('[data-edit-project]');
        if (editProject && state.selectedProject) {
            const form = root.querySelector('[data-project-edit-form]'); resetModalForm(form); fillOptions(form);
            const customer = state.selectedProject.customers?.[0] || {};
            setForm(form, { project_id: state.selectedProject.id, name: state.selectedProject.title, code: state.selectedProject.order_number, priority_id: state.selectedProject.priority_id, due_at: dateTimeValue(state.selectedProject.due_at), customer_id: customer.id || '', weight: customer.weight || '', description: projectDescription(state.selectedProject) });
            openModal('project-edit'); return;
        }

        const addProjectStage = event.target.closest('[data-open-project-stage]');
        if (addProjectStage) {
            const form = root.querySelector('[data-save-stage]'); resetModalForm(form); fillOptions(form);
            form.dataset.mode = 'project-create'; form.elements.template_id.value = addProjectStage.dataset.openProjectStage;
            root.querySelector('[data-stage-modal-title]').textContent = 'افزودن مرحله به پروژه';
            root.querySelector('[data-stage-template-name]').textContent = state.selectedProject.title;
            root.querySelector('[data-stage-dependencies]').innerHTML = (state.selectedProject.steps || []).filter(step => step.status !== 'disabled').map(step => `<option value="${Number(step.id)}">${esc(step.name)}</option>`).join('');
            const userSelect = form.elements.user_ids;
            userSelect.innerHTML = (state.selectedProject.members || []).map(user => `<option value="${Number(user.id)}">${esc(user.name)}</option>`).join('');
            form.elements.team_ids.closest('label').hidden = true; form.elements.role_ids.closest('label').hidden = true;
            root.querySelector('[data-stage-active-row]').hidden = true;
            openModal('stage'); return;
        }

        const dependencyEdit = event.target.closest('[data-edit-project-dependencies]');
        if (dependencyEdit) {
            const step = state.selectedProject.steps.find(item => Number(item.id) === Number(dependencyEdit.dataset.editProjectDependencies));
            const form = root.querySelector('[data-save-project-dependencies]'); resetModalForm(form); form.elements.step_id.value = step.id;
            root.querySelector('[data-project-dependency-title]').textContent = step.name;
            const selected = numericIds(step.dependency_ids);
            root.querySelector('[data-project-dependency-options]').innerHTML = state.selectedProject.steps.filter(item => Number(item.id) !== Number(step.id) && item.status !== 'disabled').map(item => `<option value="${Number(item.id)}" ${selected.includes(Number(item.id)) ? 'selected' : ''}>${esc(item.name)}</option>`).join('');
            openModal('project-dependencies'); return;
        }

        const action = event.target.closest('[data-task-start],[data-task-report],[data-task-complete],[data-task-archive],[data-task-restore],[data-delete-task],[data-project-activate],[data-project-archive],[data-project-restore],[data-remove-project-member],[data-disable-project-stage],[data-disable-template-stage],[data-delete-template-stage],[data-remove-team-member],[data-toggle-team-lead],[data-delete-project],[data-delete-template],[data-delete-task-type],[data-delete-customer],[data-delete-role],[data-read-notifications],[data-refresh]');
        if (!action) return;
        action.disabled = true;
        try {
            if (action.matches('[data-refresh]')) await refreshAll();
            else if (action.matches('[data-read-notifications]')) { await api('notifications/read', { method: 'POST' }); await Promise.all([loadNotifications(), loadOverview()]); }
            else if (action.dataset.taskStart) {
                const projectContext = Boolean(action.closest('[data-project-detail]'));
                await api(`tasks/${action.dataset.taskStart}/start`, { method: 'POST' });
                await Promise.all([loadTasks(), loadOverview()]);
                if (action.closest('dialog')) await showTask(action.dataset.taskStart);
                else if (projectContext && state.selectedProject) await showProject(state.selectedProject.id);
            }
            else if (action.dataset.taskReport) {
                const projectContext = Boolean(action.closest('[data-project-detail]'));
                const report = await window.AppModal.prompt('گزارش انجام‌شده برای این وظیفه در تاریخچه ذخیره می‌شود.', { title: 'ثبت گزارش کار', inputLabel: 'متن گزارش', placeholder: 'چه کاری انجام شد؟', required: true, confirmText: 'ثبت گزارش', icon: '✎' });
                if (report === null) return;
                await api(`tasks/${action.dataset.taskReport}/reports`, { method: 'POST', body: { report_text: report } });
                await loadTasks();
                if (action.closest('dialog')) await showTask(action.dataset.taskReport);
                else if (projectContext && state.selectedProject) await showProject(state.selectedProject.id);
            }
            else if (action.dataset.taskComplete) {
                const projectContext = Boolean(action.closest('[data-project-detail]'));
                const report = await window.AppModal.prompt('در صورت نیاز گزارش نهایی را بنویس؛ ثبت گزارش برای تکمیل تسک اختیاری است.', { title: 'تکمیل وظیفه', inputLabel: 'گزارش نهایی (اختیاری)', placeholder: 'خلاصه نتیجه کار…', confirmText: 'تکمیل تسک', icon: '✓' });
                if (report === null) return;
                await api(`tasks/${action.dataset.taskComplete}/complete`, { method: 'POST', body: { report_text: report } });
                await Promise.all([loadTasks(), loadProjects(), loadOverview(), loadNotifications()]);
                if (action.closest('dialog')) await showTask(action.dataset.taskComplete);
                else if (projectContext && state.selectedProject) await showProject(state.selectedProject.id);
            }
            else if (action.dataset.taskArchive) {
                if (!await window.AppModal.confirm('وظیفه از برد اصلی کنار می‌رود؛ گزارش‌ها و تاریخچه آن حفظ می‌شوند.', { title: 'آرشیو وظیفه', confirmText: 'انتقال به آرشیو', icon: '◇' })) return;
                await api(`tasks/${action.dataset.taskArchive}/archive`, { method: 'POST' });
                if (action.closest('dialog')) closeModal(action);
                await Promise.all([loadTasks(), loadArchives(), loadOverview()]);
            }
            else if (action.dataset.taskRestore) {
                await api(`tasks/${action.dataset.taskRestore}/restore`, { method: 'POST' });
                if (action.closest('dialog')) closeModal(action);
                await Promise.all([loadTasks(), loadArchives(), loadOverview()]);
            }
            else if (action.dataset.deleteTask) {
                if (!await window.AppModal.confirm('این تسک مستقل همراه تمام گزارش‌های آن برای همیشه حذف می‌شود.', { title: 'حذف تسک مستقل', confirmText: 'حذف تسک', tone: 'danger' })) return;
                await api(`tasks/${action.dataset.deleteTask}/delete`, { method: 'POST' });
                closeModal(action);
                await Promise.all([loadTasks(), loadOverview(), loadNotifications()]);
            }
            else if (action.dataset.projectActivate) {
                if (!await window.AppModal.confirm('مراحل قالب روی پروژه ساخته می‌شوند و وظایف آماده به مسئولان تخصیص پیدا می‌کنند.', { title: 'شروع پروژه', confirmText: 'شروع و ساخت تسک‌ها', icon: '▶' })) return;
                await api(`projects/${action.dataset.projectActivate}/activate`, { method: 'POST' });
                await Promise.all([loadProjects(), loadTasks(), loadOverview(), loadNotifications()]);
                await showProject(action.dataset.projectActivate);
            }
            else if (action.dataset.projectArchive) {
                if (!await window.AppModal.confirm('پروژه از داشبورد و فهرست اصلی کنار می‌رود؛ تمام مراحل، وظایف، گزارش‌ها، تصاویر و تاریخچه حفظ می‌شوند.', { title: 'آرشیو پروژه تکمیل‌شده', confirmText: 'انتقال به آرشیو', icon: '◇' })) return;
                await api(`projects/${action.dataset.projectArchive}/archive`, { method: 'POST' });
                state.selectedProject = null;
                await Promise.all([loadProjects(), loadTasks(), loadArchives(), loadOverview()]);
                go('archives');
            }
            else if (action.dataset.projectRestore) {
                const projectId = action.dataset.projectRestore;
                await api(`projects/${projectId}/restore`, { method: 'POST' });
                await Promise.all([loadProjects(), loadTasks(), loadArchives(), loadOverview()]);
                await showProject(projectId);
            }
            else if (action.dataset.removeProjectMember) {
                if (!await window.AppModal.confirm('اگر این عضو تسک بازی داشته باشد، ابتدا باید مسئول آن تسک را تغییر بدهی.', { title: 'حذف عضو از پروژه', confirmText: 'حذف عضو', tone: 'danger' })) return;
                await api(`projects/${state.selectedProject.id}/members/remove`, { method: 'POST', body: { user_id: Number(action.dataset.removeProjectMember) } });
                await Promise.all([loadProjects(), loadReference()]);
                await showProject(state.selectedProject.id);
            }
            else if (action.dataset.disableProjectStage) {
                if (!await window.AppModal.confirm('مرحله غیرفعال و تسک باز آن لغو می‌شود؛ تاریخچه مرحله باقی می‌ماند.', { title: 'غیرفعال‌کردن مرحله پروژه', confirmText: 'غیرفعال شود', tone: 'danger' })) return;
                await api(`order-steps/${action.dataset.disableProjectStage}/disable`, { method: 'POST' });
                await Promise.all([loadProjects(), loadTasks(), loadOverview()]);
                await showProject(state.selectedProject.id);
            }
            else if (action.dataset.disableTemplateStage) {
                if (!await window.AppModal.confirm('این مرحله فقط برای پروژه‌های آینده غیرفعال می‌شود و پروژه‌های قبلی تغییر نمی‌کنند.', { title: 'غیرفعال‌کردن مرحله قالب', confirmText: 'غیرفعال شود', tone: 'danger' })) return;
                await api(`template-steps/${action.dataset.disableTemplateStage}/disable`, { method: 'POST' });
                await loadTemplates();
            }
            else if (action.dataset.deleteTemplateStage) {
                if (!await window.AppModal.confirm('این مرحله فقط از قالب و پروژه‌های آینده حذف می‌شود. مراحل و گزارش‌های پروژه‌های موجود بدون تغییر باقی می‌مانند. اگر مرحله دیگری به آن وابسته باشد، حذف مسدود می‌شود.', { title: 'حذف مرحله از قالب', confirmText: 'حذف مرحله', tone: 'danger' })) return;
                await api(`template-steps/${action.dataset.deleteTemplateStage}/delete`, { method: 'POST' });
                await Promise.all([loadTemplates(), loadReference()]);
            }
            else if (action.dataset.removeTeamMember) {
                if (!await window.AppModal.confirm('عضویت کاربر از این تیم حذف می‌شود؛ مسئولیت تسک‌های قبلی او تغییر نمی‌کند.', { title: 'حذف عضو تیم', confirmText: 'حذف عضویت', tone: 'danger' })) return;
                const teamId = Number(action.dataset.removeTeamMember);
                await api(`teams/${teamId}/members/remove`, { method: 'POST', body: { user_id: Number(action.dataset.userId) } });
                await loadReference();
                const team = state.reference.teams.find(item => Number(item.id) === teamId);
                if (team) prepareTeam(team);
            }
            else if (action.dataset.toggleTeamLead) {
                const teamId = Number(action.dataset.toggleTeamLead);
                await api(`teams/${teamId}/members`, { method: 'POST', body: { user_id: Number(action.dataset.userId), is_lead: Number(action.dataset.isLead) !== 1 } });
                await loadReference();
                const team = state.reference.teams.find(item => Number(item.id) === teamId);
                if (team) prepareTeam(team);
            }
            else if (action.dataset.deleteProject) {
                if (!await window.AppModal.confirm('پروژه همراه تمام مراحل، تسک‌ها، گزارش‌ها، پیوست‌ها و تاریخچه آن برای همیشه حذف می‌شود.', { title: 'حذف کامل پروژه', confirmText: 'حذف پروژه', tone: 'danger' })) return;
                await api(`projects/${action.dataset.deleteProject}/delete`, { method: 'POST' });
                state.selectedProject = null;
                await Promise.all([loadProjects(), loadTasks(), loadOverview(), loadNotifications()]);
                go('projects');
            }
            else if (action.dataset.deleteTemplate) {
                if (!await window.AppModal.confirm('قالب و تمام مراحل آن حذف می‌شوند. قالب استفاده‌شده تا وقتی پروژه وابسته دارد قابل حذف نیست.', { title: 'حذف قالب گردش‌کار', confirmText: 'حذف قالب', tone: 'danger' })) return;
                await api(`templates/${action.dataset.deleteTemplate}/delete`, { method: 'POST' });
                state.selectedTemplate = null;
                root.querySelector('[data-template-editor]').innerHTML = '<div class="tf-empty"><strong>یک قالب را انتخاب کن</strong></div>';
                await Promise.all([loadTemplates(), loadReference()]);
            }
            else if (action.dataset.deleteTaskType) {
                if (!await window.AppModal.confirm('نوع وظیفه فقط زمانی حذف می‌شود که در هیچ قالب، مرحله یا تسکی استفاده نشده باشد.', { title: 'حذف نوع وظیفه', confirmText: 'حذف نوع وظیفه', tone: 'danger' })) return;
                await api(`task-types/${action.dataset.deleteTaskType}/delete`, { method: 'POST' });
                await loadReference();
            }
            else if (action.dataset.deleteCustomer) {
                if (!await window.AppModal.confirm('مشتری فقط در صورتی حذف می‌شود که پروژه وابسته‌ای نداشته باشد.', { title: 'حذف مشتری', confirmText: 'حذف مشتری', tone: 'danger' })) return;
                await api(`customers/${action.dataset.deleteCustomer}/delete`, { method: 'POST' });
                await loadReference();
            }
            else if (action.dataset.deleteRole) {
                if (!await window.AppModal.confirm('نقش فقط وقتی حذف می‌شود که هیچ کاربر یا مرحله قالبی به آن متصل نباشد.', { title: 'حذف نقش', confirmText: 'حذف نقش', tone: 'danger' })) return;
                await api(`roles/${action.dataset.deleteRole}/delete`, { method: 'POST' });
                await loadReference();
            }
            toast('عملیات با موفقیت انجام شد.');
        } catch (error) { toast(error.message, 'error'); }
        finally { action.disabled = false; }
    });

    root.addEventListener('change', async event => {
        if (event.target.matches('[data-role-choice]')) renderEffectivePermissions(event.target.closest('form'));
        if (event.target.matches('[data-task-filter-quick]')) {
            const form = event.target.closest('[data-task-filters]');
            syncTaskProjectFilters(event.target.name === 'scope');
            try { await loadTasks(serializeTaskFilters(form)); }
            catch (error) { toast(error.message, 'error'); }
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !root.querySelector('[data-task-filter-panel]')?.hidden) closeTaskFilters();
    });

    root.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        const submit = event.submitter || form.querySelector('[type="submit"],button:not([type])');
        if (submit) submit.disabled = true;
        try {
            if (form.matches('[data-project-filters]')) await loadProjects(new URLSearchParams(values(form)).toString());
            else if (form.matches('[data-task-filters]')) { await loadTasks(serializeTaskFilters(form)); closeTaskFilters(false); }
            else if (form.matches('[data-create-project]')) { const result = await api('projects', { method: 'POST', body: values(form) }); closeModal(form); await Promise.all([loadProjects(), loadOverview(), loadReference()]); await showProject(result.id); }
            else if (form.matches('[data-project-edit-form]')) { const data = values(form); const id = data.project_id; delete data.project_id; await api(`projects/${id}`, { method: 'POST', body: data }); closeModal(form); await Promise.all([loadProjects(), loadReference()]); await showProject(id); }
            else if (form.matches('[data-create-quick-task]')) { await api('tasks', { method: 'POST', body: values(form) }); closeModal(form); await Promise.all([loadTasks(), loadOverview(), loadNotifications()]); go('tasks'); }
            else if (form.matches('[data-edit-task]')) { const data = values(form); const id = data.task_id; delete data.task_id; await api(`tasks/${id}`, { method: 'POST', body: data }); await loadTasks(); await showTask(id); }
            else if (form.matches('[data-task-assignees]')) { const data = values(form); const id = data.task_id; delete data.task_id; await api(`tasks/${id}/assignees`, { method: 'POST', body: data }); await Promise.all([loadTasks(), loadNotifications()]); await showTask(id); }
            else if (form.matches('[data-save-template]')) { const data = values(form); const id = data.template_id; delete data.template_id; await api(id ? `templates/${id}` : 'templates', { method: 'POST', body: data }); closeModal(form); await Promise.all([loadTemplates(), loadReference()]); }
            else if (form.matches('[data-save-stage]')) { const data = values(form); const containerId = data.template_id; const stepId = data.step_id; delete data.template_id; delete data.step_id; const path = form.dataset.mode === 'project-create' ? `projects/${containerId}/stages` : (form.dataset.mode === 'template-edit' ? `template-steps/${stepId}` : `templates/${containerId}/steps`); await api(path, { method: 'POST', body: data }); closeModal(form); if (form.dataset.mode === 'project-create') { await Promise.all([loadProjects(), loadTasks()]); await showProject(containerId); } else await Promise.all([loadTemplates(), loadReference()]); }
            else if (form.matches('[data-save-project-dependencies]')) { const data = values(form); const id = data.step_id; delete data.step_id; await api(`order-steps/${id}/dependencies`, { method: 'POST', body: data }); closeModal(form); await showProject(state.selectedProject.id); }
            else if (form.matches('[data-save-task-type]')) { const data = values(form); const id = data.task_type_id; delete data.task_type_id; await api(id ? `task-types/${id}` : 'task-types', { method: 'POST', body: data }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-save-customer]')) { const data = values(form); const id = data.customer_id; delete data.customer_id; await api(id ? `customers/${id}` : 'customers', { method: 'POST', body: data }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-save-team]')) { const data = values(form); const id = data.team_id; delete data.team_id; await api(id ? `teams/${id}` : 'teams', { method: 'POST', body: data }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-team-member]')) { const data = values(form); const id = data.team_id; delete data.team_id; await api(`teams/${id}/members`, { method: 'POST', body: data }); form.reset(); await loadReference(); }
            else if (form.matches('[data-create-user]')) { await api('users', { method: 'POST', body: values(form) }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-user-edit-form]')) { const data = values(form); const id = data.user_id; delete data.user_id; await api(`users/${id}`, { method: 'POST', body: data }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-save-role]')) { const data = values(form); const id = data.role_id; const cloneFrom = form.dataset.cloneFrom; delete data.role_id; const path = id ? `roles/${id}` : (cloneFrom ? `roles/${cloneFrom}/clone` : 'roles'); await api(path, { method: 'POST', body: data }); closeModal(form); await loadReference(); }
            else if (form.matches('[data-project-member]')) { const id = form.dataset.projectMember; await api(`projects/${id}/members`, { method: 'POST', body: values(form) }); form.reset(); await Promise.all([loadReference(), loadProjects()]); await showProject(id); }
            else if (form.matches('[data-project-attachment]')) { const id = form.dataset.projectAttachment; await api(`projects/${id}/attachments`, { method: 'POST', body: new FormData(form) }); form.reset(); await showProject(id); }
            toast('اطلاعات ذخیره شد.');
        } catch (error) { toast(error.message, 'error'); }
        finally { if (submit) submit.disabled = false; }
    });

    const allowedViews = ['projects', 'tasks', 'archives', 'workflows', 'task-types', 'customers', 'teams', 'users', 'guide', 'notifications'];
    const requestedView = location.hash.replace('#', '');
    refreshAll()
        .then(async () => {
            go(allowedViews.includes(requestedView) ? requestedView : 'dashboard', true);
            const taskId = Number(new URLSearchParams(location.search).get('task') || 0);
            if (taskId > 0) await showTask(taskId);
        })
        .catch(error => toast(error.message, 'error'));
    const refreshNotificationState = () => {
        if (document.visibilityState !== 'visible') return;
        Promise.all([loadNotifications(), loadOverview()]).catch(() => null);
    };
    window.addEventListener('taskflow:notification', refreshNotificationState);
    document.addEventListener('visibilitychange', refreshNotificationState);
    window.setInterval(refreshNotificationState, 60000);
})();
