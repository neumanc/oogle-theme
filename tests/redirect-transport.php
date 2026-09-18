<?php
/**
 * Oogle Theme — real-transport redirect test (dev only; excluded from the release zip).
 * Starts two local PHP servers: A (127.0.0.1:18081) answers 302 → B (127.0.0.1:18082); B logs
 * whether an Authorization header arrived. Proves (a) core forwards Authorization across a
 * cross-host redirect when redirects are enabled, (b) redirection=0 never contacts the target,
 * (c) oogle_update_http_get() fails closed on a 3xx even when a filter asks for redirects.
 * Run inside a WordPress install with the theme active:  wp eval-file tests/redirect-transport.php
 */
$logfile = '/tmp/srvB.log';
@unlink( $logfile );
$srvA = '<?php header("Location: http://127.0.0.1:18082/landed", true, 302); echo "redirecting";';
$srvB = '<?php file_put_contents("/tmp/srvB.log", (isset($_SERVER["HTTP_AUTHORIZATION"]) ? "AUTH_PRESENT" : "no-auth") . "\n", FILE_APPEND); echo "landed";';
file_put_contents( '/tmp/srvA.php', $srvA );
file_put_contents( '/tmp/srvB.php', $srvB );
$pa = proc_open( 'php -S 127.0.0.1:18081 /tmp/srvA.php >/dev/null 2>&1', array(), $pipes_a );
$pb = proc_open( 'php -S 127.0.0.1:18082 /tmp/srvB.php >/dev/null 2>&1', array(), $pipes_b );
usleep( 400000 );
// Allow loopback as a redirect target for this test only (core refuses private IPs by default).
add_filter( 'http_request_host_is_external', '__return_true' );
$hits = fn() => file_exists( $logfile ) ? trim( file_get_contents( $logfile ) ) : '(B never contacted)';
$hdr  = array( 'Authorization' => 'Bearer ' . wp_generate_password( 20, false ) );

echo "\n(a) BASELINE core behaviour: wp_remote_get(A, redirection=3, Authorization set)\n";
@unlink( $logfile );
$r = wp_remote_get( 'http://127.0.0.1:18081/start', array( 'timeout' => 5, 'redirection' => 3, 'headers' => $hdr ) );
echo '    final HTTP ' . wp_remote_retrieve_response_code( $r ) . ', body=' . wp_remote_retrieve_body( $r ) . "\n    server B saw: " . $hits() . "\n";
$baseline_leaks = 'AUTH_PRESENT' === $hits();

echo "\n(b) wp_remote_get(A, redirection=0, Authorization set)\n";
@unlink( $logfile );
$r = wp_remote_get( 'http://127.0.0.1:18081/start', array( 'timeout' => 5, 'redirection' => 0, 'headers' => $hdr ) );
echo '    final HTTP ' . wp_remote_retrieve_response_code( $r ) . ', location=' . wp_remote_retrieve_header( $r, 'location' ) . "\n    server B saw: " . $hits() . "\n";
$zero_safe = '(B never contacted)' === $hits() && 302 === (int) wp_remote_retrieve_response_code( $r );

echo "\n(c) oogle_update_http_get() pointed at A (via oogle/updates/api_url), request_args filter tries redirection=5\n";
@unlink( $logfile );
add_filter( 'oogle/updates/request_args', function ( $a ) { $a['redirection'] = 5; return $a; } );
$res = oogle_update_http_get( 'http://127.0.0.1:18081/start', 'application/json' );
echo '    result: ' . ( is_wp_error( $res ) ? 'WP_Error ' . $res->get_error_code() . ' — ' . $res->get_error_message() : 'BODY RETURNED' ) . "\n    server B saw: " . $hits() . "\n";
$updater_unauth_note = $hits();

proc_terminate( $pa ); proc_terminate( $pb );
echo "\nSUMMARY\n";
echo '  core forwards Authorization across a cross-host redirect when redirects are enabled: ' . ( $baseline_leaks ? 'YES (risk is real)' : 'no' ) . "\n";
echo '  redirection=0 on the real transport: 3xx returned as-is, redirect target never contacted: ' . ( $zero_safe ? 'YES' : 'NO' ) . "\n";
echo '  updater on real transport: ' . ( is_wp_error( $res ) ? 'fails closed on 3xx' : 'FOLLOWED REDIRECT' ) . "; B contacted: " . $updater_unauth_note . "\n";
exit( ( $zero_safe && is_wp_error( $res ) && '(B never contacted)' === $updater_unauth_note ) ? 0 : 1 );
