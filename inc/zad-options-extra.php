<?php defined( 'ABSPATH' ) || exit;
/**
 * «بيانات ونصوص إضافية»: settings the theme already reads but that had no field (some kept from the old Memo theme).
 * Same option keys as before, so every saved value shows up here unchanged and nothing is migrated.
 */
if ( ! class_exists( 'Zad_Settings' ) ) { return; }
$zad_prefix = '_memo_theme_options';

Zad_Settings::createSection( $zad_prefix, array(
	'title'  => 'بيانات ونصوص إضافية',
	'fields' => array(

		array( 'type' => 'subheading', 'content' => 'بيانات النشاط في السكيما (تظهر لجوجل)' ),
		array( 'id' => 'zad_street', 'type' => 'text', 'title' => 'الشارع ورقم المبنى', 'desc' => 'مثال: 2851 شارع عبدالملك بن مروان' ),
		array( 'id' => 'zad_district', 'type' => 'text', 'title' => 'الحي' ),
		array( 'id' => 'zad_region', 'type' => 'text', 'title' => 'المنطقة', 'desc' => 'مثال: منطقة الرياض' ),
		array( 'id' => 'zad_postal', 'type' => 'text', 'title' => 'الرمز البريدي' ),
		array( 'id' => 'zad_city_name', 'type' => 'text', 'title' => 'المدينة الافتراضية', 'desc' => 'تُستخدم عندما لا تكون للصفحة مدينة. الافتراضي: الرياض.' ),
		array( 'id' => 'zad_lat', 'type' => 'text', 'title' => 'خط العرض (Latitude)' ),
		array( 'id' => 'zad_lng', 'type' => 'text', 'title' => 'خط الطول (Longitude)' ),
		array( 'id' => 'zad_map_url', 'type' => 'text', 'title' => 'رابط ملف النشاط على خرائط جوجل', 'desc' => 'رابط الملف نفسه (وليس إحداثيات). يُستخدم في hasMap وsameAs.' ),
		array( 'id' => 'zad_hours_spec', 'type' => 'textarea', 'title' => 'مواعيد العمل للسكيما', 'desc' => 'سطر لكل فترة: الأيام بالإنجليزية مفصولة بفاصلة | من | إلى. مثال: Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday | 08:00 | 23:00' ),
		array( 'id' => 'zad_area_served', 'type' => 'textarea', 'title' => 'المناطق المخدومة للسكيما', 'desc' => 'سطر لكل منطقة: النوع | الاسم. مثال: City | الرياض — أو AdministrativeArea | منطقة القصيم' ),

		array( 'type' => 'subheading', 'content' => 'حسابات إضافية (الفوتر وsameAs)' ),
		array( 'id' => 'zad_linkedin', 'type' => 'text', 'title' => 'لينكدإن' ),
		array( 'id' => 'zad_tiktok', 'type' => 'text', 'title' => 'تيك توك' ),
		array( 'id' => 'zad_snapchat', 'type' => 'text', 'title' => 'سناب شات' ),
		array( 'id' => 'zad_pinterest', 'type' => 'text', 'title' => 'بنترست' ),

		array( 'type' => 'subheading', 'content' => 'الصفحة الرئيسية' ),
		array( 'id' => 'memopt_sec4_h', 'type' => 'text', 'title' => 'عنوان قسم «لماذا نحن»' ),
		array(
			'id'           => 'memo_sec4_grp',
			'type'         => 'group',
			'title'        => 'بنود «لماذا نحن»',
			'button_title' => 'إضافة بند',
			'fields'       => array(
				array( 'id' => 'memo_sec4_grp_h', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'memo_sec4_grp_p', 'type' => 'textarea', 'title' => 'الوصف' ),
				array( 'id' => 'memo_sec4_grp_img', 'type' => 'media', 'title' => 'الأيقونة / الصورة' ),
			),
		),
		array( 'id' => 'zad_about_eyebrow', 'type' => 'text', 'title' => 'قسم «من نحن» — الكلمة الصغيرة فوق العنوان' ),
		array( 'id' => 'zad_about_title', 'type' => 'text', 'title' => 'قسم «من نحن» — العنوان', 'desc' => 'اتركه فارغاً لإخفاء القسم.' ),
		array( 'id' => 'zad_about_text', 'type' => 'textarea', 'title' => 'قسم «من نحن» — النص' ),
		array( 'id' => 'zad_about_points', 'type' => 'textarea', 'title' => 'قسم «من نحن» — النقاط', 'desc' => 'سطر لكل نقطة.' ),
		array( 'id' => 'zad_about_img', 'type' => 'media', 'title' => 'قسم «من نحن» — الصورة' ),
		array( 'id' => 'zad_price_title', 'type' => 'text', 'title' => 'عنوان قسم الأسعار', 'default' => 'أسعار واضحة تبدأ من' ),
		array( 'id' => 'zad_clients_title', 'type' => 'text', 'title' => 'عنوان قسم العملاء' ),
		array( 'id' => 'zad_clients_sub', 'type' => 'textarea', 'title' => 'وصف قسم العملاء' ),
		array( 'id' => 'zad_rating_score', 'type' => 'text', 'title' => 'التقييم المعروض (مثال 4.9)', 'desc' => 'اكتب رقماً حقيقياً من ملف جوجل فقط.' ),
		array( 'id' => 'zad_rating_count', 'type' => 'text', 'title' => 'عدد التقييمات المعروض' ),

		array( 'type' => 'subheading', 'content' => 'تقييمات جوجل (سحب تلقائي) — الحالة والزر في أدوات ← تقييمات جوجل (زاد)' ),
		array( 'id' => 'zad_gmb_key', 'type' => 'text', 'title' => 'مفتاح Google Places API', 'desc' => 'يُستخدم من السيرفر فقط ولا يظهر للزوار. فعّل Places API (New) وقيّد المفتاح عليه.', 'attributes' => array( 'dir' => 'ltr', 'autocomplete' => 'off' ) ),
		array( 'id' => 'zad_gmb_place', 'type' => 'text', 'title' => 'Place ID لملف النشاط', 'desc' => 'من أداة Place ID من جوجل. لو اتسحب بنجاح، التقييم الحقيقي بيحل محل القيمتين اليدويتين فوق.', 'attributes' => array( 'dir' => 'ltr' ) ),

		array( 'type' => 'subheading', 'content' => 'صفحة «من نحن» (القالب القديم)' ),
		array( 'id' => 'memopt_about_sec1_h', 'type' => 'wp_editor', 'title' => 'نص التعريف' ),
		array( 'id' => 'memopt_about_sec1_img', 'type' => 'media', 'title' => 'صورة التعريف', 'desc' => 'تُستخدم فقط إذا لم تُحدَّد صورة القصة في قسم «من نحن».' ),
		array(
			'id'           => 'memopt_about_sec2_grp',
			'type'         => 'group',
			'title'        => 'بطاقات الرؤية والرسالة',
			'button_title' => 'إضافة بطاقة',
			'fields'       => array(
				array( 'id' => 'memopt_about_sec2_grp_h', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'memopt_about_sec2_grp_p', 'type' => 'textarea', 'title' => 'النص' ),
				array( 'id' => 'memopt_about_sec2_grp_img', 'type' => 'media', 'title' => 'الصورة' ),
			),
		),
		array( 'id' => 'memopt_about_sec3_h', 'type' => 'wp_editor', 'title' => 'الأهداف' ),
		array(
			'id'           => 'zad_global_stats',
			'type'         => 'group',
			'title'        => 'أرقام الصفحة',
			'button_title' => 'إضافة رقم',
			'fields'       => array(
				array( 'id' => 'n', 'type' => 'text', 'title' => 'الرقم' ),
				array( 'id' => 'l', 'type' => 'text', 'title' => 'الوصف' ),
			),
		),
		array( 'id' => 'zad_commit_points', 'type' => 'textarea', 'title' => 'التزاماتنا', 'desc' => 'سطر لكل التزام.' ),

		array( 'type' => 'subheading', 'content' => 'المعرض ومكتبة الآفات' ),
		array( 'id' => 'memopt_gallery_sec1_h', 'type' => 'text', 'title' => 'وصف صفحة المعرض' ),
		array(
			'id'           => 'memopt_gallery_grp',
			'type'         => 'group',
			'title'        => 'صور قبل وبعد إضافية (تظهر في «أعمالنا»)',
			'desc'         => 'البند يظهر فقط إذا كانت فيه صورة قبل وصورة بعد.',
			'button_title' => 'إضافة بند',
			'fields'       => array(
				array( 'id' => 'memopt_gallery_grp_h', 'type' => 'text', 'title' => 'العنوان' ),
				array( 'id' => 'memopt_gallery_grp_link', 'type' => 'text', 'title' => 'الرابط' ),
				array( 'id' => 'memopt_gallery_grp_img_before', 'type' => 'media', 'title' => 'صورة قبل' ),
				array( 'id' => 'memopt_gallery_grp_img_after', 'type' => 'media', 'title' => 'صورة بعد' ),
			),
		),
		array( 'id' => 'memopt_pest_sec1_h', 'type' => 'wp_editor', 'title' => 'مقدمة أرشيف مكتبة الآفات' ),

		array( 'type' => 'subheading', 'content' => 'مقدمات الأقسام' ),
		array( 'id' => 'zad_lib_title', 'type' => 'text', 'title' => 'عنوان مكتبة المقالات', 'default' => 'مكتبة المعرفة' ),
		array( 'id' => 'zad_lib_lead', 'type' => 'textarea', 'title' => 'وصف مكتبة المقالات' ),
		array( 'id' => 'zad_kb_title', 'type' => 'text', 'title' => 'عنوان مركز الأسئلة', 'default' => 'مركز المساعدة' ),
		array( 'id' => 'zad_kb_lead', 'type' => 'textarea', 'title' => 'وصف مركز الأسئلة' ),
		array( 'id' => 'zad_services_title', 'type' => 'text', 'title' => 'عنوان صفحة كل الخدمات /services/ (H1)', 'desc' => 'فارغ = «خدمات اسم الشركة».' ),
		array( 'id' => 'zad_services_intro', 'type' => 'textarea', 'title' => 'مقدمة صفحة كل الخدمات /services/', 'desc' => 'نص يظهر تحت الهيدر فوق كروت الأقسام.' ),
		array( 'id' => 'zad_faq_intro', 'type' => 'textarea', 'title' => 'مقدمة أرشيف الأسئلة' ),
		array( 'id' => 'zad_footer_areas', 'type' => 'textarea', 'title' => 'عمود «مناطق نخدمها» في الفوتر', 'desc' => 'سطر لكل رابط: الاسم | الرابط. اتركه فارغاً لإخفاء العمود.' ),

		array( 'type' => 'subheading', 'content' => 'متقدم' ),
		array( 'id' => 'zad_service_slug', 'type' => 'text', 'title' => 'بادئة رابط الخدمة المفردة', 'desc' => 'غيّرها فقط مع خطة تحويلات 301؛ تغييرها يغيّر روابط كل الخدمات.' ),
	),
) );
