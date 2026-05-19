# خطة تطوير الواجهات — التحسين الشامل

## 🎯 الأهداف
1. واجهات احترافية بألوان ومسافات وخطوط حديثة
2. اتجاه LTR افتراضي مع دعم تبديل اللغة (ar / nl / en)
3. الترجمة عبر Laravel Localization (JSON)
4. البحث والفلاتر تعمل بالـ JavaScript (Alpine.js) فورياً بدون استدعاء الخادم
5. عمليات أعمق: تحديد متعدد، فرز قابل للنقر، إخفاء/إظهار أعمدة، تصدير الجدول الحالي

## 🎨 الهوية البصرية الجديدة

| العنصر | قبل | بعد |
|---|---|---|
| الخط | system-ui | **Inter** (Google Fonts) — وزن متعدد |
| الألوان الأساسية | أزرق فاتح/داكن مختلط | **slate-50** للخلفية، **indigo-600** للأساسي، **emerald-500** للنجاح، **rose-500** للخطر |
| الزوايا | 4-8px مختلط | **rounded-xl** (12px) موحّد + **rounded-lg** للأزرار |
| الظلال | بسيطة | shadow-sm طبقات • shadow-md للـ Modals |
| الأيقونات | Emojis فقط | Emojis + SVG Heroicons في الـ topbar/sidebar |
| الانتقالات | لا توجد | transition-all 150ms على كل عنصر |

## 🌐 اللغات والاتجاه

- **افتراضي**: LTR — إنجليزي
- **مدعومة**: العربية (RTL تلقائي)، الهولندية، الإنجليزية
- زر تبديل اللغة في الـ Topbar (يحفظ في الـ Session)
- كل النصوص في ملفات `lang/{locale}.json`

## ⚡ التحسينات الديناميكية (JS)

| الميزة | التطبيق |
|---|---|
| البحث الفوري | Alpine.js يفلتر الصفوف client-side بدون reload |
| فلاتر تراكمية | Alpine state مع computed properties |
| تحديد متعدد | Checkbox column + شريط أعمال يظهر عند التحديد |
| Bulk Actions | إخفاء/منع/مكاني/تعليق لمجموعة بضغطة |
| فرز الأعمدة | بالنقر على العنوان (Alpine sort) |
| إخفاء أعمدة | قائمة منسدلة لاختيار الأعمدة الظاهرة (saved in localStorage) |
| Toast notifications | نظام مركزي مع stack + auto-dismiss |
| Keyboard shortcuts | `/` للبحث • `Esc` للإغلاق • `?` لقائمة الاختصارات |

## 📋 الخطوات

1. **مكتبة الترجمة**: ملفات JSON + Helper `__()` في كل الـ Views
2. **Layout جديد**: Topbar محسّن + Sidebar اختياري + خط Inter
3. **CSS الجديد**: استبدال app.css بـ Tailwind utility-first كامل
4. **Alpine Store**: state عام للفلاتر/البحث/التحديد
5. **Toast system**: مكوّن Blade `<x-toast>` مع Alpine
6. **Students Grid v2**: تحديد متعدد + فرز + إخفاء أعمدة + بحث client-side
7. **Modals v2**: انتقالات سلسة + focus trap + keyboard
8. **Settings v2**: tabs مع smooth transitions
9. **Reports v2**: charts بسيطة بالـ SVG/Canvas

## 🧪 معايير القبول
- [ ] افتراضي LTR، تبديل سلس إلى RTL مع تغيير اللغة
- [ ] كل النصوص مترجمة (لا hard-coded)
- [ ] البحث يفلتر فوراً (≤ 50ms) بدون انتظار الخادم
- [ ] تحديد متعدد + Bulk hide يعمل
- [ ] الفرز بالنقر على العنوان يعمل
- [ ] إخفاء/إظهار أعمدة يُحفظ في localStorage
- [ ] التصميم يبدو احترافياً (لا inline styles عشوائية)
