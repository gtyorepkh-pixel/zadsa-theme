<?php defined( 'ABSPATH' ) || exit;
/**
 * Enhanced Interactive Field Case Study Work Card (Zad Pro v3.30.0)
 * Features: Video duration badge, inline video modal trigger, complaint->result summary, and contextual WhatsApp CTA.
 */
$wid       = get_the_ID();
$img       = zad_wk_image_id( $wid );
$vid       = zad_wk_video( $wid );
list( $cat, , ) = zad_wk_terms( $wid );
$svc       = zad_wk_service( $wid );
$badge     = $cat ? $cat->name : ( $svc ? zad_card_title( $svc ) : 'توثيق ميداني' );
$place     = zad_wk_place( $wid );
$complaint = wp_trim_words( wp_strip_all_tags( (string) get_post_meta( $wid, '_zad_wk_complaint', true ) ), 12, '…' );
$result    = wp_trim_words( wp_strip_all_tags( (string) get_post_meta( $wid, '_zad_wk_result', true ) ), 12, '…' );
$dur_txt   = ! empty( $vid['duration'] ) ? zad_wk_clock( $vid['duration'] ) : '';
$v_src     = 'file' === $vid['kind'] ? $vid['src'] : ( 'yt' === $vid['kind'] ? $vid['embed'] : '' );
$wa_msg    = 'مرحباً، شاهدت عملكم (' . get_the_title( $wid ) . ') وأرغب بطلب نفس الخدمة' . ( $place ? ' في ' . $place : '' );
$wa_href   = zad_wa_link( $wa_msg, $svc ?: $wid );
?>
<article class="scard scard--post zw-card zw-card--pro">
	<div class="scard__media zw-card__media">
		<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
			<?php if ( $img ) { echo wp_get_attachment_image( $img, 'zad-card', false, array( 'loading' => 'lazy', 'alt' => zad_img_alt( $img, $wid ) ) ); } else { echo '<span class="scard__ph">' . zad_icon( 'sparkle', 56 ) . '</span>'; } // phpcs:ignore ?>
		</a>
		<?php if ( $vid['kind'] && $v_src ) : ?>
			<button type="button" class="zw-playbtn" data-zw-open-video="<?php echo esc_url( $v_src ); ?>" data-zw-kind="<?php echo esc_attr( $vid['kind'] ); ?>" aria-label="تشغيل فيديو العمل">
				<svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true"><path d="M8 5v14l11-7Z"/></svg>
			</button>
			<span class="zw-badge zw-badge--video">
				<svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13" aria-hidden="true"><path d="M8 5v14l11-7Z"/></svg>
				فيديو<?php echo $dur_txt ? ' ' . esc_html( $dur_txt ) : ''; ?>
			</span>
		<?php endif; ?>
		<?php if ( $place ) : ?>
			<span class="zw-badge zw-badge--place"><?php echo zad_icon( 'pin', 13 ); // phpcs:ignore ?> <?php echo esc_html( $place ); ?></span>
		<?php endif; ?>
	</div>
	<div class="scard__body zw-card__body">
		<div class="zw-card__top">
			<?php if ( $badge ) : ?><span class="scard__cat"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
		</div>
		<h3 class="scard__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
		<?php if ( $complaint || $result ) : ?>
			<div class="zw-case">
				<?php if ( $complaint ) : ?><p class="zw-case__row"><b>المشكلة:</b> <?php echo esc_html( $complaint ); ?></p><?php endif; ?>
				<?php if ( $result ) : ?><p class="zw-case__row zw-case__row--ok"><b>النتيجة:</b> <?php echo esc_html( $result ); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="zw-card__actions">
			<a class="more" href="<?php the_permalink(); ?>">تفاصيل الحالة <?php echo zad_icon( 'arrow', 15 ); // phpcs:ignore ?></a>
			<?php if ( $wa_href ) : ?>
				<a class="zw-wa-mini" href="<?php echo esc_url( $wa_href ); ?>" data-ev="lead_whatsapp" data-src="field-work-card" target="_blank" rel="noopener">
					<?php echo zad_icon( 'whatsapp', 15 ); // phpcs:ignore ?> اطلب مثلها
				</a>
			<?php endif; ?>
		</div>
	</div>
</article>
