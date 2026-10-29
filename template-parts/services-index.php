<?php defined( 'ABSPATH' ) || exit;
/**
 * /services/ (3.46.2): the services catalog of the whole site, grouped in families (inc/zad-sections.php → zad_catalog()).
 * Hero → family tiles → search + city bar → one band per family (intro + photo cards of its pages) → services by city → CTA.
 * Text: «عنوان صفحة الخدمات» (zad_services_title) and «مقدمة صفحة الخدمات» (zad_services_intro) in the theme settings.
 */
$brand = zad_brand_name();
$cat   = zad_catalog();
$all_cities = array(); $total = 0;
foreach ( $cat as $k => $f ) {
	$total += count( $f['items'] );
	foreach ( $f['items'] as $it ) { if ( '' !== $it['city'] ) { $all_cities[ $it['city'] ][ $k ] = $all_cities[ $it['city'] ][ $k ] ?? $it['url']; } }
}
uasort( $all_cities, function ( $a, $b ) { return count( $b ) - count( $a ); } );
$main  = array_values( zad_city_map() );
$chips = array_slice( array_values( array_unique( array_merge( array_values( array_intersect( array_keys( $all_cities ), $main ) ), array_keys( $all_cities ) ) ) ), 0, 8 ); // the main cities first, then the most covered: 8 at most
$title = trim( (string) zad_opt( 'zad_services_title', '' ) ) ?: 'خدمات ' . $brand;
$intro = trim( (string) zad_opt( 'zad_services_intro', '' ) );
$lead  = 'دليل كل خدماتنا في ' . zad_hub_cities_text( array_slice( $chips, 0, 5 ) ) . ': اختر القسم أو مدينتك، وادخل صفحة الخدمة لتعرف التفاصيل والسعر، أو اطلب معاينة مجانية.';
$crumbs = array( array( 'الرئيسية', home_url( '/' ) ), array( 'الخدمات', '' ) );
$wa    = zad_wa_link( 'مرحباً، أرغب بالاستفسار عن خدماتكم', 0 );
$url   = zad_services_url();
$show  = 8;
$els   = array(); $pos = 1;
foreach ( $cat as $f ) { $els[] = array( '@type' => 'ListItem', 'position' => $pos++, 'item' => array( '@type' => 'WebPage', 'name' => $f['name'], 'url' => $f['url'] ) ); }
?>
<main id="main" class="zsec zsi zcat">
	<section class="zsec__head zcat__hero">
		<div class="wrap">
			<?php zad_render_crumbs( $crumbs ); ?>
			<div class="zsec__grid">
				<div>
					<h1 class="zsec__h1"><?php echo esc_html( $title ); ?></h1>
					<p class="zsec__lead"><?php echo esc_html( $lead ); ?></p>
					<div class="zsec__btns">
						<button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب معاينة مجانية</button>
						<?php if ( $wa ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> واتساب</a><?php endif; ?>
					</div>
				</div>
				<ul class="zsec__stats">
					<li><b><?php echo (int) count( $cat ); ?></b><span>قسم</span></li>
					<li><b><?php echo (int) $total; ?></b><span>خدمة</span></li>
					<?php if ( $all_cities ) : ?><li><b><?php echo (int) count( $all_cities ); ?></b><span><?php echo count( $all_cities ) > 2 ? 'مدن' : 'مدينة'; ?></span></li><?php endif; ?>
				</ul>
			</div>
		</div>
	</section>

	<?php if ( $cat ) : ?>
	<nav class="zcat__tiles" aria-label="أقسام الخدمات"><div class="wrap">
		<ul>
			<?php foreach ( $cat as $k => $f ) : ?>
			<li style="--fa:<?php echo esc_attr( $f['color'] ); ?>" data-zdir-tile="<?php echo esc_attr( $k ); ?>"><a href="#fam-<?php echo esc_attr( $k ); ?>"><span class="zcat__ti"><?php echo zad_icon( $f['icon'], 24 ); // phpcs:ignore ?></span><b><?php echo esc_html( $f['name'] ); ?></b><small><?php echo (int) count( $f['items'] ); ?> خدمة</small></a></li>
			<?php endforeach; ?>
		</ul>
	</div></nav>
	<?php endif; ?>

	<?php if ( $intro ) : ?>
	<section class="sec zsec__about"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div></div></section>
	<?php endif; ?>

	<section class="sec zdir" id="sections"><div class="wrap">
		<?php if ( $cat ) : ?>
		<div class="zdir__bar" data-zdir-bar>
			<label class="zdir__q"><?php echo zad_icon( 'search', 20 ); // phpcs:ignore ?><input type="search" placeholder="ابحث عن خدمة… مثلاً: جلي بلاط" aria-label="ابحث عن خدمة" data-zdir-q autocomplete="off"></label>
			<?php if ( count( $chips ) > 1 ) : ?>
			<div class="zdir__cities" role="group" aria-label="المدينة">
				<button type="button" class="is-on" data-zdir-city="" aria-pressed="true">كل المدن</button>
				<?php foreach ( $chips as $c ) : ?><button type="button" data-zdir-city="<?php echo esc_attr( $c ); ?>" aria-pressed="false"><?php echo esc_html( $c ); ?></button><?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<?php $n = 0; foreach ( $cat as $k => $f ) : $n++; ?>
		<section class="zfb" id="fam-<?php echo esc_attr( $k ); ?>" data-zdir-fam="<?php echo esc_attr( $k ); ?>" style="--fa:<?php echo esc_attr( $f['color'] ); ?>" aria-labelledby="fam-<?php echo esc_attr( $k ); ?>-t">
			<header class="zfb__head">
				<span class="zfb__ic" aria-hidden="true"><?php echo zad_icon( $f['icon'], 30 ); // phpcs:ignore ?></span>
				<div class="zfb__ttl">
					<span class="zfb__no"><?php echo esc_html( str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h2 id="fam-<?php echo esc_attr( $k ); ?>-t"><a href="<?php echo esc_url( $f['url'] ); ?>"><?php echo esc_html( $f['name'] ); ?></a></h2>
					<p class="zfb__meta"><span><?php echo (int) count( $f['items'] ); ?> خدمة</span><?php if ( $f['cities'] ) : ?><span><?php echo (int) count( $f['cities'] ); ?> <?php echo count( $f['cities'] ) > 2 ? 'مدن' : 'مدينة'; ?></span><?php endif; ?><?php if ( $f['min'] ) : ?><span>تبدأ من <b><?php echo esc_html( number_format_i18n( $f['min'] ) ); ?></b> ريال</span><?php endif; ?></p>
				</div>
				<div class="zfb__act">
					<a class="zfb__go" href="<?php echo esc_url( $f['url'] ); ?>"><?php echo $f['landed'] ? 'صفحة القسم' : 'تفاصيل الخدمة'; ?> <?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></a>
					<?php $fw = zad_wa_link( 'مرحباً، أرغب بالاستفسار عن خدمة ' . $f['name'], 0 ); if ( $fw ) : ?><a class="zfb__wa" href="<?php echo esc_url( $fw ); ?>" target="_blank" rel="noopener" aria-label="اسأل عن سعر <?php echo esc_attr( $f['name'] ); ?> على واتساب"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?><span>اسأل عن السعر</span></a><?php endif; ?>
				</div>
				<span class="zfb__mark" aria-hidden="true"><?php echo zad_icon( $f['icon'], 160 ); // phpcs:ignore ?></span>
			</header>
			<div class="zfb__intro">
				<p><?php echo esc_html( $f['desc'] ); ?></p>
				<?php if ( count( $f['cities'] ) > 1 ) : ?>
				<ul class="zfb__cities" aria-label="مدن <?php echo esc_attr( $f['name'] ); ?>"><?php foreach ( $f['cities'] as $c => $u ) : ?><li><a href="<?php echo esc_url( $u ); ?>"><?php echo zad_icon( 'pin', 14 ); // phpcs:ignore ?><?php echo esc_html( $c ); ?></a></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
			<ul class="zfb__grid">
				<?php foreach ( $f['items'] as $i => $it ) : ?>
				<li data-zdir-c="<?php echo esc_attr( $it['city'] ); ?>" data-zdir-t="<?php echo esc_attr( $it['title'] . ' ' . $it['city'] . ' ' . $f['name'] ); ?>"<?php echo $i >= $show ? ' class="is-more" hidden' : ''; ?>>
					<a class="zsv" href="<?php echo esc_url( $it['url'] ); ?>">
						<span class="zsv__img"><?php echo $it['img'] ? wp_get_attachment_image( $it['img'], 'medium', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => '(max-width:640px) 50vw, 300px' ) ) : '<span class="zsv__ph">' . zad_icon( $f['icon'], 40 ) . '</span>'; // phpcs:ignore ?><?php if ( '' !== $it['city'] ) : ?><span class="zsv__city"><?php echo zad_icon( 'pin', 13 ); // phpcs:ignore ?><?php echo esc_html( $it['city'] ); ?></span><?php endif; ?></span>
						<span class="zsv__b">
							<b class="zsv__t"><?php echo esc_html( $it['label'] ); ?></b>
							<span class="zsv__p"><?php echo $it['min'] ? 'من <b>' . esc_html( number_format_i18n( $it['min'] ) ) . '</b> ريال' : 'معاينة مجانية'; ?><?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></span>
						</span>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $f['items'] ) > $show ) : ?><button type="button" class="zfb__more" data-zdir-more>عرض كل خدمات <?php echo esc_html( $f['name'] ); ?> (<?php echo (int) count( $f['items'] ); ?>)</button><?php endif; ?>
		</section>
		<?php endforeach; ?>
		<p class="zdir__none" data-zdir-none hidden>مفيش خدمة بالاسم ده في المدينة المختارة. جرّب كلمة تانية، أو <button type="button" class="btn-link" data-open-wizard>اطلب معاينة مجانية</button> وهنرد عليك.</p>
		<?php else : ?><p class="empty">لا توجد خدمات منشورة بعد.</p><?php endif; ?>
	</div></section>

	<?php if ( count( $all_cities ) > 1 ) : ?>
	<section class="sec sec--tint zcity" id="cities"><div class="wrap">
		<header class="sec__head"><h2>خدماتنا حسب المدينة</h2><p>اختر مدينتك لتشوف الأقسام المتاحة فيها.</p></header>
		<ul class="zcity__grid">
			<?php foreach ( $all_cities as $c => $fams ) : $cnt = 0; foreach ( $cat as $f ) { foreach ( $f['items'] as $it ) { if ( $it['city'] === $c ) { $cnt++; } } } ?>
			<li class="zcity__card">
				<?php $cu = zad_city_hub_url( $c ); ?><div class="zcity__top"><span class="zcity__pin"><?php echo zad_icon( 'pin', 22 ); // phpcs:ignore ?></span><div><h3><?php if ( $cu ) : ?><a href="<?php echo esc_url( $cu ); ?>"><?php echo esc_html( $c ); ?></a><?php else : ?><?php echo esc_html( $c ); ?><?php endif; ?></h3><small><?php echo (int) $cnt; ?> خدمة في <?php echo (int) count( $fams ); ?> <?php echo count( $fams ) > 2 ? 'أقسام' : 'قسم'; ?></small></div></div>
				<ul class="zcity__fams"><?php foreach ( $fams as $k => $u ) : ?><li style="--fa:<?php echo esc_attr( $cat[ $k ]['color'] ); ?>"><a href="<?php echo esc_url( $u ); ?>"><?php echo zad_icon( $cat[ $k ]['icon'], 14 ); // phpcs:ignore ?><?php echo esc_html( $cat[ $k ]['name'] ); ?></a></li><?php endforeach; ?></ul>
				<?php if ( $cu ) : ?><a class="zcity__all" href="<?php echo esc_url( $cu ); ?>">كل خدماتنا في <?php echo esc_html( $c ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a><?php elseif ( in_array( $c, $chips, true ) ) : ?><a class="zcity__all" href="#sections" data-zdir-city-go="<?php echo esc_attr( $c ); ?>">كل خدمات <?php echo esc_html( $c ); ?> <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore ?></a><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</div></section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/cta-band' ); ?>
</main>
<script>
(function(){var b=document.querySelector('[data-zdir-bar]');if(!b)return;var q=b.querySelector('[data-zdir-q]'),city='',fams=[].slice.call(document.querySelectorAll('[data-zdir-fam]')),none=document.querySelector('[data-zdir-none]');
function norm(s){return(s||'').replace(/[ً-ْـ]/g,'').replace(/[أإآ]/g,'ا').replace(/ة/g,'ه').replace(/ى/g,'ي').toLowerCase().trim();}
function run(){var t=norm(q.value),any=false,on=!!(t||city);fams.forEach(function(f){var n=0;f.querySelectorAll('[data-zdir-t]').forEach(function(li){var ok=(!city||li.getAttribute('data-zdir-c')===city)&&(!t||norm(li.getAttribute('data-zdir-t')).indexOf(t)>-1);li.hidden=on?!ok:li.classList.contains('is-more')&&!f.classList.contains('is-open');if(ok)n++;});var m=f.querySelector('[data-zdir-more]');if(m)m.hidden=on;f.hidden=on&&!n;var tl=document.querySelector('[data-zdir-tile="'+f.getAttribute('data-zdir-fam')+'"]');if(tl)tl.classList.toggle('is-off',on&&!n);if(!f.hidden)any=true;});if(none)none.hidden=any;}
function setCity(c){city=c;b.querySelectorAll('[data-zdir-city]').forEach(function(y){var o=y.getAttribute('data-zdir-city')===c;y.classList.toggle('is-on',o);y.setAttribute('aria-pressed',o?'true':'false');});run();}
q.addEventListener('input',run);if(q.value)run();window.addEventListener('pageshow',function(){if(q.value)run();});
b.querySelectorAll('[data-zdir-city]').forEach(function(x){x.addEventListener('click',function(){setCity(x.getAttribute('data-zdir-city'));});});
document.querySelectorAll('[data-zdir-city-go]').forEach(function(a){a.addEventListener('click',function(){setCity(a.getAttribute('data-zdir-city-go'));});});
document.querySelectorAll('[data-zdir-more]').forEach(function(m){m.addEventListener('click',function(){var f=m.closest('[data-zdir-fam]');f.classList.add('is-open');m.remove();run();});});})();
</script>
<?php if ( $els && function_exists( 'zad_print_schema' ) ) { zad_print_schema( array( '@context' => 'https://schema.org', '@type' => 'ItemList', '@id' => $url . '#sections', 'name' => $title, 'numberOfItems' => count( $els ), 'itemListElement' => $els ) ); } ?>
