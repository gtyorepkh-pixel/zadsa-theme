<?php defined( 'ABSPATH' ) || exit;
/**
 * Tool pages: every tool is an ordinary WordPress Page that uses the template «أداة زاد» (or the [zad_tool] shortcode).
 * The editor chooses the parent page and the slug. The page is rendered on the server in this fixed order (§0.4):
 *   1 H1 + one line · 2 the tool (a server-rendered form / reference table; JS only enhances) · 3 «إزاي بنحسب» (formula + the parameters table,
 *   pulled live from the settings) · 4 worked examples (computed on the server by the same formula) · 5 FAQ (a field of the page) · 6 CTA + internal links.
 *
 * A tool module registers itself:
 *   zt_register_tool( 'ac-size', array(
 *     'title' => 'حاسبة حجم المكيف', 'desc' => '…', 'settings_tab' => 'ac-size',
 *     'render'   => callable( $ctx )  → prints the tool's HTML (with a no-JS fallback),
 *     'how'      => callable( $ctx )  → returns html (formula + parameters table),
 *     'examples' => callable( $ctx )  → returns html,
 *     'js' => 'assets/js/ac-size.js', 'css' => '…', 'service_keywords' => array( … ),
 *   ) );
 */

function zt_tools() {
	static $tools = null;
	if ( null === $tools ) { $tools = apply_filters( 'zad_tools_register', array() ); }
	return $tools;
}
/** Convenience for modules: hook into the filter. */
function zt_register_tool( $slug, $def ) {
	add_filter( 'zad_tools_register', function ( $t ) use ( $slug, $def ) { $t[ $slug ] = $def + array( 'settings_tab' => $slug ); return $t; } );
}

/* ---------------------------------------------- template + page settings ---------------------------------------------- */

const ZT_TEMPLATE = 'zt-tool-page.php';

add_filter( 'theme_page_templates', function ( $t ) { $t[ ZT_TEMPLATE ] = 'أداة زاد (zad-tools)'; return $t; } );
add_filter( 'template_include', function ( $tpl ) {
	if ( is_page() && ZT_TEMPLATE === get_page_template_slug( get_queried_object_id() ) ) {
		$theme = locate_template( 'zad-tools/tool-page.php' ); // the theme may override the markup
		return $theme ? $theme : ZT_DIR . 'templates/tool-page.php';
	}
	return $tpl;
}, 30 );

/** Is the current request a tool page (template or shortcode)? */
function zt_is_tool_page( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_queried_object_id();
	if ( ! $id || 'page' !== get_post_type( $id ) ) { return false; }
	if ( ZT_TEMPLATE === get_page_template_slug( $id ) ) { return true; }
	return (bool) get_post_meta( $id, '_zt_tool', true ) && has_shortcode( (string) get_post_field( 'post_content', $id ), 'zad_tool' );
}
function zt_page_tool( $id ) { return (string) get_post_meta( (int) $id, '_zt_tool', true ); }

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zt_tool', 'أداة زاد في هذه الصفحة', 'zt_metabox', 'page', 'normal', 'high' );
} );

function zt_metabox( $post ) {
	wp_nonce_field( 'zt_tool_box', 'zt_tool_nonce' );
	$g = function ( $k ) use ( $post ) { return get_post_meta( $post->ID, '_zt_' . $k, true ); };
	$tools = zt_tools();
	echo '<p>اختر القالب «أداة زاد (zad-tools)» من «سمات الصفحة»، أو ضع <code>[zad_tool]</code> في المحتوى. الصفحة الأم والرابط من عندك.</p><table class="form-table"><tbody>';
	echo '<tr><th>الأداة</th><td><select name="zt[tool]"><option value="">— اختر —</option>';
	foreach ( $tools as $slug => $t ) { echo '<option value="' . esc_attr( $slug ) . '"' . selected( $g( 'tool' ), $slug, false ) . '>' . esc_html( $t['title'] ) . '</option>'; }
	echo '</select>' . ( $tools ? '' : ' <em>لا توجد أدوات مسجّلة بعد.</em>' ) . '</td></tr>';
	echo '<tr><th>سطر تحت العنوان</th><td><input class="large-text" name="zt[intro]" value="' . esc_attr( $g( 'intro' ) ) . '" placeholder="الأداة بتعمل إيه في جملة واحدة"></td></tr>';
	echo '<tr><th>أسئلة شائعة</th><td><textarea class="large-text" rows="6" name="zt[faq]" placeholder="السؤال | الجواب  (سطر لكل سؤال)">' . esc_textarea( $g( 'faq' ) ) . '</textarea></td></tr>';
	echo '<tr><th>صفحات الخدمات المرتبطة</th><td><input class="regular-text" dir="ltr" name="zt[services]" value="' . esc_attr( $g( 'services' ) ) . '" placeholder="أرقام الصفحات مفصولة بفاصلة، مثال: 123,456"><p class="description">أول صفحة هي زر «اطلب الخدمة»، والباقي روابط داخلية.</p></td></tr>';
	echo '<tr><th>نص زر الخدمة</th><td><input class="regular-text" name="zt[cta]" value="' . esc_attr( $g( 'cta' ) ) . '" placeholder="اطلب الخدمة الآن"></td></tr>';
	echo '</tbody></table>';
}

