<?php defined( 'ABSPATH' ) || exit;
/**
 * Company/Service/Article schema engine (formerly the mu-plugin "Zad Schema").
 *
 * Self-contained, so the mu-plugin can be removed. Function prefix: zsc_.
 * Reads the old `_zad_sc_*` post meta first (nothing is lost), then falls back to the
 * theme's own service fields, then to automatic detection from the page title/URL.
 * zad_sc_settings / zad_sc_page_data / zad_sc_types are provided as thin wrappers when the
 * mu-plugin is gone, because zad-cleanup.php (llms.txt) calls them.
 */

/* ---------- 1. Company data (defaults = the validated values; filter 'zsc_settings') ---------- */

function zsc_defaults() {
	return array(
		'authors'  => array(
			'mahmoud' => array(
				'name'        => 'محمود القحطاني',
				'job_title'   => 'محرر المحتوى الفني',
				'description' => 'محرر المحتوى الفني في زاد السعودية، حاصل على ماجستير إدارة أعمال، ويتمتع بخبرة عملية وميدانية تتجاوز 12 عامًا في متابعة خدمات التنظيف ومكافحة الحشرات ونقل وتخزين العفش. يعتمد في إعداد المحتوى على زيارات مواقع التنفيذ، وملاحظات فرق العمل، وأسئلة العملاء الفعلية.',
				'credential'  => 'ماجستير إدارة الأعمال',
				'knows_about' => array( 'خدمات التنظيف', 'مكافحة الحشرات', 'نقل الأثاث', 'تخزين الأثاث' ),
				'same_as'     => array(),
				'image'       => '',
			),
		),
		'business' => array(
			'name'          => 'شركة زاد السعودية للصيانة والنظافة',
			'legal_name'    => 'زاد السعودية للصيانة والنظافة',
			'alternate'     => 'زاد السعودية',
			'description'   => 'شركة زاد السعودية تقدم خدمات تنظيف المنازل والخزانات والكنب والمكيفات، ومكافحة الحشرات، ونقل وتخزين الأثاث في الرياض وجدة والدمام والقصيم ونجران.',
			'logo'          => 'https://zadksa.com/wp-content/uploads/2024/05/logo.png',
			'telephone'     => '+966552744437',
			'email'         => 'info@zadksa.com',
			'price_range'   => '',
			'vat'           => '310778555100003',
			'cr'            => '1010769699',
			'street'        => '2851 شارع عبدالملك بن مروان، حي العليا',
			'locality'      => 'الرياض',
			'region'        => 'منطقة الرياض',
			'postal_code'   => '12611',
			'country'       => 'SA',
			'lat'           => 24.774265,
			'lng'           => 46.738586,
			'has_map'       => '',
			'image_license' => 'https://zadksa.com/privacy-policy/',
			'image_acquire' => 'https://zadksa.com/contact/',
			'same_as'       => array( 'https://www.facebook.com/zadksa2', 'https://x.com/zadksa2', 'https://www.instagram.com/zadksa2/', 'https://www.youtube.com/channel/UC5jWpqhaDYs9MO7CMTx-bFA' ),
			'area_served'   => array( array( 'City', 'الرياض' ), array( 'City', 'جدة' ), array( 'City', 'الدمام' ), array( 'City', 'القطيف' ), array( 'AdministrativeArea', 'القصيم' ), array( 'City', 'نجران' ) ),
			'hours'         => array(
				array( array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), '08:00', '22:00' ),
			),
			'knows_about'   => array( 'مكافحة الحشرات', 'تنظيف الخزانات', 'تنظيف وصيانة المكيفات', 'تنظيف الكنب والمفروشات', 'تنظيف المنازل', 'نقل الأثاث', 'تخزين الأثاث', 'جلي البلاط والرخام', 'تسليك المجاري' ),
		),
	);
}


/** Company values used when the matching theme option was never filled (keeps footer/contact/about in step with the schema). */
function zsc_opt_defaults() {
	static $m = null;
	if ( null === $m ) {
		$b = zsc_defaults()['business'];
		$m = array(
			'zad_legal_name' => $b['legal_name'], 'zad_cr' => $b['cr'], 'zad_vat' => $b['vat'],
			'zad_street' => '2851 شارع عبدالملك بن مروان', 'zad_district' => 'حي العليا', 'zad_postal' => $b['postal_code'],
			'zad_lat' => (string) $b['lat'], 'zad_lng' => (string) $b['lng'],
			'memopt_phone' => '0552744437', 'memopt_mail' => $b['email'], 'zad_site_desc' => $b['description'],
			'memopt_fb' => $b['same_as'][0], 'memopt_tw' => $b['same_as'][1], 'memopt_insta' => $b['same_as'][2], 'memopt_yt' => $b['same_as'][3],
		);
	}
	return $m;
}

/** Authors option: one per line  slug | name | job title | credential | topics (; separated) | description */
function zsc_parse_authors( $text, $fallback ) {
	$out = array();
	foreach ( zad_lines( $text ) as $l ) {
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 6, '' );
		if ( '' === $c[0] ) { continue; }
		$out[ $c[0] ] = array( 'name' => $c[1], 'job_title' => $c[2], 'credential' => $c[3], 'knows_about' => array_values( array_filter( array_map( 'trim', preg_split( '/[;؛]/u', $c[4] ) ) ) ), 'description' => $c[5], 'same_as' => array(), 'image' => '' );
	}
	return $out ? $out : $fallback;
}

