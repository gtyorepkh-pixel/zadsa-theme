<?php
/**
 * Neighbourhood library + "صفحة حي" layout (part of the theme).
 * - Library CPT (non-public): shared data per district.
 * - Per-page box on service pages / Pages: pick the district + service, fill local sections.
 * - Nothing is generated, no rewrite rules, no URL or content of existing pages is touched:
 *   the layout is applied only to pages where the owner switches it on.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

const ZAD_HOOD_CPT  = 'zad_hood';
const ZAD_HOOD_TAX  = 'zad_hood_city';
const ZAD_HOOD_TPL  = 'temp/zad-hood.php';

/* ------------------------------------------------------------------ */
/* Library: CPT + city taxonomy (not public, no URLs)                   */
/* ------------------------------------------------------------------ */
add_action( 'init', function () {
	register_post_type( ZAD_HOOD_CPT, array(
		'labels' => array( 'name' => 'الأحياء', 'singular_name' => 'حي', 'add_new' => 'إضافة حي', 'add_new_item' => 'إضافة حي جديد', 'edit_item' => 'تعديل الحي', 'all_items' => 'مكتبة الأحياء', 'menu_name' => 'الأحياء', 'search_items' => 'بحث في الأحياء' ),
		'public' => false, 'show_ui' => true, 'publicly_queryable' => false, 'rewrite' => false, 'query_var' => false,
		'exclude_from_search' => true, 'has_archive' => false, 'show_in_rest' => false, 'menu_icon' => 'dashicons-location-alt', 'menu_position' => 26,
		'supports' => array( 'title' ), 'capability_type' => 'post', 'taxonomies' => array( ZAD_HOOD_TAX ),
	) );
	register_taxonomy( ZAD_HOOD_TAX, ZAD_HOOD_CPT, array(
		'labels' => array( 'name' => 'المدن', 'singular_name' => 'مدينة', 'menu_name' => 'المدن', 'add_new_item' => 'إضافة مدينة' ),
		'public' => false, 'show_ui' => true, 'publicly_queryable' => false, 'rewrite' => false, 'query_var' => false,
		'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => false,
	) );
}, 5 );

function zad_hood_services() {
	$d = "تنظيف مكيفات\nتنظيف خزانات\nتنظيف كنب\nمكافحة حشرات\nتسليك مجاري\nجلي بلاط ورخام\nنقل أثاث";
	return array_values( array_filter( array_map( 'trim', explode( "\n", (string) zad_opt( 'zad_hood_services', $d ) ) ) ) );
}

/** Post types that can carry the district layout. */
function zad_hood_types() {
	return array_values( array_unique( array_merge( zad_service_types(), array( 'page' ) ) ) );
}

function zad_hood_active( $id ) {
	$pt = get_post_type( $id );
	if ( 'page' === $pt ) {
		return get_page_template_slug( $id ) === ZAD_HOOD_TPL;
	}
	return in_array( $pt, zad_service_types(), true ) && '1' === (string) get_post_meta( $id, '_zad_h_on', true );
}

/* ------------------------------------------------------------------ */
/* Library meta box                                                     */
/* ------------------------------------------------------------------ */
function zad_hood_sides()   { return array( 'north' => 'شمال', 'south' => 'جنوب', 'east' => 'شرق', 'west' => 'غرب', 'center' => 'وسط' ); }
function zad_hood_housing() { return array( 'فلل', 'شقق', 'عماير قديمة', 'مجمعات' ); }
function zad_hood_ac()      { return array( 'مركزي', 'سبليت', 'شباك' ); }
function zad_hood_days()    { return array( 'السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة' ); }

