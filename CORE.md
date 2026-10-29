# كور زاد 3.42.0

تنظيم الكور السابق محفوظ. تم حذف واجهات وكود الأدوات الثماني: تبنّي المحتوى، مدن الخدمات، ربط الأسئلة الجماعي، مراقب 404، تنظيف ذات صلة، فاحص الأصول، نقل الأعمال القديمة، وتنظيف قاعدة البيانات. أدوات الإدارة الأخرى تبقى وفق إعداداتها الحالية.

وظائف المدن وحقولها، روابط الأسئلة وتحريرها، الاختيار اليدوي للخدمات ذات الصلة، واقتراحات صفحة 404 باقية. اقتراحات 404 في inc/core/not-found.php، والتحويلات المحفوظة للأعمال في inc/seo/redirects.php وتعمل بدون تحميل أداة نقل أو إضافة. سجل 404 القديم لم يعد يجمع بيانات جديدة من الثيم.

صفحة العمل المنقول تعرض محتواه الأصلي من المحرر إذا كان يحمل _zad_wm_from وكانت حقول القصة المنظمة فارغة، حتى لا يختفي النص والصور. لا تعرض المحتوى نفسه مرتين عند وجود قصة منظمة.

لا يغيّر تثبيت هذا الإصدار المقالات أو قاعدة البيانات تلقائيًا. تنفيذ الصيانة والنقل على اللوكال عملية مستقلة مع نسخة احتياطية وسجل خارج الثيم. تحتاج نسخة موقع أخرى إلى نقل بياناتها وخريطة تحويلاتها بصورة مستقلة.

ملفات CSS وJavaScript والخطوط والصور محفوظة. إزالة الأدوات تقلل الكود الإداري؛ لا تمثل وحدها تخفيضًا لملفات الواجهة التي يحملها الزائر. لا يحتاج الثيم إلى ACF لحقوله الحالية.


إصدار 3.40.2 يجمع حقول السيو والمراجعة الفنية وفحص الاتساق في تبويب «السيو» ذي أيقونة SVG داخل تفاصيل الخدمة. الأسماء والقيم ومفاتيح الحفظ محفوظة، وتظل أدوات السيو للصفحات والمقالات والأسئلة في مواضعها الأصلية. محرك الثيم هو المالك المباشر للميتا والسكيما، مع إبقاء خيار الإيقاف الصريح.

إصدار 3.40.3 يوحّد اختيار روابط المدن في المحتوى وكتالوج المدن في السكيما عبر zad_link_city_ids: نفس الخدمة المحددة، مدينة معروفة مختلفة، صفحة منشورة غير محمية، مع استبعاد الأحياء والأدلة والعروض. يحتفظ بالترتيب اليدوي والحد الأقصى السابق، ولا يستبدل كتالوج الأسعار الموجود.

خريطتا HTML وXML تشتركان في أهلية الصفحات والتصنيفات: استبعاد المسودات والصفحات المحمية وnoindex والروابط الأساسية التي تشير إلى صفحة أخرى ومصادر التحويل المعروفة. يشمل الاستبعاد تحويلات الأعمال المحفوظة دون اشتراط إضافة خارجية. خريطة HTML تظل مجمّعة حسب الأقسام والموضوعات، وليست نسخة حرفية من جميع روابط XML. الأحياء المؤهلة تظهر ضمن الخدمات، ولا تُخفى عن XML بسبب فلتر قوائم الواجهة.

تُمسح ذاكرة الخرائط عند تغيير noindex أو canonical أو التصنيفات أو التحويلات وعند تحديث الإصدار. لا يغيّر هذا الإصدار المحتوى أو حقول السيو المحفوظة أو إعداد منع محركات البحث على اللوكال.

إصدار 3.40.4 يحذف أداة «تحويل الأسئلة القديمة» وعمليات التحويل والتراجع وخيار تفعيلها. توافق التحويلات المحفوظة وإيقاف نوع الأسئلة القديم ومراجعة المقتطفات التلقائية باقٍ في inc/core/faq-compatibility.php، مستقلًا عن أدوات الإدارة. لا ينقل هذا الإصدار أسئلة ولا يحذف بيانات أو سجلات التحويل المحفوظة.

