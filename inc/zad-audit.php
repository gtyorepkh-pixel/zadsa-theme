<?php
/**
 * SEO audit (Tools → فحص السيو). Read-only report: it never changes a page.
 * Also: optional noindex for low-value archives (all OFF by default).
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'فحص السيو', 'فحص السيو (زاد)', 'manage_options', 'zad-audit', 'zad_audit_page' );
} );

function zad_audit_issues() {
	return array(
		'no_desc'   => array( 'بلا وصف ميتا مخصص', 'hi' ),
		'dup_title' => array( 'عنوان مكرر', 'hi' ),
		'dup_desc'  => array( 'وصف مكرر', 'hi' ),
		'thin'      => array( 'محتوى قليل', 'hi' ),
		'orphan'    => array( 'يتيمة: لا رابط يصل إليها', 'hi' ),
		'h1'        => array( 'H1 داخل المحتوى (القالب يضع H1)', 'hi' ),
		'alt'       => array( 'صور بلا نص بديل', 'lo' ),
		'title_len' => array( 'طول العنوان غير مناسب', 'lo' ),
		'desc_len'  => array( 'طول الوصف غير مناسب', 'lo' ),
		'no_out'    => array( 'بلا روابط داخلية داخل المحتوى', 'lo' ),
	);
}

function zad_audit_path( $url ) {
	$p = wp_parse_url( (string) $url );
	if ( ! $p ) { return ''; }
	if ( ! empty( $p['host'] ) ) {
		$h = wp_parse_url( home_url() );
		if ( empty( $h['host'] ) || strtolower( $p['host'] ) !== strtolower( $h['host'] ) ) { return ''; }
	}
	return '/' . trim( rawurldecode( $p['path'] ?? '/' ), '/' ) . '/';
}

function zad_audit_flat_text( $v ) {
	if ( is_array( $v ) ) { $o = ''; foreach ( $v as $x ) { $o .= ' ' . zad_audit_flat_text( $x ); } return $o; }
	return is_string( $v ) && mb_strlen( $v ) > 20 && ! preg_match( '#^https?://#', $v ) && ! preg_match( '/^[\d,\s]+$/', $v ) ? $v : '';
}

function zad_audit_scan() {
	$types = array_values( array_unique( array_merge( zad_service_types(), zad_faq_types(), zad_article_types(), array( 'page' ) ) ) );
	$posts = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => 1000, 'has_password' => false, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'zad_all' => true ) );
	$min   = max( 50, (int) zad_opt( 'zad_audit_min_words', 300 ) );
	$front = (int) get_option( 'page_on_front' );

	$map = array(); $info = array();
	foreach ( $posts as $p ) {
		if ( get_post_meta( $p->ID, '_zad_seo_noindex', true ) || '1' === (string) get_post_meta( $p->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) { continue; }
		$map[ zad_audit_path( get_permalink( $p ) ) ] = $p->ID;
		$info[ $p->ID ] = $p;
	}
	$inbound = array_fill_keys( array_keys( $info ), 0 );
	$out_cnt = array_fill_keys( array_keys( $info ), 0 );
	$count_links = function ( $html, $from ) use ( &$inbound, &$out_cnt, $map ) {
		if ( ! $html || false === stripos( $html, 'href' ) ) { return; }
		if ( preg_match_all( '/href\s*=\s*["\']([^"\'#]+)["\']/i', $html, $m ) ) {
			foreach ( array_unique( $m[1] ) as $href ) {
				$pa = zad_audit_path( $href );
				if ( $pa && isset( $map[ $pa ] ) && $map[ $pa ] !== $from ) {
					$inbound[ $map[ $pa ] ]++;
					if ( $from ) { $out_cnt[ $from ]++; }
				}
			}
		}
	};
	foreach ( $info as $id => $p ) {
		$count_links( $p->post_content, $id );
		if ( $p->post_parent && isset( $info[ $p->post_parent ] ) ) { $inbound[ $id ]++; } // the parent page lists its children
	}
	foreach ( (array) wp_get_nav_menus() as $menu ) {
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $it ) {
			$pa = zad_audit_path( $it->url );
			if ( $pa && isset( $map[ $pa ] ) ) { $inbound[ $map[ $pa ] ]++; }
		}
	}

	$titles = array(); $descs = array();
	$rows = array();
	foreach ( $info as $id => $p ) {
		$t = trim( (string) get_post_meta( $id, '_zad_seo_title', true ) );
		if ( '' === $t ) { $t = trim( (string) get_post_meta( $id, '_yoast_wpseo_title', true ) ); }
		if ( '' === $t || false !== strpos( $t, '%%' ) ) { $t = get_the_title( $p ); }
		$d = trim( (string) get_post_meta( $id, '_zad_seo_desc', true ) );
		if ( '' === $d ) { $d = trim( (string) get_post_meta( $id, '_zad_seo_description', true ) ); }
		if ( '' === $d ) { $d = trim( (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ) ); }
		if ( false !== strpos( $d, '%%' ) ) { $d = ''; }
		$titles[ mb_strtolower( $t ) ][] = $id;
		if ( '' !== $d ) { $descs[ mb_strtolower( $d ) ][] = $id; }
		$rows[ $id ] = array( 't' => $t, 'd' => $d, 'issues' => array() );
	}
	foreach ( $rows as $id => &$r ) {
		$p = $info[ $id ]; $c = $p->post_content;
		if ( '' === $r['d'] ) { $r['issues'][] = 'no_desc'; } elseif ( mb_strlen( $r['d'] ) < 70 || mb_strlen( $r['d'] ) > 165 ) { $r['issues'][] = 'desc_len'; }
		if ( mb_strlen( $r['t'] ) < 15 || mb_strlen( $r['t'] ) > 65 ) { $r['issues'][] = 'title_len'; }
		if ( count( $titles[ mb_strtolower( $r['t'] ) ] ) > 1 ) { $r['issues'][] = 'dup_title'; }
		if ( '' !== $r['d'] && count( $descs[ mb_strtolower( $r['d'] ) ] ) > 1 ) { $r['issues'][] = 'dup_desc'; }
		if ( false !== stripos( $c, '<h1' ) ) { $r['issues'][] = 'h1'; }
		$bad = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $c, $im ) ) { foreach ( $im[0] as $tag ) { if ( ! preg_match( '/\balt\s*=\s*["\'][^"\']+["\']/i', $tag ) ) { $bad++; } } }
		$th = get_post_thumbnail_id( $id );
		if ( $th && '' === trim( (string) get_post_meta( $th, '_wp_attachment_image_alt', true ) ) ) { $bad++; }
		if ( $bad ) { $r['issues'][] = 'alt'; }
		$text = wp_strip_all_tags( strip_shortcodes( $c ) );
		foreach ( get_post_meta( $id ) as $k => $vals ) { if ( 0 === strpos( $k, '_zad_' ) ) { foreach ( $vals as $v ) { $text .= ' ' . zad_audit_flat_text( maybe_unserialize( $v ) ); } } }
		$words = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
		$r['words'] = $words;
		if ( $words < $min ) { $r['issues'][] = 'thin'; }
		if ( $id !== $front && 0 === $inbound[ $id ] ) { $r['issues'][] = 'orphan'; }
		if ( 0 === $out_cnt[ $id ] && 'post' !== $p->post_type ) { $r['issues'][] = 'no_out'; }
		$r['type'] = $p->post_type; $r['in'] = $inbound[ $id ]; $r['out'] = $out_cnt[ $id ];
	}
	unset( $r );
	return array( 'rows' => $rows, 'time' => time(), 'min' => $min );
}

function zad_audit_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	if ( isset( $_POST['zad_audit_rescan'] ) && check_admin_referer( 'zad_audit' ) ) { delete_transient( 'zad_audit_cache' ); }
	$data = get_transient( 'zad_audit_cache' );
	if ( ! $data ) { $data = zad_audit_scan(); set_transient( 'zad_audit_cache', $data, 15 * MINUTE_IN_SECONDS ); }
	$issues = zad_audit_issues();
	$sel    = isset( $_GET['issue'] ) ? sanitize_key( wp_unslash( $_GET['issue'] ) ) : ''; // phpcs:ignore
	$counts = array_fill_keys( array_keys( $issues ), 0 );
	foreach ( $data['rows'] as $r ) { foreach ( $r['issues'] as $i ) { $counts[ $i ]++; } }
	$base = admin_url( 'tools.php?page=zad-audit' );
	echo '<div class="wrap" dir="rtl"><h1>فحص السيو</h1><p>تقرير للقراءة فقط: لا يغيّر أي صفحة. فُحصت <b>' . count( $data['rows'] ) . '</b> صفحة منشورة (غير المستبعدة من الفهرسة) · آخر فحص ' . esc_html( human_time_diff( $data['time'] ) ) . ' مضت · حد المحتوى القليل: ' . (int) $data['min'] . ' كلمة (من إعدادات الثيم).</p>';
	echo '<form method="post" style="margin:8px 0">'; wp_nonce_field( 'zad_audit' ); echo '<button class="button" name="zad_audit_rescan" value="1">إعادة الفحص</button></form>';
	echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin:12px 0">';
	foreach ( $issues as $k => $i ) {
		$on = $sel === $k; $c = $counts[ $k ];
		echo '<a href="' . esc_url( add_query_arg( 'issue', $k, $base ) ) . '" style="text-decoration:none;min-width:150px;padding:10px 14px;border-radius:8px;border:2px solid ' . ( $on ? '#0c687e' : '#dcdcde' ) . ';background:#fff;color:#1d2327"><b style="font-size:22px;color:' . ( $c ? ( 'hi' === $i[1] ? '#b91c1c' : '#b45f00' ) : '#15803d' ) . '">' . (int) $c . '</b><br>' . esc_html( $i[0] ) . '</a>';
	}
	echo '</div>';
	$list = array();
	foreach ( $data['rows'] as $id => $r ) { if ( ! $sel && ! $r['issues'] ) { continue; } if ( $sel && ! in_array( $sel, $r['issues'], true ) ) { continue; } $list[ $id ] = $r; }
	uasort( $list, function ( $a, $b ) { return count( $b['issues'] ) <=> count( $a['issues'] ); } );
	echo '<p>' . ( $sel && isset( $issues[ $sel ] ) ? 'المشكلة: <b>' . esc_html( $issues[ $sel ][0] ) . '</b> — ' : 'الصفحات التي فيها أي مشكلة — ' ) . count( $list ) . ' صفحة. <a href="' . esc_url( $base ) . '">إلغاء التصفية</a></p>';
	echo '<table class="widefat striped"><thead><tr><th>الصفحة</th><th>النوع</th><th>كلمات</th><th>روابط واردة/صادرة</th><th>المشاكل</th></tr></thead><tbody>';
	foreach ( array_slice( $list, 0, 300, true ) as $id => $r ) {
		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $id, 'raw' ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( $r['type'] ) . '</td><td>' . (int) $r['words'] . '</td><td>' . (int) $r['in'] . ' / ' . (int) $r['out'] . '</td><td>';
		foreach ( $r['issues'] as $i ) { echo '<span style="display:inline-block;margin:1px 3px;padding:1px 8px;border-radius:99px;background:' . ( 'hi' === $issues[ $i ][1] ? '#fee2e2' : '#fef3c7' ) . ';font-size:12px">' . esc_html( $issues[ $i ][0] ) . '</span>'; }
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">ملاحظات: «يتيمة» تعني أن لا صفحة منشورة ولا قائمة تربط إليها (الصفحة الفرعية تُحسب مرتبطة بأمها). «محتوى قليل» يحسب النص وحقول الخدمة/الحي معاً. الروابط التي يولّدها القالب تلقائياً (البطاقات والأحياء المجاورة) غير محسوبة في «الصادرة».</p></div>';
}

/* ------------------------------------------------------------------ */
/* Optional noindex for low-value archives (all OFF by default)         */
/* ------------------------------------------------------------------ */
add_filter( 'wp_robots', function ( $r ) {
	$hit = ( zad_opt( 'zad_noindex_tag', false ) && is_tag() )
		|| ( zad_opt( 'zad_noindex_cat', false ) && is_category() )
		|| ( zad_opt( 'zad_noindex_author', false ) && is_author() )
		|| ( zad_opt( 'zad_noindex_date', false ) && is_date() )
		|| ( zad_opt( 'zad_noindex_paged', false ) && is_paged() && ( is_archive() || is_home() ) );
	if ( $hit ) {
		$r['noindex'] = true;
		$r['follow']  = true;
		unset( $r['index'], $r['max-image-preview'], $r['max-snippet'], $r['max-video-preview'] );
	}
	return $r;
}, 30 );
