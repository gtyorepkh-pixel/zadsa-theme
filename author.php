<?php defined( 'ABSPATH' ) || exit;
get_header();
$uid  = get_queried_object_id();
$name = get_the_author_meta( 'display_name', $uid );
$bio  = zad_author_bio( $uid );
$img  = zad_author_image( $uid );
?>
<?php $about = is_paged() ? '' : zad_author_about_html( $uid ); ?>
<?php if ( '' !== $about ) : // the «عن الكاتب» content carries its own hero and H1: no second header ?>
<div class="wrap authorabout__crumbs"><?php zad_render_crumbs( zad_current_crumbs() ); ?></div>
<section class="authorabout"><div class="wrap"><?php echo $about; // phpcs:ignore WordPress.Security.EscapeOutput -- saved by a user who can edit this profile ?></div></section>
<?php else : ?>
<section class="phero authorhd">
	<div class="wrap">
		<?php zad_render_crumbs( zad_current_crumbs() ); ?>
		<div class="authorhd__in">
			<?php if ( $img ) : ?><img class="authorhd__img" src="<?php echo esc_url( $img ); ?>" alt="" width="112" height="112" decoding="async"><?php endif; ?>
			<div>
				<h1><?php echo esc_html( $name ); ?></h1>
				<?php if ( $bio ) : ?><p class="authorhd__bio"><?php echo esc_html( $bio ); ?></p><?php endif; ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>
<main id="main" class="sec">
	<div class="wrap">
		<?php if ( '' !== $about && have_posts() ) : ?><h2 class="h-line">مقالات <?php echo esc_html( $name ); ?></h2><?php endif; ?>
		<?php if ( have_posts() ) : ?>
			<div class="sgrid"><?php while ( have_posts() ) { the_post(); get_template_part( 'template-parts/author-card' ); } ?></div>
			<?php echo zad_pagination(); // phpcs:ignore ?>
		<?php else : ?>
			<p class="empty">لا توجد مقالات منشورة لهذا الكاتب بعد.</p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
