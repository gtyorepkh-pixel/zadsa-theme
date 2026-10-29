<?php
/* Template Name: الأسعار الشاملة */
/**
 * One prices page for the whole site, generated from the service pages themselves.
 *
 * Grouping: by SECTION (zad_catalog() families — تنظيف، خزانات، مكيفات…), not by page. The same service in
 * ten cities is one block here, so the page stays readable as the city pages grow.
 *
 * Prices: every row comes from its service page (`zad_price_rows()` — `_zad_packages`, else the legacy
 * `_zad_prices`). Rows with the same name across cities are merged and the LOWEST real price is kept, so the
 * page reads as «يبدأ من». Nothing is typed twice: the number in the index at the top IS the smallest number
 * in that section's own rows, and the two can never disagree.
 *
 * A row with no number («بعد المعاينة») keeps its wording and is never shown as 0 — and a priced variant of
 * the same row always wins over a quote-only one.
 *
 * @package ZadPro
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/page-hero', null, array(
	'title'  => get_the_title(),
	'sub'    => '<p><span class="eyebrow-inv">شفافية كاملة</span></p><p>أسعار حقيقية لأهم خدماتنا في مكان واحد. الأسعار استرشادية، والسعر النهائي يُحدَّد بعد معاينة مجانية لحالتك.</p>',
	'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ),
) );

/** Display name of a row: the city dropped, so «خزان علوي صغير بالرياض» and «… بجدة» become one row. */
function zpr_row_key( $name ) {
	$n = function_exists( 'zad_city_norm_name' ) ? zad_city_norm_name( $name ) : $name;
	$n = preg_replace( '/\s*(?:في|ب)\s*(?:' . implode( '|', array_map( 'preg_quote', array_values( zad_city_map() ) ) ) . ')\s*$/u', '', (string) $n );
	return trim( preg_replace( '/\s+/u', ' ', (string) $n ) );
}

/**
 * Merge key for a row or a group label: the same text however its letters were typed.
 * The city pages were written by different hands, so «خزان ارضي» and «خزان أرضي» are one row, not two.
 */
