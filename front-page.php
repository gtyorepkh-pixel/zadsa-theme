<?php defined( 'ABSPATH' ) || exit;
get_header();

$img_id = function ( $k ) { $v = zad_opt( $k ); return ( is_array( $v ) && ! empty( $v['id'] ) ) ? (int) $v['id'] : 0; };
$hero_img = $img_id( 'zad_hero_img' );
$points   = zad_lines( zad_opt( 'zad_hero_points' ) );
$stats    = array_filter( (array) zad_opt( 'zad_stats', array() ), function ( $s ) { return ! empty( $s['n'] ); } );
$process  = (array) zad_opt( 'zad_process', array() );
$tests    = array_filter( (array) zad_opt( 'zad_testimonials', array() ), function ( $t ) { return ! empty( $t['text'] ); } );
$faq      = (array) zad_opt( 'zad_faq', array() );
$why      = (array) zad_opt( 'memo_sec4_grp', array() );
$hl       = array_filter( (array) zad_opt( 'zad_highlights', array() ), function ( $h ) { return ! empty( $h['t'] ); } );

$cats  = get_terms( array( 'taxonomy' => 'service_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 12 ) );
$areas = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => 0 ) );
$all   = new WP_Query( array( 'post_type' => zad_service_types(), 'posts_per_page' => 12, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );

// price teaser: cheapest services
$teaser = array();
foreach ( $all->posts as $sv ) {
	$min = zad_min_price( zad_parse_prices( get_post_meta( $sv->ID, '_zad_prices', true ) ) ) ?: (int) get_post_meta( $sv->ID, '_zad_price', true );
	if ( $min ) { $teaser[] = array( $sv, $min ); }
}
usort( $teaser, function ( $a, $b ) { return $a[1] <=> $b[1]; } );
$teaser = array_slice( $teaser, 0, 4 );
$price_page = zad_page_url( 'temp/zad-prices.php', array( 'price-plans' ) );
$areas_page = zad_page_url( 'temp/zad-areas.php', array( 'areas' ) );
$about_page = zad_page_url( 'temp/memo-about.php', array( 'about' ) );
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

<?php if ( $hl ) : ?>
<section class="hl"><div class="wrap hl__grid">
	<?php foreach ( $hl as $h ) : ?>
		<div class="hl__item"><span class="hl__ic"><?php echo zad_icon( $h['icon'] ?: 'check', 24 ); // phpcs:ignore ?></span><div><b><?php echo esc_html( $h['t'] ); ?></b><small><?php echo esc_html( $h['d'] ?? '' ); ?></small></div></div>
	<?php endforeach; ?>
</div></section>
<?php endif; ?>

<?php if ( $stats ) : ?>
<section class="stats stats--home">
	<div class="wrap stats__grid">
		<?php foreach ( $stats as $s ) : ?>
			<div class="stat"><b data-count="<?php echo esc_attr( $s['n'] ); ?>"><?php echo esc_html( $s['n'] ); ?></b><span><?php echo esc_html( $s['l'] ?? '' ); ?></span></div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php $about_title = zad_opt( 'zad_about_title' ); $about_img = $img_id( 'zad_about_img' );
if ( $about_title ) : $ap = zad_lines( zad_opt( 'zad_about_points' ) ); ?>
<section class="sec">
	<div class="wrap split">
		<div>
			<span class="eyebrow"><?php echo esc_html( zad_opt( 'zad_about_eyebrow', 'من نحن' ) ); ?></span>
			<h2><?php echo esc_html( $about_title ); ?></h2>
			<p><?php echo esc_html( zad_opt( 'zad_about_text' ) ); ?></p>
			<?php if ( $ap ) : ?><ul class="ticks ticks--dark"><?php foreach ( $ap as $x ) : ?><li><?php echo zad_icon( 'check', 18 ); // phpcs:ignore ?> <?php echo esc_html( $x ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			<p class="hero__btns"><?php if ( $about_page ) : ?><a class="btn btn--primary" href="<?php echo esc_url( $about_page ); ?>">اعرف المزيد عنّا</a><?php endif; ?><button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> احجز موعد</button></p>
		</div>
		<div class="split__img aboutimg">
			<?php if ( $about_img ) { echo wp_get_attachment_image( $about_img, 'large', false, array( 'loading' => 'lazy', 'alt' => $about_title ) ); } else { echo '<div class="aboutimg__ph">' . zad_icon( 'shield', 80 ) . '</div>'; } // phpcs:ignore ?>
			<div class="aboutimg__badge"><b><?php echo esc_html( zad_opt( 'zad_since' ) ? 'منذ ' . zad_opt( 'zad_since' ) : 'خبرة' ); ?></b><small>نخدم عملاءنا بثقة</small></div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $all->have_posts() ) : ?>
