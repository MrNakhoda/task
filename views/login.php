<?php $pageTitle = 'ورود · ' . $appName; ?>
<section class="auth-page">
    <div class="auth-shell">
        <div class="auth-panel">
            <a class="auth-brand" href="<?= htmlspecialchars($to('/'), ENT_QUOTES, 'UTF-8') ?>"><span>TF</span><div><strong>TaskFlow</strong><small>فضای یکپارچه مدیریت کار</small></div></a>
            <div class="auth-heading"><span>خوش آمدید</span><h1>ورود به فضای کار</h1><p>برای دیدن پروژه‌ها و وظایف امروز، وارد حساب خود شوید.</p></div>
            <div class="alert alert-error" data-form-error hidden></div>
            <form data-api-form data-endpoint="<?= htmlspecialchars($to('/api/v1/auth/login'), ENT_QUOTES, 'UTF-8') ?>" data-redirect="<?= htmlspecialchars($to('/workspace'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="email">ایمیل</label>
                    <input class="input" id="email" type="email" name="email" maxlength="190" autocomplete="email" inputmode="email" dir="ltr" placeholder="name@company.com" autofocus required>
                </div>
                <div class="field">
                    <label for="password">رمز عبور</label>
                    <div class="password-field"><input class="input" id="password" type="password" name="password" maxlength="4096" autocomplete="current-password" dir="ltr" placeholder="رمز عبور" required><button type="button" data-password-toggle aria-label="نمایش رمز عبور">نمایش</button></div>
                </div>
                <button class="btn btn-block auth-submit" type="submit"><span>ورود به پنل</span><i aria-hidden="true">←</i></button>
            </form>
            <?php if (\App\Support\Env::bool('AUTH_ALLOW_REGISTRATION', false)): ?><p class="auth-register">حساب ندارید؟ <a href="<?= htmlspecialchars($to('/register'), ENT_QUOTES, 'UTF-8') ?>">ساخت حساب جدید</a></p><?php endif; ?>
            <p class="auth-support">برای دریافت حساب یا بازیابی رمز، با مدیر سیستم تماس بگیرید.</p>
        </div>
        <aside class="auth-aside">
            <div class="auth-aside-copy"><span>شفاف، مرحله‌به‌مرحله</span><h2>هر کار، دقیقاً در جای خودش.</h2><p>پروژه، گردش‌کار و وظایف تیم را بدون گم‌شدن بین پیام‌ها و فایل‌ها پیش ببرید.</p></div>
            <div class="auth-preview" aria-hidden="true">
                <div class="auth-preview-head"><span></span><b>پروژه انگشتر گلوریا</b><em>۶۵٪</em></div>
                <div class="auth-preview-progress"><span></span></div>
                <div class="auth-preview-steps"><article class="done"><i>✓</i><div><b>طراحی</b><small>تکمیل شده</small></div></article><article class="active"><i>۲</i><div><b>ریخته‌گری</b><small>در حال انجام</small></div></article><article><i>۳</i><div><b>کنترل کیفیت</b><small>منتظر پیش‌نیاز</small></div></article></div>
            </div>
            <small class="auth-aside-note">TaskFlow · مدیریت پروژه و گردش‌کار</small>
        </aside>
    </div>
</section>
