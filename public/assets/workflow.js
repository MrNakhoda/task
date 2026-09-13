(() => {
    'use strict';

    const root = document.querySelector('[data-workspace]');
    if (!root) return;

    const state = { reference: {}, projects: [], tasks: [], templates: [], notifications: [], selectedTemplate: null, selectedProject: null };
    const labels = { draft: 'پیش‌نویس', active: 'در حال اجرا', completed: 'تکمیل‌شده', open: 'آماده شروع', in_progress: 'در حال انجام', pending: 'منتظر پیش‌نیاز', disabled: 'غیرفعال', cancelled: 'لغوشده' };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
    const endpoint = path => new URL(`api/v1/workflow/${path.replace(/^\//, '')}`, document.baseURI).toString();

    async function api(path, options = {}) {
        const config = { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', ...(options.headers || {}) } };
        if (config.method && config.method !== 'GET') config.headers['X-CSRF-Token'] = csrf();
        if (config.body && !(config.body instanceof FormData)) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(config.body);
        }
        const response = await fetch(endpoint(path), config);
        const payload = await response.json().catch(() => ({ ok: false, error: 'پاسخ سرور معتبر نیست.' }));
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
            if (data[key] !== undefined) data[key] = Array.isArray(data[key]) ? [...data[key], value] : [data[key], value];
            else data[key] = value;
        });
        form.querySelectorAll('select[multiple]').forEach(select => { data[select.name] = [...select.selectedOptions].map(option => Number(option.value)); });
        form.querySelectorAll('input[type="checkbox"]').forEach(input => { data[input.name] = input.checked; });
        return data;
    }

    function optionTitle(type, item) {
        if (type === 'users') return `${item.name} — ${item.email}`;
        if (type === 'roles') return item.display_name;
        return item.name || item.key_name;
    }

    function fillOptions() {
        root.querySelectorAll('[data-options]').forEach(select => {
            const type = select.dataset.options;
            const selected = [...select.selectedOptions].map(option => option.value);
            const blank = [...select.options].filter(option => option.value === '').map(option => option.outerHTML).join('');
            select.innerHTML = blank + (state.reference[type] || []).map(item => `<option value="${Number(item.id)}">${esc(optionTitle(type, item))}</option>`).join('');
            [...select.options].forEach(option => { option.selected = selected.includes(option.value); });
        });
    }

    function go(view) {
        root.querySelectorAll('[data-view]').forEach(section => section.classList.toggle('is-active', section.dataset.view === view));
        root.querySelectorAll('[data-nav-view]').forEach(button => button.classList.toggle('is-active', button.dataset.navView === view));
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

    async function loadReference() {
        const payload = await api('reference');
        state.reference = payload.reference;
        fillOptions();
        renderManagement();
    }

    async function loadOverview() {
        const payload = await api('overview');
        Object.entries(payload.overview).forEach(([key, value]) => {
            const node = root.querySelector(`[data-metric="${key}"]`);
            if (node) node.textContent = Number(value).toLocaleString('fa-IR');
        });
        const badge = root.querySelector('[data-nav-notifications]');
        const count = Number(payload.overview.notifications_unread || 0);
        badge.textContent = count ? count.toLocaleString('fa-IR') : '';
    }

    async function loadProjects(query = '') {
        const payload = await api(`projects${query ? `?${query}` : ''}`);
        state.projects = payload.projects;
        renderProjects();
        renderDashboard();
    }

    async function loadTasks(query = '') {
        const payload = await api(`tasks${query ? `?${query}` : ''}`);
        state.tasks = payload.tasks;
        renderTasks();
        renderDashboard();
    }

    async function loadTemplates() {
        const payload = await api('templates');
        state.templates = payload.templates;
        renderTemplates();
        if (state.selectedTemplate) selectTemplate(state.selectedTemplate.id);
    }

    async function loadNotifications() {
        const payload = await api('notifications');
        state.notifications = payload.notifications;
        renderNotifications();
    }

    function statusBadge(status) {
        return `<span class="tf-status status-${esc(status)}">${esc(labels[status] || status)}</span>`;
    }

    function progress(value) {
        const percent = Math.max(0, Math.min(100, Number(value || 0)));
        return `<div class="tf-progress"><span style="width:${percent}%"></span></div><b>${percent.toLocaleString('fa-IR')}٪</b>`;
    }

    function projectCard(project, compact = false) {
        return `<article class="tf-project-card ${compact ? 'compact' : ''}" data-project-open="${Number(project.id)}">
            <header><span class="tf-project-icon">${esc(String(project.title || 'پ').slice(0, 1))}</span>${statusBadge(project.status)}</header>
            <h3>${esc(project.title)}</h3><p class="tf-code">${esc(project.order_number)}</p>
            <div class="tf-progress-row">${progress(project.progress_percent)}</div>
            <footer><span>♟ ${Number(project.member_count || 0).toLocaleString('fa-IR')} عضو</span><span>⌘ ${Number(project.stage_count || 0).toLocaleString('fa-IR')} مرحله</span>${project.due_at ? `<span>◷ ${esc(String(project.due_at).slice(0, 10))}</span>` : ''}</footer>
        </article>`;
    }

    function renderProjects() {
        const list = root.querySelector('[data-project-list]');
        list.innerHTML = state.projects.length ? state.projects.map(project => projectCard(project)).join('') : '<div class="tf-empty span-all"><strong>هنوز پروژه‌ای ساخته نشده</strong><p>اولین پروژه را با اعضا و قالب گردش کار ایجاد کن.</p><button class="tf-button" data-open-project>ساخت پروژه</button></div>';
    }

    function taskCard(task) {
        const standalone = Number(task.is_standalone) === 1;
        return `<article class="tf-task-card">
            <header><span class="tf-type" style="--type-color:${esc(task.task_type_color)}">${esc(task.task_type_name)}</span>${standalone ? '<span class="tf-independent">مستقل</span>' : ''}</header>
            <h3>${esc(task.title)}</h3>
            <p>${standalone ? 'بدون پروژه' : `پروژه: ${esc(task.order_title)}`}</p>
            <small>مسئول: ${esc(task.assignee_names || 'تعیین نشده')}</small>
            ${task.due_at ? `<small>موعد: ${esc(String(task.due_at).slice(0, 16))}</small>` : ''}
            <footer>${task.status === 'open' ? `<button class="tf-button tiny secondary" data-task-start="${Number(task.id)}">شروع</button>` : ''}${['open', 'in_progress'].includes(task.status) ? `<button class="tf-link" data-task-report="${Number(task.id)}">گزارش</button><button class="tf-button tiny" data-task-complete="${Number(task.id)}">تکمیل</button>` : statusBadge(task.status)}</footer>
        </article>`;
    }

    function renderTasks() {
        const board = root.querySelector('[data-task-board]');
        const columns = [
            ['open', 'آماده شروع'],
            ['in_progress', 'در حال انجام'],
            ['completed', 'انجام‌شده'],
        ];
        board.innerHTML = columns.map(([status, title]) => {
            const tasks = state.tasks.filter(task => task.status === status);
            return `<section class="tf-task-column"><header><h2>${title}</h2><span>${tasks.length.toLocaleString('fa-IR')}</span></header><div>${tasks.map(taskCard).join('') || '<p class="tf-column-empty">موردی نیست</p>'}</div></section>`;
        }).join('');
    }

    function renderDashboard() {
        const projects = root.querySelector('[data-dashboard-projects]');
        const tasks = root.querySelector('[data-dashboard-tasks]');
        projects.innerHTML = state.projects.slice(0, 4).map(project => `<button class="tf-row" data-project-open="${Number(project.id)}"><span class="tf-project-icon small">${esc(String(project.title).slice(0, 1))}</span><span><strong>${esc(project.title)}</strong><small>${esc(project.order_number)}</small></span><span class="tf-row-progress">${Number(project.progress_percent).toLocaleString('fa-IR')}٪</span></button>`).join('') || '<div class="tf-empty small">پروژه‌ای وجود ندارد.</div>';
        const openTasks = state.tasks.filter(task => ['open', 'in_progress'].includes(task.status)).slice(0, 5);
        tasks.innerHTML = openTasks.map(task => `<div class="tf-row"><i class="tf-check-dot"></i><span><strong>${esc(task.title)}</strong><small>${Number(task.is_standalone) === 1 ? 'تسک مستقل' : esc(task.order_title)}</small></span>${statusBadge(task.status)}</div>`).join('') || '<div class="tf-empty small">وظیفه بازی وجود ندارد.</div>';
    }

    async function showProject(projectId) {
        go('project-detail');
        const target = root.querySelector('[data-project-detail]');
        target.innerHTML = '<div class="tf-loading">در حال دریافت پروژه…</div>';
        const payload = await api(`projects/${projectId}`);
        const project = state.selectedProject = payload.project;
        const stageNames = Object.fromEntries((project.steps || []).map(stage => [Number(stage.id), stage.name]));
        target.innerHTML = `<header class="tf-project-hero"><div><div class="tf-project-kicker">${esc(project.order_number)} · ${esc(project.priority_name || 'اولویت عادی')}</div><h1>${esc(project.title)}</h1><p>${esc(project.customer_names || '')}</p></div><div class="tf-project-score"><strong>${Number(project.progress_percent).toLocaleString('fa-IR')}٪</strong><span>پیشرفت پروژه</span></div></header>
            <div class="tf-project-toolbar">${statusBadge(project.status)}${project.status === 'draft' ? `<button class="tf-button" data-project-activate="${Number(project.id)}">شروع پروژه و ساخت تسک‌ها</button>` : ''}${project.status === 'active' ? `<button class="tf-button secondary" data-open-project-stage="${Number(project.id)}">+ مرحله جدید</button>` : ''}</div>
            <section class="tf-card tf-members"><div class="tf-card-head"><div><h2>اعضای پروژه</h2><p>افرادی که در «${esc(project.title)}» حضور دارند.</p></div></div><div class="tf-member-pills">${(project.members || []).map(member => `<span><i>${esc(String(member.name).slice(0, 1))}</i>${esc(member.name)}<small>${esc(member.role_label || '')}</small></span>`).join('') || '<em>عضوی انتخاب نشده است.</em>'}</div><form data-project-member="${Number(project.id)}" class="tf-inline-form"><select name="user_id" data-dynamic-users required>${(state.reference.users || []).map(user => `<option value="${Number(user.id)}">${esc(user.name)}</option>`).join('')}</select><input name="role_label" placeholder="نقش در پروژه؛ اختیاری"><button class="tf-button tiny">افزودن عضو</button></form></section>
            <section class="tf-card tf-stages"><div class="tf-card-head"><div><h2>مراحل و پیش‌نیازها</h2><p>هر مرحله پس از تکمیل تمام پیش‌نیازهایش فعال می‌شود.</p></div></div><div class="tf-stage-flow">${(project.steps || []).map((stage, index) => {
                const dependencies = String(stage.dependency_ids || '').split(',').filter(Boolean).map(id => stageNames[Number(id)] || `#${id}`);
                return `<article class="tf-stage status-${esc(stage.status)}"><header><span>${(index + 1).toLocaleString('fa-IR')}</span>${statusBadge(stage.status)}</header><h3>${esc(stage.name)}</h3><p>${esc(stage.task_type_name)}</p><div class="tf-dependency">${dependencies.length ? `پیش‌نیاز: ${esc(dependencies.join('، '))}` : 'بدون پیش‌نیاز؛ شروع هم‌زمان'}</div>${stage.task_id ? `<div class="tf-stage-task"><strong>${esc(stage.assignee_names || 'بدون مسئول')}</strong>${statusBadge(stage.task_status)}</div>` : '<div class="tf-stage-task muted">تسک هنوز ساخته نشده</div>'}</article>`;
            }).join('') || '<div class="tf-empty span-all"><strong>مرحله‌ها پس از فعال‌سازی ساخته می‌شوند</strong><p>پروژه از روی قالب انتخاب‌شده ساخته خواهد شد.</p></div>'}</div></section>
            <section class="tf-card"><div class="tf-card-head"><div><h2>تاریخچه پروژه</h2><p>رویدادها حذف یا بازنویسی نمی‌شوند.</p></div></div><div class="tf-timeline">${(project.history || []).map(item => `<article><i></i><div><strong>${esc(item.message)}</strong><small>${esc(item.actor_name || 'سیستم')} · ${esc(item.created_at)}</small></div></article>`).join('') || '<div class="tf-empty small">رویدادی ثبت نشده.</div>'}</div></section>`;
    }

    function renderTemplates() {
        const list = root.querySelector('[data-template-list]');
        list.innerHTML = `<header><h2>قالب‌ها</h2><span>${state.templates.length.toLocaleString('fa-IR')}</span></header>` + (state.templates.map(template => `<button class="tf-template-item ${state.selectedTemplate?.id == template.id ? 'is-active' : ''}" data-template-select="${Number(template.id)}"><span><strong>${esc(template.name)}</strong><small>${Number(template.step_count).toLocaleString('fa-IR')} مرحله · نسخه ${Number(template.version).toLocaleString('fa-IR')}</small></span><b>‹</b></button>`).join('') || '<div class="tf-empty small">هنوز قالبی وجود ندارد.</div>');
    }

    function selectTemplate(templateId) {
        const template = state.templates.find(item => Number(item.id) === Number(templateId));
        if (!template) return;
        state.selectedTemplate = template;
        renderTemplates();
        const editor = root.querySelector('[data-template-editor]');
        const names = Object.fromEntries((template.steps || []).map(step => [Number(step.id), step.name]));
        editor.innerHTML = `<header class="tf-editor-head"><div><span>قالب گردش کار</span><h2>${esc(template.name)}</h2><p>${esc(template.description || 'بدون توضیحات')}</p></div><button class="tf-button" data-open-template-stage="${Number(template.id)}">+ افزودن مرحله</button></header><div class="tf-template-stages">${(template.steps || []).map((step, index) => `<article><span>${(index + 1).toLocaleString('fa-IR')}</span><div><h3>#${Number(step.id)} · ${esc(step.name)}</h3><p>${esc(step.task_type_name)} · وزن ${Number(step.progress_weight).toLocaleString('fa-IR')}</p><small>${step.dependencies.length ? `بعد از: ${step.dependencies.map(id => esc(names[Number(id)] || `#${id}`)).join('، ')}` : 'بدون پیش‌نیاز؛ هم‌زمان با شروع پروژه'}</small></div></article>`).join('') || '<div class="tf-empty"><strong>قالب هنوز مرحله‌ای ندارد</strong><p>اولین مرحله را اضافه کن.</p></div>'}</div>`;
    }

    function renderManagement() {
        const teams = root.querySelector('[data-team-list]');
        const users = root.querySelector('[data-user-list]');
        const roles = root.querySelector('[data-role-list]');
        if (teams) teams.innerHTML = (state.reference.teams || []).map(team => `<article><i>♟</i><span><strong>${esc(team.name)}</strong><small>${esc(team.description || 'بدون توضیحات')}</small></span>${statusBadge(team.is_active == 1 ? 'active' : 'disabled')}</article>`).join('') || '<div class="tf-empty small">گروهی وجود ندارد.</div>';
        if (users) users.innerHTML = (state.reference.users || []).map(user => `<article><i>${esc(String(user.name).slice(0, 1))}</i><span><strong>${esc(user.name)}</strong><small>${esc(user.email)}</small></span>${statusBadge(user.status)}</article>`).join('') || '<div class="tf-empty small">کاربری وجود ندارد.</div>';
        if (roles) roles.innerHTML = (state.reference.roles || []).map(role => `<article><i>◎</i><span><strong>${esc(role.display_name)}</strong><small>${esc(role.key_name)}</small></span>${statusBadge(role.is_active == 1 ? 'active' : 'disabled')}</article>`).join('');
    }

    function renderNotifications() {
        const list = root.querySelector('[data-notification-list]');
        list.innerHTML = state.notifications.map(item => `<article class="tf-notification ${item.read_at ? '' : 'unread'}"><i>◉</i><div><strong>${esc(item.title)}</strong><p>${esc(item.body || '')}</p><small>${esc(item.created_at)}</small></div></article>`).join('') || '<div class="tf-empty">اعلانی وجود ندارد.</div>';
    }

    async function refreshAll() {
        await loadReference();
        await Promise.all([loadOverview(), loadProjects(), loadTasks(), loadTemplates(), loadNotifications()]);
    }

    root.addEventListener('click', async event => {
        const nav = event.target.closest('[data-nav-view],[data-go]');
        if (nav) { go(nav.dataset.navView || nav.dataset.go); return; }
        if (event.target.closest('[data-sidebar-toggle]')) { root.classList.toggle('sidebar-open'); return; }
        const close = event.target.closest('[data-close-modal]');
        if (close) { closeModal(close); return; }
        if (event.target.closest('[data-open-project]')) { openModal('project'); return; }
        if (event.target.closest('[data-open-quick-task]')) { openModal('quick-task'); return; }
        if (event.target.closest('[data-open-template]')) { openModal('template'); return; }
        if (event.target.closest('[data-open-task-type]')) { openModal('task-type'); return; }
        if (event.target.closest('[data-open-team]')) { openModal('team'); return; }
        if (event.target.closest('[data-open-user]')) { openModal('user'); return; }
        if (event.target.closest('[data-open-role]')) { openModal('role'); return; }
        const projectLink = event.target.closest('[data-project-open]');
        if (projectLink) { await showProject(projectLink.dataset.projectOpen).catch(error => toast(error.message, 'error')); return; }
        const templateLink = event.target.closest('[data-template-select]');
        if (templateLink) { selectTemplate(templateLink.dataset.templateSelect); return; }
        const templateStage = event.target.closest('[data-open-template-stage]');
        if (templateStage) {
            const template = state.templates.find(item => Number(item.id) === Number(templateStage.dataset.openTemplateStage));
            const form = root.querySelector('[data-create-stage]');
            form.reset(); form.dataset.mode = 'template'; form.elements.template_id.value = template.id;
            root.querySelector('[data-stage-template-name]').textContent = template.name;
            root.querySelector('[data-stage-dependencies]').innerHTML = template.steps.map(step => `<option value="${Number(step.id)}">${esc(step.name)}</option>`).join('');
            openModal('stage'); return;
        }
        const projectStage = event.target.closest('[data-open-project-stage]');
        if (projectStage) {
            const form = root.querySelector('[data-create-stage]');
            form.reset(); form.dataset.mode = 'project'; form.elements.template_id.value = projectStage.dataset.openProjectStage;
            root.querySelector('[data-stage-template-name]').textContent = `پروژه ${state.selectedProject.title}`;
            root.querySelector('[data-stage-dependencies]').innerHTML = (state.selectedProject.steps || []).filter(step => step.status !== 'disabled').map(step => `<option value="${Number(step.id)}">${esc(step.name)}</option>`).join('');
            openModal('stage'); return;
        }
        const action = event.target.closest('[data-task-start],[data-task-report],[data-task-complete],[data-project-activate],[data-read-notifications],[data-refresh]');
        if (!action) return;
        action.disabled = true;
        try {
            if (action.matches('[data-refresh]')) await refreshAll();
            else if (action.matches('[data-read-notifications]')) { await api('notifications/read', { method: 'POST' }); await Promise.all([loadNotifications(), loadOverview()]); }
            else if (action.dataset.taskStart) { await api(`tasks/${action.dataset.taskStart}/start`, { method: 'POST' }); await Promise.all([loadTasks(), loadOverview()]); }
            else if (action.dataset.taskReport) { const report = prompt('گزارش انجام کار:'); if (report) await api(`tasks/${action.dataset.taskReport}/reports`, { method: 'POST', body: { report_text: report } }); }
            else if (action.dataset.taskComplete) { const report = prompt('گزارش نهایی؛ اختیاری:') || ''; await api(`tasks/${action.dataset.taskComplete}/complete`, { method: 'POST', body: { report_text: report } }); await Promise.all([loadTasks(), loadProjects(), loadOverview(), loadNotifications()]); }
            else if (action.dataset.projectActivate && confirm('پروژه شروع شود و تسک مراحل آماده ساخته شوند؟')) { await api(`projects/${action.dataset.projectActivate}/activate`, { method: 'POST' }); await Promise.all([loadProjects(), loadTasks(), loadOverview(), loadNotifications()]); await showProject(action.dataset.projectActivate); }
            toast('انجام شد.');
        } catch (error) { toast(error.message, 'error'); }
        finally { action.disabled = false; }
    });

    root.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            if (form.matches('[data-project-filters]')) await loadProjects(new URLSearchParams(values(form)).toString());
            else if (form.matches('[data-task-filters]')) await loadTasks(new URLSearchParams(values(form)).toString());
            else if (form.matches('[data-create-project]')) { const result = await api('projects', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await Promise.all([loadProjects(), loadOverview()]); await showProject(result.id); }
            else if (form.matches('[data-create-quick-task]')) { await api('tasks', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await Promise.all([loadTasks(), loadOverview(), loadNotifications()]); go('tasks'); }
            else if (form.matches('[data-create-template]')) { await api('templates', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await Promise.all([loadTemplates(), loadReference()]); }
            else if (form.matches('[data-create-stage]')) { const data = values(form); const id = data.template_id; delete data.template_id; const path = form.dataset.mode === 'project' ? `projects/${id}/stages` : `templates/${id}/steps`; await api(path, { method: 'POST', body: data }); closeModal(form); if (form.dataset.mode === 'project') { await Promise.all([loadProjects(), loadTasks()]); await showProject(id); } else await loadTemplates(); }
            else if (form.matches('[data-create-task-type]')) { await api('task-types', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await loadReference(); }
            else if (form.matches('[data-create-team]')) { await api('teams', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await loadReference(); }
            else if (form.matches('[data-team-member]')) { const data = values(form); const id = data.team_id; delete data.team_id; await api(`teams/${id}/members`, { method: 'POST', body: data }); form.reset(); }
            else if (form.matches('[data-create-user]')) { await api('users', { method: 'POST', body: values(form) }); closeModal(form); form.reset(); await loadReference(); }
            else if (form.matches('[data-create-role]')) { const data = values(form); data.permissions = String(data.permissions || '').split(',').map(item => item.trim()).filter(Boolean); await api('roles', { method: 'POST', body: data }); closeModal(form); form.reset(); await loadReference(); }
            else if (form.matches('[data-project-member]')) { const id = form.dataset.projectMember; await api(`projects/${id}/members`, { method: 'POST', body: values(form) }); form.reset(); await showProject(id); }
            toast('اطلاعات با موفقیت ذخیره شد.');
        } catch (error) { toast(error.message, 'error'); }
        finally { if (submit) submit.disabled = false; }
    });

    const requestedView = location.hash.replace('#', '');
    if (['projects', 'tasks', 'workflows', 'teams', 'users', 'notifications'].includes(requestedView)) go(requestedView);
    refreshAll().catch(error => toast(error.message, 'error'));
})();
