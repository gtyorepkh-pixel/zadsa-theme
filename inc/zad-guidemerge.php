<?php defined( 'ABSPATH' ) || exit;
/**
 * «دمج الأدلة في الأقسام» (الأدوات ← دمج الأدلة في الأقسام) — المرحلة 1: الجرد (قراءة فقط، لا يكتب شيئاً).
 * القرار: نوع «الأقسام» (sections، الرابط /sections/) هو المدونة الوحيدة؛ كل محتوى «الأدلة» (guide) ينتقل إليه ثم يُزال النوع.
 * هذه الشاشة تجمع من قاعدة البيانات ما يلزم قبل أي تنفيذ: الأدلة بكل حالاتها، تعارض الـ slug، تصنيفات best_guide ومقابلها في best_sections،
 * الـ meta وYoast، وكل مكان يذكر /guide/ (محتوى، meta، خيارات، قوائم، تحويلات). ثم تُنتج تقريراً نصياً (Markdown) للنسخ.
 */

define( 'ZAD_GM_FROM', 'guide' );
define( 'ZAD_GM_TO', 'sections' );

function zad_gm_post_url( $p ) {
	if ( 'publish' !== $p->post_status ) { return '' === $p->post_name ? '' : home_url( '/guide/' . $p->post_name . '/' ); } // not live: the URL it would have
	return post_type_exists( ZAD_GM_FROM ) ? get_permalink( (int) $p->ID ) : home_url( '/guide/' . $p->post_name . '/' );
}

