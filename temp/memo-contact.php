<?php defined( 'ABSPATH' ) || exit; /* Template Name: تواصل معنا */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
?>
<main id="main" class="sec">
	<div class="wrap contact">
		<div class="contact__info">
			<h2>معلومات التواصل</h2>
			<ul class="clist">
				<?php if ( zad_opt( 'memopt_phone' ) ) : ?><li><?php echo zad_icon( 'phone', 24 ); // phpcs:ignore ?><span><small>اتصل بنا</small><a href="<?php echo esc_url( zad_tel_href( zad_opt( 'memopt_phone' ) ) ); ?>" dir="ltr"><?php echo esc_html( zad_opt( 'memopt_phone' ) ); ?></a></span></li><?php endif; ?>
				<?php if ( zad_wa_link( 'x', 0 ) ) : ?><li><?php echo zad_icon( 'whatsapp', 24 ); // phpcs:ignore ?><span><small>واتساب</small><a href="<?php echo esc_url( zad_wa_link( '', 0 ) ); ?>" target="_blank" rel="noopener">ابدأ المحادثة</a></span></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_mail' ) ) : ?><li><?php echo zad_icon( 'mail', 24 ); // phpcs:ignore ?><span><small>البريد</small><a href="mailto:<?php echo esc_attr( zad_opt( 'memopt_mail' ) ); ?>"><?php echo esc_html( zad_opt( 'memopt_mail' ) ); ?></a></span></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_address' ) ) : ?><li><?php echo zad_icon( 'pin', 24 ); // phpcs:ignore ?><span><small>العنوان</small><?php echo esc_html( zad_opt( 'memopt_address' ) ); ?></span></li><?php endif; ?>
				<li><?php echo zad_icon( 'clock', 24 ); // phpcs:ignore ?><span><small>أوقات العمل</small><?php echo esc_html( zad_hours_text() ); ?></span></li>
			</ul>
		</div>
		<div id="quote"><?php echo zad_quote_form( array( 'id' => 'cq', 'title' => 'أرسل طلبك' ) ); // phpcs:ignore ?></div>
	</div>
	<?php $lat = (float) zad_opt( 'zad_lat' ); $lng = (float) zad_opt( 'zad_lng' );
	if ( $lat && $lng ) : $bb = ( $lng - 0.012 ) . ',' . ( $lat - 0.007 ) . ',' . ( $lng + 0.012 ) . ',' . ( $lat + 0.007 ); ?>
	<div class="wrap mapwrap">
		<iframe title="موقعنا على الخريطة" loading="lazy" referrerpolicy="no-referrer" src="<?php echo esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bb . '&layer=mapnik&marker=' . $lat . ',' . $lng ); ?>"></iframe>
		<?php if ( zad_opt( 'zad_map_url' ) ) : ?><p><a class="btn btn--primary" href="<?php echo esc_url( zad_opt( 'zad_map_url' ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'map', 20 ); // phpcs:ignore ?> افتح في خرائط Google</a></p><?php endif; ?>
	</div>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
