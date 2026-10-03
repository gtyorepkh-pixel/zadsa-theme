<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id   = get_the_ID();
	if ( function_exists( 'zad_hood_active' ) && zad_hood_active( $id ) ) { zad_hood_render( $id ); continue; }
	$m    = function ( $k ) use ( $id ) { return get_post_meta( $id, '_zad_' . $k, true ); };
	$arr  = function ( $k ) use ( $m ) { return array_values( array_filter( (array) $m( $k ), function ( $r ) { return is_array( $r ); } ) ); };

	$city     = zad_current_city();
	$title    = $city ? zad_city_title( $id, $city ) : get_the_title();
	$tagline  = $m( 'tagline' );
	$features = zad_lines( $m( 'features' ) );
	$stats    = $arr( 'stats' );
	$why      = $arr( 'why' );
	$subs     = $arr( 'subs' );
	$tools    = $arr( 'tools' );
	$steps    = $arr( 'steps' );
	$factors  = $arr( 'factors' );
	$faq      = $arr( 'faq' );
	$signs    = $arr( 'signs' );
	$harms    = $arr( 'harms' );
	$safety   = $arr( 'safety' );
	$wrows    = $arr( 'warrantyrows' );
	$after    = zad_lines( $m( 'aftercare' ) );
	$pkgs     = array();
	foreach ( zad_lines( $m( 'packages' ) ) as $l ) {
		$c = array_map( 'trim', explode( '|', $l ) );
		$pkgs[] = array( 'name' => $c[0], 'price' => $c[1] ?? '', 'feat' => isset( $c[2] ) ? array_filter( array_map( 'trim', preg_split( '/[;؛]/u', $c[2] ) ) ) : array() );
	}
	$prices   = zad_parse_prices( $m( 'prices' ) );
	$has_det  = (bool) array_filter( wp_list_pluck( $prices, 'details' ) );
	$has_war  = (bool) array_filter( wp_list_pluck( $prices, 'warranty' ) );
	$min      = zad_min_price( $prices ) ?: (int) $m( 'price' );
	$unit     = $m( 'price_unit' ) ?: 'ريال';
	$gallery  = array_filter( array_map( 'intval', explode( ',', (string) $m( 'gallery' ) ) ) );
	$ba_ids   = array_values( array_filter( array_map( 'intval', explode( ',', (string) $m( 'ba' ) ) ) ) );
	$ba_text  = zad_lines( $m( 'ba_text' ) );
	$video    = $m( 'video' );
	$ctx      = function ( $n ) use ( $gallery ) { $g = array_values( $gallery ); if ( empty( $g[ $n ] ) ) { return; } $cap = wp_get_attachment_caption( $g[ $n ] ) ?: get_post_meta( $g[ $n ], '_wp_attachment_image_alt', true ); echo '<div class="wrap"><figure class="ctximg">' . wp_get_attachment_image( $g[ $n ], 'large', false, array( 'loading' => 'lazy' ) ) . ( $cap ? '<figcaption>' . esc_html( $cap ) . '</figcaption>' : '' ) . '</figure></div>'; };
	$rating   = $m( 'rating' );
	$reviews  = (int) $m( 'reviews' );
	$areas    = get_the_terms( $id, 'service_area' );
	$cats     = get_the_terms( $id, 'service_cat' );
	$cta      = $m( 'cta_text' ) ?: 'اطلب الخدمة الآن';
	$phone    = zad_phone( $id );
	$wa       = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . $title, $id );
	$facts    = array_filter( array(
		array( 'shield', $m( 'warranty' ) ),
		array( 'clock', $m( 'duration' ) ),
		array( 'bolt', $m( 'response' ) ),
		array( 'check', $features ? $features[0] : '' ),
	), function ( $c ) { return ! empty( $c[1] ); } );
	$thumb    = has_post_thumbnail() ? get_post_thumbnail_id() : 0;
	$provider = zad_opt( 'zad_provider', get_bloginfo( 'name' ) );
	$area_names = ( $areas && ! is_wp_error( $areas ) ) ? wp_list_pluck( $areas, 'name' ) : array();
	?>
