<?php defined( 'ABSPATH' ) || exit;
$phone = zad_phone();
$wa    = zad_wa_link( 'مرحباً، أرغب بطلب خدمة' );
?>
<section class="cta">
	<div class="wrap cta__in">
		<div>
			<h2><?php echo esc_html( zad_opt( 'zad_cta_title', 'جاهزون لخدمتك الآن' ) ); ?></h2>
			<p><?php echo esc_html( zad_opt( 'zad_cta_sub', 'تواصل معنا وسيصلك الفني مع عرض سعر واضح قبل البدء.' ) ); ?></p>
		</div>
		<div class="cta__btns">
			<?php if ( $phone ) : ?><a class="btn btn--accent" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل الآن</a><?php endif; ?>
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
		</div>
	</div>
</section>
