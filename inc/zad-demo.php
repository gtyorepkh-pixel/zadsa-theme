<?php defined( 'ABSPATH' ) || exit;
/** Tools → "بيانات تجريبية Zad": creates demo categories, areas and fully filled services. */

add_action( 'admin_menu', function () {
	add_management_page( 'بيانات تجريبية', 'بيانات تجريبية Zad', 'manage_options', 'zad-demo', 'zad_demo_page' );
} );

function zad_demo_page() {
	echo '<div class="wrap"><h1>بيانات تجريبية</h1>';
	if ( isset( $_POST['zad_demo_go'] ) && check_admin_referer( 'zad_demo' ) && current_user_can( 'manage_options' ) ) {
		$n = zad_demo_import();
		flush_rewrite_rules();
		$sv = get_page_by_title( 'شركة رش مبيدات بالرياض', OBJECT, 'zad_service' );
		$fq = get_posts( array( 'post_type' => 'zad_faq', 'numberposts' => 1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<div class="notice notice-success"><p>تم إنشاء ' . (int) $n . ' خدمات تجريبية.</p><p>';
		if ( $sv ) { echo '<a class="button button-primary" target="_blank" href="' . esc_url( get_permalink( $sv ) ) . '">افتح الخدمة الكاملة</a> '; }
		if ( $fq ) { echo '<a class="button" target="_blank" href="' . esc_url( get_permalink( $fq[0] ) ) . '">افتح سؤالاً كاملاً</a> '; }
		echo '<a class="button" target="_blank" href="' . esc_url( home_url( '/' ) ) . '">الرئيسية</a> <a class="button" target="_blank" href="' . esc_url( get_post_type_archive_link( 'zad_faq' ) ) . '">كل الأسئلة</a></p></div>';
	}
	echo '<p>ينشئ أقساماً ومدناً وثلاث خدمات وأسئلة مكتملة البيانات مع صور بديلة (يلزم GD) لتجربة التصميم كاملاً، ويملأ إعدادات القالب الفارغة فقط. يمكنك حذفها لاحقاً من قائمة الخدمات. لن يُكرَّر الإنشاء إن وُجدت خدمات بنفس العنوان.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'zad_demo' );
	echo '<p><button class="button button-primary" name="zad_demo_go" value="1">إنشاء البيانات التجريبية</button></p></form></div>';
}

function zad_demo_term( $name, $tax, $parent = 0 ) {
	$t = term_exists( $name, $tax );
	if ( ! $t ) {
		$t = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	}
	return is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
}


/** Create a gradient placeholder image in the media library (needs GD). Returns attachment ID or 0. */
function zad_demo_image( $slug, $c1, $c2, $w = 1200, $h = 800 ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$up   = wp_upload_dir();
	$file = trailingslashit( $up['path'] ) . 'zad-demo-' . $slug . '.jpg';
	if ( ! file_exists( $file ) ) {
		$im = imagecreatetruecolor( $w, $h );
		list( $r1, $g1, $b1 ) = sscanf( $c1, '#%02x%02x%02x' );
		list( $r2, $g2, $b2 ) = sscanf( $c2, '#%02x%02x%02x' );
		for ( $y = 0; $y < $h; $y++ ) {
			$t = $y / $h;
			imageline( $im, 0, $y, $w, $y, imagecolorallocate( $im, (int) ( $r1 + ( $r2 - $r1 ) * $t ), (int) ( $g1 + ( $g2 - $g1 ) * $t ), (int) ( $b1 + ( $b2 - $b1 ) * $t ) ) );
		}
		// soft circles for texture
		for ( $i = 0; $i < 6; $i++ ) {
			$col = imagecolorallocatealpha( $im, 255, 255, 255, 115 );
			imagefilledellipse( $im, ( $i * 223 + 90 ) % $w, ( $i * 157 + 120 ) % $h, 180 + $i * 40, 180 + $i * 40, $col );
		}
		imagejpeg( $im, $file, 82 );
		imagedestroy( $im );
	}
	$existing = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_zad_demo_img', 'meta_value' => $slug, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return (int) $existing[0];
	}
	$aid = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'صورة تجريبية ' . $slug, 'post_content' => '', 'post_status' => 'inherit', 'post_excerpt' => 'صورة تجريبية — استبدلها بصورة حقيقية' ), $file );
	if ( ! $aid || is_wp_error( $aid ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $file ) );
	update_post_meta( $aid, '_zad_demo_img', $slug );
	return (int) $aid;
}