function zad_hood_library() {
	$q = get_posts( array( 'post_type' => ZAD_HOOD_CPT, 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
	return $q ? $q : array();
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_hood_data', 'بيانات الحي (تُكتب مرة واحدة وتُستخدم في كل الصفحات)', 'zad_hood_data_box', ZAD_HOOD_CPT, 'normal', 'high' );
	foreach ( zad_hood_types() as $pt ) {
		add_meta_box( 'zad_hood_page', 'صفحة حي (زاد)', 'zad_hood_page_box', $pt, 'normal', 'default' );
	}
} );

function zad_hood_data_box( $post ) {
	wp_nonce_field( 'zad_hood_lib', 'zad_hood_lib_n' );
	$g = function ( $k, $d = '' ) use ( $post ) { $v = get_post_meta( $post->ID, '_zad_hd_' . $k, true ); return '' === $v ? $d : $v; };
	$housing = (array) $g( 'housing', array() ); $ac = (array) $g( 'ac', array() ); $nb = array_map( 'intval', (array) $g( 'nbrs', array() ) );
	echo '<p class="description">كل الحقول اختيارية. اكتب بيانات حقيقية فقط. المدينة تُختار من اليمين (صندوق «المدن»).</p><table class="form-table"><tbody>';
	echo '<tr><th>الاسم بالإنجليزي</th><td><input type="text" name="zd[en]" value="' . esc_attr( $g( 'en' ) ) . '" class="regular-text" dir="ltr"></td></tr>';
	echo '<tr><th>خط العرض / الطول</th><td dir="ltr"><input type="text" name="zd[lat]" value="' . esc_attr( $g( 'lat' ) ) . '" size="12" placeholder="24.7136"> <input type="text" name="zd[lng]" value="' . esc_attr( $g( 'lng' ) ) . '" size="12" placeholder="46.6753"> <span class="description">إحداثيات الحي نفسه</span></td></tr>';
	echo '<tr><th>الجهة</th><td><select name="zd[side]"><option value="">—</option>';
	foreach ( zad_hood_sides() as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $g( 'side' ), $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></td></tr>';
	echo '<tr><th>نوع السكن الغالب</th><td>';
	foreach ( zad_hood_housing() as $l ) { echo '<label style="margin-inline-end:14px"><input type="checkbox" name="zd[housing][]" value="' . esc_attr( $l ) . '"' . checked( in_array( $l, $housing, true ), true, false ) . '> ' . esc_html( $l ) . '</label>'; }
	echo '</td></tr><tr><th>نوع الخزانات الغالب</th><td><select name="zd[tanks]"><option value="">—</option>';
	foreach ( array( 'أرضي', 'علوي', 'الاثنين' ) as $l ) { echo '<option' . selected( $g( 'tanks' ), $l, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></td></tr><tr><th>نوع المكيفات الغالب</th><td>';
	foreach ( zad_hood_ac() as $l ) { echo '<label style="margin-inline-end:14px"><input type="checkbox" name="zd[ac][]" value="' . esc_attr( $l ) . '"' . checked( in_array( $l, $ac, true ), true, false ) . '> ' . esc_html( $l ) . '</label>'; }
	echo '</td></tr><tr><th>البيئة المحيطة</th><td><input type="text" name="zd[env]" value="' . esc_attr( $g( 'env' ) ) . '" class="large-text" placeholder="مثال: قرب أودية أو أراضٍ فضاء أو مشاريع إنشاء"></td></tr>';
	echo '<tr><th>وصف عام للحي</th><td><textarea name="zd[desc]" rows="3" class="large-text" placeholder="سطران من معلومات حقيقية فقط">' . esc_textarea( $g( 'desc' ) ) . '</textarea></td></tr>';
	echo '<tr><th>الأحياء المجاورة</th><td><select name="zd[nbrs][]" multiple size="8" style="min-width:260px">';
	foreach ( zad_hood_library() as $h ) { if ( $h->ID === $post->ID ) { continue; } echo '<option value="' . (int) $h->ID . '"' . selected( in_array( $h->ID, $nb, true ), true, false ) . '>' . esc_html( $h->post_title ) . '</option>'; }
	echo '</select><p class="description">Ctrl/⌘ لاختيار أكثر من حي. تُستخدم للربط الداخلي، ولا يظهر حي إلا إذا كانت له صفحة منشورة.</p></td></tr></tbody></table>';
}

add_action( 'save_post_' . ZAD_HOOD_CPT, function ( $id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || empty( $_POST['zad_hood_lib_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_hood_lib_n'] ) ), 'zad_hood_lib' ) || ! current_user_can( 'edit_post', $id ) ) { return; } // phpcs:ignore
	$d = isset( $_POST['zd'] ) && is_array( $_POST['zd'] ) ? wp_unslash( $_POST['zd'] ) : array(); // phpcs:ignore
	$num = function ( $v ) { $v = trim( (string) $v ); return is_numeric( $v ) ? (string) (float) $v : ''; };
	update_post_meta( $id, '_zad_hd_en', sanitize_text_field( $d['en'] ?? '' ) );
	update_post_meta( $id, '_zad_hd_lat', $num( $d['lat'] ?? '' ) );
	update_post_meta( $id, '_zad_hd_lng', $num( $d['lng'] ?? '' ) );
	update_post_meta( $id, '_zad_hd_side', isset( zad_hood_sides()[ $d['side'] ?? '' ] ) ? $d['side'] : '' );
	update_post_meta( $id, '_zad_hd_housing', array_values( array_intersect( (array) ( $d['housing'] ?? array() ), zad_hood_housing() ) ) );
	update_post_meta( $id, '_zad_hd_tanks', in_array( $d['tanks'] ?? '', array( 'أرضي', 'علوي', 'الاثنين' ), true ) ? $d['tanks'] : '' );
	update_post_meta( $id, '_zad_hd_ac', array_values( array_intersect( (array) ( $d['ac'] ?? array() ), zad_hood_ac() ) ) );
	update_post_meta( $id, '_zad_hd_env', sanitize_text_field( $d['env'] ?? '' ) );
	update_post_meta( $id, '_zad_hd_desc', sanitize_textarea_field( $d['desc'] ?? '' ) );
	update_post_meta( $id, '_zad_hd_nbrs', array_values( array_filter( array_map( 'absint', (array) ( $d['nbrs'] ?? array() ) ) ) ) );
	zad_hood_bump();
} );

/* ------------------------------------------------------------------ */
/* Per-page box                                                         */
/* ------------------------------------------------------------------ */
function zad_hood_row( $name, $fields, $vals ) {
	$o = '<div class="zh-row">';
	foreach ( $fields as $k => $ph ) {
		$v = $vals[ $k ] ?? '';
		$o .= ( 'a' === $k || 'd' === $k || ( 't' === $k && 'zh_reviews' === $name ) )
			? '<textarea name="' . esc_attr( $name ) . '[' . esc_attr( '%IDX%' ) . '][' . $k . ']" rows="2" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $v ) . '</textarea>'
			: '<input type="' . ( 'r' === $k ? 'number' : 'text' ) . '" ' . ( 'r' === $k ? 'min="1" max="5" style="width:80px" ' : '' ) . 'name="' . esc_attr( $name ) . '[' . esc_attr( '%IDX%' ) . '][' . $k . ']" value="' . esc_attr( $v ) . '" placeholder="' . esc_attr( $ph ) . '">';
	}
	return $o . '<button type="button" class="button zh-del">×</button></div>';
}

function zad_hood_repeater( $key, $title, $fields, $items ) {
	echo '<div class="zh-rep" data-key="' . esc_attr( $key ) . '"><strong>' . esc_html( $title ) . '</strong><div class="zh-list">';
	$i = 0;
	foreach ( (array) $items as $it ) { echo str_replace( '%IDX%', (string) $i++, zad_hood_row( 'zh_' . $key, $fields, (array) $it ) ); } // phpcs:ignore
	echo '</div><template>' . zad_hood_row( 'zh_' . $key, $fields, array() ) . '</template><p><button type="button" class="button zh-add">+ إضافة</button></p></div>'; // phpcs:ignore
}

function zad_hood_page_box( $post ) {
	wp_nonce_field( 'zad_hood_pg', 'zad_hood_pg_n' );
	$g = function ( $k, $d = '' ) use ( $post ) { $v = get_post_meta( $post->ID, '_zad_h_' . $k, true ); return '' === $v ? $d : $v; };
	$is_page = 'page' === $post->post_type;
	echo '<style>.zh-row{display:flex;gap:6px;margin:4px 0;align-items:flex-start}.zh-row input,.zh-row textarea{flex:1}.zh-rep{margin:14px 0;padding:10px;background:#f6fafc;border:1px solid #dbe7ee;border-radius:6px}#zad_hood_page .zh-t th{width:150px}</style>';
	if ( $is_page ) {
		echo '<p class="description">لتفعيل التصميم على هذه الصفحة اختر القالب <b>«صفحة حي»</b> من خيارات القالب (Template)، ثم عبّئ الحقول هنا.</p>';
	} else {
		echo '<p><label><input type="checkbox" name="zh_on" value="1"' . checked( '1', (string) $g( 'on' ), false ) . '> <b>استخدم قالب «صفحة حي» لهذه الصفحة</b> — الرابط والمحتوى الحاليان لا يتغيران، وتُضاف الأقسام الجديدة حول محتواك.</label></p>';
	}
	echo '<table class="form-table zh-t"><tbody><tr><th>الحي</th><td><select name="zh_hood"><option value="">— اختر من المكتبة —</option>';
	$cur = (int) $g( 'hood', 0 );
	foreach ( zad_hood_library() as $h ) { echo '<option value="' . (int) $h->ID . '"' . selected( $cur, $h->ID, false ) . '>' . esc_html( $h->post_title ) . '</option>'; }
	echo '</select> <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . ZAD_HOOD_CPT ) ) . '" target="_blank">+ حي جديد</a></td></tr>';
	echo '<tr><th>الخدمة</th><td><select name="zh_svc"><option value="">—</option>';
	foreach ( zad_hood_services() as $l ) { echo '<option' . selected( $g( 'svc' ), $l, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select></td></tr>';
	echo '<tr><th>الفقرة المحلية</th><td><textarea name="zh_local" rows="3" class="large-text" placeholder="لماذا تختلف الخدمة في هذا الحي؟">' . esc_textarea( $g( 'local' ) ) . '</textarea></td></tr>';
	echo '<tr><th>أرقامنا في الحي</th><td>عدد الطلبات المنفذة: <input type="number" min="0" name="zh_orders" value="' . esc_attr( $g( 'orders' ) ) . '" style="width:90px"> &nbsp; متوسط وقت الوصول (دقيقة): <input type="number" min="0" name="zh_eta" value="' . esc_attr( $g( 'eta' ) ) . '" style="width:90px"><br><span class="description">لا تكتب إلا أرقاماً حقيقية؛ القسم لا يظهر إن كانت فارغة.</span></td></tr>';
	$days = (array) $g( 'days', array() );
	echo '<tr><th>أيام تواجد الفريق</th><td>';
	foreach ( zad_hood_days() as $d ) { echo '<label style="margin-inline-end:12px"><input type="checkbox" name="zh_days[]" value="' . esc_attr( $d ) . '"' . checked( in_array( $d, $days, true ), true, false ) . '> ' . esc_html( $d ) . '</label>'; }
	echo '</td></tr><tr><th>نص رسالة واتساب</th><td><input type="text" name="zh_wa" class="large-text" value="' . esc_attr( $g( 'wa' ) ) . '" placeholder="أحتاج {الخدمة} في حي {الحي}"><br><span class="description">يمكن استخدام {الخدمة} و{الحي}.</span></td></tr></tbody></table>';
	zad_hood_repeater( 'problems', 'المشاكل الشائعة في هذا الحي', array( 't' => 'العنوان', 'd' => 'السبب في هذا الحي' ), $g( 'problems', array() ) );
	echo '<div class="zh-rep"><strong>صور قبل وبعد</strong> <span class="description">(صورتان أو أكثر: قبل ثم بعد، بدون أي بيانات شخصية)</span><p><input type="hidden" name="zh_ba" id="zh_ba" value="' . esc_attr( $g( 'ba' ) ) . '"><button type="button" class="button" id="zh_ba_btn">اختيار الصور</button> <span id="zh_ba_n">' . count( array_filter( explode( ',', (string) $g( 'ba' ) ) ) ) . ' صورة</span></p></div>';
	zad_hood_repeater( 'reviews', 'آراء عملاء من الحي (آراء حقيقية فقط)', array( 'n' => 'الاسم الأول', 'r' => '1-5', 't' => 'النص' ), $g( 'reviews', array() ) );
	zad_hood_repeater( 'faq', 'أسئلة شائعة خاصة بالحي', array( 'q' => 'السؤال', 'a' => 'الإجابة' ), $g( 'faq', array() ) );
	?>
	<script>
	(function(){
		var box=document.getElementById('zad_hood_page');if(!box)return;
		box.addEventListener('click',function(e){
			var t=e.target;
			if(t.classList.contains('zh-add')){var rep=t.closest('.zh-rep'),list=rep.querySelector('.zh-list'),tpl=rep.querySelector('template'),n=Date.now();var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/%IDX%/g,'n'+n);list.appendChild(d.firstElementChild);}
			if(t.classList.contains('zh-del')){t.closest('.zh-row').remove();}
		});
		var b=document.getElementById('zh_ba_btn');
		if(b&&window.wp&&wp.media){var fr;b.addEventListener('click',function(){
			if(!fr){fr=wp.media({title:'صور قبل وبعد',multiple:'add',library:{type:'image'}});fr.on('select',function(){var ids=fr.state().get('selection').map(function(a){return a.id});document.getElementById('zh_ba').value=ids.join(',');document.getElementById('zh_ba_n').textContent=ids.length+' صورة';});}
			fr.open();});}
	})();
	</script>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { wp_enqueue_media(); }
} );

add_action( 'save_post', function ( $id, $post ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || empty( $_POST['zad_hood_pg_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_hood_pg_n'] ) ), 'zad_hood_pg' ) || ! current_user_can( 'edit_post', $id ) || ! in_array( $post->post_type, zad_hood_types(), true ) ) { return; } // phpcs:ignore
	$p = wp_unslash( $_POST ); // phpcs:ignore
	update_post_meta( $id, '_zad_h_on', empty( $p['zh_on'] ) ? '0' : '1' );
	$hood = absint( $p['zh_hood'] ?? 0 );
	update_post_meta( $id, '_zad_h_hood', ( $hood && ZAD_HOOD_CPT === get_post_type( $hood ) ) ? $hood : 0 );
	$svc = sanitize_text_field( $p['zh_svc'] ?? '' );
	update_post_meta( $id, '_zad_h_svc', in_array( $svc, zad_hood_services(), true ) ? $svc : '' );
	update_post_meta( $id, '_zad_h_local', sanitize_textarea_field( $p['zh_local'] ?? '' ) );
	update_post_meta( $id, '_zad_h_orders', max( 0, absint( $p['zh_orders'] ?? 0 ) ) ?: '' );
	update_post_meta( $id, '_zad_h_eta', max( 0, absint( $p['zh_eta'] ?? 0 ) ) ?: '' );
	update_post_meta( $id, '_zad_h_days', array_values( array_intersect( (array) ( $p['zh_days'] ?? array() ), zad_hood_days() ) ) );
	update_post_meta( $id, '_zad_h_wa', sanitize_text_field( $p['zh_wa'] ?? '' ) );
	update_post_meta( $id, '_zad_h_ba', implode( ',', array_filter( array_map( 'absint', explode( ',', (string) ( $p['zh_ba'] ?? '' ) ) ) ) ) );
	$clean = function ( $rows, $map ) {
		$out = array();
		foreach ( (array) $rows as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$row = array(); $any = false;
			foreach ( $map as $k => $kind ) {
				$v = isset( $r[ $k ] ) ? ( 'n' === $kind ? min( 5, max( 1, absint( $r[ $k ] ) ) ) : ( 'p' === $kind ? sanitize_textarea_field( $r[ $k ] ) : sanitize_text_field( $r[ $k ] ) ) ) : '';
				if ( '' !== $v && 'n' !== $kind ) { $any = true; }
				$row[ $k ] = $v;
			}
			if ( $any ) { $out[] = $row; }
		}
		return $out;
	};
	update_post_meta( $id, '_zad_h_problems', $clean( $p['zh_problems'] ?? array(), array( 't' => 's', 'd' => 'p' ) ) );
	update_post_meta( $id, '_zad_h_reviews', $clean( $p['zh_reviews'] ?? array(), array( 'n' => 's', 'r' => 'n', 't' => 'p' ) ) );
	update_post_meta( $id, '_zad_h_faq', $clean( $p['zh_faq'] ?? array(), array( 'q' => 's', 'a' => 'p' ) ) );
	zad_hood_bump();
	// Duplicate warning (published pages only; never blocks saving).
	if ( 'publish' === $post->post_status && $hood && $svc ) {
		$dup = get_posts( array( 'post_type' => zad_hood_types(), 'post_status' => 'publish', 'numberposts' => 3, 'post__not_in' => array( $id ), 'suppress_filters' => true, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_zad_h_hood', 'value' => $hood ), array( 'key' => '_zad_h_svc', 'value' => $svc ) ) ) );
		if ( $dup ) { set_transient( 'zad_hood_dup_' . get_current_user_id(), array_map( function ( $d ) { return array( get_the_title( $d ), get_edit_post_link( $d, 'raw' ) ); }, $dup ), 120 ); }
	}
}, 20, 2 );

