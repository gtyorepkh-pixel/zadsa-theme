<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$cr = zad_current_crumbs();
	get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => $cr ) );
	$opts = get_post_meta( get_the_ID(), '_memo_metabox_options', true );
	$wa   = zad_wa_link( 'مرحباً، أرغب بالاستفسار عن: ' . get_the_title() );
	?>
<main id="main" class="sec">
	<div class="wrap slayout">
		<article class="slayout__main">
			<?php echo zad_author_byline(); // phpcs:ignore ?>
			<?php if ( has_post_thumbnail() ) : ?><div class="post-thumb"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
			<div class="prose entry-content"><?php the_content(); ?></div>
			<?php $psid = (int) get_post_meta( get_the_ID(), '_zad_post_service', true );
			if ( $psid && 'publish' === get_post_status( $psid ) ) : $psw = zad_wa_link( 'مرحباً، قرأت مقال: ' . get_the_title() . ' وأرغب بخدمة: ' . get_the_title( $psid ), $psid ); ?>
				<div class="svc-cta"><div><span class="eyebrow">الحل المناسب</span><h3><?php echo esc_html( get_the_title( $psid ) ); ?></h3><p><?php echo esc_html( get_post_meta( $psid, '_zad_tagline', true ) ?: wp_trim_words( get_the_excerpt( $psid ), 22 ) ); ?></p></div>
				<div class="svc-cta__b"><a class="btn btn--accent" href="<?php echo esc_url( get_permalink( $psid ) ); ?>">تفاصيل الخدمة</a><?php if ( $psw ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $psw ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?></div></div>
			<?php endif; ?>
			<?php echo zad_author_box(); // phpcs:ignore ?>
			<div class="footer-meta"><?php memo_tags_in(); ?></div>
			<?php echo memo_sharing_buttons(); // phpcs:ignore ?>
			<?php if ( zad_comments_enabled() && ( comments_open() || get_comments_number() ) ) { comments_template(); } ?>
		</article>
		<aside class="slayout__side">
			<div class="sticky" id="quote">
				<?php echo zad_quote_form( array( 'id' => 'pq', 'compact' => true, 'title' => 'تحتاج مساعدة؟ اطلب عرض سعر' ) ); // phpcs:ignore ?>
				<?php if ( $wa ) : ?><a class="callbox callbox--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 26 ); // phpcs:ignore ?><span><small>أو تواصل عبر</small><b>واتساب</b></span></a><?php endif; ?>
			</div>
		</aside>
	</div>
</main>
<?php endwhile;
$cur = get_queried_object_id();
$cc  = wp_get_post_categories( $cur );
$rp  = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => array( $cur ), 'category__in' => $cc, 'ignore_sticky_posts' => 1, 'no_found_rows' => true ) );
if ( $rp->have_posts() ) : ?>
<section class="sec sec--tint"><div class="wrap">
	<header class="sec__head"><span class="eyebrow">اقرأ أيضاً</span><h2>مقالات ذات صلة</h2></header>
	<div class="sgrid"><?php while ( $rp->have_posts() ) { $rp->the_post(); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?></div>
</div></section>
<?php endif;
get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
