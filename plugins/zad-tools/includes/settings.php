<?php defined( 'ABSPATH' ) || exit;
/**
 * One settings page «أدوات زاد» with a tab per tool.
 *
 * A field is declared once:  array( 'key' => 'retention_months', 'label' => …, 'type' => 'number', 'default' => 12,
 *                                   'approval' => true, 'source' => 'why this default' )
 * Rules (the order's §0.1): no business number lives in code. A default that is only a proposal is flagged «قيمة افتراضية — تحتاج اعتماد»
 * until a person ticks «اعتمدت» next to it; zt_tool_ready() is false while any field of the tool that is flagged is unapproved,
 * and the tool page stays unpublished-in-effect (notice + noindex) until then.
 *
 * Tools register their tab from their own file:   zt_register_settings( 'ac-size', 'حجم المكيف', array( …fields… ) );
 * Values are read with zt_opt( 'ac-size.base_btu_per_m2' ).
 */

/** @return array tab => array( 'title' => …, 'fields' => array key => field ) */
function zt_settings_registry() {
	global $zt_registry;
	if ( ! is_array( $zt_registry ) ) {
		$zt_registry = array();
		zt_register_settings( 'general', 'عام', array(
			array( 'key' => 'retention_months', 'label' => 'مدة الاحتفاظ ببيانات التذكيرات (شهور)', 'type' => 'number', 'default' => 12, 'min' => 1, 'max' => 120, 'approval' => true,
				'source' => 'مقترح في أمر التنفيذ §0.10 (12 شهراً) — يحتاج اعتماداً قانونياً', 'desc' => 'بعدها تُجهَّل الأسماء والأرقام والإيميلات تلقائياً.' ),
			array( 'key' => 'reminder_max_per_phone', 'label' => 'أقصى عدد تذكيرات معلّقة لكل رقم', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 50, 'approval' => true,
				'source' => 'حد أمان مقترح لمنع الإغراق — يحتاج اعتماداً' ),
			array( 'key' => 'rate_per_ip_hour', 'label' => 'أقصى طلبات تسجيل تذكير من نفس الجهاز في الساعة', 'type' => 'number', 'default' => 6, 'min' => 1, 'max' => 100, 'approval' => true,
				'source' => 'حد أمان تقني مقترح — يحتاج اعتماداً' ),
			array( 'key' => 'reminder_max_years', 'label' => 'أبعد تاريخ يُقبل للتذكير (سنوات من اليوم)', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 10, 'approval' => true,
				'source' => 'حد تحقق مقترح — يحتاج اعتماداً' ),
			array( 'key' => 'token_ttl_minutes', 'label' => 'صلاحية رمز الطلب (دقائق)', 'type' => 'number', 'default' => 30, 'min' => 5, 'max' => 240, 'approval' => true,
				'source' => 'قيمة تقنية للحماية من التزييف مع كاش LiteSpeed (لا علاقة لها بالأسعار أو الخدمات) — تُعتمد مع غيرها' ),
			array( 'key' => 'google_place_id', 'label' => 'Place ID في خرائط Google (لزر التقييم)', 'type' => 'text', 'default' => '', 'desc' => 'فارغ = لا يظهر زر التقييم.' ),
			array( 'key' => 'privacy_page_id', 'label' => 'صفحة سياسة الخصوصية', 'type' => 'page', 'default' => 0, 'desc' => 'فارغ = تُكتشف تلقائياً من الثيم (privacy-policy).' ),
			array( 'key' => 'ga_events', 'label' => 'إرسال أحداث GA4 للأدوات (بلا أي بيانات شخصية)', 'type' => 'checkbox', 'default' => 1 ),
			array( 'key' => 'tool_banner', 'label' => 'بانر موسمي اختياري (نص)', 'type' => 'text', 'default' => '', 'desc' => 'يظهر في أعلى صفحات الأدوات بين التاريخين.' ),
			array( 'key' => 'tool_banner_from', 'label' => 'البانر من (YYYY-MM-DD)', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'tool_banner_to', 'label' => 'البانر إلى (YYYY-MM-DD)', 'type' => 'text', 'default' => '' ),
		) );
		zt_register_settings( 'reminders', 'التذكيرات', array(
			array( 'key' => 'email_enabled', 'label' => 'إرسال التذكير بالإيميل (لمن كتب إيميله)', 'type' => 'checkbox', 'default' => 0, 'desc' => 'يحتاج بريداً يعمل على الاستضافة.' ),
			array( 'key' => 'email_subject', 'label' => 'عنوان إيميل التذكير', 'type' => 'text', 'default' => 'تذكير من {المنشأة}: {الخدمة}' ),
			array( 'key' => 'msg_template', 'label' => 'نص رسالة التذكير', 'type' => 'textarea', 'default' => "السلام عليكم {الاسم}، تذكير من {المنشأة}: حان موعد {الخدمة}. للحجز ردّ على هذه الرسالة.\nلإيقاف التذكيرات: {رابط_الإيقاف}",
				'desc' => 'المتغيرات: {الاسم} {الخدمة} {المنشأة} {الحي} {رابط_الإيقاف}' ),
		) );
		do_action( 'zad_tools_register_settings' ); // each tool module registers its own tab here
	}
	return $zt_registry;
}