add_action( 'admin_notices', function () {
	$k = 'zad_hood_dup_' . get_current_user_id();
	$d = get_transient( $k );
	if ( ! $d ) { return; }
	delete_transient( $k );
	echo '<div class="notice notice-warning is-dismissible"><p><b>تنبيه تكرار:</b> توجد صفحة منشورة أخرى مربوطة بنفس الحي ونفس الخدمة (تكرار المحتوى يضر بالسيو). لم يُمنع الحفظ.</p><ul>';
	foreach ( $d as $r ) { echo '<li><a href="' . esc_url( $r[1] ) . '">' . esc_html( $r[0] ) . '</a></li>'; }
	echo '</ul></div>';
} );

/* Admin list columns: district + service */
add_action( 'admin_init', function () {
	foreach ( zad_hood_types() as $pt ) {
		add_filter( "manage_{$pt}_posts_columns", function ( $c ) { $c['zad_hood'] = 'الحي / الخدمة'; return $c; } );
		add_action( "manage_{$pt}_posts_custom_column", function ( $col, $id ) {
			if ( 'zad_hood' !== $col ) { return; }
			$h = (int) get_post_meta( $id, '_zad_h_hood', true );
			if ( ! zad_hood_active( $id ) && ! $h ) { echo '—'; return; }
			echo esc_html( ( $h ? get_the_title( $h ) : '؟' ) . ' · ' . ( get_post_meta( $id, '_zad_h_svc', true ) ?: '؟' ) );
		}, 10, 2 );
	}
} );

