<?php defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id         = get_the_ID();
	$g          = function ( $k ) use ( $id ) { return trim( (string) get_post_meta( $id, '_zad_wk_' . $k, true ) ); };
	$vid        = zad_wk_video( $id );
	$alt_text   = zad_wk_alt( $id );
	$clips      = zad_wk_clips( $id );
	$facts      = zad_wk_pairs( $g( 'facts' ) );
	$tools      = zad_wk_pairs( $g( 'tools' ) );
	$faq        = zad_wk_faq( $id );
	$ba         = zad_wk_ids( $id, '_zad_wk_ba' );
	$gal        = zad_wk_ids( $id, '_zad_wk_gallery' );
	$svc        = zad_wk_service( $id );
	$done       = $g( 'date' ) ? date_i18n( get_option( 'date_format' ), strtotime( $g( 'date' ) ) ) : '';
	$place      = zad_wk_place( $id );
	$stage_lbl  = zad_wk_stage_label( $id );
	$free_story = $g( 'free_story' );
	$complaint  = $g( 'complaint' );
	$inspection = $g( 'inspection' );
	$result     = $g( 'result' );
	$why_hap    = $g( 'why_happens' );
	$tech_note  = $g( 'tech_note' );
	$paired     = zad_wk_paired_post( $id );
	$main_img   = zad_wk_image_id( $id );

	// سحب خطوات الخدمة المرتبطة تلقائياً كما في ركن نجد
	$svc_steps = array();
	if ( $svc ) {
		$raw_steps = (array) get_post_meta( $svc, '_zad_steps', true );
		foreach ( $raw_steps as $st ) {
			if ( is_array( $st ) && ! empty( $st['t'] ) ) {
				$svc_steps[] = $st;
			}
		}
	}

	if ( $stage_lbl ) { array_unshift( $facts, array( 'المرحلة', $stage_lbl ) ); }
	if ( $done ) { array_unshift( $facts, array( 'تاريخ التنفيذ', $done ) ); }
	if ( $place ) { array_unshift( $facts, array( 'الموقع', $place ) ); }
	if ( $svc ) { array_unshift( $facts, array( 'الخدمة', zad_svc_label( $svc ) ) ); }

	$wa_msg  = 'مرحباً، شاهدت عملكم (' . get_the_title() . ') وأرغب بطلب نفس الخدمة' . ( $place ? ' في ' . $place : '' );
	$wa_link = zad_wa_link( $wa_msg, $svc ?: $id );
	$phone   = zad_phone( $svc ?: $id );
	?>
