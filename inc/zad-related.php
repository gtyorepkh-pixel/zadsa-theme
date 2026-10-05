<?php
/**
 * "Related services & articles": a picker (search → click to add, × to remove, drag to reorder),
 * manual only (empty = the section is not shown), plus a cleanup tool for values that were auto-filled/saved by mistake.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_related_types() { return array_values( array_unique( array_merge( zad_service_types(), zad_article_types() ) ) ); }

function zad_related_label( $id ) {
	$pt = get_post_type( $id );
	return in_array( $pt, zad_service_types(), true ) ? 'خدمة' : 'مقال';
}

function zad_related_picker( $post_id, $ids ) {
	$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
	wp_nonce_field( 'zad_rel', 'zad_rel_n' );
	echo '<div class="zrel" data-ex="' . (int) $post_id . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'zad_rel' ) ) . '">';
	echo '<input type="hidden" name="zad[related_csv]" value="' . esc_attr( implode( ',', $ids ) ) . '" class="zrel__val">';
	echo '<input type="search" class="zrel__q widefat" placeholder="ابحث عن خدمة أو مقال بالاسم ثم اضغط عليه لإضافته…" autocomplete="off"><ul class="zrel__res"></ul>';
	echo '<ul class="zrel__sel">';
	foreach ( $ids as $id ) {
		if ( ! get_post( $id ) ) { continue; }
		echo '<li draggable="true" data-id="' . (int) $id . '"><span class="zrel__h">⋮⋮</span><b>' . esc_html( get_the_title( $id ) ) . '</b><em>' . esc_html( zad_related_label( $id ) ) . '</em><button type="button" class="zrel__x" aria-label="إزالة">×</button></li>';
	}
	echo '</ul><p class="description zrel__none"' . ( $ids ? ' hidden' : '' ) . '>لم تختر شيئاً: لن يظهر قسم «ذات صلة» في هذه الصفحة.</p></div>';
	?>
	<style>.zrel__res,.zrel__sel{list-style:none;margin:6px 0;padding:0}.zrel__res li{padding:7px 10px;border:1px solid #dcdcde;background:#fff;cursor:pointer}.zrel__res li:hover{background:#eef7fc}.zrel__sel li{display:flex;gap:10px;align-items:center;padding:8px 10px;margin:4px 0;background:#f6fafc;border:1px solid #cfe0e8;border-radius:6px;cursor:grab}.zrel__sel li b{flex:1}.zrel__sel li em{color:#0c687e;font-style:normal;font-size:12px}.zrel__x{border:0;background:#fee2e2;color:#b91c1c;border-radius:50%;width:24px;height:24px;cursor:pointer;line-height:1}.zrel__h{color:#999}.zrel__sel li.is-drag{opacity:.4}</style>
	<script>
	(function(){
		var box=document.querySelector('.zrel');if(!box||box.dataset.init)return;box.dataset.init=1;
		var val=box.querySelector('.zrel__val'),q=box.querySelector('.zrel__q'),res=box.querySelector('.zrel__res'),sel=box.querySelector('.zrel__sel'),none=box.querySelector('.zrel__none'),t;
		function sync(){var ids=[].map.call(sel.children,function(li){return li.dataset.id});val.value=ids.join(',');none.hidden=ids.length>0;}
		function has(id){return !!sel.querySelector('[data-id="'+id+'"]');}
		function add(it){if(has(it.id))return;var li=document.createElement('li');li.draggable=true;li.dataset.id=it.id;li.innerHTML='<span class="zrel__h">⋮⋮</span><b></b><em></em><button type="button" class="zrel__x" aria-label="إزالة">×</button>';li.querySelector('b').textContent=it.title;li.querySelector('em').textContent=it.label;sel.appendChild(li);sync();}
		sel.addEventListener('click',function(e){if(e.target.classList.contains('zrel__x')){e.target.closest('li').remove();sync();}});
		var drag=null;
		sel.addEventListener('dragstart',function(e){drag=e.target.closest('li');if(drag){drag.classList.add('is-drag');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','x');}});
		sel.addEventListener('dragend',function(){if(drag)drag.classList.remove('is-drag');drag=null;sync();});
		sel.addEventListener('dragover',function(e){e.preventDefault();var o=e.target.closest('li');if(!drag||!o||o===drag)return;var r=o.getBoundingClientRect();sel.insertBefore(drag,(e.clientY-r.top)>r.height/2?o.nextSibling:o);});
		q.addEventListener('keydown',function(e){if(e.key==='Enter')e.preventDefault();});
		q.addEventListener('input',function(){clearTimeout(t);var s=q.value.trim();if(s.length<2){res.innerHTML='';return;}t=setTimeout(function(){
			var u=box.dataset.ajax+'?action=zad_rel_search&_wpnonce='+encodeURIComponent(box.dataset.nonce)+'&exc='+box.dataset.ex+'&q='+encodeURIComponent(s);
			fetch(u,{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){res.innerHTML='';(j.data||[]).forEach(function(it){if(has(it.id))return;var li=document.createElement('li');li.textContent=it.title+' — '+it.label;li.onclick=function(){add(it);li.remove();};res.appendChild(li);});});
		},250);});
	})();
	</script>
	<?php
}

add_action( 'wp_ajax_zad_rel_search', function () {
	check_ajax_referer( 'zad_rel' );
	if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error(); }
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
	$ex = isset( $_GET['exc'] ) ? absint( $_GET['exc'] ) : 0; // phpcs:ignore
	if ( mb_strlen( $q ) < 2 ) { wp_send_json_success( array() ); }
	$posts = get_posts( array( 's' => $q, 'post_type' => zad_related_types(), 'post_status' => 'publish', 'numberposts' => 12, 'post__not_in' => array( $ex ), 'suppress_filters' => false, 'zad_all' => true ) );
	$out = array();
	foreach ( $posts as $p ) { $out[] = array( 'id' => $p->ID, 'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), 'label' => zad_related_label( $p->ID ) ); }
	wp_send_json_success( $out );
} );

function zad_related_save( $post_id, $in ) {
	if ( ! isset( $in['related_csv'] ) ) { return; }
	$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $in['related_csv'] ) ) ) ) );
	$ok  = array();
	foreach ( $ids as $id ) {
		if ( $id !== (int) $post_id && in_array( get_post_type( $id ), zad_related_types(), true ) ) { $ok[] = $id; }
	}
	update_post_meta( $post_id, '_zad_related', array_slice( $ok, 0, 12 ) );
}

/* ---------- Cleanup tool ---------- */
add_action( 'admin_menu', function () {
	add_management_page( 'تنظيف «ذات صلة»', 'تنظيف «ذات صلة» (زاد)', 'manage_options', 'zad-related-clean', 'zad_related_clean_page' );
} );