add_action( 'save_post_page', function ( $id ) {
	if ( ! isset( $_POST['zt_tool_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zt_tool_nonce'] ) ), 'zt_tool_box' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_autosave( $id ) ) { return; }
	$in = isset( $_POST['zt'] ) ? (array) wp_unslash( $_POST['zt'] ) : array();
	$tool = isset( $in['tool'] ) ? sanitize_key( $in['tool'] ) : '';
	update_post_meta( $id, '_zt_tool', isset( zt_tools()[ $tool ] ) ? $tool : '' );
	update_post_meta( $id, '_zt_intro', sanitize_text_field( $in['intro'] ?? '' ) );
	update_post_meta( $id, '_zt_faq', sanitize_textarea_field( $in['faq'] ?? '' ) );
	update_post_meta( $id, '_zt_services', implode( ',', array_filter( array_map( 'absint', explode( ',', zt_digits_en( (string) ( $in['services'] ?? '' ) ) ) ) ) ) );
	update_post_meta( $id, '_zt_cta', sanitize_text_field( $in['cta'] ?? '' ) );
} );

/* ---------------------------------------------- context + rendering ---------------------------------------------- */

/** Everything a tool module and the template need about the page. */
function zt_ctx( $id ) {
	$id  = (int) $id;
	$svc = array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_zt_services', true ) ) ), function ( $s ) { return 'publish' === get_post_status( $s ); } ) );
	$faq = array();
	foreach ( zt_table( get_post_meta( $id, '_zt_faq', true ) ) as $r ) { if ( count( $r ) >= 2 && '' !== $r[0] && '' !== $r[1] ) { $faq[] = array( $r[0], implode( ' | ', array_slice( $r, 1 ) ) ); } }
	$tool = zt_page_tool( $id );
	$reg  = zt_tools();
	return array(
		'id' => $id, 'tool' => $tool, 'def' => $reg[ $tool ] ?? null, 'title' => get_the_title( $id ), 'url' => get_permalink( $id ),
		'intro' => (string) get_post_meta( $id, '_zt_intro', true ), 'faq' => $faq, 'services' => $svc,
		'cta' => (string) get_post_meta( $id, '_zt_cta', true ),
		// a tool that stores personal data (collects_data) also waits for the general privacy values (retention, limits) to be approved
		'ready' => $tool && isset( $reg[ $tool ] ) ? ( zt_tool_ready( $reg[ $tool ]['settings_tab'] ?? $tool ) && ( empty( $reg[ $tool ]['collects_data'] ) || zt_tool_ready( 'general' ) ) ) : false,
	);
}

