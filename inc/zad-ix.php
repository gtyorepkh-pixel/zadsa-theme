<?php defined( 'ABSPATH' ) || exit;
/**
 * Interactive service layout ("المحتوى التفاعلي").
 *
 * The normal WordPress editor stays the main content area:
 *  - write the intro paragraph first, add a "Read more" tag, and everything after it is shown
 *    BELOW the interactive modules (or leave out the tag and all content is shown above them);
 *  - any module can also be placed inside the content with [zad_ix m="wiz"].
 * Modules: map (explore), wiz (self-check → plan → booking), report, life, safe, check.
 */

require_once __DIR__ . '/zad-ix-packs.php';

function zad_ix_modules() {
	return array(
		'map'    => 'خريطة تفاعلية (أين يختبئ / يدخل)',
		'wiz'    => 'فحص ذاتي ← خطة ← حجز',
		'report' => 'نموذج تقرير الزيارة',
		'life'   => 'دورة الحياة ولماذا المتابعة',
		'safe'   => 'حاسبة الأمان للأسرة',
		'check'  => 'قائمة التحضير',
	);
}

function zad_ix_get( $post_id ) {
	$ix = get_post_meta( $post_id, '_zad_ix', true );
	return is_array( $ix ) ? $ix : array();
}

function zad_ix_order( $ix ) {
	$all   = array_keys( zad_ix_modules() );
	$order = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $ix['order'] ?? '' ) ) ), function ( $k ) use ( $all ) { return in_array( $k, $all, true ); } ) );
	return array_values( array_unique( array_merge( $order, $all ) ) );
}

function zad_ix_enabled( $post_id ) {
	$ix = zad_ix_get( $post_id );
	return ! empty( $ix['on'] );
}

