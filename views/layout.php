<?php

declare(strict_types=1);

use App\Support\Env;
use App\Support\Url;

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : $appName;
$currentUser = isset($user) && is_array($user) ? $user : null;
$assetVersion = static fn (string $path): string => (string) (@filemtime(APP_ROOT . '/public' . $path) ?: '1');
?>
<!doctype html>
<html lang="<?= htmlspecialchars(Env::get('APP_LOCALE', 'fa') ?? 'fa', ENT_QUOTES, 'UTF-8') ?>" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#3157d5">
    <meta name="application-name" content="TaskFlow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TaskFlow">
    <meta name="app-authenticated" content="<?= $currentUser !== null ? '1' : '0' ?>">
    <meta name="app-user-id" content="<?= (int) ($currentUser['id'] ?? 0) ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="manifest" href="<?= htmlspecialchars(Url::to('/manifest.webmanifest'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(Url::to('/icons/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::to('/assets/app.css') . '?v=' . $assetVersion('/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(Url::to('/assets/app.js') . '?v=' . $assetVersion('/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(Url::to('/assets/pwa.js') . '?v=' . $assetVersion('/assets/pwa.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (isset($pageScript) && is_string($pageScript)): ?>
        <script defer src="<?= htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="<?= htmlspecialchars(Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="نمایش منو">☰</button>
        <nav class="nav" data-nav>
            <a href="<?= htmlspecialchars(Url::to('/'), ENT_QUOTES, 'UTF-8') ?>">خانه</a>
            <?php if ($currentUser !== null): ?>
                <a href="<?= htmlspecialchars(Url::to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">داشبورد</a>
                <a href="<?= htmlspecialchars(Url::to('/workspace'), ENT_QUOTES, 'UTF-8') ?>">فضای کار</a>
                <button class="btn btn-ghost btn-small" type="button" data-logout>خروج</button>
            <?php else: ?>
                <a href="<?= htmlspecialchars(Url::to('/login'), ENT_QUOTES, 'UTF-8') ?>">ورود</a>
                <?php if (Env::bool('AUTH_ALLOW_REGISTRATION', false)): ?>
                    <a class="btn btn-small" href="<?= htmlspecialchars(Url::to('/register'), ENT_QUOTES, 'UTF-8') ?>">ساخت حساب</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main><?= $content ?></main>

<div class="pwa-offline" data-pwa-offline role="status" hidden>اتصال اینترنت قطع است؛ اطلاعات تازه و عملیات اجرایی تا اتصال مجدد در دسترس نیست.</div>

<?php if ($currentUser !== null): ?>
<aside class="pwa-install-card" data-pwa-install-card role="dialog" aria-labelledby="pwa-install-title" hidden>
    <img src="<?= htmlspecialchars(Url::to('/icons/taskflow-192.png'), ENT_QUOTES, 'UTF-8') ?>" alt="" width="52" height="52">
    <div><h2 id="pwa-install-title">TaskFlow را روی گوشی نصب کنید</h2><p data-pwa-install-copy>دسترسی سریع‌تر، نمایش تمام‌صفحه و اعلان وظایف را فعال کنید.</p><ol data-pwa-ios-guide hidden><li>دکمه Share مرورگر Safari را بزنید.</li><li>گزینه Add to Home Screen را انتخاب کنید.</li><li>در پایان Add را بزنید.</li></ol></div>
    <div class="pwa-install-actions"><button class="tf-button" type="button" data-pwa-install>نصب برنامه</button><button class="tf-button ghost" type="button" data-pwa-install-dismiss>فعلاً نه</button></div>
</aside>
<?php endif; ?>

<aside class="pwa-update-card" data-pwa-update role="status" hidden><span>نسخه جدید TaskFlow آماده است.</span><button type="button" data-pwa-update-apply>به‌روزرسانی</button></aside>

<dialog class="app-dialog" data-app-dialog aria-labelledby="app-dialog-title">
    <form class="app-dialog-card" data-app-dialog-form novalidate>
        <header>
            <span class="app-dialog-icon" data-app-dialog-icon aria-hidden="true">?</span>
            <div>
                <h2 id="app-dialog-title" data-app-dialog-title>تأیید عملیات</h2>
                <p data-app-dialog-message></p>
            </div>
            <button class="app-dialog-close" type="button" data-app-dialog-cancel aria-label="بستن">×</button>
        </header>
        <label class="app-dialog-field" data-app-dialog-field hidden>
            <span data-app-dialog-label></span>
            <textarea data-app-dialog-input rows="4"></textarea>
            <small data-app-dialog-help hidden></small>
        </label>
        <footer>
            <button class="btn btn-ghost" type="button" data-app-dialog-cancel>انصراف</button>
            <button class="btn" type="submit" data-app-dialog-submit>تأیید</button>
        </footer>
    </form>
</dialog>

<footer class="footer">
    <div class="container footer-inner">
        <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="muted">PHP 8.2 · MariaDB · Modular by default</span>
    </div>
</footer>
</body>
</html>
