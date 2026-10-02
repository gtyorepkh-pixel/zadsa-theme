<?php defined( 'ABSPATH' ) || exit;
/**
 * Home-page content that shows from the first day, so the page is complete before anything is typed.
 * Only statements that are true for any site on this theme (process, method, company data) or that
 * are computed live (number of services/cities). Nothing here is saved: typing a value in the theme
 * options replaces it. Invented numbers, reviews, clients and results are NOT included.
 *
 * Admin-only preview (?zad_preview=1) additionally shows clearly-labelled sample testimonials/clients.
 */

function zad_home_preview() {
	static $on = null;
	if ( null === $on ) {
		$on = is_front_page() && ! empty( $_GET['zad_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return $on;
}

/** Services that match any keyword in their title (for the house map). */
function zad_home_match_services( $words, $max = 4 ) {
	static $all = null;
	if ( null === $all ) {
		$all = array();
		foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
			$all[ $p->ID ] = mb_strtolower( wp_strip_all_tags( $p->post_title ), 'UTF-8' );
		}
	}
	$out = array();
	foreach ( $all as $id => $t ) {
		foreach ( $words as $w ) {
			if ( false !== mb_strpos( $t, $w, 0, 'UTF-8' ) ) { $out[] = (string) $id; break; }
		}
		if ( count( $out ) >= $max ) { break; }
	}
	return $out;
}

function zad_home_defaults() {
	static $d = null;
	if ( null !== $d ) {
		return $d;
	}
	$b      = function_exists( 'zsc_defaults' ) ? zsc_defaults()['business'] : array( 'area_served' => array(), 'description' => '' );
	$cities = wp_list_pluck( array_map( function ( $a ) { return array( 'n' => $a[1] ); }, $b['area_served'] ), 'n' );
	// main services only: neighbourhood pages are excluded by the global query filter
	$cq  = new WP_Query( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$nsv = (int) $cq->found_posts;
	$hours  = zad_hours_text();

	$stats = array();
	if ( $nsv >= 3 ) { $stats[] = array( 'n' => (string) $nsv, 'l' => 'خدمة نقدّمها' ); }
	if ( $cities ) { $stats[] = array( 'n' => (string) count( $cities ), 'l' => 'مدن نخدمها' ); }
	$stats[] = array( 'n' => 'مجاناً', 'l' => 'المعاينة قبل أي عمل' );
	$stats[] = array( 'n' => '8 – 10', 'l' => 'ساعات الخدمة يومياً' );

	$rooms = array();
	foreach ( array(
		array( 'المطبخ', 'sparkle', 'الصراصير والنمل ورائحة الصرف أكثر ما يظهر هنا.', array( 'صراصير', 'نمل', 'مطبخ', 'أفران' ) ),
		array( 'الحمّام والصرف', 'drop', 'رطوبة وانسدادات وحشرات تدخل من البلاعات.', array( 'مجاري', 'تسليك', 'صراصير', 'بالوعة' ) ),
		array( 'غرف النوم', 'home', 'بق الفراش والمكيفات تحتاج عناية مبكرة.', array( 'بق', 'فراش', 'مكيف' ) ),
		array( 'المجلس والكنب', 'sparkle', 'أتربة وبقع ومفروشات تحتاج تنظيفاً عميقاً.', array( 'كنب', 'موكيت', 'مجالس', 'ستائر', 'سجاد' ) ),
		array( 'السطح والحوش', 'shield', 'قوارض وعزل ونمل أبيض وحمام.', array( 'فئران', 'قوارض', 'عزل', 'نمل أبيض', 'حمام', 'مسبح' ) ),
		array( 'الخزانات', 'drop', 'تنظيف وتعقيم وعزل لخزانات المياه.', array( 'خزان', 'خزانات' ) ),
	) as $r ) {
		$ids = zad_home_match_services( $r[3] );
		if ( $ids ) { $rooms[] = array( 'name' => $r[0], 'icon' => in_array( $r[1], zad_icon_keys(), true ) ? $r[1] : 'home', 'desc' => $r[2], 'services' => $ids ); }
	}

	$d = array(
		'zad_hero_badge'    => 'تنظيف · مكافحة حشرات · نقل أثاث',
		'zad_hero_title'    => 'زاد السعودية: خدمات منزلية ومنشآت بضمان مكتوب',
		'zad_hero_sub'      => 'معاينة مجانية قبل أي عمل، وسعر واضح قبل التنفيذ، وفريق يصلك في ' . ( $cities ? implode( '، ', array_slice( $cities, 0, 4 ) ) . ' وغيرها' : 'مدن المملكة' ) . '.',
		'zad_hero_points'   => "معاينة مجانية قبل أي التزام\nسعر واضح قبل بدء العمل\nضمان مكتوب على التنفيذ\n" . $hours,
		'zad_stats'         => $stats,
		'zad_highlights'    => array(
			array( 'icon' => 'bolt', 't' => 'معاينة مجانية', 'd' => 'قبل أي التزام' ),
			array( 'icon' => 'badge', 't' => 'ضمان مكتوب', 'd' => 'مدته وشروطه واضحة' ),
			array( 'icon' => 'check', 't' => 'سعر قبل التنفيذ', 'd' => 'بلا بنود مفاجئة' ),
			array( 'icon' => 'clock', 't' => $hours, 'd' => 'نستقبل طلبك' ),
		),
		'zad_about_eyebrow' => 'من نحن',
		'zad_about_title'   => 'شركة زاد السعودية للصيانة والنظافة',
		'zad_about_text'    => (string) $b['description'],
		'zad_about_points'  => "معاينة مجانية وسعر واضح\nضمان مكتوب على الخدمة\nتغطية لمدن متعددة\nمتابعة بعد التنفيذ",
		'memo_sec4_grp'     => array(
			array( 'memo_sec4_grp_h' => 'نعاين قبل أن نسعّر', 'memo_sec4_grp_p' => 'نفحص الحالة أولاً، ثم نقترح الخطة والسعر، فلا تدفع على تخمين.' ),
			array( 'memo_sec4_grp_h' => 'سعر واضح', 'memo_sec4_grp_p' => 'نخبرك بالتكلفة قبل بدء العمل، ولا نضيف بنداً بعد الاتفاق عليه.' ),
			array( 'memo_sec4_grp_h' => 'ضمان مكتوب', 'memo_sec4_grp_p' => 'نكتب لك مدة الضمان وما يشمله قبل التنفيذ.' ),
			array( 'memo_sec4_grp_h' => 'تنفيذ ومتابعة', 'memo_sec4_grp_p' => 'نلتزم بالموعد، ونعود للمتابعة عند اللزوم ضمن شروط الضمان.' ),
		),
		'zad_rooms'         => $rooms,
		'zad_process'       => array(
			array( 't' => 'تواصل معنا', 'd' => 'اتصال أو واتساب أو نموذج الطلب، وتخبرنا بالمشكلة.' ),
			array( 't' => 'معاينة مجانية', 'd' => 'نفحص المكان ونحدد الخدمة المناسبة.' ),
			array( 't' => 'سعر وخطة', 'd' => 'نعرض عليك السعر والخطة قبل أن نبدأ.' ),
			array( 't' => 'تنفيذ ومتابعة', 'd' => 'ننفذ في الموعد المتفق عليه، ونتابع معك بعد الخدمة.' ),
		),
		'zad_home_safety_title' => 'نراعي أسرتك في كل خطوة',
		'zad_home_safety'   => array(
			array( 't' => 'نشرح قبل أن نبدأ', 'd' => 'تعرف ما سنستخدمه ولماذا قبل بدء العمل.' ),
			array( 't' => 'أطفال وحيوانات أليفة', 'd' => 'تخبرنا بوجودهم لنختار الطريقة والاحتياطات المناسبة.' ),
			array( 't' => 'مدة العودة', 'd' => 'نحدد لك متى يمكن العودة للمكان بعد انتهاء العمل.' ),
		),
		'zad_guarantee_title' => 'ضمان مكتوب على الخدمة',
		'zad_guarantee_text'  => 'نكتب لك مدة الضمان وما يشمله قبل بدء العمل، ونعود لزيارة المتابعة عند اللزوم ضمن شروطه.',
		'zad_faq'           => array(
			array( 'q' => 'هل المعاينة مجانية؟', 'a' => 'نعم، المعاينة قبل أي عمل مجانية وبدون التزام.' ),
			array( 'q' => 'كيف أطلب الخدمة؟', 'a' => 'اتصل بنا أو راسلنا واتساب أو املأ نموذج الطلب، وسنتواصل معك لتحديد موعد المعاينة.' ),
			array( 'q' => 'هل أعرف السعر قبل التنفيذ؟', 'a' => 'نعم، نخبرك بالسعر قبل بدء العمل، ولا نضيف بنداً بعد الاتفاق عليه.' ),
			array( 'q' => 'هل يوجد ضمان؟', 'a' => 'نعم، ضمان مكتوب تُحدَّد مدته وما يشمله قبل التنفيذ.' ),
			array( 'q' => 'ما ساعات العمل؟', 'a' => $hours . '.' ),
			array( 'q' => 'ما المدن التي تخدمونها؟', 'a' => $cities ? implode( '، ', $cities ) . '.' : 'نخدم عدة مدن في المملكة؛ تواصل معنا لتأكيد منطقتك.' ),
		),
	);
	return $d;
}

/** Admin-only sample data, always labelled as such. */
function zad_home_preview_defaults() {
	return array(
		'zad_testimonials' => array(
			array( 'name' => 'مثال: عميل', 'city' => 'نموذج للمعاينة', 'text' => 'هذا نص نموذجي لتجربة شكل آراء العملاء. استبدله بآراء حقيقية من عملائك.', 'rating' => 5 ),
			array( 'name' => 'مثال: عميلة', 'city' => 'نموذج للمعاينة', 'text' => 'نموذج ثانٍ يوضح كيف يتحرك الشريط بين الآراء.', 'rating' => 5 ),
			array( 'name' => 'مثال: منشأة', 'city' => 'نموذج للمعاينة', 'text' => 'نموذج ثالث. لا تُنشر هذه النصوص للزوار.', 'rating' => 5 ),
		),
		'zad_clients' => array(
			array( 'name' => 'اسم عميل (مثال)', 'note' => 'نموذج للمعاينة' ),
			array( 'name' => 'اسم منشأة (مثال)', 'note' => 'نموذج للمعاينة' ),
			array( 'name' => 'اسم جهة (مثال)', 'note' => 'نموذج للمعاينة' ),
		),
	);
}

/** Value for a home option key when nothing is saved (null = no default). Front-end only. */
function zad_home_default( $key ) {
	static $keys = array( 'zad_hero_badge', 'zad_hero_title', 'zad_hero_sub', 'zad_hero_points', 'zad_stats', 'zad_highlights', 'zad_about_eyebrow', 'zad_about_title', 'zad_about_text', 'zad_about_points', 'memo_sec4_grp', 'zad_rooms', 'zad_process', 'zad_home_safety_title', 'zad_home_safety', 'zad_guarantee_title', 'zad_guarantee_text', 'zad_faq', 'zad_testimonials', 'zad_clients' );
	if ( ! in_array( $key, $keys, true ) || is_admin() || ! is_front_page() ) { // key check first: the defaults themselves read other options
		return null;
	}
	$d = zad_home_defaults();
	if ( isset( $d[ $key ] ) && ! ( is_array( $d[ $key ] ) && ! $d[ $key ] ) && '' !== $d[ $key ] ) {
		return $d[ $key ];
	}
	if ( zad_home_preview() ) {
		$p = zad_home_preview_defaults();
		if ( isset( $p[ $key ] ) ) { return $p[ $key ]; }
	}
	return null;
}

add_action( 'wp_body_open', function () {
	if ( ! zad_home_preview() ) { return; }
	echo '<div style="background:#fff3cd;color:#664d03;padding:10px 16px;text-align:center;font-weight:700;border-bottom:1px solid #ffe69c">معاينة للمدير فقط: تظهر آراء وعملاء «نموذجية» لتتخيّل الشكل النهائي، ولا تُعرض للزوار. أقسام «قبل/بعد» و«الفيديو» تحتاج صوراً ورابطاً حقيقيين من الإعدادات.</div>';
} );
