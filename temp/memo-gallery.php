<?php defined( 'ABSPATH' ) || exit; /* Template Name: Gallery */
get_header();
$items = zad_work_items();
$cats  = array();
$cities = array();
foreach ( $items as $it ) {
	$cats[ $it['cat_slug'] ] = $it['cat'];
	if ( $it['city'] ) { $cities[ $it['city'] ] = 1; }
}
$sub = has_excerpt() ? get_the_excerpt() : ( zad_opt( 'memopt_gallery_sec1_h' ) ?: 'صور حقيقية من تنفيذ فريقنا، قبل الخدمة وبعدها.' );
get_template_part( 'template-parts/page-hero', null, array(
	'sub'    => wp_kses_post( wpautop( wp_strip_all_tags( $sub ) ) ),
	'crumbs' => zad_current_crumbs(),
) );
?>
<main id="main" class="work">
	<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
	<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div></div></section>
	<?php endif; ?>

	<?php if ( $items ) : ?>
	<section class="sec"><div class="wrap">
		<ul class="work__stats">
			<li><b><?php echo (int) count( $items ); ?>+</b><span>عمل موثّق</span></li>
			<li><b><?php echo (int) count( $cats ); ?></b><span>نوع خدمة</span></li>
			<?php if ( $cities ) : ?><li><b><?php echo (int) count( $cities ); ?></b><span>مدينة</span></li><?php endif; ?>
			<li><b>مكتوب</b><span>ضمان على التنفيذ</span></li>
		</ul>

		<?php if ( count( $cats ) > 1 ) : ?>
		<nav class="chips chips--filter work__filter" aria-label="تصفية الأعمال" data-work-filter>
			<button type="button" class="is-on" data-f="*">الكل</button>
			<?php foreach ( $cats as $slug => $name ) : ?><button type="button" data-f="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></button><?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<div class="work__grid" data-work-grid>
			<?php foreach ( $items as $n => $it ) : ?>
				<?php if ( 'ba' === $it['type'] ) : $b = $it['ids'][0]; $a = $it['ids'][1]; ?>
				<figure class="ba work__it work__it--ba" data-ba data-cat="<?php echo esc_attr( $it['cat_slug'] ); ?>"<?php echo $n > 11 ? ' hidden data-more' : ''; ?>>
					<div class="ba__stage">
						<?php echo wp_get_attachment_image( $a, 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after' ) ); // phpcs:ignore ?>
						<div class="ba__before"><?php echo wp_get_attachment_image( $b, 'large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore ?></div>
						<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
						<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
					</div>
					<figcaption><?php echo esc_html( $it['title'] ); ?><?php if ( $it['city'] ) : ?> <small>· <?php echo esc_html( $it['city'] ); ?></small><?php endif; ?><?php if ( $it['link'] ) : ?> <a class="work__go" href="<?php echo esc_url( $it['link'] ); ?>">عن الخدمة ←</a><?php endif; ?></figcaption>
				</figure>
				<?php else : $full = wp_get_attachment_image_url( $it['ids'][0], 'full' ); ?>
				<figure class="work__it" data-cat="<?php echo esc_attr( $it['cat_slug'] ); ?>"<?php echo $n > 11 ? ' hidden data-more' : ''; ?>>
					<a href="<?php echo esc_url( $full ); ?>" data-lightbox aria-label="<?php echo esc_attr( $it['title'] ?: 'تكبير الصورة' ); ?>"><?php echo wp_get_attachment_image( $it['ids'][0], 'large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore ?></a>
					<figcaption><?php echo esc_html( $it['title'] ); ?><?php if ( $it['city'] ) : ?> <small>· <?php echo esc_html( $it['city'] ); ?></small><?php endif; ?></figcaption>
				</figure>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $items ) > 12 ) : ?><p class="work__more"><button type="button" class="btn btn--ghost" data-work-more>عرض المزيد من الأعمال</button></p><?php endif; ?>
	</div></section>
	<?php else : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<div class="emptybox">
			<?php echo zad_icon( 'sparkle', 40 ); // phpcs:ignore ?>
			<h2>نجهّز معرض أعمالنا</h2>
			<p>نضيف صور الأعمال قريباً. في هذه الأثناء اطلب معاينة مجانية وسنريك أمثلة من أعمالنا السابقة.</p>
			<p><button type="button" class="btn btn--accent" data-open-wizard>اطلب معاينة مجانية</button></p>
			<?php if ( current_user_can( 'edit_pages' ) ) : ?><p class="emptybox__tip">للمحرّر فقط: أضف «صور قبل/بعد» و«معرض الصور» في أي خدمة، أو أضف صوراً من مربع «أعمال إضافية» أسفل هذه الصفحة (يظهر بعد اختيار القالب وحفظ الصفحة).</p><?php endif; ?>
		</div>
	</div></section>
	<?php endif; ?>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
