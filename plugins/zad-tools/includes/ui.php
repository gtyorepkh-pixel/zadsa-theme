<?php defined( 'ABSPATH' ) || exit;
/**
 * Shared result renderer for the interactive tools (Phase 2 onward) — the PHP twin of ZT.resultHtml() in assets/js/zt-core.js.
 * Both turn the same "view" array/object into byte-identical HTML (tests/tools-parity.test.mjs), so the page the server prints
 * (no JS / shared link) and the result JS prints after a click are the same.
 *
 * view = array(
 *   'error' => 'text'                                   // alone: an inline error (role=alert)
 *   'badge' => 'تقديري', 'big' => 'main answer', 'lines' => array( … ),
 *   'table' => array( 'title' => '', 'head' => array( … ), 'rows' => array( array( … ) ) ),
 *   'notes' => array( … ), 'wa' => 'ready WhatsApp text', 'links' => array( array( 'href' => , 'label' => , 'event' => ) ),
 *   'optin' => array( 'date' => 'YYYY-MM-DD', 'service' => 'تنظيف خزان', 'title' => '' ),
 * )
 * env  = array( 'wa' => business number (digits), 'privacy' => url )
 */

function zt_esc( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }

/** wa.me URL: same bytes as ZT.waLink() (rawurlencode + the 5 characters JS leaves alone). */
function zt_wa_url( $number, $text ) {
	$n = preg_replace( '/\D+/', '', (string) $number );
	return '' === $n ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode( (string) $text );
}

function zt_result_html( $v, $env = array() ) {
	if ( ! empty( $v['error'] ) ) { return '<p class="zt-error" role="alert">' . zt_esc( $v['error'] ) . '</p>'; }
	$h = '<div class="zt-card" data-zt-ok="1">';
	if ( ! empty( $v['badge'] ) ) { $h .= '<span class="zt-est">' . zt_esc( $v['badge'] ) . '</span>'; }
	$h .= '<p class="zt-big">' . zt_esc( $v['big'] ?? '' ) . '</p>';
	if ( ! empty( $v['lines'] ) ) {
		$h .= '<ul class="zt-lines">';
		foreach ( $v['lines'] as $l ) { $h .= '<li>' . zt_esc( $l ) . '</li>'; }
		$h .= '</ul>';
	}
	if ( ! empty( $v['table']['rows'] ) ) {
		$t  = $v['table'];
		$h .= '<div class="zt-tblwrap">';
		if ( ! empty( $t['title'] ) ) { $h .= '<h3 class="zt-h3">' . zt_esc( $t['title'] ) . '</h3>'; }
		$h .= '<table class="zt-table"><thead><tr>';
		foreach ( $t['head'] as $c ) { $h .= '<th scope="col">' . zt_esc( $c ) . '</th>'; }
		$h .= '</tr></thead><tbody>';
		foreach ( $t['rows'] as $r ) {
			$h .= '<tr>';
			foreach ( $r as $i => $c ) { $h .= 0 === $i ? '<th scope="row">' . zt_esc( $c ) . '</th>' : '<td>' . zt_esc( $c ) . '</td>'; }
			$h .= '</tr>';
		}
		$h .= '</tbody></table></div>';
	}
	foreach ( (array) ( $v['notes'] ?? array() ) as $n ) { $h .= '<p class="zt-noteline">' . zt_esc( $n ) . '</p>'; }
	$acts = '';
	$wa   = ! empty( $v['wa'] ) ? zt_wa_url( $env['wa'] ?? '', $v['wa'] ) : '';
	if ( '' !== $wa ) { $acts .= '<a class="btn btn--wa" target="_blank" rel="noopener" data-zt-event="tool_whatsapp_click" href="' . zt_esc( $wa ) . '">أرسل النتيجة على واتساب</a>'; }
	foreach ( (array) ( $v['links'] ?? array() ) as $l ) {
		$acts .= '<a class="btn btn--ghost" href="' . zt_esc( $l['href'] ) . '"' . ( ! empty( $l['event'] ) ? ' data-zt-event="' . zt_esc( $l['event'] ) . '"' : '' ) . '>' . zt_esc( $l['label'] ) . '</a>';
	}
	if ( '' !== $acts ) { $h .= '<div class="zt-actions">' . $acts . '</div>'; }
	if ( ! empty( $v['optin'] ) ) { $h .= zt_optin_html( $v['optin'], $env ); }
	return $h . '</div>';
}

