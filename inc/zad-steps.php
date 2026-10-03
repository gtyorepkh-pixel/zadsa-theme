<?php
/**
 * "طريقة تنفيذ الخدمة": several designs, chosen per service page.
 *   cards1  = current cards (default, unchanged)     cards2 = second card design (no images)
 *   story   = alternating rows with images (theme colours); image beside the text or behind it.
 * Only the chosen design is rendered. No JS: layout is CSS.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

function zad_steps_styles() { return array( 'cards1' => 'بطاقات (1) — الشكل الحالي', 'cards2' => 'بطاقات (2) — تصميم آخر بدون صور', 'story' => 'قصة الخطوات — بصور، تتبادل يميناً ويساراً' ); }

/* ---------- editor ---------- */
function zad_steps_row( $i, $r ) {
	$img = (int) ( $r['img'] ?? 0 );
	$n   = 'zad[story][' . $i . ']';
	$o   = '<div class="zst-row"><div class="zst-r1"><input type="text" name="' . $n . '[label]" value="' . esc_attr( $r['label'] ?? '' ) . '" placeholder="الوسم الصغير (مثل: قبل النزول)"><input type="text" name="' . $n . '[title]" value="' . esc_attr( $r['title'] ?? '' ) . '" placeholder="عنوان الخطوة"><button type="button" class="button zst-del">×</button></div>';
	$o  .= '<textarea name="' . $n . '[text]" rows="3" placeholder="شرح الخطوة">' . esc_textarea( $r['text'] ?? '' ) . '</textarea>';
	$o  .= '<textarea name="' . $n . '[bullets]" rows="3" placeholder="حتى 3 بنود، بند في كل سطر">' . esc_textarea( $r['bullets'] ?? '' ) . '</textarea>';
	$o  .= '<div class="zst-img"><input type="hidden" name="' . $n . '[img]" value="' . $img . '" class="zst-imgv"><button type="button" class="button zst-pick">اختيار صورة</button> <span class="zst-thumb">' . ( $img ? wp_get_attachment_image( $img, array( 60, 60 ) ) : '' ) . '</span> <button type="button" class="button-link zst-noimg">إزالة الصورة</button></div></div>';
	return $o;
}

