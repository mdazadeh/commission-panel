<?php

/**
 * Faran Commission — one-time web installer for shared hosting (no SSH).
 *
 * Flow: token check → DB connection test → write .env → migrate → create tenant/admin → lock.
 * Security:
 *   - refuses to run once storage/installed.lock exists;
 *   - requires a random token that is written to storage/install-token.txt (outside public_html)
 *     on first visit, so only someone with File Manager access can install;
 *   - deletes the token after success and tries to delete itself.
 */
declare(strict_types=1);
use App\Services\InitialSetup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$basePath = require __DIR__.'/paths.php';
$lock = $basePath.'/storage/installed.lock';
$tokenFile = $basePath.'/storage/install-token.txt';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function page(string $title, string $body): never
{
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><style>
    :root{--bg:#f6f7f9;--card:#fff;--tx:#1c1f24;--mut:#667085;--bd:#e4e7ec;--br:#0b5cad;--bad:#c0262d;--ok:#127a3a}
    @media (prefers-color-scheme:dark){:root{--bg:#121214;--card:#1c1c1f;--tx:#ececf0;--mut:#9a9aa2;--bd:#2c2c31;--bad:#ff6b6b;--ok:#4ac26b}}
    body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.8 Tahoma,sans-serif}.w{max-width:640px;margin:0 auto;padding:16px}
    .c{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:20px;margin-bottom:16px}
    label{display:block;margin-top:10px;font-weight:bold}input{width:100%;box-sizing:border-box;padding:8px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--tx)}
    .ltr{direction:ltr}button{margin-top:16px;padding:10px 18px;border:0;border-radius:8px;background:var(--br);color:#fff;cursor:pointer}
    .bad{color:var(--bad)}.ok{color:var(--ok)}.mut{color:var(--mut)}h3{margin:18px 0 0}code{direction:ltr;unicode-bidi:isolate}
    </style></head><body><div class="w"><div class="c"><h2 style="margin-top:0">'.h($title).'</h2>'.$body.'</div></div></body></html>';
    exit;
}

if (file_exists($lock)) {
    http_response_code(404);
    page('نصب انجام شده است', '<p>سامانه قبلاً نصب شده است. این فایل (<code>install.php</code>) را از سرور حذف کنید.</p>');
}

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    page('PHP ناسازگار', '<p class="bad">حداقل PHP 8.3 لازم است. نسخه فعلی: '.h(PHP_VERSION).'</p>');
}
$missing = array_filter(['bcmath', 'pdo_mysql', 'mbstring', 'openssl', 'intl', 'zip', 'xmlreader', 'fileinfo'], fn ($e) => ! extension_loaded($e));
if ($missing) {
    page('اکستنشن ناقص', '<p class="bad">این اکستنشن‌ها فعال نیستند: '.h(implode(', ', $missing)).'</p>');
}
foreach (['storage', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    if (! is_dir($basePath.'/'.$dir)) {
        @mkdir($basePath.'/'.$dir, 0750, true);
    }
    if (! is_writable($basePath.'/'.$dir)) {
        page('دسترسی نوشتن', '<p class="bad">پوشه <code>'.h($dir).'</code> قابل نوشتن نیست.</p>');
    }
}

if (! file_exists($tokenFile)) {
    file_put_contents($tokenFile, bin2hex(random_bytes(12)));
    @chmod($tokenFile, 0600);
}
$expectedToken = trim((string) file_get_contents($tokenFile));

$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

$v = fn (string $k, string $d = '') => trim((string) ($_POST[$k] ?? $d));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! hash_equals($expectedToken, $v('token'))) {
        $errors[] = 'توکن نصب اشتباه است.';
    }
    $appUrl = rtrim($v('app_url', $defaultUrl), '/');
    if (! filter_var($appUrl, FILTER_VALIDATE_URL) || (! str_starts_with($appUrl, 'https://') && ! str_starts_with($appUrl, 'http://localhost'))) {
        $errors[] = 'آدرس سامانه باید با https:// شروع شود.';
    }
    if (! preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $v('admin_username'))) {
        $errors[] = 'نام کاربری مدیر نامعتبر است (حروف انگلیسی، عدد، . _ -).';
    }
    $pw = (string) ($_POST['admin_password'] ?? '');
    if (strlen($pw) < 10 || ! preg_match('/[A-Za-z]/', $pw) || ! preg_match('/\d/', $pw)) {
        $errors[] = 'رمز مدیر باید حداقل ۱۰ کاراکتر و شامل حرف و عدد باشد.';
    }
    if ($pw !== (string) ($_POST['admin_password_confirm'] ?? '')) {
        $errors[] = 'تکرار رمز مدیر مطابقت ندارد.';
    }

    if (! $errors) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $v('db_host', 'localhost'), (int) $v('db_port', '3306'), $v('db_name')),
                $v('db_user'), (string) ($_POST['db_pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $isMaria = stripos($ver, 'mariadb') !== false;
            $num = preg_replace('/^(\d+\.\d+\.\d+).*/', '$1', $ver);
            if ($isMaria ? version_compare($num, '10.3.0', '<') : version_compare($num, '5.7.8', '<')) {
                $errors[] = 'نسخه پایگاه داده قدیمی است: '.$ver;
            }
            if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() > 0) {
                $errors[] = 'پایگاه داده خالی نیست. برای نصب یک دیتابیس خالی بسازید.';
            }
        } catch (Throwable $e) {
            $errors[] = 'اتصال به پایگاه داده ناموفق: '.$e->getMessage();
        }
    }

    if (! $errors) {
        // Single quotes disable dotenv interpolation; fall back to double quotes only when needed.
        $q = fn (string $s) => ! str_contains($s, "'") ? "'".$s."'" : '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
        $path = parse_url($appUrl, PHP_URL_PATH) ?: '/';
        $env = [
            'APP_NAME' => $q('سامانه پورسانت فاران'),
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_LOCALE' => 'fa',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_TIMEZONE' => 'Asia/Tehran',
            'LOG_CHANNEL' => 'daily',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $q($v('db_host', 'localhost')),
            'DB_PORT' => (string) (int) $v('db_port', '3306'),
            'DB_DATABASE' => $q($v('db_name')),
            'DB_USERNAME' => $q($v('db_user')),
            'DB_PASSWORD' => $q((string) ($_POST['db_pass'] ?? '')),
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => '120',
            'SESSION_COOKIE' => 'faran_commission_session',
            'SESSION_PATH' => $path,
            'SESSION_SECURE_COOKIE' => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
            'SESSION_SAME_SITE' => 'lax',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'FILESYSTEM_DISK' => 'local',
            'MAIL_MAILER' => 'log',
            'BCRYPT_ROUNDS' => '12',
            'INERTIA_DEVTOOLS_ENABLED' => 'false',
        ];
        $content = '';
        foreach ($env as $k => $val) {
            $content .= "{$k}={$val}\n";
        }
        if (file_put_contents($basePath.'/.env', $content) === false) {
            page('خطا', '<p class="bad">نوشتن فایل .env ممکن نشد.</p>');
        }
        @chmod($basePath.'/.env', 0640);

        try {
            require $basePath.'/vendor/autoload.php';
            $app = require $basePath.'/bootstrap/app.php';
            $app->usePublicPath(__DIR__);
            $kernel = $app->make(Kernel::class);
            $kernel->bootstrap();

            Artisan::call('migrate', ['--force' => true]);
            $app->make(InitialSetup::class)->run(
                $v('company', 'شرکت صنایع الکترونیک فاران'),
                $v('admin_username'),
                $v('admin_name', 'مدیر سامانه'),
                $pw,
            );

            file_put_contents($lock, date('c'));
            @unlink($tokenFile);
            $selfDeleted = @unlink(__FILE__);
        } catch (Throwable $e) {
            @unlink($basePath.'/.env');
            page('نصب ناموفق', '<p class="bad">'.h($e->getMessage()).'</p><p class="mut">فایل .env حذف شد؛ پس از رفع مشکل، دیتابیس را خالی کنید و دوباره تلاش کنید.</p>');
        }

        page('نصب با موفقیت انجام شد', '<p class="ok">سامانه آماده است.</p>'
            .'<p><a href="'.h($appUrl).'/login">ورود به سامانه</a></p>'
            .($selfDeleted ? '' : '<p class="bad">فایل <code>install.php</code> را دستی از سرور حذف کنید.</p>')
            .'<h3>Cron (DirectAdmin ← Cron Jobs، هر دقیقه)</h3><p><code>* * * * * /usr/local/bin/php '.h($basePath).'/artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></p>');
    }
}

