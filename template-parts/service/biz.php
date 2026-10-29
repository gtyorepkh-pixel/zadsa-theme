<?php defined( 'ABSPATH' ) || exit;
/** New layout: company data from the site settings (the same values the LocalBusiness schema prints). Prints only with a street address. */
$zad_b   = function_exists( 'zsc_settings' ) ? (array) ( zsc_settings()['business'] ?? array() ) : array();
$zad_adr = trim( implode( '، ', array_filter( array( $zad_b['street'] ?? '', $zad_b['locality'] ?? '', $zad_b['postal_code'] ?? '' ) ) ) );
if ( '' !== $zad_adr ) :
	$zad_map = trim( (string) zad_opt( 'zad_map_url' ) );
	if ( '' === $zad_map && ! empty( $zad_b['lat'] ) && ! empty( $zad_b['lng'] ) ) { $zad_map = 'https://www.google.com/maps?q=' . rawurlencode( $zad_b['lat'] . ',' . $zad_b['lng'] ); } // same point the LocalBusiness hasMap uses
	$zad_cr  = trim( (string) zad_opt( 'zad_cr' ) );
	$zad_hrs = trim( (string) zad_opt( 'zad_hours' ) );
	?>
	<!-- Company data + map link (new layout) -->
	<section class="sec sec--tint" id="biz"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">من نحن وأين نخدم</span><h2>بيانات الشركة وموقعنا</h2></header>
		<div class="biz"><div class="biz__card"><ul class="biz__list">
			<?php if ( ! empty( $zad_b['name'] ) ) : ?><li><b>الشركة</b><span><?php echo esc_html( $zad_b['name'] ); ?></span></li><?php endif; ?>
			<?php if ( '' !== $first_city ) : ?><li><b>نطاق الخدمة</b><span><?php echo esc_html( $first_city ); ?></span></li><?php endif; ?>
			<li><b>العنوان</b><span><?php echo esc_html( $zad_adr ); ?></span></li>
			<?php if ( $phone ) : ?><li><b>الهاتف</b><span dir="ltr"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></span></li><?php endif; ?>
			<?php if ( '' !== $zad_hrs ) : ?><li><b>ساعات العمل</b><span><?php echo esc_html( $zad_hrs ); ?></span></li><?php endif; ?>
			<?php if ( '' !== $zad_cr ) : ?><li><b>السجل التجاري</b><span><?php echo esc_html( $zad_cr ); ?></span></li><?php endif; ?>
		</ul></div>
		<?php if ( '' !== $zad_map ) : ?><div class="biz__map"><div class="biz__pin"><?php echo zad_icon( 'pin', 36 ); // phpcs:ignore ?></div><a class="btn btn--accent" href="<?php echo esc_url( $zad_map ); ?>" target="_blank" rel="noopener">افتح موقعنا على خرائط جوجل</a></div><?php endif; ?>
		</div>
	</div></section>
<?php endif; ?>
