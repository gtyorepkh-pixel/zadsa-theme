<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * @package   Codestar Framework - WordPress Options Framework
 * @author    Codestar <info@codestarthemes.com>
 * @copyright 2015-2022 Codestar
 *
 *
 *
 */
require_once plugin_dir_path( __FILE__ ) .'classes/setup.class.php';

require_once plugin_dir_path( __FILE__ ) .'options/theme-options.php';
require_once plugin_dir_path( __FILE__ ) .'options/shortcode-options.php';
require_once plugin_dir_path( __FILE__ ) .'options/metabox-options.php';
require_once get_template_directory() . '/inc/zad-options.php';
