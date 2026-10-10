<?php defined( 'ABSPATH' ) || exit;
/**
 * Natural Arabic audio for pages (cloud text-to-speech, generated once and stored as MP3).
 * Providers: Azure Speech (ar-SA Hamed/Zariyah) or Google Cloud TTS. The key stays on the server.
 * When a page has an MP3 the "استمع" button becomes a normal audio player; otherwise the browser voice is used.
 */

function zad_tts_provider() {
	$p = zad_opt( 'zad_tts_provider', 'browser' );
	return in_array( $p, array( 'azure', 'google' ), true ) && zad_opt( 'zad_tts_key', '' ) ? $p : 'browser';
}

function zad_tts_types() {
	return array_unique( array_merge( zad_service_types(), zad_faq_types(), zad_article_types(), array( 'post' ) ) );
}

/** Plain, speakable text of a post (title + body). */
function zad_tts_text( $post ) {
	$post = get_post( $post );
	$c    = (string) $post->post_content;
	$c    = strip_shortcodes( $c );
	$c    = preg_replace( '#<(script|style|svg|noscript|table)\b.*?</\1>#is', ' ', $c );
	$c    = preg_replace( '#</(h[1-6])>#i', '. ', $c );
	$c    = preg_replace( '#</(p|li|div|tr)>#i', ".\n", $c );
	$c    = html_entity_decode( wp_strip_all_tags( $c ), ENT_QUOTES, 'UTF-8' );
	$c    = preg_replace( '#https?://\S+#u', '', $c );
	$c    = preg_replace( '/[ \t]+/u', ' ', $c );
	$c    = preg_replace( '/\.\s*\./u', '.', $c );
	$c    = trim( preg_replace( "/\n\s*\n+/u", "\n", $c ) );
	$ans = '';
	if ( function_exists( 'zad_faq_answer_text' ) && in_array( $post->post_type, zad_faq_types(), true ) && has_excerpt( $post ) ) { // the direct answer is shown above the content, so it is read first (unless the content still opens with the same sentence)
		$ans = zad_clean_answer( get_the_excerpt( $post ) );
		if ( '' !== $ans && 0 === mb_strpos( zad_clean_answer( $c ), mb_substr( $ans, 0, 30 ) ) ) { $ans = ''; }
	}
	return trim( wp_strip_all_tags( get_the_title( $post ) ) ) . ".\n" . ( '' !== $ans ? $ans . ( preg_match( '/[.!؟?]$/u', $ans ) ? '' : '.' ) . "\n" : '' ) . $c;
}

function zad_tts_chunks( $text, $limit ) {
	$out = array();
	$cur = '';
	foreach ( preg_split( '/\n+/u', $text ) as $para ) {
		$para = trim( $para );
		if ( '' === $para ) { continue; }
		while ( mb_strlen( $para, 'UTF-8' ) > $limit ) {
			$cut = mb_strrpos( mb_substr( $para, 0, $limit, 'UTF-8' ), ' ', 0, 'UTF-8' );
			$cut = $cut > $limit * 0.5 ? $cut : $limit;
			$out[] = mb_substr( $para, 0, $cut, 'UTF-8' );
			$para  = trim( mb_substr( $para, $cut, null, 'UTF-8' ) );
		}
		if ( mb_strlen( $cur . "\n" . $para, 'UTF-8' ) > $limit ) { if ( '' !== $cur ) { $out[] = $cur; } $cur = $para; } else { $cur = '' === $cur ? $para : $cur . "\n" . $para; }
	}
	if ( '' !== $cur ) { $out[] = $cur; }
	return $out;
}

