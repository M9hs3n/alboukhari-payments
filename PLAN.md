# خطة بناء نظام تذكير رسوم الطلاب — Laravel 12

> نسخة وثيقة الخطة: **1.0** — تاريخ الإنشاء: 2026-05-19
> الجهة: مدرسة Al Boukhari (هولندا)
> الاستبدال: نظام Google Sheets + Apps Script (BulkGate Transactional API) الحالي

---

## جدول المحتويات

1. [ملخص تنفيذي](#1-ملخص-تنفيذي)
2. [تحليل النظام الحالي](#2-تحليل-النظام-الحالي)
3. [أهداف النظام الجديد ومتطلباته](#3-أهداف-النظام-الجديد-ومتطلباته)
4. [حزمة التقنيات (Tech Stack)](#4-حزمة-التقنيات-tech-stack)
5. [نظرة معمارية عامة](#5-نظرة-معمارية-عامة)
6. [نموذج البيانات (Database Schema)](#6-نموذج-البيانات-database-schema)
7. [وحدات النظام (Modules) بالتفصيل](#7-وحدات-النظام-modules-بالتفصيل)
8. [تكامل مزوّد الرسائل (BulkGate + Abstraction)](#8-تكامل-مزوّد-الرسائل-bulkgate--abstraction)
9. [قوائم الانتظار والمهام الخلفية](#9-قوائم-الانتظار-والمهام-الخلفية)
10. [الجدولة والـ Triggers](#10-الجدولة-والـ-triggers)
11. [الأدوار والصلاحيات (RBAC)](#11-الأدوار-والصلاحيات-rbac)
12. [الواجهات (UX/UI)](#12-الواجهات-uxui)
13. [خطة ترحيل البيانات (Migration من Excel/Sheets)](#13-خطة-ترحيل-البيانات-migration-من-excelsheets)
14. [الأمن والامتثال](#14-الأمن-والامتثال)
15. [الاختبارات](#15-الاختبارات)
16. [النشر والـ DevOps](#16-النشر-والـ-devops)
17. [خارطة الطريق (Phases) ومعايير القبول](#17-خارطة-الطريق-phases-ومعايير-القبول)
18. [ملاحق فنية](#18-ملاحق-فنية)

---

## 1. ملخص تنفيذي

نبني نظام ويب احترافي بـ **Laravel 12** يستبدل سكربت Google Apps Script ومحفظة Google Sheets. النظام يدير:

- سجل الطلاب وأرقام أوليائهم.
- متابعة الدفعات الشهرية (12 شهر، بقيم: مدفوع كامل/جزئي/متأخر/مدفوع عبر البنك/فارغ).
- إرسال تذكيرات SMS تلقائيًا (أول جمعة + منتصف الشهر) ويدويًا (إرسال جماعي / حسب شهر وشرط).
- تحكم دقيق لكل طالب (إخفاء/حظر الرسائل/تعليق مؤقت/استثناء كلي).
- رسوم افتراضية للجميع + إمكانية رسوم مخصصة لكل طالب أو لكل (طالب × شهر).
- تكامل مزوّد BulkGate (SMS) مع طبقة تجريد تسمح بإضافة WhatsApp Cloud API أو مزوّد آخر لاحقًا.
- لوحة تحكم متعددة المستخدمين بصلاحيات RBAC، سجلات تدقيق، تقارير، استئناف ذاتي عند فشل المزوّد.

**القيمة المضافة عن السكربت الحالي:**

| النقطة | الحالي | الجديد |
|---|---|---|
| القيود لكل طالب | فقط `sms`/`send_all` boolean | إخفاء، حظر، تعليق مؤقت بفترة، يدرس مكانيًا (in-person) بدون رسائل، استثناء من حملة محددة |
| الرسوم | لا توجد قيمة هدف، خلية الشهر فقط | رسوم افتراضية + override لكل طالب + override لكل (طالب × شهر) + رسوم إضافية |
| التحقق | لا يوجد | حساب رصيد (متبقّي) لكل شهر تلقائيًا |
| سجل التغييرات | لا يوجد | Audit log + Activity log + سجل رسائل قابل للبحث والتصدير |
| الإرسال | Apps Script 6 دقائق + استئناف يدوي | Queue + Horizon، بدون قيود زمنية، إعادة محاولة ذكية، Idempotency |
| المستخدمون | مالك الشيت فقط | متعدد المستخدمين بصلاحيات (Admin/Operator/Viewer/Accountant) |
| التقارير | لا توجد | لوحة معلومات، تقارير مالية، إحصاءات تسليم |
| الواجهة | Sidebars داخل Sheets | لوحة ويب كاملة Responsive + RTL |

---

## 2. تحليل النظام الحالي

### 2.1 بنية ورقة Sheet1

```
| id | Naam | Telefoon | sms | whatsapp | send_all | Tweede telefoonnummer | January..December |
```

- **347 طالب** نشط، **297** برقم أساسي، **45** برقم ثانوي.
- خلية الشهر لها أربع حالات دلالية:
  - **فارغة** ⇒ لم يدفع (مرشح للتذكير).
  - **رقم > 0** (الافتراضي 30، وقد تظهر 10/15/45) ⇒ دفع كامل/جزئي.
  - **`X`** (نص) ⇒ متأخر يدويًا (مرشح لإنذار 15 من الشهر).
  - **`0`** ⇒ "دفع عبر البنك / مسجّل لاحقًا / دفع سابقًا" (حسب ورقة "معومات مهمه").

### 2.2 الـ Triggers والمسارات الموجودة في `script.js`

| الوظيفة | الجدولة | الشرط على خلية الشهر | القالب الافتراضي |
|---|---|---|---|
| `sendFirstFridayReminders` | يومي 09:05 (إن كان أول جمعة) | فارغة | تذكير لطيف هولندي |
| `sendMidMonthLateNotice` | يومي 09:05 (إن كان يوم 15) | `x` | إنذار تأخير هولندي |
| `runSendAllWithMessage` | يدوي (Sidebar) | `send_all=TRUE` | نص حر مع `{{Naam}}` و `{{month}}` |
| `runSendByMonthAndCondition` | يدوي (Sidebar) | `empty` / `x` / `0` | نص حر |

### 2.3 خصائص فنية ينبغي الحفاظ عليها

- **تطبيع أرقام هولندية**: 06xxxxxxxx ⇒ `+316xxxxxxxx`, 031..., 0031..., 31..., 6xxxxxxxx.
- **ASCII Sanitization** افتراضي `on`: يحوّل الأحرف اللاتينية بعلامات تشكيل إلى ASCII لتقليل عدد segments الـ SMS (160/153).
- **حساب segments**: ≤160 = 1 رسالة، وإلا 153 لكل segment.
- **Rate limit ساعي** (افتراضي 2500/ساعة، قابل للضبط).
- **Halt switch** عام يوقف الإرسال فورًا.
- **Checkpoint** كل N رسالة لاستئناف عند الفشل.
- **`tag`** يُرسل لـ BulkGate لتمييز الحملة في التقارير.
- **سجل SMS_Log**: Timestamp, Type, Month, StudentID, Naam, Telefoon, Segments, Status, Message.

### 2.4 نقاط الضعف الحالية (سنعالجها)

1. **حدود Apps Script** (6 دقائق/استدعاء، quotas يومية، تعقيد الاستئناف).
2. **لا يوجد فصل بيانات/منطق/عرض**: كل شيء في ملف واحد 1100 سطر.
3. **لا تحقق من الإدخال** (الأرقام، النصوص، تكرار الـ id).
4. **لا يوجد مفهوم "رصيد"** أو "حد رسوم"، فقط خلايا.
5. **لا يوجد كنترول دقيق لكل طالب** (الإخفاء/الاستثناء/التعليق).
6. **لا يوجد نظام مستخدمين**.
7. **سجل الرسائل يُكتب على نفس الشيت** ويمكن العبث به.
8. **لا توجد تقارير ولا داشبورد**.

---

## 3. أهداف النظام الجديد ومتطلباته

### 3.1 متطلبات وظيفية (Functional)

#### F-01 إدارة الطلاب
- CRUD كامل (إضافة/تعديل/حذف ناعم Soft Delete/استرجاع).
- حقول لكل طالب:
  - `external_id` (نفس id من Sheet للمحافظة على التوافق).
  - `name`, `phone_primary`, `phone_secondary`.
  - `is_hidden` (لا يظهر في القوائم الافتراضية).
  - `is_blocked_messages` (لا تُرسل له رسائل أبدًا).
  - `is_in_person` (يدرس مكانيًا — لا تُرسل تذكيرات).
  - `excluded_from_send_all` (مستثنى من حملات send_all فقط، لكن لا يزال يستقبل الجدولة التلقائية).
  - `notes` (ملاحظات).
  - `enrolled_at`, `withdrawn_at`.

#### F-02 العزل المؤقت (Suspensions)
- جدول مستقل `student_suspensions`:
  - `student_id`, `starts_at`, `ends_at` (nullable = مفتوح), `reason`, `created_by`.
  - يمنع الإرسال لأي رسالة (تذكير/جماعي/شرطي) طالما `now()` داخل النطاق.
- يمكن جدولة عزل مستقبلي (مثلاً عطلة، أو حتى انتهاء تسوية مالية).
- شاشة عرض جميع العزلات النشطة/المنتهية.

#### F-03 إدارة الرسوم
- **الرسوم الافتراضية العامة** (Setting): مثلاً 30 يورو/شهر.
- **Override لكل طالب** (Student Default Fee): يطغى على العام لجميع الأشهر.
- **Override لكل (طالب × شهر)** (Student Monthly Fee Override): يطغى على الاثنين.
- **رسوم إضافية** (Surcharges): مبالغ إضافية تُضاف لطالب معيّن لشهر معيّن مع سبب (كتاب، رحلة، إلخ).
- حساب **المبلغ المستحق** لكل (طالب × شهر) تلقائيًا = `fee_resolved(student, month) + surcharges(student, month)`.

#### F-04 إدارة الدفعات
- جدول `payments` فيه:
  - `student_id`, `period_year`, `period_month` (1..12), `amount`, `paid_at`, `method` (`cash`/`bank`/`manual`/`legacy_zero`), `reference`, `note`, `recorded_by`.
- حساب **الرصيد المتبقي** لكل (طالب × شهر) = `due - sum(payments)`.
- حالة الشهر (محسوبة):
  - `not_due_yet` (الشهر مستقبلي ولم يبدأ بعد).
  - `paid` (الرصيد ≤ 0).
  - `partial` (دفع جزء).
  - `unpaid` (لم يدفع شيء وحان الوقت).
  - `late` (متأخر — تجاوز يومًا محددًا من الشهر التالي).
  - `bank_recorded` (سُجل دفع بطريقة `bank` وقيمته 0 إذا اتُّفق أن البنك يُسجَّل بـ 0).
- استيراد `0` و `X` من الشيت لمنطق متوافق (انظر §13).

#### F-05 إدارة القوالب (Templates)
- مكتبة قوالب: لكل قالب: `code`, `name`, `language` (`nl`, `ar`, `en`), `body`, `is_default_for` (one of: `first_friday`, `mid_month`, `none`).
- متغيرات مدعومة:
  - `{{Naam}}`, `{{name}}` — اسم الطالب.
  - `{{month}}` — اسم الشهر بالإنجليزية (مطابق للأعمدة).
  - `{{month_nl}}` — الشهر بالهولندية.
  - `{{due_amount}}`, `{{paid_amount}}`, `{{balance}}` — المبالغ.
  - `{{year}}`.
- معاينة حية مع طالب-عيّنة وحساب segments.
- زر إدراج المتغير، حفظ آخر نص استخدم (Per-user).

#### F-06 الحملات (Campaigns) والإرسال
أربعة أنواع (متوافق تمامًا مع السكربت الحالي):

| النوع | الفلتر | المصدر |
|---|---|---|
| `send_all` | بدون شرط شهر، شرط طالب: `messages_allowed` و `included_in_send_all`. | يدوي |
| `first_friday` | الشهر الحالي، الحالة `unpaid` فقط. | تلقائي (Trigger) + يدوي |
| `mid_month` | الشهر الحالي، الحالة `late` (المعادل لـ `x`). | تلقائي + يدوي |
| `by_month_condition` | شهر مختار + شرط (`unpaid`, `late`, `bank_recorded`, `partial`, مخصص). | يدوي |

ميزات لكل حملة:
- اختيار قالب أو كتابة نص حر مع متغيرات.
- معاينة قائمة المستلمين قبل الإرسال (Dry Run) — تعرض من سيُرسل له ومن سيُستثنى ولماذا (محجوب/مخفي/معلَّق/مكاني/...).
- `Start From ID` لاستئناف يدوي.
- `Tag` تلقائي يربط الحملة بسجلات الـ Provider.
- Halt/Resume للحملة الواحدة وكذلك Halt عام (Kill switch).

#### F-07 الجدولة التلقائية
- **First Friday**: في تمام 09:05 ظهر يوم الجمعة الأول من كل شهر، إنشاء حملة `first_friday` تلقائيًا.
- **Mid-Month**: في تمام 09:05 يوم 15 من الشهر، إنشاء حملة `mid_month` تلقائيًا.
- زمن قابل للضبط من الإعدادات.
- يمكن تمكين/تعطيل كل واحدة على حدة.
- "Dry Run تلقائي" قبل ساعة من التشغيل يولّد تقريرًا بالعدد المتوقع لقائمة المسؤول لتنبيهه.

#### F-08 منع/استثناء/إخفاء طالب (المتطلبات التفصيلية)
| الحالة | السلوك | حيث يُمنَع |
|---|---|---|
| `is_hidden=true` | لا يظهر في القوائم/التقارير/الحملات افتراضيًا (يظهر فقط في عرض "المخفيون"). | كل الحملات وكل العروض |
| `is_blocked_messages=true` | يُحفظ في القاعدة لكن لا تُرسل له رسائل أبدًا. | كل الحملات (تظهر سبب الاستثناء في Dry Run) |
| `is_in_person=true` | "يدرس مكانيًا/يحضر شخصيًا للتسوية" — لا تُرسل تذكيرات لكنه يبقى ظاهرًا في التقارير المالية. | كل الحملات |
| `excluded_from_send_all=true` | يُستثنى من `send_all` فقط؛ يستقبل التذكيرات التلقائية. | حملات `send_all` فقط |
| `student_suspensions` نشط الآن | يُستثنى مؤقتًا من كل الحملات حتى ينتهي. | كل الحملات |

#### F-09 السجلات والتقارير
- **Message Log** بحقول: timestamp, type, campaign_id, student_id, name, phone, segments, status, provider_response, raw_text, tag, cost_estimated.
- **Audit Log**: من غيّر ماذا ومتى (Spatie Activity Log).
- **تقارير**:
  - رصيد كل طالب لكل شهر.
  - إجمالي المستحقات vs المحصلات (شهري/سنوي).
  - أعلى المتأخرين.
  - تقرير حملة (نسبة التسليم/الأخطاء/التكلفة).
  - تقرير استخدام الـ Quota الساعي (charts).

### 3.2 متطلبات غير وظيفية (Non-functional)

| الفئة | المتطلب |
|---|---|
| الأداء | معالجة ≥ 5000 رسالة/ساعة بدون اختناق (Queue workers قابلة للسحب). |
| الموثوقية | Idempotency لكل رسالة، إعادة محاولة Backoff Exponential، Dead-letter queue. |
| الأمان | TLS كامل، تشفير حقول حساسة (Tokens) في DB، Rate-limit للـ Auth، CSRF/XSS الافتراضية في Laravel. |
| الخصوصية | GDPR — حق المسح، تصدير بيانات الطالب، احتفاظ بسجلات الرسائل 12 شهر افتراضيًا. |
| التشغيل | Zero-downtime deploy، Health checks، Monitoring (Telescope في تطوير، Sentry في إنتاج). |
| التعريب | RTL كامل، دعم العربية والهولندية والإنجليزية في الواجهة، Localization مفاتيح. |
| التوافق | استيراد من نفس صيغة Excel/CSV الحالية وتصدير بنفس البنية للحفاظ على مصدر احتياطي. |

---

## 4. حزمة التقنيات (Tech Stack)

| الطبقة | الاختيار | السبب |
|---|---|---|
| Framework | **Laravel 12** (أو 11 إن كان 12 لم يصدر LTS بعد عند البدء) | الناضج، Queues/Scheduler/Auth جاهزة |
| PHP | 8.3+ | متطلب Laravel 12 |
| DB | **MySQL 8.0** (أو MariaDB 11/PostgreSQL 16) | إنتاج موثوق |
| Cache/Queue | **Redis 7** + **Laravel Horizon** | لمراقبة الـ workers والـ retries |
| Front-end | **Livewire 3** + **Alpine.js** + **Tailwind CSS 3** | RTL سلس، إنتاجية عالية، بدون SPA معقّد |
| UI Kit | **Filament 3** للوحة الإدارة | يوفّر CRUD/Filters/Charts/Forms جاهزة احترافية |
| HTTP Client | Laravel HTTP (Guzzle) | المدمج |
| Auth | Laravel Fortify/Breeze + 2FA (Google2FA) | الأمان |
| RBAC | **Spatie Laravel-Permission** | متعارف عليه |
| Audit | **Spatie Laravel-Activitylog** | يلتقط كل التغييرات |
| Excel | **maatwebsite/excel** | استيراد/تصدير |
| Phone | **giggsey/libphonenumber-for-php** (Propaganistas/Laravel-Phone) | تطبيع دولي معتمد |
| Tests | PHPUnit + Pest + Laravel Dusk (E2E) | تغطية كاملة |
| Errors | **Sentry** | يقظة إنتاج |
| Logs | **Monolog → stack (daily + Sentry)** | |
| Deploy | Docker + Docker Compose، أو Forge/Ploi، أو Laravel Vapor | سهولة الترقيات |
| CI/CD | GitHub Actions | اختبارات + lint + deploy |

---

## 5. نظرة معمارية عامة

```
┌────────────────────────────────────────────────────────────┐
│                       Browser (RTL)                        │
└──────────────────────────┬─────────────────────────────────┘
                           │ HTTPS
┌──────────────────────────▼─────────────────────────────────┐
│              Laravel 12 — App Layer                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────────┐ │
│  │ Filament │  │ Livewire │  │  HTTP    │  │  Console   │ │
│  │  Panel   │  │ Pages    │  │  API     │  │ Scheduler  │ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └─────┬──────┘ │
│       │             │              │              │        │
│  ┌────▼─────────────▼──────────────▼──────────────▼──────┐│
│  │              Domain Services (Actions)                ││
│  │  Students • Fees • Payments • Campaigns • Templates   ││
│  │             Suspensions • Reports • Auth              ││
│  └────────────────────────┬──────────────────────────────┘│
│                           │                               │
│  ┌────────────────────────▼──────────────────────────────┐│
│  │  Eloquent Models + Repositories + Policies            ││
│  └────────────────────────┬──────────────────────────────┘│
└───────────┬───────────────┼───────────────┬───────────────┘
            │               │               │
   ┌────────▼─────┐  ┌──────▼──────┐  ┌─────▼──────────┐
   │  MySQL 8     │  │ Redis 7     │  │ Provider Bridge │
   │  (data)      │  │ (queue/cache)│  │ (BulkGate/WhatsApp) │
   └──────────────┘  └─────────────┘  └────────┬───────┘
                            ▲                  │
                            │                  │ HTTPS
                     ┌──────┴──────┐    ┌─────▼─────┐
                     │ Horizon UI  │    │ BulkGate  │
                     │ (Workers)   │    │ Portal API│
                     └─────────────┘    └───────────┘
```

### 5.1 الطبقات (Layered Architecture)

1. **Presentation**: Filament Panel + Livewire pages + Blade.
2. **Application (Actions/Services)**: Use-Cases معزولة:
   - `SendCampaignAction`
   - `BuildRecipientListAction`
   - `RecordPaymentAction`
   - `ResolveStudentFeeAction`
   - `SuspendStudentAction`
   - ...
3. **Domain**: Eloquent Models + Enums + Value Objects (`Money`, `PhoneNumber`, `MonthKey`).
4. **Infrastructure**:
   - `MessageProvider` interface + `BulkGateDriver` + (لاحقًا) `WhatsAppCloudDriver`.
   - `Importers` (`StudentImporter` يقرأ xlsx بنفس البنية).
   - `Schedulers` (يستبدل Apps Script triggers).
5. **Cross-cutting**: Policies, Observers, Events, Listeners, Audit.

### 5.2 ميزات تشغيلية معتمدة

- **Queues** (Redis، Horizon): كل رسالة Job مستقل = `SendSmsJob` مع `tries=5`, `backoff=[60, 300, 900, 1800, 3600]`.
- **Idempotency**: كل رسالة لها `idempotency_key = sha1(campaign_id|student_id|phone|month|attempt-window)`.
- **Locking**: استخدام `Cache::lock()` على مفتاح الحملة لمنع تشغيل نفس الحملة من اثنين في وقت واحد.
- **Halt switch**: مفتاح `system.halt_sending` في DB + Redis، يُفحص قبل كل send.
- **Hourly quota**: Redis counter `bg:hour:{YYYYMMDDHH}` بحدّ من الإعدادات.

---

## 6. نموذج البيانات (Database Schema)

> الجداول الرئيسية مع الحقول الحرجة. الجدول الكامل في `database/migrations/`.

### 6.1 `students`
| العمود | النوع | ملاحظة |
|---|---|---|
| id | bigint PK | |
| external_id | bigint unique nullable | يحفظ الـ id الأصلي من الشيت |
| name | string(255) | |
| phone_primary_raw | string nullable | الإدخال الخام |
| phone_primary_e164 | string(20) nullable | بعد التطبيع |
| phone_secondary_raw | string nullable | |
| phone_secondary_e164 | string(20) nullable | |
| allow_sms | boolean default true | يقابل `sms` |
| allow_whatsapp | boolean default true | يقابل `whatsapp` |
| included_in_send_all | boolean default true | يقابل `send_all` |
| is_hidden | boolean default false | إخفاء من الواجهات |
| is_blocked_messages | boolean default false | حظر كامل للرسائل |
| is_in_person | boolean default false | يدرس مكانيًا |
| excluded_from_send_all | boolean default false | (إضافة جديدة منفصلة عن included_in_send_all لتفصيل أوضح) |
| default_fee_amount | decimal(8,2) nullable | override للطالب فقط |
| notes | text nullable | |
| enrolled_at | date nullable | |
| withdrawn_at | date nullable | حال السحب |
| created_by, updated_by | FK users | |
| timestamps + softDeletes | | |

Indexes: `name`, `phone_primary_e164`, `(is_hidden, is_blocked_messages, is_in_person)`.

### 6.2 `student_suspensions`
| العمود | النوع |
|---|---|
| id | PK |
| student_id | FK |
| starts_at | datetime |
| ends_at | datetime nullable |
| reason | string nullable |
| created_by | FK users |
| timestamps | |

Index: `(student_id, starts_at, ends_at)`.

### 6.3 `fee_settings` (Singleton عبر key/value أو جدول إعدادات عام)
| key | value |
|---|---|
| `default_monthly_fee` | decimal(8,2) — الافتراضي 30.00 |
| `currency` | EUR |
| `school_year_start_month` | 1 (يناير) أو 9 (سبتمبر) — قابل للضبط |

### 6.4 `student_monthly_fee_overrides`
| العمود | النوع |
|---|---|
| id | PK |
| student_id | FK |
| period_year | smallint |
| period_month | tinyint (1..12) |
| amount | decimal(8,2) |
| reason | string nullable |
| created_by | FK |
| timestamps | |

Unique: `(student_id, period_year, period_month)`.

### 6.5 `student_surcharges` (رسوم إضافية)
| العمود | النوع |
|---|---|
| id | PK |
| student_id | FK |
| period_year | smallint |
| period_month | tinyint |
| amount | decimal(8,2) |
| reason | string |
| created_by | FK |
| timestamps | |

### 6.6 `payments`
| العمود | النوع |
|---|---|
| id | PK |
| student_id | FK |
| period_year | smallint |
| period_month | tinyint |
| amount | decimal(8,2) |
| paid_at | date |
| method | enum(`cash`,`bank`,`manual`,`legacy_zero`) |
| reference | string nullable |
| note | text nullable |
| recorded_by | FK users |
| timestamps | |

Index: `(student_id, period_year, period_month)`.

> **القاعدة المحسوبة**: حالة (طالب × شهر) تُحسب من `fee_resolved - sum(payments)` ولا تُخزّن إلا في عرض/Materialized View إن لزم الأداء.

### 6.7 `templates`
| العمود | النوع |
|---|---|
| id | PK |
| code | string unique |
| name | string |
| language | enum(`nl`,`ar`,`en`) |
| body | text |
| is_default_for | enum nullable (`first_friday`,`mid_month`) |
| created_by | FK |
| timestamps + softDeletes | |

### 6.8 `campaigns`
| العمود | النوع |
|---|---|
| id | PK |
| type | enum(`send_all`,`first_friday`,`mid_month`,`by_month_condition`) |
| status | enum(`draft`,`queued`,`running`,`paused`,`completed`,`failed`,`canceled`) |
| period_year | smallint nullable |
| period_month | tinyint nullable |
| condition | enum nullable (`unpaid`,`late`,`bank_recorded`,`partial`,`paid`) |
| template_id | FK nullable |
| body_resolved | text — يُحفظ النص النهائي للقالب وقت الإطلاق |
| tag | string |
| start_from_external_id | bigint nullable |
| total_recipients | int default 0 |
| sent_count, failed_count, skipped_count | int default 0 |
| started_at, finished_at | datetime nullable |
| created_by | FK users |
| timestamps | |

### 6.9 `campaign_recipients` (Snapshot لمن سيُرسل لهم)
| العمود | النوع |
|---|---|
| id | PK |
| campaign_id | FK |
| student_id | FK |
| phone_e164 | string |
| body_personalized | text |
| status | enum(`pending`,`sending`,`sent`,`failed`,`skipped`) |
| skip_reason | string nullable (لماذا استُثني في Dry Run) |
| provider_message_id | string nullable |
| provider_status | string nullable |
| segments | tinyint |
| cost_estimated | decimal(8,4) nullable |
| attempts | tinyint default 0 |
| last_error | text nullable |
| sent_at | datetime nullable |
| idempotency_key | string unique |
| timestamps | |

Index: `(campaign_id, status)`.

### 6.10 `message_logs` (سجل خام أبدي يمكن البحث فيه)
| العمود | النوع |
|---|---|
| id | PK |
| campaign_id | FK nullable |
| student_id | FK nullable |
| recipient_id | FK nullable |
| direction | enum(`outbound`,`inbound`) |
| provider | string |
| phone | string |
| body | text |
| segments | tinyint |
| status | string |
| provider_response | jsonb/text |
| tag | string nullable |
| created_at | datetime |

### 6.11 `provider_quotas` (تتبّع الـ Rate Limits)
| key | value |
|---|---|
| `bg:hour:{YYYYMMDDHH}` | counter |
| `bg:max_per_hour` | 2500 |
| `bg:batch_size` | 150 |
| `bg:sleep_between_batch_ms` | 5000 |

(يُحفظ في Redis مع نسخة ثابتة في `settings`.)

### 6.12 `users`, `roles`, `permissions` (من Spatie)
Roles مقترحة: `super_admin`, `admin`, `operator`, `accountant`, `viewer`.

### 6.13 `settings` (key/value عام)
يحوي: `default_monthly_fee`, `currency`, `sms_force_ascii`, `sender_id`, `sender_id_value`, `default_country`, `trigger_first_friday_enabled`, `trigger_mid_month_enabled`, `trigger_hour`, `trigger_minute`, `max_per_hour`, إلخ.

### 6.14 `audit_logs` (Spatie Activitylog)
كل تغيّر في `students`, `payments`, `campaigns`, `templates`, `settings`.

---

## 7. وحدات النظام (Modules) بالتفصيل

### 7.1 وحدة الطلاب (Students Module)

**الواجهة في Filament:**
- Resource كامل (List/Create/Edit/View).
- Bulk actions: `Hide`, `Unhide`, `Block messages`, `Unblock`, `Mark in-person`, `Suspend (modal لاختيار النطاق)`, `Exclude from send_all`.
- Filters: `is_hidden`, `is_blocked`, `is_in_person`, `currently_suspended`, `has_overdue`, `enrolled_after`, `withdrawn_before`.
- Tab داخل صفحة الطالب:
  - **Overview**: ملخص الرصيد السنوي.
  - **Fees & Overrides**: تعديل الرسوم الافتراضية وإضافة override لأشهر.
  - **Payments**: تسجيل دفعة.
  - **Surcharges**: رسوم إضافية.
  - **Messages**: تاريخ الرسائل المرسلة له.
  - **Suspensions**: إدارة فترات العزل.
  - **Audit**: من غيّر ماذا.

**Validation:**
- الاسم مطلوب، ≥ 2 حرف.
- الهاتف يُطبَّع تلقائيًا بـ `libphonenumber` ويُرفض إن لم يصلح لـ NL/EU.
- منع تكرار `external_id`.

### 7.2 وحدة الرسوم (Fees Module)

**Action مركزي**: `ResolveStudentFeeAction(student, year, month) => Money`
- منطق:
  1. إن وُجد `student_monthly_fee_override(student, year, month)` ⇒ استخدمه.
  2. وإلا إن `student.default_fee_amount != null` ⇒ استخدمه.
  3. وإلا استخدم `setting('default_monthly_fee')`.
- يضيف `surcharges(student, year, month).sum('amount')`.

**واجهات:**
- شاشة "Fee Settings" لتغيير الافتراضي العام.
- شاشة "Bulk Override" لتطبيق رسم خاص على مجموعة طلاب (مثلاً صف معيّن إذا أضيف لاحقًا).

### 7.3 وحدة الدفعات (Payments Module)

- شاشة تسجيل دفعة سريعة (بحث طالب → اختيار شهر → مبلغ → طريقة).
- استيراد كشف بنك CSV (اختياري — Phase 2): مطابقة بالاسم/المرجع.
- منع التسجيل المكرر بنفس (طالب × شهر × مبلغ × تاريخ) خلال 5 دقائق (Idempotency).

### 7.4 وحدة العزل المؤقت (Suspensions Module)

- إنشاء عزل بـ "بدء" و"نهاية" (نهاية اختيارية).
- يمكن أن يكون مستقبليًا.
- في Dry Run الحملة، يظهر `skip_reason = "suspended until 2026-06-30"`.
- جدولة Job يومي ينظّف/يضيف tags `suspension.expired` للتنبيه.

### 7.5 وحدة القوالب (Templates Module)

- محرر نص بسيط مع أزرار إدراج المتغيرات.
- معاينة على طالب-عيّنة (يختار المستخدم طالبًا، يرى الرسالة النهائية وعدد الـ segments بنظام ASCII).
- `is_default_for=first_friday/mid_month` يربط القالب تلقائيًا بالمشغّل التلقائي.
- نسخ قالب (Duplicate).
- اللغة افتراضيًا `nl`، مع دعم `ar`/`en`.

### 7.6 وحدة الحملات (Campaigns Module)

**حياة الحملة:**
```
draft → (Build recipients) → queued → running → completed
                                ↘ paused (Halt) → resumed → running
                                ↘ failed
                                ↘ canceled
```

**الخطوات:**
1. **Build**: `BuildRecipientListAction` يبني `campaign_recipients` بناءً على:
   - نوع الحملة + شهر + شرط.
   - استبعاد:
     - `is_blocked_messages` ⇒ skip "blocked".
     - `is_in_person` ⇒ skip "in_person".
     - عزل نشط ⇒ skip "suspended".
     - `is_hidden` ⇒ skip "hidden".
     - حملة `send_all`: إضافيًا `excluded_from_send_all` أو `included_in_send_all=false`.
     - `allow_sms=false` ⇒ skip "sms_disabled".
     - بدون هاتف صالح ⇒ skip "no_phone".
   - تخصيص النص (Render template مع المتغيرات).
   - حساب segments وإيداع الـ idempotency key.
2. **Preview/Dry Run**: عرض القائمة في جدول قابل للتصفية مع أعمدة (Name, Phone, Body, Segments, Status, Reason).
3. **Confirm & Queue**: ينتقل لـ `queued` وتُدفع المهام في Redis.
4. **Run**: Worker (`SendSmsJob`) يرفع كل رسالة على حدة (أو دفعة 150 كما في السكربت).
5. **Monitor**: شاشة الحملة تظهر progress bar حي عبر Livewire + Echo (اختياري Pusher/WebSockets).

**حالة الإيقاف:**
- زر "إيقاف فوري" يضع `system.halt_sending=true` ويوقف الـ workers (يتحقق Job قبل البدء).
- استئناف لاحقًا = `false` + إعادة Dispatch للـ `pending`.

**Halting شامل أم فقط حملة؟**
- نوفّر الاثنين: `Halt All` (يوقف كل الحملات الجارية) و`Halt Campaign #X` (يوقف حملة محددة).

### 7.7 وحدة التقارير (Reports Module)

- **Dashboard**:
  - بطاقات: إجمالي الطلاب، الفعّالون، المتأخرون، المستحقات الشهرية، المحصلات، رصيد العام.
  - مخطط أعمدة: مدفوع/متأخر/فارغ لكل شهر للسنة الحالية.
  - مخطط خطي: استهلاك Quota الـ SMS أسبوعيًا.
  - قائمة آخر 10 حملات + حالتها.
- **Report Builder** (Filament): فلاتر شامل بحفظ.
- تصدير CSV/Excel/PDF.

### 7.8 وحدة المستخدمين والصلاحيات (Auth/RBAC)

- Login بـ Email + Password + اختيارياً 2FA (TOTP).
- Roles:
  - `super_admin`: كل شيء + إدارة المستخدمين والإعدادات الحساسة (API tokens).
  - `admin`: كل شيء عدا إدارة المستخدمين/الـ API tokens.
  - `operator`: حملات، تسجيل دفعات، طلاب.
  - `accountant`: دفعات، رسوم، تقارير مالية. لا يرسل رسائل.
  - `viewer`: قراءة فقط.
- كل عملية حساسة (إطلاق حملة، حذف طالب، تغيير إعداد) تتطلب تأكيد بكلمة المرور (Confirm Password mode).

### 7.9 وحدة الإعدادات (Settings Module)

شاشة موحدة مقسّمة:
- **عام**: العملة، تاريخ السنة الدراسية، اللغة الافتراضية.
- **الرسوم**: الرسم الافتراضي الشهري.
- **SMS**: SenderID, SenderID Value, Default Country, Force ASCII (on/off), Max per hour, Batch size, Sleep ms.
- **الجدولة**: تفعيل First Friday/Mid Month + ساعة:دقيقة + قوالب افتراضية لكل واحدة.
- **الأمان**: مدة الجلسة، 2FA إلزامي، IP allowlist (اختياري).
- **API Keys (BulkGate)**: تُحفظ مشفّرة في DB.

---

## 8. تكامل مزوّد الرسائل (BulkGate + Abstraction)

### 8.1 الـ Interface
```php
interface MessageProvider {
    public function send(OutboundMessage $msg): ProviderResult;
    public function name(): string;
}
```

### 8.2 `BulkGateDriver` (تنفيذ متوافق مع السكربت الحالي)
- يستخدم: `application_id`, `application_token`, `sender_id=text`, `sender_id_value=Al Boukhari`, `country=NL`, `unicode=false` افتراضيًا.
- Endpoint: `https://portal.bulkgate.com/api/2.0/advanced/transactional`.
- معالجة:
  - 429 / 503 ⇒ `RateLimitException` (Backoff طويل، إعادة Dispatch بعد 65 دقيقة افتراضيًا — مطابق `RESUME_DELAY_MINUTES`).
  - `an_hourly_transaction_messages_quota_has_been_exhausted` ⇒ كذلك.
  - 2xx: قراءة `body.data.response[0].status` (`accepted`/`sent`/`scheduled`).
  - 4xx/5xx أخرى ⇒ تُسجَّل كـ Error نهائي بعد عدد المحاولات.

### 8.3 تطبيع الأرقام
- استخدام `libphonenumber` مع region افتراضي `NL`.
- Pre-processing لحالات سكربتك الحالي: `+0031`, `0031`, `+031`, `031`, `06xxxxxxxx`, `6xxxxxxxx`, scientific notation, `.0` لاحقة، فواصل `/` و`-` و` `.
- يُحفظ الـ E.164 و الـ raw للمراجعة.

### 8.4 ASCII Sanitization (مطابق)
- NFD + إزالة diacritics.
- استبدال: `’ ‘ “ ” — – … € £ •` ⇒ ASCII.
- إزالة أي حرف غير ASCII.
- صلاحية تخطّي ASCII لحملة معينة (مثلاً عربية) ⇒ سيُحسب الـ segment بـ UCS-2 (70/67).

### 8.5 طبقات WhatsApp/Email المستقبلية
- إضافة `WhatsAppCloudDriver` لاحقًا بنفس الـ interface.
- `allow_whatsapp` على الطالب يحدد إن كان يستقبل واتساب.
- يمكن إنشاء `MultiChannelStrategy`: حاول WhatsApp أولًا، فشل ⇒ Fallback SMS.

---

## 9. قوائم الانتظار والمهام الخلفية

### 9.1 Queues
- `default` — للعمليات العادية.
- `sms-high` — أولوية عليا (تذكيرات تلقائية).
- `sms-bulk` — للحملات اليدوية الكبيرة.
- `reports` — لتوليد التقارير الثقيلة.

### 9.2 الـ Jobs الأساسية
| Job | Queue | Tries | Notes |
|---|---|---|---|
| `BuildCampaignRecipientsJob` | default | 1 | يبني الـ snapshot. |
| `SendSmsJob` | sms-bulk أو sms-high | 5 | Backoff exponential. يتحقق من `halt`، quota، suspension. |
| `MarkCampaignStatusJob` | default | 3 | يحسب progress ويغلق الحملة. |
| `DailyTriggerDispatcherJob` | default | 1 | يحلّ محل `remindersDailyDispatcher`. |
| `ImportStudentsJob` | default | 1 | استيراد ملف Excel/CSV. |
| `RotateMessageLogsJob` | reports | 1 | أرشفة شهرية. |

### 9.3 Horizon
- Dashboard على `/horizon` محمي بـ `viewHorizon` Gate.
- إعدادات Auto-scaling لـ workers (Adaptive) خصوصًا للحملات الكبيرة.

---

## 10. الجدولة والـ Triggers

### 10.1 Laravel Scheduler (`app/Console/Kernel.php`)
```
$schedule->job(new DailyTriggerDispatcherJob)
         ->dailyAt('09:05')
         ->timezone(config('app.timezone'));
$schedule->job(new RotateMessageLogsJob)->monthlyOn(1, '02:00');
$schedule->command('horizon:snapshot')->everyFiveMinutes();
```

### 10.2 `DailyTriggerDispatcherJob`
- يقرأ تاريخ اليوم.
- إن كان أول جمعة من الشهر و `setting('trigger_first_friday_enabled')` ⇒ يُنشئ `Campaign` نوع `first_friday` للشهر الحالي وينقلها لـ `queued`.
- إن كان يوم 15 و `setting('trigger_mid_month_enabled')` ⇒ يُنشئ `mid_month`.
- يدعم `force_run` (يدوي من الواجهة لتشغيل فوري).

### 10.3 Cron الوحيد المطلوب على السيرفر
```
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. الأدوار والصلاحيات (RBAC)

تعريف Permissions منفصلة (Spatie) ليتمكّن `super_admin` من بناء أدوار مخصصة:

```
students.view, students.create, students.update, students.delete, students.bulk-action
students.suspend, students.toggle-hidden, students.toggle-block, students.toggle-in-person

payments.view, payments.create, payments.update, payments.delete

fees.view, fees.update
overrides.create, overrides.delete
surcharges.create, surcharges.delete

templates.view, templates.create, templates.update, templates.delete

campaigns.view, campaigns.create_send_all, campaigns.create_by_month_condition
campaigns.trigger_first_friday_now, campaigns.trigger_mid_month_now
campaigns.run, campaigns.halt, campaigns.resume, campaigns.cancel
campaigns.see_logs, campaigns.export

settings.view, settings.update, settings.update_provider_keys

users.manage, roles.manage

reports.view, reports.export
```

أدوار جاهزة كـ seeded:
- `viewer`: `*.view` فقط.
- `accountant`: + `payments.*`, `fees.*`, `overrides.*`, `surcharges.*`, `reports.export`.
- `operator`: + `students.*`, `templates.view`, `campaigns.create_*`, `campaigns.run`, `campaigns.halt`, `campaigns.resume`.
- `admin`: كل شيء عدا `users.manage`, `roles.manage`, `settings.update_provider_keys`.
- `super_admin`: كل شيء.

---

## 12. الواجهات (UX/UI)

### 12.1 المبدأ
- Filament 3 يوفّر معظم لوحات التحكم.
- صفحات مخصّصة (Livewire) للأماكن التي تحتاج تفاعلًا حيًا (Dry Run، تقدم الحملة، محرر القالب).
- RTL كامل، تبديل لغة في الأعلى.
- ثيم داكن/فاتح.

### 12.2 الخريطة الكاملة للصفحات
```
/login
/dashboard
/students
/students/{id} [Overview | Fees | Payments | Surcharges | Messages | Suspensions | Audit]
/students/hidden          (عرض المخفيين)
/students/blocked
/students/in-person
/students/suspended
/payments
/payments/quick-entry      (إدخال سريع بحلقة)
/fees
/templates
/templates/{id}
/campaigns
/campaigns/new/send-all
/campaigns/new/by-month
/campaigns/{id}            (Live progress + Dry-Run table قابل للتصفية)
/triggers                  (إدارة First Friday / Mid Month — تشغيل يدوي/تعطيل)
/reports
/settings
/users
/roles
/audit
/horizon
```

### 12.3 شاشة "إنشاء حملة send_all"
- اختر القالب أو اكتب نصًا حرًا.
- معاينة على طالب-عيّنة.
- اختر `Start From ID` (اختياري).
- اختر "تجاهل الـ Halt العام للحملة هذه؟" (super_admin فقط).
- زر **Dry Run** → يعرض جدولًا حيًا بكل المستلمين + من سيُستثنى.
- زر **Send Now** (بعد تأكيد كلمة المرور).
- بعد الإطلاق: شريط تقدم Live + جدول بحالة كل رسالة.

### 12.4 شاشة "إنشاء حملة by-month"
- اختر السنة والشهر.
- اختر الشرط (`unpaid` = فارغة، `late` = X، `bank_recorded` = 0، `partial`).
- باقي الخطوات مطابقة لـ send_all.

### 12.5 شاشة الطالب
- زرّ سريع "أرسل تذكيرًا الآن" (يفتح modal لاختيار قالب وإرسال فردي).
- شريط حالة الشهر الحالي (`paid / partial 50% / unpaid / late`).
- جدول 12 شهر يعرض: المستحق، المدفوع، الرصيد، آخر دفعة.
- في كل خلية شهر: زر "+دفعة" مباشر.

---

## 13. خطة ترحيل البيانات (Migration من Excel/Sheets)

### 13.1 الـ Importer
- Console command: `php artisan students:import path/to/file.xlsx`.
- يقرأ الورقة `Sheet1`، يحدد الأعمدة بالاسم (نفس أسماء الشيت).
- لكل صف:
  1. ينشئ/يحدّث `Student` بـ `external_id = id`.
  2. الهاتف الأساسي والثانوي يُطبَّعان عبر libphonenumber. إن فشل التطبيع ⇒ يُحفظ raw مع flag `phone_invalid` لمراجعته يدويًا.
  3. أعمدة الأشهر تُترجم لـ `payments`:
     - رقم > 0 ⇒ `payments(method='manual', amount=الرقم, paid_at=01/شهر/سنة)`.
     - `0` ⇒ `payments(method='legacy_zero', amount=0)` (مع note: "from sheet — bank/late record").
     - `X` ⇒ **لا يُنشأ دفعة**، لكن يُسجَّل `student_flags(late_month_year_X)` لتصبح الحالة `late`. بديل: `student_monthly_marker(student, year, month, type='legacy_late')`.
- اختبار: يجب أن تكون نتائج "Dry Run" لحملة `mid_month` على الشيت الأصلي = نتائج النظام الجديد للشهر نفسه (مطابقة 1:1).

### 13.2 جدول علامات (Optional) `student_monthly_markers`
- لتمثيل `X` بدون payment.
- `(student_id, year, month, marker_type=late|bank|note)`.

### 13.3 خطة التحول (Cutover)
1. تثبيت النظام مع DB فارغ.
2. استيراد آخر نسخة من الشيت.
3. تشغيل Dry Run لمقارنة من سيتلقى الرسائل في 1 يونيو و15 يونيو 2026.
4. تطابق ≥ 99% ⇒ إيقاف Apps Script triggers، تفعيل النظام الجديد.
5. نسخة احتياطية من الشيت تُحفظ كمصدر مرجعي.

---

## 14. الأمن والامتثال

### 14.1 الأمان التقني
- HTTPS مع HSTS.
- Argon2id لكلمات المرور.
- 2FA إلزامي لـ `admin/super_admin`.
- Rate limit على `/login` (5 محاولات/دقيقة/IP).
- Encrypted columns لـ `BULKGATE_APP_TOKEN` (Laravel `Crypt::encryptString`).
- Session timeout 60 دقيقة من الخمول.
- CSP + X-Frame-Options + X-Content-Type-Options.
- CSRF, XSS escape افتراضي.
- SQL injection محمي عبر Eloquent.
- ملفات الرفع (Excel) تُفحص بحجم/امتداد/MIME قبل المعالجة.

### 14.2 GDPR
- صفحة "Privacy" تشرح ماذا نخزن وأين.
- زر "تصدير بيانات الطالب" (JSON) — حق الوصول.
- زر "حذف نهائي" (force delete) لـ super_admin — حق المسح.
- سياسة احتفاظ افتراضية بسجلات الرسائل: 12 شهر، ثم تُؤرشف وتُجرَّد من الـ body.
- Opt-out: إن أرسل ولي أمر "STOP"/"NEE" (لو فُعِّل inbound لاحقًا) ⇒ `is_blocked_messages=true`.

### 14.3 سياسة الإرسال
- لا تُرسل قبل 9:00 ولا بعد 20:00 (محلي NL).
- لا تُرسل أيام الجمعة/السبت إلا بإذن (قابل للضبط).
- Halt switch ظاهر دائمًا في القائمة العلوية للأدمن.

---

## 15. الاختبارات

### 15.1 Unit
- `ResolveStudentFeeAction`: تغطية كل سيناريوهات الـ override.
- `PhoneNormalizer`: 30 حالة هولندية (مأخوذة من بياناتك الحقيقية).
- `AsciiSanitizer`: كل المحارف الخاصة.
- `SegmentCounter`: 160/153 و GSM-7 vs UCS-2.

### 15.2 Feature
- `BuildRecipientListAction` لكل نوع حملة × كل تركيبة flags (hidden/blocked/in-person/suspended/excluded_from_send_all/no-phone/sms-disabled).
- `SendSmsJob` مع `BulkGateDriver` مزيّف (Fake) — يتأكد من Idempotency, Retries, Halt.
- Triggers: تشغيل `DailyTriggerDispatcherJob` في كل يوم من الشهر والتأكد أنه ينشئ الحملة المناسبة فقط في الأيام الصحيحة.

### 15.3 E2E (Laravel Dusk)
- تدفق المستخدم الكامل: تسجيل دخول → إنشاء حملة → Dry Run → Halt → Resume → إنهاء.

### 15.4 Contract Test مع BulkGate
- يُشغَّل ضد بيئة Sandbox مرة أسبوعيًا للتأكد من عدم تغيّر الـ API.

### 15.5 Migration Test
- استيراد الـ xlsx الفعلي → التأكد أن عدد الطلاب 347 وأن مجموع المدفوعات لكل شهر يطابق الشيت.

---

## 16. النشر والـ DevOps

### 16.1 البيئات
- `local`: Docker Compose (app + mysql + redis).
- `staging`: نسخة من الإنتاج لاختبار الترقيات.
- `production`: VPS أو Forge.

### 16.2 الخدمات في Docker Compose
```
services:
  app: php-fpm 8.3
  nginx
  mysql: 8.0
  redis: 7
  horizon: نفس الـ image مع artisan horizon
  scheduler: نفس الـ image مع cron يستدعي schedule:run
```

### 16.3 CI/CD (GitHub Actions)
- Lint: PHPStan level 8, Pint, Larastan.
- Tests: Pest + Dusk على ubuntu-latest.
- Build: composer install --no-dev، npm run build.
- Deploy: SSH/Forge webhook إلى staging تلقائي عند الدمج إلى `develop`، production عند tag.

### 16.4 Monitoring
- Sentry للأخطاء.
- Horizon Metrics للـ Queues.
- Logs مركزية (مثلاً Better Stack / Papertrail).
- Healthcheck endpoint `/up` يفحص DB + Redis + Provider.
- Uptime monitor خارجي.

### 16.5 Backups
- DB كل ليلة، احتفاظ 30 يوم.
- نسخ احتياطي للملفات (uploads/logs) أسبوعي.

---

## 17. خارطة الطريق (Phases) ومعايير القبول

### Phase 0 — تأسيس (أسبوع)
- إعداد Repo, CI, Docker, Filament, Sanctum, Spatie permission.
- Seed إعدادات افتراضية ومستخدم super_admin.
- **DoD**: تسجيل دخول ولوحة فارغة تعمل.

### Phase 1 — البيانات الأساسية (أسبوع)
- Students CRUD + Soft Delete + الحقول كلها (`is_hidden`, `is_blocked`, `is_in_person`, ...).
- Importer من xlsx الحقيقي بمطابقة 1:1.
- Suspensions module كامل.
- **DoD**: استيراد 347 طالب بدون فقد بيانات، Bulk actions تعمل، اختبارات وحدة خضراء.

### Phase 2 — الرسوم والدفعات (أسبوع)
- Fee settings + overrides + surcharges + payments.
- شاشة الطالب بجدول 12 شهر مع المبالغ والرصيد.
- تقرير "المتأخرون".
- **DoD**: تسجيل دفعة يحدّث الرصيد فورًا، Dry Run لحملة شهر يعطي نفس قائمة الشيت ± 1%.

### Phase 3 — القوالب وBulkGate (أسبوع)
- Templates CRUD + معاينة + segments counter.
- BulkGateDriver مع كل حالات الـ API.
- Send فردي من شاشة الطالب (للاختبار).
- **DoD**: إرسال رسالة اختبار حقيقية إلى رقم واحد ينجح مع تسجيل كامل.

### Phase 4 — الحملات (أسبوعان)
- Campaigns module كامل بأنواعه الأربعة.
- Dry Run + Halt/Resume + Idempotency + Quota.
- Horizon + Queues + Retry logic.
- **DoD**: حملة `by-month` على 347 طالب تكتمل بدون يدوي خلال ساعة، ولوحة Horizon تعرض الـ workers، والـ SMS_Log يُملأ تلقائيًا.

### Phase 5 — الجدولة التلقائية (أسبوع)
- `DailyTriggerDispatcherJob` + تعطيل/تفعيل من الإعدادات.
- اختبار محاكاة 30 يومًا (Carbon::setTestNow).
- **DoD**: في بيئة الـ staging، النظام في 1/يونيو وَ 15/يونيو يُنشئ الحملتين تلقائيًا.

### Phase 6 — التقارير واللوحة (أسبوع)
- Dashboard + Reports + CSV/Excel export.
- **DoD**: تقرير شهري قابل للطباعة، Dashboard يعرض الـ KPIs.

### Phase 7 — الأمن والصقل (أسبوع)
- 2FA، Audit، Permissions, GDPR features.
- اختبار اختراق أساسي (OWASP top 10 checklist).
- **DoD**: كل العمليات الحساسة تتطلب تأكيد، Audit log كامل.

### Phase 8 — Cutover (أسبوع)
- استيراد نهائي.
- مقارنة Dry Run.
- إيقاف Apps Script.
- **DoD**: شهر تشغيل فعلي بدون فقد رسائل + الفريق مرتاح للنظام الجديد.

---

## 18. ملاحق فنية

### 18.1 خريطة التحويل من الشيت إلى الـ DB

| Sheet column | DB destination |
|---|---|
| `id` | `students.external_id` |
| `Naam` | `students.name` |
| `Telefoon` | `students.phone_primary_raw` + `phone_primary_e164` |
| `sms` | `students.allow_sms` |
| `whatsapp` | `students.allow_whatsapp` |
| `send_all` | `students.included_in_send_all` |
| `Tweede telefoonnummer` | `students.phone_secondary_raw` + `phone_secondary_e164` |
| `January..December` (عدد > 0) | `payments(method='manual', amount=N, paid_at='YYYY-MM-01')` |
| `January..December` = `0` | `payments(method='legacy_zero', amount=0)` |
| `January..December` = `X` | `student_monthly_markers(type='legacy_late')` |
| `January..December` = فارغ | لا شيء (الحالة محسوبة = `unpaid`) |

### 18.2 خوارزمية بناء قائمة المستلمين (Pseudo-code)
```
function build(campaign):
  students = Student.query()
    .where('allow_sms', true)
    .whereNotNull('phone_primary_e164')
    .when(!campaign.includes_hidden, q => q.where('is_hidden', false))
    .where('is_blocked_messages', false)
    .where('is_in_person', false)
    .whereDoesntHave('activeSuspension', now())

  if campaign.type == 'send_all':
    students = students.where('included_in_send_all', true)
                      .where('excluded_from_send_all', false)

  if campaign.start_from_external_id:
    students = students.where('external_id', '>=', campaign.start_from_external_id)

  if campaign.type in [first_friday, mid_month, by_month_condition]:
    students = students.with(['payments' => fn($q)=>$q->where(year, month)])
                      .with(['markers' => ...])

  recipients = []
  foreach student in students:
    status = MonthStatusResolver::resolve(student, year, month)
    if campaign.type == 'first_friday' and status != 'unpaid': continue
    if campaign.type == 'mid_month' and status != 'late': continue
    if campaign.type == 'by_month_condition' and status != campaign.condition: continue
    body = TemplateRenderer::render(campaign.template, student, year, month)
    recipients.push({ student, phone, body, segments, idempotency_key })
  return recipients
```

### 18.3 قواعد الحالة الشهرية (Month Status)
```
fee_resolved = ResolveStudentFeeAction(student, y, m)        // ≥ 0
paid_sum     = sum(payments where year=y, month=m, method != 'legacy_zero')
has_legacy_zero = exists payment(method='legacy_zero', year=y, month=m)
is_marked_late  = exists marker(type='legacy_late', year=y, month=m)

if today < first_day_of(y, m):                       => 'not_due_yet'
if has_legacy_zero and paid_sum == 0:                => 'bank_recorded'
if paid_sum >= fee_resolved + surcharges:            => 'paid'
if paid_sum > 0:                                     => 'partial'
if is_marked_late or (today >= mid_month_of(y, m)):  => 'late'
else:                                                => 'unpaid'
```

### 18.4 خريطة استبدال دوال السكربت
| Apps Script | Laravel/PHP |
|---|---|
| `sendViaBulkGate_` | `BulkGateDriver::send()` |
| `normalizePhone_` | `PhoneNormalizer::normalize()` (libphonenumber) |
| `asciiSanitize_` | `AsciiSanitizer::sanitize()` |
| `smsSegmentsCount_` | `SegmentCounter::count()` |
| `runJobTick` | `Horizon` + `SendSmsJob` |
| `bgSetHalt_` / `bgShouldHalt_` | `SettingsService::halt()` (DB + cache) |
| `takeHourQuota_` | `QuotaService::take()` (Redis) |
| `processSendAllBatch_` | `BuildRecipientListAction` + Bulk Dispatch |
| `processFirstFridayBatch_` | كذلك مع condition=unpaid |
| `processMidMonthBatch_` | condition=late |
| `processByMonthCondBatch_` | condition=ديناميكي |
| `remindersDailyDispatcher` | `DailyTriggerDispatcherJob` |
| `enableRemindersTrigger`/`disableRemindersTrigger` | Setting toggle |
| `logSend_` | `MessageLog::create()` |
| `showSendAllSidebar` UI | صفحة `/campaigns/new/send-all` |
| `showMonthConditionSidebar` UI | `/campaigns/new/by-month` |
| `showJobStatus` | صفحة `/campaigns/{id}` |
| `forceResumeNow` | زر "Resume" على الحملة |

### 18.5 قائمة الإعدادات الكاملة (مع القيم الافتراضية)
```
app.timezone                          = Europe/Amsterdam
locale.default                        = nl
fees.default_monthly                  = 30.00
fees.currency                         = EUR
sms.provider                          = bulkgate
sms.sender_id                         = text
sms.sender_id_value                   = Al Boukhari
sms.default_country                   = NL
sms.force_ascii                       = true
sms.max_per_hour                      = 2500
sms.batch_size                        = 150
sms.sleep_between_batch_ms            = 5000
sms.checkpoint_every                  = 25
sms.resume_delay_minutes              = 65
sms.retry_short_minutes               = 3
sms.allowed_window_start              = 09:00
sms.allowed_window_end                = 20:00
sms.allow_friday                      = true
sms.allow_saturday                    = false
triggers.first_friday.enabled         = true
triggers.first_friday.template_code   = default_nl_first_friday
triggers.mid_month.enabled            = true
triggers.mid_month.template_code      = default_nl_mid_month
triggers.run_hour                     = 9
triggers.run_minute                   = 5
system.halt_sending                   = false
security.require_2fa_for_admin        = true
security.session_lifetime_minutes     = 60
gdpr.message_logs_retention_months    = 12
```

### 18.6 القوالب الافتراضية (Seeded — مطابقة لنصوص الـ script)
- `default_nl_first_friday`: `Beste ouder van {{Naam}}, Al Boukhari School groet u en herinnert u eraan om de betaling van {{month}} zo snel mogelijk te voldoen.`
- `default_nl_mid_month`: `Beste familie van student {{Naam}}, betaling voor {{month}} is vertraagd. Graag zo spoedig mogelijk voldoen.`
- `default_nl_send_all`: نسخة قابلة للتعديل.

### 18.7 معايير قبول رئيسية (نموذج)
- [ ] استيراد الـ xlsx ينتج 347 طالبًا بأرقام مُطبَّعة.
- [ ] إرسال أول جمعة لشهر تجريبي ينتج قائمة مطابقة للشيت ± 0 طالب.
- [ ] إخفاء طالب يستثنيه فورًا من كل الحملات.
- [ ] حظر الرسائل لطالب يظهر سبب الاستثناء "blocked" في Dry Run.
- [ ] تحديد طالب "يدرس مكانيًا" يستثنيه لكنه يبقى في التقارير المالية.
- [ ] عزل مؤقت ينتهي تلقائيًا في `ends_at`.
- [ ] رسم افتراضي 30 ⇒ override طالب 40 ⇒ override شهر 0 ⇒ المستحق = 0.
- [ ] إيقاف عام يوقف كل الإرسال خلال ≤ 5 ثوانٍ.
- [ ] Quota الساعي يطابق `max_per_hour` ولا يتجاوزه.
- [ ] حملة 347 رسالة تكتمل خلال أقل من ساعة على VPS متوسط.
- [ ] كل عملية حساسة موثقة في Audit Log.

---

## ختام

هذه الخطة تغطي كل الجوانب: من نسخ سلوك السكربت الحالي 1:1 (للحفاظ على استمرارية العمل)، إلى إضافة الميزات الجديدة التي طلبتها (إخفاء/حظر/عزل/مكاني/استثناء send_all/رسوم مرنة)، مع بناء أساس احترافي قابل للتوسع (WhatsApp، Email، Multi-Tenant، إلخ).

الترتيب المقترح للتنفيذ هو الـ Phases من 0 إلى 8، بمدة إجمالية تقديرية **8–10 أسابيع** لمطور واحد متفرّغ، أو **5–6 أسابيع** لاثنين.

عند الاتفاق على الخطة، نبدأ Phase 0 مباشرة: إنشاء المشروع، إعداد Docker، Filament، وأول Migration للـ Students.