/** Company/schema settings: values saved in the theme options win; the validated plugin values are the defaults. */
function zsc_settings() {
	static $s = null;
	if ( null !== $s ) {
		return $s;
	}
	$s = zsc_defaults();
	$b = $s['business'];

	$b['name']        = zad_opt( 'zad_org_name', $b['name'] );
	$b['legal_name']  = zad_opt( 'zad_legal_name', $b['legal_name'] );
	$b['alternate']   = zad_opt( 'zad_alt_name', $b['alternate'] );
	$b['description'] = zad_opt( 'zad_site_desc', $b['description'] );
	$logo = zad_opt( 'memopt_logo' );
	if ( is_array( $logo ) && ! empty( $logo['url'] ) ) { $b['logo'] = $logo['url']; }
	$ph = zad_intl_number( zad_opt( 'memopt_phone' ) );
	if ( $ph ) { $b['telephone'] = '+' . $ph; }
	$b['email']       = zad_opt( 'memopt_mail', $b['email'] );
	$pr = zad_opt( 'zad_price_range', '' );
	$b['price_range'] = ( '' !== trim( (string) $pr ) && '100–500 ر.س' !== $pr ) ? trim( (string) $pr ) : ''; // only what is written; the old theme default is not real data, and «$$» is gone
	$b['wikidata']    = trim( (string) zad_opt( 'zad_wikidata', '' ) );
	$b['vat']         = zad_opt( 'zad_vat', $b['vat'] );
	$b['cr']          = zad_opt( 'zad_cr', $b['cr'] );
	$street           = zad_opt( 'zad_street', '' );
	$b['street']      = '' !== $street ? trim( $street . ( zad_opt( 'zad_district' ) ? '، ' . zad_opt( 'zad_district' ) : '' ) ) : $b['street'];
	$b['locality']    = zad_opt( 'zad_city_name', $b['locality'] );
	$b['region']      = zad_opt( 'zad_region', $b['region'] );
	$b['postal_code'] = zad_opt( 'zad_postal', $b['postal_code'] );
	$b['lat']         = (float) zad_opt( 'zad_lat', $b['lat'] );
	$b['lng']         = (float) zad_opt( 'zad_lng', $b['lng'] );
	$b['has_map']     = zad_opt( 'zad_map_url', $b['has_map'] );
	$b['image_license'] = zad_opt( 'zad_img_license', $b['image_license'] );
	$b['image_acquire'] = zad_opt( 'zad_img_acquire', $b['image_acquire'] );

	$same = array();
	foreach ( array( 'memopt_fb', 'memopt_tw', 'memopt_insta', 'memopt_yt', 'zad_linkedin', 'zad_pinterest', 'zad_tiktok', 'zad_snapchat' ) as $k ) {
		$u = zad_opt( $k );
		if ( $u && preg_match( '#^https?://#', $u ) ) { $same[] = $u; }
	}
	if ( $same ) { $b['same_as'] = $same; }

	$areas = array();
	foreach ( zad_lines( zad_opt( 'zad_area_served', '' ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		$areas[] = 2 === count( $c ) ? array( $c[0], $c[1] ) : array( 'City', $c[0] );
	}
	if ( $areas ) { $b['area_served'] = $areas; }

	$hours = array();
	$hs = zad_opt( 'zad_hours_spec', '' );
	if ( false !== strpos( $hs, 'Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday | 08:00 | 22:00' ) && 1 === count( zad_lines( $hs ) ) ) { $hs = ''; } // old theme default
	foreach ( zad_lines( $hs ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		if ( count( $c ) >= 3 ) { $hours[] = array( array_values( array_filter( array_map( 'trim', explode( ',', $c[0] ) ) ) ), $c[1], $c[2] ); }
	}
	if ( $hours ) { $b['hours'] = $hours; }

	$know = array_values( array_filter( zad_lines( zad_opt( 'zad_knows_about', '' ) ) ) );
	if ( $know ) { $b['knows_about'] = $know; }

	$s['business'] = $b;
	$s['authors']  = zsc_parse_authors( zad_opt( 'zad_authors', '' ), $s['authors'] );
	return apply_filters( 'zsc_settings', $s );
}

/* ---------- 2. Automatic detection from title / URL ---------- */

function zsc_cities() {
	return array(
		array( '/الرياض|riyadh/iu', 'الرياض' ), array( '/بجد[ةه]|جد[ةه](?!\p{L})|jeddah/iu', 'جدة' ), array( '/الدمام|dammam/iu', 'الدمام' ),
		array( '/الخبر|khobar/iu', 'الخبر' ), array( '/القطيف|qatif/iu', 'القطيف' ), array( '/الأحساء|الاحساء|ahsa/iu', 'الأحساء' ),
		array( '/الجبيل|jubail/iu', 'الجبيل' ), array( '/بريد[ةه]|buraid|buraydah/iu', 'بريدة' ), array( '/عنيز[ةه]|unaiz/iu', 'عنيزة' ),
		array( '/(?<!\p{L})ب?الرس(?!\p{L})|ar-rass/iu', 'الرس' ), array( '/القصيم|qassim/iu', 'القصيم' ), array( '/الخرج|kharj/iu', 'الخرج' ),
		array( '/نجران|najran/iu', 'نجران' ), array( '/مك[ةه] المكرم|بمك[ةه]|mecca|makkah/iu', 'مكة المكرمة' ), array( '/المدينة المنورة|madinah|medina/iu', 'المدينة المنورة' ),
		array( '/الطائف|taif/iu', 'الطائف' ), array( '/تبوك|tabuk/iu', 'تبوك' ), array( '/حائل|hail/iu', 'حائل' ), array( '/أبها|ابها|abha/iu', 'أبها' ),
		array( '/خميس مشيط|khamis/iu', 'خميس مشيط' ), array( '/جازان|جيزان|jazan|jizan/iu', 'جازان' ),
	);
}

function zsc_types() {
	return array(
		array( '/عزل/u', 'العزل' ),
		array( '/مكافح|حشرات|مبيد|صراصير|صرصور|النمل|البق|الفئران|القوارض|الوزغ|ابادة|إبادة|رش /u', 'مكافحة الحشرات' ),
		array( '/تخزين/u', 'تخزين الأثاث' ), array( '/نقل/u', 'نقل الأثاث' ), array( '/خزان/u', 'تنظيف الخزانات' ),
		array( '/مكيف|فريون|سبليت|سبلت/u', 'تنظيف وصيانة المكيفات' ),
		array( '/كنب|مجالس|موكيت|سجاد|ستائر|مفروشات|مراتب|كراسي/u', 'تنظيف الكنب والمفروشات' ),
		array( '/مساجد|مسجد/u', 'تنظيف المساجد' ), array( '/بلاط|رخام|جلي/u', 'جلي البلاط والرخام' ),
		array( '/مجاري|بيارات|تسليك|انسداد/u', 'تسليك المجاري وشفط البيارات' ), array( '/تسرب/u', 'كشف التسربات' ),
		array( '/مسابح|مسبح/u', 'تنظيف المسابح' ), array( '/واجهات/u', 'تنظيف الواجهات' ), array( '/تنظيف|نظافة|نظاف/u', 'خدمات التنظيف' ),
	);
}

function zsc_clean_name( $title ) {
	$s     = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) ) );
	$parts = preg_split( '/\s*\|\s*|\s+[–—ـ-]\s+/u', $s );
	$s     = isset( $parts[0] ) ? $parts[0] : $s;
	$s     = preg_replace( '/\s*0?5\d{8}.*$/u', '', $s );
	$s     = preg_replace( '/\s+(خصم|بخصم|عرض|عروض|أسعار تبدأ|اسعار تبدأ|بأسعار|باسعار|ارخص|أرخص)(\s.*)?$/u', '', $s );
	$s     = preg_replace( '/\s+(شركة\s+)?زاد السعودية.*$/u', '', $s );
	return trim( $s );
}

