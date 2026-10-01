<?php defined( 'ABSPATH' ) || exit; /* Template Name: Gallery */
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'sub'    => wp_kses_post( wpautop( zad_opt( 'memopt_gallery_sec1_h' ) ) ),
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ),
) );
$items = (array) zad_opt( 'memopt_gallery_grp', array() );
?>
<main id="main" class="sec">
	<div class="wrap">
		<div class="sgrid">
			<?php foreach ( $items as $it ) :
				$b = $it['memopt_gallery_grp_img_before']['id'] ?? 0;
				$a = $it['memopt_gallery_grp_img_after']['id'] ?? 0;
				if ( ! $b || ! $a ) { continue; } ?>
				<figure class="ba" data-ba>
					<div class="ba__stage">
						<?php echo wp_get_attachment_image( $a, 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after' ) ); ?>
						<div class="ba__before"><?php echo wp_get_attachment_image( $b, 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
						<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
						<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
					</div>
					<?php if ( ! empty( $it['memopt_gallery_grp_h'] ) ) : ?>
						<figcaption><?php if ( ! empty( $it['memopt_gallery_grp_link'] ) ) : ?><a href="<?php echo esc_url( $it['memopt_gallery_grp_link'] ); ?>"><?php echo esc_html( $it['memopt_gallery_grp_h'] ); ?></a><?php else : echo esc_html( $it['memopt_gallery_grp_h'] ); endif; ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
