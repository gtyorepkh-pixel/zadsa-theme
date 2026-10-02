<?php defined( 'ABSPATH' ) || exit; /* Template Name: Services */
get_header();
$all = get_posts( array( 'post_type' => zad_service_types(), 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
$groups = array();
foreach ( $all as $p ) {
	$t = get_the_terms( $p->ID, 'service_cat' );
	$t = ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
	if ( ! $t ) {
		$o = get_post_type_object( $p->post_type );
		$key = 'pt-' . $p->post_type; $name = $o ? $o->labels->name : 'خدمات أخرى'; $desc = ''; $url = get_post_type_archive_link( $p->post_type );
	} else {
		$key = 'c-' . $t->term_id; $name = $t->name; $desc = $t->description; $url = get_term_link( $t );
	}
	if ( ! isset( $groups[ $key ] ) ) { $groups[ $key ] = array( 'name' => $name, 'desc' => $desc, 'url' => is_wp_error( $url ) ? '' : $url, 'items' => array() ); }
	$groups[ $key ]['items'][] = $p;
}
$total = count( $all );
get_template_part( 'template-parts/page-hero', null, array(
	'sub'    => wp_kses_post( wpautop( has_excerpt() ? get_the_excerpt() : 'كل ما تحتاجه لبيتك أو منشأتك في مكان واحد: تنظيف ومكافحة ونقل. اختر الخدمة، أو اطلب معاينة مجانية ونحدد لك الأنسب.' ) ),
	'crumbs' => zad_current_crumbs(),
) );
?>
<main id="main" class="svcs">
	<section class="sec svcs__top"><div class="wrap">
		<div class="svcs__bar">
			<nav class="chips chips--filter" aria-label="أقسام الخدمات" data-svc-filter>
				<button type="button" class="is-on" data-f="*">الكل <small><?php echo (int) $total; ?></small></button>
				<?php foreach ( $groups as $k => $g ) : ?><button type="button" data-f="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $g['name'] ); ?> <small><?php echo (int) count( $g['items'] ); ?></small></button><?php endforeach; ?>
			</nav>
			<label class="svcs__search"><span class="sr">ابحث في الخدمات</span><?php echo zad_icon( 'search', 18 ); // phpcs:ignore ?><input type="search" placeholder="ابحث عن خدمة…" data-svc-search autocomplete="off"></label>
		</div>
	</div></section>

	<?php foreach ( $groups as $k => $g ) : ?>
	<section class="sec svcs__grp" data-grp="<?php echo esc_attr( $k ); ?>">
		<div class="wrap">
			<header class="svcs__head">
				<div><h2><?php echo esc_html( $g['name'] ); ?></h2><?php if ( $g['desc'] ) : ?><p><?php echo esc_html( wp_strip_all_tags( $g['desc'] ) ); ?></p><?php endif; ?></div>
				<?php if ( $g['url'] ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $g['url'] ); ?>">كل خدمات <?php echo esc_html( $g['name'] ); ?> ←</a><?php endif; ?>
			</header>
			<div class="sgrid">
				<?php foreach ( $g['items'] as $post ) : setup_postdata( $post ); ?>
					<div class="svcs__it" data-t="<?php echo esc_attr( mb_strtolower( wp_strip_all_tags( get_the_title() ) . ' ' . get_post_meta( $post->ID, '_zad_tagline', true ), 'UTF-8' ) ); ?>"><?php get_template_part( 'template-parts/service-card' ); ?></div>
				<?php endforeach; wp_reset_postdata(); ?>
			</div>
		</div>
	</section>
	<?php endforeach; ?>
	<?php if ( ! $all ) : ?><section class="sec"><div class="wrap"><p class="empty">لم تُضَف خدمات بعد.</p></div></section><?php endif; ?>
	<p class="svcs__none wrap" data-svc-none hidden>لا توجد خدمة بهذا الاسم. اطلب معاينة مجانية وسنحدد لك الخدمة المناسبة.</p>

	<section class="sec sec--tint"><div class="wrap wrap--narrow svcs__help">
		<h2>لست متأكداً أي خدمة تناسبك؟</h2>
		<p>صف لنا المشكلة، وسنحدد لك الخدمة والموعد قبل الزيارة، بمعاينة مجانية وبدون التزام.</p>
		<p><button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب معاينة مجانية</button>
		<?php if ( zad_wa_link( 'x' ) ) : ?> <a class="btn btn--wa" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أحتاج مساعدة في اختيار الخدمة' ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?></p>
	</div></section>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