function zsc_detect_city( $title, $path ) {
	$path = rawurldecode( (string) $path );
	foreach ( zsc_cities() as $c ) {
		if ( preg_match( $c[0], $path ) ) { return $c[1]; }
	}
	foreach ( zsc_cities() as $c ) {
		if ( preg_match( $c[0], (string) $title ) ) { return $c[1]; }
	}
	return '';
}

function zsc_detect_place( $title ) {
	$title = (string) $title;
	if ( preg_match( '/(شمال|جنوب|شرق|غرب|وسط)\s+(ال?رياض|جد[ةه]|الدمام)/u', $title, $m ) ) {
		return $m[1] . ' ' . $m[2];
	}
	if ( preg_match( '/(?:^|\s)ب?حي\s+([^\s|،,]+)(?:\s+([^\s|،,]+))?/u', $title, $m ) ) {
		$w = $m[1];
		if ( ! empty( $m[2] ) && ! preg_match( '/^(ب|و|في|من|ل)/u', $m[2] ) && ! preg_match( '/\d/', $m[2] ) ) { $w .= ' ' . $m[2]; }
		return 'حي ' . $w;
	}
	return '';
}

function zsc_detect_type( $name, $title, $post_type ) {
	foreach ( array( $name, $title ) as $text ) {
		foreach ( zsc_types() as $t ) {
			if ( preg_match( $t[0], (string) $text ) ) { return $t[1]; }
		}
	}
	$fb = array( 'pest_control' => 'مكافحة الحشرات', 'cleaning' => 'خدمات التنظيف', 'drain_cleaning' => 'تسليك المجاري وشفط البيارات', 'tile_polishing' => 'جلي البلاط والرخام' );
	return $fb[ $post_type ] ?? 'خدمات التنظيف';
}

function zsc_detect_price( $title ) {
	return preg_match( '/(?:تبدأ|يبدأ|تبدا|يبدا)\s*(?:من\s*)?(\d{2,5})\s*(?:ر\.?\s?س|ريال)/u', (string) $title, $m ) ? (float) $m[1] : 0;
}

function zsc_is_service_auto( $title, $post_type ) {
	$title = (string) $title;
	if ( preg_match( '/^(كيف|كيفية|ما |ماذا|متى|لماذا|هل |كم |طريقة|خطوات|دليل|نصائح|أسعار|اسعار)|دليل|خطوات|\?|؟/u', $title ) ) {
		return false;
	}
	if ( 'post' !== $post_type ) {
		return true;
	}
	return '' !== zsc_detect_city( $title, '' ) && (bool) preg_match( '/شركة|خدمة|خدمات|سباك|فني|تنظيف|مكافحة|نقل|تخزين|رش|عزل|تسليك|جلي|كشف|غسيل|تعقيم|صيانة|تركيب/u', $title );
}

/* ---------- 3. Page data ---------- */

function zsc_meta( $post_id, $key ) {
	return get_post_meta( $post_id, '_zad_sc_' . $key, true );
}

function zsc_lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $l ) {
		$l = trim( $l );
		if ( '' !== $l ) { $out[] = $l; }
	}
	return $out;
}

function zsc_num( $v ) {
	$v = str_replace( array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', ',', '،' ), array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '', '' ), (string) $v );
	return is_numeric( $v ) ? (float) $v : 0;
}

function zsc_page_data( $post ) {
	$post  = get_post( $post );
	$title = get_the_title( $post );
	$path  = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );

	$auto = array(
		'is_service' => zsc_is_service_auto( $title, $post->post_type ),
		'name'       => zsc_clean_name( $title ),
		'city'       => zsc_detect_city( $title, $path ),
		'place'      => zsc_detect_place( $title ),
		'price_from' => zsc_detect_price( $title ),
	);
	$auto['type'] = zsc_detect_type( $auto['name'], $title, $post->post_type );

	$desc = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
	if ( '' === $desc || false !== strpos( $desc, '%%' ) ) {
		$desc = (string) get_post_meta( $post->ID, '_zad_seo_desc', true );
	}
	if ( '' === $desc ) {
		$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 35, '…' );
	}
	$auto['description'] = trim( preg_replace( '/\s+/u', ' ', $desc ) );

	$img              = get_the_post_thumbnail_url( $post, 'full' );
	$auto['image_id'] = $img ? (int) get_post_thumbnail_id( $post ) : 0;
	if ( ! $img && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m ) ) { $img = $m[1]; }
	$auto['image'] = $img ? $img : '';

	$mode = zsc_meta( $post->ID, 'mode' );
	$mode = $mode ? $mode : 'auto';
	$pick = function ( $key ) use ( $post, $auto ) {
		$v = zsc_meta( $post->ID, $key );
		return ( '' !== $v && null !== $v && false !== $v ) ? $v : ( $auto[ $key ] ?? '' );
	};

	$places = zsc_lines( zsc_meta( $post->ID, 'places' ) );
	if ( ! $places && $auto['place'] ) { $places = array( $auto['place'] ); }
	$price_from = zsc_num( zsc_meta( $post->ID, 'price_from' ) );
	if ( ! $price_from ) { $price_from = $auto['price_from']; }

	$offers = array();
	foreach ( zsc_lines( zsc_meta( $post->ID, 'offers' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 2 && '' !== $p[0] && zsc_num( $p[1] ) > 0 ) {
			$offers[] = array( 'name' => $p[0], 'price' => zsc_num( $p[1] ), 'unit' => $p[2] ?? '' );
		}
	}

	$d = array(
		'auto'        => $auto,
		'mode'        => $mode,
		'is_service'  => ( 'service' === $mode ) || ( 'auto' === $mode && $auto['is_service'] ),
		'name'        => $pick( 'name' ),
		'type'        => $pick( 'type' ),
		'city'        => $pick( 'city' ),
		'description' => $pick( 'description' ),
		'image'       => $pick( 'image' ),
		'image_id'    => zsc_meta( $post->ID, 'image' ) ? 0 : $auto['image_id'],
		'places'      => $places,
		'price_from'  => $price_from,
		'price_unit'  => (string) zsc_meta( $post->ID, 'price_unit' ),
		'offers'      => $offers,
		'video'       => array(
			'name' => (string) zsc_meta( $post->ID, 'video_name' ), 'desc' => (string) zsc_meta( $post->ID, 'video_desc' ),
			'url' => (string) zsc_meta( $post->ID, 'video_url' ), 'embed' => (string) zsc_meta( $post->ID, 'video_embed' ),
			'thumb' => (string) zsc_meta( $post->ID, 'video_thumb' ), 'date' => (string) zsc_meta( $post->ID, 'video_date' ),
			'duration' => absint( zsc_meta( $post->ID, 'video_duration' ) ),
		),
	);
	return apply_filters( 'zsc_page_data', $d, $post );
}

