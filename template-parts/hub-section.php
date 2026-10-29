<?php defined( 'ABSPATH' ) || exit;
/**
 * Section hub (3.46.4): a service type (/cleaning/, /pest-control/) or a section page (/cleaning/tanks/, /services/furniture-moving/).
 * Head → city picker → sub-sections → the family's services by city (each city: its pages, its neighbourhoods, its city page)
 * → the section's own text → works → before/after → questions → articles → other sections → request.
 * Built for linking without competing: the H1 stays national («خدمات تنظيف الخزانات»), city groups are titled by the city alone,
 * and every city keyword is carried by the link to that city's own service page.
 */
$d    = zad_hub_data();
$node = zad_sec_current_node();
if ( ! $d || ! $node ) { get_template_part( 'template-parts/hub-simple' ); return; }
$s      = zad_sec_data( $node );
$h1     = $d['h1'];
$topic  = $d['topic'];
$fk     = zad_catalog_key_of_node( $node );
$cat    = zad_catalog();
$fam    = $fk && isset( $cat[ $fk ] ) ? $cat[ $fk ] : null;
$groups = $fk ? zad_catalog_by_city( $fk ) : array();
$crumbs = zad_current_crumbs() ?: array( array( 'الرئيسية', home_url( '/' ) ), array( $h1, '' ) );
$wa     = zad_wa_link( 'مرحباً، أستفسر عن: ' . $h1, $node['id'] );
$color  = $fam ? $fam['color'] : 'var(--primary)';
$icon   = $fam ? $fam['icon'] : 'sparkle';

// the section's own text: the page content (section pages) or «صفحات الأقسام» body (type archives)
$body = ''; $own_faq = array(); $short = false;
if ( 'page' === $node['kind'] ) {
	$p = get_post( $node['id'] );
	if ( $p && zad_sec_is_legacy_body( $p->post_content ) ) { $body = zad_sec_intro_html( $p ); $own_faq = zad_sec_own_faq( $p->ID ); $short = true; } // 3.46.5: the old service layout → intro + its own questions only
	elseif ( $p && '' !== trim( wp_strip_all_tags( $p->post_content ) ) ) { $body = apply_filters( 'the_content', $p->post_content ); $own_faq = zad_sec_own_faq( $p->ID ); }
} else {
	$hub = zad_hub_opt( $node['pt'] );
	if ( ! empty( $hub['body'] ) ) { $body = wpautop( wp_kses_post( $hub['body'] ) ); }
}

// sub-sections (/cleaning/ → tanks, sofa, air conditioning): the families whose page sits under this one
$subs = array();
foreach ( $s['subs'] as $sid ) {
	$sk = zad_catalog_landing_ids()[ $sid ] ?? '';
	$sf = $sk && isset( $cat[ $sk ] ) ? $cat[ $sk ] : null;
	$subs[] = array( 'url' => get_permalink( $sid ), 'name' => $sf ? $sf['name'] : zad_sec_name( array( 'kind' => 'page', 'pt' => $node['pt'], 'id' => $sid ) ), 'icon' => $sf ? $sf['icon'] : 'sparkle', 'color' => $sf ? $sf['color'] : $color, 'n' => $sf ? count( $sf['items'] ) + array_sum( array_map( 'count', (array) $sf['hoods'] ) ) : count( zad_sec_children( $node['pt'], $sid ) ), 'img' => zad_sec_image( $sid ) );
}
// no family (an older section): its direct pages as the one group
if ( ! $groups ) {
	foreach ( $s['svc'] as $cid ) {
		$sc = zad_service_city_scope( $cid ); $c = in_array( $sc, array( '', 'all' ), true ) ? '' : zad_city_label( $sc );
		$groups[ $c ]['items'][] = array( 'id' => $cid, 'url' => get_permalink( $cid ), 'label' => zad_catalog_label( zad_card_title( $cid ), $c ), 'city' => $c, 'img' => zad_sec_image( $cid ), 'min' => zad_sec_min_price( array( $cid ) ) );
		$groups[ $c ] += array( 'hoods' => array(), 'hub' => '' !== $c ? zad_city_hub_url( $c ) : '' );
	}
}
$n_items = 0; $n_hoods = 0; $mins = array();
foreach ( $groups as $g ) { $n_items += count( $g['items'] ); $n_hoods += count( $g['hoods'] ); foreach ( $g['items'] as $it ) { if ( $it['min'] ) { $mins[] = $it['min']; } } }
$cities = array_values( array_filter( array_keys( $groups ), 'strlen' ) );
$pick   = array(); foreach ( $groups as $c => $g ) { if ( '' !== $c ) { $pick[ $c ] = $g['hub'] ?: ( $g['items'] ? $g['items'][0]['url'] : ( $g['hoods'] ? $g['hoods'][0][1] : '' ) ); } } // the city page (/areas/city/); the first page only if the city has none
$others = array_filter( $cat, function ( $f, $k ) use ( $fk, $subs ) { return $k !== $fk && ! in_array( $f['url'], array_column( $subs, 'url' ), true ); }, ARRAY_FILTER_USE_BOTH );

