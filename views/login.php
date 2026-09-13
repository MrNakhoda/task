<?php $pageTitle = 'ورود · ' . $appName; ?>
<section class="auth-page">
    <div class="container auth-shell">
        <div class="card auth-panel">
            <span class="eyebrow">خوش آمدید</span>
            <h1>ورود به حساب</h1>
            <p class="muted">اطلاعات حساب خود را وارد کنید.</p>
            <div class="alert alert-error" data-form-error hidden></div>
            <form data-api-form data-endpoint="<?= htmlspecialchars($to('/api/v1/auth/login'), ENT_QUOTES, 'UTF-8') ?>" data-redirect="<?= htmlspecialchars($to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="email">ایمیل</label>
                    <input class="input" id="email" type="email" name="email" maxlength="190" autocomplete="email" dir="ltr" required>
                </div>
                <div class="field">
                    <label for="password">رمز عبور</label>
                    <input class="input" id="password" type="password" name="password" maxlength="4096" autocomplete="current-password" dir="ltr" required>
                </div>
                <button class="btn btn-block" type="submit">ورود</button>
            </form>
            <p class="small muted">حساب ندارید؟ <a class="text-link" href="<?= htmlspecialchars($to('/register'), ENT_QUOTES, 'UTF-8') ?>">ثبت‌نام کنید</a></p>
        </div>
        <aside class="auth-aside">
            <span class="eyebrow eyebrow-light">Backend Starter</span>
            <h2>یک پایهٔ مطمئن برای پروژهٔ بعدی</h2>
            <p>از یک پنل ساده شروع کنید و فقط ماژول‌های موردنیاز محصول را توسعه دهید.</p>
            <ul class="check-list"><li>امنیت پیش‌فرض</li><li>چیدمان RTL و ریسپانسیو</li><li>بدون وابستگی اجباری به CDN</li></ul>
        </aside>
    </div>
</section>