function zt_register_settings( $tab, $title, $fields ) {
	global $zt_registry;
	if ( ! is_array( $zt_registry ) ) { $zt_registry = array(); }
	$zt_registry[ $tab ] = array( 'title' => $title, 'fields' => array() );
	foreach ( $fields as $f ) {
		$f += array( 'type' => 'text', 'default' => '', 'approval' => false, 'source' => '', 'desc' => '', 'required' => true );
		$zt_registry[ $tab ]['fields'][ $f['key'] ] = $f;
	}
}

function zt_field( $path ) {
	list( $tab, $key ) = array_pad( explode( '.', $path, 2 ), 2, '' );
	$r = zt_settings_registry();
	return $r[ $tab ]['fields'][ $key ] ?? null;
}

/** Saved value, else the declared default. $path = 'tab.key'. */
function zt_opt( $path, $fallback = null ) {
	$f = zt_field( $path );
	$o = get_option( 'zad_tools_opts', array() );
	if ( is_array( $o ) && array_key_exists( $path, $o ) ) { return $o[ $path ]; }
	if ( $f ) { return $f['default']; }
	return $fallback;
}

/** Has this value been explicitly approved by a person? */
function zt_is_approved( $path ) {
	$a = get_option( 'zad_tools_approved', array() );
	return is_array( $a ) && ! empty( $a[ $path ] );
}

/** Flagged and not yet approved → show the badge, block publishing. */
function zt_needs_approval( $path ) {
	$f = zt_field( $path );
	return $f && ! empty( $f['approval'] ) && ! zt_is_approved( $path );
}

/** Every flagged-unapproved field: array path => field (for the overview tab and for the phase reports). */
function zt_pending_approvals( $only_tab = '' ) {
	$out = array();
	foreach ( zt_settings_registry() as $tab => $t ) {
		if ( '' !== $only_tab && $tab !== $only_tab ) { continue; }
		foreach ( $t['fields'] as $k => $f ) {
			if ( zt_needs_approval( $tab . '.' . $k ) ) { $out[ $tab . '.' . $k ] = $f + array( 'tab' => $tab, 'tab_title' => $t['title'] ); }
		}
	}
	return $out;
}

/** A tool may be shown to visitors only when none of its required, flagged values is waiting for approval (and, when it declares them, none is empty). */
function zt_tool_ready( $tab ) {
	$r = zt_settings_registry();
	if ( ! isset( $r[ $tab ] ) ) { return true; }
	foreach ( $r[ $tab ]['fields'] as $k => $f ) {
		if ( empty( $f['required'] ) ) { continue; }
		$p = $tab . '.' . $k;
		if ( ! empty( $f['approval'] ) && ! zt_is_approved( $p ) ) { return false; }
		if ( ! empty( $f['must_fill'] ) && ( '' === (string) zt_opt( $p ) || null === zt_opt( $p ) ) ) { return false; }
	}
	return true;
}

