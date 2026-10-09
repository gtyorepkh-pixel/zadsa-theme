<?php defined( 'ABSPATH' ) || exit;
/**
 * Orders (the after-service system): order → tracking → warranty → verification → review.
 *
 * zad_order       private CPT (show_ui): first name, phone, hood, city, service, appointment, technician, final price, warranty, track token, status log.
 * zad_technician  private CPT: name, photo, years of experience, services.
 * Orders are created automatically from the quote form / booking wizard (theme action zad_lead_created — the forms behave as before),
 * or by hand from «طلب سريع». Statuses: received → confirmed → assigned → on the way → arrived → done (+ cancelled, postponed).
 */

/* ---------------------------------------------- settings ---------------------------------------------- */

add_action( 'zad_tools_register_settings', function () {
	zt_register_settings( 'orders', 'الطلبات والضمان', array(
		array( 'key' => 'review_delay_hours', 'label' => 'تذكير التقييم بعد «تمت الخدمة» (ساعات)', 'type' => 'number', 'default' => 24, 'min' => 1, 'max' => 720, 'source' => 'من أمر التنفيذ (الأداة 7): بعد 24 ساعة، مرة واحدة' ),
		array( 'key' => 'warranty_notice_days', 'label' => 'تذكير قبل انتهاء الضمان (أيام)', 'type' => 'number', 'default' => 14, 'min' => 1, 'max' => 120, 'source' => 'من أمر التنفيذ (الأداة 7): قبل 14 يوم، لمن وافق' ),
		array( 'key' => 'verify_per_hour', 'label' => 'محاولات التحقق من الضمان في الساعة لكل جهاز', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 200, 'source' => 'من أمر التنفيذ (الأداة 7): 10 محاولات في الساعة' ),
		array( 'key' => 'poll_seconds', 'label' => 'تحديث صفحة التتبع تلقائياً كل (ثانية) أثناء «في الطريق»', 'type' => 'number', 'default' => 60, 'min' => 15, 'max' => 600, 'source' => 'من أمر التنفيذ (الأداة 7): كل دقيقة' ),
		array( 'key' => 'track_per_hour', 'label' => 'أقصى طلبات تتبع في الساعة لكل جهاز', 'type' => 'number', 'default' => 240, 'min' => 20, 'max' => 2000, 'approval' => true, 'source' => 'حد تقني من عندي (4 طلبات/دقيقة) — يحتاج اعتماداً' ),
		array( 'key' => 'feedback_max', 'label' => 'أقصى عدد ملاحظات داخلية لكل طلب', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 20, 'approval' => true, 'source' => 'حد منع إغراق من عندي — يحتاج اعتماداً' ),
		array( 'key' => 'feedback_enabled', 'label' => 'نموذج «ملاحظاتك لنا» الداخلي (بجانب زر جوجل لا بدله)', 'type' => 'checkbox', 'default' => 1 ),
		array( 'key' => 'warranty_terms', 'label' => 'شروط الضمان (تظهر في الشهادة)', 'type' => 'textarea', 'default' => '', 'desc' => 'سطر لكل شرط. فارغ = لا تُطبع شروط ولا يظهر زر الشهادة.' ),
		array( 'key' => 'review_msg', 'label' => 'نص رسالة طلب التقييم', 'type' => 'textarea', 'default' => "شكراً لثقتك بـ{المنشأة} يا {الاسم}. لو حابب تشاركنا رأيك في الخدمة: {رابط_التقييم}\nلإيقاف الرسائل: {رابط_الإيقاف}" ),
		array( 'key' => 'warranty_msg', 'label' => 'نص رسالة قرب انتهاء الضمان', 'type' => 'textarea', 'default' => "السلام عليكم {الاسم}، ضمان {الخدمة} من {المنشأة} يقارب الانتهاء. تفاصيل الضمان: {رابط_التتبع}\nلإيقاف التذكيرات: {رابط_الإيقاف}" ),
		array( 'key' => 'status_msg', 'label' => 'نص رسالة تحديث الحالة للعميل', 'type' => 'textarea', 'default' => "السلام عليكم {الاسم}، طلبك لدى {المنشأة}: {الحالة}.\nتتبّع الطلب: {رابط_التتبع}" ),
	) );
} );

/** The order pages (track, certificate, verify) need the general privacy values approved: they hold personal data. */
function zt_orders_ready() { return zt_tool_ready( 'orders' ) && zt_tool_ready( 'general' ); }

/* ---------------------------------------------- statuses ---------------------------------------------- */

function zt_order_statuses() {
	return array(
		'received' => 'تم الاستلام', 'confirmed' => 'تم التأكيد', 'assigned' => 'تم تعيين الفني', 'on_the_way' => 'الفني في الطريق',
		'arrived' => 'وصل الفني', 'done' => 'تمت الخدمة', 'cancelled' => 'ملغي', 'postponed' => 'مؤجل',
	);
}
function zt_order_flow() { return array( 'received', 'confirmed', 'assigned', 'on_the_way', 'arrived', 'done' ); }
/** The next step in the normal flow ('' at the end or off-flow). */
function zt_order_next( $status ) {
	$f = zt_order_flow(); $i = array_search( $status, $f, true );
	return ( false !== $i && isset( $f[ $i + 1 ] ) ) ? $f[ $i + 1 ] : '';
}

