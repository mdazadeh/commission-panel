<?php

/**
 * Faran Commission — server-side ZIP extractor for DirectAdmin (when the File Manager «Extract» fails).
 *
 * 1. Upload this file AND the release ZIP into  domains/faranepanel.ir/public_html/
 * 2. Open  https://faranepanel.ir/faran-unzip.php
 * 3. First visit writes a one-time token to  domains/faranepanel.ir/faran-unzip-token.txt
 *    (outside the web root) — read it in File Manager and enter it. If the host refuses the write,
 *    create that file by hand (any secret, ≥ 12 chars); the page shows diagnostics either way.
 * 4. The ZIP is extracted into domains/faranepanel.ir/ (→ commission_app/ and public_html/commission/),
 *    then the ZIP, the token and this script are deleted.
 *
 * Safety: only entries under commission_app/ or public_html/commission/ are accepted; no "..", no absolute
 * paths; an existing .env and storage data are never overwritten (the ZIP does not contain them).
 */
declare(strict_types=1);

@set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

$target = dirname(__DIR__);               // domains/faranepanel.ir
$tokenFile = $target.'/faran-unzip-token.txt';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function page(string $title, string $body): never
{
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><style>
    :root{--bg:#f8fafc;--card:#fff;--tx:#0f172a;--mut:#475569;--bd:#e2e8f0;--br:#1e40af;--bad:#b91c1c;--ok:#15803d}
    @media (prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#111a2e;--tx:#e5e9f0;--mut:#a3afc2;--bd:#23304a;--br:#7aa2ff;--bad:#f87171;--ok:#4ade80}}
    body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.8 Tahoma,sans-serif}.w{max-width:640px;margin:0 auto;padding:16px}
    .c{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:20px}input,select{width:100%;box-sizing:border-box;padding:8px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--tx);direction:ltr}
    label{display:block;margin-top:12px;font-weight:bold}button{margin-top:16px;padding:10px 18px;border:0;border-radius:8px;background:var(--br);color:#fff;cursor:pointer}
    .bad{color:var(--bad)}.ok{color:var(--ok)}.mut{color:var(--mut)}code{direction:ltr;unicode-bidi:isolate;word-break:break-all}
    </style></head><body><div class="w"><div class="c"><h2 style="margin-top:0">'.h($title).'</h2>'.$body.'</div></div></body></html>';
    exit;
}

function diagnostics(string $target, string $tokenFile, ?string $writeError): string
{
    $rows = [
        'مسیر این اسکریپت' => __DIR__,
        'پوشه مقصد' => $target,
        'مقصد وجود دارد؟' => is_dir($target) ? 'بله' : 'خیر',
        'مقصد قابل نوشتن؟' => is_writable($target) ? 'بله' : 'خیر',
        'فایل توکن' => $tokenFile,
        'فایل توکن وجود دارد؟' => file_exists($tokenFile) ? 'بله' : 'خیر',
        'خطای نوشتن' => $writeError ?? '—',
        'open_basedir' => (string) ini_get('open_basedir') ?: '—',
        'PHP' => PHP_VERSION.' / '.PHP_SAPI,
    ];
    $html = '<details open><summary class="mut">اطلاعات عیب‌یابی (برای پشتیبانی)</summary><table style="width:100%;font-size:12px">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td class="mut" style="white-space:nowrap;padding:2px 8px 2px 0">'.h($k).'</td><td><code>'.h((string) $v).'</code></td></tr>';
    }

    return $html.'</table></details>';
}

if (! class_exists(ZipArchive::class)) {
    page('خطا', '<p class="bad">اکستنشن zip روی PHP فعال نیست.</p>');
}

// Token: created automatically if possible; otherwise the user creates the file by hand in File Manager.
// Either way only someone with File Manager access can know it.
$writeError = null;
if (! file_exists($tokenFile)) {
    $ok = @file_put_contents($tokenFile, bin2hex(random_bytes(10)));
    if ($ok === false) {
        $writeError = error_get_last()['message'] ?? 'unknown';
    } else {
        @chmod($tokenFile, 0600);
    }
}
clearstatcache();
$token = file_exists($tokenFile) ? trim((string) @file_get_contents($tokenFile)) : '';
if (strlen($token) < 12) {
    page('توکن ساخته نشد', '<p class="bad">اسکریپت نتوانست فایل توکن را بسازد یا توکن کوتاه است.</p>
    <p><b>راه جایگزین:</b> در File Manager، داخل پوشه <code>'.h($target).'</code> یک فایل متنی جدید به نام <code>faran-unzip-token.txt</code> بسازید و یک رمز دلخواه انگلیسی، حداقل ۱۲ کاراکتر، در آن بنویسید و ذخیره کنید. سپس این صفحه را دوباره باز کنید و همان رمز را وارد کنید.</p>'
        .diagnostics($target, $tokenFile, $writeError));
}
if (! is_writable($target)) {
    page('خطا', '<p class="bad">پوشه مقصد قابل نوشتن نیست.</p>'.diagnostics($target, $tokenFile, $writeError));
}