/* ---------- admin ---------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zad_ix_box', 'المحتوى التفاعلي (اختياري)', 'zad_ix_metabox', zad_service_types(), 'normal', 'default' );
} );

function zad_ix_fields() {
	return array(
		'map'    => array( 'title' => 'text', 'lead' => 'text', 'img' => 'int', 'items' => 'area' ),
		'wiz'    => array( 'title' => 'text', 'lead' => 'text', 'questions' => 'area', 'tiers' => 'area' ),
		'report' => array( 'title' => 'text', 'lead' => 'text', 'items' => 'area' ),
		'life'   => array( 'title' => 'text', 'lead' => 'text', 'items' => 'area', 'rule' => 'text' ),
		'safe'   => array( 'title' => 'text', 'lead' => 'text', 'items' => 'area', 'after' => 'text' ),
		'check'  => array( 'title' => 'text', 'lead' => 'text', 'items' => 'area' ),
	);
}

function zad_ix_metabox( $post ) {
	wp_nonce_field( 'zad_ix_save', 'zad_ix_nonce' );
	$ix    = zad_ix_get( $post->ID );
	$mods  = zad_ix_modules();
	$order = zad_ix_order( $ix );
	$hints = array(
		'map'   => 'سطر لكل نقطة:  الاسم | لماذا | ماذا تفعل | x% | y%   (x و y اختياريان لوضع النقطة على الصورة)',
		'wiz'   => 'الأسئلة: سطر لكل سؤال:  السؤال || single أو multi || خيار::وزن ; خيار::وزن',
		'report' => 'سطر لكل بند:  العنوان | الوصف',
		'life'  => 'سطر لكل مرحلة:  المرحلة | الشرح | المدة',
		'safe'  => 'سطر لكل حالة:  الحالة | الاحتياط',
		'check' => 'سطر لكل بند',
	);
	$packs = array();
	foreach ( zad_ix_packs() as $k => $p ) {
		$packs[ $k ] = $p;
	}
	echo '<div class="zad-ix-admin" data-packs="' . esc_attr( wp_json_encode( $packs ) ) . '">';
	echo '<p><label><input type="checkbox" name="zad_ix[on]" value="1"' . checked( ! empty( $ix['on'] ), true, false ) . '> <strong>تفعيل المحتوى التفاعلي في هذه الصفحة</strong></label></p>';
	echo '<p class="description">محرر ووردبريس يبقى هو المحتوى الأساسي: اكتب المقدمة ثم أضف وسم «Read more» (المزيد)؛ ما قبله يظهر فوق الأدوات التفاعلية، وما بعده يظهر تحتها. يمكنك أيضاً وضع أي أداة داخل المحتوى بالشورتكود <code>[zad_ix m="wiz"]</code> (القيم: map, wiz, report, life, safe, check).</p>';
	echo '<p><select data-ix-pack><option value="">— تحميل نموذج جاهز —</option>';
	foreach ( $packs as $k => $p ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $p['label'] ) . '</option>';
	}
	echo '</select> <button type="button" class="button" data-ix-load>تحميل (يستبدل الحقول أدناه)</button> <span class="description">النصوص إرشادية عامة، راجعها قبل النشر.</span></p>';
	echo '<input type="hidden" name="zad_ix[order]" value="' . esc_attr( implode( ',', $order ) ) . '" data-ix-order>';
	echo '<div data-ix-list>';
	foreach ( $order as $k ) {
		$d  = $ix[ $k ] ?? array();
		$en = ! empty( $ix['en'][ $k ] );
		echo '<details class="zad-ix-mod" data-k="' . esc_attr( $k ) . '" style="border:1px solid #dcdcde;border-radius:6px;padding:8px 12px;margin:8px 0;background:#fff"' . ( $en ? ' open' : '' ) . '>';
		echo '<summary style="cursor:pointer;font-weight:600"><label onclick="event.stopPropagation()"><input type="checkbox" name="zad_ix[en][' . esc_attr( $k ) . ']" value="1"' . checked( $en, true, false ) . '> ' . esc_html( $mods[ $k ] ) . '</label> <button type="button" class="button button-small" data-ix-up>↑</button> <button type="button" class="button button-small" data-ix-down>↓</button></summary>';
		echo '<p class="description">' . esc_html( $hints[ $k ] ) . '</p>';
		foreach ( zad_ix_fields()[ $k ] as $f => $type ) {
			$name = 'zad_ix[' . $k . '][' . $f . ']';
			$lab  = array( 'title' => 'العنوان', 'lead' => 'مقدمة قصيرة', 'img' => 'صورة (رقم المرفق، اختياري)', 'items' => 'العناصر', 'questions' => 'الأسئلة', 'tiers' => 'النتائج: الحد الأدنى للنقاط | العنوان | الوصف | خطوات الخطة مفصولة بـ ;', 'rule' => 'القاعدة (جملة مميزة)', 'after' => 'ملاحظة عامة' )[ $f ] ?? $f;
			echo '<p><label><strong>' . esc_html( $lab ) . '</strong><br>';
			if ( 'area' === $type ) {
				echo '<textarea class="widefat" rows="6" name="' . esc_attr( $name ) . '" data-f="' . esc_attr( $k . '.' . $f ) . '">' . esc_textarea( $d[ $f ] ?? '' ) . '</textarea>';
			} else {
				echo '<input class="widefat" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $d[ $f ] ?? '' ) . '" data-f="' . esc_attr( $k . '.' . $f ) . '">';
			}
			echo '</label></p>';
		}
		echo '</details>';
	}
	echo '</div></div>';
	?>
	<script>
	(function(){
		var box=document.querySelector('.zad-ix-admin'); if(!box) return;
		var list=box.querySelector('[data-ix-list]'), ord=box.querySelector('[data-ix-order]');
		function sync(){ ord.value=[].map.call(list.children,function(d){return d.dataset.k;}).join(','); }
		list.addEventListener('click',function(e){
			var d=e.target.closest('.zad-ix-mod'); if(!d) return;
			if(e.target.matches('[data-ix-up]')&&d.previousElementSibling){ list.insertBefore(d,d.previousElementSibling); sync(); e.preventDefault(); }
			if(e.target.matches('[data-ix-down]')&&d.nextElementSibling){ list.insertBefore(d.nextElementSibling,d); sync(); e.preventDefault(); }
		});
		box.querySelector('[data-ix-load]').addEventListener('click',function(){
			var k=box.querySelector('[data-ix-pack]').value; if(!k) return;
			if(!confirm('سيتم استبدال محتوى الحقول بالنموذج الجاهز. متابعة؟')) return;
			var p=JSON.parse(box.dataset.packs)[k];
			['map','wiz','report','life','safe','check'].forEach(function(m){
				Object.keys(p[m]||{}).forEach(function(f){
					var el=box.querySelector('[data-f="'+m+'.'+f+'"]'); if(el) el.value=p[m][f];
				});
				var cb=box.querySelector('[name="zad_ix[en]['+m+']"]'); if(cb) cb.checked=true;
			});
			var on=box.querySelector('[name="zad_ix[on]"]'); if(on) on.checked=true;
		});
	})();
	</script>
	<?php
}

add_action( 'save_post', function ( $id ) {
	if ( ! isset( $_POST['zad_ix_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zad_ix_nonce'] ) ), 'zad_ix_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$in  = isset( $_POST['zad_ix'] ) && is_array( $_POST['zad_ix'] ) ? wp_unslash( $_POST['zad_ix'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$out = array( 'on' => ! empty( $in['on'] ) ? 1 : 0, 'order' => implode( ',', zad_ix_order( array( 'order' => sanitize_text_field( $in['order'] ?? '' ) ) ) ), 'en' => array() );
	foreach ( zad_ix_fields() as $k => $fields ) {
		$out['en'][ $k ] = ! empty( $in['en'][ $k ] ) ? 1 : 0;
		foreach ( $fields as $f => $type ) {
			$v = $in[ $k ][ $f ] ?? '';
			$out[ $k ][ $f ] = 'int' === $type ? absint( $v ) : ( 'area' === $type ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) );
		}
	}
	update_post_meta( $id, '_zad_ix', $out );
} );

/* ---------- parsing ---------- */

