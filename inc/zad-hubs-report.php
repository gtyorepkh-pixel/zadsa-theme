<?php defined( 'ABSPATH' ) || exit;
/**
 * الأدوات ← «تقرير صفحات الأقسام (Hubs)» — READ ONLY. Writes nothing, changes no page.
 * Answers, from the live database: which pages would be treated as a «قسم» (section/hub) under the proposed rule, where every title / H1 of those pages comes from
 * (zad_hubs, the page linked by «محتوى الأرشيف», Yoast, the post-type label, theme defaults), and which service pages have the same title as a section.
 * Output = a Markdown report to copy back.
 */

function zad_hr_md( $s ) { return trim( str_replace( array( '|', "\n", "\r" ), array( '/', ' ', ' ' ), (string) $s ) ); }
function zad_hr_words( $s ) { $s = trim( wp_strip_all_tags( (string) $s ) ); return '' === $s ? 0 : count( preg_split( '/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY ) ); }
function zad_hr_key( $s ) { $s = zad_ar_norm( (string) $s ); $s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s ); return trim( preg_replace( '/\s+/u', ' ', $s ) ); }
function zad_hr_trunc( $s, $n = 70 ) { $s = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $s ) ) ); return mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n ) . '…' : $s; }

/** The mapped «محتوى الأرشيف» page (draft allowed, trash not): array( id, post|null ). */
function zad_hr_mapped( $map_opt, $keys ) {
	$m = (array) get_option( $map_opt, array() );
	foreach ( $keys as $k ) { $m = is_array( $m ) && isset( $m[ $k ] ) ? $m[ $k ] : null; }
	$id = absint( is_array( $m ) ? 0 : $m );
	$p  = $id ? get_post( $id ) : null;
	$ok = $p && 'page' === $p->post_type && ! in_array( $p->post_status, array( 'trash', 'auto-draft' ), true );
	return array( $id, $ok ? $p : null );
}

/** Same logic as hub-service.php + zad_ac_title / zad_ac_seo, but with the source named. */
function zad_hr_type_row( $pt ) {
	$o      = get_post_type_object( $pt );
	$label  = $o ? (string) $o->labels->name : $pt;
	$hub    = zad_hub_opt( $pt );
	list( $pid, $page ) = zad_hr_mapped( ZAD_AC_MAP, array( $pt ) );
	$seo    = (array) get_option( ZAD_AC_SEO, array() ); $cs = isset( $seo[ $pt ] ) && is_array( $seo[ $pt ] ) ? $seo[ $pt ] : array();
	$brand  = zad_brand_name();
	$r = array( 'pt' => $pt, 'label' => $label, 'url' => (string) get_post_type_archive_link( $pt ), 'hub' => $hub, 'map_id' => $pid, 'page' => $page, 'seo_custom' => $cs );
	// H1 (template-parts/hub-service.php)
	if ( ! empty( $hub['headline'] ) ) { $r['h1'] = $hub['headline']; $r['h1_src'] = 'zad_hubs ← «العنوان الرئيسي» (الصف: ' . ( $hub['slug'] ?? '' ) . ')'; }
	elseif ( $page && '' !== trim( get_the_title( $page ) ) && ! zad_suite_has( 'archive' ) ) { $r['h1'] = trim( get_the_title( $page ) ); $r['h1_src'] = 'عنوان الصفحة المربوطة في «محتوى الأرشيف» #' . $pid; }
	else { $r['h1'] = $label; $r['h1_src'] = 'اسم نوع المحتوى (labels->name)'; }
	// <title> (zad_ac_seo → theme default)
	if ( '' !== trim( (string) ( $cs['title'] ?? '' ) ) ) { $r['title'] = trim( $cs['title'] ); $r['title_src'] = 'محتوى الأرشيف ← عنوان SEO المخصص لهذا النوع'; }
	elseif ( $page && '' !== trim( (string) get_post_meta( $pid, '_yoast_wpseo_title', true ) ) ) { $r['title'] = trim( (string) get_post_meta( $pid, '_yoast_wpseo_title', true ) ); $r['title_src'] = 'Yoast للصفحة المربوطة #' . $pid . ' (قيمة خام، قد تحوي متغيرات %%)'; }
	elseif ( $page ) { $r['title'] = trim( get_the_title( $page ) ) . ' | ' . get_bloginfo( 'name' ); $r['title_src'] = 'عنوان الصفحة المربوطة #' . $pid . ' + اسم الموقع'; }
	else {
		$yo = get_option( 'wpseo_titles' ); $yk = 'title-ptarchive-' . $pt;
		$r['title'] = function_exists( 'zad_type_default' ) ? zad_type_default( $pt, 'title' ) : '';
		$r['title_src'] = 'افتراضي القالب (zad_type_default)' . ( is_array( $yo ) && ! empty( $yo[ $yk ] ) ? ' — لكن Yoast له قالب أرشيف: «' . zad_hr_trunc( $yo[ $yk ], 60 ) . '» (يتغلّب حسب وضع السيو)' : '' );
	}
	$r['lead_words'] = zad_hr_words( $hub['lead'] ?? '' );
	return $r;
}

