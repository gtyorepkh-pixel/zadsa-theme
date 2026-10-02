<?php defined( 'ABSPATH' ) || exit;
/** Diagnostics: build id, home sections status, one-click home seed (fills only EMPTY options). */

function zad_build_id() {
	$head = MEMO_THEME_DIR . '.git/HEAD';
	if ( is_readable( $head ) ) {
		$h = trim( (string) file_get_contents( $head ) );
		if ( 0 === strpos( $h, 'ref:' ) ) {
			$ref = MEMO_THEME_DIR . '.git/' . trim( substr( $h, 4 ) );
			if ( is_readable( $ref ) ) {
				return substr( trim( (string) file_get_contents( $ref ) ), 0, 7 );
			}
			$packed = MEMO_THEME_DIR . '.git/packed-refs';
			if ( is_readable( $packed ) && preg_match( '/^([0-9a-f]{40}) ' . preg_quote( trim( substr( $h, 4 ) ), '/' ) . '$/m', (string) file_get_contents( $packed ), $m ) ) {
				return substr( $m[1], 0, 7 );
			}
		} else {
			return substr( $h, 0, 7 );
		}
	}
	return ZAD_VERSION;
}

add_action( 'wp_footer', function () {
	if ( ! ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || current_user_can( 'manage_options' ) ) ) {
		return; // do not reveal build/commit to visitors
	}
	echo "\n<!-- Zad Pro build " . esc_html( zad_build_id() ) . " -->\n"; // phpcs:ignore
}, 99 );

add_action( 'admin_menu', function () {
	add_management_page( 'حالة الرئيسية', 'حالة الرئيسية', 'manage_options', 'zad-home', 'zad_home_status_page' );
} );

function zad_home_status_page() {
	echo '<div class="wrap"><h1>حالة الصفحة الرئيسية</h1>';
	if ( isset( $_POST['zad_home_seed'] ) && check_admin_referer( 'zad_home' ) && current_user_can( 'manage_options' ) ) {
		if ( function_exists( 'zad_demo_options' ) ) {
			zad_demo_options();
		}
		delete_transient( 'zad_area_ids' );
		echo '<div class="notice notice-success"><p>تمت تهيئة الإعدادات الفارغة فقط. لم يُستبدل أي شيء كتبتَه، ولم يُنشأ أي محتوى.</p></div>';
	}
	$theme = wp_get_theme();
	echo '<p><strong>الثيم المُفعَّل:</strong> ' . esc_html( $theme->get( 'Name' ) ) . ' — <strong>نسخة الكود (commit):</strong> <code>' . esc_html( zad_build_id() ) . '</code>';
	echo ' — <strong>الرئيسية:</strong> ' . ( 'page' === get_option( 'show_on_front' ) ? 'صفحة ثابتة' : 'آخر المقالات' ) . ' (القالب front-page.php يُطبَّق في الحالتين)</p>';
	if ( 'Zad Pro' !== $theme->get( 'Name' ) ) {
		echo '<div class="notice notice-error"><p>الثيم المفعّل ليس Zad Pro. فعّله من المظهر ← الثيمات.</p></div>';
	}

	$svc = new WP_Query( array( 'post_type' => zad_service_types(), 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$svcn = (int) $svc->found_posts;
	$g    = function ( $k ) { $v = zad_opt( $k ); return ! empty( $v ) && ( ! is_array( $v ) || array_filter( $v ) ); };
	$rows = array(
		array( 'الهيرو', $g( 'zad_hero_title' ), 'إعدادات القالب ← الرئيسية: الواجهة الاحترافية' ),
		array( 'شريط المزايا', $g( 'zad_highlights' ), 'الرئيسية: أقسام إضافية ← شريط المزايا' ),
		array( 'عدّادات الأرقام', $g( 'zad_stats' ), 'الرئيسية: الواجهة الاحترافية ← الأرقام والإنجازات' ),
		array( 'قسم «من نحن»', $g( 'zad_about_title' ), 'الرئيسية: أقسام إضافية ← قسم عنّا' ),
		array( 'الخدمات (تبويبات)', $svcn > 0, 'يلزم وجود خدمات منشورة (' . $svcn . ' حالياً)' ),
		array( 'خريطة المنزل', $g( 'zad_rooms' ), 'الرئيسية: خريطة المنزل والأمان' ),
		array( 'لماذا نحن', $g( 'memo_sec4_grp' ), 'خيارات الثيم القديم: «مميزات» الرئيسية' ),
		array( 'آلية العمل', $g( 'zad_process' ), 'الرئيسية: الواجهة الاحترافية ← كيف نعمل' ),
		array( 'قبل / بعد', $g( 'zad_home_ba' ), 'الرئيسية: أقسام إضافية ← قبل/بعد (يلزم صور)' ),
		array( 'الفيديو', $g( 'zad_home_video' ), 'الرئيسية: أقسام إضافية ← فيديو الرئيسية' ),
		array( 'الأمان', $g( 'zad_home_safety' ), 'الرئيسية: خريطة المنزل والأمان' ),
		array( 'المدن', (bool) get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0, 'number' => 1, 'fields' => 'ids' ) ), 'الخدمات ← المدن والأحياء' ),
		array( 'العملاء', $g( 'zad_clients' ), 'الرئيسية: الواجهة الاحترافية ← عملاؤنا' ),
		array( 'آراء العملاء', $g( 'zad_testimonials' ), 'الرئيسية: الواجهة الاحترافية ← آراء العملاء' ),
		array( 'الأسئلة الشائعة', $g( 'zad_faq' ), 'الرئيسية: الواجهة الاحترافية ← أسئلة شائعة' ),
	);
	echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>القسم</th><th>الحالة</th><th>أين تعبّئه</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . ( $r[1] ? '<span style="color:#12683a">✔ يظهر</span>' : '<span style="color:#a12622">✖ مخفي (لا بيانات)</span>' ) . '</td><td>' . esc_html( $r[2] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<form method="post" style="margin-top:18px">';
	wp_nonce_field( 'zad_home' );
	echo '<p>الزر التالي يملأ <strong>الإعدادات الفارغة فقط</strong> بقيم افتراضية قابلة للتعديل (أرقام ونصوص تجريبية)، ولا ينشئ خدمات أو صفحات.</p>';
	echo '<p><button class="button button-primary" name="zad_home_seed" value="1">تهيئة الرئيسية بقيم افتراضية</button></p></form></div>';
}