/** Fill global theme options that are still empty (never overwrites). */
function zad_demo_options() {
	$o = get_option( '_memo_theme_options' );
	$o = is_array( $o ) ? $o : array();
	$def = array(
		'memopt_phone'    => '0500000000',
		'memopt_whatsapp' => '966500000000',
		'memopt_mail'     => get_option( 'admin_email' ),
		'memopt_address'  => 'الرياض، المملكة العربية السعودية',
		'zad_provider'    => get_bloginfo( 'name' ),
		'zad_since'       => '2013',
		'zad_hero_title'  => 'خدمات منزلية احترافية بضمان حقيقي',
		'zad_clients'     => array( array( 'name' => 'جهة حكومية', 'note' => 'مشاريع صيانة وتشغيل' ), array( 'name' => 'شركة مقاولات', 'note' => 'الرياض' ), array( 'name' => 'مجمع سكني', 'note' => 'جدة' ), array( 'name' => 'مستشفى', 'note' => 'الدمام' ) ),
		'zad_sectors'     => array( array( 'name' => 'المطاعم والأغذية', 'names' => "مطعم النخبة\nمقهى الراحة\nمخبز الحي", 'note' => '' ), array( 'name' => 'الطبي والصيدلاني', 'names' => '', 'note' => '4+ عملاء — أسماء غير معلنة لحساسية القطاع' ), array( 'name' => 'العقارات', 'names' => "شركة الأفق العقارية\nمجمعات النخيل", 'note' => '' ) ),
		'zad_testimonials'=> array( array( 'name' => 'أبو خالد', 'city' => 'الرياض', 'text' => 'خدمة ممتازة والتزام بالموعد، والفني شرح كل خطوة قبل التنفيذ.', 'rating' => 5 ), array( 'name' => 'أم سارة', 'city' => 'جدة', 'text' => 'سعر واضح من البداية وضمان مكتوب، أنصح بهم.', 'rating' => 5 ) ),
		'zad_process'     => array( array( 't' => 'تواصل معنا', 'd' => 'اتصال أو واتساب أو نموذج الطلب.' ), array( 't' => 'معاينة وسعر', 'd' => 'نحدد الحالة ونعطيك سعراً واضحاً.' ), array( 't' => 'تنفيذ', 'd' => 'فني معتمد بمعدات ومواد مرخصة.' ), array( 't' => 'ضمان ومتابعة', 'd' => 'ضمان مكتوب ومتابعة بعد التنفيذ.' ) ),
		'zad_faq'         => array( array( 'q' => 'هل المعاينة مجانية؟', 'a' => 'نعم، المعاينة والتسعير مجانيان.' ) ),
	);
	foreach ( $def as $k => $v ) {
		if ( empty( $o[ $k ] ) ) {
			$o[ $k ] = $v;
		}
	}
	update_option( '_memo_theme_options', $o );
}

