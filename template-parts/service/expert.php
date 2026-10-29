<?php defined( 'ABSPATH' ) || exit;
/** New layout: who reviewed the technical content — the page author's profile (name, bio); hidden without a bio. */
$zad_uid = (int) get_post_field( 'post_author', $id );
if ( $zad_uid && function_exists( 'zad_author_has_bio' ) && zad_author_has_bio( $zad_uid ) ) :
	$zad_nm  = get_the_author_meta( 'display_name', $zad_uid );
	$zad_img = function_exists( 'zad_author_image' ) ? zad_author_image( $zad_uid ) : '';
	?>
	<!-- Content reviewer (new layout) -->
	<section class="sec"><div class="wrap wrap--narrow"><div class="expert">
		<?php if ( $zad_img ) : ?><img class="expert__av expert__av--img" src="<?php echo esc_url( $zad_img ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async"><?php else : ?><span class="expert__av" aria-hidden="true"><?php echo esc_html( mb_substr( $zad_nm, 0, 1 ) ); ?></span><?php endif; ?>
		<div><small>راجع المحتوى الفني</small><h3><a href="<?php echo esc_url( get_author_posts_url( $zad_uid ) ); ?>" rel="author"><?php echo esc_html( $zad_nm ); ?></a></h3><p><?php echo esc_html( zad_author_bio( $zad_uid ) ); ?></p></div>
	</div></div></section>
<?php endif; ?>
