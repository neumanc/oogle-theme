<?php
/**
 * Oogle Theme — updater security tests (dev only; excluded from the release zip).
 *
 * Run inside a WordPress install that has the theme ACTIVE, e.g.:
 *   wp eval-file tests/updater-security.php            # without a token
 *   wp eval-file tests/updater-security.php with-token # with OOGLE_GITHUB_TOKEN defined
 * All HTTP is short-circuited at pre_http_request; nothing leaves the host.
 * Never prints the token value; only whether an Authorization header was present.
 */

$with_token = in_array( 'with-token', $args ?? array(), true );
if ( $with_token ) {
	define( 'OOGLE_GITHUB_TOKEN', 'test-token-' . wp_generate_password( 24, false ) );
}

$GLOBALS['oogle_t'] = array( 'pass' => 0, 'fail' => 0 );
function t( string $name, bool $ok, string $detail = '' ): void {
	$GLOBALS['oogle_t'][ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $detail ? "  [$detail]" : '' ) . "\n";
}

$repo = oogle_update_repository();
t( 'repo parsed from Update URI', $repo === array( 'owner' => 'neumanc', 'repo' => 'oogle-theme' ), wp_json_encode( $repo ) );

echo "\n== URL host parsing (oogle_update_url_host) ==\n";
$host_cases = array(
	'https://api.github.com/repos/oogle/oogle-theme/releases/latest' => 'api.github.com',
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => 'github.com',
	'https://GitHub.com/x' => 'github.com',
	'http://github.com/x' => null,
	'https://attacker.example/x' => 'attacker.example', // parses; trust is decided by exact comparison (checked below)
	'https://github.com.attacker.example/x' => 'github.com.attacker.example',
	'https://attacker.example/github.com/neumanc/oogle-theme/releases/download/v1/oogle-theme.zip' => 'attacker.example',
	'https://user:pass@github.com/x' => null,
	'https://github.com@attacker.example/x' => null,
	'https://github.com:443/x' => null,
	'https://github.com:8443/x' => null,
	'//github.com/x' => null,
	'github.com/x' => null,
	'ftp://github.com/x' => null,
	'https:///x' => null,
	'https://' => null,
	'' => null,
	"https://github.com\x00.attacker.example/x" => null,
	'https://127.0.0.1/x' => null,
	'https://[::1]/x' => null,
	'https://github_com/x' => null,
	'https://localhost/x' => null,
	'https://10.0.0.1/x' => null,
	'https://github.com./x' => null,
	'javascript:alert(1)' => null,
	'https://github.com/x#@attacker.example' => 'github.com',
);
foreach ( $host_cases as $url => $expect ) {
	$got = oogle_update_url_host( $url );
	t( 'host(' . str_replace( "\x00", '\0', $url ) . ')', $got === $expect, 'got ' . var_export( $got, true ) );
}

echo "\n== Token host allowlist ==\n";
t( 'allowlist is exactly api.github.com', oogle_update_token_hosts() === array( 'api.github.com' ) );
foreach ( array( 'github.com', 'github.com.attacker.example', 'attacker.example', 'raw.githubusercontent.com', 'objects.githubusercontent.com', 'api.github.com.attacker.example', 'xapi.github.com' ) as $h ) {
	t( "not a token host: $h", ! in_array( $h, oogle_update_token_hosts(), true ) );
}

