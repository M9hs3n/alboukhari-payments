# 🚀 دليل النشر على الاستضافة المشتركة (cPanel/Plesk)

> النظام يعمل بدون Vite ولا Node — كل الـ assets محمولة من CDN. يكفيك PHP 8.3+ و MySQL.

## ✅ متطلبات الاستضافة

- **PHP**: 8.3 أو أحدث (8.5 مدعوم)
- **MySQL/MariaDB**: 5.7+ أو 10.3+
- **Extensions المطلوبة**: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath`, `gd` (للـ Excel)
- **مساحة**: ≥ 200MB
- **Composer**: لا حاجة على الخادم (نرفع `vendor/`)

## 📦 الخطوات

### 1) تجهيز محلياً
```bash
cd /d/sheet_to_web/app
composer install --optimize-autoloader --no-dev
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### 2) رفع الملفات إلى الاستضافة

ارفع كل محتوى مجلّد `app/` ما عدا `node_modules/` (لا يوجد عندنا أصلاً).

**هيكل cPanel المُوصى به:**
```
/home/USER/
├── boukhari/           ← مجلد المشروع (خاص بك، ليس في public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/          ← هذا الذي يجب أن يُعرض
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── artisan
└── public_html/         ← مجلد الويب الافتراضي
    └── (انسخ محتوى boukhari/public/ هنا أو اربط رمزياً)
```

### 3) ربط `public_html`

**خيار أ (الأبسط)**: انسخ محتوى `boukhari/public/*` إلى `public_html/` وعدّل `index.php`:

```php
// في public_html/index.php
require __DIR__.'/../boukhari/vendor/autoload.php';
$app = require_once __DIR__.'/../boukhari/bootstrap/app.php';
```

**خيار ب (Subdomain)**: أنشئ Subdomain يشير إلى `boukhari/public/`.

### 4) ضبط `.env` على الخادم

```env
APP_NAME="مدرسة البخاري"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=Europe/Amsterdam
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USER_boukhari
DB_USERNAME=USER_admin
DB_PASSWORD=secret-pass

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

BULKGATE_APP_ID=...
BULKGATE_APP_TOKEN=...
BULKGATE_SENDER_ID=text
BULKGATE_SENDER_ID_VALUE="Al Boukhari"
BULKGATE_DEFAULT_COUNTRY=NL
```

### 5) توليد APP_KEY وتشغيل migrations

عبر **Terminal في cPanel** أو **SSH**:
```bash
cd ~/boukhari
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6) ضبط الصلاحيات

```bash
chmod -R 755 ~/boukhari
chmod -R 775 ~/boukhari/storage ~/boukhari/bootstrap/cache
```

### 7) ضبط Cron (للتذكير الدوري)

في cPanel → Cron Jobs، أضف:
```
* * * * * cd /home/USER/boukhari && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

(لو `php` غير `/usr/local/bin/php`، اسأل الدعم الفني للاستضافة.)

### 8) اختبار

افتح `https://yourdomain.com/` — يجب أن تظهر الشاشة الرئيسية.

افتح `https://yourdomain.com/up` — يجب أن تعرض "OK".

---

## 🔧 استكشاف الأخطاء

### الصفحة بيضاء
- فعّل `APP_DEBUG=true` مؤقتاً وأعد التحميل لمعرفة الخطأ.
- تحقق من `storage/logs/laravel-*.log`.

### "MissingAppKeyException"
- شغّل `php artisan key:generate`.

### "could not find driver"
- فعّل extension `pdo_mysql` من cPanel → Select PHP Version.

### "Permission denied"
- صلاحيات `storage/` و `bootstrap/cache/` يجب أن تكون 775.

### Tailwind لا يظهر
- النظام يستخدم Tailwind CDN — تأكد أن الخادم يستطيع الوصول للإنترنت الخارجي.
- بديل: نزّل ملف `tailwind.min.css` من https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css وضعه في `public/assets/css/` وعدّل الـ Layout.

---

## 🔐 الأمان

- ❌ **لا ترفع** `.env` على Git
- ❌ **لا تترك** `APP_DEBUG=true` في الإنتاج
- ✅ **استخدم HTTPS** (Let's Encrypt مجاني عبر cPanel)
- ✅ **نسخ احتياطي يومي** للقاعدة (إعدادات cPanel → Backups)
- ✅ **مفاتيح BulkGate** تُحفظ مشفّرة في DB

---

## 📊 التشغيل الأول

1. افتح `/import` وارفع `2026 send_students_pay_re_finale.xlsx`
2. افتح `/settings` وأدخل مفاتيح BulkGate
3. افتح `/templates` وعدّل القوالب لو أردت
4. من `/send` اختر "اختبر على رقمي" للتأكد
5. ابدأ الإرسال الفعلي

---

## ⚙️ Optimization (اختياري)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer dump-autoload --optimize
```

لإلغاء عند التطوير:
```bash
php artisan optimize:clear
```