/** Everything the report needs. Pure reads. */
function zad_gm_inventory() {
	global $wpdb;
	$F = ZAD_GM_FROM; $T = ZAD_GM_TO; $inv = array();

	$inv['types'] = array();
	foreach ( array( $F, $T ) as $t ) {
		$o = get_post_type_object( $t );
		$inv['types'][ $t ] = $o ? array( 'registered' => true, 'supports' => array_keys( array_filter( get_all_post_type_supports( $t ) ) ), 'hier' => (bool) $o->hierarchical, 'slug' => is_array( $o->rewrite ) ? ( $o->rewrite['slug'] ?? '' ) : '', 'taxes' => get_object_taxonomies( $t ) ) : array( 'registered' => false );
	}

	$inv['status'] = array( $F => array(), $T => array() );
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_type, post_status, COUNT(*) n FROM {$wpdb->posts} WHERE post_type IN (%s,%s) GROUP BY post_type, post_status", $F, $T ) ) as $r ) { $inv['status'][ $r->post_type ][ $r->post_status ] = (int) $r->n; }

	$guides = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_status, post_parent, post_author FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft','inherit') ORDER BY post_status, ID", $F ) );
	$inv['guides'] = array();
	foreach ( $guides as $g ) { $inv['guides'][] = array( 'id' => (int) $g->ID, 'title' => $g->post_title, 'slug' => $g->post_name, 'status' => $g->post_status, 'url' => zad_gm_post_url( $g ), 'parent' => (int) $g->post_parent ); }

	// slug conflicts (the same slug already used by a «sections» post)
	$inv['conflicts'] = array(); $inv['noslug'] = 0;
	$secs = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft','inherit') AND post_name <> ''", $T ) ) as $s ) { $secs[ $s->post_name ][] = $s; }
	foreach ( $guides as $g ) {
		if ( '' === $g->post_name ) { $inv['noslug']++; continue; }
		foreach ( $secs[ $g->post_name ] ?? array() as $s ) { $inv['conflicts'][] = array( 'guide' => (int) $g->ID, 'gtitle' => $g->post_title, 'gstatus' => $g->post_status, 'slug' => $g->post_name, 'section' => (int) $s->ID, 'stitle' => $s->post_title, 'sstatus' => $s->post_status ); }
	}

	// best_guide terms (read straight from the tables: the taxonomy may be unregistered) with the counts of guides per status + the closest best_sections term
	$terms = function ( $tax ) use ( $wpdb ) { return (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id ttid, tt.parent, tt.count FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY t.name", $tax ) ); };
	$bs = $terms( 'best_sections' );
	$inv['sections_terms'] = array_map( function ( $x ) { return array( 'id' => (int) $x->term_id, 'name' => $x->name, 'slug' => $x->slug, 'count' => (int) $x->count ); }, $bs );
	$inv['terms'] = array();
	foreach ( $terms( 'best_guide' ) as $t ) {
		$cnt = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.post_status, COUNT(*) n FROM {$wpdb->term_relationships} tr JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_type = %s GROUP BY p.post_status", $t->ttid, $F ) ) as $r ) { $cnt[ $r->post_status ] = (int) $r->n; }
		$best = zad_gm_match_term( $t, $bs );
		$inv['terms'][] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => (int) $t->parent, 'posts' => $cnt, 'match' => $best ? array( 'id' => (int) $best[0]->term_id, 'name' => $best[0]->name, 'slug' => $best[0]->slug, 'how' => $best[1] ) : null, 'archive' => home_url( '/best-guide/' . $t->slug . '/' ) );
	}
	$inv['no_term'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status NOT IN ('auto-draft','inherit') AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = 'best_guide')", $F ) );
	$inv['other_tax'] = (array) $wpdb->get_results( $wpdb->prepare( "SELECT tt.taxonomy, COUNT(DISTINCT p.ID) n FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE p.post_type = %s AND tt.taxonomy <> 'best_guide' GROUP BY tt.taxonomy", $F ), OBJECT_K );

	// post meta of the guides (Yoast / Rank Math / theme)
	$inv['meta'] = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_key k, COUNT(DISTINCT pm.post_id) n FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status NOT IN ('auto-draft','inherit') GROUP BY pm.meta_key ORDER BY n DESC, k", $F ) ) as $m ) { $inv['meta'][ $m->k ] = (int) $m->n; }
	$inv['yoast_primary'] = (int) ( $inv['meta']['_yoast_wpseo_primary_best_guide'] ?? 0 );
	$inv['seo_plugins'] = array( 'yoast' => defined( 'WPSEO_VERSION' ), 'rankmath' => class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) );
	$inv['yoast_opts'] = array();
	foreach ( array( 'wpseo_titles' ) as $on ) { $v = get_option( $on ); if ( is_array( $v ) ) { foreach ( $v as $k => $x ) { if ( false !== strpos( $k, '-guide' ) || false !== strpos( $k, 'guide-' ) || 0 === strpos( $k, 'title-ptarchive-guide' ) ) { $inv['yoast_opts'][ $k ] = is_scalar( $x ) ? (string) $x : '…'; } } } }
	$inv['yoast_tax_meta'] = array_keys( (array) ( ( get_option( 'wpseo_taxonomy_meta' ) ?: array() )['best_guide'] ?? array() ) );

	// every place that still says /guide/ or /best-guide/
	$re = '~/(?:guide|best-guide)(?:/|(?=["\'\s#?<)\]]|$))~u';
	$inv['links'] = array( 'content' => array(), 'meta' => array(), 'options' => array(), 'menu' => array(), 'content_total' => 0 );
	foreach ( (array) $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_status NOT IN ('auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item') AND ( post_content LIKE '%/guide%' OR post_content LIKE '%/best-guide%' OR post_excerpt LIKE '%/guide%' )" ) as $p ) {
		$n = preg_match_all( $re, $p->post_content . ' ' . $p->post_excerpt ); if ( ! $n ) { continue; }
		$inv['links']['content_total'] += $n; $inv['links']['content'][ $p->post_type ] = ( $inv['links']['content'][ $p->post_type ] ?? 0 ) + 1;
		$inv['links']['content_ids'][ $p->post_type ][] = (int) $p->ID;
	}
	foreach ( (array) $wpdb->get_results( "SELECT pm.meta_key k, COUNT(*) n FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type <> 'revision' AND pm.meta_value LIKE '%/guide%' AND pm.meta_key NOT LIKE '\\_oembed%' GROUP BY pm.meta_key" ) as $m ) { $inv['links']['meta'][ $m->k ] = (int) $m->n; }
	foreach ( (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE '%/guide%' AND option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%'" ) as $o ) { if ( preg_match_all( $re, (string) $o->option_value ) ) { $inv['links']['options'][ $o->option_name ] = (int) preg_match_all( $re, (string) $o->option_value ); } }
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, pm_t.meta_value type, pm_o.meta_value obj, pm_u.meta_value url, pm_i.meta_value oid FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm_t ON pm_t.post_id = p.ID AND pm_t.meta_key = '_menu_item_type' LEFT JOIN {$wpdb->postmeta} pm_o ON pm_o.post_id = p.ID AND pm_o.meta_key = '_menu_item_object'
		LEFT JOIN {$wpdb->postmeta} pm_u ON pm_u.post_id = p.ID AND pm_u.meta_key = '_menu_item_url' LEFT JOIN {$wpdb->postmeta} pm_i ON pm_i.post_id = p.ID AND pm_i.meta_key = '_menu_item_object_id'
		WHERE p.post_type = 'nav_menu_item' AND p.post_status <> 'trash' AND ( pm_o.meta_value IN (%s,'best_guide') OR pm_u.meta_value LIKE '%%/guide%%' OR pm_u.meta_value LIKE '%%/best-guide%%' )", $F ) ) as $mi ) {
		$inv['links']['menu'][] = array( 'id' => (int) $mi->ID, 'label' => $mi->post_title, 'kind' => $mi->type . '/' . $mi->obj, 'url' => (string) $mi->url, 'object' => (int) $mi->oid );
	}
	$ids = wp_list_pluck( $inv['guides'], 'id' );
	$inv['guide_picks'] = 0; // services that hand-pick a guide (_zad_guides): IDs are kept by the move, so these keep working
	foreach ( (array) $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_zad_guides'" ) as $v ) { if ( array_intersect( array_map( 'intval', (array) maybe_unserialize( $v ) ), $ids ) ) { $inv['guide_picks']++; } }

	// existing redirects (Redirection plugin)
	$inv['redirection'] = array( 'active' => function_exists( 'zad_svcc_red_active' ) && zad_svcc_red_active(), 'guide_rules' => 0, 'sections_rules' => 0 );
	$tbl = $wpdb->prefix . 'redirection_items';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
		$inv['redirection']['table'] = true;
		$inv['redirection']['guide_rules']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE url LIKE '/guide%' OR url LIKE '%/guide/%' OR url LIKE '/best-guide%' OR action_data LIKE '%/guide%'" );
		$inv['redirection']['sections_rules'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE url LIKE '/sections%' OR action_data LIKE '%/sections%'" );
	}
	$inv['comments'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.post_type = %s", $F ) );
	$inv['with_parent'] = count( array_filter( $inv['guides'], function ( $g ) { return $g['parent']; } ) );
	$inv['trash_days'] = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;
	$inv['opt'] = array( 'zad_article_slugs' => (string) zad_opt( 'zad_article_slugs', '' ), 'zad_edu_hub' => (string) zad_opt( 'zad_edu_hub', '' ), 'zad_legacy_register' => (bool) zad_opt( 'zad_legacy_register', true ) );
	return $inv;
}

/** Closest best_sections term for a best_guide term: same slug → same name → similar name (≥ 70 %). @return array|null [term, how] */
function zad_gm_match_term( $t, $cands ) {
	foreach ( $cands as $c ) { if ( $c->slug === $t->slug ) { return array( $c, 'نفس الـ slug' ); } }
	$n = zad_gm_norm_name( $t->name );
	foreach ( $cands as $c ) { if ( zad_gm_norm_name( $c->name ) === $n && '' !== $n ) { return array( $c, 'نفس الاسم' ); } }
	$best = null; $bp = 0;
	foreach ( $cands as $c ) { similar_text( $n, zad_gm_norm_name( $c->name ), $pct ); if ( $pct > $bp ) { $bp = $pct; $best = $c; } }
	return ( $best && $bp >= 70 ) ? array( $best, 'اسم مشابه ' . round( $bp ) . '%' ) : null;
}
function zad_gm_norm_name( $s ) {
	$s = zad_ar_norm( $s );
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/** The report as Markdown (what the admin copies back). */
function zad_gm_report_md( $inv ) {
	$st = function ( $a ) { $o = array(); foreach ( $a as $k => $n ) { $o[] = "$k: $n"; } return $o ? implode( ' · ', $o ) : '—'; };
	$m  = "# جرد دمج الأدلة في الأقسام — " . wp_date( 'Y-m-d H:i' ) . ' — ' . home_url() . "\n\n";
	$m .= "## 1) الأنواع\n";
	foreach ( $inv['types'] as $t => $i ) { $m .= "- `$t`: " . ( $i['registered'] ? 'مسجّل — supports: ' . implode( ', ', $i['supports'] ) . ' — slug: ' . $i['slug'] . ( $i['hier'] ? ' — هرمي' : '' ) . ' — تصنيفات: ' . implode( ', ', $i['taxes'] ) : 'غير مسجّل' ) . "\n"; }
	$m .= "\n## 2) عدد المقالات بكل حالة\n- guide: " . $st( $inv['status']['guide'] ) . "\n- sections: " . $st( $inv['status']['sections'] ) . "\n";
	$m .= "\n## 3) الأدلة (" . count( $inv['guides'] ) . ")\n| ID | الحالة | العنوان | الرابط |\n|---|---|---|---|\n";
	foreach ( $inv['guides'] as $g ) { $m .= '| ' . $g['id'] . ' | ' . $g['status'] . ' | ' . str_replace( '|', '/', $g['title'] ) . ' | ' . ( $g['slug'] ? $g['url'] : '(بلا slug)' ) . " |\n"; }
	$m .= 'تعليقات على الأدلة: ' . $inv['comments'] . ' · أدلة لها أب (post_parent): ' . $inv['with_parent'] . ' · تفريغ السلة التلقائي بعد: ' . ( $inv['trash_days'] ? $inv['trash_days'] . ' يوماً' : 'معطّل (لا سلة)' ) . "\n";
	$m .= "\n## 4) تعارض الـ slug مع الأقسام (" . count( $inv['conflicts'] ) . ")\n";
	if ( $inv['conflicts'] ) { $m .= "| slug | دليل (ID/حالة) | قسم (ID/حالة) |\n|---|---|---|\n"; foreach ( $inv['conflicts'] as $c ) { $m .= "| {$c['slug']} | {$c['guide']} / {$c['gstatus']} | {$c['section']} / {$c['sstatus']} |\n"; } } else { $m .= "لا يوجد.\n"; }
	$m .= 'أدلة بلا slug (مسودات): ' . $inv['noslug'] . "\n";
	$m .= "\n## 5) تصنيفات best_guide (" . count( $inv['terms'] ) . ") ومقابلها المقترح في best_sections\n| التصنيف | slug | مقالات | المقابل المقترح |\n|---|---|---|---|\n";
	foreach ( $inv['terms'] as $t ) { $m .= "| {$t['name']} | {$t['slug']} | " . $st( $t['posts'] ) . ' | ' . ( $t['match'] ? "{$t['match']['name']} ({$t['match']['slug']}) — {$t['match']['how']}" : '**لا مقابل → يُنشأ جديد**' ) . " |\n"; }
	$m .= 'أدلة بلا تصنيف best_guide: ' . $inv['no_term'] . "\n\nتصنيفات best_sections الموجودة: " . ( $inv['sections_terms'] ? implode( '، ', array_map( function ( $x ) { return $x['name'] . ' (' . $x['count'] . ')'; }, $inv['sections_terms'] ) ) : '—' ) . "\n";
	$m .= "\nتصنيفات أخرى مربوطة بالأدلة: " . ( $inv['other_tax'] ? implode( '، ', array_map( function ( $r, $k ) { return $k . ' (' . $r->n . ')'; }, $inv['other_tax'], array_keys( $inv['other_tax'] ) ) ) : 'لا يوجد' ) . "\n";
	$m .= "\n## 6) الـ meta على الأدلة (عدد الأدلة لكل مفتاح)\n";
	foreach ( $inv['meta'] as $k => $n ) { $m .= "- `$k`: $n\n"; }
	$m .= 'Yoast مفعّل: ' . ( $inv['seo_plugins']['yoast'] ? 'نعم' : 'لا' ) . ' · Rank Math: ' . ( $inv['seo_plugins']['rankmath'] ? 'نعم' : 'لا' ) . ' · أدلة بتصنيف أساسي Yoast: ' . $inv['yoast_primary'] . "\n";
	if ( $inv['yoast_opts'] ) { $m .= "إعدادات Yoast الخاصة بالنوع guide:\n"; foreach ( $inv['yoast_opts'] as $k => $v ) { $m .= "- `$k` = " . mb_substr( $v, 0, 80 ) . "\n"; } }
	$m .= "\n## 7) أماكن تذكر /guide/ أو /best-guide/\n- محتوى: " . $inv['links']['content_total'] . ' رابط في ' . array_sum( $inv['links']['content'] ) . ' صفحة (' . $st( $inv['links']['content'] ) . ")\n";
	$m .= '- meta: ' . $st( $inv['links']['meta'] ) . "\n- خيارات: " . $st( $inv['links']['options'] ) . "\n- عناصر قوائم (" . count( $inv['links']['menu'] ) . "):\n";
	foreach ( $inv['links']['menu'] as $mi ) { $m .= "  - #{$mi['id']} {$mi['label']} [{$mi['kind']}] {$mi['url']}\n"; }
	$m .= '- خدمات تختار أدلة يدوياً (`_zad_guides`): ' . $inv['guide_picks'] . " (المعرّفات لا تتغير فتبقى تعمل)\n";
	$m .= "\n## 8) Redirection\n- فعّالة: " . ( $inv['redirection']['active'] ? 'نعم' : 'لا' ) . ' · قواعد موجودة تخص /guide: ' . $inv['redirection']['guide_rules'] . ' · تخص /sections: ' . $inv['redirection']['sections_rules'] . "\n";
	$m .= "\n## 9) إعدادات القالب\n- zad_article_slugs: `" . $inv['opt']['zad_article_slugs'] . "`\n- zad_edu_hub: `" . $inv['opt']['zad_edu_hub'] . "`\n- zad_legacy_register: " . ( $inv['opt']['zad_legacy_register'] ? 'مفعّل' : 'مغلق' ) . "\n";
	return $m;
}

/* ====================================================================================================
 * المرحلة 2 — التنفيذ: معاينة ← تنفيذ (على المحدد) ← «تراجع عن الكل»
 * ==================================================================================================== */

const ZAD_GM_BACKUP = '_zad_gm_content_backup';
function zad_gm_group_name() { return 'دمج الأدلة'; }

/* ---------------- links: text relinker (any text, serialized or not) ---------------- */

function zad_gm_norm( $path ) { $p = rawurldecode( (string) $path ); return '/' === $p ? '/' : mb_strtolower( rtrim( $p, '/' ) ); }
function zad_gm_enc( $path ) { return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ); }

/** Text with every link to an old URL replaced. $map: normalised old path (/guide/x) → new path (/sections/x). @return array( text, count ) */
function zad_gm_relink_text( $text, $map, $hosts ) {
	if ( ! is_string( $text ) || '' === $text || false === strpos( $text, '/' ) || ! $map ) { return array( $text, 0 ); }
	$re = '~(?P<host>https?://[^/\s"\'<>)\\\\]+)?(?P<path>/(?:guide|best-guide)(?=[/\s"\'<>)\]#?]|$)(?:/[^\s"\'<>)\]\\\\#?]*)?)~u'; // «/guide» must end there: /guide-photo.jpg is not a link to the archive
	$n  = 0;
	$out = preg_replace_callback( $re, function ( $m ) use ( $map, $hosts, &$n ) {
		if ( '' !== $m['host'] && ! in_array( strtolower( preg_replace( '#^https?://#i', '', $m['host'] ) ), $hosts, true ) ) { return $m[0]; }
		$k = zad_gm_norm( $m['path'] );
		if ( ! isset( $map[ $k ] ) ) { return $m[0]; }
		$new = $map[ $k ];
		if ( false !== strpos( $m['path'], '%' ) ) { $new = zad_gm_enc( $new ); }
		$new = '/' === substr( $m['path'], -1 ) ? rtrim( $new, '/' ) . '/' : rtrim( $new, '/' ); // keep the original trailing-slash style
		$n++;
		return $m['host'] . $new;
	}, $text );
	return array( $out, $n );
}

function zad_gm_relink_value( $v, $map, $hosts ) {
	if ( is_array( $v ) ) { $c = 0; foreach ( $v as $k => $x ) { list( $nv, $n ) = zad_gm_relink_value( $x, $map, $hosts ); $v[ $k ] = $nv; $c += $n; } return array( $v, $c ); }
	if ( is_string( $v ) ) {
		$u = maybe_unserialize( $v );
		if ( $u !== $v && ( is_array( $u ) || is_string( $u ) ) ) { list( $nv, $n ) = zad_gm_relink_value( $u, $map, $hosts ); return array( $n ? maybe_serialize( $nv ) : $v, $n ); }
		return zad_gm_relink_text( $v, $map, $hosts );
	}
	return array( $v, 0 );
}

function zad_gm_hosts() {
	$h = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); $port = wp_parse_url( home_url(), PHP_URL_PORT );
	$out = array( $h, 0 === strpos( $h, 'www.' ) ? substr( $h, 4 ) : 'www.' . $h );
	if ( $port ) { $out[] = $h . ':' . $port; }
	return array_values( array_unique( $out ) );
}

/** Where links live. dry = count only. @return array( content => array(id => n), meta => array(meta_id => n), options => array(name => n), total ) */
function zad_gm_relink_scan( $map ) {
	global $wpdb;
	$out = array( 'content' => array(), 'meta' => array(), 'options' => array(), 'total' => 0 );
	if ( ! $map ) { return $out; }
	$hosts = zad_gm_hosts();
	foreach ( (array) $wpdb->get_results( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache','attachment') AND ( post_content LIKE '%/guide%' OR post_content LIKE '%/best-guide%' OR post_excerpt LIKE '%/guide%' OR post_excerpt LIKE '%/best-guide%' )" ) as $p ) {
		list( , $a ) = zad_gm_relink_text( (string) $p->post_content, $map, $hosts ); list( , $b ) = zad_gm_relink_text( (string) $p->post_excerpt, $map, $hosts );
		if ( $a + $b ) { $out['content'][ (int) $p->ID ] = $a + $b; $out['total'] += $a + $b; }
	}
	foreach ( (array) $wpdb->get_results( "SELECT pm.meta_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type NOT IN ('revision','attachment') AND ( pm.meta_value LIKE '%/guide%' OR pm.meta_value LIKE '%/best-guide%' ) AND pm.meta_key NOT LIKE '\\_wp\\_%' AND pm.meta_key NOT LIKE '%backup%' AND pm.meta_key NOT IN ('_edit_lock','_oembed_cache')" ) as $m ) {
		list( , $n ) = zad_gm_relink_value( (string) $m->meta_value, $map, $hosts );
		if ( $n ) { $out['meta'][ (int) $m->meta_id ] = $n; $out['total'] += $n; }
	}
	foreach ( (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE ( option_value LIKE '%/guide%' OR option_value LIKE '%/best-guide%' ) AND option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%' AND option_name NOT LIKE 'zad\\_gm\\_%' AND option_name NOT LIKE 'zad\\_svcc\\_%' AND option_name NOT IN ('rewrite_rules','cron','recently_edited','active_plugins','wpseo_titles')" ) as $o ) {
		list( , $n ) = zad_gm_relink_value( (string) $o->option_value, $map, $hosts );
		if ( $n ) { $out['options'][ $o->option_name ] = $n; $out['total'] += $n; }
	}
	return $out;
}

/* ---------------- Redirection helpers (the generic layer lives in zad-svcconvert.php) ---------------- */

function zad_gm_red_ready() { return function_exists( 'zad_svcc_red_active' ) && zad_svcc_red_active(); }

/** One 301 (plain or regex). Returns the Redirection item id, 0 on failure. */
function zad_gm_red_add( $group, $from, $to, $regex = false ) {
	if ( ! $group || ! class_exists( 'Red_Item' ) ) { return 0; }
	try {
		$it = Red_Item::create( array( 'url' => $regex ? $from : '/' . trim( $from, '/' ) . '/', 'match_type' => 'url', 'action_type' => 'url', 'action_code' => 301, 'action_data' => array( 'url' => $to ), 'group_id' => $group, 'regex' => (bool) $regex, 'title' => 'دمج الأدلة في الأقسام' ) );
	} catch ( \Throwable $e ) { return 0; }
	return ( is_object( $it ) && method_exists( $it, 'get_id' ) ) ? (int) $it->get_id() : 0;
}

/** A regex rule with exactly this pattern already exists? */
function zad_gm_red_regex_exists( $pattern ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}redirection_items WHERE regex = 1 AND url = %s LIMIT 1", $pattern ) ); // phpcs:ignore
}

/** Rewrite the target of an existing redirect (keeps the stored format). Returns the old raw action_data, or null. */
function zad_gm_red_retarget( $id, $to ) {
	global $wpdb;
	$t   = $wpdb->prefix . 'redirection_items';
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT action_data FROM $t WHERE id = %d", $id ) ); // phpcs:ignore
	if ( null === $raw ) { return null; }
	if ( 'a:' === substr( (string) $raw, 0, 2 ) ) { $u = @unserialize( $raw ); if ( ! is_array( $u ) ) { return null; } $u['url'] = $to; $new = serialize( $u ); } // phpcs:ignore
	elseif ( '{' === substr( (string) $raw, 0, 1 ) ) { $j = json_decode( $raw, true ); if ( ! is_array( $j ) ) { return null; } $j['url'] = $to; $new = wp_json_encode( $j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	else { $new = $to; }
	$wpdb->update( $t, array( 'action_data' => $new ), array( 'id' => $id ) ); // phpcs:ignore
	return (string) $raw;
}

/* ---------------- plan ---------------- */

function zad_gm_sections_url( $slug ) {
	$base = get_post_type_archive_link( ZAD_GM_TO );
	return trailingslashit( $base ? $base : home_url( '/sections/' ) ) . rawurlencode( urldecode( (string) $slug ) ) . '/';
}

function zad_gm_guides() {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private','future') ORDER BY FIELD( post_status, 'publish', 'private', 'future', 'pending', 'draft' ), ID", ZAD_GM_FROM ) ); // phpcs:ignore
}

/** The «sections» post that already uses this slug (any live status), or 0. */
function zad_gm_slug_owner( $slug, $except = 0 ) {
	global $wpdb;
	if ( '' === $slug ) { return 0; }
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status NOT IN ('trash','auto-draft','inherit') AND ID <> %d LIMIT 1", ZAD_GM_TO, $slug, $except ) ); // phpcs:ignore
}

/** best_guide terms (parents first) with the suggested best_sections counterpart. */
function zad_gm_terms_list() {
	global $wpdb;
	$bs = (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.parent FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY t.name", 'best_sections' ) );
	$out = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, t.term_id tid, tt.parent, tt.description FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY tt.parent, t.name", 'best_guide' ) ) as $t ) {
		$m = zad_gm_match_term( $t, $bs );
		$out[ (int) $t->term_id ] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => (int) $t->parent, 'desc' => (string) $t->description, 'suggest' => $m ? (int) $m[0]->term_id : 0, 'why' => $m ? $m[1] : '' );
	}
	return array( 'guide' => $out, 'sections' => $bs );
}

/** Which best_guide term is «التنظيف العام» (default home of the guides that had no category)? */
function zad_gm_default_guide_term( $terms ) {
	foreach ( $terms as $t ) { if ( 'general-cleaning' === $t['slug'] || zad_gm_norm_name( $t['name'] ) === zad_gm_norm_name( 'التنظيف العام' ) ) { return $t['id']; } }
	return 0;
}

/**
 * The plan for the posted rows. $posted = array( rows => id => array( sel, act, slug ), terms => old term id => new term id | 'new', fix_inbound => bool ).
 * Authoritative: what the run does is recomputed here, the screen is not trusted.
 */
function zad_gm_plan( $posted ) {
	global $wpdb;
	$plan = array( 'error' => '', 'rows' => array(), 'terms' => array(), 'archive' => false, 'links' => array( 'total' => 0 ), 'inbound' => array(), 'redir_add' => 0, 'redir_exist' => 0, 'ok' => 0, 'skip' => 0 );
	$tl   = zad_gm_terms_list();
	foreach ( $tl['guide'] as $id => $t ) {
		$to = $posted['terms'][ $id ] ?? ( $t['suggest'] ?: 'new' );
		$plan['terms'][ $id ] = $t + array( 'to' => ( 'new' === $to || ! (int) $to ) ? 'new' : (int) $to );
	}
	$def = $posted['default_term'] ?? 'auto';
	$plan['default_to'] = 'auto' === $def ? zad_gm_default_guide_term( $plan['terms'] ) : (int) $def; // best_guide term whose counterpart is used (0 = a new «التنظيف العام»)
	$map = array(); $moving = array();
	foreach ( zad_gm_guides() as $g ) {
		$id  = (int) $g->ID; $pr = $posted['rows'][ $id ] ?? array();
		$row = array( 'id' => $id, 'title' => $g->post_title, 'wstatus' => $g->post_status, 'sel' => ! empty( $pr['sel'] ), 'act' => ( $pr['act'] ?? 'move' ), 'slug_old' => $g->post_name, 'slug' => $g->post_name, 'status' => 'none', 'why' => '', 'old_url' => zad_gm_post_url( $g ), 'new_url' => '', 'conflict' => 0, 'redir' => 'none', 'final' => '', 'merge_to' => 0 );
		$row['conflict'] = zad_gm_slug_owner( $g->post_name, $id );
		if ( isset( $pr['slug'] ) && '' !== trim( (string) $pr['slug'] ) ) { $row['slug'] = sanitize_title( wp_unslash( (string) $pr['slug'] ) ); }
		if ( ! $row['sel'] ) { $plan['rows'][ $id ] = $row; continue; }
		$why = '';
		if ( 'merge' === $row['act'] ) {
			$owner = zad_gm_slug_owner( $g->post_name, $id );
			if ( ! $owner || 'publish' !== get_post_status( $owner ) ) { $why = 'الدمج مع تحويل يحتاج مقالاً منشوراً في الأقسام بنفس الـ slug'; } else { $row['merge_to'] = $owner; }
		} else {
			$row['act'] = 'move';
			if ( '' !== $row['slug'] && zad_gm_slug_owner( $row['slug'], $id ) ) { $why = $row['slug'] === $g->post_name ? 'تعارض: الـ slug مستخدم في الأقسام — اكتب slug جديداً أو اختر «دمج مع تحويل»' : 'الـ slug الجديد مستخدم في الأقسام'; }
		}
		if ( $why ) { $row['status'] = 'skip'; $row['why'] = $why; $plan['skip']++; $plan['rows'][ $id ] = $row; continue; }
		$row['status'] = 'ok'; $plan['ok']++;
		if ( 'move' === $row['act'] && '' !== $row['slug'] ) { $row['new_url'] = zad_gm_sections_url( $row['slug'] ); }
		if ( 'merge' === $row['act'] ) { $row['new_url'] = (string) get_permalink( $row['merge_to'] ); }
		if ( 'publish' === $g->post_status && '' !== $row['new_url'] ) {
			$k = zad_svcc_path_key( $row['old_url'] );
			if ( zad_gm_red_ready() && zad_svcc_red_lookup( $k ) ) { $row['redir'] = 'exists'; $plan['redir_exist']++; }
			else { $row['redir'] = 'add'; $plan['redir_add']++; }
			list( $fin ) = zad_gm_red_ready() ? zad_svcc_red_final( $row['new_url'] ) : array( $row['new_url'] );
			$row['final'] = $fin;
			$map[ zad_gm_norm( '/' . $k ) ] = (string) wp_parse_url( $row['new_url'], PHP_URL_PATH );
			if ( zad_gm_red_ready() ) { foreach ( zad_svcc_red_inbound( $row['old_url'] ) as $in ) { $plan['inbound'][ $in[0] ] = array( $in[1], $row['final'] ); } }
		}
		$moving[ $id ] = 1;
		$plan['rows'][ $id ] = $row;
	}
	// the archives (and every link to them) are redirected only when no guide stays behind
	$left = 0;
	foreach ( zad_gm_guides() as $g ) { if ( ! isset( $moving[ (int) $g->ID ] ) ) { $left++; } }
	$plan['archive'] = ( $moving && 0 === $left );
	$plan['left']    = $left;
	if ( $plan['archive'] ) {
		$map['/guide'] = '/sections'; $map['/best-guide'] = '/sections';
		foreach ( $plan['terms'] as $t ) { $map[ zad_gm_norm( '/best-guide/' . $t['slug'] ) ] = '/' . trim( ( 'new' === $t['to'] ? 'best_sections/' . $t['slug'] : (string) wp_parse_url( (string) get_term_link( (int) $t['to'], 'best_sections' ), PHP_URL_PATH ) ), '/' ); }
	}
	$plan['map']   = $map;
	$plan['links'] = zad_gm_relink_scan( $map );
	if ( ! zad_gm_red_ready() ) { $plan['error'] = 'إضافة Redirection غير مفعّلة. فعّلها أولاً: كل تحويل 301 يجب أن يُسجَّل فيها، ولن يُنفَّذ شيء قبل ذلك.'; }
	return $plan;
}

/* ---------------- run ---------------- */

function zad_gm_log_get() {
	$def = array( 'items' => array(), 'created' => array(), 'red' => array(), 'red_changed' => array(), 'trash' => array(), 'menu' => array(), 'links' => array(), 'meta' => array(), 'options' => array(), 'termmap' => array() );
	$l = get_option( 'zad_gm_log' );
	return is_array( $l ) ? array_merge( $def, $l ) : $def;
}

function zad_gm_term_ensure( $t, &$log, &$tmap, $all = array() ) { // best_sections term id for an old best_guide term (created when needed)
	if ( isset( $tmap[ $t['id'] ] ) ) { return $tmap[ $t['id'] ]; }
	if ( 'new' !== $t['to'] ) { return $tmap[ $t['id'] ] = (int) $t['to']; }
	$parent = 0;
	if ( $t['parent'] ) { $parent = isset( $tmap[ $t['parent'] ] ) ? $tmap[ $t['parent'] ] : ( isset( $all[ $t['parent'] ] ) ? zad_gm_term_ensure( $all[ $t['parent'] ], $log, $tmap, $all ) : 0 ); }
	$ex = term_exists( $t['slug'], 'best_sections' );
	if ( $ex ) { return $tmap[ $t['id'] ] = (int) ( is_array( $ex ) ? $ex['term_id'] : $ex ); }
	$r = wp_insert_term( $t['name'], 'best_sections', array( 'slug' => $t['slug'], 'description' => $t['desc'], 'parent' => $parent ) );
	if ( is_wp_error( $r ) ) { return 0; }
	$log['created'][] = array( 'best_sections', (int) $r['term_id'] );
	return $tmap[ $t['id'] ] = (int) $r['term_id'];
}

function zad_gm_run( $posted ) {
	global $wpdb;
	$rep = array( 'error' => '', 'moved' => 0, 'merged' => 0, 'skip' => array(), 'lines' => array(), 'red_added' => 0, 'red_exists' => 0, 'red_retargeted' => 0, 'links' => 0, 'terms' => array(), 'archive' => false, 'warn' => array(), 'urlbad' => array() );
	$plan = zad_gm_plan( $posted );
	if ( $plan['error'] ) { $rep['error'] = $plan['error']; return $rep; }
	if ( ! post_type_exists( ZAD_GM_TO ) ) { $rep['error'] = 'نوع «الأقسام» غير مسجّل.'; return $rep; }
	$group = zad_svcc_red_group( zad_gm_group_name() );
	if ( ! $group ) { $rep['error'] = 'تعذّر إنشاء/إيجاد مجموعة «دمج الأدلة» في Redirection.'; return $rep; }
	$log  = zad_gm_log_get(); $tmap = array();
	$def_new = 0;
	// the term of the guides that had none: «التنظيف العام»
	$default_term = function () use ( &$plan, &$log, &$tmap, &$def_new ) {
		if ( $def_new ) { return $def_new; }
		if ( $plan['default_to'] && isset( $plan['terms'][ $plan['default_to'] ] ) ) { return $def_new = zad_gm_term_ensure( $plan['terms'][ $plan['default_to'] ], $log, $tmap, $plan['terms'] ); }
		$ex = term_exists( 'general-cleaning', 'best_sections' );
		if ( $ex ) { return $def_new = (int) ( is_array( $ex ) ? $ex['term_id'] : $ex ); }
		$r = wp_insert_term( 'التنظيف العام', 'best_sections', array( 'slug' => 'general-cleaning' ) );
		if ( is_wp_error( $r ) ) { return 0; }
		$log['created'][] = array( 'best_sections', (int) $r['term_id'] );
		return $def_new = (int) $r['term_id'];
	};
	foreach ( $plan['rows'] as $id => $row ) {
		if ( 'none' === $row['status'] ) { continue; }
		if ( 'skip' === $row['status'] ) { $rep['skip'][ $id ] = $row['why']; $rep['lines'][ $id ] = array( $row['title'], 'تخطّى: ' . $row['why'] ); continue; }
		$p = get_post( (int) $id );
		if ( ! $p || ZAD_GM_FROM !== $p->post_type ) { $rep['skip'][ $id ] = 'تغيّرت حالة الدليل'; continue; }
		$item = array( 'type' => $p->post_type, 'name' => $p->post_name, 'status' => $p->post_status, 'old_url' => $row['old_url'], 'old_terms' => array(), 'new_terms' => array(), 'yoast' => array(), 'new_url' => '' );
		if ( 'merge' === $row['act'] ) {
			$log['trash'][ $id ] = array( 'status' => $p->post_status, 'name' => $p->post_name, 'type' => $p->post_type, 'mod' => $p->post_modified, 'mod_gmt' => $p->post_modified_gmt );
			if ( ! wp_trash_post( (int) $id ) || 'trash' !== get_post_status( $id ) ) { unset( $log['trash'][ $id ] ); $rep['skip'][ $id ] = 'تعذّر نقله للسلة'; continue; }
			zad_gm_yoast_forget( array( $id ) );
			$rep['merged']++; $final = $row['final'] ?: $row['new_url'];
			$rep['lines'][ $id ] = array( $row['title'], 'دُمج مع تحويل: السلة + 301 إلى ' . urldecode( $final ) );
			$item['new_url'] = $final;
		} else {
			// 1) the type (and the slug, if renamed) — ID, date, modified date, content, image and every meta stay
			$set = array( 'post_type' => ZAD_GM_TO );
			if ( $row['slug'] !== $p->post_name && '' !== $row['slug'] ) { $set['post_name'] = $row['slug']; }
			$wpdb->update( $wpdb->posts, $set, array( 'ID' => $id ) ); // phpcs:ignore
			clean_post_cache( $id );
			// 2) classification: best_guide → best_sections (+ the Yoast primary term)
			$old = wp_get_object_terms( $id, 'best_guide', array( 'fields' => 'ids' ) );
			$old = is_wp_error( $old ) ? array() : array_map( 'intval', $old );
			$new = array();
			foreach ( $old as $oid ) { if ( isset( $plan['terms'][ $oid ] ) ) { $n = zad_gm_term_ensure( $plan['terms'][ $oid ], $log, $tmap, $plan['terms'] ); if ( $n ) { $new[ $oid ] = $n; } } }
			if ( ! $old ) { $d = $default_term(); if ( $d ) { $new[0] = $d; } }
			if ( $new ) { wp_set_object_terms( $id, array_values( array_unique( $new ) ), 'best_sections', true ); }
			if ( $old ) { wp_remove_object_terms( $id, $old, 'best_guide' ); }
			$item['old_terms'] = $old; $item['new_terms'] = array_values( array_unique( $new ) );
			$pk = '_yoast_wpseo_primary_best_guide'; $nk = '_yoast_wpseo_primary_best_sections';
			if ( metadata_exists( 'post', $id, $pk ) ) {
				$ov = (string) get_post_meta( $id, $pk, true ); $item['yoast'][ $pk ] = $ov;
				$nv = ( $ov && isset( $new[ (int) $ov ] ) ) ? $new[ (int) $ov ] : ( $new ? reset( $new ) : 0 );
				if ( $nv ) { update_post_meta( $id, $nk, (string) $nv ); $item['yoast'][ $nk ] = null; }
				delete_post_meta( $id, $pk );
			}
			// 3) menu items that point at the post
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id' AND meta_value = %d", $id ) ) as $mid ) { // phpcs:ignore
				if ( 'post_type' === get_post_meta( $mid, '_menu_item_type', true ) && ZAD_GM_FROM === get_post_meta( $mid, '_menu_item_object', true ) ) { update_post_meta( $mid, '_menu_item_object', ZAD_GM_TO ); $log['menu'][ $mid ] = ZAD_GM_FROM; }
			}
			zad_gm_yoast_forget( array( $id ) );
			$url = get_permalink( $id ); $item['new_url'] = $url; $final = $row['final'] ?: $url;
			if ( $row['new_url'] && rawurldecode( $row['new_url'] ) !== rawurldecode( $url ) ) { $rep['urlbad'][ $id ] = $url; }
			$rep['moved']++;
			$rep['lines'][ $id ] = array( $row['title'], 'نُقل إلى الأقسام → ' . urldecode( $url ) );
		}
		// the 301 (published only): only when nothing redirects the URL already, straight to the final destination
		if ( 'publish' === $item['status'] && 'add' === $row['redir'] ) {
			$rid = zad_gm_red_add( $group, rawurldecode( zad_svcc_path_key( $row['old_url'] ) ), $final );
			if ( $rid ) { $log['red'][] = $rid; $rep['red_added']++; } else { $rep['warn'][ $id ] = 'تعذّر إضافة التحويل في Redirection'; }
		} elseif ( 'exists' === $row['redir'] ) { $rep['red_exists']++; }
		$log['items'][ $id ] = $item;
	}
	// existing Redirection rules that pointed at a moved URL: re-aim them (no chains)
	if ( ! empty( $posted['fix_inbound'] ) ) {
		foreach ( $plan['inbound'] as $rid => $pair ) {
			if ( isset( $log['red_changed'][ $rid ] ) ) { continue; }
			$oldraw = zad_gm_red_retarget( (int) $rid, $pair[1] );
			if ( null !== $oldraw ) { $log['red_changed'][ $rid ] = $oldraw; $rep['red_retargeted']++; }
		}
	}
	// the archives, once no guide stays behind
	$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private','future')", ZAD_GM_FROM ) ); // phpcs:ignore
	$map = array();
	if ( 0 === $left && $log['items'] ) {
		$rep['archive'] = true;
		$to_arch = get_post_type_archive_link( ZAD_GM_TO ) ?: home_url( '/sections/' );
		$add = function ( $from, $to, $regex = false ) use ( &$log, &$rep, $group ) {
			if ( ! $regex && zad_svcc_red_lookup( $from ) ) { $rep['red_exists']++; return; }
			if ( $regex && zad_gm_red_regex_exists( $from ) ) { $rep['red_exists']++; return; }
			$id = zad_gm_red_add( $group, $from, $to, $regex );
			if ( $id ) { $log['red'][] = $id; $rep['red_added']++; }
		};
		$add( 'guide', $to_arch ); $add( '^/guide/page/([0-9]+)/?$', trailingslashit( $to_arch ) . 'page/$1/', true ); $add( 'best-guide', $to_arch );
		foreach ( $plan['terms'] as $t ) {
			$tid = zad_gm_term_ensure( $t, $log, $tmap, $plan['terms'] );
			$lnk = $tid ? get_term_link( (int) $tid, 'best_sections' ) : '';
			if ( ! $lnk || is_wp_error( $lnk ) ) { continue; }
			list( $fin ) = zad_svcc_red_final( $lnk );
			$add( 'best-guide/' . rawurldecode( $t['slug'] ), $fin );
			$add( '^/best-guide/' . preg_quote( rawurldecode( $t['slug'] ), '@' ) . '/page/([0-9]+)/?$', trailingslashit( $lnk ) . 'page/$1/', true );
			$map[ zad_gm_norm( '/best-guide/' . $t['slug'] ) ] = (string) wp_parse_url( $lnk, PHP_URL_PATH );
		}
		$map['/guide'] = (string) wp_parse_url( $to_arch, PHP_URL_PATH ); $map['/best-guide'] = $map['/guide'];
	}
	foreach ( $log['items'] as $id => $it ) { if ( 'publish' === $it['status'] && $it['new_url'] && empty( $log['trash'][ $id ] ) ) { $map[ zad_gm_norm( '/' . zad_svcc_path_key( $it['old_url'] ) ) ] = (string) wp_parse_url( $it['new_url'], PHP_URL_PATH ); } }
	foreach ( $log['items'] as $id => $it ) { if ( ! empty( $log['trash'][ $id ] ) && $it['new_url'] ) { $map[ zad_gm_norm( '/' . zad_svcc_path_key( $it['old_url'] ) ) ] = (string) wp_parse_url( $it['new_url'], PHP_URL_PATH ); } }
	zad_gm_relink_apply( $map, $log, $rep );
	foreach ( $tmap as $old => $new ) { $rep['terms'][ $old ] = $new; }
	update_option( 'zad_gm_log', $log, false );
	zad_gm_after();
	return $rep;
}

function zad_gm_yoast_forget( $ids ) { if ( function_exists( 'zad_svcc_yoast_forget' ) ) { zad_svcc_yoast_forget( $ids ); } }
function zad_gm_after() { if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) { WPSEO_Sitemaps_Cache::clear(); } wp_cache_flush(); }

/** Apply the link map everywhere; the old values go to the log (content also to a post-meta backup) so the undo can restore them. */
function zad_gm_relink_apply( $map, &$log, &$rep ) {
	global $wpdb;
	if ( ! $map ) { return; }
	$hosts = zad_gm_hosts(); $scan = zad_gm_relink_scan( $map );
	foreach ( array_keys( $scan['content'] ) as $pid ) {
		$p = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", $pid ) ); // phpcs:ignore
		if ( ! $p ) { continue; }
		list( $c, $a ) = zad_gm_relink_text( (string) $p->post_content, $map, $hosts ); list( $e, $b ) = zad_gm_relink_text( (string) $p->post_excerpt, $map, $hosts );
		if ( ! $a && ! $b ) { continue; }
		if ( '' === (string) get_post_meta( $pid, ZAD_GM_BACKUP, true ) ) { update_post_meta( $pid, ZAD_GM_BACKUP, wp_slash( wp_json_encode( array( 'c' => $p->post_content, 'e' => $p->post_excerpt ), JSON_UNESCAPED_UNICODE ) ) ); }
		$wpdb->update( $wpdb->posts, array( 'post_content' => $c, 'post_excerpt' => $e ), array( 'ID' => $pid ) ); // phpcs:ignore — modified date untouched
		clean_post_cache( $pid );
		$log['links'][ $pid ] = md5( $c . '|' . $e ); $rep['links'] += $a + $b;
	}
	foreach ( array_keys( $scan['meta'] ) as $mid ) {
		$m = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $mid ) ); // phpcs:ignore
		if ( ! $m ) { continue; }
		list( $nv, $n ) = zad_gm_relink_value( (string) $m->meta_value, $map, $hosts );
		if ( ! $n ) { continue; }
		$log['meta'][ $mid ] = array( (string) $m->meta_value, md5( is_string( $nv ) ? $nv : '' ) );
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => is_string( $nv ) ? $nv : maybe_serialize( $nv ) ), array( 'meta_id' => $mid ) ); // phpcs:ignore
		wp_cache_delete( (int) $m->post_id, 'post_meta' ); $rep['links'] += $n;
	}
	foreach ( array_keys( $scan['options'] ) as $name ) {
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore
		if ( null === $raw ) { continue; }
		list( $nv, $n ) = zad_gm_relink_value( (string) $raw, $map, $hosts );
		if ( ! $n ) { continue; }
		$log['options'][ $name ] = (string) $raw;
		$wpdb->update( $wpdb->options, array( 'option_value' => is_string( $nv ) ? $nv : maybe_serialize( $nv ) ), array( 'option_name' => $name ) ); // phpcs:ignore
		wp_cache_delete( $name, 'options' ); wp_cache_delete( 'alloptions', 'options' ); $rep['links'] += $n;
	}
}

