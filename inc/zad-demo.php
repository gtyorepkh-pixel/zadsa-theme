<?php defined( 'ABSPATH' ) || exit;
/**
 * Tools → "بيانات تجريبية Zad": 2 full services, 1 FAQ, 1 blog post (no images),
 * categories/areas, menus, about/contact pages. Everything created is marked so it can be deleted.
 */

add_action( 'admin_menu', function () {
	add_management_page( 'بيانات تجريبية', 'بيانات تجريبية Zad', 'manage_options', 'zad-demo', 'zad_demo_page' );
} );

function zad_demo_page() {
	echo '<div class="wrap"><h1>بيانات تجريبية</h1>';
	if ( isset( $_POST['zad_demo_go'] ) && check_admin_referer( 'zad_demo' ) && current_user_can( 'manage_options' ) ) {
		$n = zad_demo_import();
		flush_rewrite_rules();
		echo '<div class="notice notice-success"><p>تم إنشاء ' . (int) $n . ' عناصر تجريبية.</p><p>';
		foreach ( get_posts( array( 'post_type' => 'zad_service', 'meta_key' => '_zad_demo', 'numberposts' => 5 ) ) as $p ) {
			echo '<a class="button button-primary" target="_blank" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $p->post_title ) . '</a> ';
		}
		foreach ( get_posts( array( 'post_type' => array( 'zad_faq', 'post' ), 'meta_key' => '_zad_demo', 'numberposts' => 5 ) ) as $p ) {
			echo '<a class="button" target="_blank" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $p->post_title ) . '</a> ';
		}
		echo '<a class="button" target="_blank" href="' . esc_url( home_url( '/' ) ) . '">الرئيسية</a></p></div>';
	}
	if ( isset( $_POST['zad_demo_del'] ) && check_admin_referer( 'zad_demo' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-warning"><p>تم حذف ' . (int) zad_demo_delete() . ' عنصراً تجريبياً.</p></div>';
	}
	echo '<p>ينشئ خدمتين مكتملتين (بكل الأقسام) وسؤالاً ومقالاً، <strong>بدون صور</strong> لتضيف صورك. الصور تُضاف من: الصورة البارزة، ومعرض الصور، وقبل/بعد داخل شاشة تحرير الخدمة.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'zad_demo' );
	echo '<p><button class="button button-primary" name="zad_demo_go" value="1">إنشاء البيانات التجريبية</button> ';
	echo '<button class="button" name="zad_demo_del" value="1" onclick="return confirm(\'حذف كل العناصر التجريبية؟\');">حذف البيانات التجريبية</button></p></form></div>';
}

/** Delete everything the importer made (marked, or created by earlier versions by title). */
function zad_demo_delete() {
	$n      = 0;
	$titles = array( 'شركة رش مبيدات بالرياض', 'شركة تنظيف مسابح بالرياض', 'كشف تسربات المياه بالرياض', 'كم يدوم أثر الرش الوقائي؟', 'ماذا أغطّي قبل الرش؟', 'هل الرش الضبابي يضر النباتات؟', 'كيف أتحقق من ترخيص شركة المكافحة؟', 'ما الفرق بين الرش والطعم؟' );
	$ids    = get_posts( array( 'post_type' => array( 'zad_service', 'zad_faq', 'post' ), 'post_status' => 'any', 'meta_key' => '_zad_demo', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $titles as $t ) {
		foreach ( array( 'zad_service', 'zad_faq' ) as $pt ) {
			$p = get_page_by_title( $t, OBJECT, $pt );
			if ( $p ) { $ids[] = $p->ID; }
		}
	}
	foreach ( array_unique( $ids ) as $id ) {
		if ( wp_delete_post( $id, true ) ) { $n++; }
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_zad_demo_img', 'numberposts' => -1, 'fields' => 'ids' ) ) as $a ) {
		wp_delete_attachment( $a, true );
		$n++;
	}
	return $n;
}

/** @return int term id. */
function zad_demo_term( $name, $tax, $parent = 0 ) {
	$t = term_exists( $name, $tax );
	if ( ! $t ) {
		$t = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	}
	return is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
}


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
	$cat_ids = array();
	foreach ( array( 'مكافحة الحشرات' => 'bug', 'النظافة والتعقيم' => 'sparkle' ) as $name => $icon ) {
		$id = zad_demo_term( $name, 'service_cat' );
		if ( $id ) { update_term_meta( $id, 'zad_icon', $icon ); $cat_ids[ $name ] = $id; }
	}
	$area_ids = array();
	foreach ( array( 'الرياض', 'جدة', 'الدمام' ) as $a ) { $area_ids[] = zad_demo_term( $a, 'service_area' ); }
	foreach ( array( 'الملقا', 'النرجس', 'حطين', 'الياسمين', 'العارض' ) as $d ) { $area_ids[] = zad_demo_term( $d, 'service_area', $area_ids[0] ); }

	$stats = array( array( 'n' => '+13', 'l' => 'سنة خبرة' ), array( 'n' => '+15,000', 'l' => 'عميل راضٍ' ), array( 'n' => '24/7', 'l' => 'استقبال الطلبات' ), array( 'n' => '12', 'l' => 'فني متخصص' ) );
	$steps = array(
		array( 't' => 'التواصل والمعاينة', 'd' => 'تتواصل معنا ونحدد الحالة والمساحة. || الحالة، المساحة، الموعد' ),
		array( 't' => 'تحديد الحل والسعر', 'd' => 'نقترح الأنسب لك بسعر واضح. || سعر واضح، بلا رسوم مخفية' ),
		array( 't' => 'التنفيذ', 'd' => 'يصل الفني بالمعدات والمواد المناسبة. || معدات، مواد آمنة' ),
		array( 't' => 'الفحص والضمان', 'd' => 'نفحص النتيجة ونسلّمك الضمان المكتوب. || فحص نهائي، ضمان' ),
	);
	$faq_common = array(
		array( 'q' => 'هل المعاينة مجانية؟', 'a' => 'نعم، المعاينة والتسعير مجانيان ويُحدَّد السعر النهائي قبل البدء.' ),
		array( 'q' => 'هل هناك ضمان؟', 'a' => 'نعم، ضمان مكتوب، وإذا عادت المشكلة خلال مدة الضمان نعالجها مجاناً.' ),
		array( 'q' => 'متى يصل الفني؟', 'a' => 'غالباً في نفس اليوم عند التواصل المبكر.' ),
	);

	$services = array(
		array(
			'title' => 'شركة رش مبيدات بالرياض', 'cat' => 'مكافحة الحشرات', 'icon' => 'bug', 'tag' => 'رش محيطي ووقائي بمواد مرخصة — موجّه لا عشوائي',
			'badge' => 'الأكثر طلباً', 'price' => 150, 'warranty' => 'ضمان مكتوب', 'duration' => '30–90 دقيقة', 'response' => 'نفس اليوم', 'rating' => 4.9, 'reviews' => 320,
			'content' => '<p>خدمة رش المبيدات تناسب الوقاية العامة ومعالجة الإصابات المنتشرة على أكثر من نوع من الحشرات في وقت واحد. نبدأ دائماً بمعاينة تحدد نوع الإصابة ومصادرها قبل الرش.</p><h3>متى يكون الرش أفضل من الطعم؟</h3><p>حين تكون الإصابة منتشرة في أكثر من غرفة، أو توجد رطوبة عالية، وفي المعالجة الخارجية التي تمنع دخول الحشرات.</p><h3>رش وقائي دوري</h3><p>الرش الوقائي الدوري يمنع الإصابة قبل حدوثها، خاصة للمنازل ذات الحدائق.</p>',
			'features' => "معاينة وتحديد الإصابة أولاً\nمبيدات مبطّنة طويلة الأثر\nرش محيطي خارجي\nرش داخلي موضعي\nإعادة مجانية خلال الضمان",
			'why' => array( array( 't' => 'مبيدات طويلة الأثر', 'd' => 'تثبت على الأسطح وتقاوم الرطوبة.' ), array( 't' => 'رش موجّه', 'd' => 'نركز على المسارات ونقاط الدخول.' ), array( 't' => 'معالجة محيطية', 'd' => 'تمنع دخول الحشرات من الخارج.' ), array( 't' => 'جدولة موسمية', 'd' => 'نكرر الرش الوقائي حسب الموسم.' ) ),
			'subs' => array( array( 't' => 'رش وقائي دوري', 'd' => 'للمنازل ذات الحدائق.' ), array( 't' => 'رش علاجي', 'd' => 'للإصابات المنتشرة.' ), array( 't' => 'رش المطاعم والمنشآت', 'd' => 'بمعايير صحية.' ) ),
			'tools' => array( array( 't' => 'مبيدات مرخصة', 'd' => 'مسجلة في الهيئة العامة للغذاء والدواء.' ), array( 't' => 'معدات رش احترافية', 'd' => 'توزيع دقيق وآمن.' ), array( 't' => 'ملابس وقاية', 'd' => 'أمان الفني والسكان.' ) ),
			'signs' => array( array( 't' => 'حديقة أو أرض فضاء ملاصقة', 'd' => 'أكثر عرضة لدخول الحشرات.' ), array( 't' => 'حشرات في أكثر من غرفة', 'd' => 'يدل على إصابة منتشرة.' ), array( 't' => 'رطوبة عالية', 'd' => 'تذيب الطعوم والمبيد المبطّن يقاومها.' ), array( 't' => 'نشاط موسمي', 'd' => 'مع دخول الصيف أو تقلب الطقس.' ) ),
			'harms' => array( array( 't' => 'دخول متكرر', 'd' => 'بلا حاجز محيطي تتكرر الإصابة.' ), array( 't' => 'تلويث الطعام', 'd' => 'الحشرات الزاحفة تنقل الجراثيم.' ), array( 't' => 'انتشار لغرف أخرى', 'd' => 'يصعّب المعالجة لاحقاً.' ) ),
			'safety' => array( array( 't' => 'آمن بعد الجفاف', 'd' => 'نلتزم بمدة قبل العودة عند الحاجة.' ), array( 't' => 'مواد مرخصة SFDA', 'd' => 'مسجلة ومصنفة عالمياً.' ), array( 't' => 'آمن مع الحيوانات الأليفة', 'd' => 'نراعي القطط والكلاب وأحواض السمك.' ) ),
			'after' => "تهوية المكان بعد المعالجة\nإبعاد الأطفال والحيوانات عن الأسطح حتى الجفاف\nمسح الأسطح الملامسة للطعام قبل استخدامها",
			'factors' => array( array( 't' => 'نوع الآفة', 'd' => 'تختلف طريقة المعالجة والمواد.' ), array( 't' => 'مساحة المكان', 'd' => 'كل فئة مساحة لها سعر.' ), array( 't' => 'مرة واحدة أم دوري', 'd' => 'العقود الدورية أوفر.' ) ),
			'prices' => "شقة | رش وقائي | 150 ريال | مناسب للوقاية الدورية | ضمان شهر\nشقة | رش علاجي | 250 ريال | للإصابات المنتشرة | ضمان 3 أشهر\nفيلا | رش وقائي | 300 ريال | محيط وحديقة | ضمان شهر\nفيلا | رش علاجي | 450 ريال | داخلي وخارجي | ضمان 3 أشهر",
			'price_note' => 'عند التعاقد السنوي يحصل العميل على خصم شهرين مجاناً.',
			'packages' => "أساسية | 150 ريال | معاينة؛ رش داخلي؛ ضمان شهر\nشاملة | 300 ريال | معاينة؛ رش محيطي وداخلي؛ ضمان 3 أشهر\nدورية | من 120 ريال شهرياً | زيارات موسمية؛ أولوية في الحجز؛ ضمان مستمر",
			'warrantyrows' => array( array( 't' => 'إعادة مجانية خلال الضمان', 'd' => 'إن عادت الإصابة نعود ونعالج مجاناً.' ), array( 't' => 'ضمان مكتوب', 'd' => 'نحدد مدة الضمان كتابة قبل البدء.' ) ),
			'spec' => "المواد المستخدمة | مبيدات مبطّنة مرخصة SFDA\nمدة الفعالية | أثر وقائي يمتد أسابيع",
			'faq' => array_merge( array( array( 'q' => 'ما الفرق بين الرش والطعم؟', 'a' => 'الرش للإصابات المنتشرة والرطوبة والمعالجة الخارجية، والطعم للبؤر المحصورة.' ) ), $faq_common ),
		),
		array(
			'title' => 'شركة تنظيف مسابح بالرياض', 'cat' => 'النظافة والتعقيم', 'icon' => 'drop', 'tag' => 'تكنيس بالفاكيوم بدون تفريغ المياه',
			'badge' => 'جديد', 'price' => 150, 'warranty' => 'ضمان جودة', 'duration' => '2 – 4 ساعات', 'response' => 'معاينة نفس اليوم', 'rating' => 4.8, 'reviews' => 210,
			'content' => '<p>نوفر تنظيف المسابح بطريقتين حسب الحالة: تكنيس بالمكنسة الخاصة بدون تفريغ المياه، أو تنظيف عميق شامل للأرضيات والجدران مع التعقيم.</p><h3>لماذا التكنيس بدل التفريغ؟</h3><p>التفريغ الكامل يهدر آلاف اللترات ويحتاج وقتاً لإعادة الملء والموازنة، بينما يحل التكنيس مشكلة الطحالب والرواسب في أغلب الحالات.</p>',
			'features' => "تكنيس بالفاكيوم بدون تفريغ\nتنظيف عميق شامل\nتعقيم بمواد آمنة\nاستبدال الفلاتر عند الحاجة\nعقود دورية من 4 إلى 8 زيارات",
			'why' => array( array( 't' => 'بدون تفريغ المياه', 'd' => 'توفير الوقت والماء.' ), array( 't' => 'خبرة في الفلاتر', 'd' => 'فنيون مدربون.' ), array( 't' => 'أسعار واضحة', 'd' => 'حسب المساحة.' ) ),
			'subs' => array( array( 't' => 'تكنيس المسبح', 'd' => 'شفط الطحالب والرواسب.' ), array( 't' => 'تنظيف عميق', 'd' => 'تفريغ وغسيل وتعقيم.' ), array( 't' => 'عقد دوري', 'd' => 'من 4 إلى 8 زيارات شهرياً.' ), array( 't' => 'استبدال الفلاتر', 'd' => 'فحص وتنظيف أو استبدال.' ) ),
			'tools' => array( array( 't' => 'ماكينات فاكيوم حديثة', 'd' => 'تنظيف كامل بلا تفريغ.' ), array( 't' => 'مواد تعقيم آمنة', 'd' => 'تحافظ على توازن المياه.' ), array( 't' => 'معدات فحص المياه', 'd' => 'قياس الكلور ودرجة الحموضة.' ) ),
			'signs' => array( array( 't' => 'ماء عكر أو مخضر', 'd' => 'علامة على نمو الطحالب.' ), array( 't' => 'رواسب في القاع', 'd' => 'تحتاج شفطاً وتكنيساً.' ), array( 't' => 'رائحة كلور قوية', 'd' => 'خلل في توازن المياه.' ) ),
			'harms' => array( array( 't' => 'تلف الفلاتر والمضخة', 'd' => 'الأوساخ ترهق المعدات.' ), array( 't' => 'بكتيريا وجلد حساس', 'd' => 'مياه غير معقمة تضر السباحين.' ) ),
			'safety' => array( array( 't' => 'مواد آمنة على البشرة', 'd' => 'تعقيم معتمد لا يؤذي العينين.' ), array( 't' => 'توازن كيميائي', 'd' => 'نضبط الكلور والحموضة بعد التنظيف.' ) ),
			'after' => "انتظر ساعة قبل السباحة بعد التعقيم\nراقب نقاء المياه خلال 24 ساعة",
			'factors' => array( array( 't' => 'مساحة المسبح', 'd' => 'صغير، متوسط، كبير.' ), array( 't' => 'نوع التنظيف', 'd' => 'تكنيس أو تفريغ كامل.' ), array( 't' => 'مرة واحدة أم عقد', 'd' => 'العقد الدوري أوفر.' ) ),
			'prices' => "مسبح صغير | تنظيف عميق شامل | 250 ريال | أرضيات وجدران + تعقيم\nمسبح صغير | تكنيس بالفاكيوم | 150 - 200 ريال | بدون تفريغ المياه\nمسبح متوسط | تنظيف عميق شامل | 300 ريال | أرضيات وجدران + تعقيم\nمسبح كبير | تنظيف عميق شامل | 400 - 450 ريال | أرضيات وجدران + تعقيم\nعقد دوري | مسبح صغير 4-8 زيارات | من 600 ريال شهرياً | تكنيس دوري",
			'price_note' => 'التنظيف المؤقت دون التزام طويل، وأسعار العقود الدورية تُحدَّد بعد المعاينة.',
			'packages' => "تكنيس | 150 ريال | شفط الطحالب؛ بدون تفريغ؛ ضمان زيارة\nشامل | 300 ريال | تفريغ وغسيل؛ تعقيم؛ فحص الفلاتر\nشهري | 600 ريال شهرياً | 4 زيارات؛ أولوية؛ ضمان مستمر",
			'warrantyrows' => array( array( 't' => 'ضمان جودة التنظيف', 'd' => 'إن لم تعجبك النتيجة نعيد الزيارة مجاناً.' ), array( 't' => 'متابعة بعد الخدمة', 'd' => 'نتصل بك للاطمئنان على صفاء المياه.' ) ),
			'spec' => "نوع المسابح | سكيمر وأوفر فلو\nالمواد | معقّمات آمنة معتمدة",
			'faq' => array_merge( array( array( 'q' => 'ما الفرق بين التكنيس والتنظيف العميق؟', 'a' => 'التكنيس بدون تفريغ، والتنظيف العميق يشمل التفريغ وغسيل الأرضيات والجدران.' ) ), $faq_common ),
		),
	);

	$created = 0;
	foreach ( $services as $i => $sv ) {
		if ( get_page_by_title( $sv['title'], OBJECT, 'zad_service' ) ) { continue; }
		$pid = wp_insert_post( array( 'post_type' => 'zad_service', 'post_status' => 'publish', 'post_title' => $sv['title'], 'post_content' => $sv['content'], 'post_excerpt' => $sv['tag'], 'menu_order' => $i ) );
		if ( ! $pid || is_wp_error( $pid ) ) { continue; }
		$created++;
		update_post_meta( $pid, '_zad_demo', '1' );
		wp_set_object_terms( $pid, array( (int) $cat_ids[ $sv['cat'] ] ), 'service_cat' );
		wp_set_object_terms( $pid, array_filter( $area_ids ), 'service_area' );
		$meta = array(
			'tagline' => $sv['tag'], 'icon' => $sv['icon'], 'badge' => $sv['badge'], 'price' => $sv['price'], 'price_unit' => 'ريال', 'warranty' => $sv['warranty'],
			'duration' => $sv['duration'], 'response' => $sv['response'], 'rating' => $sv['rating'], 'reviews' => $sv['reviews'], 'featured' => '1',
			'features' => $sv['features'], 'stats' => $stats, 'why' => $sv['why'], 'subs' => $sv['subs'], 'tools' => $sv['tools'], 'steps' => $steps,
			'factors' => $sv['factors'], 'prices' => $sv['prices'], 'price_note' => $sv['price_note'], 'faq' => $sv['faq'], 'signs' => $sv['signs'], 'harms' => $sv['harms'],
			'safety' => $sv['safety'], 'aftercare' => $sv['after'], 'packages' => $sv['packages'], 'warrantyrows' => $sv['warrantyrows'], 'spec' => $sv['spec'],
		);
		foreach ( $meta as $k => $v ) { update_post_meta( $pid, '_zad_' . $k, $v ); }
	}

	// One FAQ page (linked to the first service).
	$first = get_page_by_title( 'شركة رش مبيدات بالرياض', OBJECT, 'zad_service' );
	$fcat  = zad_demo_term( 'الرش والمبيدات', 'faq_cat' );
	$q     = 'كم يدوم أثر الرش الوقائي؟';
	if ( ! get_page_by_title( $q, OBJECT, 'zad_faq' ) ) {
		$fid = wp_insert_post( array( 'post_type' => 'zad_faq', 'post_status' => 'publish', 'post_title' => $q, 'post_excerpt' => 'يمتد الأثر الوقائي أسابيع، ونحدد موعد الرش التالي حسب الموسم ونوع الإصابة.',
			'post_content' => '<p>المبيدات المبطّنة تثبت على الأسطح وتقاوم الرطوبة، لذلك يطول أثرها مقارنة بالمبيدات العادية.</p><h3>ما الذي يقلّل مدة الأثر؟</h3><p>التنظيف المتكرر للأسطح المعالجة، والرطوبة العالية جداً، وتعرّض المحيط الخارجي لأشعة الشمس المباشرة.</p><h3>متى أعيد الرش؟</h3><p>نجدول المتابعة بعد المعاينة حسب الموسم ونوع الإصابة، وغالباً مرة كل موسم للمنازل ذات الحدائق.</p>' ) );
		if ( $fid && ! is_wp_error( $fid ) ) {
			$created++;
			update_post_meta( $fid, '_zad_demo', '1' );
			if ( $fcat ) { wp_set_object_terms( $fid, array( $fcat ), 'faq_cat' ); }
			if ( $first ) { update_post_meta( $fid, '_zad_faq_services', array( (string) $first->ID ) ); }
		}
	}

	// One blog post.
	$bt = 'تحضير المنزل قبل الرش وماذا تفعل بعده: قائمة عملية';
	if ( ! get_page_by_title( $bt, OBJECT, 'post' ) ) {
		$bid = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $bt, 'post_excerpt' => 'ماذا تُفرغ وتغطّي قبل رش المبيدات، ومتى تعود، وأي الأسطح تُنظَّف وأيها تُترك.',
			'post_content' => '<p>التحضير الجيد قبل الرش يرفع فعالية المعالجة ويحمي أسرتك. هذه قائمة عملية تغطي ما قبل الزيارة وما بعدها.</p><h2>قبل الرش</h2><ul><li>غطِّ الطعام والأواني أو أخرجها من المطبخ.</li><li>أخرج الحيوانات الأليفة وأحواض السمك أو غطِّها.</li><li>أبعد الأطفال عن المكان أثناء التنفيذ.</li><li>أخبر الفني بأماكن ظهور الحشرات.</li></ul><h2>بعد الرش</h2><ul><li>هوِّ المكان حسب توجيه الفني.</li><li>لا تمسح الأسطح المعالَجة مباشرة؛ انتظر حتى الجفاف.</li><li>امسح الأسطح الملامسة للطعام قبل استخدامها.</li></ul><h2>الخطأ الشائع</h2><p>تنظيف الأسطح المعالَجة فور الانتهاء يُلغي أثر المبيد المبطّن. اترك المدة التي يحددها الفني.</p><h2>هل تحتاج مساعدة؟</h2><p>اطلب معاينة مجانية وسنخبرك بالتحضير المناسب لحالتك.</p>' ) );
		if ( $bid && ! is_wp_error( $bid ) ) { $created++; update_post_meta( $bid, '_zad_demo', '1' ); }
	}

	zad_demo_site();
	return $created;
}
