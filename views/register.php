<?php $pageTitle = 'ساخت حساب · ' . $appName; ?>
<section class="auth-page">
    <div class="container auth-shell">
        <div class="card auth-panel">
            <span class="eyebrow">شروع</span>
            <h1>حساب جدید بسازید</h1>
            <p class="muted">بعداً می‌توانید فیلدهای ثبت‌نام را متناسب با پروژه تغییر دهید.</p>
            <div class="alert alert-error" data-form-error hidden></div>
            <form data-api-form data-endpoint="<?= htmlspecialchars($to('/api/v1/auth/register'), ENT_QUOTES, 'UTF-8') ?>" data-redirect="<?= htmlspecialchars($to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">
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
                    <input class="input" id="password" type="password" name="password" minlength="10" maxlength="4096" autocomplete="new-password" dir="ltr" required>
                    <span class="field-help">حداقل ۱۰ کاراکتر</span>
                </div>
                <button class="btn btn-block" type="submit">ساخت حساب</button>
            </form>
            <p class="small muted">قبلاً ثبت‌نام کرده‌اید؟ <a class="text-link" href="<?= htmlspecialchars($to('/login'), ENT_QUOTES, 'UTF-8') ?>">وارد شوید</a></p>
        </div>
        <aside class="auth-aside auth-aside-warm">
            <span class="eyebrow eyebrow-light">ماژولار</span>
            <h2>فروشگاه، کاتالوگ، CRM یا HR</h2>
            <p>هسته ثابت می‌ماند و مدل داده و گردش‌کار هر محصول در ماژول خودش توسعه پیدا می‌کند.</p>
        </aside>
    </div>
</section>