function zad_gm_undo() {
	global $wpdb;
	$log = zad_gm_log_get();
	$r = array( 'posts' => 0, 'links' => 0, 'kept' => 0, 'trash' => 0, 'red' => 0, 'terms' => 0 );
	if ( ! $log['items'] && ! $log['red'] && ! $log['trash'] ) { return $r; }
	foreach ( (array) $log['red'] as $rid ) { zad_svcc_red_del( $rid ); $r['red']++; }
	foreach ( (array) $log['red_changed'] as $rid => $raw ) { $wpdb->update( $wpdb->prefix . 'redirection_items', array( 'action_data' => $raw ), array( 'id' => (int) $rid ) ); } // phpcs:ignore
	foreach ( (array) $log['links'] as $pid => $hash ) {
		$p = $wpdb->get_row( $wpdb->prepare( "SELECT post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", $pid ) ); // phpcs:ignore
		$bak = json_decode( (string) get_post_meta( (int) $pid, ZAD_GM_BACKUP, true ), true );
		if ( $p && is_array( $bak ) && md5( $p->post_content . '|' . $p->post_excerpt ) === $hash ) { $wpdb->update( $wpdb->posts, array( 'post_content' => $bak['c'], 'post_excerpt' => $bak['e'] ), array( 'ID' => (int) $pid ) ); clean_post_cache( (int) $pid ); delete_post_meta( (int) $pid, ZAD_GM_BACKUP ); $r['links']++; } // phpcs:ignore
		else { $r['kept']++; } // edited since: left as is
	}
	foreach ( (array) $log['meta'] as $mid => $pair ) {
		$cur = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $mid ) ); // phpcs:ignore
		if ( md5( $cur ) === $pair[1] || is_serialized( $cur ) ) { $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $pair[0] ), array( 'meta_id' => (int) $mid ) ); $r['links']++; } // phpcs:ignore
	}
	foreach ( (array) $log['options'] as $name => $raw ) { $wpdb->update( $wpdb->options, array( 'option_value' => $raw ), array( 'option_name' => $name ) ); wp_cache_delete( $name, 'options' ); wp_cache_delete( 'alloptions', 'options' ); $r['links']++; } // phpcs:ignore
	foreach ( (array) $log['menu'] as $mid => $old ) { update_post_meta( (int) $mid, '_menu_item_object', $old ); }
	foreach ( (array) $log['trash'] as $id => $it ) {
		$id = (int) $id;
		if ( 'trash' !== get_post_status( $id ) ) { continue; }
		$want = (string) $it['status']; $f = function () use ( $want ) { return $want; };
		add_filter( 'wp_untrash_post_status', $f, 99 ); wp_untrash_post( $id ); remove_filter( 'wp_untrash_post_status', $f, 99 );
		if ( get_post_status( $id ) !== $want ) { $wpdb->update( $wpdb->posts, array( 'post_status' => $want ), array( 'ID' => $id ) ); } // phpcs:ignore
		if ( get_post_field( 'post_name', $id ) !== $it['name'] ) { $wpdb->update( $wpdb->posts, array( 'post_name' => $it['name'] ), array( 'ID' => $id ) ); } // phpcs:ignore
		delete_post_meta( $id, '_wp_trash_meta_status' ); delete_post_meta( $id, '_wp_trash_meta_time' ); delete_post_meta( $id, '_wp_desired_post_slug' );
		foreach ( (array) get_post_meta( $id, '_wp_old_slug' ) as $os ) { if ( false !== strpos( (string) $os, '__trashed' ) ) { delete_post_meta( $id, '_wp_old_slug', $os ); } }
		if ( ! empty( $it['mod'] ) ) { $wpdb->update( $wpdb->posts, array( 'post_modified' => $it['mod'], 'post_modified_gmt' => $it['mod_gmt'] ), array( 'ID' => $id ) ); } // phpcs:ignore
		clean_post_cache( $id );
		$r['trash']++;
	}
	foreach ( (array) $log['items'] as $id => $it ) {
		$id = (int) $id;
		if ( ! empty( $log['trash'][ $id ] ) || get_post_type( $id ) === $it['type'] ) { continue; }
		if ( $it['new_terms'] ) { wp_remove_object_terms( $id, array_map( 'intval', $it['new_terms'] ), 'best_sections' ); }
		if ( $it['old_terms'] ) { wp_set_object_terms( $id, array_map( 'intval', $it['old_terms'] ), 'best_guide', false ); }
		foreach ( (array) $it['yoast'] as $k => $v ) { if ( null === $v ) { delete_post_meta( $id, $k ); } else { update_post_meta( $id, $k, $v ); } }
		$wpdb->update( $wpdb->posts, array( 'post_type' => $it['type'], 'post_name' => $it['name'] ), array( 'ID' => $id ) ); // phpcs:ignore
		clean_post_cache( $id ); $r['posts']++;
	}
	zad_gm_yoast_forget( array_map( 'intval', array_keys( $log['items'] ) ) );
	foreach ( (array) $log['created'] as $c ) { $t = get_term( (int) $c[1], $c[0] ); if ( $t && ! is_wp_error( $t ) && 0 === (int) $t->count ) { wp_delete_term( (int) $c[1], $c[0] ); $r['terms']++; } }
	delete_option( 'zad_gm_log' );
	zad_gm_after();
	return $r;
}

