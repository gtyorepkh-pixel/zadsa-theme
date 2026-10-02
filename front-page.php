<?php defined( 'ABSPATH' ) || exit;
get_header();

$hero_img = zad_opt( 'zad_hero_img' );
$hero_img = ( is_array( $hero_img ) && ! empty( $hero_img['id'] ) ) ? $hero_img['id'] : 0;
$points   = zad_lines( zad_opt( 'zad_hero_points' ) );
$stats    = (array) zad_opt( 'zad_stats', array() );
$process  = (array) zad_opt( 'zad_process', array() );
$tests    = (array) zad_opt( 'zad_testimonials', array() );
$faq      = (array) zad_opt( 'zad_faq', array() );
$why      = (array) zad_opt( 'memo_sec4_grp', array() );

$cats = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 12 ) );
$areas = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'number' => 24 ) );
$featured = new WP_Query( array(
	'post_type'      => 'zad_service',
	'posts_per_page' => 6,
	'no_found_rows'  => true,
	'meta_query'     => array( array( 'key' => '_zad_featured', 'value' => '1' ) ),
	'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
) );
if ( ! $featured->have_posts() ) {
	$featured = new WP_Query( array( 'post_type' => 'zad_service', 'posts_per_page' => 6, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
}
?>
<main id="main">

<section class="hero">
	<div class="wrap hero__grid">
		<div class="hero__text">
			<?php if ( zad_opt( 'zad_hero_badge' ) ) : ?><span class="pill"><?php echo zad_icon( 'badge', 18 ); // phpcs:ignore ?> <?php echo esc_html( zad_opt( 'zad_hero_badge' ) ); ?></span><?php endif; ?>
			<h1><?php echo esc_html( zad_opt( 'zad_hero_title', get_bloginfo( 'name' ) ) ); ?></h1>
			<p class="hero__sub"><?php echo esc_html( zad_opt( 'zad_hero_sub' ) ); ?></p>
			<?php if ( $points ) : ?>
				<ul class="ticks">
					<?php foreach ( $points as $pt ) : ?><li><?php echo zad_icon( 'check', 18 ); // phpcs:ignore ?> <?php echo esc_html( $pt ); ?></li><?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="hero__btns">
				<?php if ( zad_opt( 'memopt_phone' ) ) : ?><a class="btn btn--accent" href="<?php echo esc_url( zad_tel_href( zad_opt( 'memopt_phone' ) ) ); ?>"><?php echo zad_icon( 'phone', 20 ); // phpcs:ignore ?> اتصل الآن</a><?php endif; ?>
				<?php if ( zad_wa_link( 'x', 0 ) ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بطلب خدمة', 0 ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
			</div>
		</div>
		<div class="hero__side" id="quote">
			<?php echo zad_quote_form( array( 'id' => 'hq' ) ); // phpcs:ignore ?>
		</div>
	</div>
	<?php if ( $hero_img ) : ?><div class="hero__img"><?php echo wp_get_attachment_image( $hero_img, 'large', false, array( 'loading' => 'eager', 'alt' => '' ) ); ?></div><?php endif; ?>
</section>

<?php if ( $stats ) : ?>
<section class="stats">
	<div class="wrap stats__grid">
		<?php foreach ( $stats as $s ) : if ( empty( $s['n'] ) ) { continue; } ?>
			<div class="stat"><b><?php echo esc_html( $s['n'] ); ?></b><span><?php echo esc_html( $s['l'] ?? '' ); ?></span></div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php $rooms = array_filter( (array) zad_opt( 'zad_rooms', array() ), function ( $r ) { return ! empty( $r['name'] ); } );
if ( $rooms ) : ?>
<section class="sec" id="rooms">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">خريطة المنزل</span><h2>أين تحتاج الخدمة؟</h2><p>اختر المكان لنقترح عليك الخدمة المناسبة.</p></header>
		<div class="rooms">
			<?php foreach ( $rooms as $r ) : $ids = array_filter( array_map( 'intval', (array) ( $r['services'] ?? array() ) ) ); ?>
				<div class="room">
					<span class="icard__ic"><?php echo zad_icon( $r['icon'] ?: 'home', 26 ); // phpcs:ignore ?></span>
					<h3><?php echo esc_html( $r['name'] ); ?></h3>
					<?php if ( ! empty( $r['desc'] ) ) : ?><p><?php echo esc_html( $r['desc'] ); ?></p><?php endif; ?>
					<?php if ( $ids ) : ?><ul><?php foreach ( $ids as $sid ) : if ( 'publish' === get_post_status( $sid ) ) : ?><li><a href="<?php echo esc_url( get_permalink( $sid ) ); ?>"><?php echo zad_icon( 'arrow', 14 ); // phpcs:ignore ?> <?php echo esc_html( get_the_title( $sid ) ); ?></a></li><?php endif; endforeach; ?></ul><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">خدماتنا</span><h2>اختر القسم المناسب لك</h2></header>
		<div class="catgrid">
			<?php foreach ( $cats as $c ) : $ic = get_term_meta( $c->term_id, 'zad_icon', true ) ?: 'sparkle'; ?>
				<a class="cat" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
					<span class="cat__ic"><?php echo zad_icon( $ic, 32 ); // phpcs:ignore ?></span>
					<strong><?php echo esc_html( $c->name ); ?></strong>
					<small><?php echo esc_html( sprintf( '%d خدمة', $c->count ) ); ?></small>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $featured->have_posts() ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">الأكثر طلباً</span><h2>خدمات مميزة بأسعار واضحة</h2></header>
		<div class="sgrid">
			<?php while ( $featured->have_posts() ) { $featured->the_post(); get_template_part( 'template-parts/service-card' ); } wp_reset_postdata(); ?>
		</div>
		<p class="sec__more"><a class="btn btn--primary" href="<?php echo esc_url( get_post_type_archive_link( 'zad_service' ) ); ?>">عرض كل الخدمات <?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></a></p>
	</div>
</section>
<?php endif; ?>

<?php if ( $why ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">لماذا نحن</span><h2><?php echo esc_html( wp_strip_all_tags( zad_opt( 'memopt_sec4_h', 'لماذا تختارنا؟' ) ) ); ?></h2></header>
		<div class="why">
			<?php foreach ( $why as $w ) : ?>
				<div class="why__item">
					<?php if ( ! empty( $w['memo_sec4_grp_img']['id'] ) ) { echo wp_get_attachment_image( $w['memo_sec4_grp_img']['id'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); } ?>
					<h3><?php echo esc_html( $w['memo_sec4_grp_h'] ?? '' ); ?></h3>
					<p><?php echo esc_html( $w['memo_sec4_grp_p'] ?? '' ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $process ) : ?>
<section class="sec sec--dark">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">آلية العمل</span><h2>من الطلب إلى التنفيذ في خطوات بسيطة</h2></header>
		<ol class="steps">
			<?php foreach ( $process as $i => $st ) : if ( empty( $st['t'] ) ) { continue; } ?>
				<li><span class="steps__n"><?php echo esc_html( $i + 1 ); ?></span><h3><?php echo esc_html( $st['t'] ); ?></h3><p><?php echo esc_html( $st['d'] ?? '' ); ?></p></li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
<?php endif; ?>

<?php $hs = array_filter( (array) zad_opt( 'zad_home_safety', array() ), function ( $r ) { return ! empty( $r['t'] ); } );
if ( $hs ) : ?>
<section class="sec sec--tint" id="safety">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow"><?php echo zad_icon( 'shield', 14 ); // phpcs:ignore ?> الأمان أولاً</span><h2><?php echo esc_html( zad_opt( 'zad_home_safety_title', 'آمن لمن تحب' ) ); ?></h2></header>
		<div class="cardgrid"><?php foreach ( $hs as $x ) : ?><div class="icard"><span class="icard__ic"><?php echo zad_icon( 'shield', 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $x['t'] ); ?></h3><p><?php echo esc_html( $x['d'] ?? '' ); ?></p></div><?php endforeach; ?></div>
	</div>
</section>
<?php endif; ?>

<?php if ( $areas && ! is_wp_error( $areas ) ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">مناطق الخدمة</span><h2>نغطي مدنكم وأحياءكم</h2></header>
		<ul class="chips">
			<?php foreach ( $areas as $t ) : ?><li><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $t->name ); ?></a></li><?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php if ( $tests || zad_opt( 'zad_trustindex' ) ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">آراء العملاء</span><h2>ثقة عملائنا هي رأس مالنا</h2></header>
		<?php if ( $tests ) : ?>
		<div class="tgrid">
			<?php foreach ( $tests as $t ) : if ( empty( $t['text'] ) ) { continue; } ?>
				<figure class="tcard">
					<?php echo zad_stars( $t['rating'] ?? 5 ); // phpcs:ignore ?>
					<blockquote><?php echo esc_html( $t['text'] ); ?></blockquote>
					<figcaption><b><?php echo esc_html( $t['name'] ?? '' ); ?></b><span><?php echo esc_html( $t['city'] ?? '' ); ?></span></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<?php if ( zad_opt( 'zad_trustindex' ) && shortcode_exists( 'trustindex' ) ) { echo do_shortcode( '[trustindex data-widget-id="' . esc_attr( zad_opt( 'zad_trustindex' ) ) . '"]' ); } ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $faq ) : ?>
<section class="sec">
	<div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">أسئلة شائعة</span><h2>إجابات سريعة قبل أن تسأل</h2></header>
		<?php zad_render_faq( $faq ); ?>
	</div>
</section>
<?php endif; ?>

<?php
$posts = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => 1, 'no_found_rows' => true ) );
if ( $posts->have_posts() ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">المدونة</span><h2>أحدث المقالات والنصائح</h2></header>
		<div class="sgrid">
			<?php while ( $posts->have_posts() ) { $posts->the_post(); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
<?php get_footer(); ?>
