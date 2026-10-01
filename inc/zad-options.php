<?php if ( ! defined( 'ABSPATH' ) ) { die; }
/** Extra theme-option sections (same option key as the core options). */

$zad_prefix = '_memo_theme_options';

CSF::createSection( $zad_prefix, array(
	'title'  => 'الهوية والألوان',
	'fields' => array(
		array( 'id' => 'zad_color_primary', 'type' => 'color', 'title' => 'اللون الأساسي', 'default' => '#0b2e3a' ),
		array( 'id' => 'zad_color_accent', 'type' => 'color', 'title' => 'لون الأزرار (التمييز)', 'default' => '#f2b134' ),
		array( 'id' => 'memopt_lead_email', 'type' => 'text', 'title' => 'بريد استقبال الطلبات', 'desc' => 'إن تُرك فارغاً يُستخدم بريد التواصل العام.' ),
		array( 'id' => 'zad_hours', 'type' => 'text', 'title' => 'أوقات العمل', 'default' => 'نخدمكم 24 ساعة طوال أيام الأسبوع' ),
		array( 'id' => 'zad_provider', 'type' => 'text', 'title' => 'الاسم الرسمي للشركة', 'default' => '' ),
		array( 'id' => 'zad_since', 'type' => 'text', 'title' => 'سنة التأسيس', 'default' => '' ),
		array( 'id' => 'zad_reg', 'type' => 'text', 'title' => 'رقم السجل التجاري (اختياري)' ),
		array( 'id' => 'zad_rating_text', 'type' => 'text', 'title' => 'نص التقييم أعلى الموقع', 'default' => 'تقييمات حقيقية على Google' ),
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
		array( 'id' => 'zad_cta_title', 'type' => 'text', 'title' => 'عنوان الدعوة الختامية', 'default' => 'جاهزون لخدمتك الآن' ),
		array( 'id' => 'zad_cta_sub', 'type' => 'text', 'title' => 'نص الدعوة الختامية', 'default' => 'تواصل معنا وسيصلك الفني مع عرض سعر واضح قبل البدء.' ),
	),
) );