function zt_sanitize_field( $f, $raw ) {
	switch ( $f['type'] ) {
		case 'number':
			$n = zt_num( $raw );
			if ( null === $n ) { return $f['default']; }
			if ( isset( $f['min'] ) ) { $n = max( $f['min'], $n ); }
			if ( isset( $f['max'] ) ) { $n = min( $f['max'], $n ); }
			return $n == (int) $n ? (int) $n : $n;
		case 'checkbox':
			return $raw ? 1 : 0;
		case 'page':
			return absint( $raw );
		case 'textarea':
		case 'table':
			return sanitize_textarea_field( wp_unslash( (string) $raw ) );
		case 'select':
			$v = sanitize_text_field( wp_unslash( (string) $raw ) );
			return isset( $f['options'][ $v ] ) ? $v : $f['default'];
	}
	return sanitize_text_field( wp_unslash( (string) $raw ) );
}

/* ====================================================== admin ====================================================== */

add_action( 'admin_menu', function () {
	$due = function_exists( 'zt_reminders_due_count' ) ? zt_reminders_due_count() : 0;
	add_menu_page( 'أدوات زاد', 'أدوات زاد' . ( $due ? ' <span class="awaiting-mod">' . (int) $due . '</span>' : '' ), 'manage_options', 'zad-tools', 'zt_settings_page', 'dashicons-calculator', 59 );
} );

add_action( 'admin_post_zad_tools_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'غير مسموح' ); }
	check_admin_referer( 'zad_tools_save' );
	$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
	$reg = zt_settings_registry();
	if ( isset( $reg[ $tab ] ) ) {
		$o = get_option( 'zad_tools_opts', array() ); $o = is_array( $o ) ? $o : array();
		$a = get_option( 'zad_tools_approved', array() ); $a = is_array( $a ) ? $a : array();
		foreach ( $reg[ $tab ]['fields'] as $k => $f ) {
			$p   = $tab . '.' . $k;
			$raw = isset( $_POST['f'][ $k ] ) ? wp_unslash( $_POST['f'][ $k ] ) : ( 'checkbox' === $f['type'] ? 0 : '' );
			$new = zt_sanitize_field( $f, $raw );
			$old = array_key_exists( $p, $o ) ? $o[ $p ] : $f['default'];
			$o[ $p ] = $new;
			if ( ! empty( $f['approval'] ) ) {
				$ok = ! empty( $_POST['approve'][ $k ] );
				if ( $ok && ( empty( $a[ $p ] ) || (string) $old !== (string) $new ) ) { $a[ $p ] = array( 'by' => get_current_user_id(), 'at' => time(), 'value' => $new ); }
				if ( ! $ok ) { unset( $a[ $p ] ); }
				if ( $ok && ! empty( $a[ $p ] ) ) { $a[ $p ]['value'] = $new; }
			}
		}
		update_option( 'zad_tools_opts', $o, false );
		update_option( 'zad_tools_approved', $a, false );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'zad-tools', 'tab' => $tab, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function zt_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$reg = zt_settings_registry();
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	$tabs = array( 'overview' => 'نظرة عامة' ) + wp_list_pluck( $reg, 'title' ) + array( 'theme' => 'قيم الثيم (للقراءة)' );
	$base = admin_url( 'admin.php?page=zad-tools' );
	echo '<div class="wrap" dir="rtl"><h1>أدوات زاد</h1>';
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>تم الحفظ.</p></div>'; }
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $t ) {
		$n = 'overview' === $k || 'theme' === $k ? 0 : count( zt_pending_approvals( $k ) );
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $t ) . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' ) . '</a>';
	}
	echo '</h2>';
	if ( 'overview' === $tab ) { zt_overview_tab(); }
	elseif ( 'theme' === $tab ) { zt_theme_tab(); }
	elseif ( isset( $reg[ $tab ] ) ) { zt_fields_tab( $tab, $reg[ $tab ] ); }
	echo '</div>';
}

function zt_badge( $path ) {
	return zt_needs_approval( $path ) ? ' <span style="background:#fff3cd;color:#664d03;border:1px solid #ffe69c;border-radius:4px;padding:1px 7px;font-size:12px">قيمة افتراضية — تحتاج اعتماد</span>' : ( zt_is_approved( $path ) ? ' <span style="color:#0a6b3d;font-size:12px">✓ معتمدة</span>' : '' );
}

