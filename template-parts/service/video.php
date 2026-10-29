<?php defined( 'ABSPATH' ) || exit; ?>

	<?php if ( $video ) : ?>
	<section class="sec sec--dark" id="video"><div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">شاهد الفرق بنفسك</span><h2>شاهد خدماتنا عن قرب</h2></header>
		<?php $vyt = zad_youtube_id( $video ); $vposter = $vyt ? 'https://i.ytimg.com/vi/' . $vyt . '/hqdefault.jpg' : ( $thumb ? wp_get_attachment_image_url( $thumb, 'large' ) : '' ); ?>
		<div class="vframe" data-video="<?php echo esc_url( $video ); ?>"<?php echo $vposter ? ' style="--poster:url(\'' . esc_url( $vposter ) . '\')"' : ''; ?>>
			<button type="button" class="vframe__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5v14l11-7Z"/></svg></button>
		</div>
	</div></section>
	<?php endif; ?>
