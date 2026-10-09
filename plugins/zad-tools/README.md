# zad-tools — أدوات زاد (المرحلة 0)

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