echo "\n== Package URL validation (oogle_update_is_release_asset_url) ==\n";
$asset = 'oogle-theme.zip';
$pkg_cases = array(
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => true,
	'https://github.com/NeumanC/Oogle-Theme/releases/download/v1.0.0/oogle-theme.zip' => true, // owner/repo case-insensitive
	'https://GITHUB.COM/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => true, // host case-insensitive
	'http://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://attacker.example/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://github.com.attacker.example/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://attacker.example/github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://user:pw@github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://github.com@attacker.example/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://github.com:443/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://objects.githubusercontent.com/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://raw.githubusercontent.com/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false,
	'https://github.com/other/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false, // wrong owner
	'https://github.com/Oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false, // former (never owned) owner
	'https://github.com/oogle/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false, // former owner, lower-case
	'https://github.com/neumanc-attacker/oogle-theme/releases/download/v1.0.0/oogle-theme.zip' => false, // owner prefix
	'https://github.com/neumanc/oogle-theme-evil/releases/download/v1.0.0/oogle-theme.zip' => false, // repo prefix
	'https://github.com/neumanc/other-repo/releases/download/v1.0.0/oogle-theme.zip' => false, // wrong repo
	'https://github.com/neumanc/oogle-theme/archive/refs/tags/v1.0.0.zip' => false, // auto zipball
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/evil.zip' => false, // asset name mismatch
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip?x=1' => false,
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip#f' => false,
	'https://github.com/neumanc/oogle-theme/releases/download//oogle-theme.zip' => false, // empty tag
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/x/oogle-theme.zip' => false,
	'https://github.com/neumanc/oogle-theme/oogle-theme.zip' => false,
	'https://github.com/' => false,
	'https://github.com' => false,
	'not a url' => false,
	'' => false,
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip/../../../../evil.zip' => false,
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme%2Ezip' => true, // decodes to exact name
	'https://github.com/neumanc/oogle-theme/releases/download/v1.0.0/oogle-theme.zip%00' => false,
);
foreach ( $pkg_cases as $url => $expect ) {
	$got = oogle_update_is_release_asset_url( $url, $repo, $asset );
	t( 'pkg(' . $url . ')', $got === $expect, 'got ' . var_export( $got, true ) );
}

