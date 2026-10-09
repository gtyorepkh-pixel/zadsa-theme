<?php
/**
 * Zad Saudi — content fixes (warranty policy · working hours · typo).   DRY-RUN BY DEFAULT: nothing is written unless ZAD_APPLY=1.
 *
 * Run from the WordPress root (take the backup first: bash zad-backup.sh):
 *   wp eval-file zad-fix.php                       ← dry-run: lists every change (before → after) + a CSV
 *   ZAD_ONLY=25797 wp eval-file zad-fix.php        ← dry-run for one page (comma-separated IDs)
 *   ZAD_APPLY=1 wp eval-file zad-fix.php           ← applies the AUTO/HOURS/TYPO changes (and writes an undo file)
 *   ZAD_APPLY=1 ZAD_HUBS=1 wp eval-file zad-fix.php← also applies the HUB sentences (multi-service pages, only when the 80 chars before
 *                                                    the warranty name exactly ONE of the three services and no other service)
 *   ZAD_UNDO=/path/undo-….json wp eval-file zad-fix.php   ← restores the rows saved by a previous apply
 *
 * What it changes (only published / draft / pending / scheduled content; never private, trash, revisions, or `_zad_faqmig_backup`):
 *   WARRANTY  termite → «ضمان مكتوب 15 عاماً» · bedbug → «ضمان مكتوب 3 أشهر» · roach → «ضمان مكتوب 3 أو 6 أشهر حسب الاتفاق»
 *             on the pages of that service (decided from the page title + slug; a page naming two services is a HUB page);
 *             titles, excerpts, content, `_zad_*` fields (serialized values included), Yoast title / description.
 *             «ضمان 100%» and «… بنسبة 100%» of those services are replaced / removed. The free follow-up after two weeks stays.
 *             Other services (rodents, ants, spraying, cleaning…) are NEVER edited.
 *   HOURS     «من 8 صباحاً … 10 مساءً» / «8 ص – 10 م» / 08:00|22:00  →  11 مساءً / 23:00 (everything in the same 8-to-10 range).
 *   TYPO      «الجل الجديد» → «الجيل الجديد».
 * Slugs, URLs, links and post_name are never touched (direct column updates; post_modified is left as it is).
 * Anything the rules cannot decide is listed as REVIEW (with its text) and left untouched.
 */
if ( ! defined( 'ABSPATH' ) && ! defined( 'ZADFIX_LIB' ) ) { exit( "Run with: wp eval-file zad-fix.php\n" ); }

/* ============================ rules (pure functions) ============================ */

function zf_policy() {
	return array(
		'termite' => array( 'kw' => '/نمل\s*(?:ال)?[أا]بيض|[أا]رضة|termite|white-?ant/iu', 'nom' => 'ضمان مكتوب 15 عاماً', 'acc' => 'ضماناً مكتوباً لمدة 15 عاماً', 'bare' => 'مكتوب 15 عاماً', 'dur' => '15 عاماً' ),
		'bedbug'  => array( 'kw' => '/بق\s*الفراش|(?<![\p{L}])البق(?![\p{L}])|bed-?bug/iu', 'nom' => 'ضمان مكتوب 3 أشهر', 'acc' => 'ضماناً مكتوباً لمدة 3 أشهر', 'bare' => 'مكتوب 3 أشهر', 'dur' => '3 أشهر' ),
		'roach'   => array( 'kw' => '/صراصير|صرصور|cockroach|(?<![a-z])roach/iu', 'nom' => 'ضمان مكتوب 3 أو 6 أشهر حسب الاتفاق', 'acc' => 'ضماناً مكتوباً لمدة 3 أو 6 أشهر حسب الاتفاق', 'bare' => 'مكتوب 3 أو 6 أشهر حسب الاتفاق', 'dur' => '3 أو 6 أشهر حسب الاتفاق' ),
	);
}
/** Words of services that are NOT covered by the policy (a window naming them is a mixed sentence). */
function zf_other_service_re() { return '/فئران|فأر|قوارض|جرذ|حمام|نمل(?!\s*(?:ال)?[أا]بيض)|رش\s+(?:حشرات|شقة|الشقة|مبيدات|عام)|مكافحة\s+حشرات|حشرات\s+(?:زاحفة|عامة)|تنظيف|نقل\s+(?:عفش|اثاث|أثاث)|عزل|تسليك|جلي|خزانات|مكيف/u'; }

/** Policy key of a page (title + slug): exactly one of the three services, else ''. */
function zf_page_key( $title, $slug ) {
	$hay = html_entity_decode( strip_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ) . ' ' . rawurldecode( (string) $slug );
	$hit = array();
	foreach ( zf_policy() as $k => $p ) { if ( preg_match( $p['kw'], $hay ) ) { $hit[] = $k; } }
	return 1 === count( $hit ) ? $hit[0] : '';
}
/** Policy keys named in a text window. */
function zf_keys_in( $text ) {
	$o = array();
	foreach ( zf_policy() as $k => $p ) { if ( preg_match( $p['kw'], $text ) ) { $o[] = $k; } }
	return $o;
}