/** The reminder opt-in. Hidden until JS shows it (posting needs the cache-safe token), unchecked consent, honeypot. */
function zt_optin_html( $o, $env = array() ) {
	$h  = '<form class="zt-optin zt-form" data-zt-optin data-date="' . zt_esc( $o['date'] ) . '" data-service="' . zt_esc( $o['service'] ) . '" hidden novalidate>';
	$h .= '<h3 class="zt-h3">' . zt_esc( $o['title'] ?? 'ذكّرني بالموعد' ) . '</h3>';
	$h .= '<div class="zt-row"><label class="fld"><span>اسمك الأول</span><input name="first_name" autocomplete="given-name" maxlength="60"></label>';
	$h .= '<label class="fld"><span>رقم الجوال</span><input name="phone" type="tel" inputmode="tel" dir="ltr" autocomplete="tel" placeholder="05xxxxxxxx"></label></div>';
	$h .= '<label class="fld"><span>الإيميل (اختياري)</span><input name="email" type="email" dir="ltr" autocomplete="email"></label>';
	$h .= '<div class="zt-hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input name="website" tabindex="-1" autocomplete="off"></label></div>';
	$priv = ! empty( $env['privacy'] ) ? ' <a href="' . zt_esc( $env['privacy'] ) . '" target="_blank" rel="noopener">سياسة الخصوصية</a>' : '';
	$h .= '<label class="zt-consent"><input type="checkbox" name="consent" value="1"><span>أوافق على أن تتواصل معي زاد برسالة تذكير بهذا الموعد فقط، ويمكنني إيقاف التذكير في أي وقت.' . $priv . '</span></label>';
	$h .= '<button class="btn btn--accent" type="submit">فعّل التذكير</button><p class="zt-optin__msg" role="status" aria-live="polite"></p></form>';
	return $h;
}

/** Digits only helper for query values typed with Arabic numerals (GET fallback). */
function zt_req( $key, $default = '' ) {
	if ( ! isset( $_GET[ $key ] ) ) { return $default; }
	$v = wp_unslash( $_GET[ $key ] );
	return is_array( $v ) ? $default : trim( sanitize_text_field( zt_digits_en( (string) $v ) ) );
}
function zt_req_num( $key ) { return isset( $_GET[ $key ] ) ? zt_num( is_array( $_GET[ $key ] ) ? '' : wp_unslash( $_GET[ $key ] ) ) : null; }
function zt_has_req( $keys ) { foreach ( (array) $keys as $k ) { if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { return true; } } return false; }

/* ------------------------------------------------------------------------------------------------------------------
 * Small Arabic helpers shared by the tools (JS twins in zt-core.js).
 * ---------------------------------------------------------------------------------------------------------------- */

/** Arabic counted noun: 1 → one, 2 → two, 3–10 → few, else many.  zt_ar_count( 3, 'ساعة', 'ساعتان', 'ساعات', 'ساعة' ) */
function zt_ar_count( $n, $one, $two, $few, $many, $one_alone = null ) {
	$n = (float) $n;
	if ( 1.0 === $n ) { return null === $one_alone ? $one : $one_alone; }
	if ( 2.0 === $n ) { return $two; }
	$s = zt_fmt( $n );
	if ( $n >= 3 && $n <= 10 && floor( $n ) === $n ) { return $s . ' ' . $few; }
	return $s . ' ' . $many;
}

/** Signed percent for tables: +10% / −5% / 0% */
function zt_pct_label( $p ) {
	$p = (float) $p;
	if ( $p > 0 ) { return '+' . zt_fmt( $p ) . '%'; }
	if ( $p < 0 ) { return "\u{2212}" . zt_fmt( -$p ) . '%'; }
	return '0%';
}