/** Terms of the public taxonomies of the service types. */
function zad_hr_terms() {
	$tx = array();
	foreach ( zad_service_types() as $pt ) { foreach ( get_object_taxonomies( $pt, 'objects' ) as $t ) { if ( $t->public && ! in_array( $t->name, array( 'post_tag', 'category', 'post_format' ), true ) ) { $tx[ $t->name ] = $t; } } }
	$out = array();
	foreach ( $tx as $name => $t ) {
		foreach ( (array) get_terms( array( 'taxonomy' => $name, 'hide_empty' => false ) ) as $term ) {
			if ( ! $term instanceof WP_Term ) { continue; }
			list( $pid, $page ) = zad_hr_mapped( ZAD_AC_TERM_MAP, array( $name, $term->term_id ) );
			$brand = zad_brand_name();
			if ( 'service_area' === $name ) { $h1 = 'خدماتنا في ' . $term->name; $h1s = 'قالب taxonomy-service_area (service-archive.php)'; }
			elseif ( 'service_cat' === $name ) { $h1 = $term->name; $h1s = 'قالب taxonomy-service_cat (service-archive.php): اسم التصنيف'; }
			else { $h1 = $term->name; $h1s = 'لا قالب خاص بهذه الـ taxonomy → القالب العام (archive.php)'; }
			$own = trim( (string) get_term_meta( $term->term_id, '_zad_seo_title', true ) );
			$def = function_exists( 'zad_term_default' ) && in_array( $name, array( 'service_area', 'service_cat', 'faq_cat' ), true ) ? zad_term_default( $term, 'title' ) : '';
			$out[] = array( 'tax' => $name, 'term' => $term, 'url' => (string) get_term_link( $term ), 'h1' => $h1, 'h1_src' => $h1s, 'title' => '' !== $own ? $own : $def, 'title_src' => '' !== $own ? 'حقل السيو في التصنيف' : ( '' !== $def ? 'افتراضي القالب (zad_term_default)' : '—' ),
				'map_id' => $pid, 'page' => $page, 'noindex' => function_exists( 'zad_term_noindexed' ) ? zad_term_noindexed( $term ) : false );
		}
	}
	return $out;
}

