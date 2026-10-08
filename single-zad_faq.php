<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id    = get_the_ID();
	$cats  = get_the_terms( $id, 'faq_cat' );
	$cr    = array( array( 'الرئيسية', home_url( '/' ) ), array( 'الأسئلة', zad_faq_url() ) );
	if ( $cats && ! is_wp_error( $cats ) ) { $cr[] = array( $cats[0]->name, get_term_link( $cats[0] ) ); }
	$cr[]  = array( get_the_title(), '' );
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
			$albl = get_post_meta( $id, '_zad_faq_article_lbl', true ) ?: 'اقرأ الدليل كاملاً';
			if ( $aid ) : ?>
			<div class="fork fork--one">
				<a class="fork__t fork__t--art" href="<?php echo esc_url( get_permalink( $aid ) ); ?>">
					<span class="fork__n">01</span>
					<span class="fork__k">لقراءة شرح أكثر · <?php echo esc_html( zad_reading_time( $aid ) ); ?></span>
					<strong><?php echo esc_html( zad_card_title( $aid ) ); ?></strong>
					<span class="fork__go"><?php echo esc_html( $albl ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span>
				</a>
			</div>
			<?php endif; ?>
			<div class="prose entry-content"><?php echo zad_content_with_box( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p class="meta-line">آخر تحديث: <?php echo esc_html( get_the_modified_date() ); ?></p>

			<?php echo zad_link_button_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php
			$rq = array( 'post_type' => zad_faq_types(), 'posts_per_page' => 6, 'post__not_in' => array( $id ), 'no_found_rows' => true );
			if ( $cats && ! is_wp_error( $cats ) ) { $rq['tax_query'] = array( array( 'taxonomy' => 'faq_cat', 'terms' => wp_list_pluck( $cats, 'term_id' ) ) ); }
			$rel = new WP_Query( $rq );
			if ( $rel->have_posts() ) : ?>
				<h2 class="h-line" style="margin-top:36px">أسئلة ذات صلة</h2>
				<div class="faqlinks"><?php while ( $rel->have_posts() ) { $rel->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '"><span>' . esc_html( zad_card_title() ) . '</span>' . zad_icon( 'arrow', 18 ) . '</a>'; } wp_reset_postdata(); // phpcs:ignore ?></div>
			<?php endif; ?>
		</article>
		<aside class="slayout__side"><div class="sticky" id="quote">
			<?php $fwa = zad_wa_link( 'مرحباً، عندي سؤال بخصوص: ' . get_the_title(), 0 ); $fph = zad_phone( 0 ); ?>
			<div class="faq-ask">
				<h3>ما زال عندك سؤال؟</h3>
				<p>اتصل بنا أو راسلنا على واتساب ويرد عليك أحد المختصين مباشرة.</p>
				<?php if ( $fph ) : ?><a class="btn btn--primary btn--block" href="<?php echo esc_url( zad_tel_href( $fph ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل بنا</a><?php endif; ?>
				<?php if ( $fwa ) : ?><a class="btn btn--wa btn--block" href="<?php echo esc_url( $fwa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> كلّمنا على واتساب</a><?php endif; ?>
			</div>
		</div></aside>
	</div>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