/* ---------------- admin ---------------- */

add_action( 'admin_menu', function () {
	add_management_page( 'دمج الأدلة في الأقسام', 'دمج الأدلة في الأقسام', 'manage_options', 'zad-guide-merge', 'zad_gm_page' );
} );

function zad_gm_posted() {
	$rows = array();
	foreach ( (array) ( $_POST['row'] ?? array() ) as $id => $r ) { // phpcs:ignore
		$id = absint( $id ); if ( ! $id ) { continue; }
		$rows[ $id ] = array( 'sel' => ! empty( $r['sel'] ), 'act' => ( isset( $r['act'] ) && 'merge' === $r['act'] ) ? 'merge' : 'move', 'slug' => isset( $r['slug'] ) ? sanitize_text_field( wp_unslash( $r['slug'] ) ) : '' );
	}
	$terms = array();
	foreach ( (array) ( $_POST['term'] ?? array() ) as $id => $to ) { $terms[ absint( $id ) ] = ( 'new' === $to || ! absint( $to ) ) ? 'new' : absint( $to ); } // phpcs:ignore
	$def = isset( $_POST['default_term'] ) ? sanitize_text_field( wp_unslash( $_POST['default_term'] ) ) : 'auto'; // phpcs:ignore
	return array( 'rows' => $rows, 'terms' => $terms, 'default_term' => ( 'auto' === $def ) ? 'auto' : absint( $def ), 'fix_inbound' => ! empty( $_POST['fix_inbound'] ) ); // phpcs:ignore
}