/* ---------------------------------------------- pure helpers (unit-tested) ---------------------------------------------- */

/** Add months to a Y-m-d date; the day is clamped to the last day of the target month (31 Jan + 1 month = 28/29 Feb). */
function zt_add_months( $ymd, $months ) {
	$d = date_create_immutable( $ymd . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	if ( ! $d ) { return ''; }
	$day = (int) $d->format( 'j' );
	$t   = $d->modify( 'first day of this month' )->modify( '+' . (int) $months . ' months' );
	$last = (int) $t->format( 't' );
	return $t->setDate( (int) $t->format( 'Y' ), (int) $t->format( 'n' ), min( $day, $last ) )->format( 'Y-m-d' );
}

/** 3 → «3 أشهر»، 6 → «6 أشهر»، 12 → «سنة»، 180 → «15 عاماً» (Arabic number agreement). */
function zt_months_label( $m ) {
	$m = (int) $m;
	if ( $m > 0 && 0 === $m % 12 ) {
		$y = intdiv( $m, 12 );
		if ( 1 === $y ) { return 'سنة'; }
		if ( 2 === $y ) { return 'سنتان'; }
		return $y . ( $y <= 10 ? ' سنوات' : ' عاماً' );
	}
	if ( 1 === $m ) { return 'شهر'; }
	if ( 2 === $m ) { return 'شهران'; }
	return $m . ( $m <= 10 ? ' أشهر' : ' شهراً' );
}

/** «أحمد» → «أ***د» (never the whole name on a public page). */
function zt_mask_name( $first ) {
	$f = trim( (string) $first ); $n = mb_strlen( $f );
	if ( 0 === $n ) { return '***'; }
	if ( $n <= 2 ) { return mb_substr( $f, 0, 1 ) . '***'; }
	return mb_substr( $f, 0, 1 ) . '***' . mb_substr( $f, -1 );
}

function zt_first_name( $full ) {
	$p = preg_split( '/\s+/u', preg_replace( '/^\s+|\s+$/u', '', (string) $full ) );
	return mb_substr( (string) ( $p[0] ?? '' ), 0, 60 );
}

/** Uppercase, digits normalised, only letters/digits → «ZAD-2610-8K4Q7M» shape when it is one; else the cleaned string. */
function zt_code_normalize( $s ) {
	$c = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', zt_digits_en( (string) $s ) ) );
	if ( preg_match( '/^ZAD(\d{4})([A-Z2-9]{6})$/', $c, $m ) ) { return 'ZAD-' . $m[1] . '-' . $m[2]; }
	return $c;
}

/** New code: ZAD-YYMM-XXXXXX (no 0/O/1/I). Collision check is the caller's. */
function zt_code_make( $ym = null ) {
	$ym = $ym ?: wp_date( 'ym' );
	$al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $x = '';
	for ( $i = 0; $i < 6; $i++ ) { $x .= $al[ random_int( 0, strlen( $al ) - 1 ) ]; }
	return 'ZAD-' . $ym . '-' . $x;
}

function zt_track_url( $token ) {
	$slug = (string) get_option( 'zt_track_path', '' );
	return '' !== $slug ? home_url( '/' . $slug . '/' . $token . '/' ) : add_query_arg( 't', $token, home_url( '/' ) );
}
function zt_review_url() {
	$p = trim( (string) zt_opt( 'general.google_place_id' ) );
	return '' === $p ? '' : 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $p );
}

/* ---------------------------------------------- post types ---------------------------------------------- */

add_action( 'init', function () {
	register_post_type( 'zad_order', array(
		'labels' => array( 'name' => 'الطلبات', 'singular_name' => 'طلب', 'menu_name' => 'الطلبات', 'add_new' => 'طلب جديد', 'add_new_item' => 'طلب جديد', 'edit_item' => 'تعديل الطلب', 'all_items' => 'كل الطلبات' ),
		'public' => false, 'show_ui' => true, 'show_in_menu' => 'zad-tools', 'publicly_queryable' => false, 'exclude_from_search' => true, 'has_archive' => false, 'rewrite' => false, 'query_var' => false,
		'supports' => array( 'title' ), 'capability_type' => 'post', 'map_meta_cap' => true, 'show_in_rest' => false,
	) );
	register_post_type( 'zad_technician', array(
		'labels' => array( 'name' => 'الفنيون', 'singular_name' => 'فني', 'menu_name' => 'الفنيون', 'add_new' => 'إضافة فني', 'add_new_item' => 'إضافة فني', 'edit_item' => 'تعديل الفني', 'all_items' => 'كل الفنيين' ),
		'public' => false, 'show_ui' => true, 'show_in_menu' => 'zad-tools', 'publicly_queryable' => false, 'exclude_from_search' => true, 'has_archive' => false, 'rewrite' => false, 'query_var' => false,
		'supports' => array( 'title', 'thumbnail' ), 'capability_type' => 'post', 'map_meta_cap' => true, 'show_in_rest' => false,
	) );
} );

