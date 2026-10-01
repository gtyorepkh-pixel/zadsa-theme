<?php defined( 'ABSPATH' ) || exit;
$id    = get_the_ID();
$price = get_post_meta( $id, '_zad_price', true );
$unit  = get_post_meta( $id, '_zad_price_unit', true ) ?: 'ريال';
$badge = get_post_meta( $id, '_zad_badge', true );
$icon  = get_post_meta( $id, '_zad_icon', true ) ?: 'sparkle';
$rate  = get_post_meta( $id, '_zad_rating', true );
$warr  = get_post_meta( $id, '_zad_warranty', true );
$terms = get_the_terms( $id, 'service_cat' );
$wa    = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . get_the_title(), $id );
?>
<article class="scard">
	<a class="scard__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'zad-card', array( 'loading' => 'lazy', 'alt' => '' ) );
		} else { ?>
			<span class="scard__ph"><?php echo zad_icon( $icon, 56 ); // phpcs:ignore ?></span>
		<?php } ?>
		<?php if ( $badge ) : ?><span class="scard__badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
	</a>
	<div class="scard__body">
		<?php if ( $terms && ! is_wp_error( $terms ) ) : ?><span class="scard__cat"><?php echo esc_html( $terms[0]->name ); ?></span><?php endif; ?>
		<h3 class="scard__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="scard__desc"><?php echo esc_html( get_post_meta( $id, '_zad_tagline', true ) ?: wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
		<ul class="scard__meta">
			<?php if ( $rate ) : ?><li><?php echo zad_icon( 'star', 16 ); // phpcs:ignore ?> <?php echo esc_html( $rate ); ?></li><?php endif; ?>
			<?php if ( $warr ) : ?><li><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> <?php echo esc_html( $warr ); ?></li><?php endif; ?>
		</ul>
		<div class="scard__foot">
			<span class="scard__price"><?php if ( $price ) : ?><small>يبدأ من</small> <b><?php echo esc_html( number_format_i18n( $price ) ); ?></b> <?php echo esc_html( $unit ); ?><?php else : ?><small>السعر بعد المعاينة</small><?php endif; ?></span>
			<span class="scard__actions">
				<?php if ( $wa ) : ?><a class="iconbtn iconbtn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="واتساب"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?></a><?php endif; ?>
				<a class="iconbtn" href="<?php the_permalink(); ?>" aria-label="التفاصيل"><?php echo zad_icon( 'arrow', 20 ); // phpcs:ignore ?></a>
			</span>
		</div>
	</div>
</article>
