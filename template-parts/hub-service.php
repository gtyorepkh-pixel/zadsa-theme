<?php defined( 'ABSPATH' ) || exit;
/** Pillar/hub page for one service post type (e.g. /pest-control/): explains the category and points to the services under it. */
$pt     = zad_query_pt();
$pto    = get_post_type_object( $pt );
$label  = zad_ac_title( $pto ? $pto->labels->name : 'الخدمات' );
$hub    = zad_hub_opt( $pt );
$q      = new WP_Query( array( 'post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => 60, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
$svcs   = $q->posts;
$ids    = wp_list_pluck( $svcs, 'ID' );
$head   = ! empty( $hub['headline'] ) ? $hub['headline'] : $label;
$lead   = ! empty( $hub['lead'] ) ? $hub['lead'] : 'كل ما تحتاج معرفته عن ' . $label . ': ما نقدمه، وكيف تختار الخدمة المناسبة، والأسعار والمدة والضمان، وأهم الأسئلة.';
$cta    = ! empty( $hub['cta'] ) ? $hub['cta'] : 'اطلب معاينة مجانية';

// data per service
$rows = array();
$cities = array();
foreach ( $svcs as $sv ) {
	$parsed = zad_price_rows( $sv->ID );
	$min    = zad_min_price( $parsed ) ?: (int) get_post_meta( $sv->ID, '_zad_price', true );
	$rows[ $sv->ID ] = array(
		'p'    => $sv,
		'tag'  => get_post_meta( $sv->ID, '_zad_tagline', true ) ?: wp_trim_words( get_the_excerpt( $sv ), 22 ),
		'min'  => $min,
		'dur'  => get_post_meta( $sv->ID, '_zad_duration', true ),
		'war'  => get_post_meta( $sv->ID, '_zad_warranty', true ),
		'icon' => get_post_meta( $sv->ID, '_zad_icon', true ) ?: 'sparkle',
	);
	foreach ( (array) get_the_terms( $sv->ID, 'service_area' ) as $t ) {
		if ( $t instanceof WP_Term && 0 === (int) $t->parent ) { $cities[ $t->term_id ]['t'] = $t; $cities[ $t->term_id ]['s'][] = $sv; }
	}
}
$mins = array_filter( wp_list_pluck( $rows, 'min' ) );
$decide = array();
foreach ( zad_lines( $hub['decide'] ?? '' ) as $l ) {
	$c = array_map( 'trim', explode( '|', $l, 2 ) );
	if ( 2 === count( $c ) ) {
		foreach ( $svcs as $sv ) { if ( $sv->post_title === $c[1] ) { $decide[] = array( $c[0], $sv ); break; } }
	}
}
$tips = zad_lines( $hub['tips'] ?? '' );
$body = trim( (string) ( $hub['body'] ?? '' ) );
$nfaq = 0;

// FAQs linked to these services
$faqs = array();
if ( $ids ) {
	$mq = array( 'relation' => 'OR' );
	foreach ( $ids as $i ) { $mq[] = array( 'key' => '_zad_faq_services', 'value' => '"' . (int) $i . '"', 'compare' => 'LIKE' ); }
	$fq = new WP_Query( array( 'post_type' => zad_faq_types(), 'posts_per_page' => 8, 'no_found_rows' => true, 'meta_query' => $mq ) );
	$faqs = $fq->posts;
}
// Guides tied to these services, else latest
$gq = new WP_Query( array( 'post_type' => zad_article_types(), 'posts_per_page' => 3, 'no_found_rows' => true, 'ignore_sticky_posts' => 1, 'meta_query' => $ids ? array( array( 'key' => '_zad_post_service', 'value' => $ids, 'compare' => 'IN' ) ) : array() ) );
if ( ! $gq->have_posts() ) {
	$gq = new WP_Query( array( 'post_type' => zad_article_types(), 'posts_per_page' => 3, 'no_found_rows' => true, 'ignore_sticky_posts' => 1 ) );
}
$toc = array( 'directory' => 'فهرس الخدمات' );
if ( $decide ) { $toc['choose'] = 'اختر حسب حالتك'; }
if ( count( $rows ) > 1 ) { $toc['compare'] = 'مقارنة سريعة'; }
if ( $cities ) { $toc['cities'] = 'مناطق التغطية'; }
if ( $gq->have_posts() ) { $toc['guides'] = 'أدلة مفيدة'; }
if ( $faqs ) { $toc['faq'] = 'أسئلة شائعة'; }
?>
<main id="main" class="hub"><?php echo zad_archive_intro_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<section class="hubhero">
		<div class="wrap">
			<?php zad_render_crumbs( zad_current_crumbs() ?: array( array( 'الرئيسية', home_url( '/' ) ), array( $label, '' ) ) ); ?>
			<div class="hubhero__grid">
				<div>
					<span class="pill"><?php echo zad_icon( 'list', 18 ); // phpcs:ignore ?> دليل القسم</span>
					<h1><?php echo esc_html( $head ); ?></h1>
					<p class="hubhero__lead"><?php echo esc_html( $lead ); ?></p>
					<div class="hero__btns"><button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> <?php echo esc_html( $cta ); ?></button>
					<?php if ( zad_wa_link( 'x', 0 ) ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أستفسر عن: ' . $label, 0 ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?></div>
				</div>
				<ul class="hubstats">
					<li><b><?php echo count( $rows ); ?></b><span>خدمة في هذا القسم</span></li>
					<?php if ( $mins ) : ?><li><b><?php echo esc_html( number_format_i18n( min( $mins ) ) ); ?></b><span>ريال — أقل سعر يبدأ منه</span></li><?php endif; ?>
					<?php if ( $cities ) : ?><li><b><?php echo count( $cities ); ?></b><span>مدينة نخدمها</span></li><?php endif; ?>
				</ul>
			</div>
		</div>
	</section>
	<?php zad_archive_source(); ?>

	<div class="wrap hublayout">
		<aside class="hubnav"><div class="sticky">
			<strong>في هذه الصفحة</strong>
			<ol><?php foreach ( $toc as $k => $v ) : ?><li><a href="#<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></a></li><?php endforeach; ?></ol>
			<button type="button" class="btn btn--accent btn--block" data-open-wizard>احجز موعد</button>
		</div></aside>

		<div class="hubmain">
			<?php if ( $body ) : ?><section class="hubsec prose entry-content" id="overview"><?php echo wp_kses_post( wpautop( $body ) ); ?></section><?php endif; ?>

			<section class="hubsec" id="directory">
				<h2 class="h-line">فهرس خدمات <?php echo esc_html( $label ); ?></h2>
				<ol class="dirlist">
					<?php $n = 0; foreach ( $rows as $r ) : $n++; ?>
					<li>
						<a class="dir" href="<?php echo esc_url( get_permalink( $r['p'] ) ); ?>">
							<span class="dir__n"><?php echo esc_html( str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) ); ?></span>
							<span class="dir__ic"><?php echo zad_icon( $r['icon'], 26 ); // phpcs:ignore ?></span>
							<span class="dir__t"><b><?php echo esc_html( get_the_title( $r['p'] ) ); ?></b><small><?php echo esc_html( $r['tag'] ); ?></small></span>
							<span class="dir__m">
								<?php if ( $r['min'] ) : ?><em>من <?php echo esc_html( number_format_i18n( $r['min'] ) ); ?> ريال</em><?php endif; ?>
								<?php if ( $r['dur'] ) : ?><em><?php echo esc_html( $r['dur'] ); ?></em><?php endif; ?>
							</span>
							<span class="dir__go"><?php echo zad_icon( 'arrow', 20 ); // phpcs:ignore ?></span>
						</a>
					</li>
					<?php endforeach; ?>
				</ol>
				<?php if ( ! $rows ) : ?><p class="empty">لا توجد خدمات منشورة في هذا القسم بعد.</p><?php endif; ?>
			</section>

			<?php if ( $decide || $tips ) : ?>
			<section class="hubsec" id="choose">
				<h2 class="h-line">كيف تختار الخدمة المناسبة؟</h2>
				<?php if ( $decide ) : ?>
				<div class="decide">
					<?php foreach ( $decide as $d ) : ?>
						<a class="decide__c" href="<?php echo esc_url( get_permalink( $d[1] ) ); ?>"><small>إذا كانت حالتك</small><b><?php echo esc_html( $d[0] ); ?></b><span><?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?> <?php echo esc_html( get_the_title( $d[1] ) ); ?></span></a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( $tips ) : ?><ul class="tips"><?php foreach ( $tips as $t ) { echo '<li>' . zad_icon( 'check', 18 ) . '<span>' . esc_html( $t ) . '</span></li>'; } // phpcs:ignore ?></ul><?php endif; ?>
			</section>
			<?php endif; ?>

			<?php if ( count( $rows ) > 1 ) : ?>
			<section class="hubsec" id="compare">
				<h2 class="h-line">مقارنة سريعة بين الخدمات</h2>
				<div class="tbl"><table>
					<thead><tr><th>الخدمة</th><th>تبدأ من</th><th>المدة</th><th>الضمان</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td data-l="الخدمة"><b><?php echo esc_html( zad_service_base_name( $r['p']->ID ) ); ?></b></td>
							<td data-l="تبدأ من"><?php echo $r['min'] ? esc_html( number_format_i18n( $r['min'] ) . ' ريال' ) : '—'; ?></td>
							<td data-l="المدة"><?php echo esc_html( $r['dur'] ?: '—' ); ?></td>
							<td data-l="الضمان"><?php echo esc_html( $r['war'] ?: '—' ); ?></td>
							<td><a class="iconbtn" href="<?php echo esc_url( get_permalink( $r['p'] ) ); ?>" aria-label="التفاصيل"><?php echo zad_icon( 'arrow', 20 ); // phpcs:ignore ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			</section>
			<?php endif; ?>

			<?php if ( $cities ) : ?>
			<section class="hubsec" id="cities">
				<h2 class="h-line">أين نقدّم هذه الخدمات؟</h2>
				<div class="hubcities">
					<?php foreach ( $cities as $c ) : ?>
						<div class="hubcity"><h3><?php echo zad_icon( 'pin', 20 ); // phpcs:ignore ?> <?php echo esc_html( $c['t']->name ); ?></h3>
						<ul><?php foreach ( array_slice( $c['s'], 0, 6 ) as $sv ) : ?><li><a href="<?php echo esc_url( zad_area_page_url( $sv->ID, $c['t'] ) ?: get_permalink( $sv ) ); ?>"><?php echo esc_html( zad_service_base_name( $sv->ID ) . ' في ' . $c['t']->name ); ?></a></li><?php endforeach; ?></ul></div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $gq->have_posts() ) : ?>
			<section class="hubsec" id="guides">
				<h2 class="h-line">أدلة مفيدة</h2>
				<div class="sgrid"><?php while ( $gq->have_posts() ) { $gq->the_post(); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?></div>
			</section>
			<?php endif; ?>

			<?php if ( $faqs ) : ?>
			<section class="hubsec" id="faq">
				<h2 class="h-line">أسئلة شائعة</h2>
				<div class="faq">
					<?php foreach ( $faqs as $f ) : $ans = has_excerpt( $f ) ? get_the_excerpt( $f ) : wp_trim_words( wp_strip_all_tags( $f->post_content ), 40 ); ?>
						<details class="faq__item"><summary><?php echo esc_html( get_the_title( $f ) ); ?><?php echo zad_icon( 'chevron', 20 ); // phpcs:ignore ?></summary><div class="faq__a"><p><?php echo esc_html( $ans ); ?></p><a class="more" href="<?php echo esc_url( get_permalink( $f ) ); ?>">اقرأ الإجابة كاملة <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a></div></details>
					<?php endforeach; ?>
				</div>
				<p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_faq_url() ); ?>">كل الأسئلة</a></p>
			</section>
			<?php endif; ?>
		</div>
	</div>
	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