function zad_related_clean_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$msg = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zad_rc'] ) && check_admin_referer( 'zad_rc' ) ) { // phpcs:ignore
		$act = sanitize_key( wp_unslash( $_POST['zad_rc'] ) ); $n = 0;
		if ( 'clear' === $act ) {
			foreach ( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) as $id ) { // phpcs:ignore
				$v = get_post_meta( $id, '_zad_related', true );
				if ( is_array( $v ) && $v ) { update_post_meta( $id, '_zad_related_backup', $v ); update_post_meta( $id, '_zad_related', array() ); $n++; }
			}
			$msg = "فُرِّغ حقل «ذات صلة» في {$n} صفحة (نسخة احتياطية محفوظة).";
		} elseif ( 'undo' === $act ) {
			$id = absint( $_POST['undo_id'] ?? 0 ); $b = get_post_meta( $id, '_zad_related_backup', true );
			if ( is_array( $b ) ) { update_post_meta( $id, '_zad_related', $b ); delete_post_meta( $id, '_zad_related_backup' ); $msg = 'تم التراجع.'; }
		}
	}
	$ids = get_posts( array( 'post_type' => zad_related_types(), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1000, 'fields' => 'ids', 'suppress_filters' => true, 'zad_all' => true, 'meta_query' => array( 'relation' => 'OR', array( 'key' => '_zad_related', 'compare' => 'EXISTS' ), array( 'key' => '_zad_related_backup', 'compare' => 'EXISTS' ) ) ) );
	$total = count( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids', 'suppress_filters' => true, 'zad_all' => true ) ) );
	echo '<div class="wrap" dir="rtl"><h1>تنظيف «خدمات ذات صلة»</h1><p>يعرض الصفحات التي فيها قيمة محفوظة في حقل «ذات صلة». الصفحات التي اختير فيها عدد كبير غالباً امتلأت بالخطأ (اختيار الكل). التفريغ لا يغيّر شيئاً آخر، ويُحفظ ما فُرِّغ للتراجع.</p>';
	if ( $msg ) { echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>'; }
	$rows = array();
	foreach ( $ids as $id ) {
		$v = get_post_meta( $id, '_zad_related', true ); $v = is_array( $v ) ? array_filter( array_map( 'intval', $v ) ) : array();
		$bk = is_array( get_post_meta( $id, '_zad_related_backup', true ) );
		if ( $v || $bk ) { $rows[ $id ] = array( count( $v ), $bk ); }
	}
	if ( ! $rows ) { echo '<p><b>لا توجد صفحات فيها قيمة محفوظة.</b></p></div>'; return; }
	uasort( $rows, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
	echo '<form method="post">'; wp_nonce_field( 'zad_rc' );
	echo '<p><label><input type="checkbox" onclick="jQuery(\'.zrc\').prop(\'checked\',this.checked)"> تحديد الكل</label></p>';
	echo '<table class="widefat striped"><thead><tr><th></th><th>الصفحة</th><th>النوع</th><th>عدد المختار</th><th>تقدير</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $id => $r ) {
		$many = $r[0] >= 8 || ( $total && $r[0] >= $total * 0.5 );
		echo '<tr><td>' . ( $r[0] ? '<input class="zrc" type="checkbox" name="ids[]" value="' . (int) $id . '"' . ( $many ? ' checked' : '' ) . '>' : '' ) . '</td><td><a href="' . esc_url( get_edit_post_link( $id, 'raw' ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( get_post_type( $id ) ) . '</td><td>' . (int) $r[0] . '</td><td>' . ( $many ? '<span style="color:#b91c1c">يبدو اختيار الكل</span>' : ( $r[0] ? 'اختيار محدود' : '—' ) ) . '</td><td>' . ( $r[1] ? '<button class="button" name="zad_rc" value="undo" onclick="this.form.undo_id.value=' . (int) $id . '">تراجع</button>' : '' ) . '</td></tr>';
	}
	echo '</tbody></table><input type="hidden" name="undo_id" value=""><p><button class="button button-primary" name="zad_rc" value="clear" onclick="return confirm(\'تفريغ الصفحات المحددة؟\')">تفريغ المحدد</button></p></form></div>';
}


/* ---------- Coverage section ("نصل إليك في أي حي"): shown only when filled ---------- */
function zad_cov_keys() { return array( 'eyebrow', 'title', 'sub', 'box', 'text', 'chips', 'note', 'stats' ); }

/** Split "name | url" lines into array( name, url ). */
function zad_cov_chips( $text ) {
	$chips = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $l ) {
		$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 2, '' );
		if ( '' === $c[0] ) { continue; }
		$u = $c[1]; if ( '' !== $u && '/' === $u[0] && 0 !== strpos( $u, '//' ) ) { $u = home_url( $u ); }
		$chips[] = array( $c[0], preg_match( '#^https?://#', $u ) ? $u : '' );
	}
	return $chips;
}

/** Chips from the page's service_area terms (links: real hierarchical page, else an open area archive, else plain text). */
function zad_cov_area_chips( $post_id ) {
	$out   = array();
	$areas = $post_id ? get_the_terms( $post_id, 'service_area' ) : false;
	if ( ! $areas || is_wp_error( $areas ) ) { return $out; }
	foreach ( $areas as $t ) {
		$out[] = array( $t->name, zad_area_link( $post_id, $t ) );
	}
	return $out;
}

/** One district: a single <a> (or <span> without a link): no list item, no icon, no inner wrapper. */
function zad_cov_chip_li( $c ) {
	return $c[1]
		? '<a class="cov__chip cov__chip--link" href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>'
		: '<span class="cov__chip cov__chip--plain">' . esc_html( $c[0] ) . '</span>';
}

/**
 * Coverage section. Page data lives in _zad_cov (keys: eyebrow,title,sub,box,text,chips,note,stats).
 * title/text/chips/note come from the page only; eyebrow/sub/box/stats fall back to the global options when empty.
 */
function zad_coverage_html( $post_id = 0, $home = false ) {
	$raw = $post_id ? (array) get_post_meta( $post_id, '_zad_cov', true ) : array();
	$own = array();
	foreach ( zad_cov_keys() as $k ) {
		$v = ( isset( $raw[ $k ] ) && is_string( $raw[ $k ] ) ) ? trim( $raw[ $k ] ) : '';
		if ( '' !== $v ) { $own[ $k ] = $v; }
	}
	$glob = function ( $k ) { return (string) zad_opt( 'zad_cov_' . $k, '' ); };
	$area_chips = $post_id ? zad_cov_area_chips( $post_id ) : array();
	$page_has = isset( $own['title'] ) || isset( $own['text'] ) || isset( $own['chips'] ) || isset( $own['note'] );
	if ( $page_has ) {
		$d = array();
		foreach ( array( 'title', 'text', 'chips', 'note' ) as $k ) { $d[ $k ] = $own[ $k ] ?? ''; }
		foreach ( array( 'eyebrow', 'sub', 'box', 'stats' ) as $k ) { $d[ $k ] = $own[ $k ] ?? $glob( $k ); }
	} elseif ( $home || zad_opt( 'zad_cov_services', false ) ) {
		$d = array();
		foreach ( zad_cov_keys() as $k ) { $d[ $k ] = $glob( $k ); }
	} elseif ( $area_chips ) { // no coverage data on the page: build it from the page's areas (replaces the old plain section)
		$d = array( 'title' => 'نصل إليك في أي منطقة', 'text' => '', 'chips' => '', 'note' => '' );
		foreach ( array( 'eyebrow', 'sub', 'box', 'stats' ) as $k ) { $d[ $k ] = $glob( $k ); }
		if ( '' === $d['eyebrow'] ) { $d['eyebrow'] = 'تغطيتنا'; }
	} else { return ''; }
	$chips = zad_cov_chips( $d['chips'] );
	if ( $chips && $area_chips ) { // own chips without a link borrow the link of the same-named area
		$byname = array();
		foreach ( $area_chips as $a ) { $byname[ mb_strtolower( trim( $a[0] ) ) ] = $a[1]; }
		foreach ( $chips as $i => $c ) { if ( '' === $c[1] ) { $chips[ $i ][1] = $byname[ mb_strtolower( trim( $c[0] ) ) ] ?? ''; } }
	}
	if ( ! $chips ) { $chips = $area_chips; }
	if ( '' === trim( $d['title'] ) && ! $chips ) { return ''; }
	$stats = array();
	foreach ( array_slice( preg_split( '/\r\n|\r|\n/', (string) $d['stats'] ), 0, 3 ) as $l ) { $c = array_pad( array_map( 'trim', explode( '|', $l ) ), 2, '' ); if ( '' !== $c[0] ) { $stats[] = $c; } }
	$o = '<section class="sec cov"><div class="wrap"><header class="cov__head">';
	if ( $d['eyebrow'] ) { $o .= '<span class="cov__eyebrow">' . esc_html( $d['eyebrow'] ) . '</span>'; }
	if ( $d['title'] ) { $o .= '<h2>' . esc_html( $d['title'] ) . '</h2>'; }
	if ( $d['sub'] ) { $o .= '<p>' . esc_html( $d['sub'] ) . '</p>'; }
	$o .= '</header><div class="cov__card' . ( $stats ? ' has-stats' : '' ) . '">';
	if ( $stats ) {
		$o .= '<div class="cov__stats">';
		foreach ( $stats as $c ) { $o .= '<div class="cov__st"><b>' . esc_html( $c[0] ) . '</b><span>' . esc_html( $c[1] ) . '</span></div>'; }
		$o .= '</div>';
	}
	$o .= '<div class="cov__main">';
	if ( $d['box'] ) { $o .= '<h3>' . esc_html( $d['box'] ) . '</h3>'; }
	if ( $d['text'] ) { $o .= '<p class="cov__text">' . nl2br( esc_html( $d['text'] ) ) . '</p>'; }
	if ( $chips ) {
		$n = count( $chips );
		$o .= '<div class="cov__chips">';
		foreach ( array_slice( $chips, 0, 12 ) as $c ) { $o .= zad_cov_chip_li( $c ); }
		$o .= '</div>';
		if ( $n > 12 ) { // first 12 shown; the rest stay in the HTML (crawlable) inside <details>
			$o .= '<details class="cov__more"><summary>عرض كل الأحياء (' . (int) $n . ')</summary><div class="cov__chips">';
			foreach ( array_slice( $chips, 12 ) as $c ) { $o .= zad_cov_chip_li( $c ); }
			$o .= '</div></details>';
		}
	}
	if ( $d['note'] ) { $o .= '<p class="cov__note">' . zad_icon( 'check', 16 ) . ' ' . esc_html( $d['note'] ) . '</p>'; }
	return $o . '</div></div></div></section>';
}

/* per-page override fields (service pages) */
function zad_coverage_box( $post_id ) {
	$v = (array) get_post_meta( $post_id, '_zad_cov', true );
	echo '<h4>التغطية في هذه الصفحة <small>(اختياري؛ ما تتركه فارغاً من الوسم/السطر/العنوان الفرعي/الأرقام يؤخذ من الإعدادات العامة)</small></h4><div class="zad-grid">';
	echo '<p><label>الوسم الصغير<input type="text" name="zad[cov_eyebrow]" value="' . esc_attr( $v['eyebrow'] ?? '' ) . '" placeholder="تغطيتنا"></label></p>';
	echo '<p><label>العنوان<input type="text" name="zad[cov_title]" value="' . esc_attr( $v['title'] ?? '' ) . '" placeholder="نصل إليك في أي حي بالرياض"></label></p>';
	echo '<p><label>السطر تحت العنوان<input type="text" name="zad[cov_sub]" value="' . esc_attr( $v['sub'] ?? '' ) . '"></label></p>';
	echo '<p><label>عنوان البطاقة<input type="text" name="zad[cov_box]" value="' . esc_attr( $v['box'] ?? '' ) . '"></label></p>';
	echo '<p><label>الملاحظة الأخيرة<input type="text" name="zad[cov_note]" value="' . esc_attr( $v['note'] ?? '' ) . '"></label></p></div>';
	echo '<p><label>الوصف<textarea name="zad[cov_text]" rows="2" style="width:100%">' . esc_textarea( $v['text'] ?? '' ) . '</textarea></label></p>';
	echo '<p><label>الأحياء (سطر لكل حي: الاسم | الرابط اختياري)<textarea name="zad[cov_chips]" rows="4" style="width:100%">' . esc_textarea( $v['chips'] ?? '' ) . '</textarea></label></p>';
	echo '<p><label>بطاقات الأرقام (سطر لكل رقم: الرقم | الوصف — حتى 3 أسطر)<textarea name="zad[cov_stats]" rows="3" style="width:100%" placeholder="24/7 | استقبال الطلبات">' . esc_textarea( $v['stats'] ?? '' ) . '</textarea></label></p>';
}

function zad_coverage_save( $post_id, $in ) {
	if ( ! array_key_exists( 'cov_title', $in ) ) { return; }
	update_post_meta( $post_id, '_zad_cov', array(
		'eyebrow' => sanitize_text_field( $in['cov_eyebrow'] ?? '' ),
		'title'   => sanitize_text_field( $in['cov_title'] ?? '' ),
		'sub'     => sanitize_text_field( $in['cov_sub'] ?? '' ),
		'box'     => sanitize_text_field( $in['cov_box'] ?? '' ),
		'note'    => sanitize_text_field( $in['cov_note'] ?? '' ),
		'text'    => sanitize_textarea_field( $in['cov_text'] ?? '' ),
		'chips'   => sanitize_textarea_field( $in['cov_chips'] ?? '' ),
		'stats'   => sanitize_textarea_field( $in['cov_stats'] ?? '' ),
	) );
}


/* ---------- "Guides" on a service page: same category or the service's own keywords only ---------- */
function zad_is_placeholder_post( $p ) {
	return 'hello-world' === $p->post_name || in_array( trim( (string) $p->post_title ), array( 'Hello world!', 'مرحبا بالعالم!', 'أهلاً بالعالم!', 'أهلا بالعالم!' ), true );
}
/** Articles picked by hand in the service editor (_zad_guides). Empty = the section is not shown (never auto-filled). */
function zad_service_guides( $id, $limit = 4 ) {
	$ids = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, '_zad_guides', true ) ) ) );
	if ( ! $ids ) { return array(); }
	$out = array();
	foreach ( get_posts( array( 'post_type' => zad_article_types(), 'post__in' => $ids, 'orderby' => 'post__in', 'post_status' => 'publish', 'numberposts' => $limit, 'ignore_sticky_posts' => true ) ) as $p ) {
		if ( ! zad_is_placeholder_post( $p ) ) { $out[] = $p; }
	}
	return $out;
}

