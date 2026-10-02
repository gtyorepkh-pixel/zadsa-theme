<?php defined( 'ABSPATH' ) || exit; /* Template Name: خريطة الموقع */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
$list = function ( $items ) { echo '<ul class="smap">'; foreach ( $items as $u => $l ) { echo '<li><a href="' . esc_url( $u ) . '">' . esc_html( $l ) . '</a></li>'; } echo '</ul>'; };
?>
<main id="main" class="sec"><div class="wrap smapwrap">
	<?php
	$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true ) );
	if ( $cats && ! is_wp_error( $cats ) ) : foreach ( $cats as $c ) :
		$q = get_posts( array( 'post_type' => zad_service_types(), 'zad_all' => true, 'numberposts' => -1, 'tax_query' => array( array( 'taxonomy' => 'service_cat', 'terms' => $c->term_id ) ) ) );
		if ( ! $q ) { continue; }
		$it = array();
		foreach ( $q as $sv ) {
			$it[ get_permalink( $sv ) ] = $sv->post_title;
			foreach ( (array) get_the_terms( $sv->ID, 'service_area' ) as $t ) {
				if ( $t instanceof WP_Term && 0 === (int) $t->parent ) { $it[ zad_city_url( $sv->ID, $t ) ] = zad_city_title( $sv->ID, $t ); }
			}
		} ?>
		<section><h2><a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a></h2><?php $list( $it ); ?></section>
	<?php endforeach; endif; ?>

	<?php $faqs = get_posts( array( 'post_type' => zad_faq_types(), 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
	if ( $faqs ) : $it = array(); foreach ( $faqs as $f ) { $it[ get_permalink( $f ) ] = $f->post_title; } ?>
		<section><h2><a href="<?php echo esc_url( zad_faq_url() ); ?>">الأسئلة الشائعة</a></h2><?php $list( $it ); ?></section>
	<?php endif; ?>

	<?php $posts = get_posts( array( 'numberposts' => 60 ) );
	if ( $posts ) : $it = array(); foreach ( $posts as $p ) { $it[ get_permalink( $p ) ] = $p->post_title; } ?>
		<section><h2>المدونة</h2><?php $list( $it ); ?></section>
	<?php endif; ?>

	<?php $pages = get_pages( array( 'sort_column' => 'post_title' ) );
	if ( $pages ) : $it = array(); foreach ( $pages as $p ) { $it[ get_permalink( $p ) ] = $p->post_title; } ?>
		<section><h2>الصفحات</h2><?php $list( $it ); ?></section>
	<?php endif; ?>
</div></main>
<?php get_footer(); ?>