function zad_gm_status_ar( $s ) { return array( 'publish' => 'منشور', 'draft' => 'مسودة', 'private' => 'خاص', 'future' => 'مجدول', 'pending' => 'قيد المراجعة' )[ $s ] ?? $s; }

function zad_gm_hidden( $posted ) { // the posted choices, carried to the confirm form
	$o = '';
	foreach ( $posted['rows'] as $id => $r ) { foreach ( array( 'sel' => $r['sel'] ? '1' : '', 'act' => $r['act'], 'slug' => $r['slug'] ) as $k => $v ) { if ( '' !== $v ) { $o .= '<input type="hidden" name="row[' . (int) $id . '][' . $k . ']" value="' . esc_attr( $v ) . '">'; } } }
	foreach ( $posted['terms'] as $id => $to ) { $o .= '<input type="hidden" name="term[' . (int) $id . ']" value="' . esc_attr( (string) $to ) . '">'; }
	return $o . '<input type="hidden" name="default_term" value="' . esc_attr( (string) $posted['default_term'] ) . '">' . ( $posted['fix_inbound'] ? '<input type="hidden" name="fix_inbound" value="1">' : '' );
}

function zad_gm_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>دمج الأدلة في الأقسام</h1>';
	echo '<div class="notice notice-warning inline"><p><b>قبل أي تنفيذ:</b> نسخة احتياطية كاملة من قاعدة البيانات، وإضافة Redirection مفعّلة. بعد التنفيذ: احفظ الروابط الدائمة وامسح كاش Yoast للسايت ماب وكاش LiteSpeed. لا يُحذف أي مقال نهائياً؛ «تراجع عن الكل» يعيد كل شيء.</p></div>';
	$do = isset( $_POST['zad_gm_do'] ) ? sanitize_key( wp_unslash( $_POST['zad_gm_do'] ) ) : ''; // phpcs:ignore
	$posted = array( 'rows' => array(), 'terms' => array(), 'default_term' => 'auto', 'fix_inbound' => true );
	if ( $do ) {
		check_admin_referer( 'zad_gm' );
		$posted = zad_gm_posted();
		if ( 'undo' === $do ) {
			$r = zad_gm_undo();
			echo '<div class="notice notice-success"><p>تم التراجع: أدلة أُعيدت <b>' . (int) $r['posts'] . '</b> · من السلة <b>' . (int) $r['trash'] . '</b> · تحويلات حُذفت <b>' . (int) $r['red'] . '</b> · روابط/قيم أُعيدت <b>' . (int) $r['links'] . '</b> · تصنيفات جديدة حُذفت <b>' . (int) $r['terms'] . '</b>' . ( $r['kept'] ? ' · صفحات عُدِّلت بعد التنفيذ فتُركت: ' . (int) $r['kept'] : '' ) . '. امسح كاش LiteSpeed وأعد حفظ الروابط الدائمة.</p></div>';
			$posted = array( 'rows' => array(), 'terms' => array(), 'default_term' => 'auto', 'fix_inbound' => true );
		} elseif ( 'run' === $do ) {
			$rep = zad_gm_run( $posted );
			zad_gm_report_html( $rep );
			$posted = array( 'rows' => array(), 'terms' => array(), 'default_term' => 'auto', 'fix_inbound' => true );
		}
	}
	$plan = zad_gm_plan( $posted );
	if ( 'preview' === $do ) { zad_gm_preview_html( $plan, $posted ); }
	zad_gm_table_html( $plan, $posted );

	$inv = zad_gm_inventory(); $md = zad_gm_report_md( $inv );
	echo '<hr><h2>تقرير الجرد (للنسخ)</h2><p><button type="button" class="button" id="zad-gm-copy">نسخ التقرير</button></p><textarea id="zad-gm-md" readonly rows="14" style="width:100%;font-family:monospace;direction:rtl">' . esc_textarea( $md ) . '</textarea>';
	echo '<script>document.getElementById("zad-gm-copy").addEventListener("click",function(){var t=document.getElementById("zad-gm-md");t.select();try{document.execCommand("copy");this.textContent="تم النسخ ✔";}catch(e){}});</script></div>';
}