function zad_steps_box( $post_id ) {
	$style = get_post_meta( $post_id, '_zad_steps_style', true ) ?: 'cards1';
	$pos   = get_post_meta( $post_id, '_zad_story_pos', true ) ?: 'side';
	$rows  = array_values( array_filter( (array) get_post_meta( $post_id, '_zad_story', true ), 'is_array' ) );
	$sh    = (array) get_post_meta( $post_id, '_zad_story_head', true );
	echo '<style>.zst-row{padding:10px;margin:8px 0;border:1px solid #cfe0e8;border-radius:8px;background:#f6fafc;display:grid;gap:6px}.zst-r1{display:flex;gap:6px}.zst-r1 input{flex:1}.zst-row textarea{width:100%}.zst-thumb img{border-radius:6px;vertical-align:middle}</style>';
	echo '<h4>شكل قسم «طريقة التنفيذ»</h4><p><select name="zad[steps_style]" id="zst-style">';
	foreach ( zad_steps_styles() as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $style, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
	echo '</select> <span class="description">الشكلان (1) و(2) يقرآن «خطوات التنفيذ» أعلاه. «قصة الخطوات» تقرأ الحقول أدناه.</span></p>';
	echo '<div id="zst-story"' . ( 'story' === $style ? '' : ' style="display:none"' ) . '>';
	echo '<p>موضع الصورة: <select name="zad[story_pos]"><option value="side"' . selected( $pos, 'side', false ) . '>بجانب النص</option><option value="behind"' . selected( $pos, 'behind', false ) . '>خلف النص (النص فوق الصورة)</option></select></p>';
	echo '<p><input type="text" name="zad[story_title]" value="' . esc_attr( $sh['title'] ?? '' ) . '" placeholder="عنوان القسم (فارغ = كيف تسير عملية التنفيذ)" style="width:100%"></p>';
	echo '<p><textarea name="zad[story_intro]" rows="2" style="width:100%" placeholder="فقرة تمهيد (اختيارية)">' . esc_textarea( $sh['intro'] ?? '' ) . '</textarea></p>';
	echo '<div class="zst-list">';
	foreach ( $rows as $i => $r ) { echo zad_steps_row( $i, $r ); } // phpcs:ignore
	echo '</div><template id="zst-tpl">' . zad_steps_row( '%IDX%', array() ) . '</template><p><button type="button" class="button" id="zst-add">+ إضافة خطوة</button></p></div>'; // phpcs:ignore
	?>
	<script>
	(function(){
		var st=document.getElementById('zst-style'),box=document.getElementById('zst-story');if(!st||!box)return;
		st.addEventListener('change',function(){box.style.display=st.value==='story'?'':'none';});
		var list=box.querySelector('.zst-list'),tpl=document.getElementById('zst-tpl'),fr;
		document.getElementById('zst-add').addEventListener('click',function(){var d=document.createElement('div');d.innerHTML=tpl.innerHTML.replace(/%IDX%/g,'n'+Date.now());list.appendChild(d.firstElementChild);});
		box.addEventListener('click',function(e){
			var t=e.target,row=t.closest('.zst-row');
			if(t.classList.contains('zst-del')){row.remove();}
			if(t.classList.contains('zst-noimg')){row.querySelector('.zst-imgv').value=0;row.querySelector('.zst-thumb').innerHTML='';}
			if(t.classList.contains('zst-pick')&&window.wp&&wp.media){
				var f=wp.media({title:'صورة الخطوة',multiple:false,library:{type:'image'}});
				f.on('select',function(){var a=f.state().get('selection').first().toJSON();row.querySelector('.zst-imgv').value=a.id;var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;row.querySelector('.zst-thumb').innerHTML='<img src="'+u+'" width="60" height="60">';});
				f.open();
			}
		});
	})();
	</script>
	<?php
}

function zad_steps_save( $post_id, $in ) {
	if ( ! isset( $in['steps_style'] ) ) { return; }
	$st = $in['steps_style'];
	update_post_meta( $post_id, '_zad_steps_style', isset( zad_steps_styles()[ $st ] ) ? $st : 'cards1' );
	update_post_meta( $post_id, '_zad_story_pos', 'behind' === ( $in['story_pos'] ?? '' ) ? 'behind' : 'side' );
	update_post_meta( $post_id, '_zad_story_head', array( 'title' => sanitize_text_field( $in['story_title'] ?? '' ), 'intro' => sanitize_textarea_field( $in['story_intro'] ?? '' ) ) );
	$rows = array();
	foreach ( (array) ( $in['story'] ?? array() ) as $r ) {
		if ( ! is_array( $r ) ) { continue; }
		$title = sanitize_text_field( $r['title'] ?? '' );
		if ( '' === $title ) { continue; }
		$b = array_slice( array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( $r['bullets'] ?? '' ) ) ) ) ), 0, 3 );
		$rows[] = array( 'label' => sanitize_text_field( $r['label'] ?? '' ), 'title' => $title, 'text' => sanitize_textarea_field( $r['text'] ?? '' ), 'bullets' => implode( "\n", $b ), 'img' => absint( $r['img'] ?? 0 ) );
	}
	update_post_meta( $post_id, '_zad_story', $rows );
}

/* ---------- front end ---------- */
function zad_steps_cards1( $steps ) {
	$o = '<section class="sec"><div class="wrap"><header class="sec__head"><span class="eyebrow">خطوة بخطوة</span><h2>كيف تسير عملية التنفيذ</h2></header><ol class="hsteps">';
	foreach ( $steps as $i => $s ) {
		$sp = array_map( 'trim', explode( '||', (string) $s['d'] ) ); $tags = isset( $sp[1] ) ? array_filter( array_map( 'trim', preg_split( '/[,،]/u', $sp[1] ) ) ) : array();
		$o .= '<li><span class="hsteps__n">' . esc_html( $i + 1 ) . '</span><h3>' . esc_html( $s['t'] ) . '</h3><p>' . esc_html( $sp[0] ) . '</p>';
		if ( $tags ) { $o .= '<div class="hsteps__tags">'; foreach ( $tags as $tg ) { $o .= '<span class="chip">' . esc_html( $tg ) . '</span>'; } $o .= '</div>'; }
		$o .= '</li>';
	}
	return $o . '</ol></div></section>';
}

