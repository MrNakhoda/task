<?php
$pageTitle = 'مدیریت پروژه‌ها · ' . $appName;
$pageScript = $to('/assets/workflow.js') . '?v=' . (string) @filemtime(APP_ROOT . '/public/assets/workflow.js');
?>
<section class="tf-app" data-workspace>
    <svg class="tf-icon-sprite" aria-hidden="true">
        <symbol id="tf-i-home" viewBox="0 0 24 24"><path d="M3 10.8 12 3l9 7.8v9.7a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5z"/></symbol>
        <symbol id="tf-i-project" viewBox="0 0 24 24"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4l2 2h7A1.5 1.5 0 0 1 20 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5z"/></symbol>
        <symbol id="tf-i-task" viewBox="0 0 24 24"><path d="m8 12 2.5 2.5L16 9M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></symbol>
        <symbol id="tf-i-flow" viewBox="0 0 24 24"><path d="M6 6h8m4 0h.01M10 12h8M6 12h.01M6 18h8m4 0h.01"/></symbol>
        <symbol id="tf-i-tag" viewBox="0 0 24 24"><path d="m4 12 8-8h7a1 1 0 0 1 1 1v7l-8 8zm11-4h.01"/></symbol>
        <symbol id="tf-i-customer" viewBox="0 0 24 24"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m5.5-9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-1a3 3 0 0 0 0-6m1.5 11a3 3 0 0 1 2 2.8V20"/></symbol>
        <symbol id="tf-i-team" viewBox="0 0 24 24"><path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-1a3 3 0 1 0 0-6M2.5 20v-1.5A3.5 3.5 0 0 1 6 15h5a3.5 3.5 0 0 1 3.5 3.5V20m1-5h1.5a4 4 0 0 1 4 4v1"/></symbol>
        <symbol id="tf-i-users" viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8v-1a7 7 0 0 1 14 0v1"/></symbol>
        <symbol id="tf-i-help" viewBox="0 0 24 24"><path d="M9.5 9a2.6 2.6 0 1 1 4.4 1.9c-1.1.9-1.9 1.4-1.9 3.1m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></symbol>
        <symbol id="tf-i-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 8h18c0-1-3-1-3-8Zm-8 11h4"/></symbol>
        <symbol id="tf-i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
        <symbol id="tf-i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5m10-2a7 7 0 0 0-12-4L4 11m1 2a7 7 0 0 0 12 4l3-4"/></symbol>
        <symbol id="tf-i-logout" viewBox="0 0 24 24"><path d="M10 5H5v14h5m4-4 4-3-4-3m4 3H9"/></symbol>
    </svg>
    <aside class="tf-sidebar">
        <div class="tf-product"><span>TF</span><div><strong>TaskFlow</strong><small>مدیریت کار و پروژه</small></div></div>
        <nav class="tf-nav">
            <div class="tf-nav-label">کارهای روزانه</div>
            <button class="is-active" data-nav-view="dashboard" type="button"><svg><use href="#tf-i-home"/></svg> نمای کلی</button>
            <button data-nav-view="projects" type="button"><svg><use href="#tf-i-project"/></svg> پروژه‌ها</button>
            <button data-nav-view="tasks" type="button"><svg><use href="#tf-i-task"/></svg> وظایف</button>
            <div class="tf-nav-label">راه‌اندازی و مدیریت</div>
            <button data-nav-view="workflows" type="button"><svg><use href="#tf-i-flow"/></svg> قالب گردش‌کار</button>
            <button data-nav-view="task-types" type="button"><svg><use href="#tf-i-tag"/></svg> انواع وظیفه</button>
            <button data-nav-view="customers" type="button"><svg><use href="#tf-i-customer"/></svg> مشتری‌ها</button>
            <button data-nav-view="teams" type="button"><svg><use href="#tf-i-team"/></svg> تیم‌ها</button>
            <button data-nav-view="users" type="button"><svg><use href="#tf-i-users"/></svg> کاربران و نقش‌ها</button>
            <div class="tf-nav-label">پشتیبانی</div>
            <button data-nav-view="guide" type="button"><svg><use href="#tf-i-help"/></svg> راهنمای کار با سیستم</button>
        </nav>
        <div class="tf-user"><span><?= htmlspecialchars(mb_substr((string) ($user['name'] ?? 'U'), 0, 1), ENT_QUOTES, 'UTF-8') ?></span><div><strong><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></div></div>
    </aside>

    <main class="tf-main">
        <header class="tf-commandbar">
            <div class="tf-command-context">
                <button class="tf-icon-button tf-menu-button" type="button" data-sidebar-toggle aria-label="باز کردن منو"><svg><use href="#tf-i-menu"/></svg></button>
                <div><small>فضای کار</small><strong data-current-view-title>نمای کلی</strong></div>
            </div>
            <div class="tf-command-actions">
                <button class="tf-icon-button tf-notification-button" type="button" data-go="notifications" aria-label="اعلان‌ها"><svg><use href="#tf-i-bell"/></svg><b data-nav-notifications></b></button>
                <button class="tf-icon-button" type="button" data-refresh aria-label="به‌روزرسانی اطلاعات"><svg><use href="#tf-i-refresh"/></svg></button>
                <span class="tf-command-divider"></span>
                <button class="tf-profile-button" type="button" data-logout><span><?= htmlspecialchars(mb_substr((string) ($user['name'] ?? 'U'), 0, 1), ENT_QUOTES, 'UTF-8') ?></span><div><strong><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><small>خروج از حساب</small></div><svg><use href="#tf-i-logout"/></svg></button>
            </div>
        </header>
        <button class="tf-sidebar-backdrop" type="button" data-sidebar-toggle aria-label="بستن منو"></button>
        <div class="tf-toast" data-message hidden></div>

        <section class="tf-view is-active" data-view="dashboard">
            <div class="tf-page-head"><div><span>مرکز کار</span><h1>امروز چه کاری داریم؟</h1><p>پروژه‌های مرحله‌ای و کارهای مستقل را از اینجا کنترل کن.</p></div><div class="tf-head-actions"><button class="tf-button secondary" type="button" data-open-quick-task data-requires="tasks_manage" hidden>+ تسک مستقل</button><button class="tf-button" type="button" data-open-project data-requires="projects_manage" hidden>+ پروژه جدید</button></div></div>
            <section class="tf-setup-card" data-setup-checklist></section>
            <div class="tf-metrics">
                <article><i class="blue">▣</i><div><span>پروژه‌های فعال</span><strong data-metric="orders_active">—</strong></div></article>
                <article><i class="orange">✓</i><div><span>وظایف باز</span><strong data-metric="tasks_open">—</strong></div></article>
                <article><i class="green">◆</i><div><span>پروژه‌های تکمیل</span><strong data-metric="orders_completed">—</strong></div></article>
                <article><i class="purple">◉</i><div><span>اعلان جدید</span><strong data-metric="notifications_unread">—</strong></div></article>
            </div>
            <div class="tf-dashboard-grid">
                <section class="tf-card"><div class="tf-card-head"><div><h2>پروژه‌های اخیر</h2><p>برای دیدن مراحل روی پروژه کلیک کن</p></div><button class="tf-link" data-go="projects">مشاهده همه</button></div><div data-dashboard-projects></div></section>
                <section class="tf-card"><div class="tf-card-head"><div><h2>وظایف فوری</h2><p>تسک‌های پروژه و تسک‌های مستقل</p></div><button class="tf-link" data-go="tasks">مشاهده همه</button></div><div data-dashboard-tasks></div></section>
            </div>
        </section>

        <section class="tf-view" data-view="projects">
            <div class="tf-page-head"><div><span>اجرای کار</span><h1>پروژه‌ها</h1><p>هر پروژه اعضا، مراحل، پیش‌نیاز، فایل و درصد پیشرفت خودش را دارد.</p></div><button class="tf-button" type="button" data-open-project data-requires="projects_manage" hidden>+ ساخت پروژه</button></div>
            <div class="tf-context-help"><b>پروژه چیست؟</b><span>یک کار چندمرحله‌ای مثل «انگشتر گلوریا». قالب و اعضا را انتخاب کن، سپس با شروع پروژه تسک‌ها خودکار ساخته می‌شوند.</span><button data-go="guide">راهنمای کامل</button></div>
            <form class="tf-filters" data-project-filters><input name="search" placeholder="نام یا کد پروژه"><select name="status"><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="active">در حال اجرا</option><option value="completed">تکمیل‌شده</option></select><select name="priority_id" data-options="priorities"><option value="">همه اولویت‌ها</option></select><button class="tf-button secondary">فیلتر</button></form>
            <div class="tf-project-grid" data-project-list><div class="tf-loading">در حال دریافت پروژه‌ها…</div></div>
        </section>

        <section class="tf-view" data-view="project-detail"><button class="tf-back" type="button" data-go="projects">→ بازگشت به پروژه‌ها</button><div data-project-detail><div class="tf-loading">در حال دریافت پروژه…</div></div></section>

        <section class="tf-view" data-view="tasks">
            <div class="tf-page-head"><div><span>کارهای اجرایی</span><h1>وظایف</h1><p>روی «جزئیات و مسئولان» بزن تا افراد تسک را تغییر دهی.</p></div><button class="tf-button" type="button" data-open-quick-task data-requires="tasks_manage" hidden>+ تسک مستقل</button></div>
            <div class="tf-context-help"><b>دو نوع تسک داریم</b><span>تسک پروژه‌ای از مرحله پروژه ساخته می‌شود؛ تسک مستقل مثل «خرید سنگ» بدون پروژه ساخته می‌شود. در تخصیص چندنفره، تأیید یک نفر کافی است.</span></div>
            <form class="tf-filters task-filters" data-task-filters><select name="status"><option value="">همه وضعیت‌ها</option><option value="open">آماده شروع</option><option value="in_progress">در حال انجام</option><option value="completed">تکمیل‌شده</option></select><select name="task_type_id" data-options="task_types"><option value="">همه نوع وظیفه‌ها</option></select><select name="order_id" data-task-project-filter><option value="">همه پروژه‌ها</option></select><input name="customer" placeholder="نام مشتری"><button class="tf-button secondary">فیلتر</button></form>
            <div class="tf-task-columns" data-task-board></div>
        </section>

        <section class="tf-view" data-view="workflows">
            <div class="tf-page-head"><div><span>الگوی قابل استفاده مجدد</span><h1>قالب‌های گردش‌کار</h1><p>یک بار مراحل تولید را تعریف کن و برای پروژه‌های بعدی دوباره استفاده کن.</p></div><button class="tf-button" data-open-template data-requires="templates_manage" hidden>+ قالب جدید</button></div>
            <div class="tf-context-help"><b>ترتیب پیشنهادی</b><span>اول نوع وظیفه بساز، بعد قالب را ایجاد کن، سپس مراحل و پیش‌نیاز هر مرحله را مشخص کن. مسئول پیش‌فرض می‌تواند کاربر، تیم یا نقش باشد.</span><button data-go="task-types">مدیریت نوع وظیفه</button></div>
            <div class="tf-workflow-layout"><aside class="tf-card tf-template-list" data-template-list></aside><section class="tf-card tf-template-editor" data-template-editor><div class="tf-empty"><strong>یک قالب را انتخاب کن</strong><p>مراحل، پیش‌نیازها و مسئولان پیش‌فرض اینجا نمایش داده می‌شوند.</p></div></section></div>
        </section>

        <section class="tf-view" data-view="task-types">
            <div class="tf-page-head"><div><span>دسته‌بندی کار</span><h1>مدیریت انواع وظیفه</h1><p>مثل طراحی، خرید، ریخته‌گری یا کنترل کیفیت؛ این موارد در مراحل و تسک‌ها استفاده می‌شوند.</p></div><button class="tf-button" data-open-task-type data-requires="templates_manage" hidden>+ نوع وظیفه جدید</button></div>
            <div class="tf-context-help"><b>فرق نوع وظیفه با تسک</b><span>«خرید» یک نوع وظیفه است؛ «خرید سنگ پروژه گلوریا» یک تسک واقعی است. نوع وظیفه برای دسته‌بندی و فیلتر است.</span></div>
            <section class="tf-card"><div class="tf-table" data-task-type-list></div></section>
        </section>

        <section class="tf-view" data-view="customers">
            <div class="tf-page-head"><div><span>اطلاعات سفارش‌دهنده</span><h1>مشتری‌ها</h1><p>مشتری را اینجا بساز تا هنگام ساخت پروژه قابل انتخاب باشد.</p></div><button class="tf-button" data-open-customer data-requires="projects_manage" hidden>+ مشتری جدید</button></div>
            <section class="tf-card"><div class="tf-table" data-customer-list></div></section>
        </section>

        <section class="tf-view" data-view="teams">
            <div class="tf-page-head"><div><span>گروه‌بندی شرکت</span><h1>تیم‌ها</h1><p>مثلاً تیم طراحی یا تولید؛ هر کاربر می‌تواند در چند تیم باشد.</p></div><button class="tf-button" data-open-team data-requires="teams_manage" hidden>+ گروه جدید</button></div>
            <div class="tf-split"><section class="tf-card"><div class="tf-card-head"><div><h2>گروه‌ها</h2><p>اعضای هر گروه زیر نام آن دیده می‌شوند</p></div></div><div class="tf-entity-list" data-team-list></div></section><section class="tf-card"><div class="tf-card-head"><div><h2>افزودن عضو به گروه</h2><p>این تیم‌ها در تخصیص مراحل قالب قابل انتخاب‌اند.</p></div></div><form class="tf-form" data-team-member data-requires="teams_manage"><label>گروه<select name="team_id" data-options="teams" required></select></label><label>کاربر<select name="user_id" data-options="users" required></select></label><label class="tf-check"><input type="checkbox" name="is_lead"> سرگروه باشد</label><button class="tf-button">افزودن عضو</button></form></section></div>
        </section>

        <section class="tf-view" data-view="users">
            <div class="tf-page-head"><div><span>افراد و دسترسی</span><h1>کاربران و نقش‌ها</h1><p>برای هر عضو شرکت حساب بساز و نقش او را مشخص کن.</p></div><div class="tf-head-actions"><button class="tf-button secondary" data-open-role data-requires="users_manage" hidden>+ نقش جدید</button><button class="tf-button" data-open-user data-requires="users_manage" hidden>+ حساب جدید</button></div></div>
            <div class="tf-split"><section class="tf-card"><div class="tf-card-head"><div><h2>کاربران</h2><p>نقش هر کاربر از همین لیست قابل تغییر است</p></div></div><div class="tf-user-list" data-user-list></div></section><section class="tf-card"><div class="tf-card-head"><div><h2>نقش‌های دسترسی</h2><p>ادمین کامل، مدیر پروژه یا کاربر اجرایی</p></div></div><div class="tf-entity-list" data-role-list></div></section></div>
            <section class="tf-danger-zone" data-admin-only hidden><div><strong>پاک‌سازی داده‌های تست</strong><p>پروژه‌ها، تسک‌ها، قالب‌ها، انواع وظیفه، تیم‌ها، مشتری‌ها و اعلان‌ها پاک می‌شوند؛ کاربران، نقش‌ها، دسترسی‌ها و اولویت‌های پایه باقی می‌مانند.</p></div><button class="tf-button danger" type="button" data-wipe-workspace>پاک‌سازی کامل</button></section>
        </section>

        <section class="tf-view" data-view="guide">
            <div class="tf-page-head"><div><span>راهنمای داخل پنل</span><h1>از کجا شروع کنم؟</h1><p>برای اولین راه‌اندازی دقیقاً همین ترتیب را انجام بده.</p></div></div>
            <div class="tf-guide-steps">
                <article><b>۱</b><div><h2>کاربران را بساز</h2><p>برای اعضای شرکت حساب ایجاد کن و نقش مناسب بده.</p><button data-go="users">رفتن به کاربران</button></div></article>
                <article><b>۲</b><div><h2>نوع وظیفه تعریف کن</h2><p>دسته‌های کاری مثل طراحی، خرید و کنترل کیفیت را بساز.</p><button data-go="task-types">رفتن به انواع وظیفه</button></div></article>
                <article><b>۳</b><div><h2>در صورت نیاز تیم بساز</h2><p>افراد هم‌حوزه را برای تخصیص سریع‌تر گروه‌بندی کن.</p><button data-go="teams">رفتن به تیم‌ها</button></div></article>
                <article><b>۴</b><div><h2>قالب گردش‌کار بساز</h2><p>مراحل، ترتیب، وزن، پیش‌نیاز و مسئول پیش‌فرض را تعریف کن.</p><button data-go="workflows">رفتن به قالب‌ها</button></div></article>
                <article><b>۵</b><div><h2>پروژه بساز و شروع کن</h2><p>قالب، اعضا، مشتری و اولویت را انتخاب کن؛ سپس شروع پروژه را بزن.</p><button data-go="projects">رفتن به پروژه‌ها</button></div></article>
                <article><b>۶</b><div><h2>تسک‌ها را پیگیری کن</h2><p>مسئول را تغییر بده، گزارش ثبت کن و مرحله بعد را خودکار فعال کن.</p><button data-go="tasks">رفتن به وظایف</button></div></article>
            </div>
            <section class="tf-card tf-guide-card"><div class="tf-card-head"><div><h2>فرق مفاهیم اصلی</h2><p>این موارد را از هم جدا نگه دار</p></div></div><div class="tf-glossary"><article><strong>نوع وظیفه</strong><span>دسته قابل استفاده مجدد؛ مثل «خرید»</span></article><article><strong>تسک</strong><span>کار واقعی؛ مثل «خرید سنگ»</span></article><article><strong>مرحله</strong><span>یک بخش مسیر پروژه؛ مثل «کنترل کیفیت»</span></article><article><strong>قالب گردش‌کار</strong><span>الگوی مراحل و پیش‌نیاز برای پروژه‌های مشابه</span></article><article><strong>پروژه</strong><span>نمونه واقعی اجرا؛ مثل «انگشتر گلوریا»</span></article></div></section>
            <section class="tf-card tf-guide-card"><div class="tf-card-head"><div><h2>قانون تخصیص وظیفه</h2><p>سیستم دقیقاً به چه کسی تسک می‌دهد؟</p></div></div><div class="tf-guide-copy"><p>در قالب برای هر مرحله کاربر، تیم یا نقش پیش‌فرض انتخاب کن. هنگام شروع پروژه، سیستم افراد مرتبط را از بین اعضای همان پروژه انتخاب می‌کند. اگر مسئولی تعیین نشده یا مسئول پیش‌فرض عضو پروژه نباشد، تسک به اعضای پروژه داده می‌شود تا بدون مسئول نماند.</p><p>اگر تسک به چند نفر داده شود، تکمیل توسط یک نفر کافی است. مدیر از «جزئیات و مسئولان» می‌تواند تخصیص را عوض کند.</p></div></section>
        </section>

        <section class="tf-view" data-view="notifications"><div class="tf-page-head"><div><span>تغییرات کار</span><h1>اعلان‌ها</h1><p>تخصیص تسک و فعال‌شدن مراحل جدید.</p></div><button class="tf-button secondary" data-read-notifications>خواندن همه</button></div><section class="tf-card tf-notifications" data-notification-list></section></section>
    </main>

    <nav class="tf-mobile-nav" aria-label="دسترسی سریع">
        <button class="is-active" data-nav-view="dashboard" type="button"><svg><use href="#tf-i-home"/></svg><span>خانه</span></button>
        <button data-nav-view="projects" type="button"><svg><use href="#tf-i-project"/></svg><span>پروژه‌ها</span></button>
        <button data-nav-view="tasks" type="button"><svg><use href="#tf-i-task"/></svg><span>وظایف</span></button>
        <button data-nav-view="notifications" type="button"><span class="tf-mobile-bell"><svg><use href="#tf-i-bell"/></svg><b data-nav-notifications></b></span><span>اعلان‌ها</span></button>
    </nav>

    <dialog class="tf-dialog" data-modal="project"><form method="dialog" class="tf-modal-card" data-create-project><header><div><h2>ساخت پروژه جدید</h2><p>کار چندمرحله‌ای مثل «انگشتر گلوریا»</p></div><button type="button" data-close-modal>×</button></header><div class="tf-form-grid"><label>نام پروژه<input name="name" required placeholder="انگشتر گلوریا"></label><label>کد پروژه<input name="code" placeholder="GLORIA-RING"></label><label>قالب گردش‌کار<select name="workflow_template_id" data-options="templates" required></select><small>مرحله‌ها از این قالب ساخته می‌شوند.</small></label><label>اولویت<select name="priority_id" data-options="priorities"><option value="">عادی</option></select></label><label>موعد انجام<input name="due_at" type="datetime-local"></label><label>مشتری<select name="customer_id" data-options="customers"><option value="">بدون مشتری</option></select></label><label>وزن مشتری<input name="weight" type="number" min="0" step="0.001" placeholder="گرم"></label><label class="span-2">اعضای پروژه<select name="member_ids" data-options="users" multiple required></select><small>فقط افراد حاضر در این پروژه را انتخاب کن. با Ctrl چند نفر انتخاب می‌شوند.</small></label><label class="span-2">توضیحات<textarea name="description" rows="3"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button" type="submit">ساخت پیش‌نویس پروژه</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="project-edit"><form method="dialog" class="tf-modal-card" data-project-edit-form><header><div><h2>ویرایش مشخصات پروژه</h2><p>مراحل پروژه از بخش پایین همان پروژه مدیریت می‌شوند.</p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="project_id"><div class="tf-form-grid"><label>نام پروژه<input name="name" required></label><label>کد پروژه<input name="code" required></label><label>اولویت<select name="priority_id" data-options="priorities"><option value="">عادی</option></select></label><label>موعد انجام<input name="due_at" type="datetime-local"></label><label>مشتری<select name="customer_id" data-options="customers"><option value="">بدون مشتری</option></select></label><label>وزن مشتری<input name="weight" type="number" min="0" step="0.001"></label><label class="span-2">توضیحات<textarea name="description" rows="3"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره تغییرات</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="quick-task"><form method="dialog" class="tf-modal-card" data-create-quick-task><header><div><h2>تسک مستقل</h2><p>برای کار سبک بدون پروژه؛ مثل «خرید سنگ»</p></div><button type="button" data-close-modal>×</button></header><div class="tf-form-grid"><label class="span-2">عنوان تسک<input name="title" required placeholder="خرید سنگ"></label><label>نوع وظیفه<select name="task_type_id" data-options="task_types" required></select></label><label>موعد انجام<input name="due_at" type="datetime-local"></label><label class="span-2">مسئول یا مسئولان<select name="user_ids" data-options="users" multiple required></select><small>اگر چند نفر انتخاب شوند، انجام توسط یک نفر کافی است.</small></label><label class="span-2">توضیحات<textarea name="description" rows="3"></textarea></label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت و تخصیص</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="task-detail"><div class="tf-modal-card" data-task-detail><div class="tf-loading">در حال دریافت تسک…</div></div></dialog>

    <dialog class="tf-dialog" data-modal="template"><form method="dialog" class="tf-modal-card compact" data-save-template><header><div><h2 data-template-modal-title>قالب گردش‌کار جدید</h2><p>این الگو روی پروژه‌های مختلف استفاده می‌شود.</p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="template_id"><label>نام قالب<input name="name" required placeholder="تولید محصول جدید"></label><label>توضیحات<textarea name="description" rows="3"></textarea></label><label class="tf-check"><input type="checkbox" name="is_active" checked> فعال باشد</label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره قالب</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="stage"><form method="dialog" class="tf-modal-card" data-save-stage><header><div><h2 data-stage-modal-title>افزودن مرحله</h2><p data-stage-template-name></p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="template_id"><input type="hidden" name="step_id"><div class="tf-form-grid"><label>نام مرحله<input name="name" required placeholder="طراحی سه‌بعدی"></label><label>نوع وظیفه<select name="task_type_id" data-options="task_types" required></select></label><label>وزن پیشرفت<input name="progress_weight" type="number" min="0.01" step="0.01" value="1"><small>سهم این مرحله در درصد کل پروژه</small></label><label>ترتیب<input name="position" type="number" value="10"></label><label class="span-2">پیش‌نیازها<select name="dependency_ids" multiple data-stage-dependencies></select><small>خالی یعنی شروع هم‌زمان؛ با انتخاب چند مورد، تکمیل همه لازم است.</small></label><div class="span-2 tf-target-box" data-template-targets><strong>مسئول پیش‌فرض مرحله</strong><p>کاربر، تیم یا نقش انتخاب کن. هنگام اجرا فقط اعضای همان پروژه در نظر گرفته می‌شوند.</p><div class="tf-form-grid"><label>کاربران<select name="user_ids" data-options="users" multiple></select></label><label>تیم‌ها<select name="team_ids" data-options="teams" multiple></select></label><label>نقش‌ها<select name="role_ids" data-options="roles" multiple></select></label></div></div><label class="span-2">شرح مرحله<textarea name="description" rows="2"></textarea></label><label class="tf-check" data-stage-active-row><input type="checkbox" name="is_active" checked> مرحله فعال باشد</label></div><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره مرحله</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="project-dependencies"><form method="dialog" class="tf-modal-card compact" data-save-project-dependencies><header><div><h2>ویرایش پیش‌نیاز مرحله</h2><p data-project-dependency-title></p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="step_id"><label>این مرحله بعد از تکمیل کدام مراحل فعال شود؟<select name="dependency_ids" multiple data-project-dependency-options></select><small>اگر هیچ موردی انتخاب نشود، مرحله بدون پیش‌نیاز است.</small></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره پیش‌نیازها</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="task-type"><form method="dialog" class="tf-modal-card compact" data-save-task-type><header><div><h2 data-task-type-title>نوع وظیفه جدید</h2><p>برای دسته‌بندی مراحل و تسک‌ها</p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="task_type_id"><label>نام فارسی<input name="name" required placeholder="خرید"></label><label>کلید انگلیسی<input name="slug" required placeholder="purchase"><small>فقط حروف انگلیسی، عدد، خط تیره یا زیرخط</small></label><label>رنگ<input name="color" type="color" value="#3157d5"></label><label>توضیحات<textarea name="description" rows="2"></textarea></label><label class="tf-check"><input type="checkbox" name="is_active" checked> فعال باشد</label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره نوع وظیفه</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="customer"><form method="dialog" class="tf-modal-card compact" data-save-customer><header><div><h2 data-customer-title>مشتری جدید</h2><p>بعداً در پروژه قابل انتخاب است.</p></div><button type="button" data-close-modal>×</button></header><input type="hidden" name="customer_id"><label>نام مشتری<input name="name" required></label><label>تلفن<input name="phone"></label><label>ایمیل<input name="email" type="email"></label><label>یادداشت<textarea name="notes" rows="3"></textarea></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ذخیره مشتری</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="team"><form method="dialog" class="tf-modal-card compact" data-create-team><header><div><h2>ساخت گروه</h2><p>مثلاً تیم طراحی</p></div><button type="button" data-close-modal>×</button></header><label>نام گروه<input name="name" required></label><label>توضیحات<textarea name="description"></textarea></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت گروه</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="user"><form method="dialog" class="tf-modal-card compact" data-create-user><header><div><h2>حساب کاربری جدید</h2><p>برای یکی از اعضای شرکت</p></div><button type="button" data-close-modal>×</button></header><label>نام<input name="name" required></label><label>ایمیل<input name="email" type="email" required></label><label>رمز عبور<input name="password" type="password" minlength="10" required></label><label>نقش<select name="role_key"><option value="user">کاربر اجرایی</option><option value="manager">مدیر پروژه</option><option value="admin">ادمین کامل</option></select></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">ساخت حساب</button></footer></form></dialog>

    <dialog class="tf-dialog" data-modal="role"><form method="dialog" class="tf-modal-card compact" data-create-role><header><div><h2>تعریف نقش جدید</h2><p>دسترسی‌های این نقش را انتخاب کن.</p></div><button type="button" data-close-modal>×</button></header><label>کلید انگلیسی نقش<input name="key_name" required placeholder="supervisor"></label><label>نام نمایشی<input name="display_name" required placeholder="سرپرست تولید"></label><label>دسترسی‌ها<select name="permissions" data-options="permissions" multiple required></select><small>با Ctrl چند دسترسی را انتخاب کن.</small></label><footer><button type="button" class="tf-button ghost" data-close-modal>انصراف</button><button class="tf-button">تعریف نقش</button></footer></form></dialog>
</section>