$faq_term = null; $art_term = null;
if ( function_exists( 'zad_hub_match_term' ) ) {
	$faq_term = zad_hub_match_term( zad_hub_taxonomy_of( zad_faq_types() ), $h1, '' );
	$art_term = zad_hub_match_term( zad_hub_taxonomy_of( zad_article_types() ), $h1, '' );
}
$faqs     = zad_sec_faqs( $s['tree'], $faq_term, 6 );
$articles = zad_sec_articles( $s['tree'], $art_term, $s['guides'], 6 );
if ( count( $articles ) < 3 && $fk ) { $have = wp_list_pluck( $articles, 'ID' ); foreach ( zad_catalog_articles( $fk, 9 ) as $aid ) { if ( count( $articles ) >= 6 ) { break; } if ( ! in_array( $aid, $have, true ) ) { $articles[] = get_post( $aid ); } } $articles = array_values( array_filter( $articles ) ); }
$works    = array_filter( array_map( 'get_post', $s['works'] ) );
$media    = $s['media'];
$show     = 8;

?>
<main id="main" class="zsec zcat zhubp" style="--fa:<?php echo esc_attr( $color ); ?>">
	<section class="zsec__head">
		<div class="wrap">
			<?php zad_render_crumbs( $crumbs ); ?>
			<div class="zsec__grid">
				<div>
					<span class="zhubp__eyebrow"><span class="zhubp__ic"><?php echo zad_icon( $icon, 18 ); // phpcs:ignore ?></span> قسم</span>
					<h1 class="zsec__h1"><?php echo esc_html( $h1 ); ?></h1>
					<p class="zsec__lead"><?php echo esc_html( $d['lead'] ); ?></p>
					<div class="zsec__btns">
						<button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب معاينة مجانية</button>
						<?php if ( $wa ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
					</div>
				</div>
				<ul class="zsec__stats">
					<li><b><?php echo (int) $n_items; ?></b><span>خدمة</span></li>
					<?php if ( $cities ) : ?><li><b><?php echo (int) count( $cities ); ?></b><span><?php echo count( $cities ) > 2 && count( $cities ) < 11 ? 'مدن' : 'مدينة'; ?></span></li><?php endif; ?>
					<?php if ( $n_hoods ) : ?><li><b><?php echo (int) $n_hoods; ?></b><span>صفحة حي</span></li><?php elseif ( $mins ) : ?><li><b><?php echo esc_html( number_format_i18n( min( $mins ) ) ); ?></b><span>ريال يبدأ من</span></li><?php endif; ?>
				</ul>
			</div>
			<?php if ( count( $pick ) > 1 ) : ?>
			<nav class="zhubp__pick" aria-label="اختر مدينتك">
				<b><?php echo zad_icon( 'pin', 18 ); // phpcs:ignore ?> اختر مدينتك:</b>
				<?php foreach ( $pick as $c => $u ) : ?><a href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( $c ); ?></a><?php endforeach; ?>
			</nav>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $body && $short ) : ?>
	<section class="sec zhubp__intro" id="about"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
	<?php endif; ?>

	<?php if ( $subs ) : ?>
	<section class="sec" id="subs"><div class="wrap">
		<header class="sec__head"><h2>أقسام <?php echo esc_html( $topic ); ?></h2></header>
		<ul class="zhubp__subs">
			<?php foreach ( $subs as $sb ) : ?>
			<li style="--fa:<?php echo esc_attr( $sb['color'] ); ?>"><a href="<?php echo esc_url( $sb['url'] ); ?>">
				<span class="zhubp__simg"><?php echo $sb['img'] ? wp_get_attachment_image( $sb['img'], 'medium', false, array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="zsv__ph">' . zad_icon( $sb['icon'], 40 ) . '</span>'; // phpcs:ignore ?></span>
				<span class="zhubp__sb"><span class="zcat__ti"><?php echo zad_icon( $sb['icon'], 22 ); // phpcs:ignore ?></span><b><?php echo esc_html( $sb['name'] ); ?></b><small><?php echo (int) $sb['n']; ?> صفحة</small></span>
			</a></li>
			<?php endforeach; ?>
		</ul>
	</div></section>
	<?php endif; ?>

	<?php if ( $groups ) : ?>
	<section class="sec<?php echo $subs ? ' sec--tint' : ''; ?>" id="cities"><div class="wrap">
		<header class="sec__head"><h2><?php echo esc_html( $fam ? $fam['name'] : $topic ); ?> حسب المدينة</h2><p>اختر مدينتك وادخل صفحة الخدمة فيها، أو صفحة الحي لو موجودة.</p></header>
		<?php foreach ( $groups as $c => $g ) : ?>
		<section class="zhubp__city" id="city-<?php echo esc_attr( sanitize_title( $c ) ?: 'all' ); ?>" aria-label="<?php echo esc_attr( '' !== $c ? $c : 'خدمات عامة' ); ?>">
			<header class="zhubp__ch">
				<span class="zcity__pin"><?php echo zad_icon( 'pin', 20 ); // phpcs:ignore ?></span>
				<h3><?php echo esc_html( '' !== $c ? $c : 'خدمات عامة' ); ?></h3>
				<small><?php echo (int) count( $g['items'] ); ?> خدمة<?php echo $g['hoods'] ? ' · ' . (int) count( $g['hoods'] ) . ' حي' : ''; ?></small>
				<?php if ( $g['hub'] ) : ?><a class="zhubp__hub" href="<?php echo esc_url( $g['hub'] ); ?>">كل خدماتنا في <?php echo esc_html( $c ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a><?php endif; ?>
			</header>
			<?php if ( $g['items'] ) : ?>
			<ul class="zfb__grid">
				<?php foreach ( $g['items'] as $i => $it ) : ?>
				<li<?php echo $i >= $show ? ' class="is-more" hidden' : ''; ?>><a class="zsv" href="<?php echo esc_url( $it['url'] ); ?>">
					<span class="zsv__img"><?php echo $it['img'] ? wp_get_attachment_image( $it['img'], 'medium', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => '(max-width:640px) 50vw, 300px' ) ) : '<span class="zsv__ph">' . zad_icon( $icon, 40 ) . '</span>'; // phpcs:ignore ?></span>
					<span class="zsv__b"><b class="zsv__t"><?php echo esc_html( $it['label'] . ( '' !== $c && false === mb_strpos( $it['label'], $c ) ? ' ب' . $c : '' ) ); ?></b><span class="zsv__p"><?php echo $it['min'] ? 'من <b>' . esc_html( number_format_i18n( $it['min'] ) ) . '</b> ريال' : 'معاينة مجانية'; ?><?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span></span>
				</a></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $g['items'] ) > $show ) : ?><button type="button" class="zfb__more" data-zhp-more>عرض الكل (<?php echo (int) count( $g['items'] ); ?>)</button><?php endif; ?>
			<?php endif; ?>
			<?php if ( $g['hoods'] ) : ?>
			<div class="zcityp__hoods"><h4><?php echo zad_icon( 'map', 16 ); // phpcs:ignore ?> الأحياء</h4><ul><?php foreach ( $g['hoods'] as $h ) : ?><li><a href="<?php echo esc_url( $h[1] ); ?>"><?php echo esc_html( $h[0] ); ?></a></li><?php endforeach; ?></ul></div>
			<?php endif; ?>
		</section>
		<?php endforeach; ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $body && ! $short ) : ?>
	<section class="sec zsec__about" id="about"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></div></section>
	<?php endif; ?>

	<?php if ( $works ) : ?>
	<section class="sec" id="works"><div class="wrap">
		<header class="sec__head"><h2>من أعمالنا في <?php echo esc_html( $topic ); ?></h2></header>
		<div class="sgrid"><?php global $post; foreach ( $works as $post ) { setup_postdata( $post ); get_template_part( 'template-parts/work-card' ); } wp_reset_postdata(); ?></div>
		<?php if ( function_exists( 'zad_works_url' ) && zad_works_url() ) : ?><p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_works_url() ); ?>">كل أعمالنا</a></p><?php endif; ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $media['ba'] || $media['img'] ) : ?>
	<section class="sec<?php echo $works ? ' sec--tint' : ''; ?>" id="photos"><div class="wrap">
		<header class="sec__head"><h2>صور قبل وبعد</h2></header>
		<div class="zsec__photos">
			<?php foreach ( $media['ba'] as $b ) : ?>
			<figure class="ba" data-ba>
				<div class="ba__stage">
					<?php echo wp_get_attachment_image( $b[1], 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after' ) ); // phpcs:ignore ?>
					<div class="ba__before"><?php echo wp_get_attachment_image( $b[0], 'large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore ?></div>
					<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
					<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
				</div>
				<figcaption><a href="<?php echo esc_url( $b[3] ); ?>"><?php echo esc_html( $b[2] ); ?></a></figcaption>
			</figure>
			<?php endforeach; ?>
			<?php foreach ( $media['img'] as $m ) : ?>
			<figure class="zsec__img"><a href="<?php echo esc_url( $m[2] ); ?>"><?php echo wp_get_attachment_image( $m[0], 'large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore ?></a><figcaption><?php echo esc_html( $m[1] ); ?></figcaption></figure>
			<?php endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<?php if ( $faqs || $own_faq ) : ?>
	<section class="sec" id="faq"><div class="wrap wrap--narrow">
		<header class="sec__head"><h2>أسئلة شائعة عن <?php echo esc_html( $topic ); ?></h2></header>
		<?php if ( $own_faq ) { zad_render_faq( $own_faq ); } ?>
		<?php if ( $faqs ) : ?>
		<?php if ( $own_faq ) : ?><h3 class="zhubp__fq">أسئلة تفصيلية ذات صلة</h3><?php endif; ?>
		<div class="faq">
			<?php foreach ( $faqs as $f ) : $ans = function_exists( 'zad_faq_answer_text' ) ? zad_faq_answer_text( $f->ID ) : ''; if ( '' === trim( (string) $ans ) ) { $ans = has_excerpt( $f ) ? get_the_excerpt( $f ) : wp_trim_words( wp_strip_all_tags( $f->post_content ), 40 ); } ?>
			<details class="faq__item"><summary><?php echo esc_html( get_the_title( $f ) ); ?><?php echo zad_icon( 'chevron', 20 ); // phpcs:ignore ?></summary><div class="faq__a"><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $ans ), 60 ) ); ?></p><a class="more" href="<?php echo esc_url( get_permalink( $f ) ); ?>">الإجابة كاملة</a></div></details>
			<?php endforeach; ?>
		</div>
		<p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( $faq_term ? get_term_link( $faq_term ) : zad_faq_url() ); ?>">كل الأسئلة</a></p>
		<?php endif; ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $articles ) : ?>
	<section class="sec sec--tint" id="articles"><div class="wrap">
		<header class="sec__head"><h2>مقالات ونصائح عن <?php echo esc_html( $topic ); ?></h2></header>
		<div class="sgrid"><?php global $post; foreach ( $articles as $post ) { setup_postdata( $post ); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?></div>
		<?php if ( $art_term && ! is_wp_error( get_term_link( $art_term ) ) ) : ?><p class="sec__more"><a class="btn btn--ghost-dark" href="<?php echo esc_url( get_term_link( $art_term ) ); ?>">كل المقالات</a></p><?php endif; ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $others ) : ?>
	<nav class="zcat__tiles zhubp__others" aria-label="أقسام أخرى"><div class="wrap">
		<h2 class="zhubp__oh">أقسام أخرى</h2>
		<ul>
			<?php foreach ( $others as $ok => $of ) : ?>
			<li style="--fa:<?php echo esc_attr( $of['color'] ); ?>"><a href="<?php echo esc_url( $of['url'] ); ?>"><span class="zcat__ti"><?php echo zad_icon( $of['icon'], 24 ); // phpcs:ignore ?></span><b><?php echo esc_html( $of['name'] ); ?></b><small><?php echo (int) count( $of['items'] ); ?> خدمة</small></a></li>
			<?php endforeach; ?>
		</ul>
	</div></nav>
	<?php endif; ?>

	<?php if ( zad_services_url() && untrailingslashit( zad_services_url() ) !== untrailingslashit( $node['url'] ) ) : ?>
	<p class="wrap zsec__all"><a href="<?php echo esc_url( zad_services_url() ); ?>"><?php echo zad_icon( 'list', 18 ); // phpcs:ignore ?> كل خدمات <?php echo esc_html( zad_brand_name() ); ?></a></p>
	<?php endif; ?>
	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
<script>document.querySelectorAll('[data-zhp-more]').forEach(function(b){b.addEventListener('click',function(){b.closest('.zhubp__city').querySelectorAll('.is-more').forEach(function(li){li.hidden=false;});b.remove();});});</script>