/** Find an openly licensed image on Wikimedia Commons and sideload it. Returns attachment ID or 0. */
function zad_demo_open_image( $slug, $query ) {
	$existing = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_zad_demo_img', 'meta_value' => $slug, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return (int) $existing[0];
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$url = add_query_arg( array(
		'action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrnamespace' => 6, 'gsrlimit' => 12,
		'gsrsearch' => 'filetype:bitmap ' . $query, 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata|size|mime', 'iiurlwidth' => 1600,
	), 'https://commons.wikimedia.org/w/api.php' );
	$res = wp_remote_get( $url, array( 'timeout' => 20, 'user-agent' => 'ZadProTheme/3 (demo importer)' ) );
	if ( is_wp_error( $res ) ) {
		return 0;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $data['query']['pages'] ) ) {
		return 0;
	}
	foreach ( $data['query']['pages'] as $pg ) {
		$ii = $pg['imageinfo'][0] ?? null;
		if ( ! $ii || 'image/jpeg' !== ( $ii['mime'] ?? '' ) || ( $ii['width'] ?? 0 ) < 1000 || empty( $ii['thumburl'] ) ) {
			continue;
		}
		$lic = $ii['extmetadata']['LicenseShortName']['value'] ?? '';
		if ( ! preg_match( '/^(CC0|Public domain|PD|CC BY(?!-))/i', $lic ) && ! preg_match( '/^CC BY-SA/i', $lic ) ) {
			continue;
		}
		$tmp = download_url( $ii['thumburl'], 30 );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}
		$artist = wp_strip_all_tags( $ii['extmetadata']['Artist']['value'] ?? 'Wikimedia Commons' );
		$aid    = media_handle_sideload( array( 'name' => 'zad-' . $slug . '.jpg', 'tmp_name' => $tmp ), 0, wp_strip_all_tags( $pg['title'] ?? $slug ) );
		if ( is_wp_error( $aid ) ) {
			@unlink( $tmp ); // phpcs:ignore
			continue;
		}
		wp_update_post( array( 'ID' => $aid, 'post_excerpt' => 'صورة: ' . $artist . ' — ' . $lic . ' — Wikimedia Commons' ) );
		update_post_meta( $aid, '_zad_demo_img', $slug );
		update_post_meta( $aid, '_wp_attachment_image_alt', $query );
		update_post_meta( $aid, '_zad_source', $ii['descriptionurl'] ?? '' );
		return (int) $aid;
	}
	return 0;
}

