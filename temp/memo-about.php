<?php defined( 'ABSPATH' ) || exit; /* Template Name: about */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
$groups = (array) zad_opt( 'memopt_about_sec2_grp', array() );
?>
<main id="main">
	<section class="sec">
		<div class="wrap split">
			<div class="prose"><?php echo wp_kses_post( wpautop( zad_opt( 'memopt_about_sec1_h' ) ) ); ?></div>
			<div class="split__img"><?php echo wp_get_attachment_image( zad_opt( 'memopt_about_sec1_img', array() )['id'] ?? 0, 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
		</div>
	</section>
	<?php if ( $groups ) : ?>
	<section class="sec sec--tint">
		<div class="wrap why">
			<?php foreach ( $groups as $g ) : ?>
				<div class="why__item">
					<?php if ( ! empty( $g['memopt_about_sec2_grp_img']['id'] ) ) { echo wp_get_attachment_image( $g['memopt_about_sec2_grp_img']['id'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); } ?>
					<h3><?php echo esc_html( $g['memopt_about_sec2_grp_h'] ?? '' ); ?></h3>
					<p><?php echo esc_html( $g['memopt_about_sec2_grp_p'] ?? '' ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
	<section class="sec">
		<div class="wrap split split--rev">
			<div class="split__img"><?php echo wp_get_attachment_image( zad_opt( 'memopt_about_sec3_img', array() )['id'] ?? 0, 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
			<div class="prose"><?php echo wp_kses_post( wpautop( zad_opt( 'memopt_about_sec3_h' ) ) ); ?></div>
		</div>
	</section>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