/** Optional technician role: sees only their own orders and moves them forward. */
function zt_orders_install_roles() {
	add_role( 'zad_technician', 'فني زاد', array( 'read' => true, 'zt_orders' => true ) );
	$a = get_role( 'administrator' );
	if ( $a ) { $a->add_cap( 'zt_orders' ); }
}
add_action( 'init', function () { if ( 'v1' !== get_option( 'zt_roles_ver' ) ) { zt_orders_install_roles(); update_option( 'zt_roles_ver', 'v1', false ); } } );

function zt_current_technician_id() {
	if ( current_user_can( 'manage_options' ) ) { return 0; }
	$u = get_current_user_id();
	$q = get_posts( array( 'post_type' => 'zad_technician', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zt_user_id', 'meta_value' => $u ) );
	return $q ? (int) $q[0] : -1; // -1: a technician account that is not linked to a technician record sees nothing
}

/* ---------------------------------------------- create / read ---------------------------------------------- */

function zt_order_create( $d ) {
	$first = zt_first_name( $d['name'] ?? '' );
	$phone = zt_normalize_phone( $d['phone'] ?? '' );
	if ( mb_strlen( $first ) < 1 ) { $first = 'عميل'; }
	if ( '' === $phone ) { return new WP_Error( 'phone', 'رقم الجوال غير صحيح.' ); }
	$svc = mb_substr( sanitize_text_field( (string) ( $d['service'] ?? '' ) ), 0, 160 );
	$id  = wp_insert_post( array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'post_title' => $first . ' — ' . ( '' !== $svc ? $svc : 'طلب' ) ), true );
	if ( is_wp_error( $id ) ) { return $id; }
	$m = array(
		'first_name' => $first, 'phone' => $phone, 'service' => $svc, 'service_id' => absint( $d['service_id'] ?? 0 ),
		'city' => mb_substr( sanitize_text_field( (string) ( $d['city'] ?? '' ) ), 0, 80 ), 'hood' => mb_substr( sanitize_text_field( (string) ( $d['hood'] ?? '' ) ), 0, 120 ),
		'appt_date' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['date'] ?? '' ) ) ? $d['date'] : '', 'appt_period' => mb_substr( sanitize_text_field( (string) ( $d['time'] ?? '' ) ), 0, 30 ),
		'appt_time' => preg_match( '/^\d{2}:\d{2}$/', (string) ( $d['clock'] ?? '' ) ) ? $d['clock'] : '',
		'source' => preg_replace( '/[^a-z]/', '', strtolower( (string) ( $d['source'] ?? 'manual' ) ) ), 'lead_id' => absint( $d['lead_id'] ?? 0 ),
		'note' => sanitize_textarea_field( (string) ( $d['note'] ?? '' ) ), 'track_token' => bin2hex( random_bytes( 16 ) ),
		'status' => 'received', 'status_log' => array( array( 'received', time(), (int) get_current_user_id() ) ),
	);
	foreach ( $m as $k => $v ) { update_post_meta( $id, '_zt_' . $k, $v ); }
	do_action( 'zad_order_created', (int) $id );
	return (int) $id;
}

/** Orders are created for every stored quote request and booking; a failure here never reaches the visitor. */
add_action( 'zad_lead_created', function ( $lead_id, $d ) {
	try {
		$svc = $d['service'];
		if ( ! empty( $d['service_id'] ) && function_exists( 'zad_card_title' ) ) { $svc = zad_card_title( (int) $d['service_id'] ); }
		zt_order_create( array(
			'name' => $d['name'], 'phone' => $d['phone'], 'service_id' => $d['service_id'], 'service' => $svc, 'city' => $d['city'], 'hood' => $d['hood'],
			'date' => $d['date'], 'time' => $d['time'], 'source' => ! empty( $d['wizard'] ) ? 'wizard' : 'form', 'lead_id' => $lead_id, 'note' => $d['message'],
		) );
	} catch ( \Throwable $e ) { /* the form already succeeded */ }
}, 20, 2 );

