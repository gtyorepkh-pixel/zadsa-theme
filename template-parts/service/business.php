<?php defined( 'ABSPATH' ) || exit; ?>

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