/* Theme's own fields fill whatever the old `_zad_sc_*` data left empty. */
add_filter( 'zsc_page_data', function ( $d, $post ) {
	$id = $post->ID;
	if ( function_exists( 'zad_is_service' ) && in_array( $post->post_type, zad_service_types(), true ) && 'none' !== $d['mode'] ) {
		$d['is_service'] = ( 'auto' === $d['mode'] ) ? $d['auto']['is_service'] || 'zad_service' === $post->post_type : $d['is_service'];
		if ( 'zad_service' === $post->post_type ) { $d['is_service'] = 'none' !== $d['mode']; }
	}
	$tag = trim( (string) get_post_meta( $id, '_zad_tagline', true ) );
	if ( '' === (string) zsc_meta( $id, 'description' ) && '' !== $tag ) { $d['description'] = $tag; }
	if ( ! $d['offers'] && function_exists( 'zad_parse_prices' ) ) {
		foreach ( zad_price_rows( $id ) as $r ) { // packages, else the old price table
			$k = zad_price_parse( $r['price'] ); // «199 - 300» is a range, never 199300
			$d['offers'][] = array( 'name' => $r['name'], 'price' => 'fixed' === $k['kind'] ? $k['min'] : 0, 'unit' => '', 'kind' => $k['kind'], 'min' => $k['min'], 'max' => $k['max'], 'monthly' => (bool) preg_match( '/شهر/u', $r['price'] ) );
		}
		if ( ! array_filter( wp_list_pluck( $d['offers'], 'kind' ), function ( $x ) { return 'quote' !== $x; } ) ) { $d['offers'] = array(); } // nothing priced at all: no catalog
	}
	if ( ! $d['price_from'] && ! zad_price_rows( $id ) ) { // with price rows the offers above are the page's prices
		$p = (int) get_post_meta( $id, '_zad_price', true );
		if ( $p ) { $d['price_from'] = (float) $p; }
	}
	if ( ! $d['city'] && function_exists( 'zad_current_city' ) && in_array( $post->post_type, zad_service_types(), true ) ) {
		$cc = zad_current_city( $id );
		if ( 'default' !== $cc['source'] ) { $d['city'] = $cc['name']; } // never stamp the site default on a page
	}
	if ( ! $d['places'] || ! $d['city'] ) {
		$terms = get_the_terms( $id, 'service_area' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 0 === (int) $t->parent ) { if ( ! $d['city'] ) { $d['city'] = $t->name; } } elseif ( ! in_array( $t->name, $d['places'], true ) ) { $d['places'][] = $t->name; }
			}
		}
	}
	$v = $d['video'];
	if ( ! $v['url'] && ! $v['embed'] ) {
		$url = (string) get_post_meta( $id, '_zad_video', true );
		if ( $url ) {
			$yt            = function_exists( 'zad_youtube_id' ) ? zad_youtube_id( $url ) : '';
			$v['url']      = $yt ? '' : $url; // a YouTube page is not a media file: embedUrl only
			if ( $yt ) { $v['embed'] = 'https://www.youtube.com/embed/' . $yt; }
			$v['name']     = $v['name'] ? $v['name'] : zsc_clean_name( get_the_title( $post ) ) . ' — فيديو';
			$v['thumb']    = $v['thumb'] ? $v['thumb'] : ( $yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : (string) get_the_post_thumbnail_url( $post, 'large' ) );
			$v['date']     = $v['date'] ? $v['date'] : get_the_date( 'Y-m-d', $post );
			$v['duration'] = $v['duration'] ? $v['duration'] : (int) get_post_meta( $id, '_zad_video_duration', true );
			$d['video']    = $v;
		}
	}
	return $d;
}, 5, 2 );

/* ---------- 4. Graph nodes ---------- */

function zsc_iso_duration( $sec ) {
	$sec = absint( $sec );
	$h   = floor( $sec / 3600 );
	$m   = floor( ( $sec % 3600 ) / 60 );
	$s   = $sec % 60;
	return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s || ( ! $h && ! $m ) ? $s . 'S' : '' );
}