function zt_order_get( $id ) {
	$g = function ( $k, $d = '' ) use ( $id ) { $v = get_post_meta( $id, '_zt_' . $k, true ); return '' === $v ? $d : $v; };
	return array(
		'id' => (int) $id, 'first_name' => $g( 'first_name' ), 'phone' => $g( 'phone' ), 'service' => $g( 'service' ), 'service_id' => (int) $g( 'service_id', 0 ),
		'city' => $g( 'city' ), 'hood' => $g( 'hood' ), 'appt_date' => $g( 'appt_date' ), 'appt_period' => $g( 'appt_period' ), 'appt_time' => $g( 'appt_time' ),
		'technician_id' => (int) $g( 'technician_id', 0 ), 'price' => $g( 'price' ), 'token' => $g( 'track_token' ), 'status' => $g( 'status', 'received' ),
		'log' => (array) get_post_meta( $id, '_zt_status_log', true ), 'done_at' => (int) $g( 'done_at', 0 ), 'warranty_months' => (int) $g( 'warranty_months', 0 ),
		'warranty_code' => $g( 'warranty_code' ), 'service_date' => $g( 'service_date' ), 'warranty_end' => $g( 'warranty_end' ), 'reminder_optin' => (bool) $g( 'reminder_optin', 0 ),
		'note' => $g( 'note' ), 'source' => $g( 'source' ),
	);
}
function zt_order_by_token( $token ) {
	if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) { return 0; }
	$q = get_posts( array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zt_track_token', 'meta_value' => $token, 'no_found_rows' => true ) );
	return $q ? (int) $q[0] : 0;
}
function zt_order_by_code( $code ) {
	$c = zt_code_normalize( $code );
	if ( ! preg_match( '/^ZAD-\d{4}-[A-Z2-9]{6}$/', $c ) ) { return 0; }
	$q = get_posts( array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zt_warranty_code', 'meta_value' => $c, 'no_found_rows' => true ) );
	return $q ? (int) $q[0] : 0;
}

/** Warranty months of the order: the order's own override, else the unified theme policy for that service (0 = the person must enter it). */
function zt_order_policy_months( $o ) {
	if ( ! empty( $o['warranty_months'] ) ) { return (int) $o['warranty_months']; }
	return ( $o['service_id'] && function_exists( 'zad_warranty_months' ) ) ? (int) zad_warranty_months( $o['service_id'] ) : 0;
}

/* ---------------------------------------------- status changes ---------------------------------------------- */

/** Returns true or WP_Error. Marking «done» issues the warranty (months from the theme's unified policy, or the number the admin entered) and schedules the one review reminder. */
function zt_order_set_status( $id, $status, $user = 0 ) {
	$id = (int) $id;
	if ( 'zad_order' !== get_post_type( $id ) ) { return new WP_Error( 'order', 'الطلب غير موجود.' ); }
	if ( ! isset( zt_order_statuses()[ $status ] ) ) { return new WP_Error( 'status', 'حالة غير معروفة.' ); }
	$o = zt_order_get( $id );
	if ( $o['status'] === $status ) { return true; }
	$tech = zt_current_technician_id();
	if ( $tech && ( $tech < 0 || $o['technician_id'] !== $tech ) ) { return new WP_Error( 'cap', 'هذا الطلب ليس لك.' ); }
	if ( $tech ) { // a technician moves forward only, and never to cancelled / postponed
		$f = zt_order_flow();
		if ( ! in_array( $status, $f, true ) || array_search( $status, $f, true ) <= array_search( $o['status'], $f, true ) ) { return new WP_Error( 'cap', 'غير مسموح بهذه الحالة.' ); }
	}
	if ( 'assigned' === $status && ! $o['technician_id'] ) { return new WP_Error( 'tech', 'اختر الفني أولاً.' ); }
	if ( 'done' === $status ) {
		$months = zt_order_policy_months( $o );
		if ( $months < 1 ) { return new WP_Error( 'warranty_months', 'اكتب مدة الضمان بالأشهر في الطلب قبل «تمت الخدمة» (الصراصير: حسب الاتفاق).' ); }
	}
	$log = $o['log']; $log[] = array( $status, time(), (int) $user );
	update_post_meta( $id, '_zt_status', $status ); update_post_meta( $id, '_zt_status_log', $log );
	if ( 'done' === $status ) { zt_order_issue_warranty( $id, $months ); }
	do_action( 'zad_order_status_changed', $id, $status, $o['status'] );
	return true;
}

function zt_order_issue_warranty( $id, $months ) {
	$today = wp_date( 'Y-m-d' );
	$code  = (string) get_post_meta( $id, '_zt_warranty_code', true );
	if ( '' === $code ) {
		for ( $i = 0; $i < 20; $i++ ) { $c = zt_code_make(); if ( ! zt_order_by_code( $c ) ) { $code = $c; break; } }
	}
	update_post_meta( $id, '_zt_done_at', time() ); update_post_meta( $id, '_zt_service_date', $today );
	update_post_meta( $id, '_zt_warranty_months', (int) $months ); update_post_meta( $id, '_zt_warranty_code', $code );
	update_post_meta( $id, '_zt_warranty_end', zt_add_months( $today, (int) $months ) );
	$o = zt_order_get( $id );
	// one review reminder (a follow-up to the service itself, not marketing): due after the configured delay; shown in «تذكيرات اليوم»
	if ( ! get_post_meta( $id, '_zt_review_reminder', true ) ) {
		$due = wp_date( 'Y-m-d', time() + (int) zt_opt( 'orders.review_delay_hours' ) * HOUR_IN_SECONDS );
		$r   = zt_reminder_add( array( 'first_name' => $o['first_name'], 'phone' => $o['phone'], 'service' => $o['service'], 'due_date' => $due, 'tool' => 'orders-review', 'hood' => $o['hood'], 'ref' => $o['token'], 'transactional' => true ) );
		if ( ! is_wp_error( $r ) ) { update_post_meta( $id, '_zt_review_reminder', (int) $r ); }
	}
	if ( $o['reminder_optin'] ) { zt_order_schedule_warranty_reminder( $id ); }
}

/** Reminder before the warranty ends — only for a customer who ticked the box on the tracking page. */
function zt_order_schedule_warranty_reminder( $id ) {
	$o = zt_order_get( $id );
	if ( '' === $o['warranty_end'] || get_post_meta( $id, '_zt_warranty_reminder', true ) ) { return 0; }
	$due = wp_date( 'Y-m-d', strtotime( $o['warranty_end'] . ' -' . (int) zt_opt( 'orders.warranty_notice_days' ) . ' days' ) );
	if ( $due < wp_date( 'Y-m-d' ) ) { return 0; }
	$r = zt_reminder_add( array( 'first_name' => $o['first_name'], 'phone' => $o['phone'], 'service' => $o['service'], 'due_date' => $due, 'tool' => 'orders-warranty', 'hood' => $o['hood'], 'ref' => $o['token'], 'consent' => true ) );
	if ( ! is_wp_error( $r ) ) { update_post_meta( $id, '_zt_warranty_reminder', (int) $r ); return (int) $r; }
	return 0;
}

/** WhatsApp text for the customer after a status change (carries the tracking link). */
function zt_order_status_message( $id, $status = '' ) {
	$o = zt_order_get( $id ); $st = '' !== $status ? $status : $o['status'];
	return strtr( (string) zt_opt( 'orders.status_msg' ), array( '{الاسم}' => $o['first_name'], '{المنشأة}' => zt_brand(), '{الحالة}' => zt_order_statuses()[ $st ] ?? $st, '{رابط_التتبع}' => zt_track_url( $o['token'] ), '{الخدمة}' => $o['service'] ) );
}

/* ---------------------------------------------- admin: order metabox + technician box ---------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'zt_order', 'بيانات الطلب', 'zt_order_box', 'zad_order', 'normal', 'high' );
	add_meta_box( 'zt_tech', 'بيانات الفني', 'zt_tech_box', 'zad_technician', 'normal', 'high' );
} );

function zt_services_for_select() {
	if ( ! function_exists( 'zad_service_types' ) ) { return array(); }
	$out = array();
	foreach ( get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'post_parent' => 0, 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) { $out[ $p->ID ] = function_exists( 'zad_card_title' ) ? zad_card_title( $p->ID ) : $p->post_title; }
	return $out;
}

function zt_order_box( $post ) {
	wp_nonce_field( 'zt_order_box', 'zt_order_nonce' );
	$o = zt_order_get( $post->ID );
	$techs = get_posts( array( 'post_type' => 'zad_technician', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<table class="form-table" role="presentation"><tbody>';
	$row = function ( $label, $html ) { echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; }; // phpcs:ignore
	$row( 'الحالة', '<strong>' . esc_html( zt_order_statuses()[ $o['status'] ] ?? $o['status'] ) . '</strong> — تُغيَّر من شاشة «طلبات اليوم» (لتسجيل الوقت وإبلاغ العميل).' );
	$row( 'الاسم الأول', '<input name="zt_o[first_name]" value="' . esc_attr( $o['first_name'] ) . '">' );
	$row( 'الجوال', '<input dir="ltr" name="zt_o[phone]" value="' . esc_attr( $o['phone'] ) . '">' );
	$sv = '<select name="zt_o[service_id]"><option value="0">—</option>'; foreach ( zt_services_for_select() as $sid => $t ) { $sv .= '<option value="' . (int) $sid . '"' . selected( $o['service_id'], $sid, false ) . '>' . esc_html( $t ) . '</option>'; } $sv .= '</select>';
	$row( 'الخدمة', $sv . ' <input name="zt_o[service]" value="' . esc_attr( $o['service'] ) . '" placeholder="اسم الخدمة كما يظهر للعميل">' );
	$row( 'المدينة / الحي', '<input name="zt_o[city]" value="' . esc_attr( $o['city'] ) . '" placeholder="المدينة"> <input name="zt_o[hood]" value="' . esc_attr( $o['hood'] ) . '" placeholder="الحي">' );
	$row( 'الموعد', '<input type="date" name="zt_o[appt_date]" value="' . esc_attr( $o['appt_date'] ) . '"> <input type="time" name="zt_o[appt_time]" value="' . esc_attr( $o['appt_time'] ) . '"> <input name="zt_o[appt_period]" value="' . esc_attr( $o['appt_period'] ) . '" placeholder="صباحاً / مساءً" size="10">' );
	$tt = '<select name="zt_o[technician_id]"><option value="0">— لم يُعيَّن —</option>'; foreach ( $techs as $t ) { $tt .= '<option value="' . (int) $t->ID . '"' . selected( $o['technician_id'], $t->ID, false ) . '>' . esc_html( $t->post_title ) . '</option>'; } $tt .= '</select>';
	$row( 'الفني', $tt );
	$row( 'السعر النهائي (ريال)', '<input dir="ltr" class="small-text" name="zt_o[price]" value="' . esc_attr( $o['price'] ) . '">' );
	$pol = zt_order_policy_months( $o );
	$row( 'مدة الضمان (أشهر)', '<input dir="ltr" class="small-text" name="zt_o[warranty_months]" value="' . esc_attr( $o['warranty_months'] ?: '' ) . '"> <span class="description">' . ( $pol ? 'من سياسة الثيم لهذه الخدمة: ' . (int) $pol . ' (اتركه فارغاً لاعتمادها)' : 'الخدمة بلا مدة ثابتة في سياسة الثيم (مثل الصراصير: 3 أو 6 حسب الاتفاق): اكتب المدة المتفق عليها قبل «تمت الخدمة».' ) . '</span>' );
	if ( $o['warranty_code'] ) { $row( 'كود الضمان', '<code dir="ltr">' . esc_html( $o['warranty_code'] ) . '</code> — ينتهي ' . esc_html( $o['warranty_end'] ) ); }
	$row( 'رابط التتبع', '<code dir="ltr">' . esc_html( zt_track_url( $o['token'] ) ) . '</code>' );
	$row( 'ملاحظة', '<textarea name="zt_o[note]" rows="3" class="large-text">' . esc_textarea( $o['note'] ) . '</textarea>' );
	echo '</tbody></table>';
	if ( $o['log'] ) { echo '<h4>سجل الحالات</h4><ul>'; foreach ( array_reverse( $o['log'] ) as $l ) { echo '<li>' . esc_html( ( zt_order_statuses()[ $l[0] ] ?? $l[0] ) . ' — ' . wp_date( 'Y-m-d H:i', (int) $l[1] ) ) . '</li>'; } echo '</ul>'; }
	$fb = (array) get_post_meta( $post->ID, '_zt_feedback', true );
	if ( $fb ) { echo '<h4>ملاحظات العميل</h4><ul>'; foreach ( $fb as $f ) { echo '<li>' . esc_html( $f['text'] ) . ' <small>(' . esc_html( wp_date( 'Y-m-d', (int) $f['at'] ) ) . ')</small></li>'; } echo '</ul>'; }
}

add_action( 'save_post_zad_order', function ( $id ) {
	if ( ! isset( $_POST['zt_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zt_order_nonce'] ) ), 'zt_order_box' ) || ! current_user_can( 'manage_options' ) || wp_is_post_autosave( $id ) ) { return; }
	$in = isset( $_POST['zt_o'] ) ? (array) wp_unslash( $_POST['zt_o'] ) : array();
	$set = function ( $k, $v ) use ( $id ) { update_post_meta( $id, '_zt_' . $k, $v ); };
	$set( 'first_name', mb_substr( sanitize_text_field( $in['first_name'] ?? '' ), 0, 60 ) );
	$ph = zt_normalize_phone( $in['phone'] ?? '' ); if ( '' !== $ph ) { $set( 'phone', $ph ); }
	$set( 'service_id', absint( $in['service_id'] ?? 0 ) ); $set( 'service', mb_substr( sanitize_text_field( $in['service'] ?? '' ), 0, 160 ) );
	$set( 'city', mb_substr( sanitize_text_field( $in['city'] ?? '' ), 0, 80 ) ); $set( 'hood', mb_substr( sanitize_text_field( $in['hood'] ?? '' ), 0, 120 ) );
	$set( 'appt_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['appt_date'] ?? '' ) ? $in['appt_date'] : '' ); $set( 'appt_time', preg_match( '/^\d{2}:\d{2}$/', $in['appt_time'] ?? '' ) ? $in['appt_time'] : '' );
	$set( 'appt_period', mb_substr( sanitize_text_field( $in['appt_period'] ?? '' ), 0, 30 ) );
	$set( 'technician_id', absint( $in['technician_id'] ?? 0 ) );
	$pr = zt_num( $in['price'] ?? '' ); $set( 'price', null === $pr ? '' : $pr );
	$wm = zt_num( $in['warranty_months'] ?? '' ); $set( 'warranty_months', ( null !== $wm && $wm >= 1 && $wm <= 240 ) ? (int) $wm : '' );
	$set( 'note', sanitize_textarea_field( $in['note'] ?? '' ) );
} );

function zt_tech_box( $post ) {
	wp_nonce_field( 'zt_tech_box', 'zt_tech_nonce' );
	$g = function ( $k ) use ( $post ) { return get_post_meta( $post->ID, '_zt_' . $k, true ); };
	echo '<table class="form-table"><tbody><tr><th>سنوات الخبرة</th><td><input dir="ltr" class="small-text" name="zt_t[years]" value="' . esc_attr( $g( 'years' ) ) . '"></td></tr>';
	echo '<tr><th>الخدمات التي ينفذها</th><td><textarea class="large-text" rows="3" name="zt_t[services]" placeholder="سطر لكل خدمة">' . esc_textarea( $g( 'services' ) ) . '</textarea></td></tr>';
	echo '<tr><th>حساب المستخدم (اختياري)</th><td>';
	wp_dropdown_users( array( 'name' => 'zt_t[user_id]', 'selected' => (int) $g( 'user_id' ), 'show_option_none' => '— بلا حساب —', 'option_none_value' => 0, 'role' => 'zad_technician' ) );
	echo '<p class="description">أنشئ مستخدماً بدور «فني زاد» وصِله هنا ليرى طلباته فقط ويحدّث حالتها. الصورة: «الصورة المميزة».</p></td></tr></tbody></table>';
}
add_action( 'save_post_zad_technician', function ( $id ) {
	if ( ! isset( $_POST['zt_tech_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zt_tech_nonce'] ) ), 'zt_tech_box' ) || ! current_user_can( 'manage_options' ) ) { return; }
	$in = isset( $_POST['zt_t'] ) ? (array) wp_unslash( $_POST['zt_t'] ) : array();
	$y = zt_num( $in['years'] ?? '' ); update_post_meta( $id, '_zt_years', null === $y ? '' : max( 0, (int) $y ) );
	update_post_meta( $id, '_zt_services', sanitize_textarea_field( $in['services'] ?? '' ) );
	update_post_meta( $id, '_zt_user_id', absint( $in['user_id'] ?? 0 ) );
} );

function zt_technician_card( $tid ) {
	if ( ! $tid || 'zad_technician' !== get_post_type( $tid ) ) { return array(); }
	$img = get_the_post_thumbnail_url( $tid, 'thumbnail' );
	return array( 'name' => get_the_title( $tid ), 'photo' => $img ? $img : '', 'years' => (int) get_post_meta( $tid, '_zt_years', true ), 'services' => array_values( array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $tid, '_zt_services', true ) ) ) ) ) );
}

/* ---------------------------------------------- admin: today's orders, quick order ---------------------------------------------- */

add_action( 'admin_menu', function () {
	if ( current_user_can( 'manage_options' ) ) {
		add_submenu_page( 'zad-tools', 'طلبات اليوم', 'طلبات اليوم', 'zt_orders', 'zad-tools-orders', 'zt_orders_screen' );
		add_submenu_page( 'zad-tools', 'طلب سريع', 'طلب سريع', 'manage_options', 'zad-tools-quick', 'zt_quick_screen' );
	} elseif ( current_user_can( 'zt_orders' ) ) {
		add_menu_page( 'طلباتي', 'طلباتي', 'zt_orders', 'zad-tools-orders', 'zt_orders_screen', 'dashicons-clipboard', 58 );
	}
}, 15 );

add_action( 'admin_post_zt_order_status', function () {
	if ( ! current_user_can( 'zt_orders' ) ) { wp_die( 'غير مسموح' ); }
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; check_admin_referer( 'zt_order_' . $id );
	$st = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$r  = zt_order_set_status( $id, $st, get_current_user_id() );
	$args = array( 'page' => 'zad-tools-orders' );
	if ( is_wp_error( $r ) ) { $args['err'] = rawurlencode( $r->get_error_message() ); } else { $args['notify'] = $id; $args['st'] = $st; }
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
} );

function zt_orders_screen() {
	if ( ! current_user_can( 'zt_orders' ) ) { return; }
	$tech = zt_current_technician_id(); $today = wp_date( 'Y-m-d' );
	$q = array( 'post_type' => 'zad_order', 'post_status' => 'publish', 'numberposts' => 80, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array( array( 'key' => '_zt_status', 'value' => array( 'done', 'cancelled' ), 'compare' => 'NOT IN' ) ) );
	if ( $tech ) { $q['meta_query'][] = array( 'key' => '_zt_technician_id', 'value' => max( 0, $tech ) ); }
	$orders = get_posts( $q ); $sts = zt_order_statuses();
	echo '<div class="wrap" dir="rtl"><h1>' . ( $tech ? 'طلباتي' : 'طلبات اليوم والطلبات المفتوحة' ) . '</h1>';
	if ( isset( $_GET['err'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ) ) . '</p></div>'; }
	if ( ! empty( $_GET['notify'] ) ) {
		$nid = absint( $_GET['notify'] ); $o = zt_order_get( $nid ); $wa = 'https://wa.me/' . zt_intl_phone( $o['phone'] ) . '?text=' . rawurlencode( zt_order_status_message( $nid ) );
		echo '<div class="notice notice-success"><p>تم تغيير الحالة إلى «' . esc_html( $sts[ $o['status'] ] ?? '' ) . '». <a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">أبلغ العميل على واتساب</a></p></div>';
	}
	echo '<style>.zt-o{border:1px solid #c3c4c7;background:#fff;border-radius:8px;padding:12px 14px;margin:0 0 12px;max-width:760px}.zt-o h3{margin:0 0 4px}.zt-o .meta{color:#50575e;margin:0 0 8px}.zt-o form{display:inline-block;margin:2px}.zt-o .button{min-height:40px}.zt-o .next{font-weight:700}</style>';
	if ( ! $orders ) { echo '<p><strong>لا توجد طلبات مفتوحة.</strong></p>'; }
	foreach ( $orders as $p ) {
		$o = zt_order_get( $p->ID ); $t = zt_technician_card( $o['technician_id'] ); $next = zt_order_next( $o['status'] );
		echo '<div class="zt-o"><h3>#' . (int) $o['id'] . ' — ' . esc_html( $o['service'] ?: 'طلب' ) . ' <small>(' . esc_html( $sts[ $o['status'] ] ?? '' ) . ')</small></h3><p class="meta">'
			. esc_html( $o['first_name'] ) . ' · <span dir="ltr">' . esc_html( $o['phone'] ) . '</span> · ' . esc_html( trim( $o['city'] . ' ' . ( $o['hood'] ? '/ ' . $o['hood'] : '' ) ) ) . ' · ' . esc_html( trim( $o['appt_date'] . ' ' . $o['appt_time'] . ' ' . $o['appt_period'] ) ?: 'بلا موعد' ) . ' · الفني: ' . esc_html( $t ? $t['name'] : '—' ) . '</p>';
		foreach ( array_diff( array_keys( $sts ), array( $o['status'] ) ) as $s ) {
			if ( $tech && ( ! in_array( $s, zt_order_flow(), true ) || array_search( $s, zt_order_flow(), true ) <= array_search( $o['status'], zt_order_flow(), true ) ) ) { continue; }
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'zt_order_' . $o['id'] );
			echo '<input type="hidden" name="action" value="zt_order_status"><input type="hidden" name="id" value="' . (int) $o['id'] . '"><input type="hidden" name="status" value="' . esc_attr( $s ) . '"><button class="button' . ( $s === $next ? ' button-primary next' : '' ) . '">' . esc_html( $sts[ $s ] ) . '</button></form>';
		}
		if ( ! $tech ) { echo ' <a href="' . esc_url( get_edit_post_link( $o['id'] ) ) . '">تفاصيل / تعيين فني</a>'; }
		echo '</div>';
	}
	echo '</div>';
}