/** Editor field: choose the articles for «مقالات ونصائح مفيدة». */
function zad_guides_box( $post_id ) {
	$sel = array_map( 'intval', (array) get_post_meta( $post_id, '_zad_guides', true ) );
	echo '<h4>مقالات ونصائح مفيدة <small>(اختيار يدوي؛ فارغ = لا يظهر القسم. يظهر فقط إن فُعِّل من إعدادات القالب)</small></h4><input type="hidden" name="zad[guides_present]" value="1"><select name="zad[guides][]" multiple size="8" style="width:100%">';
	foreach ( get_posts( array( 'post_type' => zad_article_types(), 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'date', 'order' => 'DESC' ) ) as $a ) {
		echo '<option value="' . (int) $a->ID . '"' . ( in_array( (int) $a->ID, $sel, true ) ? ' selected' : '' ) . '>' . esc_html( $a->post_title ) . '</option>';
	}
	echo '</select><p class="description">اضغط Ctrl/⌘ لاختيار أكثر من مقال (حتى 4 تظهر). الترتيب حسب القائمة.</p>';
}
function zad_guides_save( $post_id, $in ) {
	if ( empty( $in['guides_present'] ) ) { return; }
	$ids = isset( $in['guides'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $in['guides'] ) ) ) ) : array();
	update_post_meta( $post_id, '_zad_guides', array_slice( $ids, 0, 12 ) );
}
