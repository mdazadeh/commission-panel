# سامانه پورسانت فاران

سامانه تحت وب محاسبه، نمایش و تسویه پورسانت فروش شرکت صنایع الکترونیک فاران.
آدرس استقرار: `https://faranepanel.ir/commission` — هاست اشتراکی DirectAdmin، **بدون SSH**، سازگار با **اینترنت ملی** (هیچ CDN، فونت یا API خارجی).

## وضعیت

| فاز | محتوا | وضعیت |
|---|---|---|
| ۱ — زیرساخت | ورود، نقش‌ها (RBAC)، چندشرکتی، Audit زنجیره‌ای، دوره‌های فصلی شمسی، Import فاکتور فراپیام، نصاب تحت وب، بسته استقرار | ✅ |
| ۲ — موتور محاسبه | تارگت، Tier، کسورات، فعالیت، Trace، تست طلایی | ⏳ |
| ۳ — داشبورد و گزارش | داشبورد هفتگی، Snapshot، What-if، گزارش‌های MVP | ⏳ |
| ۴ — تسویه | گردش تأیید، قفل فصل، فایل پرداخت، اصلاحیه | ⏳ |

قوانین محاسبه و تصمیمات: [`docs/rules-catalog.md`](docs/rules-catalog.md)

## فناوری

- PHP 8.3 + Laravel 13، MySQL/MariaDB، Session/Cache/Queue روی دیتابیس (بدون Redis)
- React 19 + Inertia 3 + Tailwind 4، فونت وزیرمتن داخل بسته
- محاسبات مالی با `brick/math` (بدون float)، مبالغ به **ریال صحیح**
- اکسل با OpenSpout (خواندن جریانی)، تاریخ شمسی با `morilog/jalali`

## نقش‌ها

| نقش | دسترسی |
|---|---|
| کارشناس فروش | فقط داده خودش |
| رئیس فروش | خودش و تیمش |
| مدیر بازاریابی | کل سازمان، مشاهده Import |
| معاونت فروش و بازاریابی | کل سازمان، Audit، قوانین |
| مالی | کل سازمان، بارگذاری و ثبت Import، Audit |
| مدیر سامانه | کاربران و سیستم — **بدون** دسترسی خودکار به داده مالی |

کنترل دسترسی سمت سرور است (Route + محدوده داده)، نه فقط پنهان‌کردن منو.

## توسعه محلی

```bash
composer install
npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # admin / admin-dev-password
touch storage/installed.lock
npm run dev   # و در ترمینال دیگر: php artisan serve
```

تست‌ها:

```bash
php artisan test
npm run typecheck
```

## استقرار روی هاست (بدون SSH)

1. ساخت بسته: `scripts/build-release.sh` → فایل `dist/faran-commission-<version>-<sha>.zip`
2. در DirectAdmin ← File Manager، فایل ZIP را در `domains/faranepanel.ir/` آپلود و Extract کنید. نتیجه:
   - `domains/faranepanel.ir/commission_app/` — کد، vendor، storage، `.env` (خارج از دسترس وب)
   - `domains/faranepanel.ir/public_html/commission/` — فقط فایل‌های عمومی
3. در DirectAdmin یک دیتابیس MySQL **خالی** بسازید.
4. `https://faranepanel.ir/commission/install.php` را باز کنید. توکن نصب در `commission_app/storage/install-token.txt` ساخته می‌شود (از File Manager بخوانید).
5. پس از نصب، `install.php` خودکار حذف می‌شود (اگر نشد، دستی حذف کنید).
6. Cron (هر دقیقه) — مسیر PHP را با خروجی تست Cron تطبیق دهید:
   `/usr/local/bin/php /home/faranepa/domains/faranepanel.ir/commission_app/artisan schedule:run >> /dev/null 2>&1`

### به‌روزرسانی

ZIP نسخه جدید را روی همان مسیر Extract کنید (`.env` و داده‌های `storage` دست نمی‌خورند)، سپس از منوی «وضعیت سیستم» دکمه به‌روزرسانی پایگاه داده را بزنید. **قبل از هر به‌روزرسانی از دیتابیس Backup بگیرید.**

### محدودیت‌های هاست که در کد رعایت شده

- `exec`/`proc_open` غیرفعال است → هیچ کار زمان‌بندی‌شده‌ای با `Schedule::command` نوشته نمی‌شود؛ فقط `Schedule::call`/`job`.
- `max_execution_time=30` → پردازش‌های سنگین آینده به صف دیتابیس + Cron منتقل می‌شوند.
- جزئیات: [`docs/hosting-profile.md`](docs/hosting-profile.md)

## داده‌های واقعی

فایل‌های اکسل واقعی (نام افراد و مشتریان) **هرگز** وارد مخزن نمی‌شوند. تست‌ها با داده ساختگی هم‌شکل فراپیام اجرا می‌شوند.
