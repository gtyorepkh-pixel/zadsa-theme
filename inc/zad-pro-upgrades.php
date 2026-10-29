<?php defined( 'ABSPATH' ) || exit;
/**
 * Zad Pro Upgrades v3.30.0:
 * - Safe ISO Dates for Previews/Drafts
 * - Automatic TOC + Heading IDs (H2/H3)
 * - Smart Fallbacks for "When you need this service & when you don't"
 * - Interactive Warranty Card & Timeline + Verify Link
 * - Auto Internal Linking for Neighbourhood Chips (cov__chips)
 * - Auto Fallback for "Same Service in Other Cities" & "Guides"
 * - Lead Tracking Attributes (data-ev, data-src) & Video Lightbox Modal
 */

/** 1. Safe ISO 8601 Date (Fixes datePublished: false in previews/drafts) */
function zsc_safe_iso_date( $post = null, $modified = false ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return current_time( 'c' );
	}
	$t = $modified ? get_post_modified_time( 'c', true, $post ) : get_post_time( 'c', true, $post );
	if ( ! $t || false === $t ) {
		$raw = $modified ? $post->post_modified : $post->post_date;
		if ( ! $raw || '0000-00-00 00:00:00' === $raw ) {
			$raw = current_time( 'mysql' );
		}
		$ts = strtotime( $raw );
		$t  = $ts ? gmdate( 'c', $ts ) : current_time( 'c' );
	}
	return $t;
}

/** 2. Wikidata Entities for Saudi Cities */
function zsc_city_wikidata( $city_name ) {
	$map = array(
		'الرياض'          => 'https://www.wikidata.org/wiki/Q3692',
		'جدة'             => 'https://www.wikidata.org/wiki/Q374365',
		'الدمام'          => 'https://www.wikidata.org/wiki/Q160320',
		'القطيف'          => 'https://www.wikidata.org/wiki/Q1014358',
		'الخبر'           => 'https://www.wikidata.org/wiki/Q846655',
		'القصيم'          => 'https://www.wikidata.org/wiki/Q230820',
		'بريدة'           => 'https://www.wikidata.org/wiki/Q259253',
		'عنيزة'           => 'https://www.wikidata.org/wiki/Q27138',
		'الخرج'           => 'https://www.wikidata.org/wiki/Q16123684',
		'نجران'           => 'https://www.wikidata.org/wiki/Q27174',
		'مكة المكرمة'     => 'https://www.wikidata.org/wiki/Q5806',
		'المدينة المنورة' => 'https://www.wikidata.org/wiki/Q35484',
		'الطائف'          => 'https://www.wikidata.org/wiki/Q182462',
		'الأحساء'         => 'https://www.wikidata.org/wiki/Q27182',
		'الجبيل'          => 'https://www.wikidata.org/wiki/Q27361',
	);
	$city_name = trim( (string) $city_name );
	return $map[ $city_name ] ?? '';
}

/** 4. Smart Lookup for Neighbourhood Chip Links */
function zad_find_hood_url( $hood_name, $post_id = 0 ) {
 return $post_id ? zad_link_find_district( $hood_name, (int) $post_id ) : '';
}

/** 7. Video Lightbox Modal & Click Tracking Script in Footer */
add_action( 'wp_footer', function () {
	?>
	<div class="zw-modal" id="zw-video-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="مشغل الفيديو">
		<div class="zw-modal__bg" data-zw-close></div>
		<div class="zw-modal__box">
			<button type="button" class="zw-modal__x" data-zw-close aria-label="إغلاق"><?php echo zad_icon( 'close', 22 ); // phpcs:ignore ?></button>
			<div class="zw-modal__body" id="zw-modal-body"></div>
		</div>
	</div>
	<script>
	(function(){
		// 1. Auto-tag call and WhatsApp links for Lead Tracking
		document.querySelectorAll('a[href^="tel:"], a[href*="wa.me/"], button[data-wa]').forEach(function(el){
			if(!el.hasAttribute('data-ev')){
				var isWa = el.hasAttribute('data-wa') || (el.href && el.href.indexOf('wa.me') !== -1);
				el.setAttribute('data-ev', isWa ? 'lead_whatsapp' : 'lead_call');
			}
			if(!el.hasAttribute('data-src')){
				var sec = el.closest('header, footer, .shero, .zw-card, .pkgx, .dock, .fab, .qfinal, .slayout__side');
				var src = 'page';
				if(sec){
					if(sec.tagName === 'HEADER') src = 'header';
					else if(sec.tagName === 'FOOTER') src = 'footer';
					else if(sec.classList.contains('shero')) src = 'service-hero';
					else if(sec.classList.contains('zw-card')) src = 'field-work-card';
					else if(sec.classList.contains('pkgx')) src = 'packages';
					else if(sec.classList.contains('dock')) src = 'bottom-dock';
					else if(sec.classList.contains('fab')) src = 'floating-fab';
					else if(sec.classList.contains('qfinal')) src = 'final-cta';
					else if(sec.classList.contains('slayout__side')) src = 'sidebar';
				}
				el.setAttribute('data-src', src);
			}
		});

		// 2. Inline Video Lightbox Modal for Works Section
		var modal = document.getElementById('zw-video-modal');
		var body  = document.getElementById('zw-modal-body');
		if(!modal || !body) return;
		function closeModal(){
			modal.hidden = true;
			modal.setAttribute('aria-hidden', 'true');
			body.innerHTML = '';
		}
		document.addEventListener('click', function(e){
			var btn = e.target.closest('[data-zw-open-video]');
			if(btn){
				e.preventDefault();
				var src  = btn.getAttribute('data-zw-open-video');
				var kind = btn.getAttribute('data-zw-kind') || 'file';
				if(!src) return;
				if(kind === 'yt'){
					body.innerHTML = '<iframe src="' + src + '?autoplay=1&rel=0" allow="autoplay; encrypted-media" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0;border-radius:12px"></iframe>';
				} else {
					body.innerHTML = '<video src="' + src + '" controls autoplay playsinline style="width:100%;max-height:80vh;border-radius:12px;background:#000"></video>';
				}
				modal.hidden = false;
				modal.setAttribute('aria-hidden', 'false');
				return;
			}
			if(e.target.closest('[data-zw-close]')){
				closeModal();
			}
		});
		document.addEventListener('keydown', function(e){
			if(e.key === 'Escape' && !modal.hidden) closeModal();
		});
	})();
	</script>
	<?php
}, 99 );



