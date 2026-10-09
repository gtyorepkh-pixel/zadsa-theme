<?php defined( 'ABSPATH' ) || exit;
/**
 * Image-based pest identification — a LOCKED feature of the identifier page. It does nothing (the section is not even printed) unless:
 *   «تفعيل» is ticked AND every flagged value of the tab is approved, a vision provider is chosen, and its API key constant is defined in wp-config.php
 *   (the key is never stored in the database).
 * Flow: the browser shrinks the photo (canvas → JPEG) → POST /zad/v1/pest-image (cache-safe token, honeypot, per-IP and daily limits, explicit consent to send it to the analysis
 * service) → the server checks the REAL file type by its bytes → the provider is asked to choose ONLY among the encyclopedia pests (ids) → the answer is validated against that list
 * (free text is impossible) → the file is deleted at once unless the visitor also ticked «احتفظ بالصورة». The answer is always «تقريبي» with «ابعتها لفني».
 */

interface ZT_Vision_Provider {
	public function id();
	public function label();
	public function key_constant();
	public function supports( $mime );
	/** @param string $bytes @param string $mime @param array $candidates id => 'name / scientific name' @return array|WP_Error  list of array( 'id' => int, 'confidence' => 0..1 ) */
	public function identify( $bytes, $mime, $candidates );
}

/** One HTTP POST, overridable (tests / a proxy). */
function zt_http_post( $url, $args ) {
	$o = apply_filters( 'zt_http_post_override', null, $url, $args );
	if ( null !== $o ) { return $o; }
	$r = wp_remote_post( $url, $args );
	return is_wp_error( $r ) ? $r : array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ) );
}

/** Reference adapter for the Claude Messages API (image + text). Needs ZAD_VISION_API_KEY in wp-config.php. NOT exercised against the live API in this build. */
class ZT_Vision_Claude implements ZT_Vision_Provider {
	public function id() { return 'claude'; }
	public function label() { return 'Claude (Anthropic)'; }
	public function key_constant() { return 'ZAD_VISION_API_KEY'; }
	public function supports( $mime ) { return in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ); }
	public function identify( $bytes, $mime, $candidates ) {
		$list = array(); foreach ( $candidates as $id => $name ) { $list[] = $id . ': ' . $name; }
		$prompt = "You identify household pests in a photo. Choose ONLY from this list (id: name):\n" . implode( "\n", $list ) . "\nReturn JSON only, no prose: {\"matches\":[{\"id\":<id from the list>,\"confidence\":<0 to 1>}]}. If nothing in the list fits, return {\"matches\":[]}. Never invent ids.";
		$body = array( 'model' => (string) zt_opt( 'pest-image.model' ), 'max_tokens' => 300, 'messages' => array( array( 'role' => 'user', 'content' => array(
			array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $mime, 'data' => base64_encode( $bytes ) ) ), array( 'type' => 'text', 'text' => $prompt ) ) ) ) );
		$r = zt_http_post( 'https://api.anthropic.com/v1/messages', array( 'timeout' => 25, 'headers' => array( 'x-api-key' => (string) constant( $this->key_constant() ), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $r ) || (int) ( $r['code'] ?? 0 ) < 200 || (int) $r['code'] >= 300 ) { return new WP_Error( 'provider', 'provider error' ); }
		$j = json_decode( (string) $r['body'], true ); $txt = '';
		foreach ( (array) ( $j['content'] ?? array() ) as $c ) { if ( 'text' === ( $c['type'] ?? '' ) ) { $txt .= (string) $c['text']; } }
		return zt_vision_parse( $txt );
	}
}
/** The first JSON object found in a model reply → array( array( id, confidence ) ) or WP_Error. */
function zt_vision_parse( $txt ) {
	if ( ! preg_match( '/\{.*\}/s', (string) $txt, $m ) ) { return new WP_Error( 'parse', 'no json' ); }
	$j = json_decode( $m[0], true );
	if ( ! is_array( $j ) || ! isset( $j['matches'] ) || ! is_array( $j['matches'] ) ) { return new WP_Error( 'parse', 'bad json' ); }
	$out = array(); foreach ( $j['matches'] as $x ) { if ( is_array( $x ) && isset( $x['id'], $x['confidence'] ) && is_numeric( $x['id'] ) && is_numeric( $x['confidence'] ) ) { $out[] = array( 'id' => (int) $x['id'], 'confidence' => (float) $x['confidence'] ); } }
	return $out;
}
function zt_vision_providers() { $o = array(); foreach ( (array) apply_filters( 'zt_vision_providers', array( new ZT_Vision_Claude() ) ) as $p ) { if ( $p instanceof ZT_Vision_Provider ) { $o[ $p->id() ] = $p; } } return $o; }

