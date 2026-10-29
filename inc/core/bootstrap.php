<?php defined( 'ABSPATH' ) || exit;
/** Load shared functionality first, then the admin-only modules. */
require_once __DIR__ . '/site-hooks.php';
require_once __DIR__ . '/assets.php';
$zad_core_modules = require __DIR__ . '/modules.php';
foreach ( $zad_core_modules['runtime'] as $zad_core_module ) {
 require_once ZAD_THEME_DIR . $zad_core_module;
}
if ( is_admin() ) {
 foreach ( $zad_core_modules['admin'] as $zad_core_module ) {
  require_once ZAD_THEME_DIR . $zad_core_module;
 }
}
require_once __DIR__ . '/compatibility.php';
unset( $zad_core_module, $zad_core_modules );
