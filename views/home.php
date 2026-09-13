<?php $pageTitle = $appName; ?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">شروع سریع، توسعهٔ تمیز</span>
            <h1>هستهٔ مشترک پروژه آماده است؛ روی مسئلهٔ واقعی محصول تمرکز کنید.</h1>
            <p>احراز هویت، امنیت، دیتابیس، صف، آپلود، پرداخت و پایهٔ پنل مدیریت از قبل کنار هم قرار گرفته‌اند.</p>
            <div class="hero-actions">
                <?php if (!empty($user)): ?>
                    <a class="btn" href="<?= htmlspecialchars($to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">رفتن به داشبورد</a>
                <?php else: ?>
                    <a class="btn" href="<?= htmlspecialchars($to('/register'), ENT_QUOTES, 'UTF-8') ?>">شروع پروژه</a>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($to('/login'), ENT_QUOTES, 'UTF-8') ?>">ورود به حساب</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-console" aria-label="نمونه وضعیت سرویس">
            <div class="console-bar"><span></span><span></span><span></span></div>
            <pre><code>GET /health

{
  "ok": true,
  "database": "ready",
  "modules": <?= htmlspecialchars(json_encode($modules, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>
}</code></pre>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <div><span class="eyebrow">Foundation</span><h2>زیرساخت‌هایی که دوباره نوشته نمی‌شوند</h2></div>
            <p>بخش‌های عمومی از پروژهٔ مبنا جدا و بدون وابستگی به برند یا دامنهٔ خاص آماده شده‌اند.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card"><span class="feature-icon">01</span><h3>امنیت و احراز هویت</h3><p>Session امن، RBAC، CSRF، Rate Limit و هش استاندارد رمز عبور.</p></article>
            <article class="feature-card"><span class="feature-icon">02</span><h3>داده و Migration</h3><p>PDO سخت‌گیرانه، پیشوند جدول و migrationهای مرتب و قابل رهگیری.</p></article>
            <article class="feature-card"><span class="feature-icon">03</span><h3>عملیات پس‌زمینه</h3><p>صف durable، اعلان ایمیلی و backoff برای پردازش‌های ناموفق.</p></article>
            <article class="feature-card"><span class="feature-icon">04</span><h3>ماژول‌های انتخابی</h3><p>Catalog، Commerce، CRM و HR بدون تحمیل جداول غیرضروری.</p></article>
        </div>
    </div>
</section>
