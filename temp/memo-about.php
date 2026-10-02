<?php defined( 'ABSPATH' ) || exit; /* Template Name: about */
get_header();
$img = function ( $k ) { $v = zad_opt( $k ); return ( is_array( $v ) && ! empty( $v['id'] ) ) ? (int) $v['id'] : 0; };
$ne  = function ( $rows, $key ) { return array_values( array_filter( (array) $rows, function ( $r ) use ( $key ) { return ! empty( $r[ $key ] ); } ) ); };

$story_t = zad_opt( 'zad_story_title' );
$story   = zad_opt( 'zad_story_text' );
$legacy1 = zad_opt( 'memopt_about_sec1_h' );
$legacy3 = zad_opt( 'memopt_about_sec3_h' );
$page_content = '';
while ( have_posts() ) { the_post(); $page_content = trim( get_the_content() ); }
$simg    = $img( 'zad_story_img' ) ?: $img( 'memopt_about_sec1_img' );
$mission = zad_opt( 'zad_mission' );
$vision  = zad_opt( 'zad_vision' );
$values  = $ne( zad_opt( 'zad_values', array() ), 't' );
$line    = $ne( zad_opt( 'zad_timeline', array() ), 't' );
$team    = $ne( zad_opt( 'zad_team', array() ), 'name' );
$stats   = $ne( zad_opt( 'zad_global_stats', array() ), 'n' );
$legacy2 = $ne( zad_opt( 'memopt_about_sec2_grp', array() ), 'memopt_about_sec2_grp_h' );
$points  = zad_lines( zad_opt( 'zad_commit_points' ) );
$clients = $ne( zad_opt( 'zad_clients', array() ), 'name' );
$empty   = ! ( $story || $legacy1 || $page_content || $mission || $values || $line || $team );

get_template_part( 'template-parts/page-hero', null, array(
	'title'  => get_the_title(),
	'sub'    => '<p>' . esc_html( zad_opt( 'zad_about_tagline', 'نعرّفك بنا: من نحن، وماذا نؤمن به، وكيف نعمل لخدمتك.' ) ) . '</p>',
	'crumbs' => zad_current_crumbs(),
) );
?>
<main id="main">

<?php if ( $empty && current_user_can( 'edit_theme_options' ) ) : ?>
	<div class="wrap" style="padding-top:24px"><div class="answer"><span class="eyebrow">للمدير فقط</span><p>صفحة «من نحن» فارغة. اذهب إلى الأدوات ← حالة الرئيسية ← «تهيئة بقيم افتراضية»، أو عبّئها من إعدادات القالب ← «صفحة من نحن».</p></div></div>
<?php endif; ?>

<?php if ( $story || $legacy1 || $page_content ) : ?>
<section class="sec">
	<div class="wrap split">
		<div>
			<span class="eyebrow">قصتنا</span>
			<?php if ( $story_t ) : ?><h2><?php echo esc_html( $story_t ); ?></h2><?php endif; ?>
			<div class="prose entry-content">
				<?php echo $story ? wp_kses_post( wpautop( $story ) ) : wp_kses_post( wpautop( $legacy1 ) ); ?>
				<?php echo $page_content ? apply_filters( 'the_content', $page_content ) : ''; // phpcs:ignore ?>
			</div>
			<p class="hero__btns"><button type="button" class="btn btn--accent" data-open-wizard><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> احجز موعد</button><a class="btn btn--ghost-dark" href="<?php echo esc_url( zad_services_url() ); ?>">تصفّح خدماتنا</a></p>
		</div>
		<div class="split__img aboutimg">
			<?php if ( $simg ) { echo wp_get_attachment_image( $simg, 'large', false, array( 'loading' => 'lazy', 'alt' => '' ) ); } else { echo '<div class="aboutimg__ph">' . zad_icon( 'users', 80 ) . '</div>'; } // phpcs:ignore ?>
			<?php if ( zad_opt( 'zad_since' ) ) : ?><div class="aboutimg__badge"><b>منذ <?php echo esc_html( zad_opt( 'zad_since' ) ); ?></b><small>في خدمة عملائنا</small></div><?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $stats ) : ?>
<section class="stats stats--home"><div class="wrap stats__grid"><?php foreach ( $stats as $x ) : ?><div class="stat"><b data-count="<?php echo esc_attr( $x['n'] ); ?>"><?php echo esc_html( $x['n'] ); ?></b><span><?php echo esc_html( $x['l'] ?? '' ); ?></span></div><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if ( $mission || $vision ) : ?>
<section class="sec sec--tint">
	<div class="wrap mv">
		<?php if ( $mission ) : ?><div class="mv__c"><span class="icard__ic"><?php echo zad_icon( 'bolt', 28 ); // phpcs:ignore ?></span><h3>رسالتنا</h3><p><?php echo esc_html( $mission ); ?></p></div><?php endif; ?>
		<?php if ( $vision ) : ?><div class="mv__c"><span class="icard__ic"><?php echo zad_icon( 'star', 28 ); // phpcs:ignore ?></span><h3>رؤيتنا</h3><p><?php echo esc_html( $vision ); ?></p></div><?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $values ) : ?>
