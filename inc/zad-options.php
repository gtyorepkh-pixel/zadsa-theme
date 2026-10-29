<?php if ( ! defined( 'ABSPATH' ) ) { die; }
/**
 * Zad Saudi Arabia — Smart Dropdown Command Center v3.35.0
 * Every theme feature is preserved, organized, and upgraded with interactive <select> dropdowns,
 * conditional sub-panels, brand presets, seasonal campaigns, and visual builders.
 */

$zad_prefix = '_memo_theme_options';
$zd = zsc_defaults();
$zb = $zd['business'];
$zhours = array();
foreach ( $zb['hours'] as $h ) { $zhours[] = implode( ',', $h[0] ) . ' | ' . $h[1] . ' | ' . $h[2]; }
$zarea = array();
foreach ( $zb['area_served'] as $x ) { $zarea[] = $x[0] . ' | ' . $x[1]; }
$zauth = array();
foreach ( $zd['authors'] as $slug => $au ) { $zauth[] = $slug . ' | ' . $au['name'] . ' | ' . $au['job_title'] . ' | ' . $au['credential'] . ' | ' . implode( '؛ ', $au['knows_about'] ) . ' | ' . $au['description']; }

$zad_icons_dropdown = array_combine( zad_icon_keys(), zad_icon_keys() );