function zt_fields_tab( $tab, $t ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'zad_tools_save' );
	echo '<input type="hidden" name="action" value="zad_tools_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><table class="form-table" role="presentation">';
	foreach ( $t['fields'] as $k => $f ) {
		$p = $tab . '.' . $k; $v = zt_opt( $p ); $n = 'f[' . esc_attr( $k ) . ']';
		echo '<tr><th scope="row"><label for="zt_' . esc_attr( $k ) . '">' . esc_html( $f['label'] ) . '</label>' . zt_badge( $p ) . '</th><td>';
		switch ( $f['type'] ) {
			case 'number':
				echo '<input type="text" inputmode="decimal" dir="ltr" class="small-text" id="zt_' . esc_attr( $k ) . '" name="' . $n . '" value="' . esc_attr( $v ) . '">'; break;
			case 'checkbox':
				echo '<label><input type="checkbox" id="zt_' . esc_attr( $k ) . '" name="' . $n . '" value="1"' . checked( (int) $v, 1, false ) . '> مفعّل</label>'; break;
			case 'textarea':
			case 'table':
				echo '<textarea class="large-text code" rows="' . ( 'table' === $f['type'] ? 8 : 4 ) . '" id="zt_' . esc_attr( $k ) . '" name="' . $n . '">' . esc_textarea( $v ) . '</textarea>'; break;
			case 'page':
				wp_dropdown_pages( array( 'name' => $n, 'id' => 'zt_' . $k, 'selected' => (int) $v, 'show_option_none' => '— لا شيء —', 'option_none_value' => 0 ) ); break;
			case 'select':
				echo '<select id="zt_' . esc_attr( $k ) . '" name="' . $n . '">'; foreach ( $f['options'] as $ov => $ol ) { echo '<option value="' . esc_attr( $ov ) . '"' . selected( $v, $ov, false ) . '>' . esc_html( $ol ) . '</option>'; } echo '</select>'; break;
			default:
				echo '<input type="text" class="regular-text" id="zt_' . esc_attr( $k ) . '" name="' . $n . '" value="' . esc_attr( $v ) . '">';
		}
		if ( '' !== $f['desc'] ) { echo '<p class="description">' . wp_kses_post( $f['desc'] ) . '</p>'; }
		if ( ! empty( $f['approval'] ) ) {
			echo '<p><label><input type="checkbox" name="approve[' . esc_attr( $k ) . ']" value="1"' . checked( zt_is_approved( $p ), true, false ) . '> <strong>اعتمدت هذه القيمة</strong></label>';
			if ( '' !== $f['source'] ) { echo ' <span class="description">— المصدر: ' . esc_html( $f['source'] ) . '</span>'; }
			echo '</p>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	submit_button( 'حفظ' );
	echo '</form>';
}

function zt_overview_tab() {
	$pend = zt_pending_approvals();
	echo '<p>كل قيمة افتراضية مقترحة (وليست من بيانات حقيقية) تظهر هنا حتى تعتمدها. الأداة المرتبطة بها لا تظهر للزوّار قبل الاعتماد.</p>';
	if ( ! $pend ) { echo '<p><strong>✓ لا توجد قيم تنتظر الاعتماد.</strong></p>'; }
	else {
		echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>التبويب</th><th>القيمة</th><th>الحالية</th><th>المصدر / سبب الاختيار</th></tr></thead><tbody>';
		foreach ( $pend as $p => $f ) {
			echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=zad-tools&tab=' . $f['tab'] ) ) . '">' . esc_html( $f['tab_title'] ) . '</a></td><td>' . esc_html( $f['label'] ) . '</td><td dir="ltr">' . esc_html( is_scalar( zt_opt( $p ) ) ? (string) zt_opt( $p ) : '…' ) . '</td><td>' . esc_html( $f['source'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<h3>صفحات الأدوات</h3>';
	$tools = function_exists( 'zt_tools' ) ? zt_tools() : array();
	if ( ! $tools ) { echo '<p>لا توجد أدوات مسجّلة بعد (تُضاف في المراحل التالية).</p>'; }
	else { echo '<ul>'; foreach ( $tools as $slug => $t ) { echo '<li>' . esc_html( $t['title'] ) . ' — ' . ( zt_tool_ready( $slug ) ? '<span style="color:#0a6b3d">جاهزة للنشر</span>' : '<span style="color:#b32d2e">بانتظار اعتماد قيمها</span>' ) . '</li>'; } echo '</ul>'; }
}

/** The values that already exist in the theme: shown for reading only, never copied (one source of truth). */
function zt_theme_tab() {
	if ( ! function_exists( 'zad_opt' ) ) { echo '<p>ثيم زاد غير مفعّل: لا توجد قيم للعرض.</p>'; return; }
	$edit = admin_url( 'admin.php?page=memo-theme-options' );
	$rows = array(
		'ساعات العمل' => array( function_exists( 'zad_hours_text' ) ? zad_hours_text() : '', $edit, 'خيارات الثيم ← أوقات العمل' ),
		'واتساب' => array( function_exists( 'zad_whatsapp' ) ? zad_whatsapp( 0 ) : '', $edit, 'خيارات الثيم ← واتساب' ),
		'الهاتف' => array( (string) zad_opt( 'memopt_phone' ), $edit, 'خيارات الثيم ← الهاتف' ),
		'السجل التجاري' => array( (string) zad_opt( 'zad_cr' ), $edit, 'خيارات الثيم ← بيانات الشركة' ),
		'الرقم الضريبي' => array( (string) zad_opt( 'zad_vat' ), $edit, 'خيارات الثيم ← بيانات الشركة' ),
		'الاسم النظامي' => array( (string) zad_opt( 'zad_legal_name' ), $edit, 'خيارات الثيم ← بيانات الشركة' ),
		'إحداثيات المنشأة' => array( trim( zad_opt( 'zad_lat' ) . ' , ' . zad_opt( 'zad_lng' ), ' ,' ), $edit, 'خيارات الثيم ← الموقع' ),
	);
	if ( function_exists( 'zad_warranty_summary' ) ) {
		foreach ( zad_warranty_summary() as $k => $txt ) { $rows[ 'الضمان — ' . $k ] = array( $txt, $edit, 'خيارات الثيم ← سياسة الضمان (inc/zad-warranty.php)' ); }
	}
	echo '<p>هذه القيم موجودة في الثيم وتُقرأ منه مباشرة؛ لا تُنسخ هنا. غيّرها من مكانها فتتحدث في كل الأدوات والشهادات.</p><table class="widefat striped" style="max-width:900px"><tbody>';
	foreach ( $rows as $l => $r ) { echo '<tr><th style="width:220px">' . esc_html( $l ) . '</th><td>' . ( '' !== $r[0] ? esc_html( $r[0] ) : '<em>—</em>' ) . '</td><td><a href="' . esc_url( $r[1] ) . '">' . esc_html( $r[2] ) . '</a></td></tr>'; }
	echo '</tbody></table>';
	// prices: read from each service page (packages first, then the old price rows) — never duplicated
	if ( function_exists( 'zad_service_types' ) && function_exists( 'zad_price_rows' ) && function_exists( 'zad_min_price' ) ) {
		$q = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'post_parent' => 0, 'numberposts' => 40, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<h3>الأسعار (تُقرأ من صفحة كل خدمة)</h3><table class="widefat striped" style="max-width:900px"><thead><tr><th>الخدمة</th><th>أقل سعر مقروء</th><th>مكان التعديل</th></tr></thead><tbody>';
		foreach ( $q as $p ) {
			$m = zad_min_price( zad_price_rows( $p->ID ) );
			echo '<tr><td>' . esc_html( get_the_title( $p ) ) . '</td><td>' . ( $m ? esc_html( number_format_i18n( $m ) ) . ' ريال' : '<em>بعد المعاينة</em>' ) . '</td><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">تعديل الصفحة (الباقات)</a></td></tr>';
		}
		echo '</tbody></table>';
	}
}