function zf_re() {
	static $r = null;
	if ( null !== $r ) { return $r; }
	$SP   = '(?:[\s\x{00A0}]|&nbsp;)';
	$W    = '(?:ثلاثة|ثلاث|أربعة|اربعة|أربع|اربع|خمسة|خمس|ستة|ست|سبعة|سبع|ثمانية|ثماني|تسعة|تسع|عشرة|عشر)';
	$N    = '(?:[0-9٠-٩]+|' . $W . ')';
	$U    = '(?:سنوات|سنين|سنة|أعوام|اعوام|عام|أشهر|اشهر|شهور|شهراً|شهرا|شهر)';
	$SEP  = '(?:' . $SP . '*[-–—]' . $SP . '*|' . $SP . '+(?:إلى|الى|أو|او|وحتى|و)' . $SP . '+|' . $SP . '*و' . $SP . '*)';
	$DUR1 = '(?:' . $N . '(?:' . $SEP . $N . ')*' . $SP . '*' . $U . '(?:' . $SP . '+(?:كاملة|كامل))?|(?:سنتين|سنتان|عامين|عامان|شهرين|سنة|عام|شهر)(?:' . $SP . '+(?:كاملة|كامل))?|[0-9٠-٩]+' . $SP . '*[%٪])(?![\p{L}])';
	$ADJ  = '(?:مكتوب|موثق|حقيقي|فعلي|كتابي|رسمي|فعال|شامل|كامل|مجاني)(?:اً|ًا|ا)?';
	$CONN = '(?:(?:يصل|تصل|يمتد|تمتد|يبدأ|تبدأ|يتراوح|تتراوح)' . $SP . '+)?(?:(?:إلى|الى|الي|حتى|حتي|لمدة|لـ|من|بين|مدة|نحو|قرابة)' . $SP . '+)?';
	$TAIL = '(?:' . $SP . '*(?:،|,)?' . $SP . '*(?:و)?(?:يصل|تصل)' . $SP . '+(?:إلى|الى|الي|لـ)' . $SP . '+' . $DUR1 . ')?(?:' . $SP . '+(?:حسب|وفق)' . $SP . '+(?:رغبة' . $SP . '+العميل|الاتفاق|الباقة|العقد|الخدمة|المساحة))?';
	$r = array(
		'dur1' => $DUR1,
		// «ضمان … 10 سنوات» (noun, up to two adjectives, connector, duration, optional tail)
		'main' => '/(?<![\p{L}])(?<pre>[بولكف]{0,2})(?<noun>ضمانات|ضمان(?:اً|ًا|ا)|الضمان|ضمان)(?<mid>(?:' . $SP . '+' . $ADJ . '){0,2})(?:' . $SP . '+على' . $SP . '+(?:جميع' . $SP . '+)?(?:الخدمة|الخدمات|التنفيذ|المعالجة|العمل|الإبادة|الرش|النتائج))?(?<conn>(?:' . $SP . '*[:：]' . $SP . '*|' . $SP . '+)' . $CONN . ')(?<dur>' . $DUR1 . ')' . $TAIL . '/u',
		// «مدة الضمان [على X] إلى 10 سنوات» → only the duration is replaced
		'dur2' => '/(?<![\p{L}])(?<lead>(?:مدة|فترة)' . $SP . '+(?:ال)?ضمان(?:' . $SP . '+(?:على|في|لـ|ل)[^<>\n.؛!؟،:]{1,45}?)?' . $SP . '+(?:هي|هو|تبلغ|تصل' . $SP . '+إلى|يصل' . $SP . '+إلى|إلى|الى|حتى)' . $SP . '+)(?<dur>' . $DUR1 . ')/u',
		// «100% ضمان»
		'pct_first' => '/(?<![\p{L}\d])(?:100|١٠٠)' . $SP . '*[%٪]' . $SP . '*(?<noun>ضمان)(?![\p{L}])/u',
		// «… بنسبة 100%» (removed only after a result word, see zf_fix_text)
		'pct_tail' => '/' . $SP . '+بنسبة' . $SP . '+(?:100|١٠٠)' . $SP . '*[%٪]/u',
		// the broad detector used for REVIEW (a warranty word followed within 70 chars by a duration)
		'review' => '/ضمان[^<>\n.؛!؟]{0,70}?(?:[0-9٠-٩]+|سنة|سنتين|سنوات|عام|أعوام|شهر|شهرين|أشهر|شهور|أسبوع|أسبوعين|%|٪)/u',
		'cell' => '/^' . $SP . '*(?:مع' . $SP . '+)?(?:ال)?ضمان(?:' . $SP . '+' . $ADJ . '){0,2}(?:' . $SP . '*[:：]' . $SP . '*|' . $SP . '+)' . $CONN . $DUR1 . $TAIL . $SP . '*$/u',
		// hours: «8 ص / 8 صباحاً … 10 م / 10 مساءً»
		'hours' => '/(?<a>(?:8|٨)' . $SP . '*(?:ص(?![\p{L}])|صباحاً|صباحًا|صباحا)(?:' . $SP . '*(?:،|-|–|—|إلى|الى|حتى|حتي|و)' . $SP . '*(?:الساعة' . $SP . '*)?))(?<b>(?:10|١٠))(?![0-9٠-٩])(?<c>' . $SP . '*(?:م(?![\p{L}])|مساءً|مساءا|مساء|مساءًا))/u',
		'hours_spec' => '/(08:00' . $SP . '*\|' . $SP . '*)22:00/',
		'typo' => '/الجل(' . $SP . '+)الجديد/u',
	);
	return $r;
}

/** Text without tags (a space instead of each tag, so table cells do not run together). */
function zf_plain( $s ) { return trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $s ), ENT_QUOTES, 'UTF-8' ) ) ); }