$err = $errors ? '<ul class="bad">'.implode('', array_map(fn ($e) => '<li>'.h($e).'</li>', $errors)).'</ul>' : '';
$field = fn (string $name, string $label, string $value = '', string $type = 'text', bool $ltr = true) => '<label>'.h($label).'</label><input name="'.$name.'" type="'.$type.'" value="'.h($value).'"'.($ltr ? ' class="ltr"' : '').' required>';

page('نصب سامانه پورسانت فاران', $err.'
<p class="mut">پیش از شروع، در DirectAdmin یک دیتابیس MySQL خالی بسازید. توکن نصب در فایل زیر قرار دارد (از File Manager باز کنید):<br><code>'.h(realpath($tokenFile) ?: $tokenFile).'</code></p>
<form method="post" autocomplete="off">
'.$field('token', 'توکن نصب').'
<h3>پایگاه داده</h3>
'.$field('db_host', 'میزبان', $v('db_host', 'localhost')).'
'.$field('db_port', 'پورت', $v('db_port', '3306')).'
'.$field('db_name', 'نام دیتابیس', $v('db_name')).'
'.$field('db_user', 'نام کاربری دیتابیس', $v('db_user')).'
<label>رمز دیتابیس</label><input name="db_pass" type="password" class="ltr">
<h3>سامانه</h3>
'.$field('app_url', 'آدرس سامانه', $v('app_url', $defaultUrl)).'
'.$field('company', 'نام شرکت', $v('company', 'شرکت صنایع الکترونیک فاران'), 'text', false).'
<h3>مدیر سامانه</h3>
'.$field('admin_name', 'نام', $v('admin_name', 'مدیر سامانه'), 'text', false).'
'.$field('admin_username', 'نام کاربری', $v('admin_username', 'admin')).'
'.$field('admin_password', 'رمز عبور (حداقل ۱۰ کاراکتر، حرف و عدد)', '', 'password').'
'.$field('admin_password_confirm', 'تکرار رمز عبور', '', 'password').'
<button type="submit">نصب</button>
</form>');
