<?php defined( 'ABSPATH' ) || exit;
/** Booking wizard (3 steps) shown in a drawer; opened by any [data-open-wizard]. */

/** Services list for the booking drawer: built once, cached until content changes. */
function zad_wiz_map() {
	$map = get_transient( 'zad_wiz_map' );
	if ( is_array( $map ) && $map ) {
		return $map;
	}
	$map = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $s ) {
		$t = get_the_terms( $s->ID, 'service_cat' );
		$map[ $s->ID ] = array( 'id' => $s->ID, 'name' => $s->post_title, 'cat' => ( $t && ! is_wp_error( $t ) ) ? $t[0]->term_id : 0 );
	}
	if ( $map ) {
		set_transient( 'zad_wiz_map', $map, 12 * HOUR_IN_SECONDS ); // never cache an empty list
	}
	return $map;
}

/** «صفحات مكافحة الحشرات» (post-type label) → «مكافحة الحشرات». */
function zad_wiz_cat_label( $name ) {
	$n = trim( preg_replace( '/^\s*(?:صفحات|صفحة)\s+/u', '', (string) $name ) );
	return '' === $n ? (string) $name : $n;
}

add_action( 'wp_footer', function () {
	$map = zad_wiz_map(); // may be empty: the booking sheet must still open (service step is then optional)
	$cats  = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	$areas = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0 ) );
	$cur   = zad_is_service() ? get_the_ID() : 0;
	$icons = array( 'phone', 'shield', 'clock' );
	?>
<div class="wiz" id="zad-wizard" aria-hidden="true" data-current="<?php echo (int) $cur; ?>" data-area="" data-services="<?php echo esc_attr( wp_json_encode( array_values( $map ) ) ); ?>">
	<div class="wiz__overlay" data-wiz-close></div>
	<div class="wiz__panel wz" role="dialog" aria-modal="true" aria-labelledby="wiz-title">
		<header class="wz__top">
			<div class="wz__row">
				<div><span class="wz__kick">حجز سريع · معاينة مجانية</span><h2 id="wiz-title">احجز خدمتك</h2></div>
				<button type="button" class="wiz__x" data-wiz-close aria-label="إغلاق"><?php echo zad_icon( 'close', 22 ); // phpcs:ignore ?></button>
			</div>
			<div class="wiz__progress wz__bar" aria-hidden="true"><i class="is-on" data-dot="1"></i><i data-dot="2"></i><i data-dot="3"></i></div>
			<p class="wz__lbl" data-step-lbl>الخطوة 1 من 3 · اختر خدمتك</p>
		</header>

		<form class="wiz__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-wiz-form>
			<input type="hidden" name="action" value="zad_quote">
			<input type="hidden" name="source" value="">
			<input type="hidden" name="lat" value=""><input type="hidden" name="lng" value="">
			<input type="hidden" name="service" value="">
			<div class="qform__hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

			<!-- step 1 -->
			<div class="wiz__step" data-step="1">
				<h3>ما الذي تحتاجه؟</h3>
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
					<div class="wiz__cats" data-wiz-cats>
						<?php foreach ( $cats as $c ) : $ic = get_term_meta( $c->term_id, 'zad_icon', true ) ?: 'sparkle'; ?>
							<button type="button" class="wiz__cat" data-cat="<?php echo (int) $c->term_id; ?>"><?php echo zad_icon( $ic, 26 ); // phpcs:ignore ?><span><?php echo esc_html( zad_wiz_cat_label( $c->name ) ); ?></span></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="wz__svcs" data-wiz-svcs aria-live="polite"></div>
				<label class="fld wz__sel"><span>الخدمة</span><select data-wiz-service><option value="">اختر الخدمة</option></select></label>
				<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
					<div class="wiz__lbl">في أي مدينة؟</div>
					<div class="wz__seg" data-wiz-city>
						<?php foreach ( $areas as $a ) : ?><button type="button" class="chipbtn" data-city="<?php echo esc_attr( $a->name ); ?>"><?php echo esc_html( $a->name ); ?></button><?php endforeach; ?>
					</div>
					<input type="hidden" name="area" value="">
				<?php endif; ?>
				<p class="wiz__err" data-err="1" hidden></p>
			</div>

			<!-- step 2 -->
			<div class="wiz__step" data-step="2" hidden>
				<h3>متى يناسبك؟</h3>
				<p class="wiz__sub">اختياري بالكامل — يمكنك المتابعة مباشرة.</p>
				<div class="wiz__lbl">اليوم</div>
				<div class="wz__seg" data-wiz-day>
					<button type="button" class="chipbtn" data-day="0">اليوم</button><button type="button" class="chipbtn" data-day="1">غداً</button><button type="button" class="chipbtn" data-day="2">بعد غد</button>
				</div>
				<label class="fld"><span>أو اختر تاريخاً</span><input type="date" name="date"></label>
				<div class="wiz__lbl">الفترة</div>
				<div class="wz__seg" data-wiz-time>
					<button type="button" class="chipbtn" data-time="صباحاً">صباحاً</button><button type="button" class="chipbtn" data-time="ظهراً">ظهراً</button><button type="button" class="chipbtn" data-time="مساءً">مساءً</button>
				</div>
				<input type="hidden" name="time" value="">
				<button type="button" class="wiz__geo" data-wiz-geo><?php echo zad_icon( 'pin', 20 ); // phpcs:ignore ?> <span>شارك موقعك على الخريطة (اختياري)</span></button>
				<label class="fld"><span>الحي أو العنوان</span><input type="text" name="address" autocomplete="address-level2" placeholder="مثال: حي النرجس"></label>
			</div>

			<!-- step 3 -->
			<div class="wiz__step" data-step="3" hidden>
				<h3>آخر خطوة</h3>
				<div class="wiz__sum" data-wiz-sum></div>
				<p class="wiz__sub">رقم الجوال هو الحقل الوحيد المطلوب.</p>
				<div class="fld--row">
					<label class="fld"><span>رقم الجوال *</span><input type="tel" name="phone" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="05XXXXXXXX" required></label>
					<label class="fld"><span>الاسم</span><input type="text" name="name" autocomplete="name" placeholder="اسمك"></label>
				</div>
				<label class="fld"><span>ملاحظات</span><textarea name="message" rows="2" placeholder="أي تفاصيل تساعدنا على تحضير الزيارة"></textarea></label>
				<p class="wiz__err" data-err="3" hidden></p>
				<p class="qform__note"><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> طلب مبدئي — نؤكّد الموعد النهائي بالاتصال.</p>
			</div>

			<div class="wiz__nav">
				<button type="button" class="btn btn--ghost-dark" data-wiz-back hidden>رجوع</button>
				<button type="button" class="btn btn--primary" data-wiz-next>التالي</button>
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
</div>
	<?php
}, 5 ); // before the footer scripts (priority 20), so #zad-wizard exists when they run