echo "\n== Mocked API scenarios (pre_http_request; records URL, Authorization presence, redirection) ==\n";
$log = array();
$mock = null; // callable( string $url, array $args ): array|WP_Error
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) use ( &$log, &$mock ) {
		$log[] = array(
			'url'         => $url,
			'auth'        => isset( $args['headers']['Authorization'] ),
			'redirection' => $args['redirection'] ?? 'unset',
			'timeout'     => $args['timeout'] ?? 'unset',
		);
		return $mock ? $mock( $url, $args ) : new WP_Error( 'test_no_mock', 'no mock' );
	},
	10,
	3
);
$ok200 = fn( string $body ) => array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => $body, 'headers' => array(), 'cookies' => array(), 'filename' => null );
$code  = fn( int $c, array $h = array() ) => array( 'response' => array( 'code' => $c, 'message' => '' ), 'body' => '', 'headers' => $h, 'cookies' => array(), 'filename' => null );
$style = "/*\nTheme Name: Oogle\nVersion: 9.9.9\nRequires at least: 7.0\nRequires PHP: 8.1\n*/";
$release = function ( array $over = array() ) {
	return array_merge(
		array(
			'tag_name'   => 'v9.9.9',
			'draft'      => false,
			'prerelease' => false,
			'html_url'   => 'https://github.com/neumanc/oogle-theme/releases/tag/v9.9.9',
			'assets'     => array(
				array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme.zip' ),
				array( 'name' => 'oogle-theme.zip.sha256', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme.zip.sha256' ),
			),
		),
		$over
	);
};
$run = function ( $api_body, $style_body = null ) use ( &$mock, &$log, $ok200, $code, $style ) {
	$log  = array();
	$mock = function ( $url ) use ( $api_body, $style_body, $ok200, $code, $style ) {
		if ( str_starts_with( $url, 'https://api.github.com/' ) ) {
			if ( is_wp_error( $api_body ) || ( is_array( $api_body ) && isset( $api_body['response'] ) ) ) {
				return $api_body; // Raw HTTP response / transport error.
			}
			return $ok200( is_array( $api_body ) ? wp_json_encode( $api_body ) : (string) $api_body );
		}
		if ( str_starts_with( $url, 'https://raw.githubusercontent.com/' ) ) {
			return $ok200( $style_body ?? $style );
		}
		return $code( 500 );
	};
	return oogle_update_fetch_release( oogle_update_repository() );
};
$is_err = fn( $r, string $c ) => is_wp_error( $r ) && $r->get_error_code() === $c;

$r = $run( $release() );
t( 'valid release → accepted', is_array( $r ) && '9.9.9' === $r['version'], is_wp_error( $r ) ? $r->get_error_message() : '' );
t( 'valid release → package is the pinned github.com asset URL', is_array( $r ) && 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme.zip' === $r['package'] );
t( 'valid release → requires from new style.css', is_array( $r ) && '7.0' === $r['requires'] && '8.1' === $r['requires_php'] );
t( 'API request targets canonical repo neumanc/oogle-theme', 'https://api.github.com/repos/neumanc/oogle-theme/releases/latest' === $log[0]['url'], $log[0]['url'] );
t( 'API request: redirection=0, timeout=10', 0 === $log[0]['redirection'] && 10 === $log[0]['timeout'], wp_json_encode( $log[0] ) );
t( 'API request: Authorization present == token configured', $log[0]['auth'] === $with_token, 'auth=' . var_export( $log[0]['auth'], true ) );
t( 'raw.githubusercontent request: NO Authorization ever', isset( $log[1] ) && str_starts_with( $log[1]['url'], 'https://raw.githubusercontent.com/' ) && false === $log[1]['auth'], wp_json_encode( $log[1] ?? null ) );
t( 'raw.githubusercontent request: redirection=0', isset( $log[1] ) && 0 === $log[1]['redirection'] );

$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'http://github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme.zip' ) ) ) ) );
t( 'package http:// → rejected', $is_err( $r, 'oogle_update_asset_url' ) );
foreach ( array(
	'arbitrary https domain'      => 'https://attacker.example/oogle-theme.zip',
	'lookalike github.com.attacker.example' => 'https://github.com.attacker.example/oogle/oogle-theme/releases/download/v9.9.9/oogle-theme.zip',
	'attacker.example/github.com/...' => 'https://attacker.example/github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme.zip',
	'userinfo'                     => 'https://github.com@attacker.example/oogle/oogle-theme/releases/download/v9.9.9/oogle-theme.zip',
	'malformed'                    => 'https:///oogle-theme.zip',
	'objects.githubusercontent.com direct' => 'https://objects.githubusercontent.com/oogle-theme.zip',
	'wrong repo on github.com'     => 'https://github.com/attacker/oogle-theme/releases/download/v9.9.9/oogle-theme.zip',
	'former owner Oogle/oogle-theme' => 'https://github.com/Oogle/oogle-theme/releases/download/v9.9.9/oogle-theme.zip',
) as $label => $bad ) {
	$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => $bad ) ) ) ) );
	t( "package $label → rejected, no update", $is_err( $r, 'oogle_update_asset_url' ), is_wp_error( $r ) ? $r->get_error_code() : 'ACCEPTED' );
}
$r = $run( $release( array( 'assets' => array() ) ) );
t( 'missing zip asset → rejected', $is_err( $r, 'oogle_update_asset' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/theme.zip' ) ) ) ) );
t( 'wrong asset name → rejected', $is_err( $r, 'oogle_update_asset' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/other.zip' ) ) ) ) );
t( 'asset name/URL filename mismatch → rejected', $is_err( $r, 'oogle_update_asset_url' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme-9.9.9.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v9.9.9/oogle-theme-9.9.9.zip' ) ) ) ) );
t( 'versioned asset name (oogle-theme-9.9.9.zip) → accepted', is_array( $r ) );
$r = $run( $release( array( 'tag_name' => 'latest' ) ) );
t( 'invalid semver tag → rejected', $is_err( $r, 'oogle_update_version' ) );
$r = $run( $release( array( 'tag_name' => 'v1.0' ) ) );
t( 'two-part version → rejected', $is_err( $r, 'oogle_update_version' ) );
$r = $run( $release( array( 'draft' => true ) ) );
t( 'draft → rejected', $is_err( $r, 'oogle_update_prerelease' ) );
$r = $run( $release( array( 'prerelease' => true ) ) );
t( 'prerelease → rejected', $is_err( $r, 'oogle_update_prerelease' ) );
$r = $run( '{not json' );
t( 'malformed JSON → rejected', $is_err( $r, 'oogle_update_malformed' ) );
$r = $run( '[]' );
t( 'empty JSON → rejected', $is_err( $r, 'oogle_update_malformed' ) );
$r = $run( $release( array( 'html_url' => 'https://attacker.example/phish' ) ) );
t( 'html_url off github.com → blanked, release still valid', is_array( $r ) && '' === $r['url'] );
$r = $run( $code( 404 ) );
t( 'missing release (404) → rejected', $is_err( $r, 'oogle_update_http' ) );
$r = $run( $code( 302, array( 'location' => 'https://attacker.example/' ) ) );
t( 'API 302 → rejected (never followed)', $is_err( $r, 'oogle_update_http' ) && 1 === count( $log ), 'requests made: ' . count( $log ) );
$r = $run( $code( 301, array( 'location' => 'https://api.github.com/repos/x/y/releases/latest' ) ) );
t( 'API 301 even to github → rejected (never followed)', $is_err( $r, 'oogle_update_http' ) && 1 === count( $log ) );
$r = $run( new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ) );
t( 'API timeout/failure → rejected', is_wp_error( $r ) && 'http_request_failed' === $r->get_error_code() );
$r = $run( $release(), '' );
t( 'style.css fetch empty → falls back to installed headers, still valid', is_array( $r ) && '' !== $r['requires_php'] );

echo "\n== Filtered URLs must not receive the token ==\n";
foreach ( array(
	'http://api.github.com/x'                => 'http scheme',
	'https://attacker.example/x'             => 'arbitrary https',
	'https://api.github.com.attacker.example/x' => 'lookalike',
	'https://attacker.example/api.github.com/x' => 'host in path',
	'https://token@api.github.com/x'         => 'userinfo',
	'https://api.github.com:443/x'           => 'explicit port',
	'https://raw.githubusercontent.com/x'    => 'raw host (public files)',
) as $u => $label ) {
	$log  = array();
	$mock = fn() => $code( 500 );
	oogle_update_http_get( $u, 'text/plain' );
	t( "no Authorization to $label", 1 === count( $log ) && false === $log[0]['auth'] && 0 === $log[0]['redirection'], wp_json_encode( $log[0] ?? null ) );
}
$log  = array();
$mock = fn() => $code( 500 );
oogle_update_http_get( 'https://api.github.com/repos/neumanc/oogle-theme/releases/latest', 'application/json' );
t( 'Authorization to api.github.com == token configured', $log[0]['auth'] === $with_token );

echo "\n== request_args filter cannot re-enable redirects on an authenticated request ==\n";
$f = function ( $a ) { $a['redirection'] = 5; return $a; };
add_filter( 'oogle/updates/request_args', $f );
$log  = array();
oogle_update_http_get( 'https://api.github.com/repos/neumanc/oogle-theme/releases/latest', 'application/json' );
remove_filter( 'oogle/updates/request_args', $f );
t( 'filter set redirection=5; effective value is always 0', 0 === $log[0]['redirection'] && $log[0]['auth'] === $with_token, wp_json_encode( $log[0] ) );

echo "\n== Filtered api_url / style_url off-host: no token ==\n";
$g = fn() => 'https://attacker.example/releases/latest';
add_filter( 'oogle/updates/api_url', $g );
$log = array(); $mock = fn() => $code( 500 );
oogle_update_fetch_release( $repo );
remove_filter( 'oogle/updates/api_url', $g );
t( 'api_url filtered to attacker.example → request made without Authorization', 1 === count( $log ) && false === $log[0]['auth'], wp_json_encode( $log[0] ?? null ) );

echo "\n== End-to-end through core filter + failure caching (benign) ==\n";
delete_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() ) );
$log = array(); $mock = fn() => $code( 404 );
$theme = wp_get_theme( get_template() );
$out = apply_filters( 'update_themes_github.com', false, $theme->get( 'Name' ) ? array( 'Version' => $theme->get( 'Version' ) ) : array(), get_template() );
t( 'repo 404 → update_themes filter returns false (no update offered)', false === $out );
$cached = get_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() ) );
t( 'failure cached as {error} (1 h) so host is not hammered', is_array( $cached ) && isset( $cached['error'] ) );
$n = count( $log );
apply_filters( 'update_themes_github.com', false, array(), get_template() );
t( 'second check within cache window makes no HTTP request', count( $log ) === $n );
delete_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() ) );
$log = array(); $mock = function ( $url ) use ( $ok200, $release, $style ) {
	return str_starts_with( $url, 'https://api.github.com/' ) ? $ok200( wp_json_encode( $release() ) ) : $ok200( $style );
};
$out = apply_filters( 'update_themes_github.com', false, array(), get_template() );
t( 'valid release → core receives version/package/requires', is_array( $out ) && '9.9.9' === $out['version'] && str_starts_with( $out['package'], 'https://github.com/neumanc/oogle-theme/releases/download/' ) && '8.1' === $out['requires_php'], wp_json_encode( $out ) );
t( 'other theme slug → untouched', false === apply_filters( 'update_themes_github.com', false, array(), 'some-other-theme' ) );
delete_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() ) );

$pass = $GLOBALS['oogle_t']['pass']; $fail = $GLOBALS['oogle_t']['fail'];
echo "\nRESULT (" . ( $with_token ? 'token configured' : 'no token' ) . "): $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
