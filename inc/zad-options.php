<?php if ( ! defined( 'ABSPATH' ) ) { die; }
/** Extra theme-option sections (same option key as the core options). */

$zad_prefix = '_memo_theme_options';

CSF::createSection( $zad_prefix, array(
	'title'  => 'الهوية والألوان',
	'fields' => array(
		array( 'id' => 'zad_color_primary', 'type' => 'color', 'title' => 'اللون الأساسي', 'default' => '#0b2e3a' ),
		array( 'id' => 'zad_color_accent', 'type' => 'color', 'title' => 'لون الأزرار (التمييز)', 'default' => '#f2b134' ),
		array( 'type' => 'subheading', 'content' => 'ربط الأنواع الحالية بالتصميم (للمواقع القائمة). الروابط الحالية لا تتغير أبداً — نكتب فقط «رابط» كل نوع كما هو في موقعك.' ),
		array( 'id' => 'zad_service_slugs', 'type' => 'text', 'title' => 'أنواع تُعرض بتصميم «الخدمة»', 'default' => 'pest-control,cleaning,moving', 'desc' => 'روابط الأنواع (CPT) مفصولة بفاصلة. مثال: pest-control,cleaning,moving' ),
		array( 'id' => 'zad_faq_slugs', 'type' => 'text', 'title' => 'أنواع تُعرض بتصميم «السؤال»', 'default' => 'faq' ),
		array( 'id' => 'zad_article_slugs', 'type' => 'text', 'title' => 'أنواع تُعرض بتصميم «المقال»', 'default' => 'sections,guide' ),
		array( 'id' => 'zad_faq_autolink', 'type' => 'switcher', 'title' => 'ربط الأسئلة بالخدمات تلقائياً', 'default' => true, 'desc' => 'عند حفظ سؤال غير مربوط يُربط بأقرب خدمة بحسب الكلمات المتشابهة. وللأسئلة الموجودة استخدم: الأدوات ← ربط الأسئلة بالخدمات.' ),
		array( 'id' => 'zad_faq_default_service', 'type' => 'select', 'title' => 'خدمة افتراضية للأسئلة غير المطابقة', 'options' => 'posts', 'query_args' => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ), 'placeholder' => 'بدون' ),
		array( 'id' => 'zad_services_slug', 'type' => 'text', 'title' => 'رابط الخدمات', 'default' => 'services', 'desc' => 'مثال: yourdomain.com/<b>services</b>/اسم-الخدمة — أحرف إنجليزية وشرطات فقط. يتحدّث الرابط تلقائياً بعد الحفظ.' ),
		array( 'id' => 'zad_areas_slug', 'type' => 'text', 'title' => 'رابط المدن والأحياء', 'default' => 'areas' ),
		array( 'id' => 'zad_faq_slug', 'type' => 'text', 'title' => 'رابط الأسئلة', 'default' => 'faq' ),
		array( 'id' => 'memopt_lead_email', 'type' => 'text', 'title' => 'بريد استقبال الطلبات', 'desc' => 'إن تُرك فارغاً يُستخدم بريد التواصل العام.' ),
		array( 'id' => 'zad_hours', 'type' => 'text', 'title' => 'أوقات العمل', 'default' => 'نخدمكم 24 ساعة طوال أيام الأسبوع' ),
		array( 'id' => 'zad_provider', 'type' => 'text', 'title' => 'الاسم الرسمي للشركة', 'default' => '' ),
		array( 'id' => 'zad_since', 'type' => 'text', 'title' => 'سنة التأسيس', 'default' => '' ),
		array( 'id' => 'zad_reg', 'type' => 'text', 'title' => 'رقم السجل التجاري (اختياري)' ),
		array( 'id' => 'zad_rating_text', 'type' => 'text', 'title' => 'نص التقييم أعلى الموقع', 'default' => 'تقييمات حقيقية على Google' ),
		array( 'id' => 'zad_card_badges', 'type' => 'textarea', 'title' => 'شارات بطاقات الخدمات (سطر لكل شارة)', 'default' => "فحص مجاني\nضمان مكتوب" ),
		array( 'id' => 'zad_trustindex', 'type' => 'text', 'title' => 'معرّف ودجت Trustindex (اختياري)' ),
	),
) );

