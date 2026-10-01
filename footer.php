<?php defined( 'ABSPATH' ) || exit;
$phone = zad_phone( is_singular( 'zad_service' ) ? get_the_ID() : 0 );
$wa    = zad_wa_link( is_singular( 'zad_service' ) ? 'مرحباً، أرغب بطلب خدمة: ' . get_the_title() : 'مرحباً، أرغب بطلب خدمة', is_singular( 'zad_service' ) ? get_the_ID() : 0 );
$cats  = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'number' => 8, 'parent' => 0 ) );
?>
<footer class="ftr">
	<div class="wrap ftr__grid">
		<div class="ftr__about">
			<h3><?php bloginfo( 'name' ); ?></h3>
			<?php echo wp_kses_post( wpautop( zad_opt( 'memopt_footer_h' ) ) ); ?>
			<ul class="ftr__contact">
				<?php if ( zad_opt( 'memopt_phone' ) ) : ?><li><?php echo zad_icon( 'phone', 18 ); // phpcs:ignore ?><a href="<?php echo esc_url( zad_tel_href( zad_opt( 'memopt_phone' ) ) ); ?>" dir="ltr"><?php echo esc_html( zad_opt( 'memopt_phone' ) ); ?></a></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_mail' ) ) : ?><li><?php echo zad_icon( 'mail', 18 ); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( zad_opt( 'memopt_mail' ) ); ?>"><?php echo esc_html( zad_opt( 'memopt_mail' ) ); ?></a></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_address' ) ) : ?><li><?php echo zad_icon( 'pin', 18 ); // phpcs:ignore ?><span><?php echo esc_html( zad_opt( 'memopt_address' ) ); ?></span></li><?php endif; ?>
			</ul>
		</div>
		<div>
			<h3>أهم الروابط</h3>
			<?php wp_nav_menu( array( 'theme_location' => 'footermenu', 'container' => false, 'menu_class' => 'ftr__list', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1, 'fallback_cb' => false ) ); ?>
		</div>
		<div>
			<h3>خدماتنا</h3>
			<?php
			if ( has_nav_menu( 'footerinfo' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footerinfo', 'container' => false, 'menu_class' => 'ftr__list', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1 ) );
			} elseif ( $cats && ! is_wp_error( $cats ) ) {
				echo '<ul class="ftr__list">';
				foreach ( $cats as $c ) {
					echo '<li><a href="' . esc_url( get_term_link( $c ) ) . '">' . esc_html( $c->name ) . '</a></li>';
				}
				echo '</ul>';
			}
			?>
		</div>
		<div>
			<h3>تواصل اجتماعي</h3>
			<div class="social">
				<?php
				foreach ( array( 'memopt_fb' => array( 'facebook', 'فيسبوك' ), 'memopt_tw' => array( 'x', 'إكس' ), 'memopt_insta' => array( 'instagram', 'إنستغرام' ), 'memopt_yt' => array( 'youtube', 'يوتيوب' ) ) as $k => $v ) {
					$u = zad_opt( $k );
					if ( $u && preg_match( '#^https?://#', $u ) ) {
						echo '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $v[1] ) . '">' . zad_icon( $v[0], 20 ) . '</a>'; // phpcs:ignore
					}
				}
				?>
			</div>
		</div>
	</div>
	<div class="ftr__copy"><div class="wrap">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> — جميع الحقوق محفوظة</div></div>
</footer>

<?php if ( $phone || $wa ) : ?>
<div class="dock" role="complementary" aria-label="تواصل سريع">
	<?php if ( $phone ) : ?><a class="dock__btn dock__btn--call" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 22 ); // phpcs:ignore ?><span>اتصل الآن</span></a><?php endif; ?>
	<?php if ( $wa ) : ?><a class="dock__btn dock__btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 22 ); // phpcs:ignore ?><span>واتساب</span></a><?php endif; ?>
	<a class="dock__btn dock__btn--quote" href="#quote" data-scroll-quote><?php echo zad_icon( 'bolt', 22 ); // phpcs:ignore ?><span>عرض سعر</span></a>
</div>
<button type="button" class="totop" data-totop aria-label="العودة للأعلى"><?php echo zad_icon( 'up', 22 ); // phpcs:ignore ?></button>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
