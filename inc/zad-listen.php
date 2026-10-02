<?php defined( 'ABSPATH' ) || exit;
/**
 * "استمع" — reads the page aloud with the visitor's browser voice (Web Speech API, Arabic).
 * Free, no files, no keys. The button stays hidden when the device has no Arabic voice.
 */

function zad_listen_applies() {
	return is_singular() && 'page' !== get_post_type() && ( zad_is_service() || zad_is_faq() || zad_is_article() || is_singular( 'post' ) ) && (bool) zad_opt( 'zad_listen_on', 1 );
}

add_filter( 'the_content', function ( $content ) {
	static $done = false;
	if ( $done || is_admin() || ! in_the_loop() || ! is_main_query() || ! zad_listen_applies() || mb_strlen( wp_strip_all_tags( $content ), 'UTF-8' ) < 400 ) {
		return $content;
	}
	$done = true;
	$box  = '<div class="listen" data-listen hidden role="group" aria-label="الاستماع إلى الصفحة"><button type="button" class="listen__main" data-l-play aria-label="استمع إلى الصفحة">' . zad_icon( 'phone', 18 ) . '<span data-l-label>استمع إلى الصفحة</span></button>'
		. '<span class="listen__ctl" data-l-ctl hidden><button type="button" data-l-stop class="listen__b" aria-label="إيقاف">■</button><label class="listen__sp"><span class="sr">السرعة</span><select data-l-rate><option value="0.9">0.9×</option><option value="1" selected>1×</option><option value="1.15">1.15×</option><option value="1.3">1.3×</option><option value="1.5">1.5×</option></select></label><span class="listen__st" data-l-status aria-live="polite"></span></span></div>';
	return $box . $content;
}, 8 );

add_action( 'wp_enqueue_scripts', function () {
	if ( zad_listen_applies() ) {
		wp_enqueue_script( 'zad-listen', get_template_directory_uri() . '/assets/js/zad-listen.js', array(), ZAD_VERSION, true );
		wp_add_inline_style( 'zad-main', '.listen{margin:0 0 18px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}.listen[hidden]{display:none}.listen__main{display:inline-flex;gap:8px;align-items:center;border:2px solid var(--primary);background:var(--surface);color:var(--primary);font:800 .95rem var(--font);padding:9px 18px;border-radius:999px;cursor:pointer;min-height:44px}.listen__main[aria-pressed=true]{background:var(--primary);color:#fff}.listen__ctl{display:inline-flex;gap:8px;align-items:center}.listen__ctl[hidden]{display:none}.listen__b{width:40px;height:40px;border-radius:50%;border:2px solid var(--line);background:var(--surface);cursor:pointer;color:var(--ink)}.listen__sp select{border:2px solid var(--line);border-radius:999px;padding:7px 10px;font:700 .9rem var(--font);background:var(--surface);color:var(--ink)}.listen__st{color:var(--muted);font-size:.88rem}.is-reading{background:color-mix(in srgb,var(--accent) 26%,transparent);border-radius:6px;transition:background .2s}' );
	}
}, 120 );
