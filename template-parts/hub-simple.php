<?php defined( 'ABSPATH' ) || exit;
/**
 * Section page: crumbs, H1, one short paragraph, city chips, a grid of the pages directly under it, two link lines, one request button.
 * Deliberately NOT here: prices, warranty, «choose by case», long text, FAQ, reviews, works (service pages only).
 */
$d = zad_hub_data();
if ( ! $d ) { return; }
$crumbs = zad_current_crumbs() ?: array( array( 'الرئيسية', home_url( '/' ) ), array( $d['h1'], '' ) );
$wa     = zad_wa_link( 'مرحباً، أستفسر عن: ' . $d['h1'], 0 );
?>
<main id="main" class="zhub">
	<section class="zhub__head">
		<div class="wrap">
			<?php zad_render_crumbs( $crumbs ); ?>
			<h1 class="zhub__h1"><?php echo esc_html( $d['h1'] ); ?></h1>
			<p class="zhub__lead"><?php echo esc_html( $d['lead'] ); ?></p>
			<?php if ( $d['chips'] ) : ?>
				<ul class="chips zhub__chips" aria-label="المدن">
					<?php foreach ( $d['chips'] as $c ) : ?><li><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $c['name'] ); ?></a></li><?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<section class="zhub__body">
		<div class="wrap">
			<?php if ( $d['cards'] ) : ?>
				<ul class="zhub__cards">
					<?php foreach ( $d['cards'] as $c ) : ?>
						<li>
							<a class="zhub__card" href="<?php echo esc_url( $c['url'] ); ?>">
								<?php if ( $c['city'] ) : ?><span class="zhub__city"><?php echo zad_icon( 'pin', 14 ); // phpcs:ignore ?> <?php echo esc_html( zad_city_label( $c['city'] ) ); ?></span><?php endif; ?>
								<h2 class="zhub__t"><?php echo esc_html( $c['title'] ); ?></h2>
								<?php if ( '' !== $c['tag'] ) : ?><span class="zhub__tag"><?php echo esc_html( $c['tag'] ); ?></span><?php endif; ?>
								<span class="zhub__go" aria-hidden="true"><?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $d['pages'] > 1 ) : ?>
					<nav class="pagination" aria-label="صفحات القائمة"><?php
					for ( $i = 1; $i <= $d['pages']; $i++ ) {
						echo $i === $d['paged'] ? '<span class="page-numbers current">' . (int) $i . '</span>' : '<a class="page-numbers" href="' . esc_url( get_pagenum_link( $i ) ) . '">' . (int) $i . '</a>'; // phpcs:ignore
					}
					?></nav>
				<?php endif; ?>
			<?php else : ?>
				<p class="empty">لا توجد صفحات منشورة في هذا القسم حالياً.</p>
			<?php endif; ?>

			<?php if ( $d['links'] ) : ?>
				<p class="zhub__links"><?php $i = 0; foreach ( $d['links'] as $l ) { echo ( $i++ ? ' <span aria-hidden="true">·</span> ' : '' ) . '<a href="' . esc_url( $l[1] ) . '">' . esc_html( $l[0] ) . '</a>'; } // phpcs:ignore ?></p>
			<?php endif; ?>

			<p class="zhub__cta">
				<?php if ( $wa ) : ?>
					<a class="btn btn--accent" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب الخدمة عبر واتساب</a>
				<?php else : ?>
					<button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب الخدمة</button>
				<?php endif; ?>
			</p>
		</div>
	</section>
</main>