/** Readable context of a hit at byte offset $off (for the REVIEW / MIXED rows of the CSV). */
function zf_ctx( $text, $off, $len ) {
	return mb_substr( zf_plain( mb_strcut( $text, max( 0, $off - 420 ), min( 420, $off ), 'UTF-8' ) ), -130 ) . ' ⟦' . zf_plain( mb_strcut( $text, $off, $len, 'UTF-8' ) ) . '⟧ ' . mb_substr( zf_plain( mb_strcut( $text, $off + $len, 420, 'UTF-8' ) ), 0, 130 );
}

/** The phrase to print for a match: accusative / «الضمان» / plain forms. */
function zf_phrase( $m, $k ) {
	$P = zf_policy();
	if ( preg_match( '/^ضمان(?:اً|ًا|ا)$/u', $m['noun'] ) ) { return $m['pre'] . $P[ $k ]['acc']; }
	if ( 'الضمان' === $m['noun'] ) { return $m['pre'] . 'الضمان' . ( preg_match( '/[:：]/u', $m['conn'] ) ? ': ' : ' ' ) . $P[ $k ]['bare']; }
	return $m['pre'] . $P[ $k ]['nom'];
}

/**
 * Warranty pass over one string.  $key = the page's own policy key ('' = a page that names none or several services).
 * Page mode  ($key): every «ضمان … <duration>» is replaced unless its sentence also names another service (→ MIXED, untouched).
 * Hub mode   ($key = ''): a hit is decided by the 80 characters BEFORE it: exactly one policy service and no other service → HUB;
 *            two or more → REVIEW; none → ignored (it is some other service's warranty). Replaced only when $do_hub.
 */
function zf_warranty_pass( $text, $key, $do_hub = false, $which = 'main' ) {
	$R = zf_re(); $other = zf_other_service_re(); $log = array();
	if ( ! preg_match_all( $R[ $which ], $text, $mm, PREG_OFFSET_CAPTURE ) ) { return array( $text, $log ); }
	$names = array_values( array_filter( array_keys( $mm ), 'is_string' ) );
	$out = ''; $last = 0;
	foreach ( $mm[0] as $i => $hit ) {
		list( $str, $off ) = $hit;
		$m = array( 0 => $str );
		foreach ( $names as $g ) { $m[ $g ] = $mm[ $g ][ $i ][0]; }
		if ( 'main' === $which && preg_match( '/لل$/u', $m['pre'] ) ) { $log[] = array( 'REVIEW', 'definite-prefix', $str, zf_ctx( $text, $off, strlen( $str ) ) ); continue; } // «للضمان 10 سنوات»: needs a human sentence
		$before = mb_substr( zf_plain( mb_strcut( $text, max( 0, $off - 320 ), min( 320, $off ), 'UTF-8' ) ), -80 );
		$after  = mb_substr( zf_plain( mb_strcut( $text, $off + strlen( $str ), 160, 'UTF-8' ) ), 0, 40 );
		if ( $key ) {
			$ks = zf_keys_in( $before . ' ' . $after );
			if ( array_diff( $ks, array( $key ) ) || preg_match( $other, $before . ' ' . $after ) ) { $log[] = array( 'MIXED', 'warranty:' . $key, $str, zf_ctx( $text, $off, strlen( $str ) ) ); continue; }
			$k = $key; $kind = 'AUTO';
		} else {
			if ( ! zf_keys_in( $before ) ) { continue; }                                 // some other service's warranty: never touched
			$before = preg_replace( '/^.*[.؟!؛\n]/su', '', $before );                    // the sentence of the warranty only
			$ks = zf_keys_in( $before );
			if ( ! $ks ) { $log[] = array( 'REVIEW', 'hub-other-sentence', $str, zf_ctx( $text, $off, strlen( $str ) ) ); continue; }
			if ( 1 !== count( $ks ) || preg_match( $other, $before ) ) { $log[] = array( 'REVIEW', 'hub-mixed', $before . ' ⟦' . $str . '⟧', zf_ctx( $text, $off, strlen( $str ) ) ); continue; }
			if ( preg_match( '/ضمان/u', $before ) ) { $log[] = array( 'REVIEW', 'hub-second-warranty', $str, zf_ctx( $text, $off, strlen( $str ) ) ); continue; } // the sentence already has a warranty of its own: this one may belong to another service
			$k = $ks[0]; $kind = 'HUB';
		}
		$new = 'main' === $which ? zf_phrase( $m, $k ) : $m['lead'] . zf_policy()[ $k ]['dur'];
		if ( preg_replace( '/[\s\x{00A0}]+/u', ' ', $str ) === $new ) { continue; }          // already the approved phrase
		$log[] = array( $kind, 'warranty' . ( 'main' === $which ? '' : '-duration' ) . ':' . $k, ( 'HUB' === $kind ? $before . ' ⟦' . $str . '⟧' : $str ), $new );
		if ( 'HUB' === $kind && ! $do_hub ) { continue; }
		$out .= substr( $text, $last, $off - $last ) . $new;
		$last = $off + strlen( $str );
	}
	return array( $out . substr( $text, $last ), $log );
}

