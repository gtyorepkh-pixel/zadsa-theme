<?php defined( 'ABSPATH' ) || exit;
/**
 * Quote request form: render, AJAX/no-JS handler, lead storage, email.
 */

function zad_quote_form( $args = array() ) {
	$a = wp_parse_args( $args, array(
		'service_id' => 0,
		'area'       => '',
		'title'      => 'اطلب عرض سعر مجاني',
		'sub'        => 'نرد عليك خلال دقائق',
		'id'         => 'q' . wp_rand( 100, 999 ),
		'compact'    => false,
	) );
	$services = get_posts( array( 'post_type' => 'zad_service', 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	$areas    = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0 ) );
	$sel_area = $a['area'];
	$status   = isset( $_GET['zad_sent'] ) ? sanitize_key( wp_unslash( $_GET['zad_sent'] ) ) : ''; // phpcs:ignore
	$id       = esc_attr( $a['id'] );
	ob_start();
	?>
	<form class="qform<?php echo $a['compact'] ? ' qform--compact' : ''; ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-zad-form novalidate>
		<?php if ( $a['title'] ) : ?>
			<div class="qform__head"><strong><?php echo esc_html( $a['title'] ); ?></strong><span><?php echo esc_html( $a['sub'] ); ?></span></div>
		<?php endif; ?>
		<?php if ( 'ok' === $status ) : ?><div class="qform__msg is-ok" role="status">وصلنا طلبك، سنتواصل معك قريباً.</div><?php endif; ?>
		<?php if ( 'err' === $status ) : ?><div class="qform__msg is-err" role="alert">تعذر إرسال الطلب، راجع البيانات وحاول مرة أخرى.</div><?php endif; ?>
		<input type="hidden" name="action" value="zad_quote">
		<input type="hidden" name="source" value="<?php echo esc_url( home_url( add_query_arg( null, null ) ) ); ?>">
		<div class="qform__hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

		<label class="fld" for="<?php echo $id; ?>-name"><span>الاسم</span>
			<input id="<?php echo $id; ?>-name" name="name" type="text" required autocomplete="name" placeholder="اسمك الكريم"></label>
		<label class="fld" for="<?php echo $id; ?>-phone"><span>رقم الجوال</span>
			<input id="<?php echo $id; ?>-phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel" placeholder="05xxxxxxxx" dir="ltr"></label>
		<label class="fld" for="<?php echo $id; ?>-service"><span>الخدمة</span>
			<select id="<?php echo $id; ?>-service" name="service" required>
				<option value="">اختر الخدمة</option>
				<?php foreach ( $services as $s ) : ?>
					<option value="<?php echo (int) $s->ID; ?>" <?php selected( (int) $a['service_id'], $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
				<?php endforeach; ?>
			</select></label>
		<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
			<label class="fld" for="<?php echo $id; ?>-area"><span>المدينة</span>
				<select id="<?php echo $id; ?>-area" name="area">
					<option value="">اختر المدينة</option>
					<?php foreach ( $areas as $t ) : ?>
						<option value="<?php echo esc_attr( $t->name ); ?>" <?php selected( $sel_area, $t->name ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select></label>
		<?php endif; ?>
		<?php if ( ! $a['compact'] ) : ?>
			<div class="fld--row">
				<label class="fld" for="<?php echo $id; ?>-date"><span>التاريخ المفضّل (اختياري)</span><input id="<?php echo $id; ?>-date" name="date" type="date"></label>
				<label class="fld" for="<?php echo $id; ?>-time"><span>الفترة (اختياري)</span>
					<select id="<?php echo $id; ?>-time" name="time"><option value="">أي وقت</option><option value="صباحاً">صباحاً</option><option value="ظهراً">ظهراً</option><option value="مساءً">مساءً</option></select></label>
			</div>
			<label class="fld" for="<?php echo $id; ?>-msg"><span>تفاصيل إضافية (اختياري)</span>
				<textarea id="<?php echo $id; ?>-msg" name="message" rows="3" placeholder="مساحة المكان، الحي، الوقت المناسب"></textarea></label>
		<?php endif; ?>
		<button class="btn btn--accent btn--block" type="submit"><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> إرسال الطلب</button>
		<p class="qform__note"><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> بياناتك محفوظة ولا تُشارك مع أي جهة.</p>
		<div class="qform__result" data-result hidden></div>
	</form>
	<?php
	return ob_get_clean();
}

add_shortcode( 'contact-form', function () {
	return zad_quote_form( array( 'title' => '', 'id' => 'sc' . wp_rand( 100, 999 ) ) );
} );
add_shortcode( 'zad_quote_form', function ( $atts ) {
	$atts = shortcode_atts( array( 'service' => 0, 'title' => 'اطلب عرض سعر مجاني' ), $atts );
	return zad_quote_form( array( 'service_id' => (int) $atts['service'], 'title' => $atts['title'] ) );
} );

function zad_normalize_phone( $raw ) {
	$d = preg_replace( '/\D+/', '', (string) $raw );
	if ( 0 === strpos( $d, '00966' ) ) {
		$d = substr( $d, 5 );
	} elseif ( 0 === strpos( $d, '966' ) ) {
		$d = substr( $d, 3 );
	}
	$d = ltrim( $d, '0' );
	if ( preg_match( '/^5\d{8}$/', $d ) ) {
		return '0' . $d;
	}
	// Non-mobile numbers (landline / foreign) accepted if plausible.
	$d2 = preg_replace( '/\D+/', '', (string) $raw );
	return ( strlen( $d2 ) >= 8 && strlen( $d2 ) <= 15 ) ? $d2 : '';
}

function zad_handle_quote() {
	$ajax = wp_doing_ajax();
	$fail = function ( $msg ) use ( $ajax ) {
		if ( $ajax ) {
			wp_send_json_error( array( 'message' => $msg ), 400 );
		}
		wp_safe_redirect( add_query_arg( 'zad_sent', 'err', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	};

	// No nonce on purpose: public form on cached pages; protected by honeypot + rate limit.
	if ( ! empty( $_POST['website'] ) ) { // honeypot
		$ajax ? wp_send_json_success( array( 'message' => 'تم' ) ) : wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'zad_rl_' . md5( $ip );
	$cnt = (int) get_transient( $key );
	if ( $cnt >= 5 ) {
		$fail( 'تم إرسال عدد كبير من الطلبات، حاول لاحقاً أو اتصل بنا مباشرة.' );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone   = zad_normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
	$sid     = isset( $_POST['service'] ) ? absint( $_POST['service'] ) : 0;
	$area    = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$date    = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
	$time    = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
	$addr    = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
	$lat     = isset( $_POST['lat'] ) && is_numeric( $_POST['lat'] ) ? (float) $_POST['lat'] : 0;
	$lng     = isset( $_POST['lng'] ) && is_numeric( $_POST['lng'] ) ? (float) $_POST['lng'] : 0;
	$map     = ( $lat && $lng ) ? 'https://maps.google.com/?q=' . $lat . ',' . $lng : '';
	$source  = isset( $_POST['source'] ) ? esc_url_raw( wp_unslash( $_POST['source'] ) ) : '';

	if ( mb_strlen( $name ) < 2 ) {
		$fail( 'يرجى كتابة الاسم.' );
	}
	if ( ! $phone ) {
		$fail( 'رقم الجوال غير صحيح.' );
	}
	$service = $sid ? get_post( $sid ) : null;
	if ( ! $service || 'zad_service' !== $service->post_type ) {
		$fail( 'اختر الخدمة المطلوبة.' );
	}

	set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );

	$lead_id = wp_insert_post( array(
		'post_type'   => 'zad_lead',
		'post_status' => 'publish',
		'post_title'  => $name . ' - ' . $service->post_title,
	) );
	if ( $lead_id && ! is_wp_error( $lead_id ) ) {
		update_post_meta( $lead_id, '_lead_phone', $phone );
		update_post_meta( $lead_id, '_lead_service', $service->post_title );
		update_post_meta( $lead_id, '_lead_area', $area );
		update_post_meta( $lead_id, '_lead_message', trim( $message . ( $date || $time ? "\nالموعد المفضّل: $date $time" : '' ) . ( $addr ? "\nالعنوان: $addr" : '' ) . ( $map ? "\nالموقع: $map" : '' ) ) );
		update_post_meta( $lead_id, '_lead_source', $source );
		update_post_meta( $lead_id, '_lead_ip', $ip );
		update_post_meta( $lead_id, '_lead_status', 'new' );
	}

	$to = zad_opt( 'memopt_lead_email', zad_opt( 'memopt_mail', get_option( 'admin_email' ) ) );
	if ( is_email( $to ) ) {
		$body  = "طلب جديد من الموقع\n\n";
		$body .= "الاسم: $name\nالجوال: $phone\nالخدمة: {$service->post_title}\nالمنطقة: $area\nالتفاصيل: $message\nالموعد المفضّل: $date $time\nالعنوان: $addr\nالموقع: $map\nالصفحة: $source\n";
		$body .= "واتساب: https://wa.me/" . zad_intl_number( $phone ) . "\n";
		wp_mail( $to, 'طلب جديد: ' . $service->post_title, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	$wa_text = "مرحباً، أنا $name وأرغب بخدمة: {$service->post_title}" . ( $area ? " في $area" : '' );
	$wa      = zad_wa_link( $wa_text, $sid );

	if ( $ajax ) {
		wp_send_json_success( array( 'message' => 'وصل طلبك يا ' . $name . '، سنتصل بك خلال دقائق.', 'whatsapp' => $wa ) );
	}
	wp_safe_redirect( add_query_arg( 'zad_sent', 'ok', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_zad_quote', 'zad_handle_quote' );
add_action( 'admin_post_zad_quote', 'zad_handle_quote' );
add_action( 'wp_ajax_nopriv_zad_quote', 'zad_handle_quote' );
add_action( 'wp_ajax_zad_quote', 'zad_handle_quote' );