function zsc_image_node( $src, $id, $fallback = '', $attachment_id = 0 ) {
	if ( ! $src ) { return null; }
	$b    = zsc_settings()['business'];
	$home = trailingslashit( home_url() );
	$aid  = absint( $attachment_id );
	if ( ! $aid ) {
		$aid = (int) attachment_url_to_postid( $src );
		if ( ! $aid ) {
			$orig = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $src );
			if ( $orig !== $src ) { $aid = (int) attachment_url_to_postid( $orig ); }
		}
	}
	$url = $src; $w = 0; $h = 0; $caption = ''; $year = '';
	if ( $aid ) {
		$full = wp_get_attachment_image_src( $aid, 'full' );
		if ( $full ) { $url = $full[0]; $w = (int) $full[1]; $h = (int) $full[2]; }
		$caption = trim( (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) );
		if ( '' === $caption ) { $caption = trim( (string) wp_get_attachment_caption( $aid ) ); }
		$year = substr( (string) get_post_time( 'Y', true, $aid ), 0, 4 );
		if ( ! preg_match( '/^(19|20)\d{2}$/', $year ) ) { $year = ''; }
	}
	if ( '' === $caption ) { $caption = $fallback; }
	if ( '' === $year && preg_match( '#/uploads/(\d{4})/\d{2}/#', $url, $m ) ) { $year = $m[1]; }
	$img = array( '@type' => 'ImageObject', '@id' => $id, 'url' => $url, 'contentUrl' => $url );
	if ( $w && $h ) { $img['width'] = $w; $img['height'] = $h; }
	if ( '' !== $caption ) { $img['caption'] = $caption; $img['name'] = $caption; }
	$img['inLanguage']      = 'ar';
	$img['creditText']      = $b['legal_name'];
	$img['creator']         = array( '@id' => $home . '#organization' );
	$img['copyrightHolder'] = array( '@id' => $home . '#organization' );
	$img['copyrightNotice'] = '© ' . ( $year ? $year . ' ' : '' ) . $b['legal_name'];
	if ( $year ) { $img['copyrightYear'] = (int) $year; }
	if ( ! empty( $b['image_license'] ) ) { $img['license'] = $b['image_license']; }
	if ( ! empty( $b['image_acquire'] ) ) { $img['acquireLicensePage'] = $b['image_acquire']; }
	return $img;
}

function zsc_site_nodes() {
	$b    = zsc_settings()['business'];
	$home = trailingslashit( home_url() );
	$areas = array();
	foreach ( $b['area_served'] as $a ) { $areas[] = array( '@type' => $a[0], 'name' => $a[1] ); }
	$hours = array();
	foreach ( $b['hours'] as $h ) { $hours[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $h[0], 'opens' => $h[1], 'closes' => $h[2] ); }

	$org = array(
		'@type' => 'Organization', '@id' => $home . '#organization', 'name' => $b['name'], 'legalName' => $b['legal_name'],
		'alternateName' => $b['alternate'], 'url' => $home,
		'logo' => array( '@type' => 'ImageObject', '@id' => $home . '#logo', 'url' => $b['logo'], 'contentUrl' => $b['logo'], 'caption' => $b['name'], 'name' => $b['name'], 'inLanguage' => 'ar' ),
		'sameAs' => $b['same_as'], 'knowsAbout' => $b['knows_about'],
	);
	if ( preg_match( '#^https?://#', (string) $b['wikidata'] ) && ! in_array( $b['wikidata'], (array) $org['sameAs'], true ) ) { $org['sameAs'][] = $b['wikidata']; } // optional Wikidata entity
	$lb = array(
		'@type' => 'HomeAndConstructionBusiness', '@id' => $home . '#localbusiness', 'name' => $b['name'], 'legalName' => $b['legal_name'], 'url' => $home,
		'image' => array( '@id' => $home . '#logo' ), 'description' => $b['description'], 'telephone' => $b['telephone'], 'email' => $b['email'],
		'priceRange' => $b['price_range'], 'currenciesAccepted' => 'SAR',
		'address' => array( '@type' => 'PostalAddress', 'streetAddress' => $b['street'], 'addressLocality' => $b['locality'], 'addressRegion' => $b['region'], 'postalCode' => $b['postal_code'], 'addressCountry' => $b['country'] ),
		'geo' => array( '@type' => 'GeoCoordinates', 'latitude' => $b['lat'], 'longitude' => $b['lng'] ),
		'openingHoursSpecification' => $hours, 'sameAs' => $b['same_as'], 'areaServed' => $areas,
		'vatID' => $b['vat'], 'taxID' => $b['vat'],
		'identifier' => ( '' !== trim( (string) $b['cr'] ) ) ? array( '@type' => 'PropertyValue', 'propertyID' => 'CR', 'name' => 'السجل التجاري', 'value' => $b['cr'] ) : '',
		'knowsAbout' => $b['knows_about'], 'parentOrganization' => array( '@id' => $home . '#organization' ),
	);
	if ( ! empty( $b['has_map'] ) ) { $lb['hasMap'] = $b['has_map']; }
	elseif ( ! empty( $b['lat'] ) && ! empty( $b['lng'] ) ) { $lb['hasMap'] = 'https://www.google.com/maps?q=' . $b['lat'] . ',' . $b['lng']; } // no map link in the settings: the business's own coordinates
	foreach ( array( 'telephone', 'email', 'priceRange', 'vatID', 'taxID', 'identifier', 'openingHoursSpecification' ) as $k ) { // empty settings are left out, never printed blank (tax / CR only when filled)
		if ( empty( $lb[ $k ] ) ) { unset( $lb[ $k ] ); }
	}
	if ( empty( $b['lat'] ) || empty( $b['lng'] ) ) { unset( $lb['geo'] ); }
	$site = array(
		'@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $b['alternate'], 'alternateName' => $b['name'],
		'description' => $b['description'], 'publisher' => array( '@id' => $home . '#organization' ), 'inLanguage' => 'ar',
		'potentialAction' => array( array(
			'@type' => 'SearchAction', 'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ),
			'query-input' => array( '@type' => 'PropertyValueSpecification', 'valueRequired' => true, 'valueName' => 'search_term_string' ),
		) ),
	);
	return array( $org, $site, $lb );
}