/* ==========================================================================
   1. الهوية البصرية والشركة والعروض الموسمية (قوائم منسدلة ذكية)
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'الهوية والشركة والعروض',
	'icon'   => 'fa fa-paint-brush',
	'fields' => array(
		array(
			'type'    => 'subheading',
			'content' => 'اختر قسم الإعدادات الذي تريد تعديله الآن من القائمة المنسدلة أو تصفح الأقسام بالأسفل',
		),
		// 1. نظام الهوية البصرية الجاهزة (قائمة منسدلة)
		array(
			'id'      => 'zad_brand_preset',
			'type'    => 'select',
			'title'   => 'ثيم الهوية البصرية لزاد السعودية (قائمة منسدلة)',
			'options' => array(
				'zad_official' => 'هوية زاد الرسمية (تركواز ملكي #0c687e + ذهبي #f49400)',
				'zad_emerald'  => 'هوية النظافة والبيئة (أخضر زمردي #0f766e + كهرماني #f59e0b)',
				'zad_royal'    => 'الهوية الاحترافية الداكنة (كحلي هندسي #1e3a8a + برتقالي #ea580c)',
				'custom'       => 'تخصيص حر للألوان (اختر ألوانك يدوياً بالأسفل)',
			),
			'default' => 'zad_official',
			'desc'    => 'اختر هوية جاهزة متناسقة بالكامل، أو اختر «تخصيص حر» لتحديد كل لون بنفسك.',
		),
		array( 'id' => 'zad_color_primary', 'type' => 'color', 'title' => 'اللون الأساسي', 'default' => '#0c687e', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_color_accent', 'type' => 'color', 'title' => 'لون الأزرار (التمييز)', 'default' => '#f49400', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_color_small', 'type' => 'color', 'title' => 'لون الخط الصغير والملاحظات', 'default' => '#111111', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_hdr_bg', 'type' => 'color', 'title' => 'خلفية الهيدر والقائمة', 'default' => '#0c687e', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_hdr_ink', 'type' => 'color', 'title' => 'نص القائمة', 'default' => '#ffffff', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_hdr_cta', 'type' => 'color', 'title' => 'زر الهيدر الرئيسي', 'default' => '#f49400', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_topbar_bg', 'type' => 'color', 'title' => 'خلفية الشريط العلوي', 'default' => '#074250', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_ftr_bg', 'type' => 'color', 'title' => 'خلفية الفوتر', 'default' => '#0a3947', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_ftr_head', 'type' => 'color', 'title' => 'عناوين وأيقونات الفوتر', 'default' => '#3fbfae', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),
		array( 'id' => 'zad_ftr_ink', 'type' => 'color', 'title' => 'نص الفوتر', 'default' => '#b4c2c6', 'dependency' => array( 'zad_brand_preset', '==', 'custom' ) ),

		// 2. ميزة جديدة: حملات وعروض زاد الموسمية (قائمة منسدلة)
		array( 'type' => 'subheading', 'content' => 'ميزة جديدة: شريط العروض والحملات الموسمية (Seasonal Promo Engine)' ),
		array(
			'id'      => 'zad_promo_mode',
			'type'    => 'select',
			'title'   => 'حالة العرض الموسمي (قائمة منسدلة)',
			'options' => array(
				'off'      => 'مغلق (الوضع العادي بدون شريط عروض)',
				'summer'   => 'حملة الصيف: عروض غسيل المكيفات والخزانات (توفير للمجموعات)',
				'pest'     => 'حملة مكافحة الحشرات: رش موجّه بضمان مكتوب وزيارة مجانية',
				'Founding' => 'عروض يوم التأسيس / اليوم الوطني السعودي',
				'ramadan'  => 'حملة تجهيز المنازل والمجالس قبل رمضان والعيد',
				'custom'   => 'عرض مخصص (اكتب نص العرض وشارة الخصم بنفسك)',
			),
			'default' => 'off',
			'desc'    => 'يُظهر شارة عرض جذابة في أعلى الموقع وفي تذكرة الحجز لرفع نسبة الاتصال والواتساب.',
		),
		array( 'id' => 'zad_promo_text', 'type' => 'text', 'title' => 'نص العرض المخصص', 'default' => 'خصم خاص 20% عند حجز خدمتين في زيارة واحدة', 'dependency' => array( 'zad_promo_mode', '==', 'custom' ) ),
		array( 'id' => 'zad_promo_badge', 'type' => 'text', 'title' => 'شارة العرض القصيرة', 'default' => 'عرض لفترة محدودة', 'dependency' => array( 'zad_promo_mode', '==', 'custom' ) ),

		// 3. البيانات الرسمية للشركة
		array( 'type' => 'subheading', 'content' => 'البيانات القانونية والرسمية لشركة زاد السعودية' ),
		array( 'id' => 'zad_org_name', 'type' => 'text', 'title' => 'اسم المنشأة الكامل', 'default' => $zb['name'] ),
		array( 'id' => 'zad_legal_name', 'type' => 'text', 'title' => 'الاسم النظامي للشركة', 'default' => $zb['legal_name'] ),
		array( 'id' => 'zad_alt_name', 'type' => 'text', 'title' => 'الاسم المختصر للعلامة', 'default' => $zb['alternate'] ),
		array( 'id' => 'zad_provider', 'type' => 'text', 'title' => 'اسم المزوّد في الشارات', 'default' => 'زاد السعودية' ),
		array( 'id' => 'zad_cr', 'type' => 'text', 'title' => 'رقم السجل التجاري', 'default' => $zb['cr'] ),
		array( 'id' => 'zad_ unified_num', 'type' => 'text', 'title' => 'الرقم الوطني الموحّد (700)', 'default' => '', 'desc' => 'يظهر في الفوتر وفي بيانات identifier بالسكيما مع رابط التحقق من وزارة التجارة.' ),
		array( 'id' => 'zad_vat', 'type' => 'text', 'title' => 'الرقم الضريبي (VAT)', 'default' => $zb['vat'] ),
		array( 'id' => 'zad_since', 'type' => 'text', 'title' => 'سنة التأسيس', 'default' => '2014' ),
		array( 'id' => 'zad_hours', 'type' => 'text', 'title' => 'أوقات العمل المعروضة', 'default' => 'من 8 صباحاً إلى 11 مساءً طوال أيام الأسبوع' ),
		array( 'id' => 'memopt_lead_email', 'type' => 'text', 'title' => 'بريد استقبال طلبات الموقع' ),

		// 4. نظام الثقة والتقييمات والاستماع الصوتي (قائمة منسدلة)
		array( 'type' => 'subheading', 'content' => 'نظام الثقة، التقييمات، والقارئ الصوتي الذكي' ),
		array( 'id' => 'zad_trustindex', 'type' => 'text', 'title' => 'معرّف ودجت Trustindex (اختياري)' ),
		array( 'id' => 'zad_rating_text', 'type' => 'text', 'title' => 'نص التقييم أعلى الموقع', 'default' => 'تقييمات حقيقية على Google' ),
		array( 'id' => 'zad_g_rating', 'type' => 'text', 'title' => 'تقييم خرائط جوجل (مثال: 4.9)' ),
		array( 'id' => 'zad_g_count', 'type' => 'text', 'title' => 'عدد المراجعات الفعلية' ),
		array( 'id' => 'zad_g_url', 'type' => 'text', 'title' => 'رابط مراجعات الشركة على خرائط جوجل' ),
	),
) );

/* ==========================================================================
   2. المدن والتغطية الجغرافية وروابط Wikidata (قوائم منسدلة)
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'المدن والتغطية (Wikidata)',
	'icon'   => 'fa fa-map-marker',
	'fields' => array(
		array(
			'type'    => 'subheading',
			'content' => 'جدول المدن (التغطية المؤكدة) — اختَر صفحة كل مدينة من القائمة المنسدلة، والفعّالة فقط تدخل areaServed في السكيما وتظهر في الفوتر ونافذة الحجز.',
		),
		array(
			'id'           => 'zad_cities_table',
			'type'         => 'group',
			'title'        => 'المدن المخدومة',
			'button_title' => 'إضافة مدينة جديدة +',
			'default'      => array(
				array( 'name' => 'الرياض', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q3692', 'active' => true ),
				array( 'name' => 'جدة', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q374365', 'active' => true ),
				array( 'name' => 'الدمام', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q160320', 'active' => true ),
				array( 'name' => 'القطيف', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q1014358', 'active' => true ),
				array( 'name' => 'القصيم', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q230820', 'active' => true ),
				array( 'name' => 'بريدة', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q259253', 'active' => false ),
				array( 'name' => 'عنيزة', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q27138', 'active' => false ),
				array( 'name' => 'الخرج', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q16123684', 'active' => false ),
				array( 'name' => 'نجران', 'page' => '', 'wikidata' => 'https://www.wikidata.org/wiki/Q27174', 'active' => true ),
			),
			'fields'       => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'المدينة' ),
				array(
					'id'          => 'page',
					'type'        => 'select',
					
					'title'       => 'صفحة المدينة (قائمة منسدلة بالبحث)',
					'placeholder' => '— ابحث واختر صفحة المدينة —',
					'options'     => 'posts',
					'query_args'  => array(
						'post_type'      => array_merge( array( 'page' ), function_exists( 'zad_service_types' ) ? zad_service_types() : array( 'cleaning', 'pest_control', 'zad_service' ) ),
						'posts_per_page' => -1,
						'post_status'    => 'publish',
					),
				),
				array(
					'id'         => 'wikidata',
					'type'       => 'text',
					'title'      => 'رابط ويكي داتا (Wikidata)',
					'attributes' => array( 'dir' => 'ltr', 'placeholder' => 'https://www.wikidata.org/wiki/Q3692' ),
				),
				array( 'id' => 'active', 'type' => 'switcher', 'title' => 'فعّالة', 'default' => true ),
			),
		),
		array( 'type' => 'subheading', 'content' => 'إعدادات قسم التغطية والأحياء في الصفحات' ),
		array(
			'id'      => 'zad_cov_display_mode',
			'type'    => 'select',
			'title'   => 'طريقة عرض قسم التغطية والأحياء (قائمة منسدلة)',
			'options' => array(
				'smart_links' => 'ذكي: تحويل أسماء الأحياء التي لها صفحات منشورة إلى روابط تلقائياً (أفضل للـ Local SEO)',
				'plain_chips' => 'شرائح نصية فقط بدون روابط',
				'home_only'   => 'إظهار التغطية العامة في الصفحة الرئيسية فقط',
			),
			'default' => 'smart_links',
		),
		array( 'id' => 'zad_cov_services', 'type' => 'switcher', 'title' => 'إظهار قسم التغطية في جميع صفحات الخدمات', 'default' => true ),
		array( 'id' => 'zad_cov_eyebrow', 'type' => 'text', 'title' => 'الوسم الصغير لقسم التغطية', 'default' => 'تغطيتنا' ),
		array( 'id' => 'zad_cov_title', 'type' => 'text', 'title' => 'عنوان قسم التغطية العام', 'default' => 'نصل إليك في أي حي بالرياض' ),
		array( 'id' => 'zad_cov_sub', 'type' => 'text', 'title' => 'السطر تحت العنوان', 'default' => 'فرق ميدانية موزعة في الشمال والشرق والجنوب والغرب.' ),
		array( 'id' => 'zad_cov_box', 'type' => 'text', 'title' => 'عنوان بطاقة التغطية', 'default' => 'تغطية شاملة وسريعة' ),
		array( 'id' => 'zad_cov_text', 'type' => 'textarea', 'title' => 'وصف التغطية العام' ),
		array( 'id' => 'zad_cov_chips', 'type' => 'textarea', 'title' => 'الأحياء الافتراضية (سطر لكل حي)' ),
		array( 'id' => 'zad_cov_note', 'type' => 'text', 'title' => 'الملاحظة الأخيرة تحت الأحياء', 'default' => 'لم تجد حيّك؟ نخدم جميع الأحياء والمناطق المجاورة.' ),
		array( 'id' => 'zad_cov_stats', 'type' => 'textarea', 'title' => 'بطاقات أرقام التغطية (الرقم | الوصف)', 'default' => "60 دقيقة | متوسط الوصول\n7 أيام | طوال الأسبوع\n+12 سنة | خبرة ميدانية" ),
		array( 'id' => 'zad_area_keywords', 'type' => 'text', 'title' => 'كلمات تدل على صفحات الأحياء', 'default' => 'حي,hay-,district,neighborhood' ),
		array( 'id' => 'zad_area_children', 'type' => 'switcher', 'title' => 'اعتبار الصفحات الفرعية صفحات أحياء', 'default' => true ),
	),
) );

/* ==========================================================================
   3. الضمان المكتوب وتجربة صفحة الخدمة (قوائم منسدلة ذكية)
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'الضمان وصفحات الخدمات',
	'icon'   => 'fa fa-shield',
	'fields' => array(
		array(
			'type'    => 'subheading',
			'content' => 'نظام الضمان المكتوب الموحّد — اختر نوع الخدمة ومدة الضمان من القوائم المنسدلة بدلاً من كتابة الأكواد يدوياً',
		),
		array(
			'id'           => 'zad_warranty_builder',
			'type'         => 'group',
			'title'        => 'قواعد الضمان حسب نوع الخدمة (قوائم منسدلة)',
			'button_title' => 'إضافة قاعدة ضمان جديدة +',
			'default'      => array(
				array( 'service_key' => 'termite', 'months' => '180', 'text' => 'ضمان مكتوب 15 عاماً ضد النمل الأبيض' ),
				array( 'service_key' => 'bedbug',  'months' => '3',   'text' => 'ضمان مكتوب 3 أشهر شامل زيارة مجانية' ),
				array( 'service_key' => 'roach',   'months' => '3',   'text' => 'ضمان مكتوب من 3 إلى 6 أشهر حسب الاتفاق' ),
				array( 'service_key' => 'ac',      'months' => '1',   'text' => 'ضمان مراجعة مجانية ضد التسرب أو ضعف التصريف بعد الغسيل' ),
				array( 'service_key' => 'tank',    'months' => '12',  'text' => 'ضمان مكتوب على نظافة وتعقيم وعزل الخزانات' ),
				array( 'service_key' => 'sofa',    'months' => '1',   'text' => 'ضمان سلامة الأقمشة وثبات الألوان وإزالة الروائح' ),
				array( 'service_key' => 'clean',   'months' => '1',   'text' => 'ضمان إعادة التنظيف الفوري لأي ملاحظة قبل الاستلام' ),
			),
			'fields'       => array(
				array(
					'id'      => 'service_key',
					'type'    => 'select',
					'title'   => 'فئة الخدمة (قائمة منسدلة)',
					'options' => array(
						'termite' => 'مكافحة النمل الأبيض (الأرضة)',
						'bedbug'  => 'مكافحة بق الفراش',
						'roach'   => 'رش المبيدات ومكافحة الصراصير والحشرات',
						'ac'      => 'تنظيف وصيانة المكيفات',
						'tank'    => 'تنظيف وعزل الخزانات',
						'sofa'    => 'تنظيف الكنب والمجالس والمفروشات',
						'clean'   => 'تنظيف المنازل والفلل والشقق',
					),
				),
				array(
					'id'      => 'months',
					'type'    => 'select',
					'title'   => 'مدة الضمان للسكيما (قائمة منسدلة)',
					'options' => array(
						'0'   => 'نصي فقط (حسب الاتفاق بدون رقم في السكيما)',
						'1'   => 'شهر واحد / ضمان استلام ومراجعة',
						'3'   => '3 أشهر (ربع سنوي)',
						'6'   => '6 أشهر (نصف سنوي)',
						'12'  => 'سنة كاملة (12 شهراً)',
						'60'  => '5 سنوات (60 شهراً)',
						'120' => '10 سنوات (120 شهراً)',
						'180' => '15 عاماً (180 شهراً)',
					),
				),
				array(
					'id'    => 'text',
					'type'  => 'text',
					'title' => 'النص المعروض للعميل في البطاقة والسكيما',
				),
			),
		),
		array( 'id' => 'zad_warranty_policy', 'type' => 'textarea', 'title' => 'تخصيص نصي متقدم للضمان (اختياري للمبرمج)', 'default' => '' ),

		array( 'type' => 'subheading', 'content' => 'تخصيص شكل ومميزات صفحات الخدمات (قوائم منسدلة)' ),
		array( 'id' => 'zad_ticket_title', 'type' => 'text', 'title' => 'عنوان تذكرة الحجز', 'default' => 'وش المشكلة عندك؟' ),
		array( 'id' => 'zad_est_max', 'type' => 'number', 'title' => 'أقصى عدد خيارات في حاسبة السعر', 'default' => 6 ),
		array( 'id' => 'zad_packages_flag', 'type' => 'text', 'title' => 'علامة الباقة المميزة', 'default' => 'الأكثر طلباً' ),
		array( 'id' => 'zad_packages_note', 'type' => 'text', 'title' => 'سطر الملاحظة تحت الباقات', 'default' => 'الأسعار تقديرية وتتحدد بعد المعاينة' ),
		array( 'id' => 'zad_card_badges', 'type' => 'textarea', 'title' => 'شارات الثقة في بطاقات الخدمات', 'default' => "فحص مجاني\nضمان مكتوب" ),
		array( 'id' => 'zad_float_cards', 'type' => 'textarea', 'title' => 'البطاقات العائمة حول نموذج الهيرو', 'default' => "معاينة مجانية | قبل أي عمل | bolt\nتغطية | أحياء {city} | pin\n{years}+ سنة | خبرة موثوقة | star" ),

		array( 'type' => 'subheading', 'content' => 'التحكم في الأقسام الذكية والربط الداخلي داخل صفحة الخدمة' ),
		array( 'id' => 'zad_guides_auto', 'type' => 'switcher', 'title' => 'إظهار «أدلة تهمك» تلقائياً من نفس تصنيف الخدمة', 'default' => true ),
		array( 'id' => 'zad_related_auto', 'type' => 'switcher', 'title' => 'ملء «خدمات مكملة» تلقائياً عند الفراغ', 'default' => true ),
		array( 'id' => 'zad_faq_autolink', 'type' => 'switcher', 'title' => 'ربط الأسئلة بالخدمات تلقائياً', 'default' => true ),
		array(
			'id'          => 'zad_faq_default_service',
			'type'        => 'select',
			
			'title'       => 'الخدمة الافتراضية للأسئلة غير المطابقة (قائمة منسدلة)',
			'options'     => 'posts',
			'query_args'  => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ),
			'placeholder' => '— اختر خدمة —',
		),
		array( 'id' => 'zad_bridges', 'type' => 'textarea', 'title' => 'قواعد الروابط الذكية بين الأقسام (Bridges)', 'desc' => 'مثال: drain-cleaning > cleaning, pest-control' ),
	),
) );

/* ==========================================================================
   4. السيو التقني، السكيما، والـ E-E-A-T (قوائم منسدلة)
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'السيو والسكيما و E-E-A-T',
	'icon'   => 'fa fa-code',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'إعدادات السكيما المتقدمة، المراجع الفني، وحقوق ملكية الصور والـ Indexing' ),
		array(
			'id'      => 'zad_schema_mode',
			'type'    => 'select',
			'title'   => 'وضع السكيما والـ SEO في الموقع (قائمة منسدلة)',
			'options' => array(
				'theme' => 'الثيم يتولّى كل شيء (السكيما + الميتا + خريطة الموقع)',
				'off'   => 'إيقاف محرك السيو والسكيما',
			),
			'default' => 'theme',
		),
		array( 'id' => 'zad_authors', 'type' => 'textarea', 'title' => 'بيانات الكتّاب والخبراء للسكيما', 'default' => implode( "\n", $zauth ) ),
		array(
			'id'          => 'zad_img_rights_page',
			'type'        => 'select',
			
			'title'       => 'صفحة حقوق وترخيص الصور (قائمة منسدلة)',
			'options'     => 'pages',
			'placeholder' => '— اختر صفحة حقوق الصور أو الخصوصية —',
			'desc'        => 'تُطبع في خاصية license داخل ImageObject و VideoObject لحماية صورك في جوجل.',
		),
		array( 'id' => 'zad_img_all_ours', 'type' => 'switcher', 'title' => 'تأكيد ملكية كل صور الموقع (عدا الصور المحددة بأنها ليست من شغلنا)', 'default' => false ),
		array( 'id' => 'zad_about_page', 'type' => 'select',  'title' => 'صفحة «من نحن» (AboutPage Schema)', 'options' => 'pages', 'placeholder' => '— اختر الصفحة —' ),
		array( 'id' => 'zad_contact_page', 'type' => 'select',  'title' => 'صفحة «اتصل بنا» (ContactPage & acquireLicensePage)', 'options' => 'pages', 'placeholder' => '— اختر الصفحة —' ),
		array( 'id' => 'zad_wikidata', 'type' => 'text', 'title' => 'رابط Wikidata للشركة (اختياري)', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_price_range', 'type' => 'text', 'title' => 'نطاق الأسعار العام في السكيما (priceRange)', 'default' => 'من 150 إلى 1200 ر.س' ),
		array( 'id' => 'zad_knows_about', 'type' => 'textarea', 'title' => 'مجالات خبرة الشركة (knowsAbout)', 'default' => implode( "\n", $zb['knows_about'] ) ),
		array( 'id' => 'zad_site_desc', 'type' => 'textarea', 'title' => 'وصف الموقع العام للسكيما', 'default' => $zb['description'] ),
		array( 'id' => 'zad_og_default', 'type' => 'media', 'title' => 'صورة المشاركة الافتراضية (OpenGraph 1200x630)' ),
		array( 'type' => 'subheading', 'content' => 'التتبع والتحقق (Google, Bing, GA4, Google Ads, Meta, TikTok, Snap Pixels)' ),
		array( 'id' => 'zad_google_verify', 'type' => 'text', 'title' => 'رمز تحقق Google Search Console', 'desc' => 'الصق قيمة content فقط بدون وسم meta.', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_bing_verify', 'type' => 'text', 'title' => 'رمز تحقق Bing Webmaster (msvalidate.01)', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_ga4_id', 'type' => 'text', 'title' => 'معرّف Google Analytics 4 (GA4)', 'attributes' => array( 'dir' => 'ltr', 'placeholder' => 'G-XXXXXXXXXX' ) ),
		array( 'id' => 'zad_gads_id', 'type' => 'text', 'title' => 'معرّف إعلانات جوجل (Google Ads ID)', 'attributes' => array( 'dir' => 'ltr', 'placeholder' => 'AW-123456789' ) ),
		array( 'id' => 'zad_gads_label', 'type' => 'text', 'title' => 'تسمية تحويل إعلانات جوجل (Conversion Label)', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_meta_pixel', 'type' => 'text', 'title' => 'معرّف Meta Pixel (فيسبوك وإنستغرام)', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_tiktok_pixel', 'type' => 'text', 'title' => 'معرّف TikTok Pixel', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_snap_pixel', 'type' => 'text', 'title' => 'معرّف Snap Pixel (سناب شات)', 'attributes' => array( 'dir' => 'ltr' ) ),
		array( 'id' => 'zad_work_smart_noindex', 'type' => 'switcher', 'title' => 'تنبيه عند قلة تفاصيل «أعمالنا»', 'default' => true, 'desc' => 'يعرض ملاحظة في المحرر عندما يقل الوصف عن 60 كلمة. لا يمنع الفهرسة تلقائياً؛ جودة الصفحة تُقيّم من محتواها والصور والفيديو ونتيجة العمل.' ),


		array( 'type' => 'subheading', 'content' => 'إعدادات الأرشفة والروابط الدائمة (Permalinks & Indexing)' ),
		array( 'id' => 'zad_service_slugs', 'type' => 'text', 'title' => 'أنواع المنشورات التي تعمل كصفحات خدمة', 'default' => 'pest-control,cleaning' ),
		array( 'id' => 'zad_faq_slugs', 'type' => 'text', 'title' => 'أنواع المنشورات التي تعمل كصفحات أسئلة', 'default' => 'faq' ),
		array( 'id' => 'zad_article_slugs', 'type' => 'text', 'title' => 'أنواع المنشورات التي تعمل كمقالات وأدلة', 'default' => 'sections,guide,pests-library' ),
		array( 'id' => 'zad_services_slug', 'type' => 'text', 'title' => 'رابط أرشيف الخدمات', 'default' => 'services' ),
		array( 'id' => 'zad_works_slug', 'type' => 'text', 'title' => 'رابط أرشيف «أعمالنا»', 'default' => 'works' ),
		array( 'id' => 'zad_areas_slug', 'type' => 'text', 'title' => 'رابط أرشيف المدن والأحياء', 'default' => 'areas' ),
		array( 'id' => 'zad_faq_slug', 'type' => 'text', 'title' => 'رابط أرشيف الأسئلة', 'default' => 'faq' ),
		array( 'id' => 'zad_thin_noindex', 'type' => 'switcher', 'title' => 'منع فهرسة الأحياء الفارغة تلقائياً (Noindex Thin Areas)', 'default' => false ),
		array( 'id' => 'zad_noindex_tag', 'type' => 'switcher', 'title' => 'noindex لأرشيف الوسوم', 'default' => false ),
		array( 'id' => 'zad_noindex_cat', 'type' => 'switcher', 'title' => 'noindex لأرشيف التصنيفات', 'default' => false ),
		array( 'id' => 'zad_noindex_author', 'type' => 'switcher', 'title' => 'noindex لأرشيف المؤلفين', 'default' => false ),
		array( 'id' => 'zad_noindex_date', 'type' => 'switcher', 'title' => 'noindex لأرشيف التواريخ', 'default' => false ),
		array( 'id' => 'zad_noindex_paged', 'type' => 'switcher', 'title' => 'noindex للصفحات المرقّمة (صفحة 2 فأكثر)', 'default' => false ),
		array( 'id' => 'zad_comments_on', 'type' => 'switcher', 'title' => 'تفعيل التعليقات على المقالات', 'default' => false ),
	),
) );

/* ==========================================================================
   5. الصفحة الرئيسية وأقسامها التفاعلية الكاملة
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'الصفحة الرئيسية الكاملة',
	'icon'   => 'fa fa-home',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'واجهة الصفحة الرئيسية (Hero) وشريط المزايا' ),
		array( 'id' => 'zad_hero_badge', 'type' => 'text', 'title' => 'شارة أعلى العنوان', 'default' => 'شركة مرخّصة • ضمان مكتوب • فنيون معتمدون' ),
		array( 'id' => 'zad_hero_title', 'type' => 'text', 'title' => 'العنوان الرئيسي', 'default' => 'خدمات منزلية احترافية بضمان حقيقي' ),
		array( 'id' => 'zad_hero_sub', 'type' => 'textarea', 'title' => 'النص التعريفي', 'default' => 'مكافحة حشرات، تنظيف منازل ومكيفات وخزانات وكنب، بفنيين مدربين ومواد معتمدة وأسعار واضحة قبل التنفيذ.' ),
		array( 'id' => 'zad_hero_points', 'type' => 'textarea', 'title' => 'نقاط الثقة (سطر لكل نقطة)', 'default' => "معاينة وتسعير مجاني\nمواد آمنة على الأطفال والحيوانات الأليفة\nتنفيذ في نفس اليوم" ),
		array( 'id' => 'zad_hero_img', 'type' => 'media', 'title' => 'صورة الواجهة الرئيسية' ),
		array(
			'id' => 'zad_highlights', 'type' => 'group', 'title' => 'شريط المزايا أسفل الهيرو', 'button_title' => 'إضافة ميزة +',
			'fields' => array(
				array( 'id' => 'icon', 'type' => 'select', 'title' => 'الأيقونة (قائمة منسدلة)', 'options' => $zad_icons_dropdown ),
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'text', 'title' => 'وصف قصير' ),
			),
		),
		array(
			'id' => 'zad_stats', 'type' => 'group', 'title' => 'الأرقام والإنجازات', 'button_title' => 'إضافة رقم +',
			'fields' => array(
				array( 'id' => 'n', 'type' => 'text', 'title' => 'الرقم (مثال: +15,000)' ),
				array( 'id' => 'l', 'type' => 'text', 'title' => 'الوصف' ),
			),
		),

		array( 'type' => 'subheading', 'content' => 'خريطة المنزل التفاعلية، الأمان، والخطوات' ),
		array(
			'id' => 'zad_rooms', 'type' => 'group', 'title' => 'خريطة المنزل (حسب المكان)', 'button_title' => 'إضافة مكان +',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'اسم المكان (مطبخ، حمّام، حديقة…)' ),
				array( 'id' => 'icon', 'type' => 'select', 'title' => 'الأيقونة (قائمة منسدلة)', 'options' => $zad_icons_dropdown ),
				array( 'id' => 'desc', 'type' => 'text', 'title' => 'وصف قصير' ),
				array( 'id' => 'services', 'type' => 'select', 'title' => 'الخدمات المرتبطة (قائمة منسدلة)',  'multiple' => true, 'sortable' => true, 'options' => 'posts', 'query_args' => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ) ),
			),
		),
		array( 'id' => 'zad_home_safety_title', 'type' => 'text', 'title' => 'عنوان قسم الأمان', 'default' => 'آمن لمن تحب' ),
		array(
			'id' => 'zad_home_safety', 'type' => 'group', 'title' => 'بطاقات الأمان', 'button_title' => 'إضافة بطاقة +',
			'fields' => array(
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_process', 'type' => 'group', 'title' => 'كيف نعمل (خطوات العمل في الرئيسية)', 'button_title' => 'إضافة خطوة +',
			'fields' => array(
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),

		array( 'type' => 'subheading', 'content' => 'قبل وبعد، الفيديو، العملاء، والأسئلة الشائعة في الرئيسية' ),
		array(
			'id' => 'zad_home_ba', 'type' => 'group', 'title' => 'نتائج قبل / بعد في الرئيسية', 'button_title' => 'إضافة مقارنة +',
			'fields' => array(
				array( 'id' => 'before', 'type' => 'media', 'title' => 'صورة قبل', 'library' => 'image' ),
				array( 'id' => 'after', 'type' => 'media', 'title' => 'صورة بعد', 'library' => 'image' ),
				array( 'id' => 'title', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'desc', 'type' => 'text', 'title' => 'وصف' ),
			),
		),
		array( 'id' => 'zad_home_video', 'type' => 'text', 'title' => 'فيديو الرئيسية (YouTube أو mp4)' ),
		array( 'id' => 'zad_home_video_poster', 'type' => 'media', 'title' => 'صورة غلاف الفيديو', 'library' => 'image' ),
		array( 'id' => 'zad_guarantee_title', 'type' => 'text', 'title' => 'شريط الضمان في الرئيسية: العنوان', 'default' => 'ضمان مكتوب على كل خدمة' ),
		array( 'id' => 'zad_guarantee_text', 'type' => 'text', 'title' => 'شريط الضمان في الرئيسية: النص', 'default' => 'إن عادت المشكلة خلال مدة الضمان نعالجها مجاناً — نحدد المدة كتابةً قبل البدء.' ),
		array(
			'id' => 'zad_testimonials', 'type' => 'group', 'title' => 'آراء العملاء في الرئيسية', 'button_title' => 'إضافة رأي +',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'الاسم' ),
				array( 'id' => 'city', 'type' => 'text', 'title' => 'المدينة' ),
				array( 'id' => 'text', 'type' => 'textarea', 'title' => 'الرأي' ),
				array( 'id' => 'rating', 'type' => 'number', 'title' => 'التقييم (1-5)', 'default' => 5 ),
			),
		),
		array(
			'id' => 'zad_clients', 'type' => 'group', 'title' => 'عملاؤنا (جهات وشركات)', 'button_title' => 'إضافة عميل +',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'الاسم' ),
				array( 'id' => 'note', 'type' => 'text', 'title' => 'ملاحظة (المدينة / المشروع)' ),
			),
		),
		array(
			'id' => 'zad_sectors', 'type' => 'group', 'title' => 'قطاعات العملاء (عقود المنشآت B2B)', 'button_title' => 'إضافة قطاع +',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'اسم القطاع' ),
				array( 'id' => 'names', 'type' => 'textarea', 'title' => 'أسماء العملاء (اسم في كل سطر)' ),
				array( 'id' => 'note', 'type' => 'text', 'title' => 'ملاحظة بدل الأسماء' ),
			),
		),
		array(
			'id' => 'zad_faq', 'type' => 'group', 'title' => 'أسئلة شائعة (الرئيسية)', 'button_title' => 'إضافة سؤال +',
			'fields' => array(
				array( 'id' => 'q', 'type' => 'text', 'title' => 'السؤال' ),
				array( 'id' => 'a', 'type' => 'textarea', 'title' => 'الإجابة' ),
			),
		),
		array( 'id' => 'zad_home_areas', 'type' => 'textarea', 'title' => 'مناطق الخدمة في الصفحة الرئيسية (الاسم | الرابط | وصف)' ),
		array( 'id' => 'zad_cta_title', 'type' => 'text', 'title' => 'عنوان الدعوة الختامية', 'default' => 'جاهزون لخدمتك الآن' ),
		array( 'id' => 'zad_cta_sub', 'type' => 'text', 'title' => 'نص الدعوة الختامية', 'default' => 'تواصل معنا وسيصلك الفني مع عرض سعر واضح قبل البدء.' ),
	),
) );

/* ==========================================================================
   6. فهرس الأقسام، الأدوات التفاعلية، وصفحة من نحن
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'الأقسام والأدوات التفاعلية',
	'icon'   => 'fa fa-magic',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'إعدادات نافذة الحجز السريع (Booking Wizard) وأداة تشخيص المشكلة' ),
		array( 'id' => 'zad_wiz_cats', 'type' => 'textarea', 'title' => 'أقسام نافذة «احجز موعدك» (الاسم | الأيقونة)', 'desc' => 'اتركها فارغة للأقسام الافتراضية أو اكتب auto.' ),
		array( 'id' => 'zad_wiz_cities', 'type' => 'textarea', 'title' => 'مدن نافذة «احجز موعدك»', 'desc' => 'اتركه فارغاً لتأخذ النافذة كل مدن الموقع تلقائياً.', 'default' => implode( "\n", function_exists( 'zad_city_map' ) ? zad_city_map() : array() ) ),
		array( 'id' => 'zad_wiz_names', 'type' => 'textarea', 'title' => 'تسميات مخصصة لأقسام نافذة الحجز' ),
		array( 'id' => 'zad_dx_symptoms', 'type' => 'textarea', 'title' => 'أداة شخّص مشكلتك: الأعراض العامة (العرض | الخدمة | السعر | الرابط | إيموجي)' ),
		array( 'id' => 'zad_dx_home_title', 'type' => 'text', 'title' => 'شخّص مشكلتك: العنوان', 'default' => 'لا تعرف اسم المشكلة؟' ),
		array( 'id' => 'zad_dx_home_sub', 'type' => 'text', 'title' => 'شخّص مشكلتك: السطر تحت العنوان' ),

		array( 'type' => 'subheading', 'content' => 'أداة «تعرّف على الآفة» التفاعلية' ),
		array( 'id' => 'zad_identify_where', 'type' => 'textarea', 'title' => 'أين رأيتها؟ (خيار في كل سطر)', 'default' => "المطبخ\nالحمّام والصرف\nغرفة النوم والأرائك\nالحديقة والمحيط\nالأخشاب والجدران" ),
		array( 'id' => 'zad_identify_kind', 'type' => 'textarea', 'title' => 'كيف تبدو؟ (خيار في كل سطر)', 'default' => "حشرة زاحفة داكنة\nحشرة صغيرة تعض\nحشرة طائرة\nقوارض أو آثار قرض\nنمل بأجنحة أو ثقوب بالخشب" ),
		array(
			'id' => 'zad_identify_rules', 'type' => 'group', 'title' => 'نتائج أداة تعرّف على الآفة', 'button_title' => 'إضافة نتيجة +',
			'fields' => array(
				array( 'id' => 'where', 'type' => 'text', 'title' => 'المكان' ),
				array( 'id' => 'kind', 'type' => 'text', 'title' => 'الشكل' ),
				array( 'id' => 'title', 'type' => 'text', 'title' => 'اسم الآفة المرجّحة' ),
				array( 'id' => 'text', 'type' => 'textarea', 'title' => 'شرح قصير ونصيحة' ),
				array( 'id' => 'service', 'type' => 'select',  'title' => 'الخدمة المقترحة (قائمة منسدلة)', 'options' => 'posts', 'query_args' => array( 'post_type' => zad_service_types(), 'posts_per_page' => -1 ) ),
			),
		),

		array( 'type' => 'subheading', 'content' => 'صفحات الأقسام الرئيسية (Hubs) وصفحة من نحن' ),
		array(
			'id' => 'zad_hubs', 'type' => 'group', 'title' => 'صفحات الأقسام (Hubs)', 'button_title' => 'إضافة قسم +',
			'fields' => array(
				array( 'id' => 'slug', 'type' => 'text', 'title' => 'رابط النوع (مثال: pest-control أو cleaning)' ),
				array( 'id' => 'headline', 'type' => 'text', 'title' => 'العنوان الرئيسي (H1)' ),
				array( 'id' => 'lead', 'type' => 'textarea', 'title' => 'مقدمة قصيرة تحت العنوان' ),
				array( 'id' => 'body', 'type' => 'wp_editor', 'title' => 'نظرة عامة', 'media_buttons' => false ),
				array( 'id' => 'decide', 'type' => 'textarea', 'title' => 'اختر حسب حالتك' ),
				array( 'id' => 'tips', 'type' => 'textarea', 'title' => 'كيف تختار؟' ),
				array( 'id' => 'cta', 'type' => 'text', 'title' => 'نص زر الطلب', 'default' => 'اطلب معاينة مجانية' ),
			),
		),
		array( 'id' => 'zad_about_tagline', 'type' => 'text', 'title' => 'صفحة من نحن: جملة تحت العنوان', 'default' => 'نعرّفك بنا: من نحن، وماذا نؤمن به، وكيف نعمل لخدمتك.' ),
		array( 'id' => 'zad_story_title', 'type' => 'text', 'title' => 'عنوان «قصتنا»' ),
		array( 'id' => 'zad_story_text', 'type' => 'wp_editor', 'title' => 'نص «قصتنا»', 'media_buttons' => false ),
		array( 'id' => 'zad_story_img', 'type' => 'media', 'title' => 'صورة القصة', 'library' => 'image' ),
		array( 'id' => 'zad_mission', 'type' => 'textarea', 'title' => 'رسالتنا' ),
		array( 'id' => 'zad_vision', 'type' => 'textarea', 'title' => 'رؤيتنا' ),
		array(
			'id' => 'zad_values', 'type' => 'group', 'title' => 'قيمنا', 'button_title' => 'إضافة قيمة +',
			'fields' => array(
				array( 'id' => 'icon', 'type' => 'select', 'title' => 'الأيقونة (قائمة منسدلة)', 'options' => $zad_icons_dropdown ),
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_timeline', 'type' => 'group', 'title' => 'رحلتنا (محطات الشركة)', 'button_title' => 'إضافة محطة +',
			'fields' => array(
				array( 'id' => 'year', 'type' => 'text', 'title' => 'السنة' ),
				array( 'id' => 't', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'd', 'type' => 'textarea', 'title' => 'الوصف' ),
			),
		),
		array(
			'id' => 'zad_team', 'type' => 'group', 'title' => 'فريق العمل', 'button_title' => 'إضافة عضو +',
			'fields' => array(
				array( 'id' => 'name', 'type' => 'text', 'title' => 'الاسم' ),
				array( 'id' => 'role', 'type' => 'text', 'title' => 'المسمى' ),
				array( 'id' => 'img', 'type' => 'media', 'title' => 'الصورة', 'library' => 'image' ),
			),
		),
	),
) );

/* ==========================================================================
   7. الأداء، السرعة، وأدوات الفحص والنقل (قوائم منسدلة للمدير)
   ========================================================================== */
Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'السرعة وأدوات المطور',
	'icon'   => 'fa fa-tachometer',
	'fields' => array(
		array( 'type' => 'subheading', 'content' => 'التحكم في أدوات الفحص والنقل المؤقتة (معزولة تماماً عن الزوار وجوجل)' ),
		array(
			'id'      => 'zad_tools_mode',
			'type'    => 'select',
			'title'   => 'وضع أدوات الفحص والنقل في لوحة التحكم (قائمة منسدلة)',
			'options' => array(
				'all'    => 'تفعيل جميع أدوات الفحص والنقل (الوضع الكامل أثناء التطوير)',
				'custom' => 'اختيار أدوات محددة فقط من القائمة المنسدلة بالأسفل',
				'off'    => 'إيقاف جميع أدوات النقل والفحص المؤقتة (بعد الانتهاء من الموقع)',
			),
			'default' => 'all',
		),
		array(
			'id'         => 'zad_active_tools_list',
			'type'       => 'select',
			
			'multiple'   => true,
			'title'      => 'اختر الأدوات المفعّلة (قائمة منسدلة متعددة الاختيار)',
			'options'    => array(
				'leadreport'   => 'تقارير الطلبات والنقرات (Lead Report)',
				'schemacheck'  => 'مفتش السكيما والـ JSON-LD (Schema Check)',
				'audit'        => 'فاحص السيو الشامل للموقع (SEO Audit)',
				'domaudit'     => 'فاحص عناصر الصفحة والـ DOM (DOM Audit)',
				'faqmigrate'   => 'نقل الأسئلة الشائعة (FAQ Migrate)',
				'svcconvert'   => 'محوّل صفحات الخدمات (Service Convert)',
				'faqanswer'    => 'أداة إجابات الأسئلة (FAQ Answer Tool)',
				'diag'         => 'تشخيص النظام (System Diagnostics)',
				'layout'       => 'تصميم صفحات الخدمة (كلاسيكي/جديد)',
			),
			'default'    => array( 'leadreport', 'worksmigrate', 'schemacheck', 'audit' ),
			'dependency' => array( 'zad_tools_mode', '==', 'custom' ),
		),

		array( 'type' => 'subheading', 'content' => 'إعدادات السرعة الفائقة وتخفيف الكود (Core Web Vitals)' ),
		array( 'id' => 'zad_strip_wp_css', 'type' => 'switcher', 'title' => 'تقليل CSS ووردبريس الافتراضي في الواجهة (يوفر 20KB)', 'default' => true ),
		array( 'id' => 'zad_icon_sprite', 'type' => 'switcher', 'title' => 'استخدام SVG Sprite موحّد للأيقونات (يقلل حجم الـ DOM)', 'default' => true ),
		array( 'id' => 'zad_lean', 'type' => 'switcher', 'title' => 'تخفيف الأصول والملفات غير اللازمة', 'default' => false ),
		array( 'id' => 'zad_lean_rules', 'type' => 'textarea', 'title' => 'قواعد تخفيف مخصصة', 'dependency' => array( 'zad_lean', '==', 'true' ) ),
		array(
			'id'      => 'zad_ver_mode',
			'type'    => 'select',
			'title'   => 'إدارة أرقام الإصدار (?ver=) في روابط CSS/JS (قائمة منسدلة)',
			'options' => array(
				'off'    => 'الوضع الافتراضي (تحديث تلقائي حسب وقت تعديل الملف)',
				'theme'  => 'توحيد الكل برقم إصدار الثيم',
				'remove' => 'إزالة ?ver= تماماً من الروابط',
			),
			'default' => 'off',
		),
		array( 'id' => 'zad_audit_min_words', 'type' => 'number', 'title' => 'حد المحتوى القليل في فاحص السيو (كلمة)', 'default' => 300 ),
	),
) );

