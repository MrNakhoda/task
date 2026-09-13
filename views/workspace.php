<?php
$pageTitle = 'مدیریت پروژه‌ها · ' . $appName;
$pageScript = $to('/assets/workflow.js');
?>
<section class="tf-app" data-workspace>
    <aside class="tf-sidebar">
        <div class="tf-product"><span>TF</span><div><strong>TaskFlow</strong><small>مدیریت پروژه</small></div></div>
        <nav class="tf-nav">
            <button class="is-active" data-nav-view="dashboard" type="button"><span>⌂</span> نمای کلی</button>
            <button data-nav-view="projects" type="button"><span>▣</span> پروژه‌ها</button>
            <button data-nav-view="tasks" type="button"><span>✓</span> وظایف من</button>
            <button data-nav-view="workflows" type="button"><span>⌘</span> گردش‌کارها</button>
            <div class="tf-nav-label">مدیریت</div>
            <button data-nav-view="teams" type="button"><span>♟</span> تیم‌ها</button>
            <button data-nav-view="users" type="button"><span>◎</span> کاربران و نقش‌ها</button>
            <button data-nav-view="notifications" type="button"><span>◉</span> اعلان‌ها <b data-nav-notifications></b></button>
        </nav>
        <div class="tf-user"><span><?= htmlspecialchars(mb_substr((string) ($user['name'] ?? 'U'), 0, 1), ENT_QUOTES, 'UTF-8') ?></span><div><strong><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></div></div>
    </aside>

    <main class="tf-main">
        <header class="tf-mobile-head"><button type="button" data-sidebar-toggle>☰</button><strong>TaskFlow</strong><button type="button" data-refresh>↻</button></header>
        <div class="tf-toast" data-message hidden></div>

        <section class="tf-view is-active" data-view="dashboard">
            <div class="tf-page-head"><div><span>امروز</span><h1>نمای کلی کارها</h1><p>پروژه‌های مرحله‌ای و تسک‌های سریع در یک نگاه.</p></div><div class="tf-head-actions"><button class="tf-button secondary" type="button" data-open-quick-task>+ تسک سریع</button><button class="tf-button" type="button" data-open-project>+ پروژه جدید</button></div></div>
            <div class="tf-metrics">
                <article><i class="blue">▣</i><div><span>پروژه‌های فعال</span><strong data-metric="orders_active">—</strong></div></article>
                <article><i class="orange">✓</i><div><span>وظایف باز</span><strong data-metric="tasks_open">—</strong></div></article>
                <article><i class="green">◆</i><div><span>پروژه‌های تکمیل</span><strong data-metric="orders_completed">—</strong></div></article>
                <article><i class="purple">◉</i><div><span>اعلان جدید</span><strong data-metric="notifications_unread">—</strong></div></article>
            </div>
            <div class="tf-dashboard-grid">
                <section class="tf-card"><div class="tf-card-head"><div><h2>پروژه‌های اخیر</h2><p>آخرین پروژه‌های ساخته‌شده</p></div><button class="tf-link" data-go="projects">مشاهده همه</button></div><div data-dashboard-projects></div></section>
                <section class="tf-card"><div class="tf-card-head"><div><h2>وظایف فوری</h2><p>شامل تسک‌های پروژه و تسک‌های مستقل</p></div><button class="tf-link" data-go="tasks">مشاهده همه</button></div><div data-dashboard-tasks></div></section>
            </div>
        </section>

        <section class="tf-view" data-view="projects">
            <div class="tf-page-head"><div><span>Workspace</span><h1>پروژه‌ها</h1><p>هر پروژه اعضا، مراحل، پیش‌نیازها و وظایف مستقل خودش را دارد.</p></div><button class="tf-button" type="button" data-open-project>+ ساخت پروژه</button></div>
            <form class="tf-filters" data-project-filters><input name="search" placeholder="جستجوی نام یا کد پروژه"><select name="status"><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="active">در حال اجرا</option><option value="completed">تکمیل‌شده</option></select><select name="priority_id" data-options="priorities"><option value="">همه اولویت‌ها</option></select><button class="tf-button secondary">فیلتر</button></form>
            <div class="tf-project-grid" data-project-list><div class="tf-loading">در حال دریافت پروژه‌ها…</div></div>
        </section>

        <section class="tf-view" data-view="project-detail">
            <button class="tf-back" type="button" data-go="projects">→ بازگشت به پروژه‌ها</button>
            <div data-project-detail><div class="tf-loading">در حال دریافت پروژه…</div></div>
        </section>

        <section class="tf-view" data-view="tasks">
            <div class="tf-page-head"><div><span>My Work</span><h1>وظایف من</h1><p>تسک‌های پروژه‌ها و تسک‌های سریع، تفکیک‌شده بر اساس وضعیت.</p></div><button class="tf-button" type="button" data-open-quick-task>+ تسک سریع مستقل</button></div>
            <form class="tf-filters" data-task-filters><select name="status"><option value="">همه وضعیت‌ها</option><option value="open">آماده شروع</option><option value="in_progress">در حال انجام</option><option value="completed">تکمیل‌شده</option></select><select name="task_type_id" data-options="task_types"><option value="">همه نوع وظیفه‌ها</option></select><input name="customer" placeholder="جستجوی مشتری"><button class="tf-button secondary">فیلتر</button></form>
            <div class="tf-task-columns" data-task-board></div>
        </section>

        <section class="tf-view" data-view="workflows">
            <div class="tf-page-head"><div><span>Templates</span><h1>قالب‌های گردش کار</h1><p>الگوی مراحل قابل استفاده مجدد برای پروژه‌های مختلف.</p></div><div class="tf-head-actions"><button class="tf-button secondary" data-open-task-type>+ نوع وظیفه</button><button class="tf-button" data-open-template>+ قالب جدید</button></div></div>
            <div class="tf-workflow-layout"><aside class="tf-card tf-template-list" data-template-list></aside><section class="tf-card tf-template-editor" data-template-editor><div class="tf-empty"><strong>یک قالب انتخاب کن</strong><p>مراحل و پیش‌نیازهای آن اینجا نمایش داده می‌شود.</p></div></section></div>
        </section>

        <section class="tf-view" data-view="teams">
            <div class="tf-page-head"><div><span>Teams</span><h1>تیم‌ها</h1><p>گروه‌بندی افراد شرکت برای تخصیص سریع‌تر کارها.</p></div><button class="tf-button" data-open-team>+ گروه جدید</button></div>
            <div class="tf-split"><section class="tf-card"><div class="tf-card-head"><div><h2>گروه‌های شرکت</h2><p>تیم‌های فعال</p></div></div><div class="tf-entity-list" data-team-list></div></section><section class="tf-card"><div class="tf-card-head"><div><h2>افزودن عضو به گروه</h2><p>هر نفر می‌تواند عضو چند گروه باشد.</p></div></div><form class="tf-form" data-team-member><label>گروه<select name="team_id" data-options="teams" required></select></label><label>کاربر<select name="user_id" data-options="users" required></select></label><label class="tf-check"><input type="checkbox" name="is_lead"> سرگروه باشد</label><button class="tf-button">افزودن عضو</button></form></section></div>
        </section>

        <section class="tf-view" data-view="users">
            <div class="tf-page-head"><div><span>Access</span><h1>کاربران و نقش‌ها</h1><p>ساخت حساب، تعیین مدیر و کنترل سطح دسترسی.</p></div><div class="tf-head-actions"><button class="tf-button secondary" data-open-role>+ نقش جدید</button><button class="tf-button" data-open-user>+ حساب جدید</button></div></div>
            <div class="tf-split"><section class="tf-card"><div class="tf-card-head"><div><h2>کاربران</h2><p>حساب‌های فعال سیستم</p></div></div><div class="tf-user-list" data-user-list></div></section><section class="tf-card"><div class="tf-card-head"><div><h2>نقش‌های دسترسی</h2><p>ادمین دسترسی کامل دارد.</p></div></div><div class="tf-entity-list" data-role-list></div></section></div>
        </section>

        <section class="tf-view" data-view="notifications">
            <div class="tf-page-head"><div><span>Updates</span><h1>اعلان‌ها</h1><p>تخصیص تسک و فعال‌شدن مرحله‌های جدید.</p></div><button class="tf-button secondary" data-read-notifications>خواندن همه</button></div>
            <section class="tf-card tf-notifications" data-notification-list></section>
        </section>
    </main>

    <dialog class="tf-dialog" data-modal="project"><form method="dialog" class="tf-modal-card" data-create-project><header><div><h2>ساخت پروژه جدید</h2><p>مثلاً «انگشتر گلوریا»</p></div><button value="cancel" type="button" data-close-modal>×</button></header><div class="tf-form-grid"><label>نام پروژه<input name="name" required placeholder="انگشتر گلوریا"></label><label>کد پروژه<input name="code" placeholder="GLORIA-RING"></label><label>قالب گردش کار<select name="workflow_template_id" data-options="templates" required></select></label><label>اولویت<select name="priority_id" data-options="priorities"><option value="">عادی</option></select></label><label>موعد انجام<input name="due_at" type="datetime-local"></label><label>مشتری<select name="customer_id" data-options="customers"><option value="">بدون مشتری</option></select></label><label class="span-2">اعضای پروژه<select name="member_ids" data-options="users" multiple required></select><small>با Ctrl می‌توانی چند نفر را انتخاب کنی.</small></label><label class="span-2">توضیحات<textarea name="description" rows="3"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button" type="submit">ساخت پروژه</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="quick-task"><form method="dialog" class="tf-modal-card" data-create-quick-task><header><div><h2>تسک سریع مستقل</h2><p>بدون ساخت پروژه؛ مثل «خرید سنگ»</p></div><button type="button" data-close-modal>×</button></header><div class="tf-form-grid"><label class="span-2">عنوان تسک<input name="title" required placeholder="خرید سنگ"></label><label>نوع وظیفه<select name="task_type_id" data-options="task_types" required></select></label><label>موعد انجام<input name="due_at" type="datetime-local"></label><label class="span-2">مسئول یا مسئولان<select name="user_ids" data-options="users" multiple required></select></label><label class="span-2">توضیحات<textarea name="description" rows="3"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت و تخصیص تسک</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="template"><form method="dialog" class="tf-modal-card compact" data-create-template><header><div><h2>قالب گردش کار جدید</h2><p>این قالب روی پروژه‌های مختلف قابل استفاده است.</p></div><button type="button" data-close-modal>×</button></header><label>نام قالب<input name="name" required placeholder="تولید محصول جدید"></label><label>توضیحات<textarea name="description" rows="3"></textarea></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت قالب</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="stage"><form method="dialog" class="tf-modal-card" data-create-stage><header><div><h2>افزودن مرحله</h2><p data-stage-template-name></p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="template_id"><div class="tf-form-grid"><label>نام مرحله<input name="name" required placeholder="طراحی سه‌بعدی"></label><label>نوع وظیفه<select name="task_type_id" data-options="task_types" required></select></label><label>وزن پیشرفت<input name="progress_weight" type="number" min="0.01" step="0.01" value="1"></label><label>ترتیب<input name="position" type="number" value="10"></label><label class="span-2">پیش‌نیازها<select name="dependency_ids" multiple data-stage-dependencies></select><small>بعد از تکمیل همه موارد انتخاب‌شده فعال می‌شود.</small></label><label class="span-2">مسئولان پیش‌فرض<select name="user_ids" data-options="users" multiple></select></label><label class="span-2">شرح مرحله<textarea name="description" rows="2"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">افزودن مرحله</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="task-type"><form method="dialog" class="tf-modal-card compact" data-create-task-type><header><div><h2>نوع وظیفه جدید</h2><p>برای دسته‌بندی و فیلتر تسک‌ها</p></div><button type="button" data-close-modal>×</button></header><label>نام<input name="name" required placeholder="طراحی"></label><label>کلید انگلیسی<input name="slug" required placeholder="design"></label><label>رنگ<input name="color" type="color" value="#3157d5"></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="team"><form method="dialog" class="tf-modal-card compact" data-create-team><header><div><h2>ساخت گروه</h2><p>مثلاً تیم طراحی</p></div><button type="button" data-close-modal>×</button></header><label>نام گروه<input name="name" required></label><label>توضیحات<textarea name="description"></textarea></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت گروه</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="user"><form method="dialog" class="tf-modal-card compact" data-create-user><header><div><h2>حساب کاربری جدید</h2><p>کاربر یا مدیر سیستم</p></div><button type="button" data-close-modal>×</button></header><label>نام<input name="name" required></label><label>ایمیل<input name="email" type="email" required></label><label>رمز عبور<input name="password" type="password" minlength="10" required></label><label>نقش<select name="role_key"><option value="user">کاربر</option><option value="manager">مدیر پروژه</option><option value="admin">ادمین کامل</option></select></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت حساب</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="role"><form method="dialog" class="tf-modal-card compact" data-create-role><header><div><h2>تعریف نقش جدید</h2><p>مجوزها را با ویرگول جدا کن.</p></div><button type="button" data-close-modal>×</button></header><label>کلید نقش<input name="key_name" required placeholder="supervisor"></label><label>نام نمایشی<input name="display_name" required placeholder="سرپرست تولید"></label><label>مجوزها<input name="permissions" placeholder="tasks.manage, orders.manage"></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">تعریف نقش</button></footer></form></dialog>
</section>
