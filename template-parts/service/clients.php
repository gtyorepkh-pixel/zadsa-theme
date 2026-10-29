<?php defined( 'ABSPATH' ) || exit; ?>

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