/* ---------------------------------------------- settings ---------------------------------------------- */

add_action( 'zad_tools_register_settings', function () {
	$ps = array( '' => '— اختر —' ); foreach ( zt_vision_providers() as $id => $p ) { $ps[ $id ] = $p->label(); }
	$f = 'قيمة مقترحة من عندي — تحتاج اعتماداً';
	zt_register_settings( 'pest-image', 'المعرّف بالصورة (مقفول)', array(
		array( 'key' => 'enabled', 'label' => 'تفعيل المعرّف بالصورة', 'type' => 'checkbox', 'default' => 0, 'approval' => true, 'source' => 'قرار منك بعد اختبار المزود؛ ترسل الصورة إلى خدمة تحليل خارجية (يُذكر ذلك للزائر ويوافق صراحة)' ),
		array( 'key' => 'provider', 'label' => 'مزود التحليل', 'type' => 'select', 'options' => $ps, 'default' => '', 'required' => false, 'desc' => 'المفتاح يوضع في wp-config.php كثابت <code>ZAD_VISION_API_KEY</code> ولا يُحفظ في القاعدة.' ),
		array( 'key' => 'model', 'label' => 'اسم النموذج لدى المزود', 'type' => 'text', 'default' => 'claude-sonnet-5-5', 'approval' => true, 'source' => 'الاسم الحالي للنموذج المقترح؛ تأكد منه في لوحة المزود' ),
		array( 'key' => 'max_mb', 'label' => 'أقصى حجم للصورة (MB)', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 20, 'source' => 'من أمر التنفيذ (5MB)' ),
		array( 'key' => 'max_px', 'label' => 'أقصى بُعد للصورة بعد الضغط في المتصفح (بكسل)', 'type' => 'number', 'default' => 1600, 'min' => 400, 'max' => 4000, 'approval' => true, 'source' => $f ),
		array( 'key' => 'per_ip_hour', 'label' => 'أقصى محاولات من نفس الجهاز في الساعة', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 50, 'approval' => true, 'source' => $f . ' (حد صارم لضبط التكلفة)' ),
		array( 'key' => 'daily_cap', 'label' => 'أقصى تحليلات في اليوم لكل الزوار', 'type' => 'number', 'default' => 50, 'min' => 1, 'max' => 5000, 'approval' => true, 'source' => $f . ' (سقف تكلفة)' ),
		array( 'key' => 'min_conf', 'label' => 'أقل ثقة (%) لإظهار حشرة في النتيجة', 'type' => 'number', 'default' => 50, 'min' => 1, 'max' => 100, 'approval' => true, 'source' => $f ),
	) );
} );

function zt_img_provider() { $ps = zt_vision_providers(); return $ps[ (string) zt_opt( 'pest-image.provider' ) ] ?? null; }
/** Is the feature unlocked? (everything must hold) */
function zt_img_available() {
	if ( ! zt_opt( 'pest-image.enabled' ) || ! zt_tool_ready( 'pest-image' ) ) { return false; }
	$p = zt_img_provider(); $c = $p ? $p->key_constant() : '';
	return $p && '' !== $c && defined( $c ) && '' !== (string) constant( $c );
}

/* ---------------------------------------------- the pure-ish core ---------------------------------------------- */

/** Real type from the first bytes (never trust the browser's mime / the extension). null = not allowed. */
function zt_img_sniff( $head ) {
	if ( 0 === strncmp( $head, "\xFF\xD8\xFF", 3 ) ) { return 'image/jpeg'; }
	if ( 0 === strncmp( $head, "\x89PNG\r\n\x1A\n", 8 ) ) { return 'image/png'; }
	if ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) ) { return 'image/webp'; }
	if ( 'ftyp' === substr( $head, 4, 4 ) && in_array( substr( $head, 8, 4 ), array( 'heic', 'heix', 'mif1', 'msf1', 'heim', 'heis' ), true ) ) { return 'image/heic'; }
	return null;
}

/** Candidates for the provider: id => 'الاسم / scientific'. Only published pests that have a page. */
function zt_img_candidates( $pests ) { $o = array(); foreach ( $pests as $p ) { $o[ (int) $p['id'] ] = $p['n'] . ( ! empty( $p['sci'] ) ? ' / ' . $p['sci'] : '' ); } return $o; }

/**
 * Analyse one uploaded file. $f = array( 'tmp' => path, 'size' => bytes ). $opts = array( max_bytes, min_conf (0..100), max_results, keep (bool), store_dir ).
 * The temporary file is ALWAYS removed (or moved to the private folder when keep is true). Returns array( array( id, pct ) ) or WP_Error with a user-safe message.
 */