function zpr_norm_key( $text ) {
	$t = (string) $text;
	$t = preg_replace( '/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $t );       // tashkeel + tatweel
	$t = strtr( $t, array( 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ئ' => 'ي', 'ة' => 'ه', 'ؤ' => 'و' ) );
	return trim( preg_replace( '/\s+/u', ' ', $t ) );
}

/* ---------- data: the catalog's sections, each with its merged price rows ---------- */
$zpr_fams  = function_exists( 'zad_catalog_families' ) ? zad_catalog_families() : array();
$zpr_secs  = array();
foreach ( (array) zad_catalog() as $zpr_fk => $zpr_f ) {
	$zpr_rows = array();
	foreach ( (array) ( $zpr_f['items'] ?? array() ) as $zpr_it ) {
		$zpr_unit = get_post_meta( $zpr_it['id'], '_zad_price_unit', true ) ?: 'ريال';
		// the packages carry their own sub-group (9th column) and unit; zad_price_rows() drops both, so read them
		// straight when the page has packages and keep zad_price_rows() for the pages still on the legacy table.
		$zpr_pks = zad_parse_packages( get_post_meta( $zpr_it['id'], '_zad_packages', true ) );
		$zpr_src = array();
		foreach ( $zpr_pks as $zpr_pk ) {
			$zpr_src[] = array(
				'name'    => $zpr_pk['name'],
				'details' => '' !== $zpr_pk['desc'] ? $zpr_pk['desc'] : implode( '؛ ', $zpr_pk['feat'] ),
				'group'   => $zpr_pk['cat'],
				'price'   => $zpr_pk['price'],
				'unit'    => '' !== $zpr_pk['unit'] ? $zpr_pk['unit'] : $zpr_unit,
			);
		}
		if ( ! $zpr_src ) {
			foreach ( (array) zad_price_rows( $zpr_it['id'] ) as $zpr_r ) {
				$zpr_src[] = array(
					'name'    => $zpr_r['name'],
					'details' => (string) ( $zpr_r['details'] ?? '' ),
					'group'   => (string) ( $zpr_r['group'] ?? '' ),
					'price'   => (string) $zpr_r['price'],
					'unit'    => $zpr_unit,
				);
			}
		}
		foreach ( $zpr_src as $zpr_r ) {
			$zpr_pp  = zad_price_parse( $zpr_r['price'] );
			$zpr_name = zpr_row_key( $zpr_r['name'] );
			$zpr_key  = zpr_norm_key( $zpr_name );
			if ( '' === $zpr_key ) { continue; }
			$zpr_new = array(
				'name'    => $zpr_name,
				'details' => $zpr_r['details'],
				'group'   => $zpr_r['group'],
				'price'   => (string) $zpr_r['price'],
				'unit'    => $zpr_r['unit'],
				'pp'      => $zpr_pp,
			);
			if ( ! isset( $zpr_rows[ $zpr_key ] ) ) { $zpr_rows[ $zpr_key ] = $zpr_new; continue; }
			$zpr_old = $zpr_rows[ $zpr_key ];
			$zpr_win = ( 'quote' === $zpr_old['pp']['kind'] && 'quote' !== $zpr_pp['kind'] )      // a real price beats «بعد المعاينة»
				|| ( 'quote' !== $zpr_pp['kind'] && $zpr_pp['min'] < $zpr_old['pp']['min'] );      // otherwise the cheapest wins
			if ( $zpr_win ) {
				if ( '' === $zpr_new['details'] ) { $zpr_new['details'] = $zpr_old['details']; }
				if ( '' === $zpr_new['group'] ) { $zpr_new['group'] = $zpr_old['group']; }
				$zpr_rows[ $zpr_key ] = $zpr_new;
			} elseif ( '' === $zpr_old['details'] && '' !== $zpr_new['details'] ) {
				$zpr_rows[ $zpr_key ]['details'] = $zpr_new['details'];
			}
		}
	}
	if ( ! $zpr_rows ) { continue; }
	// cheapest first inside each group, quote rows last
	uasort( $zpr_rows, function ( $a, $b ) {
		$ag = zpr_norm_key( $a['group'] ); $bg = zpr_norm_key( $b['group'] );
		if ( $ag !== $bg ) { return strcmp( $ag, $bg ); }
		$aq = 'quote' === $a['pp']['kind']; $bq = 'quote' === $b['pp']['kind'];
		if ( $aq !== $bq ) { return $aq ? 1 : -1; }
		return $aq ? 0 : ( $a['pp']['min'] <=> $b['pp']['min'] );
	} );
	$zpr_mins = array();
	foreach ( $zpr_rows as $zpr_r ) { if ( 'quote' !== $zpr_r['pp']['kind'] ) { $zpr_mins[] = $zpr_r['pp']['min']; } }
	$zpr_secs[ $zpr_fk ] = array(
		'name' => $zpr_f['name'] ?? ( $zpr_fams[ $zpr_fk ]['name'] ?? $zpr_fk ),
		'url'  => $zpr_f['url'] ?? '',
		'desc' => (string) ( $zpr_fams[ $zpr_fk ]['desc'] ?? '' ),
		'icon' => (string) ( $zpr_fams[ $zpr_fk ]['icon'] ?? 'star' ),
		'tone' => (string) ( $zpr_fams[ $zpr_fk ]['color'] ?? '' ),
		'rows' => array_values( $zpr_rows ),
		'min'  => $zpr_mins ? min( $zpr_mins ) : 0,
		'unit' => reset( $zpr_rows )['unit'],
	);
}
uasort( $zpr_secs, function ( $a, $b ) { return count( $b['rows'] ) <=> count( $a['rows'] ); } );
?>
<main id="main" class="sec zpr">
	<div class="wrap">

	<?php if ( ! $zpr_secs ) : ?>
		<p class="zpr__empty">أضف «باقات الأسعار» داخل شاشة تحرير أي خدمة لتظهر هنا.</p>
	<?php else : ?>

		<?php /* 1) index: one tile per section, carrying that section's own starting price */ ?>
		<ul class="zpr__idx">
			<?php foreach ( $zpr_secs as $zpr_k => $zpr_s ) : ?>
				<li>
					<a class="zpr__tile" href="#zpr-<?php echo esc_attr( $zpr_k ); ?>"<?php echo $zpr_s['tone'] ? ' style="--zpr-tone:' . esc_attr( $zpr_s['tone'] ) . '"' : ''; ?>>
						<span class="zpr__ic"><?php echo zad_icon( $zpr_s['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<b><?php echo esc_html( $zpr_s['name'] ); ?></b>
						<?php if ( $zpr_s['min'] ) : ?>
							<span class="zpr__from"><small>يبدأ من</small><i><?php echo esc_html( number_format_i18n( $zpr_s['min'] ) ); ?></i><span><?php echo esc_html( $zpr_s['unit'] ); ?></span></span>
						<?php else : ?>
							<span class="zpr__from zpr__from--q"><i>بعد المعاينة</i></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="zpr__fine"><?php echo zad_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span>كل رقم هنا مأخوذ من صفحة الخدمة نفسها، والسعر في الأعلى هو أقل سعر فعلي داخل القسم — فما فيش رقمين مختلفين لنفس الخدمة في مكانين. السعر النهائي يتحدد بالمساحة والحالة وقت المعاينة.</span></p>

		<?php /* 2) one block per section */ ?>
		<?php foreach ( $zpr_secs as $zpr_k => $zpr_s ) : ?>
			<section class="zpr__svc" id="zpr-<?php echo esc_attr( $zpr_k ); ?>"<?php echo $zpr_s['tone'] ? ' style="--zpr-tone:' . esc_attr( $zpr_s['tone'] ) . '"' : ''; ?>>
				<header class="zpr__head">
					<span class="zpr__ic zpr__ic--lg"><?php echo zad_icon( $zpr_s['icon'], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div class="zpr__ttl">
						<h2><?php if ( $zpr_s['url'] ) : ?><a href="<?php echo esc_url( $zpr_s['url'] ); ?>">أسعار <?php echo esc_html( $zpr_s['name'] ); ?></a><?php else : ?>أسعار <?php echo esc_html( $zpr_s['name'] ); ?><?php endif; ?></h2>
						<?php if ( '' !== $zpr_s['desc'] ) : ?><p><?php echo esc_html( $zpr_s['desc'] ); ?></p><?php endif; ?>
					</div>
					<a class="btn btn--wa btn--sm" href="<?php echo esc_url( zad_wa_link( 'مرحباً، أرغب بالاستفسار عن أسعار ' . $zpr_s['name'] ) ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> اسأل عن سعرك</a>
				</header>
				<ul class="zpr__rows">
					<?php
					// a group worth a heading has more than one row under it; a one-off group rides as a chip on its row
					$zpr_gn = array();
					foreach ( $zpr_s['rows'] as $zpr_r ) {
						$zpr_g = zpr_norm_key( $zpr_r['group'] );
						if ( '' !== $zpr_g ) { $zpr_gn[ $zpr_g ] = ( $zpr_gn[ $zpr_g ] ?? 0 ) + 1; }
					}
					$zpr_last = null;
					foreach ( $zpr_s['rows'] as $zpr_r ) :
						$zpr_g   = zpr_norm_key( $zpr_r['group'] );
						$zpr_big = '' !== $zpr_g && ( $zpr_gn[ $zpr_g ] ?? 0 ) > 1;
						if ( $zpr_big && $zpr_g !== $zpr_last ) {
							$zpr_last = $zpr_g;
							echo '<li class="zpr__grp">' . esc_html( $zpr_r['group'] ) . '</li>';
						}
						?>
						<li class="zpr__row">
							<span class="zpr__nm">
								<b><?php echo esc_html( $zpr_r['name'] ); ?><?php if ( '' !== $zpr_g && ! $zpr_big ) : ?> <em class="zpr__tag"><?php echo esc_html( $zpr_r['group'] ); ?></em><?php endif; ?></b>
								<?php if ( '' !== $zpr_r['details'] ) : ?><small><?php echo esc_html( $zpr_r['details'] ); ?></small><?php endif; ?>
							</span>
							<?php if ( 'quote' === $zpr_r['pp']['kind'] ) : ?>
								<span class="zpr__val--q"><?php echo esc_html( '' !== trim( $zpr_r['price'] ) ? $zpr_r['price'] : 'بعد المعاينة' ); ?></span>
							<?php else : ?>
								<span class="zpr__val">
									<i><?php echo esc_html( 'range' === $zpr_r['pp']['kind'] ? $zpr_r['pp']['raw_a'] . ' – ' . $zpr_r['pp']['raw_b'] : $zpr_r['pp']['raw_a'] ); ?></i>
									<span><?php echo esc_html( $zpr_r['unit'] ); ?></span>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $zpr_s['url'] ) : ?>
					<p class="zpr__more-wrap"><a class="zpr__more" href="<?php echo esc_url( $zpr_s['url'] ); ?>">كل خدمات <?php echo esc_html( $zpr_s['name'] ); ?> والتفاصيل الكاملة <?php echo zad_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>

	<?php endif; ?>

	<div class="prose entry-content"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div>
	</div>
</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