إصدار 3.41.0 (تنظيف بقايا ميمو + من ركن نجد):
- تبويب «بيانات ونصوص إضافية» في الإعدادات (inc/zad-options-extra.php): حقول لإعدادات كان القالب يقرؤها بلا حقل (بيانات النشاط للسكيما، حسابات إضافية، أقسام الرئيسية، صفحة من نحن القديمة، المعرض، مقدمة مكتبة الآفات، مقدمات الأقسام). نفس المفاتيح، فلا نقل بيانات.
- حُذف حقل «بطاقة الحجز الافتراضية في هيرو الخدمات» (zad_hero_default) لأن لا شيء يقرؤه.
- أداة «تنظيف إعدادات القالب» (أدوات): تحذف 23 إعداداً محفوظاً لا يقرؤه القالب، مع نسخة للتراجع (zad_opts_removed).
- أداة «شورت كود ميمو القديمة» (أدوات): تستبدل [memo_qs_opt] و[memo_relatedpost_opt] في المحتوى بنفس الـ HTML، مع نسخة لكل صفحة (_zad_memo_sc_backup) وتراجع. بعدها يمكن حذف _inc/_memo_shortcode.php.
- البيكسلات تعمل على الموقع الحي فقط (zad_is_production) وتُتجاهل المعرّفات بصيغة خاطئة (zad_tracking_id)، مع تنبيه في صفحة الإعدادات.
- صور WebP المولّدة بجودة 72.
- VideoObject للأعمال وخريطة الميديا (zad-media-sitemap.xml) وpreload الهيرو موجودة من قبل.

إصدار 3.41.1 (من تعديلات 12 أكتوبر، بعد اختيار أحمد):
- العناوين: zad_title_head / zad_h1_title في inc/zad-helpers.php. الـ H1 لصفحات الخدمة والأحياء والصفحات العادية بدون رقم الجوال والجزء بعد «|». حقل _zad_h1 يغيّر الـ H1 لصفحة بعينها.
- نص الروابط في الكروت والقوائم (zad_card_title → zad_anchor_trim) يُقص عند اسم العلامة أو السعر أو الخصم أو «اتصل»، إلا إذا كان للصفحة تسمية مسار تنقل (_zad_breadcrumb_label).
- الخط: body 18px (كان 17px)، و.prose ‏1.15rem (كان 1.05rem).
- صفحة الكاتب: حقل «صفحة عن الكاتب» في البروفايل (user meta zad_author_about، مسجّل في REST). يظهر في الصفحة الأولى من أرشيف الكاتب بدل الهيدر المختصر. فهرسة صفحات الكتّاب وخريطتها بيديرهم inc/seo/policy.php زي ما هو.

إصدار 3.42.0 (إضافة السيو القديمة متوقفة نهائياً — حذف كل ما يخصها من الثيم):
- حُذف كل كود التكامل مع الإضافة: وضع «yoast» وفروعه، فلاتر wpseo_* (العناوين والوصف وrobots وOG والسايت ماب والسكيما)، استدعاءات الإضافة (YoastSEO(), WPSEO_Options, WPSEO_Taxonomy_Meta, WPSEO_Sitemaps_Cache)، حذف جداول indexables في أدوات التحويل، «تثبيت العنوان» في أداة تحويل الخدمات، نقل التصنيف الأساسي في أداة دمج الأدلة، وإعداداتها في أداة حذف الأنواع والتقارير.
- عنوان الكارت المختصر: من تسمية مسار التنقل في الثيم (_zad_breadcrumb_label) بدل حقل الإضافة.
- إضافة «محتوى الأرشيف» القديمة حُذفت من inc/zad-suite.php (دوال فارغة بنفس الأسماء للقوالب). «مصدر محتوى الصفحة (مسودة)» باقٍ.
- البيانات: الثيم يقرأ عناوين وأوصاف وnoindex وcanonical وصورة المشاركة المحفوظة من الإضافة القديمة كاحتياط فقط حتى تُنقل بأداة «نقل بيانات السيو القديمة» (أدوات) إلى حقول الثيم (_zad_seo_*). بعد النقل (zad_seo_legacy_done) لا يقرأ الثيم أي مفتاح قديم. بعد التأكد تُحذف الأداة وهذه القراءة في إصدار لاحق.
- مجلد tools/db (سكربتات تطوير) خرج من الثيم.