/** HTML tables with a «الضمان» column (or «الضمان | 10 سنوات» rows): only the duration inside that cell changes, the markup stays. */
function zf_fix_tables( $html, $key, $do_hub = false ) {
	$R = zf_re(); $P = zf_policy(); $log = array(); $other = zf_other_service_re();
	if ( false === stripos( $html, '<table' ) ) { return array( $html, $log ); }
	$label = '/^(?:مدة\s+|فترة\s+)?(?:ال)?ضمان(?:ات)?$/u';
	$cellre = '/^(?:ضمان\s+)?(?:يصل\s+إلى\s+|حتى\s+|حتي\s+|لمدة\s+|يبدأ\s+من\s+)?' . $R['dur1'] . '(?:\s+(?:حسب|وفق)\s+\S+(?:\s+\S+)?)?$/u';
	$html = preg_replace_callback( '#<table\b.*?</table>#isu', function ( $t ) use ( &$log, $key, $P, $R, $do_hub, $label, $cellre, $other ) {
		$tbl = $t[0];
		if ( preg_match( '/colspan|rowspan/i', $tbl ) ) { return $tbl; }
		$col = null; $ri = -1;
		return preg_replace_callback( '#<tr\b[^>]*>.*?</tr>#isu', function ( $r ) use ( &$col, &$ri, &$log, $key, $P, $R, $do_hub, $label, $cellre, $other ) {
			$ri++; $row = $r[0];
			if ( ! preg_match_all( '#<(t[hd])\b[^>]*>(.*?)</\1>#isu', $row, $c, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) { return $row; }
			$tx = array();
			foreach ( $c as $x ) { $tx[] = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $x[2][0] ), ENT_QUOTES, 'UTF-8' ) ) ); }
			$idx = null;
			if ( 0 === $ri && count( $tx ) >= 2 && ! preg_match( $cellre, $tx[1] ) ) { foreach ( $tx as $i => $v ) { if ( preg_match( $label, $v ) ) { $col = $i; return $row; } } } // header row (unless it is already «الضمان | 10 سنوات»)
			if ( count( $tx ) >= 2 && preg_match( $label, $tx[0] ) ) { $idx = 1; }
			elseif ( null !== $col && isset( $tx[ $col ] ) ) { $idx = $col; }
			if ( null === $idx || ! preg_match( $cellre, $tx[ $idx ] ) ) { return $row; }
			$rest = implode( ' ', array_diff_key( $tx, array( $idx => 1 ) ) );
			if ( $key ) {
				if ( array_diff( zf_keys_in( $rest ), array( $key ) ) || preg_match( $other, $rest ) ) { $log[] = array( 'MIXED', 'table:' . $key, $rest . ' ⟦' . $tx[ $idx ] . '⟧', '' ); return $row; }
				$k = $key; $kind = 'AUTO';
			} else {
				$ks = zf_keys_in( $rest );
				if ( ! $ks ) { return $row; }
				if ( 1 !== count( $ks ) || preg_match( $other, $rest ) ) { $log[] = array( 'REVIEW', 'hub-table-mixed', $rest . ' ⟦' . $tx[ $idx ] . '⟧', '' ); return $row; }
				$k = $ks[0]; $kind = 'HUB';
			}
			if ( $tx[ $idx ] === $P[ $k ]['dur'] ) { return $row; }
			$log[] = array( $kind, 'table:' . $k, ( 'HUB' === $kind ? $rest . ' ⟦' . $tx[ $idx ] . '⟧' : $tx[ $idx ] ), $P[ $k ]['dur'] );
			if ( 'HUB' === $kind && ! $do_hub ) { return $row; }
			$inner = $c[ $idx ][2];
			$new   = preg_replace( '/(?:ضمان\s+)?(?:يصل\s+إلى\s+|حتى\s+|حتي\s+|لمدة\s+|يبدأ\s+من\s+)?' . $R['dur1'] . '(?:\s+(?:حسب|وفق)\s+\S+(?:\s+\S+)?)?/u', $P[ $k ]['dur'], $inner[0], 1 );
			return substr_replace( $row, $new, $inner[1], strlen( $inner[0] ) );
		}, $tbl );
	}, $html );
	return array( $html, $log );
}

/** One text → array( newText, log ).  Hours + typo on every page; warranty per zf_warranty_pass(). */
function zf_fix_text( $text, $key, $do_hub = false ) {
	$R = zf_re(); $P = zf_policy(); $log = array();
	if ( ! is_string( $text ) || '' === $text ) { return array( $text, $log ); }
	$text = preg_replace_callback( $R['hours'], function ( $m ) use ( &$log ) { $n = $m['a'] . '11' . $m['c']; $log[] = array( 'HOURS', 'hours', $m[0], $n ); return $n; }, $text );
	$text = preg_replace_callback( $R['hours_spec'], function ( $m ) use ( &$log ) { $n = $m[1] . '23:00'; $log[] = array( 'HOURS', 'hours_spec', $m[0], $n ); return $n; }, $text );
	$text = preg_replace_callback( $R['typo'], function ( $m ) use ( &$log ) { $n = 'الجيل' . $m[1] . 'الجديد'; $log[] = array( 'TYPO', 'typo', $m[0], $n ); return $n; }, $text );
	if ( $key ) {
		$text = preg_replace_callback( $R['pct_first'], function ( $m ) use ( &$log, $key, $P ) { $log[] = array( 'AUTO', 'pct:' . $key, $m[0], $P[ $key ]['nom'] ); return $P[ $key ]['nom']; }, $text );
		if ( in_array( $key, array( 'roach', 'bedbug' ), true ) ) {
			$t0   = $text;
			$text = preg_replace_callback( $R['pct_tail'], function ( $m ) use ( &$log, $t0 ) {
				$before = mb_substr( zf_plain( mb_strcut( $t0, max( 0, $m[0][1] - 120 ), min( 120, $m[0][1] ), 'UTF-8' ) ), -45 );
				if ( ! preg_match( '/تطهير|إبادة|ابادة|القضاء|نجاح|نتيجة|نتائج|فعالية|مكافحة/u', $before ) ) { return $m[0][0]; }
				$log[] = array( 'AUTO', 'pct_tail', trim( $before ) . $m[0][0], trim( $before ) ); return '';
			}, $text, -1, $cnt, PREG_OFFSET_CAPTURE );
		}
	}
	list( $text, $l ) = zf_fix_tables( $text, $key, $do_hub );          foreach ( $l as $e ) { $log[] = $e; }
	list( $text, $l ) = zf_warranty_pass( $text, $key, $do_hub );      foreach ( $l as $e ) { $log[] = $e; }
	list( $text, $l ) = zf_warranty_pass( $text, $key, $do_hub, 'dur2' ); foreach ( $l as $e ) { $log[] = $e; }
	return array( $text, $log );
}

/** Leftover warranty+duration mentions that stay after the rules (to be read by a human). */
function zf_review_hits( $text ) {
	$R = zf_re(); $P = zf_policy(); $o = array();
	$plain = zf_plain( preg_replace( '#<th\b.*?</th>#isu', ' ', (string) $text ) ); // header cells («مدة الضمان») are not statements
	foreach ( $P as $p ) { $plain = str_replace( array( $p['acc'], $p['nom'], 'الضمان: ' . $p['bare'], $p['bare'] ), ' ', $plain ); } // the approved phrases are not leftovers
	if ( preg_match_all( $R['review'], $plain, $m, PREG_OFFSET_CAPTURE ) ) {
		$seen = array();
		foreach ( $m[0] as $hit ) {
			if ( preg_match( '/متابعة/u', $hit[0] ) || isset( $seen[ $hit[0] ] ) ) { continue; } // the free follow-up is not a warranty
			$seen[ $hit[0] ] = 1; $o[] = array( mb_substr( $hit[0], 0, 140 ), zf_ctx( $plain, $hit[1], strlen( $hit[0] ) ) );
		}
	}
	return $o;
}

/** `_zad_spec` («الاسم | القيمة» lines): the «الضمان» row of a policy page says the policy; the 2-week follow-up moves to its own row. */
function zf_fix_spec( $text, $key ) {
	$P = zf_policy(); $log = array();
	if ( ! $key || ! is_string( $text ) || '' === trim( $text ) ) { return array( $text, $log ); }
	$lines = preg_split( '/\r\n|\n|\r/', $text );
	$out = array(); $done = false; $has_follow = false;
	foreach ( $lines as $l ) { if ( preg_match( '/^[\s\x{00A0}]*(?:المتابعة|زيارة المتابعة)/u', $l ) ) { $has_follow = true; } }
	foreach ( $lines as $l ) {
		if ( preg_match( '/^([\s\x{00A0}]*(?:مدة[\s\x{00A0}]+)?الضمان[\s\x{00A0}]*\|[\s\x{00A0}]*)(.*)$/u', $l, $m ) ) {
			$done = true;
			$old  = $m[2];
			if ( $old === $P[ $key ]['bare'] ) { $out[] = $l; continue; }
			$out[] = $m[1] . $P[ $key ]['bare'];
			$log[] = array( 'AUTO', 'spec-row:' . $key, $l, $m[1] . $P[ $key ]['bare'] );
			if ( ! $has_follow && preg_match( '/أسبوع|اسبوع/u', $old ) ) { // keep the follow-up as a separate row
				$out[] = 'المتابعة المجانية | ' . preg_replace( '/^[\s\x{00A0}]*(?:مكتوب|مجاناً|مجانا)[\s\x{00A0}]*/u', '', $old );
				$log[] = array( 'AUTO', 'spec-follow', '', end( $out ) );
			}
			continue;
		}
		$out[] = $l;
	}
	return array( implode( "\n", $out ), $log );
}

/** `_zad_packages` / `_zad_prices` / `_zad_sc_offers` («a | b | c» per line): a cell that is only a warranty phrase becomes the bare policy. */
function zf_fix_cells( $text, $key ) {
	$P = zf_policy(); $R = zf_re(); $log = array();
	if ( ! $key || ! is_string( $text ) || false === strpos( $text, '|' ) ) { return array( $text, $log ); }
	$lines = preg_split( '/(\r\n|\n|\r)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $lines as $i => $l ) {
		if ( $i % 2 || false === strpos( $l, '|' ) ) { continue; }
		$cells = explode( '|', $l );
		foreach ( $cells as $j => $c ) {
			if ( preg_match( $R['cell'], $c ) && trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $c ) ) !== $P[ $key ]['bare'] ) {
				$lead = preg_match( '/^[\s\x{00A0}]*/u', $c, $mm ) ? $mm[0] : ' ';
				$trail = preg_match( '/[\s\x{00A0}]*$/u', $c, $m2 ) ? $m2[0] : ' ';
				$new = $lead . $P[ $key ]['bare'] . $trail;
				$log[] = array( 'AUTO', 'cell:' . $key, trim( $c ), $P[ $key ]['bare'] );
				$cells[ $j ] = $new;
			}
		}
		$lines[ $i ] = implode( '|', $cells );
	}
	return array( implode( '', $lines ), $log );
}

/** Any value (string / array / object, serialized or not) → [ newValue, log ]; $path names the leaf for the report. */
function zf_fix_value( $v, $key, $hubs, $meta_key, $path = '' ) {
	$log = array();
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) { list( $nv, $l ) = zf_fix_value( $x, $key, $hubs, $meta_key, $path . '/' . $k ); $v[ $k ] = $nv; foreach ( $l as $e ) { $log[] = $e; } }
		return array( $v, $log );
	}
	if ( is_object( $v ) ) { return array( $v, $log ); }
	if ( ! is_string( $v ) || '' === $v ) { return array( $v, $log ); }
	$s = $v;
	if ( $key && '_zad_warranty' === $meta_key && '' === $path ) { // the short warranty field of a policy page = the policy
		$P = zf_policy();
		$new = $P[ $key ]['nom'];
		if ( preg_match( '/(?:جلسة|زيارة)[\s\x{00A0}]+(?:المتابعة|متابعة)[^|،,\n]*|متابعة[\s\x{00A0}]+مجانية[^|،,\n]*|إعادة[\s\x{00A0}]+المعالجة[^|،,\n]*/u', $s, $fm ) ) { $new .= ' مع ' . trim( preg_replace( '/^مع[\s\x{00A0}]+/u', '', $fm[0] ) ); } // the free follow-up stays, as part of the service
		if ( trim( $s ) !== $new ) { $log[] = array( 'AUTO', 'field:_zad_warranty', $s, $new ); }
		return array( $new, $log );
	}
	if ( '_zad_spec' === $meta_key ) { list( $s, $l ) = zf_fix_spec( $s, $key ); foreach ( $l as $e ) { $log[] = $e; } }
	if ( in_array( $meta_key, array( '_zad_packages', '_zad_prices', '_zad_sc_offers' ), true ) ) { list( $s, $l ) = zf_fix_cells( $s, $key ); foreach ( $l as $e ) { $log[] = $e; } }
	list( $s, $l ) = zf_fix_text( $s, $key, $hubs ); foreach ( $l as $e ) { $log[] = $e; }
	return array( $s, $log );
}

/** Hand-written replacements (zad-fix-manual.json: [{"id":25793,"field":"post_content","find":"…","replace":"…"}]) per page; "regex":true = a PCRE pattern (without delimiters; $1 in the replacement works). */
function zf_manual( $v, $rules, $field, &$hit, $path = '' ) {
	$log = array();
	if ( is_array( $v ) ) { foreach ( $v as $k => $x ) { list( $nv, $l ) = zf_manual( $x, $rules, $field, $hit, $path . '/' . $k ); $v[ $k ] = $nv; foreach ( $l as $e ) { $log[] = $e; } } return array( $v, $log ); }
	if ( ! is_string( $v ) || '' === $v ) { return array( $v, $log ); }
	foreach ( $rules as $i => $r ) {
		if ( '' !== (string) ( $r['field'] ?? '' ) && false === strpos( $field, $r['field'] ) ) { continue; }
		if ( ! empty( $r['regex'] ) ) {
			$nv = @preg_replace( '~' . $r['find'] . '~u', $r['replace'], $v, -1, $n );
			if ( null !== $nv && $n ) { $log[] = array( 'MANUAL', 'manual-regex', mb_substr( $r['find'], 0, 120 ), $r['replace'] ); $v = $nv; $hit[ $i ] = ( $hit[ $i ] ?? 0 ) + $n; }
			continue;
		}
		$n = substr_count( $v, $r['find'] );
		if ( $n ) { $v = str_replace( $r['find'], $r['replace'], $v ); $hit[ $i ] = ( $hit[ $i ] ?? 0 ) + $n; for ( $q = 0; $q < $n; $q++ ) { $log[] = array( 'MANUAL', 'manual', $r['find'], $r['replace'] ); } }
	}
	return array( $v, $log );
}

if ( defined( 'ZADFIX_LIB' ) ) { return; }

/* ============================ runner ============================ */
global $wpdb;
$apply = (bool) getenv( 'ZAD_APPLY' );
$hubs  = (bool) getenv( 'ZAD_HUBS' );
$only  = array_filter( array_map( 'intval', explode( ',', (string) getenv( 'ZAD_ONLY' ) ) ) );
$undo  = (string) getenv( 'ZAD_UNDO' );
$dir   = wp_upload_dir()['basedir'] . '/zad-inventory';
wp_mkdir_p( $dir );

/* ---------- undo ---------- */
if ( '' !== $undo ) {
	$j = json_decode( (string) file_get_contents( $undo ), true );
	if ( ! is_array( $j ) ) { exit( "Undo file not readable: $undo\n" ); }
	$n = 0;
	foreach ( (array) $j['posts'] as $id => $cols ) { $wpdb->update( $wpdb->posts, $cols, array( 'ID' => (int) $id ) ); clean_post_cache( (int) $id ); $n++; }
	foreach ( (array) $j['meta'] as $mid => $val ) { $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $val ), array( 'meta_id' => (int) $mid ) ); $n++; }
	foreach ( (array) $j['options'] as $name => $val ) { update_option( $name, maybe_unserialize( $val ) ); $n++; }
	foreach ( (array) $j['posts'] as $id => $c ) { zf_reset_yoast( (int) $id ); }
	exit( "Restored $n rows from $undo\n" );
}
function zf_reset_yoast( $id ) { // Yoast keeps its own copy of title/description in the indexable: drop it, it is rebuilt on the next visit
	global $wpdb;
	$t = $wpdb->prefix . 'yoast_indexable';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) { $wpdb->delete( $t, array( 'object_id' => $id, 'object_type' => 'post' ) ); }
}

$MAN = array(); $man_file = getenv( 'ZAD_MANUAL' ) ?: __DIR__ . '/zad-fix-manual.json'; $man_all = array(); $man_hit = array();
if ( is_readable( $man_file ) ) {
	$mj = json_decode( (string) file_get_contents( $man_file ), true );
	foreach ( (array) $mj as $i => $r ) { if ( ! empty( $r['id'] ) && isset( $r['find'], $r['replace'] ) && '' !== $r['find'] ) { $MAN[ (int) $r['id'] ][ $i ] = $r; $man_all[ $i ] = $r; } }
	echo 'manual replacements loaded: ' . count( $man_all ) . " ($man_file)\n";
}
$skip_types = array( 'revision', 'attachment', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'wp_template', 'wp_template_part', 'wp_navigation', 'zad_lead', 'user_request' );
$ph = implode( ',', array_fill( 0, count( $skip_types ), '%s' ) );
$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_name, post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','pending','future') AND post_type NOT IN ($ph) ORDER BY ID", $skip_types ) );
$meta_rows = $wpdb->get_results( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\\_zad\\_%' OR meta_key LIKE '\\_yoast\\_wpseo\\_%') AND meta_key <> '_zad_faqmig_backup'" );
$by_post = array();
foreach ( $meta_rows as $r ) { $by_post[ (int) $r->post_id ][] = $r; }

$changes = array(); $review = array(); $undo_posts = array(); $undo_meta = array(); $undo_opts = array(); $touched = array();
$rec = function ( $kind, $rule, $p, $field, $before, $after ) use ( &$changes ) {
	$changes[] = array( $kind, $rule, $p->post_type, $p->ID, $p->post_status, rawurldecode( $p->post_name ), $field, $before, $after );
};

foreach ( $posts as $p ) {
	if ( $only && ! in_array( (int) $p->ID, $only, true ) ) { continue; }
	$key = zf_page_key( $p->post_title, $p->post_name );
	$upd = array();
	foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $col ) {
		$src = $p->$col; $log0 = array();
		if ( ! empty( $MAN[ (int) $p->ID ] ) ) { list( $src, $log0 ) = zf_manual( $src, $MAN[ (int) $p->ID ], $col, $man_hit ); }
		list( $new, $log ) = zf_fix_value( $src, $key, $hubs, $col ); $log = array_merge( $log0, $log );
		foreach ( $log as $e ) { if ( in_array( $e[0], array( 'MIXED', 'REVIEW' ), true ) ) { $review[] = array( $e[0], $e[1], $p->post_type, $p->ID, $p->post_status, rawurldecode( $p->post_name ), $col, $e[2], $e[3] ); } else { $rec( $e[0], $e[1], $p, $col, $e[2], $e[3] ); } }
		if ( $new !== $p->$col ) { $upd[ $col ] = $new; }
		foreach ( zf_review_hits( $new ) as $h ) { if ( ! $key ) { continue; } $review[] = array( 'REVIEW', 'leftover', $p->post_type, $p->ID, $p->post_status, rawurldecode( $p->post_name ), $col, $h[0], $h[1] ); }
	}
	if ( $upd ) { $undo_posts[ $p->ID ] = array_intersect_key( array( 'post_title' => $p->post_title, 'post_excerpt' => $p->post_excerpt, 'post_content' => $p->post_content ), $upd ); }
	$mupd = array();
	foreach ( (array) ( $by_post[ (int) $p->ID ] ?? array() ) as $mr ) {
		$orig = $mr->meta_value; $val = maybe_unserialize( $orig );
		$log0 = array();
		if ( ! empty( $MAN[ (int) $p->ID ] ) ) { list( $val, $log0 ) = zf_manual( $val, $MAN[ (int) $p->ID ], $mr->meta_key, $man_hit ); }
		list( $nv, $log ) = zf_fix_value( $val, $key, $hubs, $mr->meta_key ); $log = array_merge( $log0, $log );
		foreach ( $log as $e ) { if ( in_array( $e[0], array( 'MIXED', 'REVIEW' ), true ) ) { $review[] = array( $e[0], $e[1], $p->post_type, $p->ID, $p->post_status, rawurldecode( $p->post_name ), $mr->meta_key, $e[2], $e[3] ); } else { $rec( $e[0], $e[1], $p, $mr->meta_key, $e[2], $e[3] ); } }
		$flat = is_string( $nv ) ? $nv : wp_json_encode( $nv, JSON_UNESCAPED_UNICODE );
		foreach ( zf_review_hits( $flat ) as $h ) { if ( ! $key ) { continue; } $review[] = array( 'REVIEW', 'leftover', $p->post_type, $p->ID, $p->post_status, rawurldecode( $p->post_name ), $mr->meta_key, $h[0], $h[1] ); }
		$new_raw = is_string( $nv ) ? $nv : maybe_serialize( $nv );
		if ( $new_raw !== $orig ) { $mupd[ (int) $mr->meta_id ] = $new_raw; $undo_meta[ (int) $mr->meta_id ] = $orig; }
	}
	if ( $upd || $mupd ) { $touched[ $p->ID ] = array( 'cols' => $upd, 'meta' => $mupd ); }
}

