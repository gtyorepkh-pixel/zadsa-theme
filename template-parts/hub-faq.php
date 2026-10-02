<?php defined( 'ABSPATH' ) || exit;
/** Knowledge-base hub for the FAQ type: live search + questions grouped by service. */
$all = new WP_Query( array( 'post_type' => zad_faq_types(), 'post_status' => 'publish', 'posts_per_page' => 400, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC' ) );
$groups = array(); // service id => list
foreach ( $all->posts as $f ) {
	$l = array_map( 'intval', zad_faq_linked( $f->ID ) );
	$k = $l ? $l[0] : 0;
	$groups[ $k ][] = $f;
}
// services with questions first (by count), general last
uasort( $groups, function ( $a, $b ) { return count( $b ) <=> count( $a ); } );
if ( isset( $groups[0] ) ) { $g0 = $groups[0]; unset( $groups[0] ); $groups[0] = $g0; }
$total = count( $all->posts );
?>
<main id="main" class="kb">
	<section class="kbhero">
		<div class="wrap">
			<?php zad_render_crumbs( array( array( 'الرئيسية', home_url( '/' ) ), array( 'الأسئلة', '' ) ) ); ?>
			<h1><?php echo esc_html( zad_opt( 'zad_kb_title', 'مركز المساعدة' ) ); ?></h1>
			<p><?php echo esc_html( zad_opt( 'zad_kb_lead', 'ابحث عن إجابتك بين مئات الأسئلة، أو تصفّح الأسئلة حسب الخدمة.' ) ); ?></p>
			<div class="kbsearch"><?php echo zad_icon( 'search', 22 ); // phpcs:ignore ?><input type="search" data-kb-search placeholder="اكتب سؤالك أو كلمة مفتاحية…" aria-label="بحث في الأسئلة" autocomplete="off"><span class="kbsearch__n" data-kb-count><?php echo (int) $total; ?></span></div>
			<?php if ( count( $groups ) > 1 ) : ?>
			<div class="kbtopics">
				<?php foreach ( $groups as $sid => $list ) : if ( ! $sid ) { continue; } ?>
					<a href="#kb-<?php echo (int) $sid; ?>"><?php echo esc_html( zad_service_base_name( $sid ) ); ?> <i><?php echo count( $list ); ?></i></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php zad_archive_source(); ?>

	<div class="wrap kbbody">
		<?php if ( $total ) : foreach ( $groups as $sid => $list ) : ?>
			<section class="kbgroup" id="kb-<?php echo (int) $sid; ?>" data-kb-group>
				<header class="kbgroup__h">
					<h2><?php echo $sid ? esc_html( zad_service_base_name( $sid ) ) : 'أسئلة عامة'; ?> <small><?php echo count( $list ); ?></small></h2>
					<?php if ( $sid ) : ?><a class="more" href="<?php echo esc_url( get_permalink( $sid ) ); ?>">صفحة الخدمة <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a><?php endif; ?>
				</header>
				<div class="kbq-list">
					<?php foreach ( $list as $f ) : $ans = has_excerpt( $f ) ? get_the_excerpt( $f ) : wp_trim_words( wp_strip_all_tags( $f->post_content ), 36 ); ?>
						<details class="kbq" data-kb-item data-s="<?php echo esc_attr( zad_ar_norm( $f->post_title . ' ' . $ans ) ); ?>">
							<summary><span><?php echo esc_html( get_the_title( $f ) ); ?></span><?php echo zad_icon( 'chevron', 20 ); // phpcs:ignore ?></summary>
							<div class="kbq__a"><p><?php echo esc_html( $ans ); ?></p><a class="more" href="<?php echo esc_url( get_permalink( $f ) ); ?>">اقرأ الإجابة كاملة <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a></div>
						</details>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; else : ?><p class="empty">لا توجد أسئلة منشورة بعد.</p><?php endif; ?>
		<p class="empty" data-kb-empty hidden>لا نتائج مطابقة. جرّب كلمة أخرى أو <button type="button" class="linkbtn" data-open-wizard>اسألنا مباشرة</button>.</p>
	</div>
	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
