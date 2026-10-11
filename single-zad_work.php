<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id     = get_the_ID();
	$url    = get_permalink();
	$g      = function ( $k ) use ( $id ) { return trim( (string) get_post_meta( $id, '_zad_wk_' . $k, true ) ); };
	$vid    = zad_wk_video( $id );
	$clips  = zad_wk_clips( $id );
	$facts  = zad_wk_pairs( $g( 'facts' ) );
	$tools  = zad_wk_pairs( $g( 'tools' ) );
	$faq    = zad_wk_faq( $id );
	$ba     = zad_wk_ids( $id, '_zad_wk_ba' );
	$gal    = zad_wk_ids( $id, '_zad_wk_gallery' );
	$svc    = zad_wk_service( $id );
	$done   = $g( 'date' ) ? date_i18n( get_option( 'date_format' ), strtotime( $g( 'date' ) ) ) : '';
	$place  = zad_wk_place( $id );
	if ( $done ) { array_unshift( $facts, array( 'تاريخ التنفيذ', $done ) ); }
	if ( $place && ! array_filter( $facts, function ( $f ) { return 0 === mb_strpos( $f[0], 'الحي' ); } ) ) { array_unshift( $facts, array( 'الموقع', $place ) ); }
	$secs   = array( 'complaint' => array( 'الشكوى', 'ما الذي واجهه العميل' ), 'inspection' => array( 'الفحص', 'كيف شخّصنا المشكلة' ), 'result' => array( 'النتيجة', 'ما تحقق بعد التنفيذ' ) );
	get_template_part( 'template-parts/page-hero', null, array( 'title' => get_the_title(), 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( 'أعمالنا', zad_works_url() ), array( get_the_title(), '' ) ), 'sub' => has_excerpt() ? '<p>' . esc_html( get_the_excerpt() ) . '</p>' : '' ) );
	?>
<main id="main" class="zw">
	<?php if ( $vid['kind'] ) : ?>
	<section class="sec zw-video" id="video"><div class="wrap wrap--narrow">
		<div class="zw-player" data-zw-player data-kind="<?php echo esc_attr( $vid['kind'] ); ?>"<?php echo $vid['yt'] ? ' data-yt="' . esc_attr( $vid['yt'] ) . '"' : ''; ?>>
			<?php if ( 'file' === $vid['kind'] ) : ?>
				<video controls preload="metadata" playsinline<?php echo $vid['poster'] ? ' poster="' . esc_url( $vid['poster'] ) . '"' : ''; ?> src="<?php echo esc_url( $vid['src'] ); ?>"></video>
			<?php else : ?>
				<div class="zw-yt"<?php echo $vid['poster'] ? ' style="--poster:url(\'' . esc_url( $vid['poster'] ) . '\')"' : ''; ?>><button type="button" class="zw-yt__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="30" height="30" aria-hidden="true"><path d="M8 5v14l11-7Z"/></svg></button></div>
			<?php endif; ?>
		</div>
		<?php if ( $clips ) : ?>
		<nav class="zw-clips" aria-label="مقاطع الفيديو">
			<?php foreach ( $clips as $c ) : ?>
			<button type="button" class="zw-clip" data-start="<?php echo (int) $c[0]; ?>" data-end="<?php echo (int) $c[1]; ?>"><span><?php echo esc_html( $c[2] ); ?></span><small dir="ltr"><?php echo esc_html( zad_wk_clock( $c[0] ) ); ?></small></button>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div></section>
	<?php endif; ?>

	<?php if ( $facts ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">ملخص</span><h2>الحالة في سطور</h2></header>
		<div class="tbl"><table><tbody>
			<?php foreach ( $facts as $f ) : ?><tr><th scope="row"><?php echo esc_html( $f[0] ); ?></th><td><?php echo esc_html( $f[1] ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
	</div></section>
	<?php endif; ?>

	<?php foreach ( $secs as $k => $lbl ) : if ( '' === $g( $k ) ) { continue; } ?>
	<section class="sec<?php echo 'inspection' === $k ? ' sec--tint' : ''; ?>" id="<?php echo esc_attr( $k ); ?>"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow"><?php echo esc_html( $lbl[1] ); ?></span><h2><?php echo esc_html( $lbl[0] ); ?></h2></header>
		<div class="prose"><?php echo wp_kses_post( wpautop( esc_html( $g( $k ) ) ) ); ?></div>
	</div></section>
	<?php endforeach; ?>

	<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) || has_blocks() ) : ?>
	<section class="sec"><div class="wrap wrap--narrow"><div class="prose entry-content"><?php the_content(); ?></div></div></section>
	<?php endif; ?>

	<?php if ( count( $ba ) >= 2 ) : ?>
	<section class="sec sec--tint"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">قبل وبعد</span><h2>النتيجة بالصور</h2></header>
		<div class="sgrid">
			<?php for ( $i = 0; $i + 1 < count( $ba ); $i += 2 ) : ?>
			<figure class="ba" data-ba>
				<div class="ba__stage">
					<?php echo wp_get_attachment_image( $ba[ $i + 1 ], 'large', false, array( 'loading' => 'lazy', 'class' => 'ba__after', 'alt' => zad_img_alt( $ba[ $i + 1 ], $id ) ) ); // phpcs:ignore ?>
					<div class="ba__before"><?php echo wp_get_attachment_image( $ba[ $i ], 'large', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $ba[ $i ], $id ) ) ); // phpcs:ignore ?></div>
					<span class="ba__tag ba__tag--b">قبل</span><span class="ba__tag ba__tag--a">بعد</span>
					<input type="range" min="0" max="100" value="50" aria-label="مقارنة قبل وبعد">
				</div>
			</figure>
			<?php endfor; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<?php if ( $gal ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">من الموقع</span><h2>صور العمل</h2></header>
		<div class="gal"><?php foreach ( $gal as $gid ) : ?><a href="<?php echo esc_url( wp_get_attachment_image_url( $gid, 'full' ) ); ?>" data-lightbox><?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $gid, $id ) ) ); // phpcs:ignore ?></a><?php endforeach; ?></div>
	</div></section>
	<?php endif; ?>

	<?php if ( $svc ) : $sp = get_post( $svc ); ?>
	<section class="sec sec--mint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">الخدمة</span><h2>الخدمة المنفّذة</h2></header>
		<div class="sgrid zw-svc"><?php $GLOBALS['post'] = $sp; setup_postdata( $sp ); get_template_part( 'template-parts/service-card' ); wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<?php if ( $tools ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">المعدات</span><h2>الأدوات المستعملة</h2></header>
		<ul class="zw-tools"><?php foreach ( $tools as $t ) : ?><li><b><?php echo esc_html( $t[0] ); ?></b><?php if ( '' !== $t[1] ) : ?><span><?php echo esc_html( $t[1] ); ?></span><?php endif; ?></li><?php endforeach; ?></ul>
	</div></section>
	<?php endif; ?>

	<?php if ( $faq ) : ?>
	<section class="sec sec--tint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">أسئلة</span><h2>أسئلة شائعة عن هذا العمل</h2></header>
		<?php zad_render_faq( array_map( function ( $f ) { return array( 'q' => $f['q'], 'a' => $f['a'] ); }, $faq ) ); ?>
	</div></section>
	<?php endif; ?>

	<?php $sim = zad_works_similar( $id, 3 ); if ( $sim ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">أعمال مشابهة</span><h2>أعمال مشابهة</h2></header>
		<div class="sgrid"><?php foreach ( $sim as $w ) { $GLOBALS['post'] = $w; setup_postdata( $w ); get_template_part( 'template-parts/work-card' ); } wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
