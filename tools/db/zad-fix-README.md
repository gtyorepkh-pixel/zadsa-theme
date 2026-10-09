# أدوات تعديل المحتوى — الضمان · ساعات العمل · الخطأ الإملائي · فحص canonical

كل الأوامر من **Terminal → Open site shell** في Local (داخل `app/public`)، والملفات تُنسخ إلى `app/public` أولاً.

## الترتيب
1. **النسخة الاحتياطية** (تمت): `~/zad-backups/zad-db-20261009-035353.sql.gz`. خذ نسخة جديدة قبل التطبيق: `bash zad-backup.sh`.
2. ارفع الثيم **3.20.0** وفعّله (فيه سياسة الضمان المركزية وساعات 8–11 ونموذج الطلب ومعالج الحجز).
3. **تجربة بلا كتابة** (الافتراضي):
   ```
   wp eval-file zad-fix.php
   ```
   تعرض: عدد التغييرات حسب النوع والجدول والحقل، أول 60 تغييراً (قبل ← بعد)، وملف CSV بكل تغيير وبكل بند «REVIEW».
   لصفحة واحدة: `ZAD_ONLY=25797 wp eval-file zad-fix.php`
4. **راجع** الـCSV (`wp-content/uploads/zad-inventory/fix-dryrun-*.csv`)، ثم **طبّق**:
   ```
   ZAD_APPLY=1 wp eval-file zad-fix.php
   ```
   يكتب ملف تراجع `undo-*.json`. للتراجع: `ZAD_UNDO=/المسار/undo-….json wp eval-file zad-fix.php`.
5. صفحات الهَبّ (التي تذكر أكثر من خدمة): تظهر في الـCSV بنوع `HUB` ولا تُطبَّق إلا بـ `ZAD_HUBS=1` (مع `ZAD_APPLY=1`).
6. **امسح كاش LiteSpeed** (لوحة LiteSpeed Cache → Toolbox → Purge All) وكاش الصفحات/المتصفح.
7. **فحص canonical**: `wp eval-file zad-seo-check.php` (أو `bash zad-canonical-check.sh` بـcurl، يعمل أيضاً على السيرفر الحي).

## ما الذي يعدّله `zad-fix.php`
| النوع | القاعدة |
|---|---|
| WARRANTY | النمل الأبيض ← «ضمان مكتوب 15 عاماً» · بق الفراش ← «ضمان مكتوب 3 أشهر» · الصراصير ← «ضمان مكتوب 3 أو 6 أشهر حسب الاتفاق». الصفحة تُحدَّد من عنوانها + رابطها (صفحة تذكر خدمتين = هَبّ). «ضمان 100%» و«بنسبة 100%» تُستبدل/تُحذف. المتابعة المجانية بعد أسبوعين تبقى (في البطاقة الفنية تصير صفاً مستقلاً). |
| HOURS | «من 8 صباحاً … 10 مساءً» و«8 ص – 10 م» و`08:00 \| 22:00` ← 11 مساءً / 23:00 (في المحتوى والخيارات `zad_hours` و`zad_hours_spec`). |
| TYPO | «الجل الجديد» ← «الجيل الجديد». |
| REVIEW | ما لا تستطيع القواعد حسمه (يبقى كما هو) مع نصه — لتقرأه أنت. |
| MIXED | جملة في صفحة خدمة تذكر معها خدمة أخرى — لا تُمسّ. |

لا يمسّ أبداً: الروابط، الـslug، `post_name`، `post_modified`، `_zad_faqmig_backup`، المحتوى الخاص/المحذوف/المراجعات، ولا ضمان الخدمات الأخرى (قوارض، نمل عادي، رش عام، تنظيف…).
التعديل بأعمدة مباشرة (بلا `wp_update_post`) فلا تتغير الروابط ولا تُنشأ مراجعات؛ ويُحذف سجل Yoast indexable للصفحة المعدّلة ليُعاد بناؤه تلقائياً بالعنوان/الوصف الجديد.