<main id="main" class="zw">
	<!-- رأس صفحة العمل الميداني -->
	<section class="shero zw-hero">
		<div class="wrap">
			<?php zad_render_crumbs( zad_current_crumbs() ); ?>
			<div class="zw-hero__head">
				<div class="zw-hero__badges">
					<?php if ( $svc ) : ?><a class="pill" href="<?php echo esc_url( get_permalink( $svc ) ); ?>"><?php echo zad_icon( 'check', 16 ); // phpcs:ignore ?> <?php echo esc_html( zad_svc_label( $svc ) ); ?></a><?php endif; ?>
					<?php if ( $stage_lbl ) : ?><span class="pill pill--stage"><?php echo esc_html( $stage_lbl ); ?></span><?php endif; ?>
					<?php if ( $place ) : ?><span class="pill pill--place"><?php echo zad_icon( 'pin', 15 ); // phpcs:ignore ?> <?php echo esc_html( $place ); ?></span><?php endif; ?>
					<?php if ( $done ) : ?><span class="pill pill--date"><?php echo zad_icon( 'calendar', 15 ); // phpcs:ignore ?> <?php echo esc_html( $done ); ?></span><?php endif; ?>
				</div>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
			</div>
		</div>
	</section>

	<!-- 1. الوسيط الرئيسي: أول فيديو أو صورة في أعلى الصفحة -->
	<section class="sec zw-media-top">
		<div class="wrap wrap--narrow">
			<?php if ( $vid['kind'] ) : ?>
				<figure class="zw-player-wrap">
					<div class="zw-player" data-zw-player data-kind="<?php echo esc_attr( $vid['kind'] ); ?>"<?php echo $vid['yt'] ? ' data-yt="' . esc_attr( $vid['yt'] ) . '"' : ''; ?>>
						<?php if ( 'file' === $vid['kind'] ) : ?>
							<video controls preload="metadata" playsinline<?php echo $vid['poster'] ? ' poster="' . esc_url( $vid['poster'] ) . '"' : ''; ?> src="<?php echo esc_url( $vid['src'] ); ?>" aria-label="<?php echo esc_attr( $alt_text ); ?>"></video>
						<?php else : ?>
							<iframe loading="lazy" src="<?php echo esc_url( $vid['embed'] ); ?>" title="<?php echo esc_attr( get_the_title() ); ?>" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>
						<?php endif; ?>
					</div>
					<?php if ( $alt_text ) : ?><figcaption class="zw-caption"><?php echo esc_html( $alt_text ); ?></figcaption><?php endif; ?>
				</figure>
				<?php if ( $clips ) : ?>
				<nav class="zw-clips" aria-label="مقاطع الفيديو">
					<?php foreach ( $clips as $c ) : ?>
					<button type="button" class="zw-clip" data-start="<?php echo (int) $c[0]; ?>" data-end="<?php echo (int) $c[1]; ?>"><span><?php echo esc_html( $c[2] ); ?></span><small dir="ltr"><?php echo esc_html( zad_wk_clock( $c[0] ) ); ?></small></button>
					<?php endforeach; ?>
				</nav>
				<?php endif; ?>
			<?php elseif ( $main_img ) : ?>
				<figure class="ctximg zw-main-photo">
					<?php echo wp_get_attachment_image( $main_img, 'large', false, array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => $alt_text ) ); ?>
					<?php if ( $alt_text ) : ?><figcaption><?php echo esc_html( $alt_text ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endif; ?>

			<!-- ملاحظة الفني الميداني إن وُجدت -->
			<?php if ( '' !== $tech_note ) : ?>
				<blockquote class="zw-technote">
					<div class="zw-technote__ic"><?php echo zad_icon( 'tool', 22 ); // phpcs:ignore ?></div>
					<div>
						<p><?php echo esc_html( $tech_note ); ?></p>
						<cite>— ملاحظة فني التنفيذ في الموقع</cite>
					</div>
				</blockquote>
			<?php endif; ?>
		</div>
	</section>

	<!-- 2. المقارنة التلقائية مع العمل الزوجي (قبل / بعد) إن وُجد معرّف زوج مطابق -->
	<?php if ( $paired ) :
		$p_img   = zad_wk_image_id( $paired->ID );
		$p_stage = zad_wk_stage_label( $paired->ID ) ?: 'المرحلة المقابلة';
		?>
		<section class="sec sec--cream zw-pair-sec">
			<div class="wrap wrap--narrow">
				<header class="sec__head">
					<span class="eyebrow">نفس الموقع (قبل وبعد)</span>
					<h2>مقارنة الحالة في نفس الزيارة</h2>
				</header>
				<div class="zw-pair-grid">
					<div class="zw-pair-card is-current">
						<span class="zw-pair-tag"><?php echo esc_html( $stage_lbl ?: 'هذه المرحلة' ); ?></span>
						<?php if ( $main_img ) { echo wp_get_attachment_image( $main_img, 'medium_large', false, array( 'alt' => $alt_text ) ); } ?>
						<h3><?php echo esc_html( get_the_title() ); ?></h3>
					</div>
					<a class="zw-pair-card" href="<?php echo esc_url( get_permalink( $paired->ID ) ); ?>">
						<span class="zw-pair-tag zw-pair-tag--other"><?php echo esc_html( $p_stage ); ?></span>
						<?php if ( $p_img ) { echo wp_get_attachment_image( $p_img, 'medium_large', false, array( 'alt' => zad_wk_alt( $paired->ID ) ) ); } ?>
						<h3><?php echo esc_html( get_the_title( $paired->ID ) ); ?></h3>
						<span class="more">شاهد المرحلة المقابلة <?php echo zad_icon( 'arrow', 15 ); // phpcs:ignore ?></span>
					</a>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- 3. تفاصيل ما حدث في العمل: إما النص الحر، أو الأقسام الثلاثة المهيكلة -->
	<section class="sec zw-details">
		<div class="wrap wrap--narrow">
			<?php if ( '' !== $free_story ) : ?>
				<div class="zw-story-card">
					<h2>ماذا حدث في هذا العمل؟</h2>
					<div class="prose"><?php echo wp_kses_post( wpautop( esc_html( $free_story ) ) ); ?></div>
				</div>
			<?php elseif ( '' !== $complaint || '' !== $inspection || '' !== $result ) : ?>
				<div class="zw-3blocks">
					<?php if ( '' !== $complaint ) : ?>
						<article class="zw-bcard zw-bcard--before">
							<span class="zw-bcard__k">1. الحالة قبل العمل</span>
							<h2>كيف كان المكان قبل أن نبدأ</h2>
							<p><?php echo nl2br( esc_html( $complaint ) ); ?></p>
						</article>
					<?php endif; ?>
					<?php if ( '' !== $inspection ) : ?>
						<article class="zw-bcard zw-bcard--done">
							<span class="zw-bcard__k">2. ما فعلناه</span>
							<h2>طريقة المعالجة والتنفيذ في الزيارة</h2>
							<p><?php echo nl2br( esc_html( $inspection ) ); ?></p>
						</article>
					<?php endif; ?>
					<?php if ( '' !== $result ) : ?>
						<article class="zw-bcard zw-bcard--after">
							<span class="zw-bcard__k">3. النتيجة</span>
							<h2>الحالة بعد انتهاء العمل</h2>
							<p><?php echo nl2br( esc_html( $result ) ); ?></p>
						</article>
					<?php endif; ?>
				</div>
			<?php elseif ( get_post_meta( $id, '_zad_wm_from', true ) && '' !== trim( (string) get_post_field( 'post_content', $id ) ) ) : ?>
                <div class="zw-story-card"><div class="prose entry-content"><?php
                    $legacy_story = apply_filters( 'the_content', get_the_content() );
                    // The old placeholder has no handler; the native booking controls are below.
                    if ( ! shortcode_exists( 'zad_cta' ) ) { $legacy_story = preg_replace( '/\[zad_cta\s*\]/', '', $legacy_story ); }
                    echo $legacy_story; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered post content.
                ?></div></div>
			<?php endif; ?>

			<!-- 4. لماذا تحدث هذه الحالة؟ (القسم التثقيفي للـ SEO) -->
			<?php if ( '' !== $why_hap ) : ?>
				<div class="zw-why-box">
					<h2><?php echo zad_icon( 'search', 20 ); // phpcs:ignore ?> لماذا تحدث هذه الحالة وكيف تمنع تكرارها؟</h2>
					<p><?php echo nl2br( esc_html( $why_hap ) ); ?></p>
				</div>
			<?php endif; ?>

			<!-- 5. خطوات الخدمة المربوطة تلقائياً (كما في ركن نجد: خطوات الخدمة تُضاف تلقائياً) -->
			<?php if ( $svc_steps ) : ?>
				<div class="zw-svc-steps">
					<h2>خطواتنا المعتمدة في <?php echo esc_html( zad_svc_label( $svc ) ); ?></h2>
					<ol class="hsteps">
						<?php foreach ( $svc_steps as $idx => $st ) : ?>
							<li>
								<span class="hsteps__n"><?php echo (int) ( $idx + 1 ); ?></span>
								<h3><?php echo esc_html( $st['t'] ); ?></h3>
								<?php if ( ! empty( $st['d'] ) ) : ?><p><?php echo esc_html( $st['d'] ); ?></p><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- 6. معرض صور وفيديوهات الزيارة المرتب (قبل ← أثناء ← بعد) -->
	<?php if ( count( $gal ) > 1 || ( count( $gal ) === 1 && $gal[0] !== $main_img ) ) : ?>
	<section class="sec sec--tint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">توثيق الزيارة</span><h2>صور ولقطات العمل (قبل ← أثناء ← بعد)</h2></header>
		<div class="gal zw-visit-gal">
			<?php foreach ( $gal as $gid ) :
				$gmime = (string) get_post_mime_type( $gid );
				$gurl  = wp_get_attachment_url( $gid );
				if ( 0 === strpos( $gmime, 'video/' ) ) : ?>
					<div class="zw-gal-vid">
						<video controls preload="metadata" playsinline src="<?php echo esc_url( $gurl ); ?>"></video>
					</div>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_get_attachment_image_url( $gid, 'full' ) ); ?>" target="_blank" rel="noopener">
						<?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $gid, $id ) ) ); ?>
					</a>
				<?php endif;
			endforeach; ?>
		</div>
	</div></section>
	<?php endif; ?>

	<!-- 7. بطاقة بيانات العمل والأدوات -->
	<?php if ( $facts || $tools ) : ?>
	<section class="sec"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">بطاقة العمل</span><h2>تفاصيل الزيارة في سطور</h2></header>
		<?php if ( $facts ) : ?>
		<div class="tbl"><table><tbody>
			<?php foreach ( $facts as $f ) : ?><tr><th scope="row"><?php echo esc_html( $f[0] ); ?></th><td><?php echo esc_html( $f[1] ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php endif; ?>
		<?php if ( $tools ) : ?>
		<ul class="zw-tools" style="margin-top:16px"><?php foreach ( $tools as $t ) : ?><li><b><?php echo esc_html( $t[0] ); ?></b><?php if ( '' !== $t[1] ) : ?><span> — <?php echo esc_html( $t[1] ); ?></span><?php endif; ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
	</div></section>
	<?php endif; ?>

	<!-- 8. بطاقة الخدمة المنفّذة + زر طلب نفس العمل -->
	<?php if ( $svc ) : $sp = get_post( $svc ); ?>
	<section class="sec sec--mint"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">الخدمة المرتبطة</span><h2>هل لديك حالة مشابهة؟ اطلب خدمة <?php echo esc_html( zad_svc_label( $svc ) ); ?></h2></header>
		<div class="sgrid zw-svc"><?php $GLOBALS['post'] = $sp; setup_postdata( $sp ); get_template_part( 'template-parts/service-card' ); wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<!-- 9. أعمال مشابهة -->
	<?php $sim = zad_works_similar( $id, 3 ); if ( $sim ) : ?>
	<section class="sec"><div class="wrap">
		<header class="sec__head"><span class="eyebrow">من الميدان</span><h2>أعمال أخرى من نفس القسم</h2></header>
		<div class="zw-grid4"><?php foreach ( $sim as $w ) { $GLOBALS['post'] = $w; setup_postdata( $w ); get_template_part( 'template-parts/work-card' ); } wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>
</main>
<?php endwhile; get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
