<?php defined( 'ABSPATH' ) || exit;
/** Booking wizard (3 steps) shown in a drawer; opened by any [data-open-wizard]. */

add_action( 'wp_footer', function () {
	$services = get_posts( array( 'post_type' => 'zad_service', 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	if ( ! $services ) {
		return;
	}
	$cats  = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	$areas = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0 ) );
	$cur   = is_singular( 'zad_service' ) ? get_the_ID() : 0;
	$map   = array();
	foreach ( $services as $s ) {
		$t = get_the_terms( $s->ID, 'service_cat' );
		$map[ $s->ID ] = array( 'id' => $s->ID, 'name' => $s->post_title, 'cat' => ( $t && ! is_wp_error( $t ) ) ? $t[0]->term_id : 0 );
	}
	$icons = array( 'phone', 'shield', 'clock' );
	?>
<div class="wiz" id="zad-wizard" aria-hidden="true" data-current="<?php echo (int) $cur; ?>" data-area="<?php echo esc_attr( ( function_exists( 'zad_current_city' ) && zad_current_city() ) ? zad_current_city()->name : '' ); ?>" data-services="<?php echo esc_attr( wp_json_encode( array_values( $map ) ) ); ?>">
	<div class="wiz__overlay" data-wiz-close></div>
	<div class="wiz__panel" role="dialog" aria-modal="true" aria-labelledby="wiz-title">
		<div class="wiz__head"><h2 id="wiz-title">احجز موعدك</h2><button type="button" class="wiz__x" data-wiz-close aria-label="إغلاق"><?php echo zad_icon( 'close', 22 ); // phpcs:ignore ?></button></div>

		<ul class="wiz__trust">
			<?php if ( zad_opt( 'zad_since' ) ) : ?><li><?php echo zad_icon( 'bolt', 16 ); // phpcs:ignore ?> خبرة منذ <?php echo esc_html( zad_opt( 'zad_since' ) ); ?></li><?php endif; ?>
			<li><?php echo zad_icon( 'badge', 16 ); // phpcs:ignore ?> ضمان مكتوب</li>
			<li><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> معاينة مجانية</li>
		</ul>

		<ol class="wiz__progress" aria-hidden="true">
			<li class="is-on" data-dot="1"><span>1</span>الخدمة</li><li data-dot="2"><span>2</span>الموعد</li><li data-dot="3"><span>3</span>بياناتك</li>
		</ol>

		<form class="wiz__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-wiz-form>
			<input type="hidden" name="action" value="zad_quote">
			<input type="hidden" name="source" value="">
			<input type="hidden" name="lat" value=""><input type="hidden" name="lng" value="">
			<input type="hidden" name="service" value="">
			<div class="qform__hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

			<!-- step 1 -->
			<div class="wiz__step" data-step="1">
				<h3>ما الخدمة التي تحتاجها ولأي مدينة؟</h3>
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
					<div class="wiz__lbl">القسم</div>
					<div class="wiz__cats" data-wiz-cats>
						<?php foreach ( $cats as $c ) : $ic = get_term_meta( $c->term_id, 'zad_icon', true ) ?: 'sparkle'; ?>
							<button type="button" class="wiz__cat" data-cat="<?php echo (int) $c->term_id; ?>"><?php echo zad_icon( $ic, 24 ); // phpcs:ignore ?><span><?php echo esc_html( $c->name ); ?></span></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<label class="fld"><span>الخدمة</span><select data-wiz-service><option value="">اختر الخدمة</option></select></label>
				<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
					<div class="wiz__lbl">المدينة</div>
					<div class="wiz__chips" data-wiz-city>
						<?php foreach ( $areas as $a ) : ?><button type="button" class="chipbtn" data-city="<?php echo esc_attr( $a->name ); ?>"><?php echo esc_html( $a->name ); ?></button><?php endforeach; ?>
					</div>
					<input type="hidden" name="area" value="">
				<?php endif; ?>
				<p class="wiz__err" data-err="1" hidden></p>
			</div>

			<!-- step 2 -->
			<div class="wiz__step" data-step="2" hidden>
				<h3>متى وأين يناسبك الموعد؟</h3>
				<p class="wiz__sub">هذه الخطوة اختيارية بالكامل — يمكنك تخطّيها.</p>
				<div class="wiz__lbl">الفترة المفضّلة</div>
				<div class="wiz__chips" data-wiz-time>
					<button type="button" class="chipbtn" data-time="صباحاً">صباحاً</button><button type="button" class="chipbtn" data-time="ظهراً">ظهراً</button><button type="button" class="chipbtn" data-time="مساءً">مساءً</button>
				</div>
				<input type="hidden" name="time" value="">
				<label class="fld"><span>التاريخ المفضّل</span><input type="date" name="date"></label>
				<button type="button" class="wiz__geo" data-wiz-geo><?php echo zad_icon( 'pin', 20 ); // phpcs:ignore ?> <span>شارك موقعك على الخريطة (اختياري)</span></button>
				<label class="fld"><span>الحي أو العنوان</span><input type="text" name="address" autocomplete="address-level2" placeholder="مثال: حي النرجس"></label>
			</div>

			<!-- step 3 -->
			<div class="wiz__step" data-step="3" hidden>
				<h3>بياناتك</h3>
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
}, 20 );