/** Hierarchical service pages and the proposed rule. */
function zad_hr_pages() {
	$o = array( 'sections' => array(), 'has_city' => array(), 'no_city_kids' => array(), 'suspect' => array(), 'flat_types' => array(), 'total' => 0 );
	foreach ( zad_service_types() as $pt ) {
		if ( ! is_post_type_hierarchical( $pt ) ) { $o['flat_types'][] = $pt; continue; }
		$posts = get_posts( array( 'post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true, 'no_found_rows' => true, 'orderby' => 'menu_order title', 'order' => 'ASC', 'zad_all' => true ) );
		$kids  = array(); foreach ( $posts as $p ) { $kids[ (int) $p->post_parent ][] = $p; }
		$o['total'] += count( $posts );
		$specific = function ( $id ) { $s = zad_service_city_scope( $id ); return ! in_array( $s, array( '', 'all' ), true ) ? $s : ''; };
		foreach ( $posts as $p ) {
			$ch = $kids[ (int) $p->ID ] ?? array(); if ( ! $ch ) { continue; }
			list( $scope, $src ) = zad_city_resolve( $p->ID );
			$ck = array_values( array_filter( $ch, function ( $c ) use ( $specific ) { return '' !== $specific( $c->ID ); } ) );
			$row = array( 'p' => $p, 'url' => get_permalink( $p ), 'scope' => $scope, 'src' => $src, 'kids' => count( $ch ), 'city_kids' => count( $ck ), 'kid_cities' => array_values( array_unique( array_map( function ( $c ) use ( $specific ) { return $specific( $c->ID ); }, $ck ) ) ) );
			if ( '' !== $specific( $p->ID ) ) { $o['has_city'][] = $row; continue; } // has a city → stays a service page, even with hood pages under it
			if ( $ck ) { $o['sections'][] = $row; continue; }
			// no city and no city children: do the children look like cities that the city list does not know?
			$guess = array(); foreach ( $ch as $c ) { $g = function_exists( 'zsc_detect_city' ) ? zsc_detect_city( $c->post_title, rawurldecode( (string) wp_parse_url( get_permalink( $c ), PHP_URL_PATH ) ) ) : ''; if ( $g ) { $guess[ $g ] = 1; } }
			$row['guess'] = array_keys( $guess );
			if ( $guess ) { $o['suspect'][] = $row; } else { $o['no_city_kids'][] = $row; }
		}
	}
	return $o;
}


/** Fetch a public URL of this site and read what a visitor/Google gets: H1, <title>, canonical, robots, number of section cards, schema types. */
function zad_hr_fetch( $url ) {
	$r = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false, 'headers' => array( 'Cache-Control' => 'no-cache' ), 'user-agent' => 'ZadHubReport/1' ) );
	if ( is_wp_error( $r ) ) { return array( 'err' => $r->get_error_message() ); }
	$code = (int) wp_remote_retrieve_response_code( $r ); $h = (string) wp_remote_retrieve_body( $r );
	$o = array( 'code' => $code, 'loc' => (string) wp_remote_retrieve_header( $r, 'location' ) );
	$o['h1']    = preg_match( '#<h1[^>]*>(.*?)</h1>#su', $h, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : '';
	$o['title'] = preg_match( '#<title>(.*?)</title>#su', $h, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' ) : '';
	$o['canon'] = preg_match( '#<link rel=["\']canonical["\'] href=["\']([^"\']+)#i', $h, $m ) ? $m[1] : '';
	$o['robots'] = preg_match( '#<meta name=["\']robots["\'] content=["\']([^"\']*)#i', $h, $m ) ? $m[1] : '';
	$o['cards'] = substr_count( $h, 'class="zhub__card"' ); $o['hub'] = false !== strpos( $h, 'class="zhub"' ) || false !== strpos( $h, 'class="zhub__' );
	$types = array();
	if ( preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $h, $mm ) ) { foreach ( $mm[1] as $j ) { $d = json_decode( $j, true ); if ( ! $d ) { continue; } foreach ( isset( $d['@graph'] ) ? $d['@graph'] : array( $d ) as $x ) { foreach ( (array) ( $x['@type'] ?? array() ) as $t ) { $types[ $t ] = 1; } } } }
	$o['schema'] = array_keys( $types );
	return $o;
}

function zad_hr_report() {
	global $wpdb;
	$brand = zad_brand_name(); $m = '';
	$m .= '# تقرير صفحات الأقسام (Hubs) — ' . wp_date( 'Y-m-d H:i' ) . ' — ' . home_url() . "\n\n";
	$m .= 'الثيم ' . ZAD_VERSION . ' · وضع السيو: `' . zad_seo_mode() . '` · Yoast: ' . ( defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : 'غير مفعّل' ) . ' · إضافة «محتوى الأرشيف» القديمة نشطة: ' . ( zad_suite_has( 'archive' ) ? '**نعم** (تتجاوز عناوين الثيم)' : 'لا' ) . ' · اسم العلامة: ' . $brand . "\n\n";

	/* 1) type archives */
	$m .= "## 1) أرشيفات أنواع الخدمات (/cleaning/ و/pest-control/ …)\n";
	$rows = array(); $h1s = array(); $ts = array();
	foreach ( zad_service_types() as $pt ) {
		$o = get_post_type_object( $pt ); if ( ! $o || ! $o->has_archive ) { continue; }
		$r = zad_hr_type_row( $pt ); $rows[] = $r; $h1s[ zad_hr_key( $r['h1'] ) ][] = $pt; $ts[ zad_hr_key( $r['title'] ) ][] = $pt;
	}
	if ( ! $rows ) { $m .= "لا يوجد نوع خدمة له أرشيف.\n"; }
	foreach ( $rows as $r ) {
		$m .= "\n### `{$r['pt']}` — " . zad_hr_md( $r['url'] ) . "\n";
		$m .= '- اسم النوع (labels->name): «' . zad_hr_md( $r['label'] ) . "»\n";
		$m .= '- **H1 الحالي:** «' . zad_hr_md( $r['h1'] ) . '» ← المصدر: ' . $r['h1_src'] . "\n";
		$m .= '- **عنوان الصفحة `<title>` الحالي:** «' . zad_hr_md( $r['title'] ) . '» ← المصدر: ' . $r['title_src'] . "\n";
		$h = $r['hub'];
		$m .= '- صف «صفحات الأقسام (zad_hubs)» المطابق: ' . ( $h ? 'نعم — slug=`' . ( $h['slug'] ?? '' ) . '` · العنوان الرئيسي: «' . zad_hr_md( $h['headline'] ?? '' ) . '» · المقدمة: ' . zad_hr_words( $h['lead'] ?? '' ) . ' كلمة' . ( zad_hr_words( $h['lead'] ?? '' ) > 60 ? ' (**أطول من 60**)' : '' ) . ' · النظرة العامة (body): ' . zad_hr_words( $h['body'] ?? '' ) . ' كلمة · «اختر حسب حالتك»: ' . count( zad_lines( $h['decide'] ?? '' ) ) . ' سطر · نصائح: ' . count( zad_lines( $h['tips'] ?? '' ) ) : '**لا يوجد صف** (يُستخدم اسم النوع أو الصفحة المربوطة)' ) . "\n";
		$m .= '- صفحة «محتوى الأرشيف» المربوطة: ' . ( $r['page'] ? '#' . $r['map_id'] . ' «' . zad_hr_md( get_the_title( $r['page'] ) ) . '» (' . $r['page']->post_status . ') — ' . zad_hr_words( $r['page']->post_content ) . ' كلمة، تظهر كاملة تحت الـ H1' : ( $r['map_id'] ? '#' . $r['map_id'] . ' (غير صالحة: محذوفة أو ليست صفحة)' : 'لا يوجد' ) ) . "\n";
		if ( $r['seo_custom'] ) { $m .= '- عنوان/وصف SEO مخصّص في «محتوى الأرشيف»: «' . zad_hr_md( $r['seo_custom']['title'] ?? '' ) . '» / «' . zad_hr_trunc( $r['seo_custom']['description'] ?? '', 60 ) . "»\n"; }
	}
	$dup = false;
	foreach ( $h1s as $k => $pts ) { if ( count( $pts ) > 1 ) { $dup = true; $m .= "\n> ⚠️ **H1 متطابق** بين: `" . implode( '` و`', $pts ) . '` ← «' . zad_hr_md( $k ) . "»\n"; } }
	foreach ( $ts as $k => $pts ) { if ( count( $pts ) > 1 ) { $dup = true; $m .= "\n> ⚠️ **عنوان `<title>` متطابق** بين: `" . implode( '` و`', $pts ) . '` ← «' . zad_hr_md( $k ) . "»\n"; } }
	// the same hub row used by two types / the same page mapped twice
	$maps = (array) get_option( ZAD_AC_MAP, array() ); $by = array(); foreach ( $maps as $pt => $id ) { if ( $id ) { $by[ absint( $id ) ][] = $pt; } }
	foreach ( $by as $id => $pts ) { if ( count( $pts ) > 1 ) { $m .= "\n> ⚠️ الصفحة #$id مربوطة بأكثر من نوع: `" . implode( '` و`', $pts ) . "`\n"; } }
	$hubs = (array) zad_opt( 'zad_hubs', array() ); $seen = array();
	foreach ( $hubs as $i => $h ) { $k = zad_hr_key( $h['headline'] ?? '' ); if ( '' !== $k ) { $seen[ $k ][] = ( $h['slug'] ?? '#' . $i ); } }
	foreach ( $seen as $k => $sl ) { if ( count( $sl ) > 1 ) { $m .= "\n> ⚠️ صفوف zad_hubs بنفس العنوان الرئيسي: `" . implode( '` و`', $sl ) . '` ← «' . zad_hr_md( $k ) . "»\n"; } }
	if ( ! $dup && $rows ) { $m .= "\nلا تطابق في H1 أو `<title>` بين الأرشيفات.\n"; }
	$m .= "\nكل صفوف zad_hubs المحفوظة (" . count( $hubs ) . "):\n";
	foreach ( $hubs as $h ) { $m .= '- `' . zad_hr_md( $h['slug'] ?? '' ) . '` → «' . zad_hr_md( $h['headline'] ?? '' ) . '» / مقدمة ' . zad_hr_words( $h['lead'] ?? '' ) . ' كلمة / body ' . zad_hr_words( $h['body'] ?? '' ) . ' / decide ' . count( zad_lines( $h['decide'] ?? '' ) ) . ' / tips ' . count( zad_lines( $h['tips'] ?? '' ) ) . "\n"; }

	/* 2) taxonomy archives */
	$terms = zad_hr_terms();
	$m .= "\n## 2) أرشيفات التصنيفات والمدن (service_cat · service_area · أقسام التنظيف/المكافحة)\n";
	$by = array(); foreach ( $terms as $t ) { $by[ $t['tax'] ][] = $t; }
	foreach ( $by as $tax => $list ) {
		$m .= "\n### `$tax` (" . count( $list ) . ")\n| الاسم | الرابط | منشورات | H1 | عنوان الصفحة | محتوى الأرشيف | فهرسة |\n|---|---|---|---|---|---|---|\n";
		foreach ( $list as $t ) {
			$m .= '| ' . ( $t['term']->parent ? '↳ ' : '' ) . zad_hr_md( $t['term']->name ) . ' | ' . zad_hr_md( rawurldecode( $t['url'] ) ) . ' | ' . (int) $t['term']->count . ' | ' . zad_hr_md( $t['h1'] ) . ' | ' . zad_hr_md( $t['title'] ) . ' (' . $t['title_src'] . ') | ' . ( $t['page'] ? '#' . $t['map_id'] . ' ' . zad_hr_words( $t['page']->post_content ) . ' كلمة' : '—' ) . ' | ' . ( $t['noindex'] ? 'noindex' : 'index' ) . " |\n";
		}
		$m .= $tax === 'service_area' ? '' : '';
	}
	$m .= "\nمصدر H1 لكل نوع: service_area ← «خدماتنا في {الاسم}»، service_cat ← اسم التصنيف، غيرها ← القالب العام.\n";
	$cp = get_page_by_path( 'cities' ); $ar = get_page_by_path( 'areas' );
	$m .= '- صفحة `/cities/`: ' . ( $cp ? '#' . $cp->ID . ' «' . zad_hr_md( $cp->post_title ) . '» (' . $cp->post_status . ') قالب: `' . ( get_page_template_slug( $cp ) ?: 'افتراضي' ) . '`' : 'لا توجد صفحة بهذا الرابط' ) . "\n";
	$m .= '- صفحة `/' . zad_slug( 'zad_areas_slug', 'areas' ) . '/` كصفحة: ' . ( $ar ? '#' . $ar->ID . ' «' . zad_hr_md( $ar->post_title ) . '» قالب: `' . ( get_page_template_slug( $ar ) ?: 'افتراضي' ) . '`' : 'لا توجد (الرابط تستعمله taxonomy المدن)' ) . "\n";

	/* 3) the rule on hierarchical service pages */
	$pg = zad_hr_pages();
	$m .= "\n## 3) صفحات الخدمات الهرمية حسب القاعدة المقترحة\n";
	$m .= 'أنواع هرمية فُحصت: ' . ( zad_service_types() ? implode( '، ', array_filter( zad_service_types(), 'is_post_type_hierarchical' ) ) : '—' ) . ' · صفحات منشورة: ' . $pg['total'] . ' · أنواع غير هرمية (لا تتأثر): ' . ( $pg['flat_types'] ? implode( '، ', $pg['flat_types'] ) : '—' ) . "\n";
	$tbl = function ( $title, $list, $extra ) use ( &$m ) {
		$m .= "\n### $title (" . count( $list ) . ")\n";
		if ( ! $list ) { $m .= "لا يوجد.\n"; return; }
		$m .= "| الرابط | العنوان | مدينة الصفحة | أبناء | أبناء بمدينة | $extra |\n|---|---|---|---|---|---|\n";
		foreach ( $list as $r ) {
			$m .= '| ' . zad_hr_md( rawurldecode( (string) wp_parse_url( $r['url'], PHP_URL_PATH ) ) ) . ' | ' . zad_hr_md( $r['p']->post_title ) . ' (#' . $r['p']->ID . ') | ' . ( '' === $r['scope'] ? '—' : zad_city_label( $r['scope'] ) . ' (' . $r['src'] . ')' ) . ' | ' . $r['kids'] . ' | ' . $r['city_kids'] . ' | ' . ( 'غير معروفة' === $extra ? implode( '، ', $r['guess'] ?? array() ) : implode( '، ', array_map( 'zad_city_label', $r['kid_cities'] ) ) ) . " |\n";
		}
	};
	$tbl( 'أ) ستصير «قسم» (بلا مدينة + تحتها صفحات بمدن)', $pg['sections'], 'مدن الأبناء' );
	$tbl( 'ب) تبقى «خدمة» لأن لها مدينة (حتى لو تحتها أحياء)', $pg['has_city'], 'مدن الأبناء' );
	$tbl( 'ج) ⚠️ مشتبه: بلا مدينة ومعروف أن أبناءها أسماء مدن غير موجودة في قائمة المدن (تبقى «خدمة» حتى تُضاف المدن من «مدن الخدمات»)', $pg['suspect'], 'مدن مكتشفة بالاسم' );
	$tbl( 'د) تبقى «خدمة» (بلا مدينة وأبناؤها بلا مدن — صفحات فرعية عادية)', $pg['no_city_kids'], 'مدن الأبناء' );

	/* 4) title collisions with services */
	$m .= "\n## 4) تطابق عنوان قسم مع عنوان صفحة خدمة\n";
	$sec = array(); // key => [label, where]
	foreach ( $rows as $r ) { $sec[ zad_hr_key( $r['h1'] ) ][] = 'H1 أرشيف `' . $r['pt'] . '`'; }
	foreach ( $pg['sections'] as $r ) { $sec[ zad_hr_key( $r['p']->post_title ) ][] = 'قسم هرمي #' . $r['p']->ID; }
	foreach ( $terms as $t ) { if ( in_array( $t['tax'], array( 'service_cat', 'service_area' ), true ) ) { $sec[ zad_hr_key( $t['h1'] ) ][] = 'H1 ' . $t['tax'] . ' «' . $t['term']->name . '»'; } }
	$hit = 0;
	$all = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true, 'no_found_rows' => true, 'zad_all' => true ) );
	$sec_ids = array(); foreach ( $pg['sections'] as $r ) { $sec_ids[ $r['p']->ID ] = 1; }
	foreach ( $all as $p ) {
		if ( isset( $sec_ids[ $p->ID ] ) ) { continue; }
		$k = zad_hr_key( $p->post_title ); $y = zad_hr_key( (string) get_post_meta( $p->ID, '_yoast_wpseo_title', true ) );
		foreach ( array( $k => 'عنوان الصفحة (H1)', $y => 'عنوان Yoast' ) as $kk => $what ) {
			if ( '' !== $kk && isset( $sec[ $kk ] ) ) { $hit++; $m .= '- ' . $what . ' للصفحة #' . $p->ID . ' «' . zad_hr_md( $p->post_title ) . '» (' . zad_hr_md( rawurldecode( (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH ) ) ) . ') = ' . implode( ' و', array_unique( $sec[ $kk ] ) ) . "\n"; }
		}
	}
	if ( ! $hit ) { $m .= "لا توجد صفحة خدمة بعنوان مطابق لعنوان قسم.\n"; }
	// section vs section
	foreach ( $sec as $k => $w ) { if ( '' !== $k && count( $w ) > 1 ) { $m .= '- ⚠️ عنوان واحد لأكثر من قسم: «' . zad_hr_md( $k ) . '» ← ' . implode( ' و', $w ) . "\n"; } }

	/* 5) what the site really serves (each URL is fetched) */
	$m .= "\n## 5) ما يخدمه الموقع فعلاً (جلب كل رابط)\nCanonical مخالف للرابط = مشكلة. «تصميم القسم» = الصفحة تعرض قالب الأقسام الجديد.\n";
	$urls = array();
	foreach ( $rows as $r ) { if ( $r['url'] ) { $urls[ $r['url'] ] = 'أرشيف ' . $r['pt']; } }
	foreach ( $terms as $t ) { $urls[ $t['url'] ] = $t['tax'] . ' «' . $t['term']->name . '»'; }
	foreach ( $pg['sections'] as $r ) { $urls[ $r['url'] ] = 'قسم هرمي #' . $r['p']->ID; }
	foreach ( $pg['has_city'] as $r ) { if ( count( $urls ) < 70 ) { $urls[ $r['url'] ] = 'خدمة بمدينة #' . $r['p']->ID . ' (يجب أن تبقى خدمة)'; } }
	if ( $cp ) { $urls[ get_permalink( $cp ) ] = 'صفحة /cities/'; }
	$m .= "| الصفحة | الحالة | H1 | title | robots | canonical | تصميم القسم (كروت) | schema |\n|---|---|---|---|---|---|---|---|\n";
	foreach ( array_slice( $urls, 0, 70, true ) as $u => $what ) {
		$f = zad_hr_fetch( $u );
		if ( isset( $f['err'] ) ) { $m .= '| ' . zad_hr_md( $what . ' ' . rawurldecode( $u ) ) . ' | تعذّر الجلب: ' . zad_hr_md( $f['err'] ) . " | | | | | | |\n"; continue; }
		$bad = $f['canon'] && untrailingslashit( rawurldecode( $f['canon'] ) ) !== untrailingslashit( rawurldecode( $u ) );
		$m .= '| ' . zad_hr_md( $what . ' — ' . rawurldecode( (string) wp_parse_url( $u, PHP_URL_PATH ) ) ) . ' | ' . $f['code'] . ' | ' . zad_hr_md( $f['h1'] ) . ' | ' . zad_hr_md( $f['title'] ) . ' | ' . zad_hr_md( $f['robots'] ) . ' | ' . ( $bad ? '⚠️ ' . zad_hr_md( rawurldecode( $f['canon'] ) ) : 'نفسها' ) . ' | ' . ( $f['hub'] ? 'نعم / ' . $f['cards'] : 'لا' ) . ' | ' . implode( ',', $f['schema'] ) . " |\n";
	}
	$h1c = array();
	$m .= "\n---\nللقراءة فقط: هذه الأداة لا تغيّر أي صفحة أو إعداد.\n";
	return $m;
}

add_action( 'admin_menu', function () { add_management_page( 'تقرير صفحات الأقسام (Hubs)', 'تقرير صفحات الأقسام (Hubs)', 'manage_options', 'zad-hubs-report', function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$md = zad_hr_report();
	echo '<div class="wrap" dir="rtl"><h1>تقرير صفحات الأقسام (Hubs)</h1><div class="notice notice-info inline"><p>للقراءة فقط — لا تغيّر شيئاً. انسخ التقرير وأرسله.</p></div>';
	echo '<p><button type="button" class="button button-primary" id="zad-hr-copy">نسخ التقرير</button></p><textarea id="zad-hr-md" readonly rows="40" style="width:100%;font-family:monospace;direction:rtl">' . esc_textarea( $md ) . '</textarea>';
	echo '<script>document.getElementById("zad-hr-copy").addEventListener("click",function(){var t=document.getElementById("zad-hr-md");t.select();try{document.execCommand("copy");this.textContent="تم النسخ ✔";}catch(e){}});</script></div>';
} ); } );