function zad_tts_request( $chunk ) {
	$key = trim( (string) zad_opt( 'zad_tts_key', '' ) );
	if ( 'azure' === zad_tts_provider() ) {
		$region = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) zad_opt( 'zad_tts_region', 'westeurope' ) ) );
		$voice  = preg_replace( '/[^A-Za-z0-9-]/', '', (string) zad_opt( 'zad_tts_voice', 'ar-SA-HamedNeural' ) );
		$ssml   = "<speak version='1.0' xml:lang='ar-SA'><voice name='" . $voice . "'><prosody rate='-3%'>" . htmlspecialchars( $chunk, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</prosody></voice></speak>';
		$r      = wp_remote_post( "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1", array( 'timeout' => 90, 'headers' => array( 'Ocp-Apim-Subscription-Key' => $key, 'Content-Type' => 'application/ssml+xml', 'X-Microsoft-OutputFormat' => 'audio-24khz-48kbitrate-mono-mp3', 'User-Agent' => 'zad-theme' ), 'body' => $ssml ) );
		if ( is_wp_error( $r ) ) { return $r; }
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) { return new WP_Error( 'tts', 'Azure: ' . wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_strip_all_tags( wp_remote_retrieve_body( $r ) ), 0, 160 ) ); }
		return wp_remote_retrieve_body( $r );
	}
	$voice = preg_replace( '/[^A-Za-z0-9-]/', '', (string) zad_opt( 'zad_tts_voice', 'ar-XA-Wavenet-B' ) );
	$r     = wp_remote_post( 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . rawurlencode( $key ), array( 'timeout' => 90, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'input' => array( 'text' => $chunk ), 'voice' => array( 'languageCode' => 'ar-XA', 'name' => $voice ), 'audioConfig' => array( 'audioEncoding' => 'MP3', 'speakingRate' => 0.97 ) ) ) ) );
	if ( is_wp_error( $r ) ) { return $r; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || empty( $j['audioContent'] ) ) { return new WP_Error( 'tts', 'Google: ' . ( $j['error']['message'] ?? wp_remote_retrieve_response_code( $r ) ) ); }
	return base64_decode( $j['audioContent'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

function zad_tts_budget( $chars, $add = false ) {
	$k    = 'zad_tts_used_' . gmdate( 'Ym' );
	$used = (int) get_option( $k, 0 );
	$cap  = (int) zad_opt( 'zad_tts_cap', 400000 );
	if ( $used + $chars > $cap ) { return false; }
	if ( $add ) { update_option( $k, $used + $chars, false ); }
	return true;
}

/** Generate (or refresh) the MP3 for one post. @return array|WP_Error */
function zad_tts_generate( $post_id, $force = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) { return new WP_Error( 'tts', 'الصفحة غير منشورة.' ); }
	if ( 'browser' === zad_tts_provider() ) { return new WP_Error( 'tts', 'لم يُضبط مزوّد الصوت ومفتاحه في الإعدادات.' ); }
	$text = zad_tts_text( $post );
	$max  = (int) zad_opt( 'zad_tts_max', 12000 );
	if ( mb_strlen( $text, 'UTF-8' ) > $max ) { $text = mb_substr( $text, 0, $max, 'UTF-8' ); }
	$hash = md5( $text . '|' . zad_tts_provider() . '|' . zad_opt( 'zad_tts_voice', '' ) );
	$cur  = get_post_meta( $post->ID, '_zad_audio', true );
	if ( ! $force && is_array( $cur ) && ( $cur['hash'] ?? '' ) === $hash && ! empty( $cur['url'] ) ) { return $cur; }
	$chars = mb_strlen( $text, 'UTF-8' );
	if ( ! zad_tts_budget( $chars ) ) { return new WP_Error( 'tts', 'تجاوزت حد الاستهلاك الشهري المضبوط في الإعدادات.' ); }
	$limit = 'azure' === zad_tts_provider() ? 2800 : 1700;
	$bin   = '';
	foreach ( zad_tts_chunks( $text, $limit ) as $ch ) {
		$part = zad_tts_request( $ch );
		if ( is_wp_error( $part ) ) { update_post_meta( $post->ID, '_zad_audio_err', $part->get_error_message() ); return $part; }
		$bin .= $part;
	}
	$up = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'zad-audio';
	wp_mkdir_p( $dir );
	$name = $post->ID . '-' . substr( $hash, 0, 8 ) . '.mp3';
	if ( false === file_put_contents( $dir . '/' . $name, $bin ) ) { return new WP_Error( 'tts', 'تعذّر حفظ الملف في مجلد الرفع.' ); }
	if ( is_array( $cur ) && ! empty( $cur['file'] ) && $cur['file'] !== $name && file_exists( $dir . '/' . $cur['file'] ) ) { wp_delete_file( $dir . '/' . $cur['file'] ); }
	zad_tts_budget( $chars, true );
	delete_post_meta( $post->ID, '_zad_audio_err' );
	$meta = array( 'hash' => $hash, 'file' => $name, 'url' => trailingslashit( $up['baseurl'] ) . 'zad-audio/' . $name, 'chars' => $chars, 'time' => time() );
	update_post_meta( $post->ID, '_zad_audio', $meta );
	return $meta;
}

function zad_tts_audio_url( $post_id ) {
	$m = get_post_meta( $post_id, '_zad_audio', true );
	return ( is_array( $m ) && ! empty( $m['url'] ) && 'browser' !== zad_tts_provider() ) ? $m['url'] : '';
}

/* ---- auto-generate after publish/update (background, never blocks saving) ---- */
add_action( 'save_post', function ( $id, $post ) {
	if ( wp_is_post_revision( $id ) || 'publish' !== $post->post_status || ! in_array( $post->post_type, zad_tts_types(), true ) || 'browser' === zad_tts_provider() || ! zad_opt( 'zad_tts_auto', 0 ) ) { return; }
	if ( ! wp_next_scheduled( 'zad_tts_generate_event', array( $id ) ) ) { wp_schedule_single_event( time() + 45, 'zad_tts_generate_event', array( $id ) ); }
}, 20, 2 );
add_action( 'zad_tts_generate_event', function ( $id ) { zad_tts_generate( $id ); } );

/* ---- admin: AJAX + meta box + bulk tool ---- */
add_action( 'wp_ajax_zad_tts_make', function () {
	check_ajax_referer( 'zad_tts', 'n' );
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error( array( 'message' => 'غير مسموح.' ), 403 ); }
	$r = zad_tts_generate( $id, ! empty( $_POST['force'] ) );
	is_wp_error( $r ) ? wp_send_json_error( array( 'message' => $r->get_error_message() ) ) : wp_send_json_success( array( 'url' => $r['url'], 'chars' => $r['chars'] ) );
} );
add_action( 'wp_ajax_zad_tts_list', function () {
	check_ajax_referer( 'zad_tts', 'n' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( null, 403 ); }
	$ids = get_posts( array( 'post_type' => zad_tts_types(), 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'zad_all' => true ) );
	$todo = array();
	foreach ( $ids as $i ) {
		$m = get_post_meta( $i, '_zad_audio', true );
		if ( ! is_array( $m ) || empty( $m['url'] ) ) { $todo[] = array( 'id' => $i, 'title' => get_the_title( $i ) ); }
	}
	wp_send_json_success( array( 'todo' => $todo ) );
} );