/** 8. Integration with "المدن (التغطية المؤكدة)" Table in Theme Options */
function zad_get_confirmed_cities() {
	$rows = zad_opt( 'zad_cities_table', array() );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}
	$active = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $r['active'] ) && ! empty( $r['name'] ) ) {
			$page_id  = ! empty( $r['page'] ) ? (int) $r['page'] : 0;
			$page_url = ( $page_id && 'publish' === get_post_status( $page_id ) ) ? get_permalink( $page_id ) : '';
			$wd       = ! empty( $r['wikidata'] ) ? trim( (string) $r['wikidata'] ) : zsc_city_wikidata( $r['name'] );
			$active[] = array(
				'name'     => trim( (string) $r['name'] ),
				'page_id'  => $page_id,
				'url'      => $page_url,
				'wikidata' => $wd,
			);
		}
	}
	return $active;
}

// ربط المدن الفعّالة تلقائياً بـ zsc_settings (Scehma areaServed + Wikidata)
add_filter( 'zsc_settings', function ( $s ) {
	$confirmed = zad_get_confirmed_cities();
	if ( $confirmed ) {
		$areas = array();
		// القصيم منطقة إدارية تحتها بريدة وعنيزة، فما ينفعش تتكتب City في السكيما. فلتر: zad_schema_regions.
		$regions = (array) apply_filters( 'zad_schema_regions', array( 'القصيم', 'منطقة القصيم', 'الشرقية', 'المنطقة الشرقية' ) );
		foreach ( $confirmed as $c ) {
			$type    = in_array( $c['name'], $regions, true ) ? 'AdministrativeArea' : 'City';
			$areas[] = array( $type, $c['name'], $c['wikidata'] );
		}
		$s['business']['area_served'] = $areas;
	}
	return $s;
}, 20 );



/** 9. Brand Preset Colors & Seasonal Promo Bar Integration */
add_action( 'wp_head', function () {
	$preset = zad_opt( 'zad_brand_preset', 'zad_official' );
	$palettes = array(
		'zad_emerald' => array( '--primary' => '#0f766e', '--accent' => '#f59e0b', '--hdr-bg' => '#0f766e', '--topbar-bg' => '#115e59', '--ftr-bg' => '#042f2e' ),
		'zad_royal'   => array( '--primary' => '#1e3a8a', '--accent' => '#ea580c', '--hdr-bg' => '#1e3a8a', '--topbar-bg' => '#172554', '--ftr-bg' => '#0f172a' ),
	);
	if ( isset( $palettes[ $preset ] ) ) {
		$css = ':root{';
		foreach ( $palettes[ $preset ] as $k => $v ) { $css .= $k . ':' . $v . '!important;'; }
		$css .= '}';
		echo '<style id="zad-brand-preset">' . $css . '</style>' . "\n"; // phpcs:ignore
	}
}, 99 );

add_action( 'wp_body_open', function () {
	$mode = zad_opt( 'zad_promo_mode', 'off' );
	if ( ! $mode || 'off' === $mode ) { return; }
	$promos = array(
		'summer'   => array( 'عروض الصيف', 'غسيل حتى 3 مكيفات سبليت أو شباك بـ 150 ريال فقط مع فحص الفريون مجاناً!' ),
		'pest'     => array( 'ضمان مكتوب', 'رش مبيدات موجّه للمنازل والشركات بضمان مكتوب حتى 6 أشهر وزيارة مجانية!' ),
		'Founding' => array( 'عروض وطنية', 'خصومات خاصة بمناسبة اليوم الوطني على باقات التنظيف الشامل والخزانات!' ),
		'ramadan'  => array( 'تجهيز رمضان', 'عروض خاصة لتنظيف المجالس والكنب والمكيفات بالبخار قبل الشهر الكريم!' ),
		'custom'   => array( zad_opt( 'zad_promo_badge', 'عرض خاص' ), zad_opt( 'zad_promo_text', '' ) ),
	);
	if ( empty( $promos[ $mode ][1] ) ) { return; }
	$wa = zad_wa_link( 'مرحباً، أرغب بالاستفادة من العرض: ' . $promos[ $mode ][1] );
	echo '<div class="zad-promo-bar"><div class="wrap zad-promo-bar__in"><span class="zad-promo-bar__badge">' . esc_html( $promos[ $mode ][0] ) . '</span><p>' . esc_html( $promos[ $mode ][1] ) . '</p>' . ( $wa ? '<a href="' . esc_url( $wa ) . '" data-ev="lead_whatsapp" data-src="promo-bar" target="_blank" rel="noopener">احجز العرض الآن ←</a>' : '' ) . '</div></div>'; // phpcs:ignore
} );