إصدار 3.43.0 (حذف أدوات خلص دورها):
- حُذفت: «إنشاء صفحة ركيزة الجلي» (zad-seed-pillar.php)، «بيانات تجريبية» (zad-demo.php) ومعها زر «تهيئة الرئيسية بقيم افتراضية» في «حالة الرئيسية»، «تقرير صفحات الأقسام (Hubs)» (zad-hubs-report.php)، «دمج الأدلة» و«دمج مدونة الحشرات» (zad-guidemerge.php)، «حذف النقل والتسليك نهائياً» (zad-purge-types.php). الثلاث الأخيرة اتشغلت وخلصت.
- باقية: «فصل الإجابة المباشرة» (لسه ما اتشغلتش)، و«نقل بيانات السيو القديمة» و«تنظيف إعدادات القالب» و«شورت كود ميمو القديمة» (تُحذف بعد تشغيلها).
- zad_hub_match_term: مطابقة التصنيف بالـ slug فقط (المطابقة بالاسم كانت شغالة في لوحة التحكم بس، عن طريق أداة الدمج).

إصدار 3.43.1:
- اتشال جدول «عناوين المقال» (فلتر the_content في inc/zad-frontend.php، وzad_toc_enabled_here، وحقول zad_toc_on / zad_toc_types / zad_toc_title / zad_toc_min، وCSS ‎.toc). الفهرس الوحيد دلوقتي «محتويات الصفحة» (inc/zad-pro-upgrades.php)، وهو بيدّي العناوين الـ id بتاعتها بنفسه.

إصدار 3.44.0 (كود ميمو بقى zad):
- فولدر _inc اتشال كله. مكانه في inc/core:
  - theme-setup.php: دعم الثيم ومقاس zad-card والقوائم (كان MemoSetupTheme). مقاسات memo-cards-xlg/lg/sm اتشالت لأن محدش بيستخدمها، فالصور الجديدة مش هيتعمل لها 3 نسخ زيادة. تنظيف الهيدر موجود أصلاً في security.php.
  - template-tags.php: zad_post_tags و zad_share_buttons و zad_search_icon و zad_comment_item (كانوا memo_tags_in و memo_sharing_buttons و memo_get_svg و better_commets). الترقيم بقى zad_pagination في كل القوالب.
  - nav-walker.php: Zad_Nav_Walker (كان memo_walker).
  - legacy-shortcodes.php: شورت كودز المحتوى القديمة بنفس أسماء الوسوم، لحد ما أداة «شورت كود ميمو القديمة» تتشغل.
- اتشالت دوال ميمو اللي محدش بيستخدمها: memo_get_img و memo_breadcrumbs و memo_get_phone و memo_get_whatsapp، وأيقونات $memo_icons.
- الثوابت: ZAD_THEME_DIR و ZAD_THEME_URI بدل MEMO_THEME_DIR و MEMO_THEME_URI. MEMO_VERSION_VERSION اتشال.
- اتشال inc/zad-rokn-engine.php (ملف مش متحمّل من أي حتة) والقراءة الميتة لـ _memo_options في compatibility.php.
- باقي باسم ميمو عن قصد: مفاتيح التخزين (_memo_theme_options و memopt_* و _memo_metabox_options)، ووسوم الشورت كود، وقوالب temp/memo-* (مرحلة لاحقة)، وأداة «شورت كود ميمو القديمة».

