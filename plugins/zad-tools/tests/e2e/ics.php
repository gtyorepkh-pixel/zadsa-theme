<?php
require __DIR__ . '/../stub.php'; require ZT_DIR . 'includes/core.php'; require ZT_DIR . 'includes/ui.php';
parse_str( $argv[1] ?? '', $q );
$events = isset( $q['ev'] ) ? zt_ics_parse_ev( $q['ev'] ) : array( array( $q['d'] ?? '', $q['t'] ?? '' ) );
echo zt_ics_build_multi( $events, 'زاد', '20261009T100000Z', 'zadksa.com' );
