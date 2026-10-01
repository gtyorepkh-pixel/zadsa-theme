<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$cat = get_the_category();
	$cr  = array( array( 'الرئيسية', home_url( '/' ) ) );
	if ( $cat ) { $cr[] = array( $cat[0]->name, get_category_link( $cat[0] ) ); }
	$cr[] = array( get_the_title(), '' );
	get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => $cr ) );
	$opts = get_post_meta( get_the_ID(), '_memo_metabox_options', true );
	$wa   = zad_wa_link( 'مرحباً، أرغب بالاستفسار عن: ' . get_the_title() );
	?>
<main id="main" class="sec">
	<div class="wrap slayout">
		<article class="slayout__main">
			<?php if ( has_post_thumbnail() ) : ?><div class="post-thumb"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
			<div class="prose entry-content"><?php the_content(); ?></div>
			<div class="footer-meta"><?php memo_tags_in(); ?></div>
			<?php echo memo_sharing_buttons(); // phpcs:ignore ?>
			<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
		</article>
		<aside class="slayout__side">
			<div class="sticky" id="quote">
				<?php echo zad_quote_form( array( 'id' => 'pq', 'compact' => true, 'title' => 'تحتاج مساعدة؟ اطلب عرض سعر' ) ); // phpcs:ignore ?>
				<?php if ( $wa ) : ?><a class="callbox callbox--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 26 ); // phpcs:ignore ?><span><small>أو تواصل عبر</small><b>واتساب</b></span></a><?php endif; ?>
			</div>
		</aside>
	</div>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