CSF::createSection( $zad_prefix, array(
	'title'  => 'الرئيسية: الواجهة الاحترافية',
	'fields' => array(
		array( 'id' => 'zad_hero_badge', 'type' => 'text', 'title' => 'شارة أعلى العنوان', 'default' => 'شركة مرخّصة • ضمان مكتوب • فنيون معتمدون' ),
		array( 'id' => 'zad_hero_title', 'type' => 'text', 'title' => 'العنوان الرئيسي', 'default' => 'خدمات منزلية احترافية بضمان حقيقي' ),
		array( 'id' => 'zad_hero_sub', 'type' => 'textarea', 'title' => 'النص التعريفي', 'default' => 'مكافحة حشرات، تنظيف، كشف تسربات والمزيد، بفنيين مدربين ومواد معتمدة وأسعار واضحة قبل التنفيذ.' ),
		array( 'id' => 'zad_hero_points', 'type' => 'textarea', 'title' => 'نقاط الثقة (سطر لكل نقطة)', 'default' => "معاينة وتسعير مجاني\nمواد آمنة على الأطفال والحيوانات الأليفة\nتنفيذ في نفس اليوم" ),
		array( 'id' => 'zad_hero_img', 'type' => 'media', 'title' => 'صورة الواجهة (اختياري)' ),
		array(
			'id' => 'zad_stats', 'type' => 'group', 'title' => 'الأرقام والإنجازات', 'button_title' => 'إضافة رقم',
			'fields' => array(
				array( 'id' => 'n', 'type' => 'text', 'title' => 'الرقم (مثال: +15,000)' ),
				array( 'id' => 'l', 'type' => 'text', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_process', 'type' => 'group', 'title' => 'كيف نعمل (خطوات)', 'button_title' => 'إضافة خطوة',
			'fields' => array(
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_testimonials', 'type' => 'group', 'title' => 'آراء العملاء', 'button_title' => 'إضافة رأي',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'الاسم' ),
				array( 'id' => 'city', 'type' => 'text', 'title' => 'المدينة' ),
				array( 'id' => 'text', 'type' => 'textarea', 'title' => 'الرأي' ),
				array( 'id' => 'rating', 'type' => 'number', 'title' => 'التقييم (1-5)', 'default' => 5 ),
			),
		),
		array(
			'id' => 'zad_faq', 'type' => 'group', 'title' => 'أسئلة شائعة (الرئيسية)', 'button_title' => 'إضافة سؤال',
			'fields' => array(
				array( 'id' => 'q', 'type' => 'text', 'title' => 'السؤال' ),
				array( 'id' => 'a', 'type' => 'textarea', 'title' => 'الإجابة' ),
			),
		),
		array(
			'id' => 'zad_clients', 'type' => 'group', 'title' => 'عملاؤنا (جهات وشركات)', 'button_title' => 'إضافة عميل',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'الاسم' ),
				array( 'id' => 'note', 'type' => 'text', 'title' => 'ملاحظة (المدينة / المشروع)' ),
			),
		),
		array(
			'id' => 'zad_sectors', 'type' => 'group', 'title' => 'قطاعات العملاء (شركات تثق بنا)', 'button_title' => 'إضافة قطاع',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'اسم القطاع' ),
				array( 'id' => 'names', 'type' => 'textarea', 'title' => 'أسماء العملاء (اسم في كل سطر) — أو اتركه فارغاً وضع الملاحظة', 'desc' => '' ),
				array( 'id' => 'note', 'type' => 'text', 'title' => 'ملاحظة بدل الأسماء (مثال: 4+ عملاء — أسماء غير معلنة)' ),
			),
		),
		array( 'id' => 'zad_cta_title', 'type' => 'text', 'title' => 'عنوان الدعوة الختامية', 'default' => 'جاهزون لخدمتك الآن' ),
		array( 'id' => 'zad_cta_sub', 'type' => 'text', 'title' => 'نص الدعوة الختامية', 'default' => 'تواصل معنا وسيصلك الفني مع عرض سعر واضح قبل البدء.' ),
	),
) );

