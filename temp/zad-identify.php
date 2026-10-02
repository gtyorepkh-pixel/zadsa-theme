<?php defined( 'ABSPATH' ) || exit; /* Template Name: تعرّف على الآفة */
get_header();
get_template_part( 'template-parts/page-hero', null, array( 'sub' => '<p>أجب عن سؤالين ونقترح عليك الآفة المرجّحة والحل المناسب.</p>', 'crumbs' => array( array( 'الرئيسية', home_url( '/' ) ), array( get_the_title(), '' ) ) ) );
$where = zad_lines( zad_opt( 'zad_identify_where' ) );
$kind  = zad_lines( zad_opt( 'zad_identify_kind' ) );
$rules = array();
foreach ( (array) zad_opt( 'zad_identify_rules', array() ) as $r ) {
	if ( empty( $r['title'] ) ) { continue; }
	$sid = ! empty( $r['service'] ) ? (int) $r['service'] : 0;
	$rules[] = array( 'where' => $r['where'] ?? '', 'kind' => $r['kind'] ?? '', 'title' => $r['title'], 'text' => $r['text'] ?? '', 'url' => $sid ? get_permalink( $sid ) : '', 'service' => $sid ? get_the_title( $sid ) : '', 'wa' => zad_wa_link( 'مرحباً، لدي مشكلة: ' . $r['title'], $sid ) );
}
?>
<main id="main" class="sec"><div class="wrap wrap--narrow">
	<div class="idt" data-identify data-rules="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>">
		<div class="idt__step" data-id-step="1">
			<h2>1) أين رأيتها؟</h2>
			<div class="wiz__chips"><?php foreach ( $where as $w ) : ?><button type="button" class="chipbtn" data-id-where="<?php echo esc_attr( $w ); ?>"><?php echo esc_html( $w ); ?></button><?php endforeach; ?></div>
		</div>
		<div class="idt__step" data-id-step="2" hidden>
			<h2>2) كيف تبدو أو ماذا تفعل؟</h2>
			<div class="wiz__chips"><?php foreach ( $kind as $k ) : ?><button type="button" class="chipbtn" data-id-kind="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $k ); ?></button><?php endforeach; ?></div>
			<p><button type="button" class="btn btn--ghost-dark" data-id-back>رجوع</button></p>
		</div>
		<div class="idt__res" data-id-result hidden>
			<span class="eyebrow">النتيجة المرجّحة</span>
			<h2 data-id-title></h2><p data-id-text></p>
			<div class="hero__btns"><a class="btn btn--primary" data-id-service href="#"></a><a class="btn btn--wa" data-id-wa href="#" target="_blank" rel="noopener"><?php echo zad_icon( 'whatsapp', 20 ); // phpcs:ignore ?> اسأل عبر واتساب</a><button type="button" class="btn btn--ghost-dark" data-id-reset>ابدأ من جديد</button></div>
		</div>
		<p class="meta-line">النتيجة تقريبية للإرشاد، والفحص الميداني هو ما يحسم نوع الآفة.</p>
	</div>
	<div class="prose entry-content"><?php while ( have_posts() ) { the_post(); the_content(); } ?></div>
</div></main>
<?php get_template_part( 'template-parts/cta-band' ); get_footer(); ?>
