<?php
require __DIR__ . '/../stub.php'; require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php';
parse_str( $argv[1] ?? '', $q ); echo zt_ics_build( $q['d'] ?? '', $q['t'] ?? '', 'زاد', '20261009T100000Z', 'zadksa.com' );
