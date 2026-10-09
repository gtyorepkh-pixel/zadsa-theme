# zad-tools — أدوات زاد (المراحل 0–1)

إضافة مستقلة تحمل بيانات الأدوات وإعداداتها حتى لا تضيع لو تغيّر الثيم. المرحلة 0 تبني **البنية** فقط (لا توجد أداة بعد).

- `includes/settings.php` — صفحة «أدوات زاد»: تبويب لكل أداة، علامة «قيمة افتراضية — تحتاج اعتماد»، وبوابة `zt_tool_ready()` التي تُخفي الأداة عن الزوّار حتى تُعتمد قيمها. تبويب «قيم الثيم (للقراءة)» يعرض ما هو موجود في الثيم دون نسخه.
- `includes/reminders.php` — جدول `wp_zad_reminders`، قنوات الإرسال (قائمة اللوحة / إيميل / واجهة WhatsApp Business API غير مربوطة)، مهمة WP-Cron يومية، التجهيل بعد مدة الاحتفاظ، رابط الإيقاف، ومصدّر/ماسح بيانات الخصوصية (بالإيميل).
- `includes/rest.php` — `GET /zad/v1/token` و`POST /zad/v1/reminders` (رمز لا ينتهي في الكاش + honeypot + rate limit).
- `includes/tool-page.php` + `templates/tool-page.php` — قالب الصفحة «أداة زاد» والـ shortcode `[zad_tool]` وترتيب الأقسام الثابت (§0.4) وسكيما `WebApplication` + `FAQPage` عبر فلتر الثيم `zad_schema_page_nodes`.
- `assets/js/zt-core.js` (≈ 3KB مضغوط) — دوال نقية (أرقام عربية، تنسيق، رابط النتيجة، رسالة واتساب، أحداث GA4 بلا بيانات شخصية) لها توأم PHP في `includes/core.php`.

## تسجيل أداة (من مرحلة 2)
```php
add_action( 'zad_tools_register_settings', function () { zt_register_settings( 'ac-size', 'حجم المكيف', array( /* حقول، كل رقم مقترح بـ 'approval' => true */ ) ); } );
zt_register_tool( 'ac-size', array( 'title' => '…', 'desc' => '…', 'collects_data' => false, 'render' => …, 'how' => …, 'examples' => …, 'js' => …, 'css' => … ) );
```

## الاختبارات
`npm test` داخل المجلد: اختبارات JS (node:test)، ومطابقة JS/PHP على نفس المدخلات، وفحوص PHP بمحاكاة ووردبريس.

## المرحلة 1 — نظام الطلب ← التتبع ← الضمان ← التحقق ← التقييم
- `includes/orders.php` — `zad_order` و`zad_technician` (غير عامَّين)، الحالات، الإنشاء من الفورم/المعالج تلقائياً (فعل الثيم `zad_lead_created`) أو من «طلب سريع»، إصدار الضمان عند «تمت الخدمة»، شاشة «طلبات اليوم»، دور «فني زاد».
- `includes/tracking.php` — صفحتا القالبين «تتبع الطلب» و«التحقق من الضمان» (noindex، خارج الـSitemap والكاش)، مسار `/track/{token}/` (قاعدة إعادة كتابة واحدة)، شهادة الضمان القابلة للطباعة (QR محلي)، REST للتتبع والملاحظات وتفعيل تذكير الضمان.
- `assets/js/vendor/qrcode.js` — qrcode-generator 1.4.4 (MIT)، يُحمَّل في صفحة الشهادة فقط.

### ما تنشئه أنت في ووردبريس
1. صفحة بقالب «تتبع الطلب (zad-tools)» (مثلاً `/track/`) — تتبع الإضافة عنوانها تلقائياً.
2. صفحة بقالب «التحقق من الضمان (zad-tools)» (مثلاً `/warranty/verify/`).
3. من «أدوات زاد»: اعتمد قيم تبويبي «عام» و«الطلبات والضمان»، واكتب شروط الضمان، وضع Place ID.

## المرحلة 2 — حاسبة المكيف · متى أرجع بعد الرش · حاسبة الخزان (إصدار 0.3.0)

- كل أداة: ملف `includes/tools/<tool>.php` (إعدادات + نموذج + نتيجة من السيرفر + «إزاي بنحسب» + أمثلة) و`assets/js/<tool>.js` (الدالة الصافية + الربط).
- المحرك المشترك: `includes/ui.php` (`zt_result_html` ⇄ `ZT.resultHtml` يخرجان HTML متطابقاً بايت ببايت، `.ics`، تواريخ بأعداد صحيحة، `zt_ar_count`)، و`ZT.mount()` في `zt-core.js`.
- الاختبارات: `npm test` (JS + PHP + تطابق JS/PHP) و`npm run e2e` (Chromium على صفحات مولّدة بـ stub). Lighthouse: `LH_DIR=<مجلد فيه lighthouse> node tests/e2e/lh.mjs`.
- لإضافة أداة جديدة: `zt_register_tool( slug, [render, result, how, examples, related, js, collects_data] )` + `zt_register_settings()`.
