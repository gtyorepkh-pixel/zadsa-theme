<?php defined( 'ABSPATH' ) || exit;
$phone = zad_phone( zad_is_service() ? get_the_ID() : 0 );
$wa    = zad_wa_link( zad_is_service() ? 'مرحباً، أرغب بطلب خدمة: ' . get_the_title() : 'مرحباً، أرغب بطلب خدمة', zad_is_service() ? get_the_ID() : 0 );
$cats  = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'number' => 8, 'parent' => 0 ) );
?>
<footer class="ftr">
	<?php if ( $phone || $wa ) : ?>
	<div class="wrap ftr__cta">
		<div><h2>جاهزون لخدمتك</h2><p>تواصل معنا الآن لمعاينة مجانية وعرض سعر واضح.</p></div>
		<div class="hero__btns">
			<?php if ( $phone ) : ?><a class="btn btn--accent" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" dir="ltr"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> <?php echo esc_html( $phone ); ?></a><?php endif; ?>
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
	<?php $f_areas = zad_link_lines( 'zad_footer_areas' ); $f_cities = has_nav_menu( 'footercities' ) ? array( true ) : zad_footer_cities_fallback(); // the cities column (menu or the cities that have pages) replaces the plain «مناطق نخدمها» list, never both ?>
	<div class="wrap ftr__grid<?php echo ( $f_cities || $f_areas ) ? '' : ' ftr__grid--3'; ?>">
		<div class="ftr__brand ftr__about">
			<b><?php bloginfo( 'name' ); ?></b>
			<?php echo wp_kses_post( wpautop( zad_opt( 'memopt_footer_h' ) ) ); ?>
			<ul class="ftr__contact">
				<?php if ( zad_opt( 'memopt_phone' ) ) : ?><li><?php echo zad_icon( 'phone', 18 ); // phpcs:ignore ?><a href="<?php echo esc_url( zad_tel_href( zad_opt( 'memopt_phone' ) ) ); ?>" dir="ltr"><?php echo esc_html( zad_opt( 'memopt_phone' ) ); ?></a></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_mail' ) ) : ?><li><?php echo zad_icon( 'mail', 18 ); // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( zad_opt( 'memopt_mail' ) ); ?>"><?php echo esc_html( zad_opt( 'memopt_mail' ) ); ?></a></li><?php endif; ?>
				<?php if ( zad_opt( 'memopt_address' ) ) : ?><li><?php echo zad_icon( 'pin', 18 ); // phpcs:ignore ?><span><?php echo esc_html( zad_opt( 'memopt_address' ) ); ?></span></li><?php endif; ?>
			</ul>
			<div class="social">
				<?php
				foreach ( array( 'memopt_fb' => array( 'facebook', 'فيسبوك' ), 'memopt_tw' => array( 'x', 'إكس' ), 'memopt_insta' => array( 'instagram', 'إنستغرام' ), 'zad_linkedin' => array( 'linkedin', 'لينكدإن' ), 'zad_pinterest' => array( 'pinterest', 'بنترست' ) ) as $k => $v ) {
					$u = zad_opt( $k );
					if ( $u && preg_match( '#^https?://#', $u ) ) {
						echo '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $v[1] ) . '">' . zad_icon( $v[0], 20 ) . '</a>'; // phpcs:ignore
					}
				}
				?>
			</div>
		</div>
		<div>
			<h3>أهم الروابط</h3>
			<?php
			$edu = zad_footer_edu_item(); // optional «مركز المحتوى التعليمي» (setting); nothing when empty
			if ( has_nav_menu( 'footermenu' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footermenu', 'container' => false, 'menu_class' => 'ftr__list', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1, 'fallback_cb' => false ) );
				echo $edu ? zad_footer_list( array( $edu ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				$imp = zad_footer_important_fallback();
				if ( $edu ) { $imp[] = $edu; }
				echo zad_footer_list( $imp ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<div>
			<h3>خدماتنا</h3>
			<?php
			if ( has_nav_menu( 'footerinfo' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footerinfo', 'container' => false, 'menu_class' => 'ftr__list', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1 ) );
			} elseif ( $cats && ! is_wp_error( $cats ) ) {
				$cl = array();
				foreach ( $cats as $c ) { $u = get_term_link( $c ); if ( $u && ! is_wp_error( $u ) ) { $cl[] = array( $c->name, $u ); } }
				echo zad_footer_list( $cl ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<?php if ( $f_cities || $f_areas ) : ?>
		<div>
			<?php if ( $f_cities ) : ?>
			<h3>المدن</h3>
			<?php if ( has_nav_menu( 'footercities' ) ) { wp_nav_menu( array( 'theme_location' => 'footercities', 'container' => false, 'menu_class' => 'ftr__list', 'items_wrap' => '<ul class="%2$s">%3$s</ul>', 'depth' => 1 ) ); } else { echo zad_footer_list( $f_cities ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
			<h3>مناطق نخدمها</h3>
			<ul class="ftr__list"><?php foreach ( $f_areas as $a ) { echo '<li>' . esc_html( $a['name'] ) . '</li>'; } ?></ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php if ( zad_opt( 'zad_cr' ) || zad_opt( 'zad_vat' ) || zad_hours_text() ) : ?>
	<div class="wrap ftr__badges">
		<?php if ( zad_opt( 'zad_cr' ) ) : ?><div class="ftr__badge"><?php echo zad_icon( 'badge', 26 ); // phpcs:ignore ?><div><b>السجل التجاري</b><span dir="ltr"><?php echo esc_html( zad_opt( 'zad_cr' ) ); ?></span></div></div><?php endif; ?>
		<?php if ( zad_opt( 'zad_vat' ) ) : ?><div class="ftr__badge"><?php echo zad_icon( 'shield', 26 ); // phpcs:ignore ?><div><b>ضريبة القيمة المضافة</b><span dir="ltr"><?php echo esc_html( zad_opt( 'zad_vat' ) ); ?></span></div></div><?php endif; ?>
		<?php if ( zad_hours_text() ) : ?><div class="ftr__badge"><?php echo zad_icon( 'clock', 26 ); // phpcs:ignore ?><div><b>ساعات العمل</b><span><?php echo esc_html( zad_hours_text() ); ?></span></div></div><?php endif; ?>
	</div>
	<?php endif; ?>
	<?php if ( zad_opt( 'zad_legal_name' ) ) : ?><div class="wrap"><p class="ftr__legalname"><?php echo esc_html( zad_opt( 'zad_legal_name' ) ); ?></p></div><?php endif; ?>
	<div class="ftr__copy"><div class="wrap ftr__bottom"><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> — جميع الحقوق محفوظة</span><?php zad_legal_links(); ?></div></div>
</footer>

<?php if ( $phone || $wa ) : ?>
<div class="dock" role="complementary" aria-label="تواصل سريع" style="--dock-n:<?php echo (int) ( ( $phone ? 1 : 0 ) + ( $wa ? 1 : 0 ) + 1 ); ?>">
	<?php if ( $phone ) : ?><a class="dock__btn dock__btn--call" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>"><?php echo zad_icon( 'phone', 22 ); // phpcs:ignore ?><span>اتصل الآن</span></a><?php endif; ?>
	<?php if ( $wa ) : ?><a class="dock__btn dock__btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 22 ); // phpcs:ignore ?><span>واتساب</span></a><?php endif; ?>
	<button type="button" class="dock__btn dock__btn--quote" data-open-wizard><?php echo zad_icon( 'bolt', 22 ); // phpcs:ignore ?><span>عرض سعر</span></button>
</div>
<div class="fab" data-fab>
	<div class="fab__actions" id="fab-actions">
		<?php if ( $phone ) : ?><a class="fab__a fab__a--call" href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" aria-label="اتصال"><?php echo zad_icon( 'phone', 22 ); // phpcs:ignore ?><span>اتصال</span></a><?php endif; ?>
		<?php if ( $wa ) : ?><a class="fab__a fab__a--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="واتساب"><?php echo zad_icon( 'whatsapp', 22 ); // phpcs:ignore ?><span>واتساب</span></a><?php endif; ?>
	</div>
	<button type="button" class="fab__main" data-fab-toggle aria-expanded="false" aria-controls="fab-actions" aria-label="تواصل معنا"><?php echo zad_icon( 'bolt', 26 ); // phpcs:ignore ?><span class="fab__badge">2</span></button>
</div>
<button type="button" class="totop" data-totop aria-label="العودة للأعلى"><?php echo zad_icon( 'up', 22 ); // phpcs:ignore ?></button>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
