<?php defined( 'ABSPATH' ) || exit;
/**
 * City page (3.46.3): /areas/{city}/.
 * Head → the city's own text (term description, else a suggested text) → its sections as tiles → one band per section
 * (photo cards of the city's services + its neighbourhood pages + a link to the section page) → the cities under it →
 * articles about the city → the other cities → request. Built from zad_city_catalog() (inc/zad-sections.php).
 */
$d    = zad_hub_data();
$term = get_queried_object();
if ( ! $d || ! $term instanceof WP_Term ) { get_template_part( 'template-parts/hub-simple' ); return; }
$name  = $term->name;
$cat   = zad_city_catalog( $term );
list( $intro, $suggested ) = zad_city_intro( $term );
$h1    = $d['h1'];
$total = 0; $nh = 0; $mins = array(); $ids = array();
foreach ( $cat as $c ) { $total += count( $c['items'] ); $nh += count( $c['hoods'] ); foreach ( $c['items'] as $it ) { $ids[] = $it['id']; if ( $it['min'] ) { $mins[] = $it['min']; } } }
$crumbs = array( array( 'الرئيسية', home_url( '/' ) ) );
if ( zad_services_url() ) { $crumbs[] = array( 'الخدمات', zad_services_url() ); }
if ( $term->parent ) { $pt = get_term( $term->parent, 'service_area' ); if ( $pt && ! is_wp_error( $pt ) ) { $crumbs[] = array( $pt->name, get_term_link( $pt ) ); } }
$crumbs[] = array( $name, '' );
$wa    = zad_wa_link( 'مرحباً، أرغب بخدمة في ' . $name, 0 );
$kids  = array_filter( (array) get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false, 'parent' => $term->term_id ) ), function ( $t ) { return $t instanceof WP_Term; } );
$others = array_filter( zad_city_terms(), function ( $t ) use ( $term ) { return $t->term_id !== $term->term_id && $t->parent !== $term->term_id && ! $t->parent; } );
// articles that talk about the city (not service pages)
$arts = array();
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 12, 's' => $name, 'post__not_in' => $ids ? $ids : array( 0 ), 'suppress_filters' => false, 'no_found_rows' => true ) ) as $p ) {
	if ( false !== mb_strpos( zad_city_norm_name( $p->post_title ), zad_city_norm_name( $name ) ) && ( zad_catalog_is_article( $p->post_title ) || ! preg_match( '/شركة|مؤسسة/u', $p->post_title ) ) ) { $arts[] = $p; }
	if ( count( $arts ) >= 6 ) { break; }
}
$show = 8;
?>
<main id="main" class="zsec zcat zcityp">
	<section class="zsec__head">
		<div class="wrap">
			<?php zad_render_crumbs( $crumbs ); ?>
			<div class="zsec__grid">
				<div>
					<span class="zcityp__eyebrow"><?php echo zad_icon( 'pin', 18 ); // phpcs:ignore ?> <?php echo esc_html( $name ); ?></span>
					<h1 class="zsec__h1"><?php echo esc_html( $h1 ); ?></h1>
					<p class="zsec__lead">كل خدماتنا في <?php echo esc_html( $name ); ?> في صفحة واحدة: اختر القسم، وادخل صفحة الخدمة لتعرف التفاصيل والسعر، أو اطلب معاينة مجانية.</p>
					<div class="zsec__btns">
						<button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب معاينة في <?php echo esc_html( $name ); ?></button>
						<?php if ( $wa ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
					</div>
				</div>
				<ul class="zsec__stats">
					<li><b><?php echo (int) count( $cat ); ?></b><span><?php echo count( $cat ) > 2 && count( $cat ) < 11 ? 'أقسام' : 'قسم'; ?></span></li>
					<li><b><?php echo (int) $total; ?></b><span>خدمة</span></li>
					<?php if ( $nh ) : ?><li><b><?php echo (int) $nh; ?></b><span>صفحة حي</span></li><?php elseif ( $mins ) : ?><li><b><?php echo esc_html( number_format_i18n( min( $mins ) ) ); ?></b><span>ريال يبدأ من</span></li><?php endif; ?>
				</ul>
			</div>
		</div>
	</section>

	<section class="sec zcityp__intro"><div class="wrap wrap--narrow">
		<div class="prose entry-content"><?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php if ( $suggested && current_user_can( 'manage_categories' ) ) : ?><p class="zcityp__note">النص ده مقترح ولسه مش محفوظ في المدينة. اكتب نصك من <a href="<?php echo esc_url( get_edit_term_link( $term, 'service_area' ) ); ?>">تعديل المدينة ← الوصف</a>، أو احفظ المقترح من أدوات ← صفحات المدن (زاد). (الرسالة دي بتظهر لك انت بس)</p><?php endif; ?>
	</div></section>

	<?php if ( $cat ) : ?>
	<nav class="zcat__tiles" aria-label="أقسام الخدمات في <?php echo esc_attr( $name ); ?>"><div class="wrap">
		<ul>
			<?php foreach ( $cat as $k => $c ) : ?>
			<li style="--fa:<?php echo esc_attr( $c['fam']['color'] ); ?>"><a href="#fam-<?php echo esc_attr( $k ); ?>"><span class="zcat__ti"><?php echo zad_icon( $c['fam']['icon'], 24 ); // phpcs:ignore ?></span><b><?php echo esc_html( $c['fam']['name'] ); ?></b><small><?php echo (int) ( count( $c['items'] ) + count( $c['hoods'] ) ); ?> صفحة</small></a></li>
			<?php endforeach; ?>
		</ul>
	</div></nav>

	<section class="sec zdir" id="sections"><div class="wrap">
		<?php $n = 0; foreach ( $cat as $k => $c ) : $n++; $f = $c['fam']; ?>
		<section class="zfb" id="fam-<?php echo esc_attr( $k ); ?>" style="--fa:<?php echo esc_attr( $f['color'] ); ?>" aria-labelledby="fam-<?php echo esc_attr( $k ); ?>-t">
			<header class="zfb__head">
				<span class="zfb__ic" aria-hidden="true"><?php echo zad_icon( $f['icon'], 30 ); // phpcs:ignore ?></span>
				<div class="zfb__ttl">
					<span class="zfb__no"><?php echo esc_html( str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h2 id="fam-<?php echo esc_attr( $k ); ?>-t"><?php echo esc_html( $f['name'] . ' في ' . $name ); ?></h2>
					<p class="zfb__meta"><?php if ( $c['items'] ) : ?><span><?php echo (int) count( $c['items'] ); ?> خدمة</span><?php endif; ?><?php if ( $c['hoods'] ) : ?><span><?php echo (int) count( $c['hoods'] ); ?> صفحة حي</span><?php endif; ?></p>
				</div>
				<div class="zfb__act">
					<a class="zfb__go" href="<?php echo esc_url( $f['url'] ); ?>">كل <?php echo esc_html( $f['name'] ); ?> <?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></a>
					<?php $fw = zad_wa_link( 'مرحباً، أرغب بخدمة ' . $f['name'] . ' في ' . $name, 0 ); if ( $fw ) : ?><a class="zfb__wa" href="<?php echo esc_url( $fw ); ?>" target="_blank" rel="noopener" aria-label="اسأل عن سعر <?php echo esc_attr( $f['name'] ); ?> في <?php echo esc_attr( $name ); ?>"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?><span>اسأل عن السعر</span></a><?php endif; ?>
				</div>
				<span class="zfb__mark" aria-hidden="true"><?php echo zad_icon( $f['icon'], 160 ); // phpcs:ignore ?></span>
			</header>
			<?php if ( $c['items'] ) : ?>
			<ul class="zfb__grid">
				<?php foreach ( $c['items'] as $i => $it ) : ?>
				<li<?php echo $i >= $show ? ' class="is-more" hidden' : ''; ?>>
					<a class="zsv" href="<?php echo esc_url( $it['url'] ); ?>">
						<span class="zsv__img"><?php echo $it['img'] ? wp_get_attachment_image( $it['img'], 'medium', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => '(max-width:640px) 50vw, 300px' ) ) : '<span class="zsv__ph">' . zad_icon( $f['icon'], 40 ) . '</span>'; // phpcs:ignore ?><?php if ( zad_city_norm_name( $it['city'] ) !== zad_city_norm_name( $name ) ) : ?><span class="zsv__city"><?php echo zad_icon( 'pin', 13 ); // phpcs:ignore ?><?php echo esc_html( $it['city'] ); ?></span><?php endif; ?></span>
						<span class="zsv__b">
							<b class="zsv__t"><?php echo esc_html( $it['label'] ); ?></b>
							<span class="zsv__p"><?php echo $it['min'] ? 'من <b>' . esc_html( number_format_i18n( $it['min'] ) ) . '</b> ريال' : 'معاينة مجانية'; ?><?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span>
						</span>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $c['items'] ) > $show ) : ?><button type="button" class="zfb__more" data-zcp-more>عرض كل خدمات <?php echo esc_html( $f['name'] ); ?> في <?php echo esc_html( $name ); ?> (<?php echo (int) count( $c['items'] ); ?>)</button><?php endif; ?>
			<?php endif; ?>
			<?php if ( $c['hoods'] ) : ?>
			<div class="zcityp__hoods">
				<h3><?php echo zad_icon( 'map', 18 ); // phpcs:ignore ?> خدمات أخرى في <?php echo esc_html( $f['name'] ); ?></h3>
				<ul><?php foreach ( $c['hoods'] as $h ) : ?><li><a href="<?php echo esc_url( $h[1] ); ?>"><?php echo esc_html( $h[0] ); ?></a></li><?php endforeach; ?></ul>
			</div>
			<?php endif; ?>
		</section>
		<?php endforeach; ?>
	</div></section>
	<?php else : ?>
	<section class="sec"><div class="wrap"><p class="empty">لسه مفيش صفحات خدمة منشورة في <?php echo esc_html( $name ); ?>. اطلب الخدمة وهنرتب لك الزيارة.</p></div></section>
	<?php endif; ?>

	<?php if ( $kids ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><h2>مدن <?php echo esc_html( $name ); ?></h2></header>
		<ul class="zcity__grid">
			<?php foreach ( $kids as $kt ) : $kc = zad_city_catalog( $kt ); $kn = 0; foreach ( $kc as $x ) { $kn += count( $x['items'] ); } ?>
			<li class="zcity__card">
				<div class="zcity__top"><span class="zcity__pin"><?php echo zad_icon( 'pin', 22 ); // phpcs:ignore ?></span><div><h3><a href="<?php echo esc_url( get_term_link( $kt ) ); ?>"><?php echo esc_html( $kt->name ); ?></a></h3><small><?php echo (int) $kn; ?> خدمة</small></div></div>
				<?php if ( $kc ) : ?><ul class="zcity__fams"><?php foreach ( $kc as $x ) : ?><li style="--fa:<?php echo esc_attr( $x['fam']['color'] ); ?>"><a href="<?php echo esc_url( $x['items'] ? $x['items'][0]['url'] : $x['hoods'][0][1] ); ?>"><?php echo zad_icon( $x['fam']['icon'], 14 ); // phpcs:ignore ?><?php echo esc_html( $x['fam']['name'] ); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
				<a class="zcity__all" href="<?php echo esc_url( get_term_link( $kt ) ); ?>">كل خدماتنا في <?php echo esc_html( $kt->name ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div></section>
	<?php endif; ?>

	<?php if ( $arts ) : ?>
	<section class="sec<?php echo $kids ? '' : ' sec--tint'; ?>" id="articles"><div class="wrap">
		<header class="sec__head"><h2>مقالات ونصائح عن <?php echo esc_html( $name ); ?></h2></header>
		<div class="sgrid"><?php global $post; foreach ( $arts as $post ) { setup_postdata( $post ); get_template_part( 'template-parts/post-card' ); } wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<?php if ( $others ) : ?>
	<section class="sec zcityp__others"><div class="wrap">
		<header class="sec__head"><h2>خدماتنا في مدن أخرى</h2></header>
		<ul class="zcityp__chips"><?php foreach ( $others as $ot ) : ?><li><a href="<?php echo esc_url( get_term_link( $ot ) ); ?>"><?php echo zad_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $ot->name ); ?></a></li><?php endforeach; ?>
		<?php if ( zad_services_url() ) : ?><li class="is-all"><a href="<?php echo esc_url( zad_services_url() ); ?>"><?php echo zad_icon( 'list', 16 ); // phpcs:ignore ?> كل الخدمات</a></li><?php endif; ?></ul>
	</div></section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
<script>document.querySelectorAll('[data-zcp-more]').forEach(function(b){b.addEventListener('click',function(){b.closest('.zfb').querySelectorAll('.is-more').forEach(function(li){li.hidden=false;});b.remove();});});</script>
