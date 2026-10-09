<?php defined( 'ABSPATH' ) || exit;
/**
 * Reminders engine (shared by the tank calculator, the maintenance plan and the order system).
 *
 * Table wp_zad_reminders. Channels (adapters): the admin queue (default, needs no API), e-mail, and a WhatsApp Business API
 * adapter that is only an interface for now. A daily WP-Cron job sends the e-mail ones and anonymises old rows (retention).
 * Consent is explicit and stored with its time; every message carries an unsubscribe link.
 */

function zt_reminders_table() { global $wpdb; return $wpdb->prefix . 'zad_reminders'; }

function zt_reminders_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t  = zt_reminders_table();
	$cs = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $t (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		first_name varchar(80) NOT NULL DEFAULT '',
		phone varchar(20) NOT NULL DEFAULT '',
		email varchar(120) NOT NULL DEFAULT '',
		service varchar(160) NOT NULL DEFAULT '',
		due_date date NOT NULL,
		source_tool varchar(60) NOT NULL DEFAULT '',
		hood varchar(120) NOT NULL DEFAULT '',
		consent_at datetime DEFAULT NULL,
		consent_ver varchar(20) NOT NULL DEFAULT '',
		status varchar(12) NOT NULL DEFAULT 'pending',
		token char(32) NOT NULL DEFAULT '',
		ref varchar(40) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		sent_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY due_status (due_date,status),
		KEY phone (phone),
		UNIQUE KEY token (token)
	) $cs;" );
}

/** Add a reminder. Returns the new id, or a WP_Error with a user-safe message. Consent is mandatory. */
function zt_reminder_add( $d ) {
	global $wpdb;
	$name  = preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) ( $d['first_name'] ?? '' ) ) );
	$name  = mb_substr( $name, 0, 60 );
	$phone = zt_normalize_phone( $d['phone'] ?? '' );
	$mail  = sanitize_email( (string) ( $d['email'] ?? '' ) );
	$svc   = mb_substr( sanitize_text_field( (string) ( $d['service'] ?? '' ) ), 0, 160 );
	$tool  = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $d['tool'] ?? '' ) ) );
	$hood  = mb_substr( sanitize_text_field( (string) ( $d['hood'] ?? '' ) ), 0, 120 );
	$due   = (string) ( $d['due_date'] ?? '' );
	$trans = ! empty( $d['transactional'] ); // a follow-up to a service the person ordered (the single review request): needs no marketing consent
	if ( ! $trans && empty( $d['consent'] ) ) { return new WP_Error( 'consent', 'يلزم الموافقة على استلام التذكير.' ); }
	if ( mb_strlen( $name ) < 2 ) { return new WP_Error( 'name', 'اكتب اسمك الأول.' ); }
	if ( '' === $phone ) { return new WP_Error( 'phone', 'رقم الجوال غير صحيح.' ); }
	if ( '' !== $mail && ! is_email( $mail ) ) { $mail = ''; }
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) || ! strtotime( $due ) ) { return new WP_Error( 'due', 'تاريخ التذكير غير صحيح.' ); }
	$today = wp_date( 'Y-m-d' );
	if ( $due < $today || $due > wp_date( 'Y-m-d', strtotime( '+' . max( 1, (int) zt_opt( 'general.reminder_max_years' ) ) . ' years' ) ) ) { return new WP_Error( 'due', 'اختر تاريخاً قادماً.' ); }
	$t   = zt_reminders_table();
	$cap = (int) zt_opt( 'general.reminder_max_per_phone' );
	if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE phone=%s AND status='pending'", $phone ) ) >= $cap ) { return new WP_Error( 'cap', 'وصلت للحد الأقصى من التذكيرات لهذا الرقم.' ); }
	$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE phone=%s AND source_tool=%s AND due_date=%s AND service=%s AND status='pending' LIMIT 1", $phone, $tool, $due, $svc ) );
	if ( $dup ) { return $dup; } // already set: confirm, never a second row
	$ok = $wpdb->insert( $t, array(
		'first_name' => $name, 'phone' => $phone, 'email' => $mail, 'service' => $svc, 'due_date' => $due, 'source_tool' => $tool, 'hood' => $hood,
		'consent_at' => $trans ? null : current_time( 'mysql' ), 'consent_ver' => $trans ? 'service' : 'v1', 'ref' => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) ( $d['ref'] ?? '' ) ) ), 'status' => 'pending', 'token' => bin2hex( random_bytes( 16 ) ), 'created_at' => current_time( 'mysql' ),
	) );
	return $ok ? (int) $wpdb->insert_id : new WP_Error( 'db', 'تعذر الحفظ، حاول لاحقاً.' );
}