## بعد النقل إلى zadksa.com (بدل zadksa.local)
```
wp search-replace 'http://zadksa.local'  'https://zadksa.com' --all-tables-with-prefix --skip-columns=guid --dry-run --report-changed-only
wp search-replace 'https://zadksa.local' 'https://zadksa.com' --all-tables-with-prefix --skip-columns=guid --dry-run --report-changed-only
wp search-replace 'zadksa.local'         'zadksa.com'         --all-tables-with-prefix --skip-columns=guid --dry-run --report-changed-only   # البريد info@zadksa.local وغيره
```
بعد مراجعة النتيجة أعد الأوامر **بدون** `--dry-run`. (WP-CLI يتعامل مع البيانات المتسلسلة بأمان.) ثم: `wp rewrite flush`، وامسح الكاش، وشغّل `zad-seo-check.php` على الموقع الحي.

---

# دمج الأقسام والأدلة ومدونة الحشرات في CPT واحد: `/blog/`

الملفات: `zad-blog-migrate.php` · `zad-redirect-check.sh` · `zad-core-blog.php` (يُنسخ إلى `wp-content/mu-plugins/`).

## قبل البدء
1. نسخة احتياطية: `bash zad-backup.sh`.
2. ثيم **3.20.2** مفعّل، وانسخ `zad-core-blog.php` إلى `app/public/wp-content/mu-plugins/` (يسجّل النوع الجديد `zad_blog` على `/blog/`).
3. إن كانت عندك **صفحة** رابطها `blog` أعد تسمية رابطها (تتعارض مع أرشيف /blog/) — الأداة تنبّهك.
4. حدّث الروابط الدائمة مرة: لوحة التحكم ← الإعدادات ← الروابط الدائمة ← حفظ.

## المرحلة 1 — النقل والتحويلات (تجربة أولاً)
```
wp eval-file zad-blog-migrate.php                         # تقرير فقط
ZAD_APPLY=1 wp eval-file zad-blog-migrate.php             # تنفيذ
```
- تنقل المقالات بتغيير نوعها فقط (الـslug والتاريخ والحالة والكاتب والتصنيفات والحقول كما هي). **المسودات والخاصة والمجدولة تُنقل كما هي بلا تحويل.**
- تحويل 301 لكل مقال **منشور** (من رابطه القديم إلى `/blog/…`) + تحويل الأرشيفات القديمة `/guide/` و`/sections/` و`/pests-library/` إلى `/blog/`.
  - إضافة Redirection موجودة ← تُنشأ التحويلات تلقائياً في مجموعة «Zad blog migration» (لا تكتب فوق تحويل موجود).
  - غير موجودة ← ملف `blog-redirects-*.csv` يُستورد من Redirection → Tools → Import.
- يُصلح عناصر القوائم (المقالات وروابط الأرشيف) لتشير إلى النوع الجديد.
- سلاغ مكرر بين نوعين: لا يُنقل ويُسرد (أو `ZAD_RESOLVE=suffix` يضيف «‑نوعه» للسلاغ).
- اختيارياً: `ZAD_RELINK=1` يعدّل الروابط الداخلية القديمة داخل المحتوى والحقول والقوائم؛ و`ZAD_YOAST_FROM=guide` ينسخ إعدادات Yoast لنوع إلى النوع الجديد.
- ملف تراجع: `ZAD_UNDO=…/blog-undo-*.json wp eval-file zad-blog-migrate.php`.
- بعدها: امسح LiteSpeed، احفظ الروابط الدائمة، ثم **اختبر**:
```
bash zad-redirect-check.sh wp-content/uploads/zad-inventory/blog-plan-….csv        # الرابط القديم = 301 إلى الجديد، والجديد = 200
```

## المرحلة 2 — حذف الأنواع القديمة (بعد نجاح الاختبار)
```
ZAD_STAGE=retire wp eval-file zad-blog-migrate.php                 # تقرير: ما الذي سيُحذف
ZAD_STAGE=retire ZAD_APPLY=1 wp eval-file zad-blog-migrate.php
```
ترفض الأداة إن بقي مقال بنوع قديم، أو نقص تحويل، أو فشل اختبار حيّ لعيّنة (20 مقالاً). عند النجاح:
تحذف المهملات ومسودات النظام، وتحذف `register_post_type` للأنواع الثلاثة من ملف الـmu-plugin/الثيم الذي يسجّلها (نسخة `.bak` تبقى بجانبه + فحص صياغة قبل الحفظ)، وتحدّث قواعد الروابط. أعد تشغيل `zad-redirect-check.sh` للتأكد أن الروابط القديمة ما زالت 301.
