<?php defined( 'ABSPATH' ) || exit; /* Template Name: الأسعار الشاملة */
get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'title'  => 'خطط الأسعار',
	'sub'    => '<p><span class="eyebrow-inv">شفافية كاملة</span></p><p>أسعار حقيقية لأهم خدماتنا في مكان واحد. الأسعار استرشادية، والسعر النهائي يُحدَّد بدقة بعد معاينة مجانية لحالتك الفعلية.</p>',
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ),
) );
$svcs = get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
$rows = array();
foreach ( $svcs as $sv ) {
	$p = zad_parse_prices( get_post_meta( $sv->ID, '_zad_prices', true ) );
	if ( ! $p ) { continue; }
	$min  = zad_min_price( $p ) ?: (int) get_post_meta( $sv->ID, '_zad_price', true );
	$unit = get_post_meta( $sv->ID, '_zad_price_unit', true ) ?: 'ريال';
	$rows[ $sv->ID ] = array( 'p' => $sv, 'rows' => $p, 'min' => $min, 'unit' => $unit, 'cats' => implode( ',', (array) wp_get_post_terms( $sv->ID, 'service_cat', array( 'fields' => 'ids' ) ) ) );
}
$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0 ) );
?>
<main id="main" class="sec">
	<div class="wrap pricewrap">
		<?php if ( $rows ) : ?>

		<?php if ( $cats && ! is_wp_error( $cats ) && count( $cats ) > 1 ) : ?>
			<div class="tabs" role="tablist" data-tabs>
				<button type="button" class="tabs__b is-on" data-tab="all">الكل</button>
				<?php foreach ( $cats as $c ) : ?><button type="button" class="tabs__b" data-tab="<?php echo (int) $c->term_id; ?>"><?php echo esc_html( $c->name ); ?></button><?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- 1) Overview table -->
		<div class="tbl tbl--sum">
			<table>
				<thead><tr><th>الخدمة</th><th>يبدأ من</th><th>التفاصيل</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $sid => $r ) : ?>
					<tr class="tabitem" data-cats="<?php echo esc_attr( $r['cats'] ); ?>">
						<td data-l="الخدمة"><a href="<?php echo esc_url( get_permalink( $r['p'] ) ); ?>"><b>أسعار <?php echo esc_html( zad_service_base_name( $sid ) ); ?></b></a></td>
						<td data-l="يبدأ من"><?php echo $r['min'] ? '<b class="pricenum">' . esc_html( number_format_i18n( $r['min'] ) ) . '</b> ' . esc_html( $r['unit'] ) : 'بعد المعاينة'; ?></td>
						<td><a class="btn btn--ghost-dark btn--sm" href="#svc-<?php echo (int) $sid; ?>">عرض الجدول <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="tbl__note"><?php echo zad_icon( 'shield', 16 ); // phpcs:ignore ?> الأسعار تقديرية وتختلف حسب الحالة — تواصل معنا للعرض الدقيق.</p>

		<!-- 2) Detailed tables -->
		<?php foreach ( $rows as $sid => $r ) : $sv = $r['p']; $has_det = (bool) array_filter( wp_list_pluck( $r['rows'], 'details' ) ); ?>
			<section class="pricebox tabitem" id="svc-<?php echo (int) $sid; ?>" data-cats="<?php echo esc_attr( $r['cats'] ); ?>">
				<header class="pricebox__head">
					<h2 class="h-line"><a href="<?php echo esc_url( get_permalink( $sv ) ); ?>">أسعار <?php echo esc_html( zad_service_base_name( $sid ) ); ?></a></h2>
					<span class="pricebox__links"><a class="more" href="<?php echo esc_url( get_permalink( $sv ) ); ?>">التفاصيل الكاملة <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
					<a class="btn btn--wa btn--sm" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بخدمة: ' . $sv->post_title, $sid ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 18 ); // phpcs:ignore ?> اطلب الخدمة</a></span>
				</header>
				<div class="tbl"><table>
					<thead><tr><th>الخدمة</th><?php if ( $has_det ) : ?><th>التفاصيل</th><?php endif; ?><th>السعر</th></tr></thead>
					<tbody>
					<?php $last = null; foreach ( $r['rows'] as $row ) :
						if ( $row['group'] && $row['group'] !== $last ) { echo '<tr class="tbl__grp"><td colspan="3">' . esc_html( $row['group'] ) . '</td></tr>'; $last = $row['group']; } ?>
						<tr><td data-l="الخدمة"><?php echo esc_html( $row['name'] ); ?></td><?php if ( $has_det ) : ?><td data-l="التفاصيل" class="tbl__det"><?php echo esc_html( $row['details'] ); ?></td><?php endif; ?><td data-l="السعر"><b><?php echo esc_html( $row['price'] ); ?></b></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php if ( get_post_meta( $sid, '_zad_price_note', true ) ) : ?><p class="tbl__note"><?php echo esc_html( get_post_meta( $sid, '_zad_price_note', true ) ); ?></p><?php endif; ?>
			</section>
		<?php endforeach; ?>

		<?php else : ?><p class="empty">أضف «قائمة الأسعار» داخل شاشة تحرير كل خدمة لتظهر هنا.</p><?php endif; ?>
		<div class="prose entry-content"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div>
	</div>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