add_action( 'add_meta_boxes', function () {
	if ( 'browser' === zad_tts_provider() ) { return; }
	add_meta_box( 'zad_tts_box', 'الصوت (قراءة الصفحة)', function ( $p ) {
		$m   = get_post_meta( $p->ID, '_zad_audio', true );
		$err = get_post_meta( $p->ID, '_zad_audio_err', true );
		echo '<div data-tts data-id="' . (int) $p->ID . '" data-n="' . esc_attr( wp_create_nonce( 'zad_tts' ) ) . '">';
		if ( is_array( $m ) && ! empty( $m['url'] ) ) { echo '<audio controls preload="none" src="' . esc_url( $m['url'] ) . '" style="width:100%"></audio><p class="description">' . (int) $m['chars'] . ' حرف · ' . esc_html( wp_date( 'Y-m-d H:i', $m['time'] ) ) . '</p>'; }
		else { echo '<p class="description">لا يوجد ملف صوتي بعد.</p>'; }
		if ( $err ) { echo '<p style="color:#b32d2e">آخر خطأ: ' . esc_html( $err ) . '</p>'; }
		echo '<p><button type="button" class="button" data-tts-go>توليد / تحديث الصوت الآن</button> <span data-tts-st></span></p></div>';
		echo '<script>(function(){var b=document.querySelector("[data-tts]");if(!b)return;b.querySelector("[data-tts-go]").addEventListener("click",function(){var st=b.querySelector("[data-tts-st]");st.textContent="جاري التوليد…";var f=new FormData();f.append("action","zad_tts_make");f.append("id",b.dataset.id);f.append("n",b.dataset.n);f.append("force","1");fetch(ajaxurl,{method:"POST",body:f,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){st.textContent=j.success?"تم. حدّث الصفحة لتسمعه.":(j.data&&j.data.message)||"فشل";}).catch(function(){st.textContent="تعذّر الاتصال"});});})();</script>';
	}, zad_tts_types(), 'side' );
} );

