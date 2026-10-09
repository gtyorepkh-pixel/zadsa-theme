<?php defined( 'ABSPATH' ) || exit;
/** Booking wizard (3 steps) shown in a drawer; opened by any [data-open-wizard]. */

/** Icon for a section from its name (the sprite has: bug drop snow truck tool shield paint home sparkle). */
function zad_wiz_icon_for( $name ) {
	$map = array( '/حشر|مكافح|قوارض|pest/iu' => 'bug', '/خزان|مياه|مجار|تسليك/u' => 'drop', '/مكيف|تبريد|تكييف/u' => 'snow', '/نقل|تخزين|أثاث|اثاث/u' => 'truck', '/عزل/u' => 'shield', '/دهان|طلاء|بلاط|رخام|جلي/u' => 'paint', '/صيانة|نجار|كهرب|سباك/u' => 'tool', '/فلل|شقق|منزل/u' => 'home', '/تنظيف|نظاف/u' => 'sparkle' );
	foreach ( $map as $re => $ic ) { if ( preg_match( $re, (string) $name ) ) { return $ic; } }
	return 'sparkle';
}

/** Sections of the booking sheet and the home hero card: the theme setting «أقسام نافذة احجز موعدك» (سطر: الاسم | أيقونة), else a fixed default list; write «auto» in the setting to take them from the pages instead. */
function zad_wiz_manual() {
	$out = array();
	$src = trim( (string) zad_opt( 'zad_wiz_cats', '' ) );
	if ( 'auto' === $src ) { return array(); } // «auto»: sections come from the site's pages
	if ( '' === $src ) { $src = "مكافحة الحشرات | bug\nتنظيف المكيفات | snow\nتنظيف الخزانات | drop\nتنظيف الكنب والمفروشات | sparkle\nتنظيف فلل وشقق | home"; } // default sections: fixed, not dependent on how pages are filed
	foreach ( zad_lines( $src ) as $i => $l ) {
		$c  = array_map( 'trim', explode( '|', $l ) );
		if ( '' === $c[0] ) { continue; }
		$ic = isset( $c[1] ) ? sanitize_key( $c[1] ) : '';
		$out[] = array( 'key' => 'm' . $i, 'name' => $c[0], 'icon' => in_array( $ic, zad_icon_keys(), true ) ? $ic : zad_wiz_icon_for( $c[0] ) );
	}
	return $out;
}

