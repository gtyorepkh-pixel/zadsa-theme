#!/bin/sh
# Builds a throw-away real WordPress 6.5 (SQLite) in $WPX (default /tmp/wpsite) to test the theme's DB tools end to end. Needs npm access (the WP core + SQLite
# plugin come from the @wp-playground/wordpress-builds package). Usage: tools/wptest/setup.sh ; then php -S 127.0.0.1:8099 -t $WPX $WPX/router.php
set -e
WPX=${WPX:-/tmp/wpsite}; REPO=$(cd "$(dirname "$0")/../.." && pwd); T=$(mktemp -d)
cd "$T" && npm pack @wp-playground/wordpress-builds >/dev/null 2>&1
tar xzf wp-playground-wordpress-builds-*.tgz package/src/wordpress/wp-6.5.zip package/src/sqlite-database-integration/sqlite-database-integration.zip
mkdir -p "$WPX" && cd "$WPX" && unzip -q -o "$T/package/src/wordpress/wp-6.5.zip" && unzip -q -o "$T/package/src/sqlite-database-integration/sqlite-database-integration.zip" -d wp-content/plugins/
P=wp-content/plugins/sqlite-database-integration-main
sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WPX/$P#g; s#{SQLITE_PLUGIN}#sqlite-database-integration-main/load.php#g" $P/db.copy > wp-content/db.php
mkdir -p wp-content/database wp-content/mu-plugins; rm -f wp-content/database/.ht.sqlite
cp "$REPO"/mu-plugins/*.php "$REPO"/tools/mu-plugins/zad-core-cpt.php wp-content/mu-plugins/
cp "$REPO"/tools/wptest/redirection-standin.php wp-content/mu-plugins/ ; cp "$REPO"/tools/wptest/router.php "$WPX"/router.php
ln -sfn "$REPO" wp-content/themes/zadsa-theme
cat > wp-config.php <<'PHP'
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $k ) { define( $k, 'x' ); }
$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_DEBUG_LOG', __DIR__ . '/debug.log' );
define( 'WP_HOME', getenv( 'WPX_URL' ) ?: 'http://127.0.0.1:8099' ); define( 'WP_SITEURL', WP_HOME ); define( 'DISALLOW_FILE_MODS', true );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP
php -r 'define("WP_INSTALLING",true); require "wp-load.php"; require_once ABSPATH."wp-admin/includes/upgrade.php"; wp_install("Zad Test","admin","a@example.com",true,"","pass1234","ar"); switch_theme("zadsa-theme"); global $wp_rewrite; $wp_rewrite->set_permalink_structure("/%postname%/"); update_option("zt_pests_rules",0);'
echo "ready: $WPX"
