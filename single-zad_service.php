<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id       = get_the_ID();
	$m        = function ( $k ) use ( $id ) { return get_post_meta( $id, '_zad_' . $k, true ); };
	$tagline  = $m( 'tagline' );
	$price    = $m( 'price' );
	$unit     = $m( 'price_unit' ) ?: 'ريال';
	$features = zad_lines( $m( 'features' ) );
	$steps    = (array) $m( 'steps' );
	$faq      = (array) $m( 'faq' );
	$gallery  = array_filter( array_map( 'intval', explode( ',', (string) $m( 'gallery' ) ) ) );
	$video    = $m( 'video' );
	$rating   = $m( 'rating' );
	$reviews  = (int) $m( 'reviews' );
	$areas    = get_the_terms( $id, 'service_area' );
	$cta      = $m( 'cta_text' ) ?: 'اطلب الخدمة الآن';
	$phone    = zad_phone( $id );
	$wa       = zad_wa_link( 'مرحباً، أرغب بطلب خدمة: ' . get_the_title(), $id );
	$chips    = array_filter( array(
		array( 'shield', 'الضمان', $m( 'warranty' ) ),
		array( 'clock', 'مدة التنفيذ', $m( 'duration' ) ),
		array( 'bolt', 'الاستجابة', $m( 'response' ) ),
		array( 'badge', 'السعر', $price ? 'يبدأ من ' . number_format_i18n( $price ) . ' ' . $unit : '' ),
	), function ( $c ) { return ! empty( $c[2] ); } );
	$thumb = has_post_thumbnail() ? get_post_thumbnail_id() : 0;
	?>
<main id="main">
	<section class="shero">
		<div class="wrap shero__grid">
			<div class="shero__text">
				<?php zad_render_crumbs( zad_service_crumbs( $id ) ); ?>
				<h1><?php the_title(); ?></h1>
				<?php if ( $tagline ) : ?><p class="shero__tag"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
				<?php if ( $rating ) : ?>
					<p class="rate"><?php echo zad_stars( $rating ); // phpcs:ignore ?> <b><?php echo esc_html( $rating ); ?></b><?php if ( $reviews ) : ?> <span>(<?php echo esc_html( number_format_i18n( $reviews ) ); ?> تقييم)</span><?php endif; ?></p>
				<?php endif; ?>
				<?php if ( $chips ) : ?>
					<ul class="facts">
						<?php foreach ( $chips as $c ) : ?>
							<li><?php echo zad_icon( $c[0], 22 ); // phpcs:ignore ?><span><small><?php echo esc_html( $c[1] ); ?></small><b><?php echo esc_html( $c[2] ); ?></b></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<div class="hero__btns">
					<a class="btn btn--accent" href="#quote" data-scroll-quote><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> <?php echo esc_html( $cta ); ?></a>
					<?php if ( $phone ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل</a><?php endif; ?>
					<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
				</div>
			</div>
			<?php if ( $thumb ) : ?>
				<div class="shero__img"><?php echo wp_get_attachment_image( $thumb, 'large', false, array( 'loading' => 'eager', 'alt' => get_the_title() ) ); ?></div>
			<?php endif; ?>
		</div>
	</section>

	<div class="wrap slayout">
		<div class="slayout__main">
			<?php if ( $features ) : ?>
			<section class="blk">
				<h2>مميزات الخدمة</h2>
				<ul class="featgrid">
					<?php foreach ( $features as $f ) : ?><li><?php echo zad_icon( 'check', 20 ); // phpcs:ignore ?><span><?php echo esc_html( $f ); ?></span></li><?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>

			<section class="blk prose entry-content">
				<?php the_content(); ?>
			</section>

			<?php if ( $steps ) : ?>
			<section class="blk">
				<h2>خطوات التنفيذ</h2>
				<ol class="timeline">
					<?php foreach ( $steps as $i => $s ) : ?>
						<li><span class="timeline__n"><?php echo esc_html( $i + 1 ); ?></span><div><h3><?php echo esc_html( $s['t'] ); ?></h3><p><?php echo esc_html( $s['d'] ?? '' ); ?></p></div></li>
					<?php endforeach; ?>
				</ol>
			</section>
			<?php endif; ?>

			<?php if ( $gallery ) : ?>
			<section class="blk">
				<h2>من أعمالنا</h2>
				<div class="gal">
					<?php foreach ( $gallery as $gid ) : ?>
						<a href="<?php echo esc_url( wp_get_attachment_image_url( $gid, 'full' ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $video ) : ?>
			<section class="blk">
				<h2>شاهد كيف نعمل</h2>
				<div class="video"><?php echo wp_oembed_get( $video ) ?: '<video controls preload="none" src="' . esc_url( $video ) . '"></video>'; // phpcs:ignore ?></div>
			</section>
			<?php endif; ?>

			<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
			<section class="blk">
				<h2>مناطق تقديم الخدمة</h2>
				<ul class="chips">
					<?php foreach ( $areas as $t ) : ?><li><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $t->name ); ?></a></li><?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>

			<?php if ( $faq ) : ?>
			<section class="blk">
				<h2>الأسئلة الشائعة</h2>
				<?php zad_render_faq( $faq ); ?>
			</section>
			<?php endif; ?>
		</div>

		<aside class="slayout__side">
			<div class="sticky" id="quote">
				<?php echo zad_quote_form( array( 'service_id' => $id, 'id' => 'sq', 'title' => 'اطلب ' . get_the_title() ) ); // phpcs:ignore ?>
				<?php if ( $phone ) : ?>
					<a class="callbox" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 26 ); // phpcs:ignore ?><span><small>أو اتصل مباشرة</small><b dir="ltr"><?php echo esc_html( $phone ); ?></b></span></a>
				<?php endif; ?>
			</div>
		</aside>
	</div>

	<?php
	$rel = zad_related_services( $id, 3 );
	if ( $rel->have_posts() ) : ?>
	<section class="sec sec--tint">
		<div class="wrap">
			<header class="sec__head"><span class="eyebrow">قد يهمك أيضاً</span><h2>خدمات ذات صلة</h2></header>
			<div class="sgrid">
				<?php while ( $rel->have_posts() ) { $rel->the_post(); get_template_part( 'template-parts/service-card' ); } wp_reset_postdata(); ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
<?php endwhile; get_footer(); ?>
