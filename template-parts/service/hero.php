<?php defined( 'ABSPATH' ) || exit; ?>
<main id="main"<?php echo ( isset( $zad_layout ) && 'new' === $zad_layout ) ? ' class="svc--new"' : ''; ?>>

	<!-- 1. HERO + instant price estimator -->
	<section class="shero<?php echo $thumb ? ' shero--photo' : ''; ?>">
		<?php echo $thumb ? zad_hero_img( $thumb ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div class="wrap">
			<?php zad_render_crumbs( zad_current_crumbs() ); ?>
			<div class="shero__grid">
				<div class="shero__text">
					<?php $hbadge = trim( (string) $m( 'badge' ) ) ?: trim( (string) zad_opt( 'zad_provider', '' ) ); // page badge, else the explicit provider option; never the bare site name
					if ( $hbadge ) : ?><span class="pill"><?php echo zad_icon( 'badge', 18 ); // phpcs:ignore ?> <?php echo esc_html( $hbadge ); ?></span><?php endif; ?>
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
					<?php $hc = zad_hero_card( $id, (bool) $prices );
					if ( 'ticket' === $hc ) : echo zad_ticket_html( $id ); // phpcs:ignore
					elseif ( 'q30' === $hc ) : echo zad_q30_html( $id, 'hero-a' ); // phpcs:ignore
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
						<?php echo zad_ticket_html( $id ); // phpcs:ignore ?>
					<?php endif; ?>
					<?php $fc = ( 'ticket' === $hc ) ? array() : zad_float_cards( $id ); if ( $fc ) : ?>
					<ul class="fcards" aria-label="مزايا الخدمة"><?php foreach ( $fc as $n => $c ) : ?>
						<li class="fcard fcard--<?php echo (int) ( $n + 1 ); ?>"><span class="fcard__ic"><?php echo zad_icon( $c[2], 24 ); // phpcs:ignore ?></span><span class="fcard__tx"><b><?php echo esc_html( $c[0] ); ?></b><small><?php echo esc_html( $c[1] ); ?></small></span></li>
					<?php endforeach; ?></ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
