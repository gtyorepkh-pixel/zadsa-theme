<?php defined( 'ABSPATH' ) || exit; /* Template Name: الأسعار الشاملة */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'sub' => '<p>أسعار كل خدماتنا في مكان واحد. السعر النهائي يُؤكَّد بعد المعاينة المجانية.</p>', 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
$svcs = get_posts( array( 'post_type' => zad_service_types(), 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
$rows = array();
foreach ( $svcs as $sv ) {
	$p = zad_parse_prices( get_post_meta( $sv->ID, '_zad_prices', true ) );
	if ( $p ) { $rows[ $sv->ID ] = array( $sv, $p ); }
}
?>
<main id="main" class="sec">
	<div class="wrap">
		<?php if ( $rows ) : ?>
		<nav class="chips chips--start" aria-label="الخدمات"><?php foreach ( $rows as $sid => $r ) : ?><a href="#svc-<?php echo (int) $sid; ?>" class="chipa"><?php echo esc_html( zad_service_base_name( $sid ) ); ?></a><?php endforeach; ?></nav>
		<?php foreach ( $rows as $sid => $r ) : $sv = $r[0]; $has_det = (bool) array_filter( wp_list_pluck( $r[1], 'details' ) ); ?>
			<section class="pricebox" id="svc-<?php echo (int) $sid; ?>">
				<header class="pricebox__head"><h2 class="h-line"><a href="<?php echo esc_url( get_permalink( $sv ) ); ?>"><?php echo esc_html( $sv->post_title ); ?></a></h2>
					<a class="btn btn--wa" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بخدمة: ' . $sv->post_title, $sid ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب الخدمة</a></header>
				<div class="tbl"><table>
					<thead><tr><th>الخدمة</th><?php if ( $has_det ) : ?><th>التفاصيل</th><?php endif; ?><th>السعر</th></tr></thead>
					<tbody>
					<?php $last = null; foreach ( $r[1] as $row ) :
						if ( $row['group'] && $row['group'] !== $last ) { echo '<tr class="tbl__grp"><td colspan="3">' . esc_html( $row['group'] ) . '</td></tr>'; $last = $row['group']; } ?>
						<tr><td data-l="الخدمة"><?php echo esc_html( $row['name'] ); ?></td><?php if ( $has_det ) : ?><td data-l="التفاصيل" class="tbl__det"><?php echo esc_html( $row['details'] ); ?></td><?php endif; ?><td data-l="السعر"><b><?php echo esc_html( $row['price'] ); ?></b></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php if ( get_post_meta( $sid, '_zad_price_note', true ) ) : ?><p class="tbl__note"><?php echo esc_html( get_post_meta( $sid, '_zad_price_note', true ) ); ?></p><?php endif; ?>
			</section>
		<?php endforeach; else : ?><p class="empty">أضف «قائمة الأسعار» داخل شاشة تحرير كل خدمة لتظهر هنا.</p><?php endif; ?>
		<div class="prose entry-content"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div>
	</div>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