function zt_unsub_url( $token ) { return add_query_arg( 'zad_unsub', $token, home_url( '/' ) ); }

/** Message text from the template setting. */
function zt_reminder_message( $r, $template = null ) {
	if ( null === $template ) {
		$src = isset( $r->source_tool ) ? $r->source_tool : '';
		$template = 'orders-review' === $src ? (string) zt_opt( 'orders.review_msg' ) : ( 'orders-warranty' === $src ? (string) zt_opt( 'orders.warranty_msg' ) : (string) zt_opt( 'reminders.msg_template' ) );
	}
	$ref = isset( $r->ref ) ? (string) $r->ref : '';
	return strtr( $template, array(
		'{الاسم}' => $r->first_name, '{الخدمة}' => $r->service, '{المنشأة}' => zt_brand(), '{الحي}' => $r->hood, '{رابط_الإيقاف}' => zt_unsub_url( $r->token ),
		'{رابط_التتبع}' => ( '' !== $ref && function_exists( 'zt_track_url' ) ) ? zt_track_url( $ref ) : '', '{رابط_التقييم}' => function_exists( 'zt_review_url' ) ? zt_review_url() : '',
	) );
}

function zt_reminders_due( $limit = 200 ) {
	global $wpdb;
	$t = zt_reminders_table();
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status='pending' AND due_date <= %s ORDER BY due_date ASC, id ASC LIMIT %d", wp_date( 'Y-m-d' ), $limit ) );
}
function zt_reminders_due_count() {
	global $wpdb;
	$t = zt_reminders_table();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status='pending' AND due_date <= %s", wp_date( 'Y-m-d' ) ) );
}

/* ---------------------------------------- channels (adapters) ---------------------------------------- */

interface ZT_Reminder_Channel {
	public function id();
	public function label();
	public function enabled();
	/** @return true|WP_Error  true = delivered by the adapter itself. */
	public function send( $reminder, $message );
}

/** Default: nothing is sent by the server; the reminder waits in «تذكيرات اليوم» with a ready wa.me button. */
class ZT_Channel_Queue implements ZT_Reminder_Channel {
	public function id() { return 'queue'; }
	public function label() { return 'قائمة الإرسال في لوحة التحكم'; }
	public function enabled() { return true; }
	public function send( $r, $m ) { return new WP_Error( 'manual', 'يُرسل يدوياً من الشاشة.' ); }
}
class ZT_Channel_Email implements ZT_Reminder_Channel {
	public function id() { return 'email'; }
	public function label() { return 'إيميل'; }
	public function enabled() { return (bool) zt_opt( 'reminders.email_enabled' ); }
	public function send( $r, $m ) {
		if ( '' === $r->email ) { return new WP_Error( 'no_email', 'لا إيميل.' ); }
		$subject = strtr( (string) zt_opt( 'reminders.email_subject' ), array( '{المنشأة}' => zt_brand(), '{الخدمة}' => $r->service ) );
		return wp_mail( $r->email, $subject, $m, array( 'Content-Type: text/plain; charset=UTF-8' ) ) ? true : new WP_Error( 'mail', 'فشل إرسال الإيميل.' );
	}
}
/* ZT_Channel_WABA (WhatsApp Business API) lives in includes/waba.php: locked by a setting, needs a provider registered by filter and approved templates. */
function zt_channels() { return apply_filters( 'zad_tools_channels', array( new ZT_Channel_Queue(), new ZT_Channel_Email(), new ZT_Channel_WABA() ) ); }

/* ---------------------------------------- daily job ---------------------------------------- */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'zad_tools_daily' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zad_tools_daily' ); }
} );

/** Automatic channels (email, WhatsApp Business API when unlocked): returns how many reminders were delivered by an adapter. The queue is manual and is skipped. */
function zt_reminders_send_auto() {
	global $wpdb;
	$t = zt_reminders_table(); $sent = 0;
	foreach ( zt_channels() as $ch ) {
		if ( 'queue' === $ch->id() || ! $ch->enabled() ) { continue; }
		foreach ( zt_reminders_due( 'waba' === $ch->id() ? max( 1, (int) zt_opt( 'waba.max_per_run' ) ) : 100 ) as $r ) {
			if ( 'email' === $ch->id() && '' === $r->email ) { continue; }
			if ( true === $ch->send( $r, zt_reminder_message( $r ) ) ) {
				$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => (int) $r->id ) ); $sent++;
			}
		}
	}
	return $sent;
}

add_action( 'zad_tools_daily', function () {
	$sent = zt_reminders_send_auto();
	$anon = zt_reminders_retention();
	update_option( 'zad_tools_last_cron', array( 'at' => time(), 'sent' => $sent, 'anonymised' => $anon ), false );
} );