function zt_img_process( $f, $provider, $candidates, $opts ) {
	$tmp = (string) ( $f['tmp'] ?? '' ); $keep = ! empty( $opts['keep'] ); $kept = false;
	try {
		if ( '' === $tmp || ! is_readable( $tmp ) ) { return new WP_Error( 'file', 'لم يصلنا ملف صالح.' ); }
		if ( (int) ( $f['size'] ?? filesize( $tmp ) ) > (int) $opts['max_bytes'] || filesize( $tmp ) > (int) $opts['max_bytes'] ) { return new WP_Error( 'size', 'الصورة أكبر من الحجم المسموح.' ); }
		$bytes = (string) file_get_contents( $tmp ); $mime = zt_img_sniff( substr( $bytes, 0, 16 ) );
		if ( null === $mime ) { return new WP_Error( 'type', 'نوع الملف غير مدعوم (jpg أو png أو webp أو heic).' ); }
		if ( ! $provider->supports( $mime ) ) { return new WP_Error( 'type', 'هذا النوع غير مدعوم في التحليل؛ أرسل الصورة بصيغة JPG.' ); }
		if ( ! $candidates ) { return new WP_Error( 'cfg', 'لا حشرات في الموسوعة للمطابقة.' ); }
		$r = $provider->identify( $bytes, $mime, $candidates );
		if ( $keep && ! is_wp_error( $r ) && ! empty( $opts['store_dir'] ) ) { $kept = zt_img_keep( $tmp, $opts['store_dir'], $mime ); }
		if ( is_wp_error( $r ) || ! is_array( $r ) ) { return new WP_Error( 'provider', 'تعذر تحليل الصورة الآن، أرسلها لفنيينا على واتساب.' ); }
		$out = array(); $seen = array();
		foreach ( $r as $x ) {
			$id = (int) ( $x['id'] ?? 0 ); $c = (float) ( $x['confidence'] ?? 0 );
			if ( ! isset( $candidates[ $id ] ) || isset( $seen[ $id ] ) || $c < 0 || $c > 1 ) { continue; } // an id outside the list (or a bad number) is dropped: free text is impossible
			$pct = (int) floor( $c * 100 + 0.5 ); if ( $pct < (int) $opts['min_conf'] ) { continue; }
			$seen[ $id ] = 1; $out[] = array( 'id' => $id, 'pct' => $pct );
		}
		usort( $out, function ( $a, $b ) { return ( $b['pct'] <=> $a['pct'] ) ?: ( $a['id'] <=> $b['id'] ); } );
		return array_slice( $out, 0, max( 1, (int) $opts['max_results'] ) );
	} finally {
		if ( ! $kept && '' !== $tmp && file_exists( $tmp ) ) { @unlink( $tmp ); }
	}
}

/** Move the file into the private folder (random name, deny-all). */
function zt_img_keep( $tmp, $dir, $mime ) {
	if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) ) { return false; }
	@file_put_contents( $dir . '/index.html', '' ); @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	$ext = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic' )[ $mime ] ?? 'bin';
	$to = $dir . '/' . bin2hex( random_bytes( 12 ) ) . '.' . $ext;
	return @rename( $tmp, $to ) || ( @copy( $tmp, $to ) && @unlink( $tmp ) );
}
function zt_img_store_dir() { $u = wp_upload_dir(); return rtrim( $u['basedir'], '/' ) . '/zad-pest-photos'; }
/** Retention for kept photos: older than the general retention months → deleted (daily job). Returns the number removed. */
function zt_img_cleanup( $dir, $months, $now = null ) {
	$now = $now ?? time(); $n = 0; $cut = $now - max( 1, (int) $months ) * 30 * DAY_IN_SECONDS;
	foreach ( (array) glob( rtrim( $dir, '/' ) . '/*.{jpg,png,webp,heic,bin}', GLOB_BRACE ) as $f ) { if ( filemtime( $f ) < $cut && @unlink( $f ) ) { $n++; } }
	return $n;
}
add_action( 'zad_tools_daily', function () { zt_img_cleanup( zt_img_store_dir(), (int) zt_opt( 'general.retention_months' ) ); } );

/** Daily global cap (cost control): true = allowed (and counted). */
function zt_img_daily_ok() {
	$k = 'zt_img_day_' . wp_date( 'Ymd' ); $n = (int) get_transient( $k );
	if ( $n >= (int) zt_opt( 'pest-image.daily_cap' ) ) { return false; }
	set_transient( $k, $n + 1, DAY_IN_SECONDS ); return true;
}

