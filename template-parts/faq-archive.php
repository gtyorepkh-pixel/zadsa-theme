<?php defined( 'ABSPATH' ) || exit;
$term = is_tax() ? get_queried_object() : null;
$cats = get_terms( array( 'taxonomy' => 'faq_cat', 'hide_empty' => true ) );
$cr   = array( array( 'الرئيسية', home_url( '/' ) ), array( 'الأسئلة', $term ? zad_faq_url() : '' ) );
if ( $term ) { $cr[] = array( $term->name, '' ); }
get_template_part( 'template-parts/page-hero', null, array( 'title' => $term ? $term->name : 'الأسئلة الشائعة', 'sub' => '<p>' . esc_html( $term && $term->description ? $term->description : 'إجابات واضحة على أكثر ما يسألنا عنه عملاؤنا.' ) . '</p>', 'crumbs' => $cr ) );
?>
<main id="main" class="sec"><?php echo zad_archive_intro_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?><div class="wrap">
	<div class="filters">
		<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
			<nav class="chips chips--filter" aria-label="أقسام الأسئلة">
				<a class="<?php echo ! $term ? 'is-on' : ''; ?>" href="<?php echo esc_url( zad_faq_url() ); ?>">الكل</a>
				<?php foreach ( $cats as $c ) : ?><a class="<?php echo $term && $term->term_id === $c->term_id ? 'is-on' : ''; ?>" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a><?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<form class="filters__search" role="search" method="get" action="<?php echo esc_url( zad_faq_url() ); ?>">
			<label class="sr" for="fq-q">ابحث في الأسئلة</label>
			<input id="fq-q" type="search" name="q" value="<?php echo esc_attr( isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '' ); // phpcs:ignore ?>" placeholder="ابحث في الأسئلة">
			<button type="submit" aria-label="بحث"><?php echo zad_icon( 'search', 18 ); // phpcs:ignore ?></button>
		</form>
	</div>
	<?php if ( have_posts() ) : ?>
		<div class="faqlinks faqlinks--grid"><?php while ( have_posts() ) { the_post(); echo '<a href="' . esc_url( get_permalink() ) . '"><span>' . esc_html( get_the_title() ) . '</span>' . zad_icon( 'arrow', 18 ) . '</a>'; } // phpcs:ignore ?></div>
		<?php echo memo_pagination(); // phpcs:ignore ?>
	<?php else : ?><p class="empty">لا توجد أسئلة مطابقة.</p><?php endif; ?>
</div></main>
<section class="sec sec--tint" id="quote"><div class="wrap wrap--narrow"><?php echo zad_quote_form( array( 'id' => 'fa', 'title' => 'لم تجد إجابتك؟ اسألنا' ) ); // phpcs:ignore ?></div></section>
<?php get_template_part( 'template-parts/cta-band' );
