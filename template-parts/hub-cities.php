<?php defined( 'ABSPATH' ) || exit;
/**
 * /areas/: one card per city (the cities that have a published page), each with a chip for every section it has.
 * The three main cities come first. One request button, no extra text.
 */
$d = zad_hub_data();
if ( ! $d ) { return; }
$crumbs = zad_current_crumbs() ?: array( array( 'الرئيسية', home_url( '/' ) ), array( $d['h1'], '' ) );
$wa     = zad_wa_link( 'مرحباً، أستفسر عن: ' . $d['h1'], 0 );
$first  = array( 'riyadh' => 0, 'jeddah' => 1, 'dammam' => 2 );
$rows   = array();
foreach ( $d['cards'] as $i => $c ) {
	$t = zad_city_term( $c['title'] );
	if ( ! $t ) { continue; }
	$chips = array();
	foreach ( zad_city_catalog( $t ) as $x ) {
		$u = $x['items'] ? $x['items'][0]['url'] : ( $x['hoods'] ? $x['hoods'][0][1] : '' );
		foreach ( $x['items'] as $it ) { // the city's main page of the section (…/section/city/), else the shortest link
			if ( basename( untrailingslashit( (string) wp_parse_url( $it['url'], PHP_URL_PATH ) ) ) === $t->slug ) { $u = $it['url']; break; }
			if ( strlen( $it['url'] ) < strlen( $u ) ) { $u = $it['url']; }
		}
		if ( '' !== $u ) { $chips[] = array( 'name' => $x['fam']['name'], 'icon' => $x['fam']['icon'], 'color' => $x['fam']['color'], 'url' => $u ); }
	}
	$rows[] = array( 'name' => $c['title'], 'url' => $c['url'], 'chips' => $chips, 'rank' => $first[ $t->slug ] ?? 10 + $i );
}
usort( $rows, function ( $a, $b ) { return $a['rank'] - $b['rank']; } );
?>
<main id="main" class="zhub">
	<section class="zhub__head">
		<div class="wrap">
			<?php zad_render_crumbs( $crumbs ); ?>
			<h1 class="zhub__h1"><?php echo esc_html( $d['h1'] ); ?></h1>
			<p class="zhub__lead"><?php echo esc_html( $d['lead'] ); ?></p>
		</div>
	</section>

	<section class="zhub__body">
		<div class="wrap">
			<?php if ( $rows ) : ?>
			<ul class="zcities">
				<?php foreach ( $rows as $r ) : ?>
				<li class="zcities__card">
					<a class="zcities__city" href="<?php echo esc_url( $r['url'] ); ?>"><span class="zcities__pin"><?php echo zad_icon( 'pin', 22 ); // phpcs:ignore ?></span><h2><?php echo esc_html( $r['name'] ); ?></h2><span class="zcities__go" aria-hidden="true"><?php echo zad_icon( 'arrow', 18 ); // phpcs:ignore ?></span></a>
					<?php if ( $r['chips'] ) : ?><ul class="zcities__fams"><?php foreach ( $r['chips'] as $c ) : ?><li style="--fa:<?php echo esc_attr( $c['color'] ); ?>"><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo zad_icon( $c['icon'], 14 ); // phpcs:ignore ?> <?php echo esc_html( $c['name'] ); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php else : ?>
				<p class="empty">لا توجد مدن منشورة حالياً.</p>
			<?php endif; ?>

			<p class="zhub__cta">
				<?php if ( $wa ) : ?>
					<a class="btn btn--accent" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اطلب الخدمة عبر واتساب</a>
				<?php else : ?>
					<button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> اطلب الخدمة</button>
				<?php endif; ?>
			</p>
		</div>
	</section>
</main>