CSF::createSection( $zad_prefix, array(
	'title'  => 'بيانات الشركة والسكيما',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'تُستخدم في بيانات Google المنظّمة (Schema) وفي الفوتر وصفحة الاتصال.' ),
		array( 'id' => 'zad_legal_name', 'type' => 'text', 'title' => 'الاسم النظامي للشركة' ),
		array( 'id' => 'zad_cr', 'type' => 'text', 'title' => 'رقم السجل التجاري' ),
		array( 'id' => 'zad_vat', 'type' => 'text', 'title' => 'الرقم الضريبي (VAT)' ),
		array( 'id' => 'zad_street', 'type' => 'text', 'title' => 'الشارع ورقم المبنى', 'default' => '' ),
		array( 'id' => 'zad_district', 'type' => 'text', 'title' => 'الحي', 'default' => '' ),
		array( 'id' => 'zad_city_name', 'type' => 'text', 'title' => 'المدينة', 'default' => 'الرياض' ),
		array( 'id' => 'zad_region', 'type' => 'text', 'title' => 'المنطقة', 'default' => 'منطقة الرياض' ),
		array( 'id' => 'zad_postal', 'type' => 'text', 'title' => 'الرمز البريدي' ),
		array( 'id' => 'zad_lat', 'type' => 'text', 'title' => 'خط العرض (Latitude)', 'desc' => 'مثال: 24.6584739 — انسخه من خرائط Google.' ),
		array( 'id' => 'zad_lng', 'type' => 'text', 'title' => 'خط الطول (Longitude)', 'desc' => 'مثال: 46.8421882' ),
		array( 'id' => 'zad_map_url', 'type' => 'text', 'title' => 'رابط موقعك في خرائط Google (hasMap)' ),
		array( 'id' => 'zad_hours_spec', 'type' => 'textarea', 'title' => 'ساعات العمل للسكيما', 'desc' => 'سطر لكل فترة: الأيام بالإنجليزية مفصولة بفاصلة | من | إلى', 'default' => "Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday | 09:00 | 22:30\nFriday | 16:00 | 22:30" ),
		array( 'id' => 'zad_price_range', 'type' => 'text', 'title' => 'نطاق الأسعار (priceRange)', 'desc' => 'مثال: 100–500 ر.س — يظهر في بيانات Google ويزيل تنبيه «priceRange غير مضمّن».', 'default' => '100–500 ر.س' ),
		array( 'id' => 'zad_linkedin', 'type' => 'text', 'title' => 'لينكدإن' ),
		array( 'id' => 'zad_pinterest', 'type' => 'text', 'title' => 'بينترست' ),
		array( 'id' => 'zad_tiktok', 'type' => 'text', 'title' => 'تيك توك' ),
		array( 'id' => 'zad_snapchat', 'type' => 'text', 'title' => 'سناب شات' ),
		array( 'id' => 'zad_og_default', 'type' => 'media', 'title' => 'صورة المشاركة الافتراضية (OG)', 'desc' => '1200×630 تقريباً.' ),
		array( 'id' => 'zad_site_desc', 'type' => 'textarea', 'title' => 'وصف الموقع (للسكيما والصفحة الرئيسية)' ),
	),
) );

CSF::createSection( $zad_prefix, array(
	'title'  => 'الرئيسية: خريطة المنزل والأمان',
	'fields' => array(
		array(
			'id' => 'zad_rooms', 'type' => 'group', 'title' => 'خريطة المنزل (حسب المكان)', 'button_title' => 'إضافة مكان',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'اسم المكان (مطبخ، حمّام، حديقة…)' ),
				array( 'id' => 'icon', 'type' => 'select', 'title' => 'الأيقونة', 'options' => array_combine( zad_icon_keys(), zad_icon_keys() ) ),
				array( 'id' => 'desc', 'type' => 'text', 'title' => 'وصف قصير (مثال: أكثر الأماكن عرضة للصراصير)' ),
				array( 'id' => 'services', 'type' => 'select', 'title' => 'الخدمات المرتبطة', 'chosen' => true, 'multiple' => true, 'sortable' => true, 'options' => 'posts', 'query_args' => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ) ),
			),
		),
		array( 'id' => 'zad_home_safety_title', 'type' => 'text', 'title' => 'عنوان قسم الأمان', 'default' => 'آمن لمن تحب' ),
		array(
			'id' => 'zad_home_safety', 'type' => 'group', 'title' => 'بطاقات الأمان', 'button_title' => 'إضافة بطاقة',
			'fields' => array(
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_global_stats', 'type' => 'group', 'title' => 'أرقام الشركة (صفحة من نحن)', 'button_title' => 'إضافة رقم',
			'fields' => array(
				array( 'id' => 'n', 'type' => 'text', 'title' => 'الرقم' ),
				array( 'id' => 'l', 'type' => 'text', 'title' => 'الوصف' ),
			),
		),
	),
) );

