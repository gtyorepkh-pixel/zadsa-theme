<?php defined( 'ABSPATH' ) || exit; ?>
<article class="scard scard--post">
	<a class="scard__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'zad-card', array( 'loading' => 'lazy', 'alt' => '' ) ); } else { echo '<span class="scard__ph">' . zad_icon( 'sparkle', 56 ) . '</span>'; } // phpcs:ignore ?>
	</a>
	<div class="scard__body">
		<span class="scard__cat"><?php echo esc_html( get_the_date() ); ?></span>
		<h3 class="scard__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="scard__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
		<a class="more" href="<?php the_permalink(); ?>">اقرأ المزيد <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
	</div>
</article>
