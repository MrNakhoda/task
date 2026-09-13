<?php
$pageTitle = 'فضای کار · ' . $appName;
$pageScript = $to('/assets/workflow.js');
?>
<section class="workspace-page" data-workspace>
    <div class="container workspace-stack">
        <header class="workspace-hero">
            <div><span class="eyebrow eyebrow-light">TaskFlow Workspace</span><h1>مرکز سفارش‌ها و کارهای تیم</h1><p><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>، جریان کار امروز اینجاست.</p></div>
            <button class="btn btn-light" type="button" data-refresh>به‌روزرسانی</button>
        </header>

        <div class="workspace-alert" data-message hidden></div>
        <section class="workspace-metrics" aria-label="خلاصه وضعیت">
            <article><span>سفارش فعال</span><strong data-metric="orders_active">—</strong></article>
            <article><span>سفارش تکمیل</span><strong data-metric="orders_completed">—</strong></article>
            <article><span>وظیفه باز</span><strong data-metric="tasks_open">—</strong></article>
            <article><span>اعلان جدید</span><strong data-metric="notifications_unread">—</strong></article>
        </section>

        <nav class="workspace-tabs" aria-label="بخش‌ها">
            <button class="is-active" type="button" data-tab="tasks">وظایف من</button>
            <button type="button" data-tab="orders">سفارش‌ها</button>
            <button type="button" data-tab="templates">گردش کار</button>
            <button type="button" data-tab="people">تیم و دسترسی</button>
            <button type="button" data-tab="notifications">اعلان‌ها</button>
        </nav>

        <section class="workspace-panel is-active" data-panel="tasks" id="tasks">
            <div class="panel-heading"><div><h2>وظایف</h2><p>وظایف تخصیص‌یافته از همه سفارش‌ها، قابل فیلتر بر اساس نوع و وضعیت.</p></div></div>
            <form class="filter-row" data-filter-tasks>
                <select name="status"><option value="">همه وضعیت‌ها</option><option value="open">باز</option><option value="in_progress">در حال انجام</option><option value="completed">تکمیل‌شده</option></select>
                <select name="task_type_id" data-options="task_types"><option value="">همه نوع‌ها</option></select>
                <input name="customer" placeholder="نام مشتری">
                <button class="btn btn-secondary" type="submit">فیلتر</button>
            </form>
            <div class="task-board" data-task-list><div class="loading">در حال دریافت وظایف…</div></div>
        </section>

        <section class="workspace-panel" data-panel="orders" id="orders">
            <div class="panel-heading"><div><h2>سفارش‌ها و پروژه‌ها</h2><p>پیشرفت مرحله‌ای از ۰ تا ۱۰۰ درصد و شروع خودکار مراحل آماده.</p></div><button class="btn" type="button" data-toggle-form="order-form">سفارش جدید</button></div>
            <form class="quick-form" data-form="order-form" data-create-order hidden>
                <h3>ساخت سفارش پیش‌نویس</h3>
                <div class="form-grid">
                    <label>عنوان<input name="title" required></label>
                    <label>شماره سفارش<input name="order_number" placeholder="خودکار"></label>
                    <label>قالب<select name="workflow_template_id" data-options="templates" required></select></label>
                    <label>پروژه<select name="project_id" data-options="projects"><option value="">بدون پروژه</option></select></label>
                    <label>اولویت<select name="priority_id" data-options="priorities"><option value="">بدون اولویت</option></select></label>
                    <label>مشتری<select name="customer_id" data-options="customers"><option value="">بدون مشتری</option></select></label>
                    <label>وزن<input type="number" min="0" step="0.001" name="weight"></label>
                    <label>موعد<input type="datetime-local" name="due_at"></label>
                </div>
                <label>توضیحات<textarea name="details" rows="2"></textarea></label>
                <button class="btn" type="submit">ذخیره پیش‌نویس</button>
            </form>
            <form class="filter-row" data-filter-orders>
                <select name="status"><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="active">فعال</option><option value="completed">تکمیل‌شده</option></select>
                <select name="priority_id" data-options="priorities"><option value="">همه اولویت‌ها</option></select>
                <input name="customer" placeholder="نام مشتری">
                <input type="number" step="0.001" name="weight" placeholder="وزن">
                <button class="btn btn-secondary" type="submit">فیلتر</button>
            </form>
            <div class="order-list" data-order-list><div class="loading">در حال دریافت سفارش‌ها…</div></div>
        </section>

        <section class="workspace-panel" data-panel="templates" id="templates">
            <div class="panel-heading"><div><h2>قالب گردش کار</h2><p>مرحله‌ها، پیش‌نیازها، نوع وظیفه و مسئولان کاملاً داینامیک هستند.</p></div></div>
            <div class="management-grid">
                <form class="quick-form" data-simple-create="task-types"><h3>نوع وظیفه</h3><label>نام<input name="name" required></label><label>کلید<input name="slug" required placeholder="quality_control"></label><label>رنگ<input name="color" type="color" value="#3157d5"></label><button class="btn" type="submit">افزودن نوع</button></form>
                <form class="quick-form" data-simple-create="templates"><h3>قالب جدید</h3><label>نام<input name="name" required></label><label>توضیح<textarea name="description"></textarea></label><button class="btn" type="submit">ساخت قالب</button></form>
                <form class="quick-form span-2" data-create-template-step><h3>مرحله جدید قالب</h3><div class="form-grid"><label>قالب<select name="template_id" data-options="templates" required></select></label><label>نوع وظیفه<select name="task_type_id" data-options="task_types" required></select></label><label>نام مرحله<input name="name" required></label><label>ترتیب<input type="number" name="position" value="10"></label><label>وزن پیشرفت<input type="number" name="progress_weight" min="0.01" step="0.01" value="1"></label><label>شناسه پیش‌نیازها<input name="dependency_ids" placeholder="مثال: 1,2"></label><label class="span-2">مسئولان<select name="user_ids" data-options="users" multiple></select></label></div><button class="btn" type="submit">افزودن مرحله</button></form>
            </div>
            <div class="template-list" data-template-list></div>
        </section>

        <section class="workspace-panel" data-panel="people" id="people">
            <div class="panel-heading"><div><h2>تیم، مشتری و دسترسی</h2><p>ساخت حساب، ادمین، نقش، گروه، پروژه و مشتری.</p></div></div>
            <div class="management-grid">
                <form class="quick-form" data-create-user><h3>حساب کاربری</h3><label>نام<input name="name" required></label><label>ایمیل<input type="email" name="email" required></label><label>رمز<input type="password" name="password" minlength="10" required></label><label>نقش<select name="role_key"><option value="user">کاربر</option><option value="manager">مدیر</option><option value="admin">ادمین کامل</option></select></label><button class="btn" type="submit">ساخت حساب</button></form>
                <form class="quick-form" data-simple-create="teams"><h3>گروه</h3><label>نام<input name="name" required></label><label>توضیح<textarea name="description"></textarea></label><button class="btn" type="submit">ساخت گروه</button></form>
                <form class="quick-form" data-simple-create="projects"><h3>پروژه</h3><label>نام<input name="name" required></label><label>کد<input name="code"></label><label>توضیح<textarea name="description"></textarea></label><button class="btn" type="submit">ساخت پروژه</button></form>
                <form class="quick-form" data-simple-create="customers"><h3>مشتری</h3><label>نام<input name="name" required></label><label>تلفن<input name="phone"></label><label>ایمیل<input type="email" name="email"></label><button class="btn" type="submit">ثبت مشتری</button></form>
                <form class="quick-form" data-create-role><h3>نقش سفارشی</h3><label>کلید<input name="key_name" required placeholder="supervisor"></label><label>عنوان<input name="display_name" required></label><label>مجوزها<input name="permissions" placeholder="tasks.manage,orders.manage"></label><button class="btn" type="submit">تعریف نقش</button></form>
                <form class="quick-form" data-add-member><h3>عضویت گروه</h3><label>گروه<select name="team_id" data-options="teams" required></select></label><label>کاربر<select name="user_id" data-options="users" required></select></label><label class="check"><input type="checkbox" name="is_lead"> سرگروه</label><button class="btn" type="submit">افزودن عضو</button></form>
            </div>
        </section>

        <section class="workspace-panel" data-panel="notifications" id="notifications">
            <div class="panel-heading"><div><h2>اعلان‌ها</h2><p>ایجاد وظیفه، تغییر مسئول و فعال‌شدن مراحل بعدی.</p></div><button class="btn btn-secondary" type="button" data-read-notifications>خواندن همه</button></div>
            <div class="notification-list" data-notification-list></div>
        </section>
    </div>
</section>