function zsc_service_nodes( $post ) {
	$post = get_post( $post );
	$d    = zsc_page_data( $post );
	$home = trailingslashit( home_url() );
	$url  = get_permalink( $post );

	$webpage = array(
		'@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url,
		'name' => wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ),
		'isPartOf' => array( '@id' => $home . '#website' ), 'inLanguage' => 'ar',
		'datePublished' => get_post_time( 'c', true, $post ), 'dateModified' => get_post_modified_time( 'c', true, $post ),
	);
	if ( $d['description'] ) { $webpage['description'] = $d['description']; }
	$image = zsc_image_node( $d['image'], $url . '#primaryimage', $d['name'], $d['image_id'] );
	if ( $image ) { $webpage['primaryImageOfPage'] = $image; $webpage['image'] = array( '@id' => $url . '#primaryimage' ); }

	if ( ! $d['is_service'] ) { return array( $webpage ); }
	if ( $image ) { $webpage['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' ); }

	$hoods = array(); // neighbourhoods: the page's «تغطية» chips (name before «|»), then the other known places; at most 15
	$cov   = (array) get_post_meta( $post->ID, '_zad_cov', true );
	foreach ( zsc_lines( isset( $cov['chips'] ) ? $cov['chips'] : '' ) as $l ) {
		$nm = trim( explode( '|', $l )[0] );
		if ( '' !== $nm && ! in_array( $nm, $hoods, true ) ) { $hoods[] = $nm; }
	}
	foreach ( $d['places'] as $p ) { if ( ! in_array( $p, $hoods, true ) ) { $hoods[] = $p; } }
	$hoods = array_slice( $hoods, 0, 15 );
	$area = array();
	if ( $d['city'] ) {
		$area[] = array( '@type' => 'City', 'name' => $d['city'], 'containedInPlace' => array( '@type' => 'Country', 'name' => 'المملكة العربية السعودية' ) );
		foreach ( $hoods as $p ) { $area[] = array( '@type' => 'Place', 'name' => $p, 'containedInPlace' => array( '@type' => 'City', 'name' => $d['city'] ) ); }
	} else {
		$area[] = array( '@type' => 'Country', 'name' => 'المملكة العربية السعودية' );
		foreach ( $hoods as $p ) { $area[] = array( '@type' => 'Place', 'name' => $p ); }
	}
	$service = array(
		'@type' => 'Service', '@id' => $url . '#service', 'name' => $d['name'], 'serviceType' => $d['type'], 'url' => $url,
		'provider' => array( '@id' => $home . '#localbusiness' ), 'brand' => array( '@id' => $home . '#organization' ),
		'areaServed' => count( $area ) === 1 ? $area[0] : $area, 'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
	);
	if ( $d['description'] ) { $service['description'] = $d['description']; }
	if ( $image ) { $service['image'] = $image; }
	$spec = array(); // «البطاقة الفنية»: the visible rows, nothing else
	foreach ( zad_spec_rows( $post->ID ) as $r ) { $spec[] = array( '@type' => 'PropertyValue', 'name' => $r[0], 'value' => $r[1] ); }
	if ( $spec ) { $service['additionalProperty'] = $spec; }
	$aud = trim( (string) get_post_meta( $post->ID, '_zad_audience', true ) );
	if ( '' !== $aud ) { $service['audience'] = array( '@type' => 'Audience', 'audienceType' => $aud ); }
	$hrs = array();
	foreach ( zsc_settings()['business']['hours'] as $h ) { $hrs[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $h[0], 'opens' => $h[1], 'closes' => $h[2] ); } // the working hours of the settings
	if ( $hrs ) { $service['hoursAvailable'] = $hrs; }

	$pkcat = function_exists( 'zad_pk_catalog' ) ? zad_pk_catalog( $post->ID ) : null;
	if ( $d['offers'] ) {
		$items = array(); $min = null; $max = null;
		$cc    = function_exists( 'zad_current_city' ) ? zad_current_city( $post->ID ) : array( 'name' => '', 'source' => 'default' );
		$cityn = ( ! empty( $cc['name'] ) && 'default' !== $cc['source'] ) ? $cc['name'] : ''; // never stamp the site default city on an offer
		foreach ( $d['offers'] as $o ) {
			$kind = isset( $o['kind'] ) ? $o['kind'] : 'fixed';
			$pp   = array( 'kind' => $kind, 'min' => isset( $o['min'] ) ? $o['min'] : $o['price'], 'max' => isset( $o['max'] ) ? $o['max'] : 0 );
			$it   = array( '@type' => 'Offer', 'itemOffered' => array( '@type' => 'Service', 'name' => $o['name'] ) ) + zad_price_offer( $pp, ! empty( $o['monthly'] ) );
			$lo   = 'quote' === $kind ? null : $pp['min'];
			$hi   = 'range' === $kind ? $pp['max'] : ( 'fixed' === $kind ? $pp['min'] : null );
			if ( null !== $lo ) { $min = null === $min ? $lo : min( $min, $lo ); }
			if ( null !== $hi ) { $max = null === $max ? $hi : max( $max, $hi ); }
			if ( 'quote' !== $kind ) { $it['availability'] = 'https://schema.org/InStock'; }
			if ( '' !== $cityn ) { $it['areaServed'] = array( '@type' => 'City', 'name' => $cityn ); }
			$items[] = $it;
		}
		$service['hasOfferCatalog'] = array( '@type' => 'OfferCatalog', 'name' => 'أسعار ' . $d['name'], 'itemListElement' => $items );
		if ( null !== $min ) {
			$agg = array( '@type' => 'AggregateOffer', 'priceCurrency' => 'SAR', 'lowPrice' => $d['price_from'] ? min( $d['price_from'], $min ) : $min, 'offerCount' => count( $items ), 'url' => $url );
			if ( null !== $max ) { $agg['highPrice'] = $max; }
			$service['offers'] = $agg;
		}
	} elseif ( $d['price_from'] ) {
		$offer = array( '@type' => 'AggregateOffer', 'priceCurrency' => 'SAR', 'lowPrice' => $d['price_from'], 'url' => $url );
		if ( $d['price_unit'] ) { $offer['description'] = 'يبدأ من ' . $d['price_from'] . ' ريال ' . $d['price_unit']; }
		$service['offers'] = $offer;
	}
	if ( $pkcat ) { $service['hasOfferCatalog'] = $pkcat; } // packages on the page: only the packages catalog goes into the schema (the price table stays visible on the page)

	$nodes = array();
	$v = $d['video'];
	if ( $v['name'] && $v['thumb'] && $v['date'] && ( $v['url'] || $v['embed'] ) ) {
		$video = array( '@type' => 'VideoObject', '@id' => $url . '#video', 'name' => $v['name'], 'description' => $v['desc'] ? $v['desc'] : $v['name'], 'thumbnailUrl' => $v['thumb'], 'uploadDate' => $v['date'], 'inLanguage' => 'ar', 'publisher' => array( '@id' => $home . '#organization' ) );
		if ( $v['url'] ) { $video['contentUrl'] = $v['url']; }
		if ( $v['embed'] ) { $video['embedUrl'] = $v['embed']; }
		if ( $v['duration'] ) { $video['duration'] = zsc_iso_duration( $v['duration'] ); }
		$nodes[] = $video;
		$service['subjectOf'] = array( '@id' => $url . '#video' );
	}
	$webpage['about']      = array( '@id' => $url . '#service' );
	$webpage['mainEntity'] = array( '@id' => $url . '#service' );
	if ( function_exists( 'zad_qnet_links' ) ) { $rl = zad_qnet_links( $post->ID ); if ( $rl ) { $webpage['relatedLink'] = $rl; } } // links only: the question pages keep their own FAQPage
	$webpage['lastReviewed'] = get_post_modified_time( 'c', true, $post );
	$reviewer = function_exists( 'zad_reviewer_id' ) ? zsc_person_node( zad_reviewer_id( $post ) ) : null;
	if ( $reviewer ) { $webpage['reviewedBy'] = array( '@id' => $reviewer['@id'] ); $nodes[] = $reviewer; }
	return apply_filters( 'zsc_service_nodes', array_merge( array( $webpage, $service ), $nodes ), $post );
}