/** Retention: closed (sent / cancelled) rows older than the retention period, and pending rows whose date passed that long ago, lose every personal field. */
function zt_reminders_retention() {
	global $wpdb;
	$t   = zt_reminders_table();
	$m   = max( 1, (int) zt_opt( 'general.retention_months' ) );
	$cut = wp_date( 'Y-m-d H:i:s', strtotime( "-$m months" ) );
	$cutd = substr( $cut, 0, 10 );
	$n = (int) $wpdb->query( $wpdb->prepare( "UPDATE $t SET first_name='', phone='', email='', status='cancelled' WHERE (first_name<>'' OR phone<>'' OR email<>'') AND ((status IN ('sent','cancelled') AND COALESCE(sent_at,created_at) < %s) OR (status='pending' AND due_date < %s))", $cut, $cutd ) );
	return $n;
}

/* ---------------------------------------- admin screen: today's reminders ---------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'zad-tools', 'تذكيرات اليوم', 'تذكيرات اليوم', 'manage_options', 'zad-tools-reminders', 'zt_reminders_screen' );
}, 20 );

add_action( 'admin_post_zad_reminder_mark', function () {
	global $wpdb;
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'zad_reminder_' . $id );
	$do = isset( $_POST['do'] ) && 'cancel' === $_POST['do'] ? 'cancel' : 'sent';
	if ( 'cancel' === $do ) { $wpdb->update( zt_reminders_table(), array( 'status' => 'cancelled' ), array( 'id' => $id ) ); }
	else { $wpdb->update( zt_reminders_table(), array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=zad-tools-reminders&done=1' ) );
	exit;
} );

function zt_reminders_screen() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$t = zt_reminders_table(); $rows = zt_reminders_due();
	echo '<div class="wrap" dir="rtl"><h1>تذكيرات اليوم</h1><p>الأرقام تظهر لك أنت فقط. زر «واتساب» يفتح الرسالة جاهزة؛ بعد الإرسال اضغط «تم الإرسال».</p>';
	if ( isset( $_GET['done'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>تم.</p></div>'; }
	if ( ! $rows ) { echo '<p><strong>لا توجد تذكيرات مستحقة اليوم.</strong></p>'; }
	else {
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>الاسم</th><th>الجوال</th><th>الخدمة</th><th>الحي</th><th>الاستحقاق</th><th>الأداة</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$wa = 'https://wa.me/' . zt_intl_phone( $r->phone ) . '?text=' . rawurlencode( zt_reminder_message( $r ) );
			echo '<tr><td>' . esc_html( $r->first_name ) . '</td><td dir="ltr">' . esc_html( $r->phone ) . '</td><td>' . esc_html( $r->service ) . '</td><td>' . esc_html( $r->hood ) . '</td><td dir="ltr">' . esc_html( $r->due_date ) . '</td><td>' . esc_html( $r->source_tool ) . '</td><td>';
			echo '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">إرسال على واتساب</a> ';
			foreach ( array( 'sent' => 'تم الإرسال', 'cancel' => 'إلغاء' ) as $do => $lbl ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( 'zad_reminder_' . (int) $r->id );
				echo '<input type="hidden" name="action" value="zad_reminder_mark"><input type="hidden" name="id" value="' . (int) $r->id . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><button class="button">' . esc_html( $lbl ) . '</button></form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	$all = $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status" );
	echo '<p class="description">الإجمالي: ' . esc_html( implode( ' · ', array_map( function ( $x ) { return $x->status . '=' . $x->n; }, (array) $all ) ) ) . ' — آخر تشغيل للمهمة اليومية: ';
	$c = get_option( 'zad_tools_last_cron' ); echo $c ? esc_html( wp_date( 'Y-m-d H:i', $c['at'] ) . " (أُرسل {$c['sent']}، جُهّل {$c['anonymised']})" ) : 'لم تعمل بعد';
	echo '</p></div>';
}

/* ---------------------------------------- unsubscribe (a query var, no rewrite rule, noindex) ---------------------------------------- */