/* ------------------------------------------------------------------ */
/* Cache version (neighbour lists)                                      */
/* ------------------------------------------------------------------ */
function zad_hood_bump() { update_option( 'zad_hood_ver', time(), false ); }
add_action( 'transition_post_status', function ( $n, $o, $post ) { if ( in_array( $post->post_type, zad_hood_types(), true ) || ZAD_HOOD_CPT === $post->post_type ) { zad_hood_bump(); } }, 10, 3 );

/** Published district pages for the neighbouring districts of $hood, same service. */
function zad_hood_neighbours( $id, $hood, $svc ) {
	if ( ! $hood || ! $svc ) { return array(); }
	$key = 'zad_hnb_' . $id . '_' . (int) get_option( 'zad_hood_ver', 0 );
	$c   = get_transient( $key );
	if ( is_array( $c ) ) { return $c; }
	$nb  = array_map( 'intval', (array) get_post_meta( $hood, '_zad_hd_nbrs', true ) );
	$out = array();
	if ( $nb ) {
		$q = get_posts( array( 'post_type' => zad_hood_types(), 'post_status' => 'publish', 'numberposts' => 40, 'post__not_in' => array( $id ), 'suppress_filters' => true, 'zad_all' => true,
			'meta_query' => array( 'relation' => 'AND', array( 'key' => '_zad_h_hood', 'value' => $nb, 'compare' => 'IN' ), array( 'key' => '_zad_h_svc', 'value' => $svc ) ) ) );
		foreach ( $q as $p ) {
			if ( ! zad_hood_active( $p->ID ) ) { continue; }
			$out[] = array( get_the_title( (int) get_post_meta( $p->ID, '_zad_h_hood', true ) ), get_permalink( $p ) );
		}
	}
	set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/* ------------------------------------------------------------------ */
/* Front end                                                            */
/* ------------------------------------------------------------------ */
function zad_hood_data( $id ) {
	$g    = function ( $k ) use ( $id ) { return get_post_meta( $id, '_zad_h_' . $k, true ); };
	$hid  = (int) $g( 'hood' );
	$hn   = $hid ? get_the_title( $hid ) : '';
	$svc  = (string) $g( 'svc' );
	$city = '';
	if ( $hid ) { $t = get_the_terms( $hid, ZAD_HOOD_TAX ); if ( $t && ! is_wp_error( $t ) ) { $city = $t[0]->name; } }
	return array( 'id' => $id, 'hid' => $hid, 'hood' => $hn, 'svc' => $svc, 'city' => $city, 'g' => $g );
}

function zad_hood_render( $id ) {
	$D    = zad_hood_data( $id );
	$g    = $D['g'];
	$lib  = function ( $k ) use ( $D ) { return $D['hid'] ? get_post_meta( $D['hid'], '_zad_hd_' . $k, true ) : ''; };
	$title = get_the_title( $id );
	$phone = zad_phone( $id );
	$tpl   = trim( (string) $g( 'wa' ) ) ?: 'أحتاج {الخدمة} في حي {الحي}';
	$msg   = str_replace( array( '{الخدمة}', '{الحي}' ), array( $D['svc'] ?: $title, $D['hood'] ), $tpl );
	$wa    = zad_wa_link( $msg, $id );
	$provider = zad_opt( 'zad_provider', get_bloginfo( 'name' ) );
	$sides = zad_hood_sides();
	$chips = array_filter( array(
		$D['hood'] ? ( ( $D['city'] ? $D['city'] . ' — ' : '' ) . 'حي ' . $D['hood'] ) : '',
		$lib( 'side' ) && isset( $sides[ $lib( 'side' ) ] ) ? 'جهة ' . $sides[ $lib( 'side' ) ] : '',
		$lib( 'housing' ) ? implode( '، ', (array) $lib( 'housing' ) ) : '',
	) );
	$local   = trim( (string) $g( 'local' ) );
	$desc    = trim( (string) $lib( 'desc' ) );
	$orders  = (int) $g( 'orders' ); $eta = (int) $g( 'eta' ); $days = (array) $g( 'days' );
	$stats   = array();
	if ( $orders > 0 ) { $stats[] = array( number_format_i18n( $orders ), 'طلب منفّذ في الحي' ); }
	if ( $eta > 0 ) { $stats[] = array( number_format_i18n( $eta ) . ' د', 'متوسط وقت الوصول' ); }
	if ( $days ) { $stats[] = array( count( $days ) >= 7 ? 'كل الأيام' : implode( '، ', $days ), 'أيام تواجد الفريق' ); }
	$problems = array_values( array_filter( (array) $g( 'problems' ), function ( $r ) { return ! empty( $r['t'] ); } ) );
	$ba       = array_values( array_filter( array_map( 'intval', explode( ',', (string) $g( 'ba' ) ) ) ) );
	$reviews  = array_values( array_filter( (array) $g( 'reviews' ), function ( $r ) { return ! empty( $r['t'] ); } ) );
	$faq      = array_values( array_filter( (array) $g( 'faq' ), function ( $r ) { return ! empty( $r['q'] ) && ! empty( $r['a'] ); } ) );
	$nbs      = zad_hood_neighbours( $id, $D['hid'], $D['svc'] );
	$parent   = wp_get_post_parent_id( $id );
	$env      = trim( (string) $lib( 'env' ) );
	?>
<main id="main" class="hd">
	<section class="shero hd-hero"><div class="wrap">
		<?php zad_render_crumbs( zad_current_crumbs() ); ?>
		<div class="hd-hero__in">
			<?php if ( $provider ) : ?><span class="pill"><?php echo zad_icon( 'badge', 18 ); // phpcs:ignore ?> <?php echo esc_html( $provider ); ?></span><?php endif; ?>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $chips ) : ?><ul class="hd-chips"><?php foreach ( $chips as $c ) { echo '<li>' . esc_html( $c ) . '</li>'; } ?></ul><?php endif; ?>
			<div class="hero__btns">
				<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب عبر واتساب</a><?php endif; ?>
				<?php if ( $phone ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل بنا</a><?php endif; ?>
			</div>
		</div>
	</div></section>

	<?php if ( $local || $desc ) : ?>
	<section class="sec"><div class="wrap wrap--narrow"><div class="hd-local"><?php echo zad_icon( 'pin', 24 ); // phpcs:ignore ?>
		<div><?php if ( $local ) { echo '<p>' . nl2br( esc_html( $local ) ) . '</p>'; } if ( $desc ) { echo '<p class="hd-desc">' . nl2br( esc_html( $desc ) ) . '</p>'; } ?></div></div></div></section>
	<?php endif; ?>

	<?php if ( trim( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) !== '' ) : ?>
	<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo apply_filters( 'the_content', get_post_field( 'post_content', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
	<?php endif; ?>

	<?php if ( $stats ) : ?>
	<section class="stats stats--svc"><div class="wrap stats__grid"><?php foreach ( $stats as $s ) { echo '<div class="stat"><b>' . esc_html( $s[0] ) . '</b><span>' . esc_html( $s[1] ) . '</span></div>'; } ?></div></section>
	<?php endif; ?>

	<?php if ( $problems ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">من واقع الحي</span><h2>المشاكل الشائعة<?php echo $D['hood'] ? ' في حي ' . esc_html( $D['hood'] ) : ''; ?></h2></header>
		<div class="hd-grid"><?php foreach ( $problems as $p ) { echo '<article class="hd-card"><h3>' . esc_html( $p['t'] ) . '</h3>' . ( ! empty( $p['d'] ) ? '<p>' . esc_html( $p['d'] ) . '</p>' : '' ) . '</article>'; } ?></div>
		<?php if ( $env ) { echo '<p class="hd-env">' . zad_icon( 'pin', 16 ) . ' البيئة المحيطة: ' . esc_html( $env ) . '</p>'; } // phpcs:ignore ?>
	</div></section>
	<?php endif; ?>

	<?php if ( count( $ba ) >= 2 ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">أعمال حقيقية</span><h2>من شغلنا<?php echo $D['hood'] ? ' في حي ' . esc_html( $D['hood'] ) : ''; ?></h2></header>
		<div class="hd-ba"><?php for ( $i = 0; $i + 1 < count( $ba ); $i += 2 ) { echo '<figure><span>قبل</span>' . wp_get_attachment_image( $ba[ $i ], 'large', false, array( 'loading' => 'lazy' ) ) . '</figure><figure><span>بعد</span>' . wp_get_attachment_image( $ba[ $i + 1 ], 'large', false, array( 'loading' => 'lazy' ) ) . '</figure>'; } // phpcs:ignore ?></div>
	</div></section>
	<?php endif; ?>

	<?php if ( $reviews ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">آراء العملاء</span><h2>ماذا قال عملاؤنا<?php echo $D['hood'] ? ' في ' . esc_html( $D['hood'] ) : ''; ?></h2></header>
		<div class="hd-grid"><?php foreach ( $reviews as $r ) { echo '<blockquote class="hd-card"><div>' . ( ! empty( $r['r'] ) ? zad_stars( (int) $r['r'] ) : '' ) . '</div><p>' . esc_html( $r['t'] ) . '</p>' . ( ! empty( $r['n'] ) ? '<cite>— ' . esc_html( $r['n'] ) . '</cite>' : '' ) . '</blockquote>'; } // phpcs:ignore ?></div>
	</div></section>
	<?php endif; ?>

	<?php if ( $faq ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">أسئلة الحي</span><h2>أسئلة شائعة</h2></header>
		<?php zad_render_faq( $faq ); ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $nbs ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">التغطية</span><h2>أحياء مجاورة نخدمها</h2></header>
		<ul class="hd-nb"><?php foreach ( $nbs as $n ) { echo '<li><a href="' . esc_url( $n[1] ) . '">' . esc_html( $n[0] ) . '</a></li>'; } ?></ul>
	</div></section>
	<?php endif; ?>

	<?php echo function_exists( 'zad_bridges_html' ) ? zad_bridges_html( $id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<section class="sec sec--dark" id="quote"><div class="wrap qfinal">
		<div>
			<span class="eyebrow">اطلب الآن</span>
			<h2><?php echo esc_html( $D['svc'] && $D['hood'] ? 'احجز ' . $D['svc'] . ' في حي ' . $D['hood'] : 'احصل على عرض سعر اليوم' ); ?></h2>
			<p><?php echo esc_html( $provider ); ?> — جاهزون لخدمتك.</p>
			<div class="hero__btns">
				<?php if ( $phone ) : ?><a class="btn btn--accent" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" dir="ltr"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> <?php echo esc_html( $phone ); ?></a><?php endif; ?>
				<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
			</div>
			<?php if ( $parent ) : ?><p class="hd-up"><a href="<?php echo esc_url( get_permalink( $parent ) ); ?>">← العودة إلى <?php echo esc_html( get_the_title( $parent ) ); ?></a></p><?php endif; ?>
		</div>
		<?php echo zad_quote_form( array( 'service_id' => $id, 'id' => 'fq', 'title' => 'اترك بياناتك ونتصل بك', 'sub' => 'رد خلال دقائق', 'area' => $D['hood'] ) ); // phpcs:ignore ?>
	</div></section>
</main>
	<?php
}

/* ------------------------------------------------------------------ */
/* Schema: district-aware Service (+ FAQPage from the district FAQ)     */
/* ------------------------------------------------------------------ */
function zad_hood_area_node( $D ) {
	if ( ! $D['hid'] || '' === $D['hood'] ) { return null; }
	$a = array( '@type' => 'AdministrativeArea', 'name' => 'حي ' . $D['hood'] );
	if ( $D['city'] ) { $a['containedInPlace'] = array( '@type' => 'City', 'name' => $D['city'] ); }
	$lat = get_post_meta( $D['hid'], '_zad_hd_lat', true ); $lng = get_post_meta( $D['hid'], '_zad_hd_lng', true );
	if ( '' !== $lat && '' !== $lng ) { $a['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng ); }
	return $a;
}

// Service types: adjust the Service the theme already prints (no duplicate node).
add_filter( 'zsc_service_nodes', function ( $nodes, $post ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	if ( ! zad_hood_active( $id ) ) { return $nodes; }
	$D = zad_hood_data( $id ); $area = zad_hood_area_node( $D );
	foreach ( $nodes as $k => $n ) {
		if ( isset( $n['@type'] ) && 'Service' === $n['@type'] ) {
			$nodes[ $k ]['name'] = wp_strip_all_tags( get_the_title( $id ) );
			if ( $area ) { $nodes[ $k ]['areaServed'] = $area; }
			// The district layout shows no price table, so no price markup either.
			unset( $nodes[ $k ]['offers'], $nodes[ $k ]['hasOfferCatalog'], $nodes[ $k ]['subjectOf'] );
		}
	}
	return $nodes;
}, 10, 2 );

add_action( 'wp_head', function () {
	if ( ! is_singular( zad_hood_types() ) || 'theme' !== zad_schema_owner() ) { return; }
	$id = get_queried_object_id();
	if ( ! zad_hood_active( $id ) ) { return; }
	$D = zad_hood_data( $id ); $url = get_permalink( $id ); $home = trailingslashit( home_url() );
	if ( 'page' === get_post_type( $id ) ) {
		$s = array( '@type' => 'Service', '@id' => $url . '#service', 'name' => wp_strip_all_tags( get_the_title( $id ) ), 'url' => $url, 'provider' => array( '@id' => $home . '#localbusiness' ), 'mainEntityOfPage' => array( '@id' => $url . '#webpage' ) );
		if ( $D['svc'] ) { $s['serviceType'] = $D['svc']; }
		$area = zad_hood_area_node( $D );
		if ( $area ) { $s['areaServed'] = $area; }
		zad_print_graph( array( $s ) );
	}
	$faq = array_values( array_filter( (array) get_post_meta( $id, '_zad_h_faq', true ), function ( $r ) { return ! empty( $r['q'] ) && ! empty( $r['a'] ); } ) );
	if ( $faq ) {
		$ents = array();
		foreach ( $faq as $f ) { $ents[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) ); }
		zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ents ) );
	}
}, 12 );

/* Page template registration for Pages ("Template Name" lives in temp/zad-hood.php). */