function zad_ix_pipe( $text, $n ) {
	$rows = array();
	foreach ( zad_lines( $text ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		$c = array_pad( $c, $n, '' );
		$rows[] = $c;
	}
	return $rows;
}

/* ---------- rendering ---------- */

function zad_ix_head( $d, $eyebrow ) {
	$o = '<header class="sec__head">';
	if ( $eyebrow ) { $o .= '<span class="eyebrow">' . esc_html( $eyebrow ) . '</span>'; }
	if ( ! empty( $d['title'] ) ) { $o .= '<h2>' . esc_html( $d['title'] ) . '</h2>'; }
	if ( ! empty( $d['lead'] ) ) { $o .= '<p>' . esc_html( $d['lead'] ) . '</p>'; }
	return $o . '</header>';
}

function zad_ix_module_html( $k, $ix, $post_id ) {
	$d = $ix[ $k ] ?? array();
	if ( empty( $d ) ) {
		return '';
	}
	$title = get_the_title( $post_id );
	$o     = '';
	switch ( $k ) {
		case 'map':
			$rows = zad_ix_pipe( $d['items'] ?? '', 5 );
			if ( ! $rows ) { return ''; }
			$img = ! empty( $d['img'] ) ? wp_get_attachment_image( $d['img'], 'large', false, array( 'loading' => 'lazy', 'class' => 'ixm__img' ) ) : '';
			$o  .= zad_ix_head( $d, 'تعرّف على المشكلة' ) . '<div class="ixm" data-ixm>';
			if ( $img ) {
				$o .= '<div class="ixm__stage">' . $img;
				foreach ( $rows as $i => $r ) {
					if ( '' !== $r[3] && '' !== $r[4] ) {
						$o .= '<button type="button" class="ixm__pin" data-i="' . (int) $i . '" style="inset-inline-start:' . (float) $r[3] . '%;top:' . (float) $r[4] . '%" aria-label="' . esc_attr( $r[0] ) . '">' . ( $i + 1 ) . '</button>';
					}
				}
				$o .= '</div>';
			}
			$o .= '<div class="ixm__body"><ul class="ixm__tabs" role="tablist">';
			foreach ( $rows as $i => $r ) {
				$o .= '<li><button type="button" role="tab" class="ixm__tab' . ( 0 === $i ? ' is-on' : '' ) . '" data-i="' . (int) $i . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '"><b>' . ( $i + 1 ) . '</b><span>' . esc_html( $r[0] ) . '</span></button></li>';
			}
			$o .= '</ul><div class="ixm__panels">';
			foreach ( $rows as $i => $r ) {
				$o .= '<article class="ixm__panel" data-i="' . (int) $i . '"' . ( 0 === $i ? '' : ' hidden' ) . '><h3>' . esc_html( $r[0] ) . '</h3>';
				if ( $r[1] ) { $o .= '<p><strong>لماذا؟</strong> ' . esc_html( $r[1] ) . '</p>'; }
				if ( $r[2] ) { $o .= '<p class="ixm__do"><strong>ماذا تفعل؟</strong> ' . esc_html( $r[2] ) . '</p>'; }
				$o .= '</article>';
			}
			$o .= '</div></div></div>';
			break;

		case 'wiz':
			$qs = array();
			foreach ( zad_lines( $d['questions'] ?? '' ) as $l ) {
				$c = array_map( 'trim', explode( '||', $l ) );
				if ( count( $c ) < 3 ) { continue; }
				$opts = array();
				foreach ( preg_split( '/[;؛]/u', $c[2] ) as $op ) {
					$p = array_map( 'trim', explode( '::', $op ) );
					if ( '' !== $p[0] ) { $opts[] = array( $p[0], (int) ( $p[1] ?? 0 ) ); }
				}
				if ( $opts ) { $qs[] = array( $c[0], 'multi' === $c[1] ? 'multi' : 'single', $opts ); }
			}
			if ( ! $qs ) { return ''; }
			$tiers = array();
			foreach ( zad_lines( $d['tiers'] ?? '' ) as $l ) {
				$c = array_pad( array_map( 'trim', explode( '|', $l ) ), 4, '' );
				$tiers[] = array( 'min' => (int) $c[0], 'title' => $c[1], 'text' => $c[2], 'plan' => array_values( array_filter( array_map( 'trim', preg_split( '/[;؛]/u', $c[3] ) ) ) ) );
			}
			usort( $tiers, function ( $a, $b ) { return $a['min'] <=> $b['min']; } );
			$wa = zad_whatsapp( $post_id );
			$o .= zad_ix_head( $d, 'افحص بنفسك' );
			$o .= '<div class="ixw" data-ixw data-svc="' . esc_attr( $title ) . '" data-wa="' . esc_attr( $wa ) . '" data-tiers="' . esc_attr( wp_json_encode( $tiers ) ) . '"><form class="ixw__form" novalidate>';
			foreach ( $qs as $qi => $q ) {
				$o .= '<fieldset class="ixw__q" data-qi="' . (int) $qi . '" data-type="' . esc_attr( $q[1] ) . '"><legend><span class="ixw__n">' . ( $qi + 1 ) . '</span>' . esc_html( $q[0] ) . ( 'multi' === $q[1] ? ' <small>(يمكن اختيار أكثر من إجابة)</small>' : '' ) . '</legend><div class="ixw__opts">';
				foreach ( $q[2] as $oi => $op ) {
					$o .= '<label class="ixw__opt"><input type="' . ( 'multi' === $q[1] ? 'checkbox' : 'radio' ) . '" name="q' . (int) $qi . '" value="' . (int) $oi . '" data-w="' . (int) $op[1] . '"><span>' . esc_html( $op[0] ) . '</span></label>';
				}
				$o .= '</div></fieldset>';
			}
			$o .= '<div class="ixw__nav"><button type="button" class="btn btn--ghost" data-ixw-prev hidden>السابق</button><button type="button" class="btn btn--accent" data-ixw-next>التالي</button></div></form>';
			$o .= '<div class="ixw__res ix-printable" data-ixw-res hidden aria-live="polite"><span class="ixw__badge" data-res-title></span><p data-res-text></p><h3>الخطة المبدئية</h3><ol data-res-plan></ol><h3>إجاباتك</h3><ul class="ixw__ans" data-res-ans></ul><p class="ixw__disc">خطة مبدئية حسب إجاباتك وليست عرض سعر. يحدد الفني الخطة النهائية والسعر بعد المعاينة.</p>';
			$o .= '<div class="ixw__cta"><button type="button" class="btn btn--accent" data-ixw-book>' . zad_icon( 'bolt', 20 ) . ' احجز معاينة بهذه الخطة</button>';
			if ( $wa ) { $o .= '<a class="btn btn--wa" data-ixw-wa target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . $wa ) . '">' . zad_icon( 'whatsapp', 20 ) . ' أرسلها واتساب</a>'; }
			$o .= '<button type="button" class="btn btn--ghost" data-ix-print>اطبع أو احفظ PDF</button><button type="button" class="btn btn--ghost" data-ixw-reset>إعادة الفحص</button></div></div></div>';
			break;

		case 'report':
			$rows = zad_ix_pipe( $d['items'] ?? '', 2 );
			if ( ! $rows ) { return ''; }
			$o .= zad_ix_head( $d, 'بكل شفافية' ) . '<div class="ixr"><div class="ixr__head"><b>' . esc_html( get_bloginfo( 'name' ) ) . '</b><span>تقرير زيارة</span><em>نموذج توضيحي</em></div><ul class="ixr__list">';
			foreach ( $rows as $r ) {
				$o .= '<li>' . zad_icon( 'check', 18 ) . '<div><strong>' . esc_html( $r[0] ) . '</strong><span>' . esc_html( $r[1] ) . '</span></div></li>';
			}
			$o .= '</ul></div>';
			break;

		case 'life':
			$rows = zad_ix_pipe( $d['items'] ?? '', 3 );
			if ( ! $rows ) { return ''; }
			$o .= zad_ix_head( $d, 'لماذا نتابع؟' ) . '<div class="ixl" data-ixl><ol class="ixl__steps">';
			foreach ( $rows as $i => $r ) {
				$o .= '<li><button type="button" class="ixl__step' . ( 0 === $i ? ' is-on' : '' ) . '" data-i="' . (int) $i . '"><b>' . ( $i + 1 ) . '</b><span>' . esc_html( $r[0] ) . '</span></button></li>';
			}
			$o .= '</ol><div class="ixl__panels">';
			foreach ( $rows as $i => $r ) {
				$o .= '<article class="ixl__panel" data-i="' . (int) $i . '"' . ( 0 === $i ? '' : ' hidden' ) . '><h3>' . esc_html( $r[0] ) . ( $r[2] ? ' <small>' . esc_html( $r[2] ) . '</small>' : '' ) . '</h3><p>' . esc_html( $r[1] ) . '</p></article>';
			}
			$o .= '</div>';
			if ( ! empty( $d['rule'] ) ) { $o .= '<p class="ixl__rule">' . esc_html( $d['rule'] ) . '</p>'; }
			$o .= '</div>';
			break;

		case 'safe':
			$rows = zad_ix_pipe( $d['items'] ?? '', 2 );
			if ( ! $rows ) { return ''; }
			$o .= zad_ix_head( $d, 'أمان أسرتك أولاً' ) . '<div class="ixs" data-ixs><div class="ixs__chips" role="group" aria-label="حالة الأسرة">';
			foreach ( $rows as $i => $r ) {
				$o .= '<button type="button" class="ixs__chip" data-i="' . (int) $i . '" aria-pressed="false">' . esc_html( $r[0] ) . '</button>';
			}
			$o .= '</div><p class="ixs__hint" data-ixs-hint>اختر ما ينطبق على أسرتك لتظهر الاحتياطات.</p><ul class="ixs__list">';
			foreach ( $rows as $i => $r ) {
				$o .= '<li data-i="' . (int) $i . '" hidden><strong>' . esc_html( $r[0] ) . '</strong> ' . esc_html( $r[1] ) . '</li>';
			}
			$o .= '</ul>';
			if ( ! empty( $d['after'] ) ) { $o .= '<p class="ixs__after">' . zad_icon( 'shield', 18 ) . ' ' . esc_html( $d['after'] ) . '</p>'; }
			$o .= '</div>';
			break;

		case 'check':
			$items = zad_lines( $d['items'] ?? '' );
			if ( ! $items ) { return ''; }
			$o .= zad_ix_head( $d, 'جهّز بيتك' ) . '<div class="ixc ix-printable" data-ixc data-key="' . esc_attr( 'zadixc' . $post_id ) . '" data-svc="' . esc_attr( $title ) . '" data-wa="' . esc_attr( zad_whatsapp( $post_id ) ) . '"><div class="ixc__bar"><div class="ixc__fill" data-ixc-fill></div></div><p class="ixc__pct"><b data-ixc-n>0</b> من ' . count( $items ) . ' جاهز</p><ul class="ixc__list">';
			foreach ( $items as $i => $it ) {
				$o .= '<li><label><input type="checkbox" data-i="' . (int) $i . '"><span>' . esc_html( $it ) . '</span></label></li>';
			}
			$o .= '</ul><div class="ixc__cta"><button type="button" class="btn btn--wa" data-ixc-wa>' . zad_icon( 'whatsapp', 20 ) . ' أرسل القائمة واتساب</button><button type="button" class="btn btn--ghost" data-ix-print>اطبع أو احفظ PDF</button></div></div>';
			break;
	}
	return '<section class="sec ix ix--' . esc_attr( $k ) . '" id="ix-' . esc_attr( $k ) . '"><div class="wrap">' . $o . '</div></section>';
}

/** Modules already printed (by shortcode) so the layout does not repeat them. */
function zad_ix_done( $k = null ) {
	static $done = array();
	if ( null === $k ) { return $done; }
	$done[ $k ] = true;
	return $done;
}

function zad_ix_render( $post_id ) {
	$ix = zad_ix_get( $post_id );
	if ( empty( $ix['on'] ) ) {
		return '';
	}
	$out  = '';
	$done = zad_ix_done();
	foreach ( zad_ix_order( $ix ) as $k ) {
		if ( empty( $ix['en'][ $k ] ) || isset( $done[ $k ] ) ) { continue; }
		$out .= zad_ix_module_html( $k, $ix, $post_id );
	}
	return $out;
}

add_shortcode( 'zad_ix', function ( $atts ) {
	$a  = shortcode_atts( array( 'm' => '' ), $atts );
	$id = get_the_ID();
	if ( ! $id || ! isset( zad_ix_modules()[ $a['m'] ] ) ) {
		return '';
	}
	$html = zad_ix_module_html( $a['m'], zad_ix_get( $id ), $id );
	if ( $html ) { zad_ix_done( $a['m'] ); }
	return $html;
} );

/* assets only where used */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$p = get_queried_object();
	if ( ! $p || ( ! zad_ix_enabled( $p->ID ) && ! has_shortcode( $p->post_content, 'zad_ix' ) ) ) {
		return;
	}
	wp_enqueue_style( 'zad-ix', get_template_directory_uri() . '/assets/css/zad-ix.css', array( 'zad-main' ), ZAD_VERSION );
	wp_enqueue_script( 'zad-ix', get_template_directory_uri() . '/assets/js/zad-ix.js', array(), ZAD_VERSION, true );
}, 110 );

/** Split rendered content around the "Read more" marker: [before, after]. */
function zad_ix_split( $html, $post_id ) {
	if ( ! zad_ix_enabled( $post_id ) ) {
		return array( $html, '' );
	}
	$parts = preg_split( '#(?:<p>\s*)?<span id="more-' . (int) $post_id . '"></span>(?:\s*</p>)?#', $html, 2 );
	if ( count( $parts ) < 2 ) {
		return array( $html, '' );
	}
	return array( force_balance_tags( $parts[0] ), force_balance_tags( $parts[1] ) );
}
