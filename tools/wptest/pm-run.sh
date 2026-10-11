#!/bin/sh
# php fixtures with the OLD mu-plugin, then the real test with the NEW one (pests-library no longer registered by the mu-plugin; the theme's safety net registers it while rows exist)
set -e
R=$(cd "$(dirname "$0")/../.." && pwd); W=${WPX:-/tmp/wpsite}
git -C "$R" show 748ffb3:tools/mu-plugins/zad-core-cpt.php > "$W/wp-content/mu-plugins/zad-core-cpt.php"
php "$R/tools/wptest/pm-fixtures.php"
cp "$R/tools/mu-plugins/zad-core-cpt.php" "$W/wp-content/mu-plugins/zad-core-cpt.php"
php "$R/tools/wptest/pm-test.php"
