<?php defined( 'ABSPATH' ) || exit;
/**
 * WhatsApp Business API channel for the reminders engine — a LOCKED skeleton. Nothing is sent unless ALL of these hold:
 *   1. «تفعيل» is ticked AND approved in أدوات زاد ← WhatsApp Business API (flagged: it is a decision with a cost and legal consequences);
 *   2. a provider is registered with the filter `zt_waba_providers` (no provider ships here) and its credential constant is defined in wp-config.php (never in the database);
 *   3. the reminder's tool has an approved template row whose variables include {رابط_الإيقاف} (every message carries the unsubscribe link);
 *   4. the reminder row has an explicit consent time (a transactional row without consent needs «السماح بالطلبات الخدمية» ticked and approved).
 * Anything else stays pending in «تذكيرات اليوم» with the ready wa.me button, exactly as before.
 */

interface ZT_WABA_Provider {
	public function id();
	public function label();
	/** Name of the constant in wp-config.php that holds the credential (e.g. ZAD_WABA_TOKEN). */
	public function key_constant();
	/** @param string $phone digits, international (9665…)  @param string $template approved template name  @param string $lang  @param array $vars ordered body variables  @return true|WP_Error */
	public function send_template( $phone, $template, $lang, $vars );
}

function zt_waba_providers() { $o = array(); foreach ( (array) apply_filters( 'zt_waba_providers', array() ) as $p ) { if ( $p instanceof ZT_WABA_Provider ) { $o[ $p->id() ] = $p; } } return $o; }

add_action( 'zad_tools_register_settings', function () {
	$ps = array( '' => '— لا يوجد مزود مسجّل —' ); foreach ( zt_waba_providers() as $id => $p ) { $ps[ $id ] = $p->label(); }
	zt_register_settings( 'waba', 'WhatsApp Business API (مقفول)', array(
		array( 'key' => 'enabled', 'label' => 'تفعيل الإرسال الآلي عبر WhatsApp Business API', 'type' => 'checkbox', 'default' => 0, 'approval' => true, 'source' => 'قرار منك بعد اختبار المزود وقوالبه المعتمدة من واتساب (له تكلفة وشروط)' ),
		array( 'key' => 'provider', 'label' => 'المزود', 'type' => 'select', 'options' => $ps, 'default' => '', 'required' => false, 'desc' => 'المزودون يُسجَّلون برمجياً (فلتر zt_waba_providers)؛ مفتاحه يوضع كثابت في wp-config.php ولا يُحفظ هنا.' ),
		array( 'key' => 'templates', 'label' => 'القوالب المعتمدة: الأداة | اسم القالب | اللغة | المتغيرات بالترتيب', 'type' => 'table', 'default' => '', 'required' => false,
			'desc' => 'الأدوات: <code>tank plan orders-warranty orders-review</code>. المتغيرات المتاحة: <code>{الاسم} {الخدمة} {المنشأة} {الحي} {رابط_الإيقاف}</code> مفصولة بفاصلة؛ <strong>يجب أن تتضمن {رابط_الإيقاف}</strong> وإلا يُتجاهل الصف. مثال للتنسيق فقط: <code>tank | اسم_القالب | ar | {الاسم},{الخدمة},{رابط_الإيقاف}</code>' ),
		array( 'key' => 'allow_transactional', 'label' => 'السماح بالطلبات الخدمية بلا موافقة تسويقية (طلب التقييم)', 'type' => 'checkbox', 'default' => 0, 'approval' => true, 'source' => 'قرار قانوني منك — الافتراضي: لا' ),
		array( 'key' => 'max_per_run', 'label' => 'أقصى رسائل في التشغيل اليومي الواحد', 'type' => 'number', 'default' => 100, 'min' => 1, 'max' => 5000, 'approval' => true, 'source' => 'حد أمان للتكلفة من عندي — يحتاج اعتماداً' ),
	) );
} );

/** Valid template rows: array( tool => array( template, lang, vars[] ) ). */
function zt_waba_templates() {
	$out = array();
	foreach ( zt_table( zt_opt( 'waba.templates' ) ) as $r ) {
		if ( count( $r ) < 4 ) { continue; }
		$vars = array_values( array_filter( array_map( 'trim', explode( ',', str_replace( '،', ',', implode( '|', array_slice( $r, 3 ) ) ) ) ), 'strlen' ) );
		if ( '' === $r[0] || '' === $r[1] || ! in_array( '{رابط_الإيقاف}', $vars, true ) ) { continue; }
		$out[ preg_replace( '/[^a-z0-9\-]/', '', strtolower( $r[0] ) ) ] = array( $r[1], '' !== $r[2] ? $r[2] : 'ar', $vars );
	}
	return $out;
}
function zt_waba_provider() { $ps = zt_waba_providers(); $id = (string) zt_opt( 'waba.provider' ); return $ps[ $id ] ?? null; }
function zt_waba_credential_ok( $p ) { $c = $p->key_constant(); return '' !== $c && defined( $c ) && '' !== (string) constant( $c ); }

class ZT_Channel_WABA implements ZT_Reminder_Channel {
	public function id() { return 'waba'; }
	public function label() { return 'WhatsApp Business API'; }
	public function enabled() {
		if ( ! zt_opt( 'waba.enabled' ) || ! zt_tool_ready( 'waba' ) ) { return false; }
		$p = zt_waba_provider();
		return $p && zt_waba_credential_ok( $p ) && (bool) zt_waba_templates();
	}
	public function send( $r, $m ) {
		$p = zt_waba_provider(); $tpl = zt_waba_templates()[ $r->source_tool ?? '' ] ?? null;
		if ( ! $p || ! $tpl ) { return new WP_Error( 'no_template', 'لا قالب معتمد لهذه الأداة؛ يبقى في القائمة اليدوية.' ); }
		if ( empty( $r->consent_at ) && ! zt_opt( 'waba.allow_transactional' ) ) { return new WP_Error( 'consent', 'لا موافقة صريحة على هذا الصف.' ); }
		$map = array( '{الاسم}' => $r->first_name, '{الخدمة}' => $r->service, '{المنشأة}' => zt_brand(), '{الحي}' => $r->hood, '{رابط_الإيقاف}' => zt_unsub_url( $r->token ) );
		$vars = array(); foreach ( $tpl[2] as $v ) { $vars[] = (string) ( $map[ $v ] ?? '' ); }
		$res = $p->send_template( zt_intl_phone( $r->phone ), $tpl[0], $tpl[1], $vars );
		return true === $res ? true : new WP_Error( 'send', 'تعذر الإرسال عبر المزود.' ); // the provider's raw error is never shown or stored here
	}
}