function zad_steps_cards2( $steps ) {
	$o = '<section class="sec sec--tint"><div class="wrap"><header class="sec__head"><span class="eyebrow">خطوة بخطوة</span><h2>كيف تسير عملية التنفيذ</h2></header><ol class="ssteps">';
	foreach ( $steps as $i => $s ) {
		$sp = array_map( 'trim', explode( '||', (string) $s['d'] ) ); $tags = isset( $sp[1] ) ? array_filter( array_map( 'trim', preg_split( '/[,،]/u', $sp[1] ) ) ) : array();
		$o .= '<li><b class="ssteps__n">' . esc_html( sprintf( '%02d', $i + 1 ) ) . '</b><h3>' . esc_html( $s['t'] ) . '</h3><p>' . esc_html( $sp[0] ) . '</p>';
		if ( $tags ) { $o .= '<div class="hsteps__tags">'; foreach ( $tags as $tg ) { $o .= '<span class="chip">' . esc_html( $tg ) . '</span>'; } $o .= '</div>'; }
		$o .= '</li>';
	}
	return $o . '</ol></div></section>';
}

function zad_steps_story( $id, $rows ) {
	$pos = get_post_meta( $id, '_zad_story_pos', true ) === 'behind' ? 'behind' : 'side';
	$sh  = (array) get_post_meta( $id, '_zad_story_head', true );
	$o   = '<section class="sec story story--' . $pos . '"><div class="wrap"><header class="sec__head"><span class="eyebrow">خطوة بخطوة</span><h2>' . esc_html( $sh['title'] ?? '' ?: 'كيف تسير عملية التنفيذ' ) . '</h2>';
	if ( ! empty( $sh['intro'] ) ) { $o .= '<p>' . esc_html( $sh['intro'] ) . '</p>'; }
	$o  .= '</header>';
	foreach ( $rows as $i => $r ) {
		$num   = sprintf( '%02d', $i + 1 );
		$label = ( ! empty( $r['label'] ) ? $r['label'] . ' · ' : '' ) . $num;
		$b     = array_filter( array_map( 'trim', explode( "\n", (string) ( $r['bullets'] ?? '' ) ) ) );
		$img   = (int) ( $r['img'] ?? 0 );
		$bl = '';
		if ( $b ) { $bl = '<ul class="story__list">'; foreach ( $b as $x ) { $bl .= '<li>' . zad_icon( 'check', 18 ) . esc_html( $x ) . '</li>'; } $bl .= '</ul>'; }
		$media = $img ? wp_get_attachment_image( $img, 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr( $r['title'] ) ) ) : '';
		$o .= '<article class="story__row">';
		if ( 'behind' === $pos ) {
			$o .= '<div class="story__card' . ( $img ? '' : ' is-plain' ) . '">' . $media . '<div class="story__ov"><span class="story__tag">' . esc_html( $label ) . '</span><h3>' . esc_html( $r['title'] ) . '</h3>' . ( $r['text'] ? '<p>' . esc_html( $r['text'] ) . '</p>' : '' ) . $bl . '</div></div>';
		} else {
			$o .= '<div class="story__txt"><span class="story__tag">' . esc_html( $label ) . '</span><h3>' . esc_html( $r['title'] ) . '</h3>' . ( $r['text'] ? '<p>' . esc_html( $r['text'] ) . '</p>' : '' ) . $bl . '</div>';
			$o .= '<div class="story__media' . ( $img ? '' : ' is-plain' ) . '">' . $media . '<span class="story__num" aria-hidden="true">' . esc_html( $num ) . '</span></div>';
		}
		$o .= '</article>';
	}
	return $o . '</div></section>';
}

/** Section HTML for a service page ($steps = the classic "steps" rows). */
function zad_steps_html( $id, $steps ) {
	$style = get_post_meta( $id, '_zad_steps_style', true ) ?: 'cards1';
	if ( 'story' === $style ) {
		$rows = array_values( array_filter( (array) get_post_meta( $id, '_zad_story', true ), function ( $r ) { return is_array( $r ) && ! empty( $r['title'] ); } ) );
		if ( $rows ) { return zad_steps_story( $id, $rows ); }
	}
	if ( ! $steps ) { return ''; }
	return 'cards2' === $style ? zad_steps_cards2( $steps ) : zad_steps_cards1( $steps );
}
