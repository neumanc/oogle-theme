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
$style = "/*\nTheme Name: Oogle\nVersion: 1.9.9\nRequires at least: 7.0\nRequires PHP: 8.1\n*/";
$release = function ( array $over = array() ) {
	return array_merge(
		array(
			'tag_name'   => 'v1.9.9',
			'draft'      => false,
			'prerelease' => false,
			'html_url'   => 'https://github.com/neumanc/oogle-theme/releases/tag/v1.9.9',
			'assets'     => array(
				array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip' ),
				array( 'name' => 'oogle-theme.zip.sha256', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' ),
			),
		),
		$over
	);
};
$run = function ( $api_body, $style_body = null, ?string $installed = '1.0.0' ) use ( &$mock, &$log, $ok200, $code, $style ) {
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
	return oogle_update_fetch_release( oogle_update_repository(), $installed );
};
$is_err = fn( $r, string $c ) => is_wp_error( $r ) && $r->get_error_code() === $c;

$r = $run( $release() );
t( 'valid release → accepted', is_array( $r ) && '1.9.9' === $r['version'], is_wp_error( $r ) ? $r->get_error_message() : '' );
t( 'valid release → package is the pinned github.com asset URL', is_array( $r ) && 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip' === $r['package'] );
t( 'valid release → checksum sidecar URL recorded', is_array( $r ) && 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' === $r['checksum'] );
t( 'valid release → requires from new style.css', is_array( $r ) && '7.0' === $r['requires'] && '8.1' === $r['requires_php'] );
t( 'API request targets canonical repo neumanc/oogle-theme (release list, bounded)', 'https://api.github.com/repos/neumanc/oogle-theme/releases?per_page=30' === $log[0]['url'], $log[0]['url'] );
t( 'API request: redirection=0, timeout=10', 0 === $log[0]['redirection'] && 10 === $log[0]['timeout'], wp_json_encode( $log[0] ) );
t( 'API request: Authorization present == token configured', $log[0]['auth'] === $with_token, 'auth=' . var_export( $log[0]['auth'], true ) );
t( 'raw.githubusercontent request: NO Authorization ever', isset( $log[1] ) && str_starts_with( $log[1]['url'], 'https://raw.githubusercontent.com/' ) && false === $log[1]['auth'], wp_json_encode( $log[1] ?? null ) );
t( 'raw.githubusercontent request: redirection=0', isset( $log[1] ) && 0 === $log[1]['redirection'] );

$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'http://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip' ) ) ) ) );
t( 'package http:// → rejected', $is_err( $r, 'oogle_update_asset_url' ) );
foreach ( array(
	'arbitrary https domain'      => 'https://attacker.example/oogle-theme.zip',
	'lookalike github.com.attacker.example' => 'https://github.com.attacker.example/oogle/oogle-theme/releases/download/v1.9.9/oogle-theme.zip',
	'attacker.example/github.com/...' => 'https://attacker.example/github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip',
	'userinfo'                     => 'https://github.com@attacker.example/oogle/oogle-theme/releases/download/v1.9.9/oogle-theme.zip',
	'malformed'                    => 'https:///oogle-theme.zip',
	'objects.githubusercontent.com direct' => 'https://objects.githubusercontent.com/oogle-theme.zip',
	'wrong repo on github.com'     => 'https://github.com/attacker/oogle-theme/releases/download/v1.9.9/oogle-theme.zip',
	'former owner Oogle/oogle-theme' => 'https://github.com/Oogle/oogle-theme/releases/download/v1.9.9/oogle-theme.zip',
) as $label => $bad ) {
	$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => $bad ) ) ) ) );
	t( "package $label → rejected, no update", $is_err( $r, 'oogle_update_asset_url' ), is_wp_error( $r ) ? $r->get_error_code() : 'ACCEPTED' );
}
$r = $run( $release( array( 'assets' => array() ) ) );
t( 'missing zip asset → rejected', $is_err( $r, 'oogle_update_asset' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/theme.zip' ) ) ) ) );
t( 'wrong asset name → rejected', $is_err( $r, 'oogle_update_asset' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/other.zip' ) ) ) ) );
t( 'asset name/URL filename mismatch → rejected', $is_err( $r, 'oogle_update_asset_url' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme-1.9.9.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme-1.9.9.zip' ), array( 'name' => 'oogle-theme-1.9.9.zip.sha256', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme-1.9.9.zip.sha256' ) ) ) ) );
t( 'versioned asset name (oogle-theme-1.9.9.zip) with sidecar → accepted', is_array( $r ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip' ) ) ) ) );
t( 'zip without .sha256 sidecar → rejected (checksum mandatory)', $is_err( $r, 'oogle_update_checksum_missing' ) );
$r = $run( $release( array( 'assets' => array( array( 'name' => 'oogle-theme.zip', 'browser_download_url' => 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip' ), array( 'name' => 'oogle-theme.zip.sha256', 'browser_download_url' => 'https://attacker.example/oogle-theme.zip.sha256' ) ) ) ) );
t( 'sidecar on another host → rejected', $is_err( $r, 'oogle_update_asset_url' ) );
$r = $run( $release( array( 'tag_name' => 'latest' ) ) );
t( 'invalid semver tag → no release offered', $is_err( $r, 'oogle_update_no_release' ) );
$r = $run( $release( array( 'tag_name' => 'v1.0' ) ) );
t( 'two-part version → no release offered', $is_err( $r, 'oogle_update_no_release' ) );
$r = $run( $release( array( 'draft' => true ) ) );
t( 'draft → skipped, no release offered', $is_err( $r, 'oogle_update_no_release' ) );
$r = $run( $release( array( 'prerelease' => true ) ) );
t( 'prerelease → skipped, no release offered', $is_err( $r, 'oogle_update_no_release' ) );
$r = $run( '{not json' );
t( 'malformed JSON → rejected', $is_err( $r, 'oogle_update_malformed' ) );
$r = $run( '[]' );
t( 'empty list → no release offered', $is_err( $r, 'oogle_update_no_release' ) );
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
t( 'style.css empty → REJECTED (no requirements inherited from the installed copy)', $is_err( $r, 'oogle_update_requirements' ) );

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
t( 'valid release → core receives version/package/requires', is_array( $out ) && '1.9.9' === $out['version'] && str_starts_with( $out['package'], 'https://github.com/neumanc/oogle-theme/releases/download/' ) && '8.1' === $out['requires_php'], wp_json_encode( $out ) );
t( 'other theme slug → untouched', false === apply_filters( 'update_themes_github.com', false, array(), 'some-other-theme' ) );
delete_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() ) );

echo "\n== Requirements fail closed (nothing inherited from the installed copy) ==\n";
$css = fn( string $v, string $wp, string $php ) => "/*\nTheme Name: Oogle\nVersion: $v\n" . ( '' !== $wp ? "Requires at least: $wp\n" : '' ) . ( '' !== $php ? "Requires PHP: $php\n" : '' ) . "*/";
$r = $run( $release(), $css( '1.9.9', '7.0', '8.4' ) );
t( 'style.css 200 with valid requirements → offered with the RELEASE requirements', is_array( $r ) && '7.0' === $r['requires'] && '8.4' === $r['requires_php'] );
$mock_style = null;
$run_style = function ( $style_response ) use ( &$mock, &$log, $ok200, $release ) {
	$log  = array();
	$mock = function ( $url ) use ( $style_response, $ok200, $release ) {
		return str_starts_with( $url, 'https://api.github.com/' ) ? $ok200( wp_json_encode( $release() ) ) : $style_response;
	};
	return oogle_update_fetch_release( oogle_update_repository(), '1.0.0' );
};
$r = $run_style( new WP_Error( 'http_request_failed', 'cURL error 28' ) );
t( 'style.css request fails → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run_style( $code( 404 ) );
t( 'style.css 404 → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run_style( $code( 500 ) );
t( 'style.css 500 → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run_style( $code( 302, array( 'location' => 'https://raw.githubusercontent.com/x' ) ) );
t( 'style.css 302 → rejected (never followed)', $is_err( $r, 'oogle_update_requirements' ) && 2 === count( $log ) );
$r = $run( $release(), $css( '1.9.9', '', '' ) );
t( 'both requirement headers absent → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), $css( '1.9.9', '7.0', '' ) );
t( 'Requires PHP absent → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), $css( '1.9.9', '', '8.4' ) );
t( 'Requires at least absent → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), $css( '1.9.9', 'seven', '8.4' ) );
t( 'malformed Requires at least → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), $css( '1.9.9', '7.0', '8.4 or newer' ) );
t( 'malformed Requires PHP → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), "Theme Name: Oogle\n<html>not a stylesheet</html>" );
t( 'style.css is not a theme header → rejected', $is_err( $r, 'oogle_update_requirements' ) );
$r = $run( $release(), $css( '1.9.8', '7.0', '8.4' ) );
t( 'style.css Version differs from the tag → rejected', $is_err( $r, 'oogle_update_version_mismatch' ) );
$r = $run( $release(), $css( '1.9.9', '7.0', '9.0' ) );
t( 'future release requiring newer PHP → offered with requires_php 9.0 (core and the package gate refuse to apply it)', is_array( $r ) && '9.0' === $r['requires_php'] && ! is_php_version_compatible( $r['requires_php'] ) );
$r = $run( $release(), $css( '1.9.9', '99.0', '8.4' ) );
t( 'future release requiring newer WordPress → offered with requires 99.0 (core refuses to apply it)', is_array( $r ) && '99.0' === $r['requires'] && ! is_wp_version_compatible( $r['requires'] ) );
$r = $run( $release(), $css( '1.9.9', preg_replace( '/[^0-9.].*$/', '', get_bloginfo( 'version' ) ), PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ) );
t( 'requirements equal to this environment → compatible', is_array( $r ) && is_php_version_compatible( $r['requires_php'] ) && is_wp_version_compatible( $r['requires'] ) );
$r = $run( $release(), $css( '1.9.9', '6.0', '7.4' ) );
t( 'requirements lower than this environment → compatible', is_array( $r ) && '6.0' === $r['requires'] && '7.4' === $r['requires_php'] );
$installed_req = wp_get_theme( get_template() )->get( 'RequiresPHP' );
$r = $run( $release(), $css( '1.9.9', '7.0', '' ) );
t( 'no release value is ever substituted with the installed "Requires PHP: ' . $installed_req . '"', is_wp_error( $r ) );

echo "\n== Major-version policy (oogle/updates/allowed_major) ==\n";
$list = function ( array $tags, array $over = array() ) use ( $release ) {
	$out = array();
	foreach ( $tags as $tag ) {
		$v     = ltrim( $tag, 'v' );
		$out[] = $release( array_merge( array(
			'tag_name' => $tag,
			'html_url' => "https://github.com/neumanc/oogle-theme/releases/tag/$tag",
			'assets'   => array(
				array( 'name' => 'oogle-theme.zip', 'browser_download_url' => "https://github.com/neumanc/oogle-theme/releases/download/$tag/oogle-theme.zip" ),
				array( 'name' => 'oogle-theme.zip.sha256', 'browser_download_url' => "https://github.com/neumanc/oogle-theme/releases/download/$tag/oogle-theme.zip.sha256" ),
			),
		), $over[ $tag ] ?? array() ) );
	}
	return $out;
};
$run_list = function ( array $releases, string $installed ) use ( &$mock, &$log, $ok200 ) {
	$log  = array();
	$mock = function ( $url ) use ( $releases, $ok200 ) {
		if ( str_starts_with( $url, 'https://api.github.com/' ) ) {
			return $ok200( wp_json_encode( $releases ) );
		}
		if ( preg_match( '#/(v\d+\.\d+\.\d+)/style\.css$#', $url, $m ) ) {
			return $ok200( "/*\nTheme Name: Oogle\nVersion: " . ltrim( $m[1], 'v' ) . "\nRequires at least: 7.0\nRequires PHP: 8.4\n*/" );
		}
		return $ok200( '' );
	};
	return oogle_update_fetch_release( oogle_update_repository(), $installed );
};
$r = $run_list( $list( array( 'v1.0.1', 'v1.0.0' ) ), '1.0.0' );
t( '1.0.0 → 1.0.1 patch offered', is_array( $r ) && '1.0.1' === $r['version'], is_wp_error( $r ) ? $r->get_error_message() : '' );
$r = $run_list( $list( array( 'v1.1.0', 'v1.0.1', 'v1.0.0' ) ), '1.0.0' );
t( '1.0.0 → 1.1.0 minor offered (newest 1.x wins)', is_array( $r ) && '1.1.0' === $r['version'] );
$r = $run_list( $list( array( 'v2.0.0', 'v1.9.1', 'v1.9.0' ) ), '1.9.0' );
t( '1.9.0 with latest 2.0.0 → 2.0.0 NOT offered; newest 1.x (1.9.1) is', is_array( $r ) && '1.9.1' === $r['version'] );
$r = $run_list( $list( array( 'v2.0.0', 'v1.9.1' ) ), '1.9.1' );
t( '1.9.1 with latest 2.0.0 and no newer 1.x → stays on 1.9.1 (core files it under no_update)', is_array( $r ) && '1.9.1' === $r['version'] );
$r = $run_list( $list( array( 'v2.0.0' ) ), '1.9.1' );
t( 'only 2.x published → no release for a 1.x site', $is_err( $r, 'oogle_update_no_release' ) );
$opt = fn() => 2;
add_filter( 'oogle/updates/allowed_major', $opt );
$r = $run_list( $list( array( 'v2.0.0', 'v1.9.1' ) ), '1.9.1' );
remove_filter( 'oogle/updates/allowed_major', $opt );
t( 'explicit opt-in to major 2 → 2.0.0 offered', is_array( $r ) && '2.0.0' === $r['version'] );
$any = fn() => null;
add_filter( 'oogle/updates/allowed_major', $any );
$r = $run_list( $list( array( 'v3.0.0', 'v2.0.0', 'v1.9.1' ) ), '1.9.1' );
remove_filter( 'oogle/updates/allowed_major', $any );
t( 'allowed_major null → newest of any major (3.0.0)', is_array( $r ) && '3.0.0' === $r['version'] );
$r = $run_list( $list( array( 'v1.0.2', 'v1.0.1' ), array( 'v1.0.2' => array( 'prerelease' => true ) ) ), '1.0.0' );
t( 'prerelease 1.0.2 skipped → 1.0.1 offered', is_array( $r ) && '1.0.1' === $r['version'] );
$r = $run_list( $list( array( 'v1.0.2', 'v1.0.1' ), array( 'v1.0.2' => array( 'draft' => true ) ) ), '1.0.0' );
t( 'draft 1.0.2 skipped → 1.0.1 offered', is_array( $r ) && '1.0.1' === $r['version'] );
$r = $run_list( $list( array( 'v1.0.2-rc.1', 'v1.0.1' ) ), '1.0.0' );
t( 'published -rc tag skipped → 1.0.1 offered', is_array( $r ) && '1.0.1' === $r['version'] );
$r = $run_list( $list( array( 'v1.0.9', 'v1.0.10', 'v1.0.2' ) ), '1.0.0' );
t( 'semantic ordering (1.0.10 > 1.0.9), not list order', is_array( $r ) && '1.0.10' === $r['version'] );
$r = $run_list( $list( array( 'v1.0.1' ), array( 'v1.0.1' => array( 'assets' => array() ) ) ), '1.0.0' );
t( 'newest allowed candidate without assets → rejected, no silent fallback to an older release', $is_err( $r, 'oogle_update_asset' ) );
t( 'single request for the whole discovery', 1 === count( array_filter( $log, fn( $l ) => str_starts_with( $l['url'], 'https://api.github.com/' ) ) ) );

echo "\n== Published checksum (oogle_update_published_checksum) ==\n";
$sha = str_repeat( 'ab', 32 );
$mock = fn() => $ok200( "$sha  oogle-theme.zip\n" );
t( 'sha256 sidecar parsed', $sha === oogle_update_published_checksum( 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' ) );
$log = array();
oogle_update_published_checksum( 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' );
t( 'sidecar request carries no Authorization (never a token host)', false === $log[0]['auth'] );
$mock = fn() => $ok200( "not a digest" );
t( 'malformed sidecar → error', $is_err( oogle_update_published_checksum( 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' ), 'oogle_update_checksum_malformed' ) );
$mock = fn() => $code( 404 );
t( 'sidecar 404 → error', $is_err( oogle_update_published_checksum( 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip.sha256' ), 'oogle_update_checksum_http' ) );
t( 'sidecar off github.com → refused without a request', $is_err( oogle_update_published_checksum( 'https://attacker.example/oogle-theme.zip.sha256' ), 'oogle_update_checksum_url' ) );

echo "\n== Download guard (upgrader_pre_download) ==\n";
$key = 'oogle_update_' . md5( 'neumanc/oogle-theme|' . get_template() );
$tmp_zip = wp_tempnam( 'oogle-theme.zip' );
file_put_contents( $tmp_zip, 'zip-bytes-' . wp_generate_password( 8, false ) );
$good_sha = hash_file( 'sha256', $tmp_zip );
$package  = 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/oogle-theme.zip';
$sidecar  = $package . '.sha256';
set_site_transient( $key, array( 'version' => '1.9.9', 'url' => '', 'package' => $package, 'checksum' => $sidecar, 'requires' => '7.0', 'requires_php' => '8.4' ), HOUR_IN_SECONDS );
$serve = function ( string $digest ) use ( &$mock, $ok200, $tmp_zip, $package, $sidecar, $code ) {
	$mock = function ( $url, $args ) use ( $digest, $ok200, $tmp_zip, $package, $sidecar, $code ) {
		if ( $url === $sidecar ) {
			return $ok200( "$digest  oogle-theme.zip\n" );
		}
		if ( $url === $package ) {
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				copy( $tmp_zip, $args['filename'] );
			}
			return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => '', 'headers' => array(), 'cookies' => array(), 'filename' => $args['filename'] ?? null );
		}
		return $code( 500 );
	};
};
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
$serve( $good_sha );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'matching checksum → verified file path returned to core', is_string( $got ) && is_file( $got ) && hash_file( 'sha256', $got ) === $good_sha );
if ( is_string( $got ) ) { @unlink( $got ); }
$serve( str_repeat( '00', 32 ) );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'checksum mismatch → WP_Error, temp file removed', $is_err( $got, 'oogle_update_checksum_mismatch' ) );
$serve( $good_sha );
$got = oogle_update_pre_download( false, 'https://github.com/neumanc/oogle-theme/releases/download/v1.9.9/other.zip', $upgrader, array( 'theme' => get_template() ) );
t( 'package differs from the validated release → refused before download', $is_err( $got, 'oogle_update_package_unknown' ) );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'theme' => 'some-other-theme' ) );
t( 'another theme\'s update → untouched (false)', false === $got );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'type' => 'theme', 'action' => 'install' ) );
t( 'install/upload path (no theme context) → untouched', false === $got );
$pre = wp_tempnam( 'pre.zip' );
copy( $tmp_zip, $pre );
$got = oogle_update_pre_download( $pre, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'file supplied by an earlier hook (e.g. Oogle Core) is verified, not trusted: match → same path', $got === $pre );
file_put_contents( $pre, 'tampered' );
$got = oogle_update_pre_download( $pre, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'file supplied by an earlier hook with wrong hash → rejected', $is_err( $got, 'oogle_update_checksum_mismatch' ) && file_exists( $pre ) );
@unlink( $pre );
$mock = fn( $url ) => $url === $sidecar ? $code( 404 ) : $code( 500 );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'sidecar unavailable at install time → update refused (fail closed)', $is_err( $got, 'oogle_update_checksum_http' ) );
$err = new WP_Error( 'x', 'earlier error' );
t( 'earlier WP_Error passes through', $err === oogle_update_pre_download( $err, $package, $upgrader, array( 'theme' => get_template() ) ) );
delete_site_transient( $key );
$serve( $good_sha );
$got = oogle_update_pre_download( false, $package, $upgrader, array( 'theme' => get_template() ) );
t( 'no validated release cached (and repo 500) → refused', $is_err( $got, 'oogle_update_package_unknown' ) );
@unlink( $tmp_zip );

echo "\n== Package validation (oogle_update_validate_package) ==\n";
$installed_theme = wp_get_theme( get_template() );
$fixture = function ( array $over = array(), array $files = array() ) use ( $installed_theme ) {
	$dir = trailingslashit( get_temp_dir() ) . 'oogle-pkg-' . wp_generate_password( 8, false ) . '/';
	wp_mkdir_p( $dir . 'templates' );
	$h = array_merge( array(
		'Theme Name'        => $installed_theme->get( 'Name' ),
		'Version'           => '1.0.1',
		'Requires at least' => '7.0',
		'Requires PHP'      => '8.4',
		'Update URI'        => $installed_theme->get( 'UpdateURI' ),
	), $over );
	$css = "/*\n";
	foreach ( $h as $k => $v ) {
		if ( null !== $v ) {
			$css .= "$k: $v\n";
		}
	}
	$css .= "*/\n";
	$defaults = array(
		'style.css'            => $css,
		'theme.json'           => wp_json_encode( array( 'version' => 3, 'settings' => array( 'appearanceTools' => true ) ) ),
		'templates/index.html' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
	);
	foreach ( array_merge( $defaults, $files ) as $name => $body ) {
		if ( null === $body ) {
			@unlink( $dir . $name );
		} else {
			file_put_contents( $dir . $name, $body );
		}
	}
	return $dir;
};
$rm = function ( string $dir ) {
	foreach ( array( 'style.css', 'theme.json', 'templates/index.html' ) as $f ) { @unlink( $dir . $f ); }
	@rmdir( $dir . 'templates' ); @rmdir( $dir );
};
$check = function ( array $over = array(), array $files = array(), string $expected = '1.0.1' ) use ( $fixture, $rm ) {
	$dir = $fixture( $over, $files );
	$res = oogle_update_validate_package( $dir, $expected );
	$rm( $dir );
	return $res;
};
t( 'valid 1.0.1 package → accepted', true === $check() );
t( 'expected version unknown (empty transient) → rejected', $is_err( $check( array(), array(), '' ), 'oogle_update_package_no_expected' ) );
t( 'style.css missing → rejected', $is_err( $check( array(), array( 'style.css' => null ) ), 'oogle_update_package_no_style' ) );
t( 'other theme (Theme Name differs) → rejected', $is_err( $check( array( 'Theme Name' => 'Twenty Twenty-Five' ) ), 'oogle_update_package_identity' ) );
t( 'empty Theme Name → rejected', $is_err( $check( array( 'Theme Name' => '' ) ), 'oogle_update_package_identity' ) );
t( 'child theme package (Template header) → rejected', $is_err( $check( array( 'Template' => 'oogle-theme' ) ), 'oogle_update_package_child' ) );
t( 'Update URI pointing elsewhere → rejected', $is_err( $check( array( 'Update URI' => 'https://github.com/attacker/oogle-theme' ) ), 'oogle_update_package_uri' ) );
t( 'Update URI missing → rejected', $is_err( $check( array( 'Update URI' => null ) ), 'oogle_update_package_uri' ) );
t( 'wrong version (1.0.2 when installing 1.0.1) → rejected', $is_err( $check( array( 'Version' => '1.0.2' ) ), 'oogle_update_package_version' ) );
t( 'downgrade (0.9.0 when installing 0.9.0) → rejected', $is_err( $check( array( 'Version' => '0.9.0' ), array(), '0.9.0' ), 'oogle_update_package_downgrade' ) );
t( 'Requires PHP missing → rejected', $is_err( $check( array( 'Requires PHP' => null ) ), 'oogle_update_package_requirements' ) );
t( 'Requires at least missing → rejected', $is_err( $check( array( 'Requires at least' => null ) ), 'oogle_update_package_requirements' ) );
t( 'incompatible PHP requirement (99.0) → rejected', $is_err( $check( array( 'Requires PHP' => '99.0' ) ), 'oogle_update_package_php' ) );
t( 'incompatible WordPress requirement (99.0) → rejected', $is_err( $check( array( 'Requires at least' => '99.0' ) ), 'oogle_update_package_wp' ) );
t( 'templates/index.html missing → rejected', $is_err( $check( array(), array( 'templates/index.html' => null ) ), 'oogle_update_package_no_index' ) );
t( 'theme.json missing → rejected', $is_err( $check( array(), array( 'theme.json' => null ) ), 'oogle_update_package_no_theme_json' ) );
t( 'theme.json invalid JSON → rejected', $is_err( $check( array(), array( 'theme.json' => '{not json' ) ), 'oogle_update_package_theme_json' ) );
t( 'theme.json without version/settings → rejected', $is_err( $check( array(), array( 'theme.json' => '{"styles":{}}' ) ), 'oogle_update_package_theme_json' ) );
t( 'real working-tree package at its own version → accepted', true === oogle_update_validate_package( get_template_directory(), (string) $installed_theme->get( 'Version' ) ) );

echo "\n== Source selection gate (upgrader_source_selection) ==\n";
$upd = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
$sel = function ( string $dir, array $extra ) use ( $upd ) {
	return oogle_update_source_selection( $dir, dirname( untrailingslashit( $dir ) ) . '/', $upd, $extra );
};
set_site_transient( 'update_themes', (object) array( 'response' => array( get_template() => array( 'new_version' => '1.0.1', 'package' => 'x' ) ) ) );
$dir = $fixture( array( 'Theme Name' => 'Evil' ) );
$res = $sel( $dir, array( 'theme' => get_template() ) );
$rm( $dir );
t( 'own upgrade with a wrong package → WP_Error stops the upgrader', $is_err( $res, 'oogle_update_package_identity' ) );
$dir = $fixture();
$res = $sel( $dir, array( 'theme' => 'some-other-theme' ) );
$rm( $dir );
t( 'another theme\'s upgrade → untouched', $res === $dir );
$dir = $fixture();
$res = $sel( $dir, array( 'type' => 'theme', 'action' => 'install' ) );
$rm( $dir );
t( 'install/upload path → untouched (core check_package applies there)', $res === $dir );
delete_site_transient( 'update_themes' );
$dir = $fixture();
$res = $sel( $dir, array( 'theme' => get_template() ) );
$rm( $dir );
t( 'own upgrade without an expected version → rejected', $is_err( $res, 'oogle_update_package_no_expected' ) );
set_site_transient( 'update_themes', (object) array( 'response' => array( get_template() => array( 'new_version' => '1.0.1', 'package' => 'x' ) ) ) );
$upd->maintenance_mode( true ); // what Theme_Upgrader::current_before() does when this theme is the active stylesheet
$dir = $fixture( array( 'Theme Name' => 'Evil' ) );
$res = $sel( $dir, array( 'theme' => get_template() ) );
$rm( $dir );
t( 'rejected package switches maintenance mode off again (no 503 until the 10-minute expiry)', is_wp_error( $res ) && ! file_exists( ABSPATH . '.maintenance' ) );
if ( file_exists( ABSPATH . '.maintenance' ) ) { @unlink( ABSPATH . '.maintenance' ); }
delete_site_transient( 'update_themes' );

$pass = $GLOBALS['oogle_t']['pass']; $fail = $GLOBALS['oogle_t']['fail'];
echo "\nRESULT (" . ( $with_token ? 'token configured' : 'no token' ) . "): $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
