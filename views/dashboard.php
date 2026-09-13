<?php $pageTitle = 'داشبورد · ' . $appName; ?>
<section class="dashboard-page">
    <div class="container dashboard-stack">
        <section class="dashboard-hero">
            <div>
                <span class="eyebrow eyebrow-light">پنل مدیریت</span>
                <h1>سلام، <?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="status-pill"><span></span> هسته آماده است</div>
        </section>

        <section>
            <div class="section-heading compact">
                <div><span class="eyebrow">Modules</span><h2>ماژول‌های پروژه</h2></div>
                <p>کارت‌ها نقطهٔ شروع صفحه‌های مدیریتی هر دامنه هستند.</p>
            </div>
            <div class="module-grid">
                <?php foreach ($modules as $index => $module): ?>
                    <article class="module-card tone-<?= ($index % 4) + 1 ?>">
                        <div class="module-icon"><?= strtoupper(substr($module, 0, 1)) ?></div>
                        <div><h3><?= htmlspecialchars(ucfirst($module), ENT_QUOTES, 'UTF-8') ?></h3><p>Schema installed · routes ready to extend</p></div>
                        <span class="badge">فعال</span>
                    </article>
                <?php endforeach; ?>
                <?php if ($modules === []): ?>
                    <div class="empty-state">هنوز ماژولی فعال نشده است. مقدار <code>APP_MODULES</code> را در فایل محیطی تنظیم کنید.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-grid">
            <article class="card metric-card"><span class="metric-label">وضعیت API</span><strong>Ready</strong><small>/api/v1/meta</small></article>
            <article class="card metric-card"><span class="metric-label">احراز هویت</span><strong>Session</strong><small>CSRF + Rate Limit</small></article>
            <article class="card metric-card"><span class="metric-label">دیتابیس</span><strong>PDO</strong><small>MariaDB / MySQL</small></article>
        </section>

        <section class="card next-steps">
            <div><span class="eyebrow">TaskFlow</span><h2>مدیریت پروژه و گردش کار آماده است</h2></div>
            <div><p class="muted">سفارش، پروژه، وظیفه، گروه، نقش و Workflow را از یک پنل مدیریت کنید.</p><a class="btn" href="<?= htmlspecialchars($to('/workspace'), ENT_QUOTES, 'UTF-8') ?>">ورود به فضای کار</a></div>
        </section>
    </div>
</section>