// glob() may be disabled on shared hosts → scandir fallback.
$entries = function_exists('glob') ? (glob(__DIR__.'/*.zip') ?: []) : array_map(fn ($f) => __DIR__.'/'.$f, array_filter(scandir(__DIR__) ?: [], fn ($f) => str_ends_with(strtolower($f), '.zip')));
$zips = array_values(array_filter($entries, fn ($f) => str_contains(basename($f), 'commission')));
if (! $zips) {
    page('فایل ZIP پیدا نشد', '<p>فایل ZIP نسخه (نامی که شامل <code>commission</code> است) را کنار همین فایل در <code>'.h(__DIR__).'</code> آپلود کنید.</p>'.diagnostics($target, $tokenFile, $writeError));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $options = implode('', array_map(fn ($z) => '<option>'.h(basename($z)).'</option>', $zips));
    page('باز کردن بسته سامانه پورسانت', '<p>توکن را از این فایل (با File Manager) بخوانید:<br><code>'.h($tokenFile).'</code></p>
    <p class="mut">اگر فایل را نمی‌بینید، صفحه File Manager را Refresh کنید.</p>
    <form method="post"><label>فایل</label><select name="zip">'.$options.'</select>
    <label>توکن</label><input name="token" autocomplete="off" required><button>باز کردن</button></form>'.diagnostics($target, $tokenFile, $writeError));
}

if (! hash_equals($token, trim((string) ($_POST['token'] ?? '')))) {
    page('توکن اشتباه', '<p class="bad">توکن درست نیست.</p>');
}
$zipPath = __DIR__.'/'.basename((string) ($_POST['zip'] ?? ''));
if (! in_array($zipPath, $zips, true)) {
    page('خطا', '<p class="bad">فایل انتخاب‌شده معتبر نیست.</p>');
}

$zip = new ZipArchive;
if ($zip->open($zipPath) !== true) {
    page('خطا', '<p class="bad">فایل ZIP خراب است یا کامل آپلود نشده. دوباره آپلود کنید.</p>');
}

// Validate every entry before writing anything.
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')
        || ! (str_starts_with($name, 'commission_app/') || str_starts_with($name, 'public_html/'))
        || (str_starts_with($name, 'public_html/') && ! str_starts_with($name, 'public_html/commission/') && $name !== 'public_html/')) {
        $zip->close();
        page('بسته نامعتبر', '<p class="bad">مسیر غیرمجاز در بسته: <code>'.h($name).'</code></p>');
    }
}

$files = $dirs = 0;
$errors = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    $dest = $target.'/'.$name;
    if (str_ends_with($name, '/')) {
        if (! is_dir($dest) && ! @mkdir($dest, 0755, true)) {
            $errors[] = $name;
        }
        $dirs++;

        continue;
    }
    if (! is_dir(dirname($dest))) {
        @mkdir(dirname($dest), 0755, true);
    }
    $in = $zip->getStream($name);
    $out = $in ? @fopen($dest, 'wb') : false;
    if (! $in || ! $out || stream_copy_to_stream($in, $out) === false) {
        $errors[] = $name;
    }
    if ($in) { fclose($in); }
    if ($out) { fclose($out); @chmod($dest, 0644); }
    $files++;
}
$zip->close();

if ($errors) {
    page('باز کردن ناقص ماند', '<p class="bad">'.count($errors).' مورد نوشته نشد، مثلاً:</p><p><code>'.h(implode("\n", array_slice($errors, 0, 5))).'</code></p><p class="mut">فایل ZIP و این اسکریپت حذف نشدند تا دوباره امتحان کنید.</p>');
}

@unlink($zipPath);
@unlink($tokenFile);
$self = @unlink(__FILE__);

$installed = file_exists($target.'/commission_app/storage/installed.lock');
$next = $installed
    ? '<p>این یک <b>به‌روزرسانی</b> بود. وارد سامانه شوید و از «وضعیت سیستم» دکمه به‌روزرسانی پایگاه داده را بزنید.</p><p><a href="/commission/login">ورود به سامانه</a></p>'
    : '<p>حالا نصاب را باز کنید: <a href="/commission/install.php">/commission/install.php</a></p>';

page('بسته باز شد', '<p class="ok">'.number_format($files).' فایل و '.number_format($dirs).' پوشه در <code>'.h($target).'</code> قرار گرفت.</p>'.$next
    .($self ? '' : '<p class="bad">فایل <code>faran-unzip.php</code> را دستی حذف کنید.</p>'));