إصدار 3.45.0 (من ركن نجد + القوالب):
- التحويلات (inc/seo/redirect-engine.php، بعد rokn inc/redirects.php): خريطة واحدة في الخيار zad_redirects بتتنفذ في parse_request قبل استعلام ووردبريس. المفاتيح مسار مفكوك بحروف صغيرة ومن غير / في الآخر. 301 أو 410.
  - روابط خرائط إضافة السيو القديمة (sitemap_index.xml و*-sitemap.xml) بتتحوّل لـ /wp-sitemap.xml.
  - الروابط الزبالة بترجع 410: tel: / whatsapp: / mailto: جوه الرابط، esc_url(، ‎?replytocom، ملفات ‎.php مش موجودة (index.php وwp-*.php مستثنيين). بيتقفل من الإعدادات (zad_redirect_junk).
  - نشر صفحة رابطها عليه تحويل بيطلّع تنبيه.
  - الأدوات ← «التحويلات (زاد)» (inc/admin/redirects.php): عرض وبحث وإضافة وحذف، ونقل من جدول Redirection (المفعّل وغير الـ regex) مع تراجع (zad_redirects_prev). الإضافة نفسها ما بتتلمسش.
  - أداة «تحويل صفحات الخدمات» بتسجّل في خريطة الثيم لو Redirection مش مفعّلة. سياسة السايت ماب بتستبعد مصادر خريطة الثيم.
  - صفحات الكتّاب ما بتتحوّلش (عكس ركن نجد) عشان صفحة محمود. الخلاصات (feed) بيقفلها mu-plugin zad-cleanup زي ما هو.
- الصلاحيات (inc/core/permissions.php، بعد rokn inc/acf.php): الـ Canonical وnoindex و«نوع الصفحة (خدمة / قسم)» و«نوع الصفحة (رئيسية / حي)» للمدير بس. تغيير الـ slug أو الصفحة الأب لصفحة منشورة للمدير بس (الباقي بيتحفظ وبيظهر تنبيه).
- القيم الافتراضية (inc/core/settings-defaults.json، بعد rokn settings-defaults.json): بيانات الشركة الأساسية (الجوال والواتساب والاسم والسجل والضريبي والعنوان والإحداثيات والمواعيد والمدن والسوشيال). zad_opt بيرجعلها بس لو المفتاح مش موجود خالص في الإعدادات. القيم من نسخة 7 أكتوبر. الإيميل والوصف مش فيها (الإيميل في اللوكال مختلف، والوصف فيه خدمة اتلغت).
- الطلبات: رقم الجوال المكتوب بأرقام عربية (٠-٩) أو فارسية بقى مقبول في الفورم (PHP وJS). قبل كدا كان بيترفض.
- القوالب: temp/memo-about|contact|gallery|services بقت temp/zad-*. الصفحات المتسجّلة بالاسم القديم شغالة (inc/core/page-templates.php)، والاسم القديم بيفضل ظاهر في محرر الصفحة دي عشان الحفظ ما يشيلش القالب. الأدوات ← «قوالب الصفحات القديمة» بتغيّر الاسم المتسجّل مع تراجع.
- الصور: صور الهيرو مش محتاجة تتنقل من ركن نجد، الثيم بيستخدم ملفات AVIF/WebP الجنب الصورة في <picture> وبيعمل preload للهيرو (inc/zad-frontend.php).
- سطر «Template Name» في قوالب temp/zad-* بقى في سطر لوحده: ووردبريس ما كانش بيشوفه وهو بعد defined(...) في نفس السطر، فالقوالب دي ما كانتش بتظهر في اختيار قالب الصفحة (نفس إصلاح 12 أكتوبر اللي راح مع 3.40.4).

إصدار 3.45.1:
- أداة «نقل الأسئلة والأزرار»: الأسئلة بقت بتتقري من خصائص بلوك wpsp/faq-child نفسه (question/answer)، ومن الـ HTML لو السؤال في <span class="wpsp-question"> مش <hN> بس. قبل كدا أغلب الصفحات كان السؤال فيها span فالأداة ما كانتش بتلاقي أسئلة ومش بتنقل. وبقت بتدوّر في المقالات (sections) وأنواع الأسئلة كمان، مش بس الخدمات والصفحات والمقالات العادية.
- صفحة «اتصل بنا»: نص محرر الصفحة بيظهر فوق بيانات التواصل، والشورت كود بتاع إضافة مش متسطبة بيتشال.

إصدار 3.45.2:
- عرض أسئلة الصفحات والمقالات (_zad_faq تحت المحتوى + FAQPage) وشورت كود [zad_cta] اتنقلوا من inc/zad-faqmigrate.php (بيتحمّل في لوحة التحكم بس) لـ inc/zad-content-faq.php (بيتحمّل للزوار). قبل كدا الأسئلة اللي اتنقلت من بلوك الإضافة والأزرار اللي اتحوّلت لـ [zad_cta] ما كانتش بتظهر في الموقع (المشكلة موجودة من 3.40.4).

إصدار 3.46.0 (الأقسام وصفحة كل الخدمات):
- نوع «الخدمات» (zad_service) بقى هرمي تحت /services/ (كان /service/ ومفيهوش صفحات)، و/services/ نفسها صفحة كل الخدمات (archive-zad_service.php ← template-parts/services-index.php): كروت كل الأقسام في مستوى واحد + جدول القسم × المدينة + ItemList. عنوانها ومقدمتها من «بيانات ونصوص إضافية» (zad_services_title / zad_services_intro).
- تصميم القسم الجديد (template-parts/hub-section.php، بيانات inc/zad-sections.php) لأرشيف كل نوع (/cleaning/، /pest-control/) ولكل صفحة قسم (/cleaning/tanks/، /services/furniture-moving/): هيدر وأرقام، فهرس، محتوى القسم (محتوى صفحة القسم، أو «صفحات الأقسام (Hubs)» ← نظرة عامة للأرشيف)، كروت الخدمات بالصور، جدول الخدمة × المدينة، الأعمال، قبل/بعد وصور من صفحات القسم، أسئلة (من صفحات الأسئلة المربوطة أو تصنيفها)، مقالات، طلب. أي جزء من غير بيانات مش بيظهر. صفحات المدن والتصنيفات فضلت بالتصميم البسيط.
- H1 أرشيف التنظيف «خدمات التنظيف» بدل «خدمات cleaning»، ونفس الاسم في الـ title.
- /services-category/{cleaning|pest-control}/ (نسخة من أرشيف النوع) ← 301 للأرشيف، وخرجت من السايت ماب.
- مسار التنقل: «الخدمات» بعد الرئيسية في الأقسام وصفحات الخدمات، والأسامي مختصرة («تنظيف خزانات بالرياض» بدل العنوان الطويل بالسعر).
- لينك «كل الخدمات» آخر عمود الخدمات في الفوتر (footerinfo) وتحت كل قسم. القائمة الرئيسية زي ما هي.
- أداة «تحويل صفحات الخدمات»: النقل لـ zad_service بقى بصفحة أم، وفيها صفوف جاهزة لمقالات النقل والتخزين والتسليك بروابط إنجليزي قصيرة (/services/furniture-moving/riyadh/ …) وزرار يعمل الأقسام الثلاثة كمسودات. صفحات الأحياء بتتنقل في دفعة تانية تحت صفحة الرياض.


## 3.46.1: /services/ directory design
- The section cards on /services/ were rebuilt as a directory (template-parts/services-index.php, `.zdir`/`.zdc` in zad-hub.css):
  - a sticky bar with live search and a city picker, plus a no-results line
  - one card per section, with its own colour and icon (`zad_sec_look()`, filter `zad_sec_look`)
  - a short card name (`zad_sec_card_name()`), a numbered heading and a meta line (services · cities · starting price)
  - a short paragraph, which falls back to an auto line when the section has none
  - city chips
  - two columns of service links: short label (`zad_sec_link_label()`) + city tag, 6 shown, then «+N خدمة أخرى»
  - «يضم أيضاً» for sub-sections, «صفحة القسم» and a WhatsApp «اسأل عن السعر» button
- The city matrix row headers sit on a light background and fit on phones.

## 3.46.2: /services/ is the catalog of every service
- `zad_catalog()` in inc/zad-sections.php groups every service of the site into fixed families: التنظيف, الخزانات, الكنب والمفروشات, المكيفات, الحشرات, التسليك, جلي البلاط, النقل, التخزين (+ العزل and كشف التسربات when they exist).
  - The families come from `zad_catalog_families()` (filter `zad_catalog_families`), each with its words, landing page, colour, icon and short text.
  - Sources: the hierarchical service pages, and the service articles (posts) that are not converted yet, using the converter's test.
  - A converted article's new page replaces it by itself.
  - Cached with zad_city_ver + zad_catalog_ver (bumped when a post is saved or changes status) + the file's mtime.
- Card labels: `zad_catalog_label()` gives the short name without the city or filler words, and keeps «شمال/جنوب/شرق/غرب + city» for district pages.
- The page (template-parts/services-index.php) is built in this order:
  - hero
  - family tiles
  - search + city bar (8 cities at most)
  - one band per family: head, text, city chips and photo cards, 8 shown, then «عرض كل…»
  - «خدماتنا حسب المدينة»
  - CTA
- Converter: a new «جلي البلاط» section (/services/tile-polishing/) with the tile articles preset (5 city pages + 5 Riyadh districts).

## 3.46.3: City pages
- `/areas/{city}/` (service_area terms) now uses template-parts/hub-city.php (hub.php routes kind `city` there). The page is built in this order:
  - crumbs Home › الخدمات › (region) › city
  - the city's own text: the term description, else `zad_city_intro_default()` (written per city)
  - section tiles
  - one band per section, with photo cards of the city's services and «… حسب الحي» neighbourhood links
  - the cities under it
  - articles about the city
  - other cities
  - CTA
- Data comes from `zad_city_catalog( $term )`, which includes child cities. The catalog now also keeps neighbourhood pages (`hoods` per family and city, labels from `zad_catalog_hood_label()`).
- City pages are indexed when the city has catalog entries (`zad_hub_noindex`).
- /services/ city cards link to the city page (`zad_city_hub_url()`).
- Tools → «صفحات المدن (زاد)»: makes the missing city terms (Qassim cities go under القصيم) and saves the suggested texts into empty descriptions. It has one undo, and texts edited by hand are kept.

## 3.46.4: Section hubs
- A family's own page (/cleaning/tanks/, /cleaning/sofa/, /cleaning/air-conditioning/, and the /services/ sections once published) is always a section (`zad_page_kind` → `zad_catalog_landing_ids()`), even while its city pages are drafts. The «نوع الصفحة» box still wins.
- template-parts/hub-section.php is built in this order:
  - head (eyebrow, H1, lead, stats)
  - «اختر مدينتك» picker
  - TOC
  - sub-sections as photo tiles
  - «… حسب المدينة»: one card per city, titled by the city alone, holding the city's service cards (anchor = label + city), its neighbourhood links and «كل خدماتنا في {city}» to the city page
  - the section's own text
  - works, before/after, FAQ, articles
  - «أقسام أخرى»
  - CTA
- Data: `zad_catalog_by_city()`, `zad_catalog_key_of_node()`.
- The hub's ItemList schema and its noindex test now use the family's catalog (filter `zad_hub_data`).

## 3.46.5: Link network + tidy section pages
- «روابط ذات صلة» (`zad_netlinks_html()`, template-parts/service/links.php, replacing the hand-picked «نفس الخدمة في مدن أخرى»; hand-picked cities still come first).
  - **City service page:** all its neighbourhood pages as links (the text-only «الأحياء التي نخدمها» steps aside), 4 other cities + «كل المدن», 7 other sections in the city + «كل خدماتنا في {city}», 3 articles (rotated per page).
  - **Neighbourhood page:** its parent page + 6 neighbouring neighbourhoods + 3 sections in the city + the city page + 2 articles.
- **Articles:** «الخدمة المرتبطة» (the section page + the hand-picked service + 3 city pages) and «من نفس القسم» (3 articles, rotated), via `the_content` (`zad_article_links_html()`).
- A neighbourhood-type page that has pages under it is its city's main page in the catalog.
- **Section pages that still carry the old designed service content** (a `zad-…-pillar` wrapper) show only an intro (excerpt, else the first 1–2 paragraphs) under the head + the page's own questions (`_zad_faq`, with FAQPage schema) before the question pages. Nothing is deleted from the page.
- CSS: `.znet*` in zad.css (loaded on every page).

## 3.46.6: Admin: sections apart from services
- **Lists:** for zad_service, cleaning and pest_control, «كل الخدمات» lists the services only. A new «الأقسام» submenu (edit.php?post_type=…&zad_kind=section) lists the section pages. The list has the views الخدمات · الأقسام · الكل, and a search looks in all of them.
- **Taxonomy:** the old service_cat taxonomy is renamed «التصنيفات», so it is not confused with the section pages.
