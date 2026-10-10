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

add_action( 'admin_menu', function () {
	add_management_page( 'دمج الأدلة في الأقسام', 'دمج الأدلة في الأقسام', 'manage_options', 'zad-guide-merge', 'zad_gm_page' );
} );

function zad_gm_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>دمج الأدلة في الأقسام <small style="font-weight:400">— المرحلة 1: الجرد (قراءة فقط)</small></h1>';
	echo '<div class="notice notice-info inline"><p>هذه الشاشة <b>لا تكتب أي شيء</b> في الموقع. تجمع بيانات الأدلة (guide) ومقابلها في الأقسام (sections) وتنتج تقريراً. انسخ التقرير بالكامل وأرسله. التنفيذ لا يبدأ إلا بعد موافقتك. وقبل أي تنفيذ لاحق خذ نسخة احتياطية كاملة من قاعدة البيانات.</p></div>';
	$inv = zad_gm_inventory();
	$md  = zad_gm_report_md( $inv );
	echo '<h2>التقرير (للنسخ)</h2><p><button type="button" class="button button-primary" id="zad-gm-copy">نسخ التقرير</button></p><textarea id="zad-gm-md" readonly rows="28" style="width:100%;font-family:monospace;direction:rtl">' . esc_textarea( $md ) . '</textarea>';
	echo '<script>document.getElementById("zad-gm-copy").addEventListener("click",function(){var t=document.getElementById("zad-gm-md");t.select();try{document.execCommand("copy");this.textContent="تم النسخ ✔";}catch(e){}});</script></div>';
}