add_action( 'admin_menu', function () {
	add_management_page( 'توليد الصوت للصفحات', 'توليد الصوت (زاد)', 'manage_options', 'zad-tts', function () {
		$used = (int) get_option( 'zad_tts_used_' . gmdate( 'Ym' ), 0 );
		echo '<div class="wrap" dir="rtl"><h1>توليد الصوت للصفحات</h1>';
		if ( 'browser' === zad_tts_provider() ) { echo '<div class="notice notice-warning"><p>اختر مزوّد الصوت وأدخل المفتاح من إعدادات الثيم (الهوية والألوان ← الصوت) أولاً.</p></div></div>'; return; }
		echo '<p>المزوّد: <strong>' . esc_html( zad_tts_provider() ) . '</strong> · الصوت: <code>' . esc_html( zad_opt( 'zad_tts_voice', '' ) ) . '</code> · المستهلك هذا الشهر: <strong>' . (int) $used . '</strong> من ' . (int) zad_opt( 'zad_tts_cap', 400000 ) . ' حرف.</p>';
		echo '<p><button class="button button-primary" id="zad-tts-run">توليد الصوت لكل الصفحات الناقصة</button> <span id="zad-tts-st"></span></p><ol id="zad-tts-log"></ol>';
		echo '<script>(function(){var n="' . esc_js( wp_create_nonce( 'zad_tts' ) ) . '",st=document.getElementById("zad-tts-st"),log=document.getElementById("zad-tts-log");document.getElementById("zad-tts-run").addEventListener("click",function(){var b=this;b.disabled=true;var f=new FormData();f.append("action","zad_tts_list");f.append("n",n);fetch(ajaxurl,{method:"POST",body:f,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){var todo=(j.data&&j.data.todo)||[],i=0;function next(){if(i>=todo.length){st.textContent="انتهى.";b.disabled=false;return;}var t=todo[i];st.textContent=(i+1)+" / "+todo.length;var g=new FormData();g.append("action","zad_tts_make");g.append("id",t.id);g.append("n",n);fetch(ajaxurl,{method:"POST",body:g,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(k){var li=document.createElement("li");li.textContent=t.title+" — "+(k.success?"تم":((k.data&&k.data.message)||"فشل"));log.appendChild(li);if(!k.success&&k.data&&/حد الاستهلاك|Azure|Google/.test(k.data.message||"")){st.textContent="توقف: "+k.data.message;b.disabled=false;return;}i++;next();});}next();});});})();</script></div>';
	} );
} );