add_action( 'admin_post_zt_quick_order', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zt_quick' );
	$sid = absint( $_POST['service_id'] ?? 0 ); $svcs = zt_services_for_select();
	$id = zt_order_create( array(
		'name' => wp_unslash( $_POST['name'] ?? '' ), 'phone' => wp_unslash( $_POST['phone'] ?? '' ), 'service_id' => $sid, 'service' => $svcs[ $sid ] ?? sanitize_text_field( wp_unslash( $_POST['service'] ?? '' ) ),
		'city' => wp_unslash( $_POST['city'] ?? '' ), 'hood' => wp_unslash( $_POST['hood'] ?? '' ), 'date' => wp_unslash( $_POST['date'] ?? '' ), 'clock' => wp_unslash( $_POST['clock'] ?? '' ),
		'time' => wp_unslash( $_POST['period'] ?? '' ), 'source' => 'manual', 'note' => wp_unslash( $_POST['note'] ?? '' ),
	) );
	wp_safe_redirect( is_wp_error( $id ) ? add_query_arg( array( 'page' => 'zad-tools-quick', 'err' => rawurlencode( $id->get_error_message() ) ), admin_url( 'admin.php' ) ) : admin_url( 'post.php?post=' . $id . '&action=edit' ) ); exit;
} );

