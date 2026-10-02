<?php defined( 'ABSPATH' ) || exit;
$zq = ( isset( $args['query'] ) && $args['query'] instanceof WP_Query ) ? $args['query'] : $GLOBALS['wp_query'];
/** Shared by services archive and service taxonomies. */
$term    = is_tax() ? get_queried_object() : null;
$cats    = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0 ) );
$current = ( $term && 'service_cat' === $term->taxonomy ) ? $term->term_id : 0;
$crumbs  = array( array( 'الرئيسية', home_url( '/' ) ) );
if ( $term ) {
	$crumbs[] = array( 'الخدمات', zad_services_url() );
	$crumbs[] = array( $term->name, '' );
} elseif ( is_post_type_archive() && empty( $args['hub'] ) && ! post_type_exists( 'zad_service' ) ) {
	$crumbs[] = array( 'الخدمات', zad_services_url() );
	$crumbs[] = array( post_type_archive_title( '', false ), '' );
} else {
	$crumbs[] = array( 'الخدمات', '' );
}
if ( $term && 'service_area' === $term->taxonomy ) {
	$title = 'خدماتنا في ' . $term->name;
	$sub   = $term->description ?: 'نقدم جميع خدماتنا في ' . $term->name . ' بفنيين معتمدين وضمان على التنفيذ.';
} elseif ( $term ) {
	$title = $term->name;
	$sub   = $term->description;
} elseif ( is_post_type_archive() && empty( $args['hub'] ) && ! post_type_exists( 'zad_service' ) ) {
	$title = post_type_archive_title( '', false );
	$sub   = 'اختر الخدمة التي تحتاجها واطلب عرض سعر مجاني خلال دقائق.';
	$tt    = get_term_by( 'slug', sanitize_title( zad_type_base( zad_query_pt() ) ), 'service_cat' );
	$current = $tt ? $tt->term_id : 0;
} else {
	$title = 'جميع الخدمات';
	$sub   = 'اختر الخدمة التي تحتاجها واطلب عرض سعر مجاني خلال دقائق.';
}
get_template_part( 'template-parts/page-hero', null, array( 'title' => $title, 'sub' => wpautop( esc_html( $sub ) ), 'crumbs' => $crumbs ) );
?>
<main id="main" class="sec">
	<?php zad_archive_source(); ?>
	<div class="wrap">
		<div class="filters">
			<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
				<nav class="chips chips--filter" aria-label="أقسام الخدمات">
					<a class="<?php echo ! $current ? 'is-on' : ''; ?>" href="<?php echo esc_url( zad_services_url() ); ?>">الكل</a>
					<?php foreach ( $cats as $c ) : ?>
						<a class="<?php echo $current === $c->term_id ? 'is-on' : ''; ?>" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<form class="filters__search" role="search" method="get" action="<?php echo esc_url( zad_services_url() ); ?>">
				<label class="sr" for="sq-q">ابحث في الخدمات</label>
				<input id="sq-q" type="search" name="q" value="<?php echo esc_attr( isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '' ); // phpcs:ignore ?>" placeholder="ابحث في الخدمات">
				<button type="submit" aria-label="بحث"><?php echo zad_icon( 'search', 18 ); // phpcs:ignore ?></button>
			</form>
		</div>

		<?php if ( $zq->have_posts() ) : ?>
			<div class="sgrid">
				<?php while ( $zq->have_posts() ) { $zq->the_post(); get_template_part( 'template-parts/service-card' ); } wp_reset_postdata(); ?>
			</div>
			<?php echo zad_pagination( $zq ); // phpcs:ignore ?>
		<?php else : ?>
			<p class="empty">لا توجد خدمات مطابقة حالياً.</p>
		<?php endif; ?>
	</div>
</main>
<section class="sec sec--tint" id="quote">
	<div class="wrap wrap--narrow"><?php echo zad_quote_form( array( 'id' => 'aq', 'title' => 'لم تجد ما تبحث عنه؟ أرسل طلبك', 'area' => ( $term && 'service_area' === $term->taxonomy ) ? $term->name : '' ) ); // phpcs:ignore ?></div>
</section>
<?php get_template_part( 'template-parts/cta-band' ); ?>
