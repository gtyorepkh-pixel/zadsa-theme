<?php defined( 'ABSPATH' ) || exit;
$phone = zad_phone( zad_is_service() ? get_the_ID() : 0 );
$wa    = zad_wa_link( zad_is_service() ? 'مرحباً، أرغب بطلب خدمة: ' . get_the_title() : 'مرحباً، أرغب بطلب خدمة', zad_is_service() ? get_the_ID() : 0 );
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
				foreach ( array( 'memopt_fb' => array( 'facebook', 'فيسبوك' ), 'memopt_tw' => array( 'x', 'إكس' ), 'memopt_insta' => array( 'instagram', 'إنستغرام' ), 'zad_linkedin' => array( 'linkedin', 'لينكدإن' ), 'zad_pinterest' => array( 'pinterest', 'بينترست' ), 'zad_tiktok' => array( 'tiktok', 'تيك توك' ), 'zad_snapchat' => array( 'snapchat', 'سناب شات' ), 'memopt_yt' => array( 'youtube', 'يوتيوب' ) ) as $k => $v ) {
					$u = zad_opt( $k );
					if ( $u && preg_match( '#^https?://#', $u ) ) {
						echo '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $v[1] ) . '">' . zad_icon( $v[0], 20 ) . '</a>'; // phpcs:ignore
					}
				}
				?>
			</div>
		</div>
	</div>
	<?php if ( zad_opt( 'zad_cr' ) || zad_opt( 'zad_vat' ) ) : ?>
	<div class="wrap ftr__badges">
		<?php if ( zad_opt( 'zad_cr' ) ) : ?><div class="ftr__badge"><?php echo zad_icon( 'badge', 26 ); // phpcs:ignore ?><div><b>السجل التجاري</b><span dir="ltr"><?php echo esc_html( zad_opt( 'zad_cr' ) ); ?></span></div></div><?php endif; ?>
		<?php if ( zad_opt( 'zad_vat' ) ) : ?><div class="ftr__badge"><?php echo zad_icon( 'shield', 26 ); // phpcs:ignore ?><div><b>ضريبة القيمة المضافة</b><span dir="ltr"><?php echo esc_html( zad_opt( 'zad_vat' ) ); ?></span></div></div><?php endif; ?>
		<?php if ( zad_opt( 'zad_hours' ) ) : ?><div class="ftr__badge"><?php echo zad_icon( 'clock', 26 ); // phpcs:ignore ?><div><b>ساعات العمل</b><span><?php echo esc_html( zad_opt( 'zad_hours' ) ); ?></span></div></div><?php endif; ?>
	</div>
	<?php endif; ?>
	<?php if ( zad_opt( 'zad_legal_name' ) ) : ?><div class="wrap"><p class="ftr__legalname"><?php echo esc_html( zad_opt( 'zad_legal_name' ) ); ?></p></div><?php endif; ?>
	<div class="ftr__copy"><div class="wrap ftr__bottom"><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> — جميع الحقوق محفوظة</span><?php zad_legal_links(); ?></div></div>
</footer>

<?php if ( $phone || $wa ) : ?>
<div class="dock" role="complementary" aria-label="تواصل سريع">
	<?php if ( $phone ) : ?><a class="dock__btn dock__btn--call" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 22 ); // phpcs:ignore ?><span>اتصل الآن</span></a><?php endif; ?>
	<?php if ( $wa ) : ?><a class="dock__btn dock__btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 22 ); // phpcs:ignore ?><span>واتساب</span></a><?php endif; ?>
	<button type="button" class="dock__btn dock__btn--quote" data-open-wizard><?php echo zad_icon( 'bolt', 22 ); // phpcs:ignore ?><span>عرض سعر</span></button>
</div>
<div class="fab" data-fab>
	<div class="fab__actions" id="fab-actions">
		<button type="button" class="fab__a fab__a--book" data-open-wizard aria-label="اترك رسالة"><?php echo zad_icon( 'calendar', 22 ); // phpcs:ignore ?><span>اترك رسالة</span></button>
		<?php if ( $phone ) : ?><a class="fab__a fab__a--call" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" aria-label="اتصال"><?php echo zad_icon( 'phone', 22 ); // phpcs:ignore ?><span>اتصال</span></a><?php endif; ?>
		<?php if ( $wa ) : ?><a class="fab__a fab__a--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="واتساب"><?php echo zad_icon( 'whatsapp', 22 ); // phpcs:ignore ?><span>واتساب</span></a><?php endif; ?>
	</div>
	<button type="button" class="fab__main" data-fab-toggle aria-expanded="false" aria-controls="fab-actions" aria-label="تواصل معنا"><?php echo zad_icon( 'bolt', 26 ); // phpcs:ignore ?><span class="fab__badge">3</span></button>
</div>
<button type="button" class="totop" data-totop aria-label="العودة للأعلى"><?php echo zad_icon( 'up', 22 ); // phpcs:ignore ?></button>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
