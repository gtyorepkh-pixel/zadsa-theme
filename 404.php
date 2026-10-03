<?php defined( 'ABSPATH' ) || exit;
get_header();
$req  = zad_404_norm( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore
$sug  = zad_404_suggest( $req, 5 );
$lst  = zad_404_lists();
?>
<main id="main" class="sec">
	<div class="wrap wrap--narrow nf">
		<span class="nf__n">404</span>
		<h1>عذراً، الصفحة غير موجودة</h1>
		<p>ربما تم نقل الصفحة أو حذفها. جرّب البحث، أو اختر من الاقتراحات أدناه.</p>
		<?php get_search_form(); ?>
		<p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">الصفحة الرئيسية</a> <a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_services_url() ); ?>">كل الخدمات</a></p>
	</div>
	<?php if ( $sug ) : ?>
	<div class="wrap wrap--narrow"><header class="sec__head"><h2>هل تقصد؟</h2></header>
		<ul class="hd-nb"><?php foreach ( $sug as $s ) { echo '<li><a href="' . esc_url( $s[1] ) . '">' . esc_html( $s[0] ) . '</a></li>'; } ?></ul></div>
	<?php endif; ?>
	<?php if ( $lst['svc'] ) : ?>
	<div class="wrap wrap--narrow"><header class="sec__head"><h2>خدماتنا</h2></header>
		<ul class="hd-nb"><?php foreach ( $lst['svc'] as $s ) { echo '<li><a href="' . esc_url( $s[1] ) . '">' . esc_html( $s[0] ) . '</a></li>'; } ?></ul></div>
	<?php endif; ?>
	<?php if ( $lst['kids'] ) : ?>
	<div class="wrap wrap--narrow"><header class="sec__head"><h2>مدن وأحياء نخدمها</h2></header>
		<ul class="hd-nb"><?php foreach ( $lst['kids'] as $s ) { echo '<li><a href="' . esc_url( $s[1] ) . '">' . esc_html( $s[0] ) . '</a></li>'; } ?></ul></div>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