function zsc_person_node( $user_id ) {
	$cfg  = zsc_settings();
	$home = trailingslashit( home_url() );
	$user = get_userdata( $user_id );
	if ( ! $user ) { return null; }
	$slug = $user->user_nicename;
	$a    = $cfg['authors'][ $slug ] ?? array();
	$aurl = get_author_posts_url( $user_id, $slug );
	$bio  = function_exists( 'zad_author_has_bio' ) ? zad_author_has_bio( $user_id ) : true;
	// Fixed id = author page + #person (same node on the author page and in every article). Authors without a bio have no public page: no url.
	$p    = array( '@type' => 'Person', '@id' => $aurl . '#person', 'name' => ! empty( $a['name'] ) ? $a['name'] : $user->display_name, 'worksFor' => array( '@id' => $home . '#organization' ) );
	if ( $bio ) { $p['url'] = $aurl; }
	if ( ! empty( $a['job_title'] ) ) { $p['jobTitle'] = $a['job_title']; }
	$desc = ! empty( $a['description'] ) ? $a['description'] : trim( (string) get_the_author_meta( 'description', $user_id ) );
	if ( '' !== $desc ) { $p['description'] = $desc; }
	if ( ! empty( $a['credential'] ) ) { $p['hasCredential'] = array( '@type' => 'EducationalOccupationalCredential', 'credentialCategory' => 'degree', 'name' => $a['credential'] ); }
	if ( ! empty( $a['knows_about'] ) ) { $p['knowsAbout'] = $a['knows_about']; }
	$same = ! empty( $a['same_as'] ) ? $a['same_as'] : ( function_exists( 'zad_author_sameas' ) ? zad_author_sameas( $user_id ) : array() );
	if ( $same ) { $p['sameAs'] = $same; }
	$img = ! empty( $a['image'] ) ? $a['image'] : ( function_exists( 'zad_author_image' ) ? zad_author_image( $user_id ) : '' );
	if ( $img ) { $p['image'] = array( '@type' => 'ImageObject', 'url' => $img, 'caption' => $p['name'] ); }
	return $p;
}

function zsc_is_article_page( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'publish' !== $post->post_status ) { return false; }
	$mode = zsc_meta( $post->ID, 'mode' );
	if ( 'none' === $mode || 'service' === $mode ) { return false; }
	if ( in_array( $post->post_type, array_merge( zad_article_types(), zad_faq_types() ), true ) ) { return true; }
	return 'post' === $post->post_type && ! zsc_is_service_auto( get_the_title( $post ), 'post' );
}

function zsc_article_nodes( $post ) {
	$post = get_post( $post );
	$home = trailingslashit( home_url() );
	$url  = get_permalink( $post );
	$d    = zsc_page_data( $post );
	$title    = wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
	$headline = mb_strlen( $title, 'UTF-8' ) > 110 ? mb_substr( $title, 0, 109, 'UTF-8' ) . '…' : $title;
	$image    = zsc_image_node( $d['image'], $url . '#primaryimage', $title, $d['image_id'] );
	$person   = zsc_person_node( (int) $post->post_author );

	$webpage = array( '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'isPartOf' => array( '@id' => $home . '#website' ), 'inLanguage' => 'ar', 'datePublished' => get_post_time( 'c', true, $post ), 'dateModified' => get_post_modified_time( 'c', true, $post ), 'mainEntity' => array( '@id' => $url . '#article' ) );
	if ( $d['description'] ) { $webpage['description'] = $d['description']; }
	if ( $image ) { $webpage['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' ); $webpage['image'] = array( '@id' => $url . '#primaryimage' ); }
	$article = array(
		'@type' => in_array( $post->post_type, zad_faq_types(), true ) ? 'Article' : 'BlogPosting', '@id' => $url . '#article', 'headline' => $headline, 'url' => $url,
		'mainEntityOfPage' => array( '@id' => $url . '#webpage' ), 'isPartOf' => array( '@id' => $url . '#webpage' ),
		'datePublished' => get_post_time( 'c', true, $post ), 'dateModified' => get_post_modified_time( 'c', true, $post ),
		'publisher' => array( '@id' => $home . '#organization' ), 'inLanguage' => 'ar',
		'wordCount' => count( preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ), -1, PREG_SPLIT_NO_EMPTY ) ),
	);
	if ( $d['description'] ) { $article['description'] = $d['description']; }
	if ( $image ) { $article['image'] = $image; }
	if ( $person ) { $article['author'] = array( '@id' => $person['@id'] ); }
	$pto = get_post_type_object( $post->post_type );
	if ( $pto && 'post' !== $post->post_type ) { $article['articleSection'] = wp_strip_all_tags( $pto->labels->name ); }
	$nodes = array( $webpage, $article );
	if ( $person ) { $nodes[] = $person; }
	return $nodes;
}

