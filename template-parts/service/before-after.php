<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 12. Before / after -->
	<?php if ( count( $ba_ids ) >= 2 ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قبل وبعد</span><h2>نتائج حقيقية لـ <?php echo esc_html( $title ); ?></h2></header>
		<div class="sgrid">
			<?php for ( $i = 0; $i + 1 < count( $ba_ids ); $i += 2 ) :
				$cap = isset( $ba_text[ $i / 2 ] ) ? array_map( 'trim', explode( '|', $ba_text[ $i / 2 ] ) ) : array( '', '' ); ?>
				<figure class="ba" data-ba>
					<div class="ba__stage">
						<?php echo wp_get_attachment_image( $ba_ids[ $i + 1 ], 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after', 'alt' => zad_img_alt( $ba_ids[ $i + 1 ] ) ) ); ?>
						<div class="ba__before"><?php echo wp_get_attachment_image( $ba_ids[ $i ], 'large', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $ba_ids[ $i ] ) ) ); ?></div>
						<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
						<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
					</div>
					<?php if ( ! empty( $cap[0] ) ) : ?><figcaption><b><?php echo esc_html( $cap[0] ); ?></b><?php if ( ! empty( $cap[1] ) ) : ?><br><span><?php echo esc_html( $cap[1] ); ?></span><?php endif; ?></figcaption><?php endif; ?>
				</figure>
			<?php endfor; ?>
		</div>
	</div></section>
	<?php endif; ?>