CSF::createSection( $zad_prefix, array(
	'title'  => 'أداة تعرّف على الآفة',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'معالج أسئلة بسيط: المكان ← نوع المشكلة ← النتيجة والخدمة المقترحة. أنشئ صفحة بقالب «تعرّف على الآفة».' ),
		array( 'id' => 'zad_identify_where', 'type' => 'textarea', 'title' => 'أين رأيتها؟ (خيار في كل سطر)', 'default' => "المطبخ\nالحمّام والصرف\nغرفة النوم والأرائك\nالحديقة والمحيط\nالأخشاب والجدران" ),
		array( 'id' => 'zad_identify_kind', 'type' => 'textarea', 'title' => 'كيف تبدو؟ (خيار في كل سطر)', 'default' => "حشرة زاحفة داكنة\nحشرة صغيرة تعض\nحشرة طائرة\nقوارض أو آثار قرض\nنمل بأجنحة أو ثقوب بالخشب" ),
		array(
			'id' => 'zad_identify_rules', 'type' => 'group', 'title' => 'النتائج', 'button_title' => 'إضافة نتيجة',
			'fields' => array(
				array( 'id' => 'where', 'type' => 'text', 'title' => 'المكان (نفس النص تماماً من القائمة أعلاه)' ),
				array( 'id' => 'kind', 'type' => 'text', 'title' => 'الشكل (نفس النص تماماً)' ),
				array( 'id' => 'title', 'type' => 'text', 'title' => 'اسم الآفة المرجّحة' ),
				array( 'id' => 'text', 'type' => 'textarea', 'title' => 'شرح قصير ونصيحة' ),
				array( 'id' => 'service', 'type' => 'select', 'title' => 'الخدمة المقترحة', 'options' => 'posts', 'query_args' => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ) ),
			),
		),
	),
) );

CSF::createSection( $zad_prefix, array(
	'title'  => 'الرئيسية: أقسام إضافية',
	'fields' => array(
		array(
			'id' => 'zad_highlights', 'type' => 'group', 'title' => 'شريط المزايا أسفل الهيرو', 'button_title' => 'إضافة ميزة',
			'fields' => array(
				array( 'id' => 'icon', 'type' => 'select', 'title' => 'الأيقونة', 'options' => array_combine( zad_icon_keys(), zad_icon_keys() ) ),
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'text', 'title' => 'وصف قصير' ),
			),
		),
		array( 'id' => 'zad_about_eyebrow', 'type' => 'text', 'title' => 'قسم «عنّا»: الوسم', 'default' => 'من نحن' ),
		array( 'id' => 'zad_about_title', 'type' => 'text', 'title' => 'قسم «عنّا»: العنوان' ),
		array( 'id' => 'zad_about_text', 'type' => 'textarea', 'title' => 'قسم «عنّا»: النص' ),
		array( 'id' => 'zad_about_points', 'type' => 'textarea', 'title' => 'قسم «عنّا»: نقاط (سطر لكل نقطة)' ),
		array( 'id' => 'zad_about_img', 'type' => 'media', 'title' => 'قسم «عنّا»: صورة' ),
		array( 'id' => 'zad_price_title', 'type' => 'text', 'title' => 'قسم الأسعار: العنوان', 'default' => 'أسعار واضحة تبدأ من' ),
		array(
			'id' => 'zad_home_ba', 'type' => 'group', 'title' => 'نتائج قبل / بعد', 'button_title' => 'إضافة مقارنة',
			'fields' => array(
				array( 'id' => 'before', 'type' => 'media', 'title' => 'قبل', 'library' => 'image' ),
				array( 'id' => 'after', 'type' => 'media', 'title' => 'بعد', 'library' => 'image' ),
				array( 'id' => 'title', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'desc', 'type' => 'text', 'title' => 'وصف' ),
			),
		),
		array( 'id' => 'zad_home_video', 'type' => 'text', 'title' => 'فيديو الرئيسية (YouTube أو mp4)' ),
		array( 'id' => 'zad_home_video_poster', 'type' => 'media', 'title' => 'صورة غلاف الفيديو', 'library' => 'image' ),
		array( 'id' => 'zad_guarantee_title', 'type' => 'text', 'title' => 'شريط الضمان: العنوان', 'default' => 'ضمان مكتوب على كل خدمة' ),
		array( 'id' => 'zad_guarantee_text', 'type' => 'text', 'title' => 'شريط الضمان: النص', 'default' => 'إن عادت المشكلة خلال مدة الضمان نعالجها مجاناً — نحدد المدة كتابةً قبل البدء.' ),
		array( 'id' => 'zad_rating_score', 'type' => 'text', 'title' => 'ملخص التقييم: الرقم', 'default' => '4.9' ),
		array( 'id' => 'zad_rating_count', 'type' => 'text', 'title' => 'ملخص التقييم: عدد التقييمات', 'default' => '' ),
	),
) );