/** Whole days in a month; integers only so JS and PHP can never differ. */
function zt_days_in_month( $y, $m ) {
	$d = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	if ( 2 === $m && ( ( 0 === $y % 4 && 0 !== $y % 100 ) || 0 === $y % 400 ) ) { return 29; }
	return $d[ $m - 1 ];
}
/** 'YYYY-MM-DD' + n months (day clamped to the month's end). '' for an invalid date. */
function zt_add_months_str( $ymd, $n ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) ) { return ''; }
	$y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
	if ( $mo < 1 || $mo > 12 || $d < 1 || $d > zt_days_in_month( $y, $mo ) ) { return ''; }
	$t  = $y * 12 + ( $mo - 1 ) + (int) $n;
	$y2 = intdiv( $t, 12 ); $m2 = $t % 12 + 1;
	$d2 = min( $d, zt_days_in_month( $y2, $m2 ) );
	return sprintf( '%04d-%02d-%02d', $y2, $m2, $d2 );
}
function zt_ar_date( $ymd ) {
	static $mn = array( 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) ) { return ''; }
	return (int) $m[3] . ' ' . $mn[ (int) $m[2] - 1 ] . ' ' . $m[1];
}

/* ------------------------------------------------------------------------------------------------------------------
 * Calendar file (.ics): built on the server, no personal data in it (title + date only).  GET  /?zad_ics=1&d=YYYY-MM-DD&t=عنوان
 * ---------------------------------------------------------------------------------------------------------------- */

function zt_ics_escape( $s ) { return str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\;', '\,', '\n', '\n', '\n' ), (string) $s ); }

/** One all-day VEVENT with a reminder the day before at 09:00. $stamp = 'YYYYMMDDTHHMMSSZ' (injected so tests are exact). */
function zt_ics_build( $date, $title, $desc, $stamp, $host = 'zadksa.com' ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date ) ) { return ''; }
	$d0   = str_replace( '-', '', $date );
	$next = zt_add_days_str( $date, 1 );
	if ( '' === $next ) { return ''; }
	$lines = array(
		'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Zad//zad-tools//AR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
		'BEGIN:VEVENT', 'UID:' . md5( $date . '|' . $title ) . '@' . $host, 'DTSTAMP:' . $stamp,
		'DTSTART;VALUE=DATE:' . $d0, 'DTEND;VALUE=DATE:' . str_replace( '-', '', $next ),
		'SUMMARY:' . zt_ics_escape( $title ),
	);
	if ( '' !== (string) $desc ) { $lines[] = 'DESCRIPTION:' . zt_ics_escape( $desc ); }
	$lines = array_merge( $lines, array( 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . zt_ics_escape( $title ), 'TRIGGER:-PT15H', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR' ) );
	$out = array();
	foreach ( $lines as $l ) { $out[] = zt_ics_fold( $l ); } // RFC 5545: lines folded at 75 octets
	return implode( "\r\n", $out ) . "\r\n";
}
function zt_ics_fold( $line ) {
	if ( strlen( $line ) <= 75 ) { return $line; }
	$out = ''; $cur = ''; $first = true;
	foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$lim = $first ? 75 : 74;
		if ( strlen( $cur ) + strlen( $ch ) > $lim ) { $out .= ( $first ? '' : "\r\n " ) . $cur; $cur = ''; $first = false; }
		$cur .= $ch;
	}
	return $out . ( $first ? '' : "\r\n " ) . $cur;
}
function zt_add_days_str( $ymd, $n ) {
	if ( '' === zt_add_months_str( $ymd, 0 ) ) { return ''; } // invalid date
	preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m );
	$y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3] + (int) $n;
	while ( $d > zt_days_in_month( $y, $mo ) ) { $d -= zt_days_in_month( $y, $mo ); $mo++; if ( $mo > 12 ) { $mo = 1; $y++; } }
	return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
}

/** The public download. Cache-safe (no-store), nothing personal, the title is cut at 80 characters. */
function zt_ics_url( $date, $title ) {
	return home_url( '/' ) . '?' . zt_qs_string( array( 'd' => $date, 't' => $title, 'zad_ics' => '1' ) );
}
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['zad_ics'] ) ) { return; }
	$d = isset( $_GET['d'] ) ? sanitize_text_field( wp_unslash( $_GET['d'] ) ) : '';
	$t = isset( $_GET['t'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['t'] ) ), 0, 80 ) : '';
	$ics = '' !== $t ? zt_ics_build( $d, $t, zt_brand(), gmdate( 'Ymd\THis\Z' ), wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'zadksa.com' ) : '';
	if ( '' === $ics ) { status_header( 400 ); exit; }
	nocache_headers();
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="zad-reminder.ics"' );
	header( 'X-Robots-Tag: noindex' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );
