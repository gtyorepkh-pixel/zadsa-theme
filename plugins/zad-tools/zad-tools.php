<?php
/**
 * Plugin Name: Zad Tools — أدوات زاد
 * Description: بنية أدوات زاد التفاعلية: صفحة إعدادات واحدة، محرك التذكيرات، قالب صفحة الأداة الموحد، REST آمن، وقياس GA4. كل البيانات والإعدادات هنا (لا تضيع لو تغيّر الثيم).
 * Version: 0.1.0
 * Author: Zad
 * Text Domain: zad-tools
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;

define( 'ZT_VERSION', '0.1.0' );
define( 'ZT_FILE', __FILE__ );
define( 'ZT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZT_URL', plugin_dir_url( __FILE__ ) );

require_once ZT_DIR . 'includes/core.php';
require_once ZT_DIR . 'includes/settings.php';
require_once ZT_DIR . 'includes/reminders.php';
require_once ZT_DIR . 'includes/rest.php';
require_once ZT_DIR . 'includes/tool-page.php';

register_activation_hook( __FILE__, function () {
	zt_reminders_install();
	if ( ! wp_next_scheduled( 'zad_tools_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zad_tools_daily' );
	}
	update_option( 'zad_tools_version', ZT_VERSION, false );
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'zad_tools_daily' );
} );