/* ---------------------------------------------- REST ---------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'zad/v1', '/pest-image', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'zt_rest_pest_image' ) );
} );

function zt_rest_pest_image( WP_REST_Request $req ) {
	if ( ! zt_img_available() ) { return new WP_Error( 'zt_off', 'الميزة غير متاحة حالياً.', array( 'status' => 404 ) ); }
	$g = zt_rest_guard( $req, 'pest-image', (int) zt_opt( 'pest-image.per_ip_hour' ) );
	if ( true !== $g ) { return $g; }
	if ( ! $req->get_param( 'consent_analyze' ) ) { return new WP_Error( 'zt_consent', 'يلزم الموافقة على إرسال الصورة للتحليل.', array( 'status' => 400 ) ); }
	if ( ! zt_img_daily_ok() ) { return new WP_Error( 'zt_cap', 'وصلنا لحد التحليلات اليوم، أرسل الصورة لفنيينا على واتساب.', array( 'status' => 429 ) ); }
	$file = isset( $_FILES['photo'] ) && is_array( $_FILES['photo'] ) ? $_FILES['photo'] : array();
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! empty( $file['error'] ) ) { return new WP_Error( 'zt_file', 'لم يصلنا ملف صالح.', array( 'status' => 400 ) ); }
	$cfg = function_exists( 'zt_pid_cfg' ) ? zt_pid_cfg() : array( 'pests' => array() ); $byId = array(); foreach ( $cfg['pests'] as $p ) { $byId[ (int) $p['id'] ] = $p; }
	$res = zt_img_process( array( 'tmp' => $file['tmp_name'], 'size' => (int) $file['size'] ), zt_img_provider(), zt_img_candidates( $cfg['pests'] ), array(
		'max_bytes' => (int) zt_opt( 'pest-image.max_mb' ) * MB_IN_BYTES, 'min_conf' => (int) zt_opt( 'pest-image.min_conf' ), 'max_results' => (int) ( $cfg['max'] ?? 3 ), 'keep' => (bool) $req->get_param( 'keep' ), 'store_dir' => zt_img_store_dir() ) );
	if ( is_wp_error( $res ) ) { return new WP_Error( 'zt_' . $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) ); }
	$cards = array();
	foreach ( $res as $x ) { $p = $byId[ $x['id'] ]; $cards[] = array( 't' => $p['n'], 'u' => $p['u'], 'p' => $x['pct'], 'img' => $p['img'], 'alt' => $p['alt'], 'svc' => $p['svc'] ); }
	return new WP_REST_Response( array( 'ok' => true, 'cards' => $cards ), 200 );
}

/* ---------------------------------------------- the section on the identifier page ---------------------------------------------- */

/** Printed by the identifier page ONLY when the feature is unlocked; JS-only (needs the canvas + token). */
function zt_img_section_html() {
	if ( ! zt_img_available() ) { return ''; }
	$mb = (int) zt_opt( 'pest-image.max_mb' );
	return '<section class="zt-img zt-jsonly" data-zt-image data-cfg="' . zt_esc( wp_json_encode( array( 'maxMb' => $mb, 'maxPx' => (int) zt_opt( 'pest-image.max_px' ) ) ) ) . '"><h3 class="zt-h3">أو ارفع صورة للحشرة</h3>'
		. '<p class="zt-noteline">التحديد بالصورة تقريبي. الحد ' . zt_esc( zt_fmt( $mb ) ) . ' ميغابايت، وتُصغَّر الصورة على جهازك قبل الإرسال.</p>'
		. '<form class="zt-form" novalidate><label class="fld"><span>الصورة (jpg / png / webp / heic)</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/heic"></label>'
		. '<div class="zt-hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input name="website" tabindex="-1" autocomplete="off"></label></div>'
		. '<label class="zt-consent"><input type="checkbox" name="consent_analyze" value="1"><span>أوافق على إرسال الصورة إلى خدمة تحليل خارجية لتحديد الحشرة فقط.</span></label>'
		. '<label class="zt-consent"><input type="checkbox" name="keep" value="1"><span>اسمحوا لزاد بالاحتفاظ بالصورة لتحسين الموسوعة (اختياري؛ بدونه تُحذف فور التحليل).</span></label>'
		. '<div class="zt-actions"><button class="btn btn--accent" type="submit">حلّل الصورة</button></div><p class="zt-img__msg" role="status" aria-live="polite"></p></form></section>';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'page' ) || ! function_exists( 'zt_is_tool_page' ) || ! zt_is_tool_page() || 'pest-id' !== zt_page_tool( get_queried_object_id() ) || ! zt_img_available() ) { return; }
	wp_enqueue_script( 'zt-pest-image', ZT_URL . 'assets/js/pest-image.js', array( 'zt-core' ), ZT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}, 20 );