Zad_Settings::createSection( $zad_prefix, array( 'title' => 'تشغيل محرك السيو', 'fields' => array(
 array( 'type' => 'subheading', 'content' => 'محرك السيو داخل الثيم ومخرجاته الاختيارية' ),
 array( 'id' => 'zad_llms_enabled', 'type' => 'switcher', 'title' => 'توفير ملفات التعريف النصية للمحتوى المنشور', 'default' => true, 'desc' => 'تُستبعد المسودات والصفحات المحمية والروابط المحولة وnoindex. ليس ضماناً للظهور في البحث.' ),
 array( 'id' => 'zad_indexnow_enabled', 'type' => 'switcher', 'title' => 'إرسال تحديث الروابط إلى IndexNow', 'default' => false, 'desc' => 'اختياري. لا يرسل من اللوكال أو الموقع الخاص، ويستخدم نفس أهلية خرائط الموقع.' ),
 array( 'id' => 'zad_redirect_junk', 'type' => 'switcher', 'title' => 'الروابط الزبالة ترجع 410', 'default' => true, 'desc' => 'روابط فيها tel: أو whatsapp: أو esc_url( أو ‎?replytocom، وملفات ‎.php مش موجودة. التحويلات نفسها في الأدوات ← التحويلات (زاد).' ),
) ) );