add_action( 'template_redirect', function () {
	if ( empty( $_GET['zad_unsub'] ) ) { return; }
	global $wpdb;
	$tok = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['zad_unsub'] ) );
	$t   = zt_reminders_table();
	$row = 32 === strlen( $tok ) ? $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, status FROM $t WHERE token=%s", $tok ) ) : null;
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	$msg = '';
	if ( $row && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // a click is not enough (mail scanners prefetch links): the person confirms
		$wpdb->update( $t, array( 'status' => 'cancelled', 'first_name' => '', 'phone' => '', 'email' => '' ), array( 'id' => (int) $row->id ) );
		$msg = '<p><strong>تم إيقاف التذكيرات وحذف بياناتك من قائمة التذكير.</strong></p>';
	} elseif ( $row && 'pending' === $row->status ) {
		$msg = '<p>هل تريد إيقاف تذكيرات زاد وحذف بياناتك منها؟</p><form method="post"><button class="btn btn--accent" type="submit">نعم، أوقف التذكيرات</button></form>';
	} else {
		$msg = '<p>هذا الرابط غير صالح أو التذكير موقوف بالفعل.</p>';
	}
	add_filter( 'wp_robots', function ( $r ) { $r['noindex'] = true; $r['nofollow'] = true; return $r; } );
	status_header( 200 );
	get_header();
	echo '<main id="main" class="sec"><div class="wrap wrap--narrow"><h1>إيقاف التذكيرات</h1>' . $msg . '</div></main>'; // phpcs:ignore WordPress.Security.EscapeOutput
	get_footer();
	exit;
}, 1 );

/* ---------------------------------------- privacy: policy text + export / erase by e-mail ---------------------------------------- */

add_action( 'admin_init', function () {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
	wp_add_privacy_policy_content( 'أدوات زاد (مسودة للمراجعة)', wp_kses_post( zt_privacy_draft_html() ) );
} );

function zt_privacy_draft_html() {
	$m = (int) zt_opt( 'general.retention_months' );
	return '<h3>أدوات زاد التفاعلية والتذكيرات</h3><p><em>مسودة — تُراجَع وتُعتمد قبل النشر.</em></p>'
		. '<p>تتيح بعض أدواتنا (مثل حاسبة الخزان وجدول الصيانة السنوي ونظام تتبع الطلب) أن تطلب تذكيراً بموعد خدمة قادمة أو أن نتتبع طلبك. عند ذلك نجمع: الاسم الأول، رقم الجوال، وإيميلك إن كتبته، والخدمة وتاريخ التذكير والحي الذي اخترته.</p>'
		. '<p>الغرض: إرسال التذكير الذي طلبته أو تنفيذ طلبك فقط، ولا نستخدمها للإعلان ولا نشاركها مع جهة أخرى.</p>'
		. '<p>الأساس: موافقتك الصريحة التي تعطيها بتحديد مربع الموافقة (غير محدد مسبقاً).</p>'
		. '<p>المدة: نحتفظ بها حتى ' . ( $m ? (int) $m : '…' ) . ' شهراً ثم تُحذف الأسماء والأرقام والإيميلات تلقائياً.</p>'
		. '<p>حقوقك: كل رسالة تذكير فيها رابط «إيقاف التذكيرات» يحذف بياناتك فوراً، ويمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها بمراسلتنا.</p>'
		. '<p>الحماية: تُحفظ البيانات على خادم الموقع، ولا يظهر رقم جوالك كاملاً في أي صفحة عامة.</p>';
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $e ) {
	$e['zad-tools-reminders'] = array( 'exporter_friendly_name' => 'تذكيرات أدوات زاد', 'callback' => function ( $email ) {
		global $wpdb; $t = zt_reminders_table(); $out = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email=%s", $email ) ) as $r ) {
			$out[] = array( 'group_id' => 'zad-reminders', 'group_label' => 'تذكيرات أدوات زاد', 'item_id' => 'rem-' . $r->id, 'data' => array(
				array( 'name' => 'الاسم', 'value' => $r->first_name ), array( 'name' => 'الجوال', 'value' => $r->phone ), array( 'name' => 'الخدمة', 'value' => $r->service ),
				array( 'name' => 'تاريخ التذكير', 'value' => $r->due_date ), array( 'name' => 'الحي', 'value' => $r->hood ), array( 'name' => 'وقت الموافقة', 'value' => (string) $r->consent_at ) ) );
		}
		return array( 'data' => $out, 'done' => true );
	} );
	return $e;
} );
add_filter( 'wp_privacy_personal_data_erasers', function ( $e ) {
	$e['zad-tools-reminders'] = array( 'eraser_friendly_name' => 'تذكيرات أدوات زاد', 'callback' => function ( $email ) {
		global $wpdb; $n = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . zt_reminders_table() . " SET first_name='', phone='', email='', status='cancelled' WHERE email=%s", $email ) );
		return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	} );
	return $e;
} );

/** After an update of the plugin files: bring the table up to date (dbDelta only adds). */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'zad_tools_version' ) !== ZT_VERSION ) { zt_reminders_install(); if ( function_exists( 'zt_report_install' ) ) { zt_report_install(); } update_option( 'zad_tools_version', ZT_VERSION, false ); }
} );