<main id="main">

	<!-- 1. HERO + instant price estimator -->
	<section class="shero<?php echo $thumb ? ' shero--photo' : ''; ?>"<?php echo $thumb ? ' style="--hero-img:url(\'' . esc_url( wp_get_attachment_image_url( $thumb, 'full' ) ) . '\')"' : ''; ?>>
		<div class="wrap">
			<?php zad_render_crumbs( zad_current_crumbs() ); ?>
			<div class="shero__grid">
				<div class="shero__text">
					<?php if ( $provider ) : ?><span class="pill"><?php echo zad_icon( 'badge', 18 ); // phpcs:ignore ?> <?php echo esc_html( $provider ); ?></span><?php endif; ?>
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php if ( $tagline ) : ?><p class="shero__tag"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
					<?php if ( $rating ) : ?>
						<p class="rate"><?php echo zad_stars( $rating ); // phpcs:ignore ?> <b><?php echo esc_html( $rating ); ?></b><?php if ( $reviews ) : ?> <span>(<?php echo esc_html( number_format_i18n( $reviews ) ); ?> تقييم)</span><?php endif; ?></p>
					<?php endif; ?>
					<?php if ( $facts ) : ?>
						<ul class="ticks">
							<?php foreach ( $facts as $f ) : ?><li><?php echo zad_icon( $f[0], 18 ); // phpcs:ignore ?> <?php echo esc_html( $f[1] ); ?></li><?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<div class="hero__btns">
						<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> تواصل عبر واتساب</a><?php endif; ?>
						<?php if ( $phone ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل بنا مباشرة</a><?php endif; ?>
					</div>
					<?php if ( $area_names ) : ?>
						<div class="herochips">
							<small>نغطي أحياءك</small>
							<ul><?php foreach ( array_slice( $area_names, 0, 8 ) as $n ) { echo '<li>' . esc_html( $n ) . '</li>'; } ?><?php if ( count( $area_names ) > 8 ) { echo '<li>+' . esc_html( count( $area_names ) - 8 ) . ' أخرى</li>'; } ?></ul>
						</div>
					<?php endif; ?>
				</div>

				<div class="shero__side">
					<?php $hc = zad_hero_card( $id, (bool) $prices ); if ( 'q30' === $hc ) : echo zad_q30_html( $id, 'hero-a' ); // phpcs:ignore
					elseif ( 'dx' === $hc ) : echo zad_dx_html( $id, 'hero-b' ); // phpcs:ignore
					elseif ( 'est' === $hc ) : ?>
					<div class="est" data-est data-wa="<?php echo esc_attr( zad_whatsapp( $id ) ); ?>" data-title="<?php echo esc_attr( $title ); ?>" data-unit="<?php echo esc_attr( $unit ); ?>">
						<div class="est__head"><span class="est__step">1</span><div><strong>حدّد خدمتك واعرف سعرها</strong><small>اختر الخدمة المطلوبة من الخيارات</small></div></div>
						<div class="est__chips" role="radiogroup" aria-label="اختر الخدمة">
						<?php
						$by_group = array();
						foreach ( $prices as $i => $r ) { $by_group[ $r['group'] ][ $i ] = $r; }
						$est_max = (int) zad_opt( 'zad_est_max', 6 ); $est_n = 0; // 0 = show all; the full list stays in the price table below
						foreach ( $by_group as $gname => $rows ) :
							if ( $est_max > 0 ) {
								if ( $est_n >= $est_max ) { break; }
								$rows = array_slice( $rows, 0, $est_max - $est_n, true );
							}
							$est_n += count( $rows );
							if ( '' !== $gname ) { echo '<div class="est__grp">' . esc_html( $gname ) . '</div>'; }
							echo '<div class="est__row">';
							foreach ( $rows as $i => $r ) {
								$per = (bool) preg_match( '/م\s*[²2]|متر/u', $r['price'] ) && $r['num'] > 0;
								echo '<label class="est__chip"><input type="radio" name="est_' . (int) $id . '" value="' . (int) $i . '" data-est-opt data-price="' . esc_attr( $r['price'] ) . '" data-num="' . (int) $r['num'] . '" data-per="' . ( $per ? 1 : 0 ) . '" data-group="' . esc_attr( $gname ) . '"><span class="est__nm">' . esc_html( $r['name'] ) . '</span></label>';
							}
							echo '</div>';
						endforeach;
						?>
						</div>
						<div class="est__area" data-est-area hidden>
							<div class="est__ahead"><span class="est__step">2</span><strong>كم المساحة؟ (م²)</strong></div>
							<div class="est__stepper"><button type="button" data-est-dec aria-label="أنقص">−</button><input type="number" inputmode="numeric" min="1" max="5000" value="50" data-est-qty aria-label="المساحة بالمتر المربع"><button type="button" data-est-inc aria-label="زِد">+</button></div>
							<div class="est__presets"><button type="button" data-q="25">25</button><button type="button" data-q="50">50</button><button type="button" data-q="100">100</button><button type="button" data-q="200">200</button></div>
						</div>
						<div class="est__out" aria-live="polite"><small data-est-label>سعرك التقديري</small><b data-est-price>اختر خدمة من الأعلى</b><em data-est-sub></em></div>
						<a class="btn btn--wa btn--block" data-est-wa href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب هذه الخدمة عبر واتساب</a>
						<p class="est__note">السعر تقريبي ويُؤكَّد نهائياً بعد المعاينة.</p>
					</div>
					<?php else : ?>
						<?php echo zad_quote_form( array( 'service_id' => $id, 'id' => 'hq', 'title' => 'اطلب ' . $title, 'compact' => true ) ); // phpcs:ignore ?>
					<?php endif; ?>
					<?php $fc = zad_float_cards(); if ( $fc ) : ?>
					<ul class="fcards" aria-label="مزايا الخدمة"><?php foreach ( $fc as $n => $c ) : ?>
						<li class="fcard fcard--<?php echo (int) ( $n + 1 ); ?>"><span class="fcard__ic"><?php echo zad_icon( $c[2], 24 ); // phpcs:ignore ?></span><span class="fcard__tx"><b><?php echo esc_html( $c[0] ); ?></b><small><?php echo esc_html( $c[1] ); ?></small></span></li>
					<?php endforeach; ?></ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Stats strip -->
	<?php if ( $stats ) : ?>
	<section class="stats stats--svc"><div class="wrap stats__grid">
		<?php foreach ( $stats as $s ) : ?><div class="stat"><b><?php echo esc_html( $s['n'] ); ?></b><span><?php echo esc_html( $s['l'] ); ?></span></div><?php endforeach; ?>
	</div></section>
	<?php endif; ?>

	<?php echo zad_rating_strip(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<!-- 3. Intro (content) + all-services sidebar -->
	<section class="sec">
		<div class="wrap slayout">
			<div class="slayout__main">
				<h2 class="h-line"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $city ) : ?><div class="cityblock"><?php echo zad_icon( 'pin', 22 ); // phpcs:ignore ?><p><?php echo esc_html( zad_city_text( $id, $city ) ); ?></p></div><?php endif; ?>
				<?php
				ob_start();
				the_content();
				list( $ix_before, $ix_after ) = zad_ix_split( ob_get_clean(), $id );
				?>
				<div class="prose entry-content"><?php echo $ix_before; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<p><a class="btn btn--accent" href="#quote" data-scroll-quote><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> <?php echo esc_html( $cta ); ?></a></p>
			</div>
			<aside class="slayout__side">
				<div class="sticky">
					<?php zad_services_sidebar( $id ); ?>
				</div>
			</aside>
		</div>
	</section>

	<?php if ( zad_ix_enabled( $id ) ) : ?>
		<!-- 3b. Interactive modules (optional) + content that follows the "Read more" tag -->
		<?php echo zad_ix_render( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( '' !== trim( wp_strip_all_tags( $ix_after ) ) ) : ?>
		<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo $ix_after; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
		<?php endif; ?>
	<?php endif; ?>

	<?php echo zad_children_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo zad_coverage_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php if ( 'dx' !== $hc ) { echo zad_dx_section( $id, 'sec-b' ); } // phpcs:ignore ?>

	<!-- 4. What's included -->
	<?php if ( $features ) : ?>
	<section class="sec sec--tint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">ماذا تشمل الخدمة</span><h2>ما تشمله خدمة <?php echo esc_html( $title ); ?></h2></header>
		<ul class="featgrid">
			<?php foreach ( $features as $f ) : ?><li><?php echo zad_icon( 'check', 20 ); // phpcs:ignore ?><span><?php echo esc_html( $f ); ?></span></li><?php endforeach; ?>
		</ul>
	</div></section>
	<?php endif; ?>

	<!-- 5. Why us -->
	<?php if ( $why ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">لماذا نحن</span><h2>خدمة حقيقية تقوم على أسس واضحة</h2></header>
		<div class="cardgrid">
			<?php foreach ( $why as $i => $w ) : ?>
				<div class="icard"><span class="icard__ic"><?php echo zad_icon( array( 'shield', 'tool', 'badge', 'clock', 'users', 'star' )[ $i % 6 ], 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 6. Sub-services -->
	<?php if ( $subs ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">خدماتنا</span><h2>أنواع <?php echo esc_html( $title ); ?></h2></header>
		<div class="cardgrid cardgrid--4">
			<?php foreach ( $subs as $w ) : ?>
				<div class="icard icard--line"><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p>
				<?php if ( $wa ) : ?><a class="more" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بخدمة: ' . $w['t'], $id ) ); ?>" target="_blank" rel="noopener">اطلب الآن <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a><?php endif; ?></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 7. Tools -->
	<?php if ( $tools ) : ?>
	<section class="sec sec--dark"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">أدواتنا ومعداتنا</span><h2>نعتمد على معدات ومواد حقيقية في كل زيارة</h2></header>
		<div class="cardgrid">
			<?php foreach ( $tools as $w ) : ?>
				<div class="icard icard--dark"><span class="icard__ic"><?php echo zad_icon( 'tool', 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 8. Execution steps (design chosen per page) -->
	<?php echo zad_steps_html( $id, $steps ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<!-- 9. Pricing factors + CTA box -->
	<?php if ( $factors ) : ?>
	<section class="sec sec--tint"><div class="wrap pf">
		<div class="pf__main">
			<span class="eyebrow">تسعير شفاف</span>
			<h2>كيف نحدد سعر <?php echo esc_html( $title ); ?>؟</h2>
			<ol class="pf__list">
				<?php foreach ( $factors as $i => $s ) : ?>
					<li><span class="timeline__n"><?php echo esc_html( $i + 1 ); ?></span><div><h3><?php echo esc_html( $s['t'] ); ?></h3><p><?php echo esc_html( $s['d'] ); ?></p></div></li>
				<?php endforeach; ?>
			</ol>
		</div>
		<div class="pf__box">
			<h3>احجز معاينة مجانية الآن</h3>
			<ul class="ticks"><li><?php echo zad_icon( 'check', 18 ); // phpcs:ignore ?> فحص وتشخيص دقيق للحالة</li><li><?php echo zad_icon( 'check', 18 ); // phpcs:ignore ?> سعر نهائي واضح بلا رسوم مخفية</li><li><?php echo zad_icon( 'check', 18 ); // phpcs:ignore ?> تنفيذ بمعايير احترافية موحّدة</li></ul>
			<?php if ( $wa ) : ?><a class="btn btn--wa btn--block" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> احجز عبر واتساب</a><?php endif; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 10. Price list -->
	<?php if ( $prices ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قائمة الأسعار</span><h2>أسعار <?php echo esc_html( $title ); ?></h2></header>
		<div class="tbl"><table>
			<thead><tr><th>الخدمة</th><?php if ( $has_det ) : ?><th>التفاصيل</th><?php endif; ?><th>السعر</th><?php if ( $has_war ) : ?><th>الضمان</th><?php endif; ?><th></th></tr></thead>
			<tbody>
			<?php $last = null; foreach ( $prices as $r ) :
				if ( $r['group'] && $r['group'] !== $last ) { echo '<tr class="tbl__grp"><td colspan="5">' . esc_html( $r['group'] ) . '</td></tr>'; $last = $r['group']; } ?>
				<tr>
					<td data-l="الخدمة"><?php echo esc_html( $r['name'] ); ?></td>
					<?php if ( $has_det ) : ?><td data-l="التفاصيل" class="tbl__det"><?php echo esc_html( $r['details'] ); ?></td><?php endif; ?>
					<td data-l="السعر"><b><?php echo esc_html( $r['price'] ); ?></b></td>
					<?php if ( $has_war ) : ?><td data-l="الضمان"><?php echo esc_html( $r['warranty'] ?: '—' ); ?></td><?php endif; ?>
					<td><a class="iconbtn iconbtn--wa" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بـ: ' . $r['name'], $id ) ); ?>" target="_blank" rel="noopener" aria-label="اطلب عبر واتساب"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php if ( $m( 'price_note' ) ) : ?><p class="tbl__note"><?php echo esc_html( $m( 'price_note' ) ); ?></p><?php endif; ?>
	</div></section>
	<?php endif; ?>

	<!-- 11. Technical card (spec) -->
	<section class="sec sec--mint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow"><?php echo zad_icon( 'check', 14 ); // phpcs:ignore ?> نظرة سريعة</span><h2>البطاقة الفنية</h2></header>
		<dl class="info">
			<?php
			$info = array(
				'اسم الخدمة'     => $title,
				'مقدّم الخدمة'   => $provider,
				'التصنيف'        => ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '',
				'مناطق الخدمة'   => implode( '، ', $area_names ),
				'نطاق السعر'     => $min ? 'من ' . number_format_i18n( $min ) . ' ' . $unit : '',
				'مدة التنفيذ'    => $m( 'duration' ),
				'الضمان'         => $m( 'warranty' ),
				'ساعات العمل'    => zad_hours_text(),
			);
			foreach ( zad_lines( $m( 'spec' ) ) as $l ) {
				$c = array_map( 'trim', explode( '|', $l ) );
				if ( count( $c ) >= 2 ) { $info[ $c[0] ] = $c[1]; }
			}
			foreach ( $info as $k => $v ) {
				if ( $v ) { echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>'; }
			}
			?>
		</dl>
	</div></section>

	<!-- 11b. Signs + harms -->
	<?php if ( $signs ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">علامات الإصابة</span><h2>كيف تعرف أنك تحتاج هذه الخدمة؟</h2><p>إذا لاحظت أياً منها، تواصل معنا لفحص مجاني.</p></header>
		<div class="cardgrid cardgrid--2">
			<?php foreach ( $signs as $w ) : ?><div class="icard icard--row"><span class="icard__ic"><?php echo zad_icon( 'search', 24 ); // phpcs:ignore ?></span><div><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>
	<?php if ( $harms ) : ?>
	<section class="sec sec--cream"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">الأضرار المحتملة</span><h2>لماذا لا تؤجّل المعالجة؟</h2></header>
		<div class="cardgrid">
			<?php foreach ( $harms as $w ) : ?><div class="icard icard--warn"><span class="icard__ic"><?php echo zad_icon( 'bolt', 24 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<?php $ctx( 1 ); ?>
	<!-- 11c. Safety -->
	<?php if ( $safety || $after ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow"><?php echo zad_icon( 'shield', 14 ); // phpcs:ignore ?> الأمان أولاً</span><h2>آمن لمن تحب</h2></header>
		<?php if ( $safety ) : ?><div class="cardgrid"><?php foreach ( $safety as $w ) : ?><div class="icard"><span class="icard__ic"><?php echo zad_icon( 'shield', 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div><?php endforeach; ?></div><?php endif; ?>
		<?php if ( $after ) : ?><div class="after"><h3>إرشادات ما بعد الخدمة</h3><ul><?php foreach ( $after as $l ) { echo '<li>' . zad_icon( 'check', 18 ) . '<span>' . esc_html( $l ) . '</span></li>'; } // phpcs:ignore ?></ul></div><?php endif; ?>
	</div></section>
	<?php endif; ?>

	<?php $ctx( 0 ); ?>
	<!-- 11d. Packages -->
	<?php if ( $pkgs ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">باقات الحماية</span><h2>اختر الباقة المناسبة</h2></header>
		<div class="pkgs">
			<?php foreach ( $pkgs as $i => $pk ) : ?>
				<div class="pkg<?php echo 1 === $i ? ' pkg--hot' : ''; ?>">
					<?php if ( 1 === $i ) : ?><span class="pkg__flag">الأكثر طلباً</span><?php endif; ?>
					<h3><?php echo esc_html( $pk['name'] ); ?></h3>
					<div class="pkg__price"><?php echo esc_html( $pk['price'] ); ?></div>
					<ul><?php foreach ( $pk['feat'] as $f ) { echo '<li>' . zad_icon( 'check', 18 ) . '<span>' . esc_html( $f ) . '</span></li>'; } // phpcs:ignore ?></ul>
					<a class="btn <?php echo 1 === $i ? 'btn--accent' : 'btn--ghost-dark'; ?> btn--block" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بباقة: ' . $pk['name'] . ' - ' . $title, $id ) ); ?>" target="_blank" rel="noopener">اختر هذه الباقة</a>
				</div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 11e. Warranty -->
	<?php if ( $wrows ) : ?>
	<section class="sec sec--cream"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow"><?php echo zad_icon( 'badge', 14 ); // phpcs:ignore ?> الضمان</span><h2>ضمان مكتوب وموثّق</h2></header>
		<div class="wrows"><?php foreach ( $wrows as $w ) : ?><div class="wrow"><span class="icard__ic"><?php echo zad_icon( 'shield', 24 ); // phpcs:ignore ?></span><div><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div></div><?php endforeach; ?></div>
	</div></section>
	<?php endif; ?>

	<!-- 12. Before / after -->
	<?php if ( count( $ba_ids ) >= 2 ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قبل وبعد</span><h2>نتائج حقيقية لـ <?php echo esc_html( $title ); ?></h2></header>
		<div class="sgrid">
			<?php for ( $i = 0; $i + 1 < count( $ba_ids ); $i += 2 ) :
				$cap = isset( $ba_text[ $i / 2 ] ) ? array_map( 'trim', explode( '|', $ba_text[ $i / 2 ] ) ) : array( '', '' ); ?>
				<figure class="ba" data-ba>
					<div class="ba__stage">
						<?php echo wp_get_attachment_image( $ba_ids[ $i + 1 ], 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after' ) ); ?>
						<div class="ba__before"><?php echo wp_get_attachment_image( $ba_ids[ $i ], 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
						<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
						<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
					</div>
					<?php if ( ! empty( $cap[0] ) ) : ?><figcaption><b><?php echo esc_html( $cap[0] ); ?></b><?php if ( ! empty( $cap[1] ) ) : ?><br><span><?php echo esc_html( $cap[1] ); ?></span><?php endif; ?></figcaption><?php endif; ?>
				</figure>
			<?php endfor; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<?php if ( $video ) : ?>
	<section class="sec sec--dark" id="video"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">شاهد الفرق بنفسك</span><h2>شاهد خدماتنا عن قرب</h2></header>
		<div class="vframe" data-video="<?php echo esc_url( $video ); ?>"<?php echo $thumb ? ' style="--poster:url(\'' . esc_url( wp_get_attachment_image_url( $thumb, 'large' ) ) . '\')"' : ''; ?>>
			<button type="button" class="vframe__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5v14l11-7Z"/></svg></button>
		</div>
	</div></section>
	<?php endif; ?>

	<?php if ( $gallery ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">من أعمالنا</span><h2>صور من التنفيذ</h2></header>
		<?php if ( $gallery ) : ?><div class="gal"><?php foreach ( array_slice( array_values( $gallery ), 2 ) ?: $gallery as $gid ) : ?><a href="<?php echo esc_url( wp_get_attachment_image_url( $gid, 'full' ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?></a><?php endforeach; ?></div><?php endif; ?>
	</div></section>
	<?php endif; ?>

	<!-- 13a. B2B sectors -->
	<?php $sectors = array_filter( (array) zad_opt( 'zad_sectors', array() ), function ( $c ) { return ! empty( $c['name'] ); } );
	if ( $sectors ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قطاع الأعمال</span><h2>شركات تثق بنا</h2></header>
		<div class="b2b">
			<?php foreach ( $sectors as $sct ) : ?>
				<div class="b2b__s"><h3><?php echo esc_html( $sct['name'] ); ?></h3>
				<?php $names = zad_lines( $sct['names'] ?? '' ); if ( $names ) : ?><ul><?php foreach ( $names as $n ) { echo '<li>' . esc_html( $n ) . '</li>'; } ?></ul><?php else : ?><p><?php echo esc_html( $sct['note'] ?? '' ); ?></p><?php endif; ?></div>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 13. Clients -->
	<?php $clients = array_filter( (array) zad_opt( 'zad_clients', array() ), function ( $c ) { return ! empty( $c['name'] ); } );
	if ( $clients ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">عملاؤنا</span><h2>ثقة جهات وشركات كبرى</h2></header>
		<div class="clients">
			<?php foreach ( $clients as $c ) : ?><div class="client"><b><?php echo esc_html( $c['name'] ); ?></b><span><?php echo esc_html( $c['note'] ?? '' ); ?></span></div><?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 14. Coverage -->
	<?php if ( $area_names ) : ?>
	<section class="sec sec--cream"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">تغطيتنا</span><h2>نصل إليك في أي منطقة</h2></header>
		<ul class="chips"><?php foreach ( $areas as $t ) : ?><li><a href="<?php echo esc_url( 0 === (int) $t->parent ? zad_city_url( $id, $t ) : get_term_link( $t ) ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $t->name ); ?></a></li><?php endforeach; ?></ul>
	</div></section>
	<?php endif; ?>

	<!-- 15. FAQ -->
	<?php if ( $faq ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">الأسئلة الشائعة</span><h2>كل ما تريد معرفته</h2></header>
		<?php zad_render_faq( $faq ); ?>
	</div></section>
	<?php endif; ?>

	<!-- 15a. Related detailed questions -->
	<?php $rf = zad_service_faqs( $id, 6 );
	if ( $rf->have_posts() ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">شبكة الأسئلة</span><h2>أسئلة تفصيلية ذات صلة</h2></header>
		<div class="faqlinks faqlinks--grid"><?php while ( $rf->have_posts() ) { $rf->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '"><span>' . esc_html( get_the_title() ) . '</span>' . zad_icon( 'arrow', 18 ) . '</a>'; } wp_reset_postdata(); // phpcs:ignore ?></div>
		<p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_faq_url() ); ?>">كل الأسئلة</a></p>
	</div></section>
	<?php endif; ?>

	<!-- 15b. Guides -->
	<?php $gp = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 4, 'ignore_sticky_posts' => 1, 'no_found_rows' => true ) );
	if ( $gp->have_posts() ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">أدلة تهمّك</span><h2>مقالات ونصائح مفيدة</h2></header>
		<div class="sgrid"><?php while ( $gp->have_posts() ) { $gp->the_post(); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?></div>
		<p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ) ); ?>">كل المقالات</a></p>
	</div></section>
	<?php endif; ?>

	<?php echo function_exists( 'zad_bridges_html' ) ? zad_bridges_html( $id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<!-- 16. Related + final quote -->
	<?php
	$rel = zad_related_services( $id, 6 );
	if ( $rel->have_posts() ) : ?>
	<section class="sec sec--mint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قد يهمك أيضاً</span><h2>خدمات ومقالات ذات صلة</h2></header>
		<div class="sgrid"><?php while ( $rel->have_posts() ) { $rel->the_post(); get_template_part( 'template-parts/' . ( in_array( get_post_type(), zad_service_types(), true ) ? 'service-card' : 'post-card' ) ); } wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<section class="sec sec--dark" id="quote"><div class="wrap qfinal">
		<div>
			<span class="eyebrow">اطلب الآن</span>
			<h2>احصل على عرض سعر لـ <?php echo esc_html( $title ); ?> اليوم</h2>
			<p><?php echo esc_html( $provider ); ?> — جاهزون لخدمتك<?php echo $area_names ? ' في ' . esc_html( implode( '، ', array_slice( $area_names, 0, 3 ) ) ) : ''; ?>.</p>
			<div class="hero__btns">
				<?php if ( $phone ) : ?><a class="btn btn--accent" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" dir="ltr"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> <?php echo esc_html( $phone ); ?></a><?php endif; ?>
				<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
			</div>
		</div>
		<?php $fin_a = ( 'q30' === get_post_meta( $id, '_zad_final_req', true ) ) ? zad_q30_html( $id, 'sec-a' ) : ''; if ( '' !== $fin_a ) : echo $fin_a; // phpcs:ignore
		else : ?>
		<?php echo zad_quote_form( array( 'service_id' => $id, 'id' => 'fq', 'title' => 'اترك بياناتك ونتصل بك', 'sub' => 'رد خلال دقائق', 'area' => $city ? $city->name : '' ) ); // phpcs:ignore ?>
		<?php endif; ?>
	</div></section>
</main>
<?php endwhile; get_footer(); ?>
