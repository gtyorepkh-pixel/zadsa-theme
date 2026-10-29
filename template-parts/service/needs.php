<?php defined( 'ABSPATH' ) || exit;
/** New layout: «هل تحتاج الخدمة؟» — the heading, the self-check collapsed behind one button, and the signs. Same data as the old wizard and signs sections. */
$zad_ix   = function_exists( 'zad_ix_get' ) ? zad_ix_get( $id ) : array();
$zad_wiz  = function_exists( 'zad_ix_wiz_active' ) && zad_ix_wiz_active( $id ) ? zad_ix_inner( 'wiz', $id ) : '';
if ( $zad_wiz || $signs ) :
	$zad_wd = (array) ( $zad_ix['wiz'] ?? array() );
	$zad_ss = zad_sec( $id, 'signs' );
	$zad_h2 = $zad_wiz && ! empty( $zad_wd['title'] ) ? $zad_wd['title'] : $zad_ss['title'];
	$zad_ey = $zad_ss['eyebrow'];
	$zad_nq = $zad_wiz ? count( zad_ix_questions( $zad_wd ) ) : 0;
	if ( $zad_wiz && ! zad_ix_questions( $zad_wd ) ) { $zad_wiz = ''; }
	if ( $zad_wiz ) { zad_ix_done( 'wiz' ); }
	?>
	<!-- Needs (new layout): heading → collapsed self-check → signs -->
	<section class="sec" id="needs"><div class="wrap">
		<header class="sec__head"><?php if ( '' !== $zad_ey ) : ?><span class="eyebrow"><?php echo esc_html( $zad_ey ); ?></span><?php endif; ?><h2><?php echo esc_html( $zad_h2 ); ?></h2><?php if ( ! empty( $zad_wd['lead'] ) ) : ?><p><?php echo esc_html( $zad_wd['lead'] ); ?></p><?php endif; ?></header>
		<?php if ( $zad_wiz ) : ?>
		<details class="need__details"><summary class="btn btn--accent">ابدأ الفحص: <?php echo (int) $zad_nq; ?> أسئلة سريعة تعرف بعدها الخطة المناسبة</summary><div class="need__grp ix ix--wiz" id="ix-wiz"><?php echo $zad_wiz; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></details>
		<?php endif; ?>
		<?php if ( $signs ) : ?>
		<div class="need__grp"><header class="sec__head"><h3><?php echo esc_html( $zad_ss['title'] ); ?></h3></header>
			<div class="cardgrid cardgrid--2">
				<?php foreach ( $signs as $w ) : ?><div class="icard icard--row"><span class="icard__ic"><?php echo zad_icon( 'search', 24 ); // phpcs:ignore ?></span><div><h3><?php echo esc_html( $w['t'] ); ?></h3><p><?php echo esc_html( $w['d'] ); ?></p></div></div><?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div></section>
<?php endif; ?>
