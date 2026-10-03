<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id    = get_the_ID();
	$cats  = get_the_terms( $id, 'faq_cat' );
	$cr    = array( array( 'الرئيسية', home_url( '/' ) ), array( 'الأسئلة', zad_faq_url() ) );
	if ( $cats && ! is_wp_error( $cats ) ) { $cr[] = array( $cats[0]->name, get_term_link( $cats[0] ) ); }
	$cr[]  = array( get_the_title(), '' );
	$sids  = array_map( 'intval', (array) get_post_meta( $id, '_zad_faq_services', true ) );
	$short = has_excerpt() ? get_the_excerpt() : '';
	get_template_part( 'template-parts/page-hero', null, array( 'title' => get_the_title(), 'crumbs' => $cr ) );
	?>
<main id="main" class="sec">
	<div class="wrap slayout">
		<article class="slayout__main">
			<?php if ( $short ) : ?><div class="answer"><span class="eyebrow" data-nosnippet>الإجابة المختصرة</span><p><?php echo esc_html( zad_clean_answer( $short ) ); ?></p></div><?php endif; ?>
			<?php
			$more = trim( (string) get_post_meta( $id, '_zad_faq_more', true ) );
			if ( $more ) : ?><div class="faq-more"><?php echo wp_kses_post( wpautop( esc_html( $more ) ) ); ?></div><?php endif;
			$aid = (int) get_post_meta( $id, '_zad_faq_article', true );
			$aid = ( $aid && get_post_status( $aid ) === 'publish' ) ? $aid : 0;
			$sid = $sids ? (int) $sids[0] : 0;
			$sid = ( $sid && get_post_status( $sid ) === 'publish' ) ? $sid : 0;
			$albl = get_post_meta( $id, '_zad_faq_article_lbl', true ) ?: 'اقرأ الدليل كاملاً';
			if ( $aid || $sid ) : ?>
			<div class="fork<?php echo ( $aid && $sid ) ? '' : ' fork--one'; ?>">
				<?php if ( $sid ) : ?>
				<a class="fork__t fork__t--svc" href="<?php echo esc_url( get_permalink( $sid ) ); ?>">
					<span class="fork__n">01</span>
					<span class="fork__k">هل تحتاج الخدمة؟</span>
					<strong><?php echo esc_html( get_the_title( $sid ) ); ?></strong>
					<span class="fork__go">تفاصيل الخدمة والسعر <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span>
				</a>
				<?php endif; if ( $aid ) : ?>
				<a class="fork__t fork__t--art" href="<?php echo esc_url( get_permalink( $aid ) ); ?>">
					<span class="fork__n">0<?php echo $sid ? '2' : '1'; ?></span>
					<span class="fork__k">لقراءة شرح أكثر · <?php echo esc_html( zad_reading_time( $aid ) ); ?></span>
					<strong><?php echo esc_html( get_the_title( $aid ) ); ?></strong>
					<span class="fork__go"><?php echo esc_html( $albl ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span>
				</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="prose entry-content"><?php the_content(); ?></div>
			<p class="meta-line">آخر تحديث: <?php echo esc_html( get_the_modified_date() ); ?></p>

			<?php if ( $sids ) :
				$sv = new WP_Query( array( 'post_type' => zad_service_types(), 'post__in' => $sids, 'posts_per_page' => 3, 'orderby' => 'post__in', 'no_found_rows' => true ) );
				if ( $sv->have_posts() ) : ?>
				<h2 class="h-line">الخدمة المرتبطة</h2>
				<div class="sgrid"><?php while ( $sv->have_posts() ) { $sv->the_post(); get_template_part( 'template-parts/service-card' ); } wp_reset_postdata(); ?></div>
			<?php endif; endif; ?>

			<?php
			$rq = array( 'post_type' => zad_faq_types(), 'posts_per_page' => 6, 'post__not_in' => array( $id ), 'no_found_rows' => true );
			if ( $cats && ! is_wp_error( $cats ) ) { $rq['tax_query'] = array( array( 'taxonomy' => 'faq_cat', 'terms' => wp_list_pluck( $cats, 'term_id' ) ) ); }
			$rel = new WP_Query( $rq );
			if ( $rel->have_posts() ) : ?>
				<h2 class="h-line" style="margin-top:36px">أسئلة ذات صلة</h2>
				<div class="faqlinks"><?php while ( $rel->have_posts() ) { $rel->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '"><span>' . esc_html( get_the_title() ) . '</span>' . zad_icon( 'arrow', 18 ) . '</a>'; } wp_reset_postdata(); // phpcs:ignore ?></div>
			<?php endif; ?>
		</article>
		<aside class="slayout__side"><div class="sticky" id="quote">
			<?php echo zad_quote_form( array( 'id' => 'fq1', 'compact' => true, 'title' => 'ما زال عندك سؤال؟', 'sub' => 'اترك رقمك ونتصل بك', 'service_id' => $sids ? $sids[0] : 0 ) ); // phpcs:ignore ?>
		</div></aside>
	</div>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