function zsc_home_nodes() {
	$b    = zsc_settings()['business'];
	$home = trailingslashit( home_url() );
	$id   = (int) get_option( 'page_on_front' );
	$desc = $id ? (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ) : '';
	if ( '' === $desc || false !== strpos( $desc, '%%' ) ) { $desc = zad_opt( 'zad_site_desc', $b['description'] ); }
	$page = array(
		'@type' => 'WebPage', '@id' => $home . '#webpage', 'url' => $home,
		'name' => wp_strip_all_tags( html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' ) ), 'description' => $desc,
		'isPartOf' => array( '@id' => $home . '#website' ), 'about' => array( '@id' => $home . '#organization' ),
		'mainEntity' => array( '@id' => $home . '#localbusiness' ), 'primaryImageOfPage' => array( '@id' => $home . '#logo' ), 'inLanguage' => 'ar',
	);
	if ( $id ) { $page['datePublished'] = get_post_time( 'c', true, $id ); $page['dateModified'] = get_post_modified_time( 'c', true, $id ); }
	return array( $page );
}

/** Attach BreadcrumbList to the graph and link it from the WebPage node. */
function zsc_with_breadcrumb( $nodes, $url ) {
	$crumbs = zad_current_crumbs();
	if ( count( $crumbs ) < 2 ) { return $nodes; }
	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ? $c[1] : $url );
	}
	foreach ( $nodes as &$n ) {
		if ( isset( $n['@type'] ) && ( 'WebPage' === $n['@type'] || ( is_array( $n['@type'] ) && in_array( 'WebPage', $n['@type'], true ) ) ) ) {
			$n['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}
	}
	unset( $n );
	$nodes[] = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items );
	return $nodes;
}

/* ---------- 5. Compat wrappers for zad-cleanup.php (llms.txt) when the mu-plugin is gone ---------- */

if ( ! function_exists( 'zad_sc_settings' ) ) {
	function zad_sc_settings() { return zsc_settings(); }
}
if ( ! function_exists( 'zad_sc_types' ) ) {
	function zad_sc_types() { return zsc_types(); }
}
if ( ! function_exists( 'zad_sc_page_data' ) ) {
	function zad_sc_page_data( $post ) { return zsc_page_data( $post ); }
}

/* ---------- 6. De-duplication: Schema Pro and the retired mu-plugin ---------- */

/** Types the theme now owns; JSON-LD of these types from other sources is removed (FAQPage, HowTo, Video, Review… are kept). */
function zsc_is_owned_type( $node ) {
	if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
		return false;
	}
	$keep = array( 'FAQPage', 'HowTo', 'VideoObject', 'Review', 'AggregateRating', 'ItemList', 'Event', 'Product', 'Recipe', 'Course', 'JobPosting' );
	foreach ( (array) $node['@type'] as $t ) {
		if ( in_array( $t, $keep, true ) ) {
			return false;
		}
		if ( preg_match( '/(Organization|Corporation|Business|Store|Contractor|Company|Service|Person|WebSite|WebPage|AboutPage|ContactPage|Article|BlogPosting|BreadcrumbList|SiteNavigationElement|Plumber|Electrician|Locksmith|HousePainter|Agent)$/', $t ) ) {
			return true;
		}
	}
	return isset( $node['address'] ) && isset( $node['telephone'] );
}

function zsc_filter_foreign_jsonld( $html ) {
	if ( false === stripos( $html, 'ld+json' ) ) {
		return $html;
	}
	return preg_replace_callback( '#(<!--(?:(?!-->).)*-->\s*)?<script[^>]*application/ld\+json[^>]*>(.*?)</script>\s*#is', function ( $m ) {
		$d = json_decode( trim( $m[2] ), true );
		if ( ! is_array( $d ) ) {
			return $m[0];
		}
		$enc = function ( $x ) { return '<script type="application/ld+json">' . wp_json_encode( $x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n"; };
		if ( isset( $d['@graph'] ) && is_array( $d['@graph'] ) ) {
			$left = array_values( array_filter( $d['@graph'], function ( $n ) { return ! zsc_is_owned_type( $n ); } ) );
			if ( count( $left ) === count( $d['@graph'] ) ) { return $m[0]; }
			if ( ! $left ) { return ''; }
			$d['@graph'] = $left;
			return $enc( $d );
		}
		$list = isset( $d[0] ) ? $d : array( $d );
		$left = array_values( array_filter( $list, function ( $n ) { return ! zsc_is_owned_type( $n ); } ) );
		if ( count( $left ) === count( $list ) ) { return $m[0]; }
		if ( ! $left ) { return ''; }
		return $enc( isset( $d[0] ) ? $left : $left[0] );
	}, $html );
}

/** Pages on which the theme prints the schema graph (everything except 404/search). */
function zsc_is_active_request() {
	return ! is_admin() && ! is_feed() && ! is_404() && ! is_search() && 'theme' === zad_schema_owner();
}

/* With the theme in charge, silence a still-installed mu-plugin zad-schema.php so nothing prints twice. */
add_action( 'wp', function () {
	if ( 'theme' !== zad_schema_owner() && 'theme' !== zad_opt( 'zad_schema_mode', 'auto' ) ) {
		return;
	}
	if ( 'theme' !== zad_opt( 'zad_schema_mode', 'auto' ) ) {
		return; // auto mode never reaches here with the mu-plugin present (owner = other)
	}
	global $wp_filter;
	if ( empty( $wp_filter['wp_head'] ) ) {
		return;
	}
	foreach ( $wp_filter['wp_head']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $key => $cb ) {
			if ( ! ( $cb['function'] instanceof Closure ) ) { continue; }
			try {
				$f = ( new ReflectionFunction( $cb['function'] ) )->getFileName();
			} catch ( Throwable $e ) {
				continue;
			}
			if ( $f && 'zad-schema.php' === basename( $f ) ) {
				remove_action( 'wp_head', $cb['function'], $prio );
			}
		}
	}
}, 1 );
