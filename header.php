<?php defined( 'ABSPATH' ) || exit;
$logo = zad_opt( 'memopt_logo' );
$logo_url = ( is_array( $logo ) && ! empty( $logo['url'] ) ) ? $logo['url'] : MEMO_THEME_URI . 'assets/img/logo.png';
$phone = zad_opt( 'memopt_phone' );
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main">تخطي إلى المحتوى</a>

<header id="site-header" class="hdr">
	<div class="topbar">
		<div class="wrap topbar__in">
			<span class="topbar__hours"><?php echo zad_icon( 'clock', 16 ); // phpcs:ignore ?> <?php echo esc_html( zad_opt( 'zad_hours', 'نخدمكم 24 ساعة طوال أيام الأسبوع' ) ); ?></span>
			<span class="topbar__links">
				<?php if ( $phone ) : ?><a href="<?php echo esc_url( zad_tel_href( $phone ) ); ?>" dir="ltr"><?php echo zad_icon( 'phone', 16 ); // phpcs:ignore ?> <?php echo esc_html( $phone ); ?></a><?php endif; ?>
				<?php if ( zad_opt( 'memopt_mail' ) ) : ?><a href="mailto:<?php echo esc_attr( zad_opt( 'memopt_mail' ) ); ?>"><?php echo zad_icon( 'mail', 16 ); // phpcs:ignore ?> <?php echo esc_html( zad_opt( 'memopt_mail' ) ); ?></a><?php endif; ?>
			</span>
		</div>
	</div>
	<div class="navbar">
		<div class="wrap navbar__in">
			<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="180" height="56">
			</a>
			<button class="burger" type="button" aria-expanded="false" aria-controls="primary-nav" data-nav-open>
				<?php echo zad_icon( 'menu', 26 ); // phpcs:ignore ?><span class="sr">القائمة</span>
			</button>
			<nav id="primary-nav" class="nav" aria-label="القائمة الرئيسية">
				<button class="nav__close" type="button" data-nav-close><?php echo zad_icon( 'close', 24 ); // phpcs:ignore ?><span class="sr">إغلاق</span></button>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'mainmenu',
					'container'      => false,
					'menu_class'     => 'nav__list',
					'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
					'depth'          => 3,
					'walker'         => new memo_walker(),
					'fallback_cb'    => false,
				) );
				?>
				<form role="search" method="get" class="nav__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="sr" for="hs">ابحث</label>
					<input id="hs" type="search" name="s" placeholder="ابحث عن خدمة...">
					<button type="submit" aria-label="بحث"><?php echo zad_icon( 'search', 18 ); // phpcs:ignore ?></button>
				</form>
			</nav>
			<?php if ( zad_whatsapp( 0 ) || $phone ) : ?>
				<a class="btn btn--accent hdr__cta" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بطلب خدمة', 0 ) ?: zad_tel_href( $phone ) ); ?>" <?php echo zad_wa_link( '', 0 ) ? 'target="_blank" rel="noopener"' : ''; ?>>
					<?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب الآن
				</a>
			<?php endif; ?>
		</div>
	</div>
	<div class="nav-overlay" data-nav-close></div>
</header>