<section class="sec">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">قيمنا</span><h2>ما نلتزم به في كل زيارة</h2></header>
		<div class="cardgrid">
			<?php foreach ( $values as $v ) : ?><div class="icard"><span class="icard__ic"><?php echo zad_icon( ! empty( $v['icon'] ) ? $v['icon'] : 'shield', 26 ); // phpcs:ignore ?></span><h3><?php echo esc_html( $v['t'] ); ?></h3><p><?php echo esc_html( $v['d'] ?? '' ); ?></p></div><?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $legacy2 ) : ?>
<section class="sec sec--tint"><div class="wrap why">
	<?php foreach ( $legacy2 as $g ) : ?><div class="why__item"><?php if ( ! empty( $g['memopt_about_sec2_grp_img']['id'] ) ) { echo wp_get_attachment_image( $g['memopt_about_sec2_grp_img']['id'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); } ?><h3><?php echo esc_html( $g['memopt_about_sec2_grp_h'] ); ?></h3><p><?php echo esc_html( $g['memopt_about_sec2_grp_p'] ?? '' ); ?></p></div><?php endforeach; ?>
</div></section>
<?php endif; ?>

<?php if ( $line ) : ?>
<section class="sec sec--dark">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">رحلتنا</span><h2>محطات صنعت الفرق</h2></header>
		<ol class="journey">
			<?php foreach ( $line as $i => $m ) : ?><li><span class="journey__y"><?php echo esc_html( $m['year'] ?? '' ); ?></span><div><h3><?php echo esc_html( $m['t'] ); ?></h3><p><?php echo esc_html( $m['d'] ?? '' ); ?></p></div></li><?php endforeach; ?>
		</ol>
	</div>
</section>
<?php endif; ?>

<?php if ( $points ) : ?>
<section class="sec">
	<div class="wrap wrap--narrow">
		<header class="sec__head"><span class="eyebrow">التزامنا تجاهك</span><h2>وعودنا لك</h2></header>
		<ul class="featgrid"><?php foreach ( $points as $x ) : ?><li><?php echo zad_icon( 'check', 20 ); // phpcs:ignore ?><span><?php echo esc_html( $x ); ?></span></li><?php endforeach; ?></ul>
	</div>
</section>
<?php endif; ?>

<?php if ( $team ) : ?>
<section class="sec sec--tint">
	<div class="wrap">
		<header class="sec__head"><span class="eyebrow">فريقنا</span><h2>أشخاص يقفون خلف كل خدمة</h2></header>
		<div class="team">
			<?php foreach ( $team as $m ) : $tid = ! empty( $m['img']['id'] ) ? (int) $m['img']['id'] : 0; ?>
				<div class="member"><div class="member__img"><?php if ( $tid ) { echo wp_get_attachment_image( $tid, 'medium', false, array( 'loading' => 'lazy', 'alt' => $m['name'] ) ); } else { echo zad_icon( 'users', 52 ); } // phpcs:ignore ?></div><h3><?php echo esc_html( $m['name'] ); ?></h3><p><?php echo esc_html( $m['role'] ?? '' ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $legacy3 || zad_opt( 'zad_cr' ) || zad_opt( 'zad_vat' ) ) : ?>
<section class="sec">
	<div class="wrap wrap--narrow">
		<?php if ( $legacy3 ) : ?><div class="prose"><?php echo wp_kses_post( wpautop( $legacy3 ) ); ?></div><?php endif; ?>
		<?php if ( zad_opt( 'zad_cr' ) || zad_opt( 'zad_vat' ) ) : ?>
		<header class="sec__head"><span class="eyebrow">شركة موثّقة</span><h2>بياناتنا الرسمية</h2></header>
		<dl class="info">
			<?php foreach ( array( 'الاسم النظامي' => zad_opt( 'zad_legal_name' ), 'السجل التجاري' => zad_opt( 'zad_cr' ), 'الرقم الضريبي' => zad_opt( 'zad_vat' ), 'العنوان' => zad_opt( 'memopt_address' ), 'ساعات العمل' => zad_opt( 'zad_hours' ) ) as $k => $v ) { if ( $v ) { echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>'; } } ?>
		</dl>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $clients ) : ?>
<section class="sec sec--tint"><div class="wrap">
	<header class="sec__head"><span class="eyebrow">عملاؤنا</span><h2>جهات وشركات تثق بنا</h2></header>
	<div class="clients"><?php foreach ( $clients as $c ) : ?><div class="client"><b><?php echo esc_html( $c['name'] ); ?></b><span><?php echo esc_html( $c['note'] ?? '' ); ?></span></div><?php endforeach; ?></div>
</div></section>
<?php endif; ?>

</main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