<section class="sec sec--tint" id="services">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">خدماتنا</span><h2>كل ما يحتاجه منزلك في مكان واحد</h2></header>
		<?php if ( $cats && ! is_wp_error( $cats ) && count( $cats ) > 1 ) : ?>
			<div class="tabs" role="tablist" data-tabs>
				<button type="button" class="tabs__b is-on" data-tab="all">الكل</button>
				<?php foreach ( $cats as $c ) : ?><button type="button" class="tabs__b" data-tab="<?php echo (int) $c->term_id; ?>"><?php echo esc_html( $c->name ); ?></button><?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="sgrid" data-tab-items>
			<?php while ( $all->have_posts() ) { $all->the_post();
				$tc = wp_get_post_terms( get_the_ID(), 'service_cat', array( 'fields' => 'ids' ) ); ?>
				<div class="tabitem" data-cats="<?php echo esc_attr( implode( ',', (array) $tc ) ); ?>"><?php get_template_part( 'template-parts/service-card' ); ?></div>
			<?php } wp_reset_postdata(); ?>
		</div>
		<p class="sec__more"><a class="btn btn--primary" href="<?php echo esc_url( zad_services_url() ); ?>">عرض كل الخدمات <?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></a></p>
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

<?php if ( $why ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">لماذا نحن</span><h2><?php echo esc_html( wp_strip_all_tags( zad_opt( 'memopt_sec4_h', 'لماذا تختارنا؟' ) ) ); ?></h2></header>
		<div class="why">
			<?php foreach ( $why as $w ) : ?>
				<div class="why__item">
					<?php if ( ! empty( $w['memo_sec4_grp_img']['id'] ) ) { echo wp_get_attachment_image( $w['memo_sec4_grp_img']['id'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); } else { echo '<span class="icard__ic">' . zad_icon( 'badge', 26 ) . '</span>'; } // phpcs:ignore ?>
					<h3><?php echo esc_html( $w['memo_sec4_grp_h'] ?? '' ); ?></h3>
					<p><?php echo esc_html( $w['memo_sec4_grp_p'] ?? '' ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $teaser ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">الأسعار</span><h2><?php echo esc_html( zad_opt( 'zad_price_title', 'أسعار واضحة تبدأ من' ) ); ?></h2></header>
		<div class="ptease">
			<?php foreach ( $teaser as $t ) : ?>
				<a class="ptease__c" href="<?php echo esc_url( get_permalink( $t[0] ) ); ?>"><small><?php echo esc_html( zad_service_base_name( $t[0]->ID ) ); ?></small><b><?php echo esc_html( number_format_i18n( $t[1] ) ); ?> <i>ريال</i></b><span>التفاصيل <?php echo zad_icon( 'arrow', 14 ); // phpcs:ignore ?></span></a>
			<?php endforeach; ?>
		</div>
		<?php if ( $price_page ) : ?><p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( $price_page ); ?>">كل الأسعار في جدول واحد</a></p><?php endif; ?>
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

<?php $hba = array_filter( (array) zad_opt( 'zad_home_ba', array() ), function ( $b ) { return ! empty( $b['before']['id'] ) && ! empty( $b['after']['id'] ); } );
if ( $hba ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">قبل وبعد</span><h2>نتائج حقيقية من أعمالنا</h2></header>
		<div class="sgrid">
			<?php foreach ( $hba as $b ) : ?>
				<figure class="ba" data-ba>
					<div class="ba__stage">
						<?php echo wp_get_attachment_image( $b['after']['id'], 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after' ) ); ?>
						<div class="ba__before"><?php echo wp_get_attachment_image( $b['before']['id'], 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
						<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
						<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
					</div>
					<?php if ( ! empty( $b['title'] ) ) : ?><figcaption><b><?php echo esc_html( $b['title'] ); ?></b><?php if ( ! empty( $b['desc'] ) ) : ?><br><span><?php echo esc_html( $b['desc'] ); ?></span><?php endif; ?></figcaption><?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php $hv = zad_opt( 'zad_home_video' );
if ( $hv ) : $poster = $img_id( 'zad_home_video_poster' ); ?>
<section class="sec sec--dark" id="video"><div class="wrap wrap--narrow">
	<header class="sec__head"><span class="eyebrow">شاهد الفرق بنفسك</span><h2>شاهد خدماتنا عن قرب</h2></header>
	<div class="vframe" data-video="<?php echo esc_url( $hv ); ?>"<?php echo $poster ? ' style="--poster:url(\'' . esc_url( wp_get_attachment_image_url( $poster, 'large' ) ) . '\')"' : ''; ?>>
		<button type="button" class="vframe__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5v14l11-7Z"/></svg></button>
	</div>
</div></section>
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

<?php if ( zad_opt( 'zad_guarantee_title' ) ) : ?>
<section class="gband"><div class="wrap gband__in">
	<span class="gband__ic"><?php echo zad_icon( 'badge', 40 ); // phpcs:ignore ?></span>
	<div><h2><?php echo esc_html( zad_opt( 'zad_guarantee_title' ) ); ?></h2><p><?php echo esc_html( zad_opt( 'zad_guarantee_text' ) ); ?></p></div>
	<button type="button" class="btn btn--accent" data-open-wizard>اطلب معاينة مجانية</button>
</div></section>
<?php endif; ?>

<?php $home_areas = zad_link_lines( 'zad_home_areas' ); if ( $home_areas ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">مناطق الخدمة</span><h2>نغطي مدنكم وأحياءكم</h2></header>
		<div class="citygrid">
			<?php foreach ( $home_areas as $a ) : $tag = $a['url'] ? 'a' : 'div'; ?>
				<<?php echo $tag; // phpcs:ignore ?> class="city"<?php echo $a['url'] ? ' href="' . esc_url( $a['url'] ) . '"' : ''; // phpcs:ignore ?>>
					<span class="city__ic"><?php echo zad_icon( 'pin', 26 ); // phpcs:ignore ?></span>
					<strong><?php echo esc_html( $a['name'] ); ?></strong>
					<?php if ( $a['note'] ) : ?><small><?php echo esc_html( $a['note'] ); ?></small><?php endif; ?>
				</<?php echo $tag; // phpcs:ignore ?>>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php echo zad_coverage_html( 0, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

<?php $clients = array_filter( (array) zad_opt( 'zad_clients', array() ), function ( $c ) { return ! empty( $c['name'] ); } );
if ( $clients ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">عملاؤنا</span><h2><?php echo esc_html( zad_opt( 'zad_clients_title', '' ) ?: 'جهات وشركات تثق بنا' ); ?></h2><?php if ( zad_opt( 'zad_clients_sub', '' ) ) { echo '<p>' . esc_html( zad_opt( 'zad_clients_sub' ) ) . '</p>'; } ?></header>
		<div class="clients"><?php foreach ( $clients as $c ) : ?><div class="client"><b><?php echo esc_html( $c['name'] ); ?></b><span><?php echo esc_html( $c['note'] ?? '' ); ?></span></div><?php endforeach; ?></div>
	</div>
</section>
<?php endif; ?>

<?php if ( $tests || zad_opt( 'zad_trustindex' ) ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">آراء العملاء</span><h2>ثقة عملائنا هي رأس مالنا</h2>
			<?php if ( zad_opt( 'zad_rating_score' ) ) : ?><p class="ratesum"><?php echo zad_stars( (float) zad_opt( 'zad_rating_score' ) ); // phpcs:ignore ?> <b><?php echo esc_html( zad_opt( 'zad_rating_score' ) ); ?></b><?php if ( zad_opt( 'zad_rating_count' ) ) : ?> <span>من <?php echo esc_html( zad_opt( 'zad_rating_count' ) ); ?> تقييم</span><?php endif; ?></p><?php endif; ?></header>
		<?php if ( $tests ) : ?>
		<div class="slider" data-slider>
			<button type="button" class="slider__b slider__b--prev" data-slide="-1" aria-label="السابق"><?php echo zad_icon( 'chevron', 22, 'rot-r' ); // phpcs:ignore ?></button>
			<div class="slider__track" data-track>
				<?php foreach ( $tests as $t ) : ?>
					<figure class="tcard">
						<?php echo zad_stars( $t['rating'] ?? 5 ); // phpcs:ignore ?>
						<blockquote><?php echo esc_html( $t['text'] ); ?></blockquote>
						<figcaption><b><?php echo esc_html( $t['name'] ?? '' ); ?></b><span><?php echo esc_html( $t['city'] ?? '' ); ?></span></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
			<button type="button" class="slider__b slider__b--next" data-slide="1" aria-label="التالي"><?php echo zad_icon( 'chevron', 22, 'rot-l' ); // phpcs:ignore ?></button>
		</div>
		<?php endif; ?>
		<?php if ( zad_opt( 'zad_trustindex' ) && shortcode_exists( 'trustindex' ) ) { echo do_shortcode( '[trustindex data-widget-id="' . esc_attr( zad_opt( 'zad_trustindex' ) ) . '"]' ); } ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $faq ) : ?>
<section class="sec sec--tint">
	<div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">أسئلة شائعة</span><h2>إجابات سريعة قبل أن تسأل</h2></header>
		<?php zad_render_faq( $faq ); ?>
		<p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_faq_url() ); ?>">كل الأسئلة الشائعة</a></p>
	</div>
</section>
<?php endif; ?>

<?php
$posts = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => 1, 'no_found_rows' => true ) );
if ( $posts->have_posts() ) : ?>
<section class="sec">
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