/** Pages (about, contact), main/footer menus. */
function zad_demo_site() {
	$mk = function ( $title, $tpl, $content ) {
		$p = get_page_by_title( $title, OBJECT, 'page' );
		if ( $p ) {
			return $p->ID;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
		if ( $id && ! is_wp_error( $id ) && $tpl ) {
			update_post_meta( $id, '_wp_page_template', $tpl );
		}
		return $id;
	};
	$about   = $mk( 'من نحن', 'temp/memo-about.php', '' );
	$contact = $mk( 'اتصل بنا', 'temp/memo-contact.php', '' );

	$menu_id = 0;
	$m = wp_get_nav_menu_object( 'القائمة الرئيسية' );
	if ( $m ) {
		return;
	}
	$menu_id = wp_create_nav_menu( 'القائمة الرئيسية' );
	if ( is_wp_error( $menu_id ) ) {
		return;
	}
	$add = function ( $title, $url, $parent = 0, $obj_id = 0, $type = 'custom' ) use ( $menu_id ) {
		$args = array( 'menu-item-title' => $title, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent );
		if ( 'custom' === $type ) {
			$args['menu-item-url']  = $url;
			$args['menu-item-type'] = 'custom';
		} else {
			$args['menu-item-type']      = 'post_type';
			$args['menu-item-object']    = 'page';
			$args['menu-item-object-id'] = $obj_id;
		}
		return wp_update_nav_menu_item( $menu_id, 0, $args );
	};
	$add( 'الرئيسية', home_url( '/' ) );
	$svc = $add( 'الخدمات', get_post_type_archive_link( 'zad_service' ) );
	foreach ( get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => false ) ) as $t ) {
		$add( $t->name, get_term_link( $t ), $svc );
	}
	$add( 'الأسئلة الشائعة', get_post_type_archive_link( 'zad_faq' ) );
	if ( $about ) { $add( 'من نحن', '', 0, $about, 'page' ); }
	if ( $contact ) { $add( 'اتصل بنا', '', 0, $contact, 'page' ); }

	$f = wp_create_nav_menu( 'قائمة الفوتر' );
	if ( ! is_wp_error( $f ) ) {
		foreach ( array( array( 'الرئيسية', home_url( '/' ) ), array( 'الخدمات', get_post_type_archive_link( 'zad_service' ) ), array( 'الأسئلة الشائعة', get_post_type_archive_link( 'zad_faq' ) ) ) as $it ) {
			wp_update_nav_menu_item( $f, 0, array( 'menu-item-title' => $it[0], 'menu-item-url' => $it[1], 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
		}
	}
	$loc = get_theme_mod( 'nav_menu_locations', array() );
	$loc['mainmenu']   = $menu_id;
	if ( ! is_wp_error( $f ) ) { $loc['footermenu'] = $f; }
	set_theme_mod( 'nav_menu_locations', $loc );
}

function zad_demo_import() {
	zad_demo_options();
	$cats = array(
		'مكافحة الحشرات' => 'bug',
		'النظافة والتعقيم' => 'sparkle',
		'المياه والتسربات' => 'drop',
	);
	$cat_ids = array();
	foreach ( $cats as $name => $icon ) {
		$id = zad_demo_term( $name, 'service_cat' );
		if ( $id ) {
			update_term_meta( $id, 'zad_icon', $icon );
			$cat_ids[ $name ] = $id;
		}
	}
	$area_ids = array();
	foreach ( array( 'الرياض', 'جدة', 'الدمام' ) as $a ) {
		$area_ids[] = zad_demo_term( $a, 'service_area' );
	}
	foreach ( array( 'الملقا', 'النرجس', 'حطين', 'الياسمين', 'العارض' ) as $d ) {
		$area_ids[] = zad_demo_term( $d, 'service_area', $area_ids[0] );
	}

	$common_faq = array(
		array( 'q' => 'هل المعاينة مجانية؟', 'a' => 'نعم، المعاينة والتسعير مجانيان ويُحدَّد السعر النهائي قبل البدء.' ),
		array( 'q' => 'هل هناك ضمان؟', 'a' => 'نعم، ضمان مكتوب، وإذا عادت المشكلة خلال مدة الضمان نعالجها مجاناً.' ),
		array( 'q' => 'متى يصل الفني؟', 'a' => 'غالباً في نفس اليوم عند التواصل المبكر.' ),
	);
	$common_steps = array(
		array( 't' => 'التواصل والمعاينة', 'd' => 'تتواصل معنا ونحدد الحالة والمساحة. || الحالة، المساحة، الموعد' ),
		array( 't' => 'تحديد الحل والسعر', 'd' => 'نقترح الأنسب لك بسعر واضح.' ),
		array( 't' => 'التنفيذ', 'd' => 'يصل الفني بالمعدات والمواد المناسبة. || معدات، مواد آمنة' ),
		array( 't' => 'الفحص والضمان', 'd' => 'نفحص النتيجة ونسلّمك الضمان.' ),
	);
	$stats = array( array( 'n' => '+13', 'l' => 'سنة خبرة' ), array( 'n' => '+15,000', 'l' => 'عميل راضٍ' ), array( 'n' => '24/7', 'l' => 'استقبال الطلبات' ), array( 'n' => '12', 'l' => 'فني متخصص' ) );

	$services = array(
		array(
			'title' => 'شركة رش مبيدات بالرياض', 'cat' => 'مكافحة الحشرات', 'icon' => 'bug', 'tag' => 'رش محيطي ووقائي بمواد مرخصة — موجّه لا عشوائي',
			'badge' => 'الأكثر طلباً', 'price' => 150, 'warranty' => 'ضمان مكتوب', 'duration' => '30–90 دقيقة', 'response' => 'نفس اليوم', 'rating' => 4.9, 'reviews' => 320,
			'content' => '<p>خدمة رش المبيدات تناسب الوقاية العامة ومعالجة الإصابات المنتشرة على أكثر من نوع من الحشرات في وقت واحد. نبدأ دائماً بمعاينة تحدد نوع الإصابة ومصادرها قبل الرش.</p><h3>متى يكون الرش أفضل من الطعم؟</h3><p>حين تكون الإصابة منتشرة في أكثر من غرفة، أو توجد رطوبة عالية، وفي المعالجة الخارجية التي تمنع دخول الحشرات.</p>',
			'features' => "معاينة وتحديد الإصابة أولاً\nمبيدات مبطّنة طويلة الأثر\nرش محيطي خارجي\nرش داخلي موضعي\nإعادة مجانية خلال الضمان",
			'why' => array( array( 't' => 'مبيدات طويلة الأثر', 'd' => 'تثبت على الأسطح وتقاوم الرطوبة.' ), array( 't' => 'رش موجّه', 'd' => 'نركز على المسارات ونقاط الدخول.' ), array( 't' => 'معالجة محيطية', 'd' => 'تمنع دخول الحشرات من الخارج.' ), array( 't' => 'جدولة موسمية', 'd' => 'نكرر الرش الوقائي حسب الموسم.' ) ),
			'subs' => array( array( 't' => 'رش وقائي دوري', 'd' => 'للمنازل ذات الحدائق.' ), array( 't' => 'رش علاجي', 'd' => 'للإصابات المنتشرة.' ), array( 't' => 'رش المطاعم والمنشآت', 'd' => 'بمعايير صحية.' ) ),
			'tools' => array( array( 't' => 'مبيدات مرخصة', 'd' => 'مسجلة في الهيئة العامة للغذاء والدواء.' ), array( 't' => 'معدات رش احترافية', 'd' => 'توزيع دقيق وآمن.' ), array( 't' => 'ملابس وقاية', 'd' => 'أمان الفني والسكان.' ) ),
			'signs' => array( array( 't' => 'حديقة أو أرض فضاء ملاصقة', 'd' => 'أكثر عرضة لدخول الحشرات.' ), array( 't' => 'حشرات في أكثر من غرفة', 'd' => 'يدل على إصابة منتشرة.' ), array( 't' => 'رطوبة عالية', 'd' => 'تذيب الطعوم والمبيد المبطّن يقاومها.' ), array( 't' => 'نشاط موسمي', 'd' => 'مع دخول الصيف أو تقلب الطقس.' ) ),
			'harms' => array( array( 't' => 'دخول متكرر', 'd' => 'بلا حاجز محيطي تتكرر الإصابة.' ), array( 't' => 'تلويث الطعام', 'd' => 'الحشرات الزاحفة تنقل الجراثيم.' ), array( 't' => 'انتشار لغرف أخرى', 'd' => 'يصعّب المعالجة لاحقاً.' ) ),
			'safety' => array( array( 't' => 'آمن بعد الجفاف', 'd' => 'نلتزم بمدة قبل العودة عند الحاجة.' ), array( 't' => 'مواد مرخصة SFDA', 'd' => 'مسجلة ومصنفة عالمياً.' ), array( 't' => 'آمن مع الحيوانات الأليفة', 'd' => 'نراعي القطط والكلاب وأحواض السمك.' ) ),
			'after' => "تهوية المكان بعد المعالجة\nإبعاد الأطفال والحيوانات عن الأسطح حتى الجفاف\nمسح الأسطح الملامسة للطعام قبل استخدامها",
			'steps' => $common_steps,
			'factors' => array( array( 't' => 'نوع الآفة', 'd' => 'تختلف طريقة المعالجة والمواد.' ), array( 't' => 'مساحة المكان', 'd' => 'كل فئة مساحة لها سعر.' ), array( 't' => 'مرة واحدة أم دوري', 'd' => 'العقود الدورية أوفر.' ) ),
			'price_note' => 'عند التعاقد السنوي يحصل العميل على خصم شهرين مجاناً.',
			'prices' => "شقة | رش وقائي | 150 ريال | مناسب للوقاية الدورية | ضمان شهر\nشقة | رش علاجي | 250 ريال | للإصابات المنتشرة | ضمان 3 أشهر\nفيلا | رش وقائي | 300 ريال | محيط وحديقة | ضمان شهر\nفيلا | رش علاجي | 450 ريال | داخلي وخارجي | ضمان 3 أشهر",
			'packages' => "أساسية | 150 ريال | معاينة؛ رش داخلي؛ ضمان شهر\nشاملة | 300 ريال | معاينة؛ رش محيطي وداخلي؛ ضمان 3 أشهر\nدورية | من 120 ريال شهرياً | زيارات موسمية؛ أولوية في الحجز؛ ضمان مستمر",
			'warrantyrows' => array( array( 't' => 'إعادة مجانية خلال الضمان', 'd' => 'إن عادت الإصابة نعود ونعالج مجاناً.' ), array( 't' => 'ضمان مكتوب', 'd' => 'نحدد مدة الضمان كتابة قبل البدء.' ) ),
			'spec' => "المواد المستخدمة | مبيدات مبطّنة مرخصة SFDA\nمدة الفعالية | أثر وقائي يمتد أسابيع",
			'faq' => array_merge( array( array( 'q' => 'ما الفرق بين الرش والطعم؟', 'a' => 'الرش للإصابات المنتشرة والرطوبة والمعالجة الخارجية، والطعم للبؤر المحصورة.' ) ), $common_faq ),
		),
		array(
			'title' => 'شركة تنظيف مسابح بالرياض', 'cat' => 'النظافة والتعقيم', 'icon' => 'drop', 'tag' => 'تكنيس بالفاكيوم بدون تفريغ المياه',
			'badge' => '', 'price' => 150, 'warranty' => 'ضمان جودة', 'duration' => '2 – 4 ساعات', 'response' => 'معاينة نفس اليوم', 'rating' => 4.8, 'reviews' => 210,
			'content' => '<p>نوفر تنظيف المسابح بطريقتين حسب الحالة: تكنيس بالمكنسة الخاصة بدون تفريغ المياه، أو تنظيف عميق شامل للأرضيات والجدران مع التعقيم.</p>',
			'features' => "تكنيس بالفاكيوم بدون تفريغ\nتنظيف عميق شامل\nتعقيم بمواد آمنة\nاستبدال الفلاتر عند الحاجة\nعقود دورية من 4 إلى 8 زيارات",
			'why' => array( array( 't' => 'بدون تفريغ المياه', 'd' => 'توفير الوقت والماء.' ), array( 't' => 'خبرة في الفلاتر', 'd' => 'فنيون مدربون.' ), array( 't' => 'أسعار واضحة', 'd' => 'حسب المساحة.' ) ),
			'subs' => array( array( 't' => 'تكنيس المسبح', 'd' => 'شفط الطحالب والرواسب.' ), array( 't' => 'تنظيف عميق', 'd' => 'تفريغ وغسيل وتعقيم.' ), array( 't' => 'عقد دوري', 'd' => 'من 4 إلى 8 زيارات شهرياً.' ) ),
			'tools' => array( array( 't' => 'ماكينات فاكيوم حديثة', 'd' => 'تنظيف كامل بلا تفريغ.' ), array( 't' => 'مواد تعقيم آمنة', 'd' => 'تحافظ على توازن المياه.' ) ),
			'steps' => $common_steps, 'signs' => array(), 'harms' => array(), 'safety' => array(), 'after' => '',
			'factors' => array( array( 't' => 'مساحة المسبح', 'd' => 'صغير، متوسط، كبير.' ), array( 't' => 'نوع التنظيف', 'd' => 'تكنيس أو تفريغ كامل.' ) ),
			'prices' => "مسبح صغير | تنظيف عميق شامل | 250 ريال\nمسبح صغير | تكنيس | 150 - 200 ريال\nمسبح متوسط | تنظيف عميق شامل | 300 ريال\nمسبح كبير | تنظيف عميق شامل | 400 - 450 ريال\nعقد دوري | مسبح صغير 4-8 زيارات | من 600 ريال شهرياً",
			'packages' => '', 'warrantyrows' => array(), 'spec' => '', 'faq' => $common_faq,
		),
		array(
			'title' => 'كشف تسربات المياه بالرياض', 'cat' => 'المياه والتسربات', 'icon' => 'drop', 'tag' => 'أجهزة حديثة بدون تكسير',
			'badge' => 'جديد', 'price' => 200, 'warranty' => 'ضمان سنة', 'duration' => 'من ساعة إلى 3 ساعات', 'response' => 'خلال ساعتين', 'rating' => 4.9, 'reviews' => 180,
			'content' => '<p>نحدد مصدر التسرب بدقة باستخدام أجهزة حرارية وصوتية دون تكسير عشوائي، ثم نقدم تقريراً واضحاً.</p>',
			'features' => "أجهزة كشف حرارية وصوتية\nبدون تكسير عشوائي\nتقرير مصوّر\nضمان على الإصلاح",
			'why' => array( array( 't' => 'دقة عالية', 'd' => 'نحدد الموضع بدقة.' ), array( 't' => 'بدون تكسير', 'd' => 'نحافظ على الأرضيات.' ) ),
			'subs' => array(), 'tools' => array(), 'steps' => $common_steps, 'signs' => array(), 'harms' => array(), 'safety' => array(), 'after' => '', 'factors' => array(),
			'prices' => "كشف | تسرب داخلي | 200 ريال\nكشف | تسرب مسبح | 350 ريال", 'packages' => '', 'warrantyrows' => array(), 'spec' => '', 'faq' => $common_faq,
		),
	);

	$created = 0;
	foreach ( $services as $i => $sv ) {
		if ( get_page_by_title( $sv['title'], OBJECT, 'zad_service' ) ) {
			continue;
		}
		$pid = wp_insert_post( array(
			'post_type'    => 'zad_service',
			'post_status'  => 'publish',
			'post_title'   => $sv['title'],
			'post_content' => $sv['content'],
			'post_excerpt' => $sv['tag'],
			'menu_order'   => $i,
		) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			continue;
		}
		$created++;
		wp_set_object_terms( $pid, array( (int) $cat_ids[ $sv['cat'] ] ), 'service_cat' );
		wp_set_object_terms( $pid, array_filter( $area_ids ), 'service_area' );
		$meta = array(
			'tagline' => $sv['tag'], 'icon' => $sv['icon'], 'badge' => $sv['badge'], 'price' => $sv['price'], 'price_unit' => 'ريال', 'warranty' => $sv['warranty'],
			'duration' => $sv['duration'], 'response' => $sv['response'], 'rating' => $sv['rating'], 'reviews' => $sv['reviews'], 'featured' => '1',
			'features' => $sv['features'], 'stats' => $stats, 'why' => $sv['why'], 'subs' => $sv['subs'], 'tools' => $sv['tools'], 'steps' => $sv['steps'],
			'factors' => $sv['factors'], 'prices' => $sv['prices'], 'faq' => $sv['faq'], 'signs' => $sv['signs'], 'harms' => $sv['harms'], 'safety' => $sv['safety'],
			'aftercare' => $sv['after'], 'price_note' => $sv['price_note'] ?? '', 'packages' => $sv['packages'], 'warrantyrows' => $sv['warrantyrows'], 'spec' => $sv['spec'],
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $pid, '_zad_' . $k, $v );
		}
		// Placeholder images so every section of the design is visible.
		$pal = array( array( '#0b2e3a', '#1c6a7d' ), array( '#8a5a00', '#f2b134' ), array( '#0d7f70', '#5fd1bf' ), array( '#3a4a6b', '#8ea6d9' ), array( '#6b3a3a', '#d98e8e' ), array( '#35523a', '#8fd99b' ) );
		$qs   = array( 'pest control spraying', 'swimming pool cleaning', 'plumber leak detection' );
		$hero = zad_demo_open_image( 'hero-' . $i, $qs[ $i % 3 ] ) ?: zad_demo_image( 'hero-' . $i, $pal[ $i % 6 ][0], $pal[ $i % 6 ][1], 1600, 900 );
		if ( $hero ) {
			set_post_thumbnail( $pid, $hero );
		}
		if ( 0 === $i ) {
			$g = array();
			foreach ( array( 'work-1' => 2, 'work-2' => 3, 'work-3' => 4, 'work-4' => 5 ) as $slug => $pi ) {
				$aid = zad_demo_open_image( $slug, array( 'work-1' => 'pest control technician', 'work-2' => 'insecticide spraying', 'work-3' => 'cockroach', 'work-4' => 'house exterior garden' )[ $slug ] ) ?: zad_demo_image( $slug, $pal[ $pi ][0], $pal[ $pi ][1], 1200, 900 );
				if ( $aid ) { $g[] = $aid; }
			}
			update_post_meta( $pid, '_zad_gallery', implode( ',', $g ) );
			$ba = array();
			foreach ( array( 'before-1' => array( '#5b5b52', '#8d8d7e' ), 'after-1' => array( '#0d7f70', '#6fe0cd' ), 'before-2' => array( '#6a5b4b', '#a89478' ), 'after-2' => array( '#2e6aa8', '#8cc2f2' ) ) as $slug => $cc ) {
				$aid = zad_demo_image( $slug, $cc[0], $cc[1], 1200, 900 );
				if ( $aid ) { $ba[] = $aid; }
			}
			update_post_meta( $pid, '_zad_ba', implode( ',', $ba ) );
			update_post_meta( $pid, '_zad_ba_text', "قبل وبعد: منزل في حي الملقا | رش محيطي ووقائي لفيلا مع حديقة\nقبل وبعد: مطعم في حطين | معالجة داخلية موضعية" );
		}
	}
	// Demo FAQ pages linked to the first service.
	$first = get_page_by_title( 'شركة رش مبيدات بالرياض', OBJECT, 'zad_service' );
	$fcat  = zad_demo_term( 'الرش والمبيدات', 'faq_cat' );
	$faqs  = array(
		'كم يدوم أثر الرش الوقائي؟' => array( 'يمتد الأثر الوقائي أسابيع، ونحدد موعد الرش التالي حسب الموسم ونوع الإصابة.', '<p>المبيدات المبطّنة تثبت على الأسطح وتقاوم الرطوبة، لذلك يطول أثرها مقارنة بالمبيدات العادية. نجدول المتابعة بعد المعاينة.</p>' ),
		'ماذا أغطّي قبل الرش؟' => array( 'غطِّ الطعام والأواني وأخرج الحيوانات الأليفة وأحواض السمك.', '<p>نرسل لك قائمة التحضير قبل الزيارة، ونوضح متى يمكن العودة للمكان بعد الجفاف.</p>' ),
		'هل الرش الضبابي يضر النباتات؟' => array( 'يُوجَّه بعيداً عن النباتات الحساسة وبتركيز مناسب، ونحدد ذلك بعد المعاينة.', '<p>الفوغ يناسب المساحات الكبيرة والمنشآت، أما النباتات فنحميها أو نستبدله برش موضعي.</p>' ),
		'كيف أتحقق من ترخيص شركة المكافحة؟' => array( 'اطلب السجل التجاري ورخصة المبيدات وتحقق منها من الجهات الرسمية.', '<p>الشركة الموثوقة تعرض بياناتها بوضوح وتقدم عقداً وضماناً مكتوبين.</p>' ),
	);
	foreach ( $faqs as $q => $a ) {
		if ( get_page_by_title( $q, OBJECT, 'zad_faq' ) ) {
			continue;
		}
		$fid = wp_insert_post( array( 'post_type' => 'zad_faq', 'post_status' => 'publish', 'post_title' => $q, 'post_excerpt' => $a[0], 'post_content' => $a[1] ) );
		if ( $fid && ! is_wp_error( $fid ) ) {
			if ( $fcat ) { wp_set_object_terms( $fid, array( $fcat ), 'faq_cat' ); }
			if ( $first ) { update_post_meta( $fid, '_zad_faq_services', array( (string) $first->ID ) ); }
		}
	}
	zad_demo_site();
	return $created;
}