/* ---------- theme options: working hours (+ typo) ---------- */
$opt_changes = array();
foreach ( array( '_memo_theme_options' ) as $oname ) {
	$ov = get_option( $oname, null );
	if ( null === $ov ) { continue; }
	list( $nv, $log ) = zf_fix_value( $ov, '', false, $oname );
	foreach ( $log as $e ) { $changes[] = array( $e[0], $e[1], 'option', 0, '-', $oname, $oname, $e[2], $e[3] ); }
	if ( $nv !== $ov ) { $opt_changes[ $oname ] = array( $nv, $ov ); }
}

/* ---------- apply ---------- */
$stamp = gmdate( 'Ymd-Hi' );
if ( $apply ) {
	foreach ( $touched as $id => $t ) {
		if ( $t['cols'] ) { $wpdb->update( $wpdb->posts, $t['cols'], array( 'ID' => (int) $id ) ); }
		foreach ( $t['meta'] as $mid => $val ) { $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $val ), array( 'meta_id' => (int) $mid ) ); }
		clean_post_cache( (int) $id ); wp_cache_delete( (int) $id, 'post_meta' ); zf_reset_yoast( (int) $id );
	}
	foreach ( $opt_changes as $name => $pair ) { $undo_opts[ $name ] = maybe_serialize( $pair[1] ); update_option( $name, $pair[0] ); }
	delete_transient( 'zad_wiz_map3' );
	file_put_contents( "$dir/undo-$stamp.json", wp_json_encode( array( 'posts' => $undo_posts, 'meta' => $undo_meta, 'options' => $undo_opts ), JSON_UNESCAPED_UNICODE ) );
}

/* ---------- report ---------- */
$csv = "$dir/fix-" . ( $apply ? 'APPLIED' : 'dryrun' ) . "-$stamp.csv";
$fh  = fopen( $csv, 'w' ); fwrite( $fh, "\xEF\xBB\xBF" );
fputcsv( $fh, array( 'kind', 'rule', 'object_type', 'object_id', 'status', 'slug', 'field', 'before', 'after (or context for REVIEW/MIXED)' ) );
foreach ( array_merge( $changes, $review ) as $r ) { fputcsv( $fh, $r ); }
fclose( $fh );

foreach ( $man_all as $i => $r ) { if ( empty( $man_hit[ $i ] ) ) { echo "!! manual replacement NOT FOUND (page #{$r['id']}): " . mb_substr( $r['find'], 0, 60 ) . "\n"; } }
$ctr = array();
foreach ( $changes as $c ) { $ctr[ $c[0] ][ $c[1] ] = ( $ctr[ $c[0] ][ $c[1] ] ?? 0 ) + 1; }
$rev = array();
foreach ( $review as $c ) { $rev[ $c[0] . ':' . $c[1] ] = ( $rev[ $c[0] . ':' . $c[1] ] ?? 0 ) + 1; }
echo ( $apply ? "== APPLIED ==" : "== DRY-RUN (nothing written) ==" ) . ( $hubs ? ' [+HUB]' : '' ) . "\n";
foreach ( $ctr as $k => $rules ) { echo "$k: " . array_sum( $rules ) . '   (' . implode( ', ', array_map( function ( $r, $n ) { return "$r=$n"; }, array_keys( $rules ), $rules ) ) . ")\n"; }
foreach ( $rev as $k => $n ) { echo "$k: $n\n"; }
$by_type = array(); $by_field = array();
foreach ( $changes as $c ) {
	if ( 'MIXED' === $c[0] || 'REVIEW' === $c[0] ) { continue; }
	$by_type[ $c[2] ] = ( $by_type[ $c[2] ] ?? 0 ) + 1;
	$fk = in_array( $c[6], array( 'post_title', 'post_excerpt', 'post_content' ), true ) ? $wpdb->posts . '.' . $c[6] : ( 'option' === $c[2] ? $wpdb->options . '.' . $c[5] : $wpdb->postmeta . '.' . $c[6] );
	$by_field[ $fk ] = ( $by_field[ $fk ] ?? 0 ) + 1;
}
arsort( $by_type ); arsort( $by_field );
echo "— حسب نوع المحتوى —\n"; foreach ( $by_type as $k => $n ) { echo "   $k: $n\n"; }
echo "— حسب الجدول.الحقل —\n"; foreach ( $by_field as $k => $n ) { echo "   $k: $n\n"; }
echo 'posts touched: ' . count( $touched ) . '  | meta rows: ' . array_sum( array_map( function ( $t ) { return count( $t['meta'] ); }, $touched ) ) . '  | options: ' . count( $opt_changes ) . "\n";
$shown = 0;
foreach ( $changes as $c ) {
	if ( $shown++ >= 60 ) { echo "… (the rest is in the CSV)\n"; break; }
	echo "[{$c[0]}] #{$c[3]} {$c[2]} {$c[6]}\n   قبل: " . mb_substr( preg_replace( '/\s+/u', ' ', $c[7] ), 0, 110 ) . "\n   بعد: " . mb_substr( preg_replace( '/\s+/u', ' ', $c[8] ), 0, 110 ) . "\n";
}
echo "CSV: $csv\n" . ( $apply ? "UNDO: $dir/undo-$stamp.json   (ZAD_UNDO=… wp eval-file zad-fix.php)\n" : "To apply: ZAD_APPLY=1 wp eval-file zad-fix.php\n" );
