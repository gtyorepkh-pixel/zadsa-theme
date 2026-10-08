<?php defined( 'ABSPATH' ) || exit;
if ( ! empty( $args['lite'] ) ) : // minimal card (related sections) ?>
<article class="scard scard--lite scard--post"><a class="scard__lite" href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'zad-card', array( 'loading' => 'lazy', 'alt' => zad_img_alt( get_post_thumbnail_id(), get_the_ID() ) ) ); } ?><h3 class="scard__title"><?php echo esc_html( zad_card_title() ); ?></h3></a><p class="scard__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p></article>
<?php return; endif; ?>
<article class="scard scard--post">
	<a class="scard__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-label="<?php echo esc_attr( zad_card_title() ); ?>">
		<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'zad-card', array( 'loading' => 'lazy', 'alt' => zad_img_alt( get_post_thumbnail_id(), get_the_ID() ) ) ); } else { echo '<span class="scard__ph">' . zad_icon( 'sparkle', 56 ) . '</span>'; } // phpcs:ignore ?>
	</a>
	<div class="scard__body">
		<span class="scard__cat"><?php echo esc_html( get_the_date() ); ?></span>
		<h3 class="scard__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( zad_card_title() ); ?></a></h3>
		<p class="scard__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
		<a class="more" href="<?php the_permalink(); ?>">اقرأ المزيد <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
	</div>
</article>
