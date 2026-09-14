<?php $pageTitle = 'ساخت حساب · ' . $appName; ?>
<section class="auth-page">
    <div class="auth-shell">
        <div class="auth-panel">
            <a class="auth-brand" href="<?= htmlspecialchars($to('/'), ENT_QUOTES, 'UTF-8') ?>"><span>TF</span><div><strong>TaskFlow</strong><small>فضای یکپارچه مدیریت کار</small></div></a>
            <div class="auth-heading"><span>حساب جدید</span><h1>ساخت حساب کاربری</h1><p>اطلاعات اصلی را وارد کنید؛ مدیر سیستم دسترسی شما را تنظیم می‌کند.</p></div>
            <div class="alert alert-error" data-form-error hidden></div>
            <form data-api-form data-endpoint="<?= htmlspecialchars($to('/api/v1/auth/register'), ENT_QUOTES, 'UTF-8') ?>" data-redirect="<?= htmlspecialchars($to('/workspace'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="name">نام نمایشی</label>
                    <input class="input" id="name" type="text" name="name" minlength="2" maxlength="120" autocomplete="name" required>
                </div>
                <div class="field">
                    <label for="email">ایمیل</label>
                    <input class="input" id="email" type="email" name="email" maxlength="190" autocomplete="email" dir="ltr" required>
                </div>
                <div class="field">
                    <label for="password">رمز عبور</label>
                    <div class="password-field"><input class="input" id="password" type="password" name="password" minlength="10" maxlength="4096" autocomplete="new-password" dir="ltr" required><button type="button" data-password-toggle aria-label="نمایش رمز عبور">نمایش</button></div>
                    <span class="field-help">حداقل ۱۰ کاراکتر</span>
                </div>
                <button class="btn btn-block auth-submit" type="submit"><span>ساخت حساب</span><i aria-hidden="true">←</i></button>
            </form>
            <p class="auth-register">قبلاً ثبت‌نام کرده‌اید؟ <a href="<?= htmlspecialchars($to('/login'), ENT_QUOTES, 'UTF-8') ?>">ورود به حساب</a></p>
        </div>
        <aside class="auth-aside auth-aside-warm">
            <div class="auth-aside-copy"><span>شروع منظم</span><h2>همه اعضای تیم، یک تصویر مشترک.</h2><p>بعد از ورود، فقط پروژه‌ها و وظایفی را می‌بینید که متناسب با نقش شماست.</p></div>
            <ul class="auth-benefits"><li><i>✓</i> دسترسی مبتنی بر نقش</li><li><i>✓</i> وظایف شخصی و پروژه‌ای</li><li><i>✓</i> تاریخچه شفاف فعالیت‌ها</li></ul>
        </aside>
    </div>
</section>
