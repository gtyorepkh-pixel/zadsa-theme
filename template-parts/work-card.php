<?php defined( 'ABSPATH' ) || exit;
/** A work card (archive, «أعمال من الميدان» on service pages, similar works). Uses the current post. */
$wid   = get_the_ID();
$img   = zad_wk_image_id( $wid );
$vid   = zad_wk_video( $wid );
list( $cat, , ) = zad_wk_terms( $wid );
$svc   = zad_wk_service( $wid );
$badge = $cat ? $cat->name : ( $svc ? zad_card_title( $svc ) : '' );
$place = zad_wk_place( $wid );
?>
<article class="scard scard--post zw-card">
	<a class="scard__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php if ( $img ) { echo wp_get_attachment_image( $img, 'zad-card', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $img, $wid ) ) ); } else { echo '<span class="scard__ph">' . zad_icon( 'sparkle', 56 ) . '</span>'; } // phpcs:ignore ?>
		<?php if ( $vid['kind'] ) : ?><span class="zw-badge zw-badge--video"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" aria-hidden="true"><path d="M8 5v14l11-7Z"/></svg> فيديو</span><?php endif; ?>
	</a>
	<div class="scard__body">
		<?php if ( $badge ) : ?><span class="scard__cat"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
		<h3 class="scard__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
		<?php if ( $place ) : ?><p class="zw-place"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $place ); ?></p><?php endif; ?>
		<a class="more" href="<?php the_permalink(); ?>">شاهد العمل <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
	</div>
</article>
