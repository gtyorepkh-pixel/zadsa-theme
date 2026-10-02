<?php defined( 'ABSPATH' ) || exit;
/** Content-library hub for article types (e.g. /guide/, /sections/): featured guide + topic filters + editorial grid. */
$pt    = get_query_var( 'post_type' );
$pto   = get_post_type_object( is_string( $pt ) ? $pt : 'post' );
$label = zad_ac_title( $pto ? $pto->labels->name : 'المقالات' );
$hub   = zad_hub_opt( is_string( $pt ) ? $pt : '' );
$title = ! empty( $hub['headline'] ) ? $hub['headline'] : ( zad_opt( 'zad_lib_title', 'مكتبة المعرفة' ) . ' — ' . $label );
$lead  = ! empty( $hub['lead'] ) ? $hub['lead'] : zad_opt( 'zad_lib_lead', 'أدلة عملية ونصائح من خبراء الميدان.' );
$tax   = '';
foreach ( get_object_taxonomies( is_string( $pt ) ? $pt : 'post', 'objects' ) as $k => $o ) { if ( $o->public && ! in_array( $k, array( 'service_cat', 'service_area', 'faq_cat', 'post_format' ), true ) ) { $tax = $k; break; } }
$terms = $tax ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true, 'number' => 14 ) ) : array();
$paged = max( 1, (int) get_query_var( 'paged' ) );
$first = ( 1 === $paged && have_posts() ) ? $GLOBALS['wp_query']->posts[0] : null;
?>
<main id="main" class="lib">
	<section class="libhero">
		<div class="wrap">
			<?php zad_render_crumbs( array( array( 'الرئيسية', home_url( '/' ) ), array( $label, '' ) ) ); ?>
			<span class="pill"><?php echo zad_icon( 'list', 18 ); // phpcs:ignore ?> مكتبة</span>
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $lead ); ?></p>
		</div>
	</section>
	<?php zad_archive_source(); ?>
	<div class="wrap libbody">
		<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
			<nav class="chips chips--filter" aria-label="المواضيع"><a class="is-on" href="<?php echo esc_url( get_post_type_archive_link( $pt ) ); ?>">الكل</a><?php foreach ( $terms as $t ) : ?><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a><?php endforeach; ?></nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<?php if ( $first ) :
				$svc = (int) get_post_meta( $first->ID, '_zad_post_service', true ); ?>
				<article class="libfeat">
					<a class="libfeat__img" href="<?php echo esc_url( get_permalink( $first ) ); ?>" tabindex="-1" aria-hidden="true"><?php if ( has_post_thumbnail( $first ) ) { echo get_the_post_thumbnail( $first, 'large', array( 'loading' => 'eager', 'alt' => '' ) ); } else { echo '<span class="libfeat__ph">' . zad_icon( 'list', 70 ) . '</span>'; } // phpcs:ignore ?></a>
					<div class="libfeat__b">
						<span class="eyebrow">الأحدث</span>
						<h2><a href="<?php echo esc_url( get_permalink( $first ) ); ?>"><?php echo esc_html( get_the_title( $first ) ); ?></a></h2>
						<p><?php echo esc_html( wp_trim_words( has_excerpt( $first ) ? $first->post_excerpt : wp_strip_all_tags( $first->post_content ), 36 ) ); ?></p>
						<p class="libmeta"><span><?php echo zad_icon( 'clock', 16 ); // phpcs:ignore ?> <?php echo esc_html( zad_reading_time( $first ) ); ?> دقائق قراءة</span><span><?php echo esc_html( get_the_date( '', $first ) ); ?></span><?php if ( $svc ) : ?><span class="libtag"><?php echo esc_html( zad_service_base_name( $svc ) ); ?></span><?php endif; ?></p>
						<a class="btn btn--primary" href="<?php echo esc_url( get_permalink( $first ) ); ?>">اقرأ الدليل</a>
					</div>
				</article>
			<?php endif; ?>

			<div class="libgrid">
				<?php $i = 0; while ( have_posts() ) { the_post(); $i++; if ( $first && 1 === $i && 1 === $paged ) { continue; } $svc = (int) get_post_meta( get_the_ID(), '_zad_post_service', true ); ?>
					<article class="libcard">
						<a class="libcard__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'zad-card', array( 'loading' => 'lazy', 'alt' => '' ) ); } else { echo '<span class="scard__ph">' . zad_icon( 'list', 44 ) . '</span>'; } // phpcs:ignore ?></a>
						<div class="libcard__b">
							<p class="libmeta"><span><?php echo zad_icon( 'clock', 14 ); // phpcs:ignore ?> <?php echo esc_html( zad_reading_time() ); ?> د</span><span><?php echo esc_html( get_the_date() ); ?></span></p>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
							<?php if ( $svc ) : ?><a class="libtag" href="<?php echo esc_url( get_permalink( $svc ) ); ?>"><?php echo zad_icon( 'tool', 14 ); // phpcs:ignore ?> <?php echo esc_html( zad_service_base_name( $svc ) ); ?></a><?php endif; ?>
						</div>
					</article>
				<?php } ?>
			</div>
			<?php echo memo_pagination(); // phpcs:ignore ?>
		<?php else : ?><p class="empty">لا يوجد محتوى منشور بعد.</p><?php endif; ?>
	</div>
	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