/** The body of a tool page (sections 2–6; the template prints the H1 around it). */
function zt_render_body( $ctx ) {
	$def = $ctx['def'];
	if ( ! $def ) {
		if ( current_user_can( 'edit_post', $ctx['id'] ) ) { echo '<div class="wrap wrap--narrow"><p><strong>اختر الأداة من صندوق «أداة زاد في هذه الصفحة».</strong></p></div>'; }
		return;
	}
	if ( ! $ctx['ready'] ) {
		echo '<section class="sec"><div class="wrap wrap--narrow"><p class="zt-note"><strong>هذه الأداة قيد المراجعة</strong> وستُفتح بعد اعتماد قيمها.</p></div></section>';
		if ( current_user_can( 'manage_options' ) ) { echo '<div class="wrap wrap--narrow"><p>للمدير: <a href="' . esc_url( admin_url( 'admin.php?page=zad-tools&tab=overview' ) ) . '">اعتمد القيم المطلوبة</a> لتظهر الأداة للزوّار.</p></div>'; }
		return;
	}
	$banner = zt_active_banner();
	if ( '' !== $banner ) { echo '<div class="wrap wrap--narrow"><p class="zt-banner" role="note">' . esc_html( $banner ) . '</p></div>'; }
	// 2 · the tool
	echo '<section class="sec zt-sec" id="zt-tool" data-zt-tool="' . esc_attr( $ctx['tool'] ) . '"><div class="wrap wrap--narrow">';
	if ( is_callable( $def['render'] ?? null ) ) { call_user_func( $def['render'], $ctx ); }
	echo '<div class="zt-result" id="zt-result" role="status" aria-live="polite" aria-atomic="true"></div>'; // the space is reserved: the result never shifts the page (CLS)
	echo '</div></section>';
	// 3 · how we calculate
	if ( is_callable( $def['how'] ?? null ) ) {
		$h = (string) call_user_func( $def['how'], $ctx );
		if ( '' !== $h ) { echo '<section class="sec sec--tint zt-sec"><div class="wrap wrap--narrow"><header class="sec__head"><h2>إزاي بنحسب</h2></header>' . wp_kses_post( $h ) . '</div></section>'; }
	}
	// 4 · worked examples
	if ( is_callable( $def['examples'] ?? null ) ) {
		$e = (string) call_user_func( $def['examples'], $ctx );
		if ( '' !== $e ) { echo '<section class="sec zt-sec"><div class="wrap wrap--narrow"><header class="sec__head"><h2>أمثلة محسوبة</h2></header>' . wp_kses_post( $e ) . '</div></section>'; }
	}
	// 5 · FAQ
	if ( $ctx['faq'] ) {
		echo '<section class="sec sec--tint zt-sec"><div class="wrap wrap--narrow"><header class="sec__head"><h2>أسئلة شائعة</h2></header><div class="zt-faq">';
		foreach ( $ctx['faq'] as $f ) { echo '<details><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[1] ) . '</p></details>'; }
		echo '</div></div></section>';
	}
	// 6 · CTA + internal links
	zt_render_cta( $ctx );
}

function zt_render_cta( $ctx ) {
	if ( ! $ctx['services'] ) { return; }
	$first = $ctx['services'][0];
	$label = '' !== $ctx['cta'] ? $ctx['cta'] : 'اطلب الخدمة الآن';
	echo '<section class="sec zt-sec"><div class="wrap wrap--narrow"><p><a class="btn btn--accent" href="' . esc_url( get_permalink( $first ) ) . '" data-zt-event="tool_cta">' . esc_html( $label ) . '</a></p>';
	if ( count( $ctx['services'] ) > 1 ) {
		echo '<ul class="zt-links">';
		foreach ( array_slice( $ctx['services'], 1, 6 ) as $sid ) { echo '<li><a href="' . esc_url( get_permalink( $sid ) ) . '">' . esc_html( function_exists( 'zad_card_title' ) ? zad_card_title( $sid ) : get_the_title( $sid ) ) . '</a></li>'; }
		echo '</ul>';
	}
	echo '</div></section>';
}

function zt_active_banner() {
	$t = trim( (string) zt_opt( 'general.tool_banner' ) );
	if ( '' === $t ) { return ''; }
	$today = wp_date( 'Y-m-d' ); $f = (string) zt_opt( 'general.tool_banner_from' ); $to = (string) zt_opt( 'general.tool_banner_to' );
	if ( ( '' !== $f && $today < $f ) || ( '' !== $to && $today > $to ) ) { return ''; }
	return $t;
}

add_shortcode( 'zad_tool', function () {
	$id = get_the_ID();
	if ( ! $id ) { return ''; }
	ob_start();
	zt_render_body( zt_ctx( $id ) );
	return ob_get_clean();
} );

/* ---------------------------------------------- assets: only on tool pages ---------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'page' ) || ! zt_is_tool_page() ) { return; }
	$ctx = zt_ctx( get_queried_object_id() );
	wp_enqueue_style( 'zt-tool', ZT_URL . 'assets/css/zt-tool.css', array(), ZT_VERSION );
	wp_enqueue_script( 'zt-core', ZT_URL . 'assets/js/zt-core.js', array(), ZT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'zt-core', 'ZT_CFG', array(
		'tool' => $ctx['tool'], 'wa' => zt_wa_number(), 'rest' => esc_url_raw( rest_url( 'zad/v1/' ) ), 'ga' => (bool) zt_opt( 'general.ga_events' ),
		'brand' => zt_brand(), 'url' => $ctx['url'], 'privacy' => zt_privacy_url(),
	) );
	$def = $ctx['def'];
	if ( $def && $ctx['ready'] ) {
		if ( ! empty( $def['css'] ) ) { wp_enqueue_style( 'zt-' . $ctx['tool'], $def['css'], array( 'zt-tool' ), ZT_VERSION ); }
		if ( ! empty( $def['js'] ) ) { wp_enqueue_script( 'zt-' . $ctx['tool'], $def['js'], array( 'zt-core' ), ZT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) ); }
	}
} );

function zt_privacy_url() {
	$id = (int) zt_opt( 'general.privacy_page_id' );
	if ( $id && 'publish' === get_post_status( $id ) ) { return get_permalink( $id ); }
	if ( function_exists( 'zad_legal_items' ) ) { foreach ( zad_legal_items() as $it ) { if ( false !== mb_strpos( $it[0], 'الخصوصية' ) ) { return $it[1]; } } }
	return function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
}

/* ---------------------------------------------- SEO: canonical, breadcrumbs, schema ---------------------------------------------- */

/** A result link carries ?… parameters: its canonical is always the plain page (the theme prints the canonical from the page URL, never from the query string). */
add_filter( 'zad_current_crumbs', function ( $c ) {
	if ( ! is_singular( 'page' ) || ! zt_is_tool_page() ) { return $c; }
	$id = get_queried_object_id(); $out = array( array( 'الرئيسية', home_url( '/' ) ) );
	foreach ( array_reverse( get_post_ancestors( $id ) ) as $a ) { $out[] = array( get_the_title( $a ), get_permalink( $a ) ); }
	$out[] = array( get_the_title( $id ), '' );
	return $out;
} );

/** WebApplication (+ FAQPage) join the theme's single @graph through the theme's filter; no theme or Yoast node is repeated. */
add_filter( 'zad_schema_page_nodes', function ( $nodes, $id ) {
	if ( ! zt_is_tool_page( $id ) ) { return $nodes; }
	$ctx = zt_ctx( $id );
	if ( ! $ctx['def'] || ! $ctx['ready'] ) { return $nodes; }
	return array_merge( $nodes, zt_schema_nodes( $ctx ) );
}, 10, 2 );

function zt_schema_nodes( $ctx ) {
	$u    = $ctx['url']; $home = home_url( '/' );
	$desc = '' !== $ctx['intro'] ? $ctx['intro'] : (string) ( $ctx['def']['desc'] ?? '' );
	$app  = array(
		'@type' => 'WebApplication', '@id' => $u . '#webapp', 'name' => $ctx['title'], 'url' => $u,
		'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any', 'isAccessibleForFree' => true, 'inLanguage' => 'ar-SA',
		'provider' => array( '@id' => $home . '#localbusiness' ), 'mainEntityOfPage' => array( '@id' => $u . '#webpage' ),
	);
	if ( '' !== $desc ) { $app['description'] = $desc; }
	$out = array( $app );
	if ( $ctx['faq'] ) {
		$ents = array();
		foreach ( $ctx['faq'] as $f ) { $ents[] = array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) ); }
		$out[] = array( '@type' => 'FAQPage', '@id' => $u . '#faq', 'mainEntity' => $ents, 'isPartOf' => array( '@id' => $u . '#webpage' ) );
	}
	return $out;
}

/** Fallback when the theme does not own the schema (another plugin prints it): one small script, never both. */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'page' ) || ! zt_is_tool_page() || ( function_exists( 'zad_schema_owner' ) && 'theme' === zad_schema_owner() ) ) { return; }
	$ctx = zt_ctx( get_queried_object_id() );
	if ( ! $ctx['def'] || ! $ctx['ready'] ) { return; }
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => zt_schema_nodes( $ctx ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 21 );

/** noindex helper for utility pages (tracking / verification, Phase 1) and for a tool that is not approved yet. */
add_filter( 'wp_robots', function ( $r ) {
	if ( is_singular( 'page' ) && zt_is_tool_page() ) {
		$ctx = zt_ctx( get_queried_object_id() );
		if ( $ctx['tool'] && ! $ctx['ready'] ) { $r['noindex'] = true; $r['nofollow'] = true; }
	}
	return $r;
} );