function zt_quick_screen() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap" dir="rtl"><h1>طلب سريع (من واتساب أو التليفون)</h1>';
	if ( isset( $_GET['err'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ) ) . '</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'zt_quick' );
	echo '<input type="hidden" name="action" value="zt_quick_order"><table class="form-table"><tbody>';
	echo '<tr><th>الاسم الأول</th><td><input name="name" required></td></tr><tr><th>الجوال</th><td><input dir="ltr" name="phone" required></td></tr>';
	echo '<tr><th>الخدمة</th><td><select name="service_id"><option value="0">—</option>'; foreach ( zt_services_for_select() as $sid => $t ) { echo '<option value="' . (int) $sid . '">' . esc_html( $t ) . '</option>'; } echo '</select> <input name="service" placeholder="أو اكتب اسم الخدمة"></td></tr>';
	echo '<tr><th>المدينة / الحي</th><td><input name="city" placeholder="المدينة"> <input name="hood" placeholder="الحي"></td></tr>';
	echo '<tr><th>الموعد</th><td><input type="date" name="date"> <input type="time" name="clock"> <input name="period" placeholder="صباحاً / مساءً" size="10"></td></tr>';
	echo '<tr><th>ملاحظة</th><td><textarea name="note" rows="3" class="large-text"></textarea></td></tr></tbody></table>';
	submit_button( 'إنشاء الطلب' ); echo '</form></div>';
}

add_filter( 'manage_zad_order_posts_columns', function () { return array( 'cb' => '<input type="checkbox">', 'title' => 'الطلب', 'zt_status' => 'الحالة', 'zt_hood' => 'الحي', 'zt_appt' => 'الموعد', 'zt_code' => 'كود الضمان' ); } );
add_action( 'manage_zad_order_posts_custom_column', function ( $col, $id ) {
	$o = zt_order_get( $id );
	if ( 'zt_status' === $col ) { echo esc_html( zt_order_statuses()[ $o['status'] ] ?? '' ); }
	if ( 'zt_hood' === $col ) { echo esc_html( trim( $o['city'] . ' / ' . $o['hood'], ' /' ) ); }
	if ( 'zt_appt' === $col ) { echo esc_html( trim( $o['appt_date'] . ' ' . $o['appt_time'] ) ); }
	if ( 'zt_code' === $col ) { echo '<code dir="ltr">' . esc_html( $o['warranty_code'] ) . '</code>'; }
}, 10, 2 );
