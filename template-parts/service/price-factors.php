<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 9. Pricing factors + CTA box -->
	<?php if ( $factors && ! zad_parse_packages( $m( 'packages' ) ) ) : // with packages the factors live inside the packages section (one price H2) ?>
	<section class="sec sec--tint"><div class="wrap pf">
		<div class="pf__main">
			<?php $fs = zad_sec( $id, 'factors' ); ?>
			<span class="eyebrow"><?php echo esc_html( $fs['eyebrow'] ); ?></span>
			<h2><?php echo esc_html( $fs['title'] ); ?></h2>
			<?php if ( '' !== $fs['lead'] ) : ?><p><?php echo esc_html( $fs['lead'] ); ?></p><?php endif; ?>
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