/** Renames from the theme setting «أسماء أقسام نافذة احجز موعدك»: «الاسم الحالي | الاسم الظاهر | أيقونة» (matched by the current name or the section key). */
function zad_wiz_rename( $cats ) {
	$rules = array();
	foreach ( zad_lines( zad_opt( 'zad_wiz_names', '' ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		if ( count( $c ) >= 2 && '' !== $c[0] && '' !== $c[1] ) { $rules[ $c[0] ] = array( $c[1], isset( $c[2] ) ? sanitize_key( $c[2] ) : '' ); }
	}
	if ( ! $rules ) { return $cats; }
	foreach ( $cats as &$c ) {
		$r = isset( $rules[ $c['name'] ] ) ? $rules[ $c['name'] ] : ( isset( $rules[ $c['key'] ] ) ? $rules[ $c['key'] ] : null );
		if ( $r ) { $c['name'] = $r[0]; if ( '' !== $r[1] && in_array( $r[1], zad_icon_keys(), true ) ) { $c['icon'] = $r[1]; } }
	}
	unset( $c );
	return $cats;
}

/**
 * Sections + services for the booking sheet: built once, cached until content changes.
 * A section is the page's service_cat term, or — for pages that were never saved since the term existed — its post type,
 * so every kind of service shows up (not only the ones that already carry a term). Per section: shallowest pages first, 30 at most.
 */
function zad_wiz_data() {
	$d = get_transient( 'zad_wiz_map3' );
	if ( is_array( $d ) && ! empty( $d['services'] ) ) {
		return $d;
	}
	$buckets = array(); $cats = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 600, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $s ) {
		$t = get_the_terms( $s->ID, 'service_cat' );
		if ( $t && ! is_wp_error( $t ) ) {
			$nm = zad_wiz_cat_label( $t[0]->name ); $ic = (string) get_term_meta( $t[0]->term_id, 'zad_icon', true );
		} else {
			$o   = get_post_type_object( $s->post_type );
			$nm = zad_wiz_cat_label( $o ? $o->labels->name : $s->post_type ); $ic = '';
		}
		$key = 'n' . substr( md5( $nm ), 0, 8 ); // one section per name: the term «مكافحة الحشرات» and the post type «صفحات مكافحة الحشرات» are the same section
		if ( ! isset( $cats[ $key ] ) ) { $cats[ $key ] = array( 'key' => $key, 'name' => $nm, 'icon' => ( '' === $ic || 'sparkle' === $ic ) ? zad_wiz_icon_for( $nm ) : $ic ); }
		$buckets[ $key ][] = array( 'id' => $s->ID, 'name' => $s->post_title, 'cat' => $key, 'depth' => count( get_post_ancestors( $s ) ) );
	}
	$services = array();
	foreach ( $buckets as $rows ) {
		usort( $rows, function ( $a, $b ) { return $a['depth'] <=> $b['depth']; } ); // stable in PHP 8: menu order / title kept inside a depth
		foreach ( array_slice( $rows, 0, 30 ) as $r ) { unset( $r['depth'] ); $services[] = $r; }
	}
	if ( count( $cats ) > 1 ) { foreach ( $cats as $k => $c ) { if ( in_array( $c['name'], array( 'الخدمات', 'خدمات' ), true ) ) { unset( $cats[ $k ] ); } } } // the generic default type is not a section when real ones exist
	$d = array( 'services' => $services, 'cats' => array_values( $cats ) );
	if ( $services ) {
		set_transient( 'zad_wiz_map3', $d, 12 * HOUR_IN_SECONDS ); // never cache an empty list
	}
	return $d;
}

/** «صفحات مكافحة الحشرات» (post-type label) → «مكافحة الحشرات». */
function zad_wiz_cat_label( $name ) {
	$n = trim( preg_replace( '/^\s*(?:صفحات|صفحة)\s+/u', '', (string) $name ) );
	return '' === $n ? (string) $name : $n;
}

add_action( 'wp_footer', function () {
	$data  = zad_wiz_data(); // may be empty: the booking sheet must still open (service step is then optional)
	$map   = $data['services'];
	$cats  = zad_wiz_manual();
	if ( ! $cats ) { $cats = zad_wiz_rename( $data['cats'] ); }
	$cities = zad_lines( zad_opt( 'zad_wiz_cities', "الرياض\nجدة\nالدمام\nالقصيم\nنجران" ) );
	$cur   = zad_is_service() ? get_the_ID() : 0;
	$qid   = is_singular() ? (int) get_queried_object_id() : 0;
	$hood  = ''; // a neighbourhood page: its district (and city) go into the sheet; every other page leaves both empty
	$hcity = '';
	if ( $qid && function_exists( 'zad_hood_active' ) && zad_hood_active( $qid ) ) {
		$HD    = zad_hood_data( $qid );
		$hood  = (string) $HD['hood'];
		$hcity = (string) $HD['city'];
		if ( ! $cur ) { $cur = $qid; } // a page-type neighbourhood page is still «the page's service»
	}
	?>
<div class="wiz" id="zad-wizard" aria-hidden="true" data-current="<?php echo (int) $cur; ?>" data-area="<?php echo esc_attr( $hood ); ?>" data-city="<?php echo esc_attr( $hcity ); ?>" data-services="<?php echo esc_attr( wp_json_encode( array_values( $map ) ) ); ?>"><template id="zad-wizard-tpl">
	<div class="wiz__overlay" data-wiz-close></div>
	<div class="wiz__panel wz" role="dialog" aria-modal="true" aria-labelledby="wiz-title">

		<!-- rail: brand · live steps · reasons to trust (becomes a slim header on phones) -->
		<aside class="wz__rail">
			<div class="wz__brand"><span class="wz__logo"><?php echo zad_icon( 'calendar', 24 ); // phpcs:ignore ?></span><div><small>حجز سريع</small><strong id="wiz-title">احجز زيارة معاينة</strong></div></div>
			<ol class="wz__steps" aria-label="مراحل الحجز">
				<li data-dot="1" class="is-cur"><i>1</i><span><b>الخدمة</b><em data-rail="svc">لم تُختر بعد</em></span></li>
				<li data-dot="2"><i>2</i><span><b>الموعد</b><em data-rail="when">أي وقت يناسبك</em></span></li>
				<li data-dot="3"><i>3</i><span><b>بياناتك</b><em data-rail="who">رقم الجوال فقط</em></span></li>
			</ol>
			<ul class="wz__perks">
				<?php foreach ( array_slice( zad_lines( zad_opt( 'zad_card_badges', "فحص مجاني\nضمان مكتوب" ) ), 0, 3 ) as $pk ) : ?><li><?php echo zad_icon( 'check', 16 ); // phpcs:ignore ?><span><?php echo esc_html( $pk ); ?></span></li><?php endforeach; ?>
				<li><?php echo zad_icon( 'clock', 16 ); // phpcs:ignore ?><span><?php echo esc_html( zad_hours_text() ); ?></span></li>
			</ul>
		</aside>

		<div class="wz__main">
			<header class="wz__top">
				<p class="wz__lbl" data-step-lbl>الخطوة 1 من 3 · اختر خدمتك</p>
				<button type="button" class="wiz__x" data-wiz-close aria-label="إغلاق"><?php echo zad_icon( 'close', 20 ); // phpcs:ignore ?></button>
			</header>
			<div class="wz__bar" aria-hidden="true"><i data-bar></i></div>

			<form class="wiz__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-wiz-form>
				<input type="hidden" name="action" value="zad_quote">
				<input type="hidden" name="source" value="">
				<?php if ( $qid ) : ?><input type="hidden" name="page_id" value="<?php echo (int) $qid; ?>"><?php endif; ?>
				<?php if ( '' !== $hood ) : ?><input type="hidden" name="hood" value="<?php echo esc_attr( $hood ); ?>"><?php endif; ?>
				<input type="hidden" name="lat" value=""><input type="hidden" name="lng" value="">
				<input type="hidden" name="service" value="">
				<input type="hidden" name="svc_label" value=""><input type="hidden" name="wiz" value="1">
				<div class="qform__hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

				<!-- step 1: what + where -->
				<div class="wiz__step" data-step="1">
					<h3>ما الخدمة التي تحتاجها ولأي مدينة؟</h3>
					<p class="wiz__sub">اختر القسم والمدينة للمتابعة.</p>
					<?php if ( $cats ) : ?>
						<div class="wiz__lbl">الخدمة</div>
						<div class="wiz__cats" data-wiz-cats role="group" aria-label="الخدمة">
							<?php foreach ( $cats as $c ) : ?>
								<button type="button" class="wiz__cat" data-cat="<?php echo esc_attr( $c['key'] ); ?>"><span class="wz__ic"><?php echo zad_icon( $c['icon'], 24 ); // phpcs:ignore ?></span><span class="wz__tx"><?php echo esc_html( $c['name'] ); ?></span></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<label class="fld wz__sel"><span>الخدمة</span><select data-wiz-service><option value="">اختر الخدمة</option></select></label>
					<?php if ( $cities ) : ?>
						<div class="wiz__lbl">المدينة</div>
						<div class="wz__seg wz__seg--city" data-wiz-city role="group" aria-label="المدينة">
							<?php foreach ( $cities as $a ) : ?><button type="button" class="chipbtn" data-city="<?php echo esc_attr( $a ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?><span><?php echo esc_html( $a ); ?></span></button><?php endforeach; ?>
						</div>
						<input type="hidden" name="area" value="">
					<?php endif; ?>
					<p class="wiz__err" data-err="1" hidden></p>
				</div>

				<!-- step 2: when (optional) -->
				<div class="wiz__step" data-step="2" hidden>
					<h3>متى يناسبك الموعد؟</h3>
					<p class="wiz__sub">هذه الخطوة اختيارية — يمكنك تخطّيها والمتابعة.</p>
					<div class="wiz__lbl">اليوم</div>
					<div class="wz__seg wz__seg--day" data-wiz-day role="group" aria-label="اليوم">
						<button type="button" class="chipbtn" data-day="0"><b>اليوم</b><small data-dd></small></button><button type="button" class="chipbtn" data-day="1"><b>غداً</b><small data-dd></small></button><button type="button" class="chipbtn" data-day="2"><b>بعد غد</b><small data-dd></small></button>
					</div>
					<label class="fld"><span>أو اختر تاريخاً آخر</span><input type="date" name="date"></label>
					<div class="wiz__lbl">الفترة المفضّلة</div>
					<div class="wz__seg wz__seg--time" data-wiz-time role="group" aria-label="الفترة">
						<button type="button" class="chipbtn" data-time="صباحاً"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 18h18M6.5 18a5.5 5.5 0 0 1 11 0M12 5v3M4.6 9.6l2 2M19.4 9.6l-2 2"/></svg><span>صباحاً</span></button>
						<button type="button" class="chipbtn" data-time="ظهراً"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/></svg><span>ظهراً</span></button>
						<button type="button" class="chipbtn" data-time="مساءً"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z"/></svg><span>مساءً</span></button>
					</div>
					<input type="hidden" name="time" value="">
					<div class="wz__where">
						<label class="fld"><span>الحي أو العنوان</span><input type="text" name="address" autocomplete="address-level2" placeholder="مثال: حي النرجس"></label>
						<button type="button" class="wiz__geo" data-wiz-geo><?php echo zad_icon( 'pin', 20 ); // phpcs:ignore ?> <span>أو شارك موقعك على الخريطة</span></button>
					</div>
				</div>

				<!-- step 3: contact -->
				<div class="wiz__step" data-step="3" hidden>
					<h3>آخر خطوة — كيف نتواصل معك؟</h3>
					<p class="wiz__sub">رقم الجوال هو الحقل الوحيد المطلوب.</p>
					<div class="fld--row">
						<label class="fld"><span>رقم الجوال *</span><input type="tel" name="phone" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="05XXXXXXXX" required></label>
						<label class="fld"><span>الاسم</span><input type="text" name="name" autocomplete="name" placeholder="اسمك"></label>
					</div>
					<label class="fld"><span>ملاحظات</span><textarea name="message" rows="2" placeholder="أي تفاصيل تساعدنا على تحضير الزيارة"></textarea></label>
					<div class="wiz__sum" data-wiz-sum></div>
					<p class="wiz__err" data-err="3" hidden></p>
					<p class="qform__note"><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> طلب مبدئي — نؤكّد الموعد النهائي بالاتصال.</p>
				</div>

				<div class="wiz__nav">
					<button type="button" class="btn btn--ghost-dark" data-wiz-back hidden>رجوع</button>
					<button type="button" class="btn btn--primary" data-wiz-next>التالي <span aria-hidden="true">←</span></button>
					<button type="submit" class="btn btn--wa" data-wiz-submit hidden><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> إرسال الطلب</button>
				</div>
			</form>

			<div class="wiz__done" data-wiz-done hidden>
				<span class="icard__ic"><?php echo zad_icon( 'check', 28 ); // phpcs:ignore ?></span>
				<h3>تم استلام طلبك</h3>
				<p data-wiz-done-msg>سنتصل بك خلال دقائق لتأكيد الموعد.</p>
				<a class="btn btn--wa" data-wiz-wa href="#" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> متابعة عبر واتساب</a>
			</div>
		</div>
	</div></template>
</div>
	<?php
}, 5 ); // before the footer scripts (priority 20), so #zad-wizard exists when they run
