<?php defined( 'ABSPATH' ) || exit;
/** Existing optional tools and selection keys. Admin requests only. */
// 3. عزل أدوات الفحص والنقل المؤقتة: تعمل في لوحة التحكم فقط ولا تُحمّل للزوار أو جوجل أبداً
if ( is_admin() ) {
	$tools_mode = zad_opt( 'zad_tools_mode', 'all' );
	$all_tools  = array(
		'leadreport'   => 'inc/zad-leadreport.php',
		'schemacheck'  => 'inc/zad-schemacheck.php',
		'faqmigrate'   => 'inc/zad-faqmigrate.php',
		'svcconvert'   => 'inc/zad-svcconvert.php',
		'labmerge'     => 'inc/zad-labmerge.php',   // «نقل محتوى المسودات»: لسه شغالة — دمج المسودات ما تمّش
		'cityclean'    => 'inc/zad-cityclean.php',  // «إزالة ذكر مدن»: القواعد الموسّعة لسه ما اتشغلتش
		'domaudit'     => 'inc/zad-domaudit.php',
		'audit'        => 'inc/zad-audit.php',      // «فحص السيو»
		'faqanswer'    => 'inc/zad-faq-answer-tool.php',
		'diag'         => 'inc/zad-diag.php',
		'pkgcat'       => 'inc/zad-pkgcat.php',    // «توحيد فئات الباقات»: توحيد العمود التاسع عبر صفحات المدن
		'ctxlinks'     => 'inc/zad-ctxlinks.php',  // «الروابط السياقية»: زرع روابط جوّه النص بين خدمات المدينة الواحدة
		'pkgcopy'      => 'inc/zad-pkgcopy.php',   // «نسخ باقات الأسعار بين المدن»: جدة تأخذ أسعار الرياض
		'faqlink'      => 'inc/zad-faqlink.php',  // «ربط الأسئلة بالخدمات»: الحقل اللي الـREST ما يكتبهوش
	);
	if ( 'all' === $tools_mode ) {
		foreach ( $all_tools as $file ) {
			if ( file_exists( ZAD_THEME_DIR . $file ) ) { require_once ZAD_THEME_DIR . $file; }
		}
	} elseif ( 'custom' === $tools_mode ) {
		$picked = (array) zad_opt( 'zad_active_tools_list', array_keys( $all_tools ) );
		foreach ( $picked as $tk ) {
			if ( isset( $all_tools[ $tk ] ) && file_exists( ZAD_THEME_DIR . $all_tools[ $tk ] ) ) {
				require_once ZAD_THEME_DIR . $all_tools[ $tk ];
			}
		}
	}
}

