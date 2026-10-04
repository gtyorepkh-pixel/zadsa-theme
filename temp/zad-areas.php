<?php defined( 'ABSPATH' ) || exit; /* Template Name: المناطق (كل المدن) */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'sub' => '<p>نغطي المدن والأحياء التالية — اختر مدينتك لتصفح الخدمات المتاحة فيها.</p>', 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
$cities = get_terms( array( 'taxonomy' => 'service_area', 'parent' => 0, 'hide_empty' => false ) );
$svcs   = get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
?>
<main id="main" class="sec">
	<div class="wrap">
		<?php if ( $cities && ! is_wp_error( $cities ) ) : foreach ( $cities as $c ) :
			$dist = get_terms( array( 'taxonomy' => 'service_area', 'parent' => $c->term_id, 'hide_empty' => false ) ); ?>
			<section class="areabox" id="<?php echo esc_attr( $c->slug ); ?>">
				<header class="areabox__head">
					<h2><?php echo zad_icon( 'pin', 26 ); // phpcs:ignore ?> <?php echo esc_html( $c->name ); ?></h2>
					<a class="btn btn--ghost-dark" href="<?php echo esc_url( get_term_link( $c ) ); ?>">كل خدمات <?php echo esc_html( $c->name ); ?></a>
				</header>
				<?php if ( $dist && ! is_wp_error( $dist ) ) : ?>
					<h3 class="areabox__t">أحياء مغطاة</h3>
					<ul class="chips chips--start"><?php foreach ( $dist as $d ) : ?><li><a href="<?php echo esc_url( get_term_link( $d ) ); ?>"><?php echo esc_html( $d->name ); ?></a></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<h3 class="areabox__t">الخدمات في <?php echo esc_html( $c->name ); ?></h3>
				<ul class="areabox__svcs">
					<?php foreach ( $svcs as $sv ) : if ( has_term( $c->term_id, 'service_area', $sv ) ) : ?>
						<li><a href="<?php echo esc_url( zad_area_page_url( $sv->ID, $c ) ?: get_permalink( $sv ) ); ?>"><?php echo zad_icon( 'check', 16 ); // phpcs:ignore ?> <?php echo esc_html( zad_service_base_name( $sv->ID ) . ' في ' . $c->name ); ?></a></li>
					<?php endif; endforeach; ?>
				</ul>
			</section>
		<?php endforeach; else : ?><p class="empty">أضف مدناً من «الخدمات ← المدن والأحياء».</p><?php endif; ?>
	</div>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