function zad_gm_report_html( $rep ) {
	if ( $rep['error'] ) { echo '<div class="notice notice-error"><p>' . esc_html( $rep['error'] ) . '</p></div>'; return; }
	echo '<div class="notice notice-success"><p><b>تم.</b> نُقل <b>' . (int) $rep['moved'] . '</b> دليلاً إلى الأقسام · دُمج مع تحويل <b>' . (int) $rep['merged'] . '</b> · تحويلات 301 أُضيفت في Redirection <b>' . (int) $rep['red_added'] . '</b> (موجودة سابقاً: ' . (int) $rep['red_exists'] . ') · قواعد قديمة أُعيد توجيهها <b>' . (int) $rep['red_retargeted'] . '</b> · روابط داخلية عُدِّلت <b>' . (int) $rep['links'] . '</b>' . ( $rep['archive'] ? ' · أُضيفت تحويلات أرشيف /guide/ وتصنيفات best-guide' : ' · تحويلات الأرشيف تُضاف عند نقل آخر دليل' ) . '. احفظ الروابط الدائمة وامسح كاش LiteSpeed.</p></div>';
	if ( $rep['urlbad'] ) { echo '<div class="notice notice-warning"><p>رابط جديد مختلف عن المتوقع: ' . esc_html( implode( ' ، ', array_map( 'urldecode', $rep['urlbad'] ) ) ) . '</p></div>'; }
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>الدليل</th><th>النتيجة</th></tr></thead><tbody>';
	foreach ( $rep['lines'] as $id => $l ) { echo '<tr><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $l[0] ) . '</a></td><td>' . esc_html( $l[1] ) . '</td></tr>'; }
	echo '</tbody></table>';
	foreach ( $rep['warn'] as $id => $w ) { echo '<p>⚠️ #' . (int) $id . ': ' . esc_html( $w ) . '</p>'; }
}

function zad_gm_preview_html( $plan, $posted ) {
	echo '<div class="notice notice-info inline" style="padding:10px 14px"><h2 style="margin-top:0">معاينة — لم يُنفَّذ شيء</h2>';
	if ( $plan['error'] ) { echo '<p style="color:#b32d2e"><b>' . esc_html( $plan['error'] ) . '</b></p></div>'; return; }
	echo '<ul style="list-style:disc;margin-inline-start:22px"><li>سيُنقل/يُدمج: <b>' . (int) $plan['ok'] . '</b> دليل · سيُتخطّى: <b>' . (int) $plan['skip'] . '</b></li>';
	echo '<li>تحويلات 301 جديدة: <b>' . (int) $plan['redir_add'] . '</b> · موجودة مسبقاً ولن تتكرر: <b>' . (int) $plan['redir_exist'] . '</b></li>';
	echo '<li>روابط داخلية ستُعدَّل: <b>' . (int) $plan['links']['total'] . '</b> (محتوى: ' . count( $plan['links']['content'] ) . ' صفحة · حقول: ' . count( $plan['links']['meta'] ) . ' · خيارات: ' . count( $plan['links']['options'] ) . ')</li>';
	echo '<li>' . ( $plan['archive'] ? 'هذا التنفيذ ينقل آخر دليل، فتُضاف تحويلات أرشيف /guide/ (بما فيه المرقّم) وتصنيفات best-guide، ويُحدَّث أي رابط لها.' : 'يبقى ' . (int) $plan['left'] . ' دليل غير محدد، لذا لا تُضاف تحويلات الأرشيف الآن (حتى لا يُخفى أرشيف /guide/ وفيه أدلة).' ) . '</li>';
	echo '<li>قواعد Redirection موجودة تشير إلى روابط ستتحوّل (يُعاد توجيهها لتفادي السلاسل): <b>' . count( $plan['inbound'] ) . '</b></li></ul>';
	foreach ( $plan['rows'] as $r ) { if ( 'skip' === $r['status'] ) { echo '<p style="color:#b32d2e">⚠️ ' . esc_html( $r['title'] ) . ': ' . esc_html( $r['why'] ) . '</p>'; } }
	echo '<form method="post">'; wp_nonce_field( 'zad_gm' ); echo zad_gm_hidden( $posted ); // phpcs:ignore
	echo '<p><button class="button button-primary" name="zad_gm_do" value="run" onclick="return confirm(\'تنفيذ الدمج على المحدد؟ لا يُحذف شيء نهائياً وتقدر تتراجع.\');"' . ( $plan['ok'] ? '' : ' disabled' ) . '>تأكيد التنفيذ</button></p></form></div>';
}

function zad_gm_table_html( $plan, $posted ) {
	$tl  = zad_gm_terms_list(); $done = zad_gm_log_get(); $has_log = (bool) ( $done['items'] || $done['trash'] );
	echo '<form method="post">'; wp_nonce_field( 'zad_gm' );
	echo '<h2>تصنيفات best_guide ← best_sections</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>تصنيف الأدلة</th><th>مقالات</th><th>يُربط بـ (قابل للتعديل)</th></tr></thead><tbody>';
	foreach ( $tl['guide'] as $id => $t ) {
		$cur = $plan['terms'][ $id ]['to'] ?? 'new';
		global $wpdb; $cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id = %d AND tt.taxonomy = 'best_guide'", $id ) ); // phpcs:ignore
		echo '<tr><td>' . esc_html( $t['name'] ) . ' <code>' . esc_html( urldecode( $t['slug'] ) ) . '</code></td><td>' . $cnt . '</td><td><select name="term[' . (int) $id . ']"><option value="new"' . selected( $cur, 'new', false ) . '>إنشاء تصنيف جديد بنفس الاسم والـ slug</option>';
		foreach ( $tl['sections'] as $s ) { echo '<option value="' . (int) $s->term_id . '"' . selected( $cur, (int) $s->term_id, false ) . '>' . esc_html( $s->name ) . '</option>'; }
		echo '</select>' . ( $t['why'] ? ' <small>(الاقتراح: ' . esc_html( $t['why'] ) . ')</small>' : '' ) . '</td></tr>';
	}
	if ( ! $tl['guide'] ) { echo '<tr><td colspan="3">لا توجد تصنيفات best_guide.</td></tr>'; }
	echo '</tbody></table><p>الأدلة بلا تصنيف تُوضع تحت: <select name="default_term"><option value="auto">«التنظيف العام»' . ( $plan['default_to'] ? '' : ' (يُنشأ إن لم يوجد)' ) . '</option>';
	foreach ( $tl['guide'] as $id => $t ) { echo '<option value="' . (int) $id . '"' . selected( $posted['default_term'], $id, false ) . '>تصنيف الأدلة: ' . esc_html( $t['name'] ) . '</option>'; }
	echo '</select></p>';

	echo '<h2>الأدلة (' . count( $plan['rows'] ) . ')</h2><p class="zad-gm-bar"><label>عرض: <select id="zad-gm-f"><option value="">الكل</option><option value="publish">المنشور</option><option value="draft">المسودات</option><option value="conf">التعارض</option></select></label> <button type="button" class="button" data-gm="trial">حدّد 3 للتجربة</button> <button type="button" class="button" data-gm="all">حدّد الكل</button> <button type="button" class="button" data-gm="none">ألغِ التحديد</button> <span id="zad-gm-n"></span></p>';
	echo '<table class="widefat striped" id="zad-gm-t"><thead><tr><th style="width:28px"></th><th>الدليل</th><th>الحالة</th><th>الرابط الحالي</th><th>الرابط الجديد / slug</th><th>تحويل 301</th><th>تعارض</th></tr></thead><tbody>';
	foreach ( $plan['rows'] as $id => $r ) {
		$hasterm = (bool) wp_get_object_terms( $id, 'best_guide', array( 'fields' => 'ids' ) );
		echo '<tr data-st="' . esc_attr( $r['wstatus'] ) . '" data-conf="' . (int) (bool) $r['conflict'] . '" data-term="' . (int) $hasterm . '" data-ar="' . (int) ( false !== strpos( $r['slug_old'], '%' ) ) . '"><td><input type="checkbox" name="row[' . (int) $id . '][sel]" value="1"' . checked( $r['sel'], true, false ) . '></td>';
		echo '<td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $r['title'] ) . '</a> <small>#' . (int) $id . '</small></td><td>' . esc_html( zad_gm_status_ar( $r['wstatus'] ) ) . '</td>';
		echo '<td dir="ltr" style="font-size:11px">' . ( $r['old_url'] ? esc_html( urldecode( (string) wp_parse_url( $r['old_url'], PHP_URL_PATH ) ) ) : '— (بلا slug)' ) . '</td>';
		echo '<td><input type="text" name="row[' . (int) $id . '][slug]" value="' . esc_attr( urldecode( $r['slug'] ) ) . '" dir="ltr" style="width:100%" placeholder="slug"' . ( '' === $r['slug_old'] ? ' disabled' : '' ) . '>' . ( $r['new_url'] ? '<small dir="ltr" style="display:block">' . esc_html( urldecode( (string) wp_parse_url( $r['new_url'], PHP_URL_PATH ) ) ) . '</small>' : '' ) . '</td>';
		echo '<td>' . ( 'publish' !== $r['wstatus'] ? '—' : ( 'add' === $r['redir'] ? 'يُضاف' : ( 'exists' === $r['redir'] ? 'موجود' : ( $r['sel'] ? '—' : 'عند التحديد' ) ) ) ) . '</td>';
		echo '<td>' . ( $r['conflict'] ? '<b style="color:#b32d2e">slug مستخدم في «' . esc_html( get_the_title( $r['conflict'] ) ) . '»</b><br><label><input type="radio" name="row[' . (int) $id . '][act]" value="move"' . checked( $r['act'], 'move', false ) . '> slug جديد (اكتبه)</label><br><label><input type="radio" name="row[' . (int) $id . '][act]" value="merge"' . checked( $r['act'], 'merge', false ) . '> دمج مع تحويل</label>' : '—' ) . ( 'skip' === $r['status'] ? '<br><span style="color:#b32d2e">' . esc_html( $r['why'] ) . '</span>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin-top:12px"><label><input type="checkbox" name="fix_inbound" value="1"' . checked( $posted['fix_inbound'], true, false ) . '> أعد توجيه قواعد Redirection الموجودة التي تشير لروابط الأدلة إلى الرابط الجديد مباشرة (لتفادي السلاسل)</label></p>';
	echo '<p><button class="button button-primary" name="zad_gm_do" value="preview">معاينة المحدد</button> ';
	echo '<button class="button" name="zad_gm_do" value="undo"' . ( $has_log ? '' : ' disabled' ) . ' onclick="return confirm(\'التراجع عن كل ما نُفِّذ بهذه الأداة؟\');">تراجع عن الكل' . ( $has_log ? ' (' . count( $done['items'] ) . ')' : '' ) . '</button></p></form>';
	?>
<script>
(function () {
	var t = document.getElementById('zad-gm-t'); if (!t) { return; }
	var rows = [].slice.call(t.tBodies[0].rows), f = document.getElementById('zad-gm-f'), n = document.getElementById('zad-gm-n');
	function cnt() { n.textContent = t.querySelectorAll('input[name$="[sel]"]:checked').length + ' محدد'; }
	f.addEventListener('change', function () { rows.forEach(function (r) { r.style.display = (!f.value || (f.value === 'conf' ? r.dataset.conf === '1' : r.dataset.st === f.value)) ? '' : 'none'; }); });
	t.addEventListener('change', cnt);
	function pick(fn) { rows.forEach(function (r) { var c = r.querySelector('input[name$="[sel]"]'); c.checked = fn(r); }); cnt(); }
	[].forEach.call(document.querySelectorAll('[data-gm]'), function (b) { b.addEventListener('click', function () {
		var k = b.getAttribute('data-gm'), g = { t: 0, n: 0, a: 0, k: 0 };
		if (k === 'all') { pick(function () { return true; }); } else if (k === 'none') { pick(function () { return false; }); }
		else { pick(function (r) { if (r.dataset.st !== 'publish' || g.k >= 3) { return false; } var pk = function () { g.k++; return true; }; if (r.dataset.conf === '1' && !g.c) { g.c = 1; return pk(); } if (r.dataset.ar === '1' && !g.a) { g.a = 1; return pk(); } if (r.dataset.term === '1' && !g.t) { g.t = 1; return pk(); } if (r.dataset.term === '0' && !g.n) { g.n = 1; return pk(); } return false; }); }
	}); });
	cnt();
})();
</script>
	<?php
}
