<?php
/**
 * Read-only status: what the parent knows about itself, for dashboards.
 *
 * The status function answers from local state only: the parent's headers, the
 * active child, the updater's configuration and the updater's cached release
 * lookup. It never makes a network request, prints nothing and adds no admin
 * screen, notice or hook of its own.
 *
 * The theme also describes itself to an Oogle admin dashboard (Oogle Core,
 * shipped in the Oogle Manager plugin) through the `oogle_core_modules`
 * filter. That is a passive registration: the theme does not look for Core,
 * and when nothing applies the filter, nothing here runs.
 *
 * @package Oogle
 * @since   1.3.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Parent status, cache-only.
 *
 * @return array{
 *     version: string,
 *     template: string,
 *     stylesheet: string,
 *     is_child: bool,
 *     child: array{name: string, version: string, update_uri: string}|null,
 *     updates_enabled: bool,
 *     repository: string,
 *     allowed_major: int|null,
 *     cached_release: array{version: string, url: string, requires: string, requires_php: string}|null,
 *     last_error: string,
 *     signing_key_ids: string[],
 *     sodium_available: bool,
 *     token_defined: bool,
 *     reserved_child_files: string[]
 * }
 */
function oogle_status(): array {
	$repo    = oogle_update_repository();
	$cached  = null !== $repo ? get_site_transient( 'oogle_update_' . md5( $repo['owner'] . '/' . $repo['repo'] . '|' . get_template() ) ) : false;
	$release = null;
	$error   = '';
	if ( is_array( $cached ) && isset( $cached['version'] ) ) {
		$release = array(
			'version'      => (string) $cached['version'],
			'url'          => (string) ( $cached['url'] ?? '' ),
			'requires'     => (string) ( $cached['requires'] ?? '' ),
			'requires_php' => (string) ( $cached['requires_php'] ?? '' ),
		);
	} elseif ( is_array( $cached ) && isset( $cached['error'] ) ) {
		$error = (string) $cached['error'];
	}

	$child    = null;
	$reserved = array();
	if ( is_child_theme() ) {
		$theme = wp_get_theme();
		$child = array(
			'name'       => (string) $theme->get( 'Name' ),
			'version'    => (string) $theme->get( 'Version' ),
			'update_uri' => (string) $theme->get( 'UpdateURI' ),
		);
		foreach ( array( 'assets/css/base.css', 'assets/css/editor.css' ) as $path ) {
			if ( file_exists( get_stylesheet_directory() . '/' . $path ) ) {
				$reserved[] = $path;
			}
		}
	}

	$status = array(
		'version'              => OOGLE_VERSION,
		'template'             => get_template(),
		'stylesheet'           => get_stylesheet(),
		'is_child'             => null !== $child,
		'child'                => $child,
		'updates_enabled'      => oogle_updates_enabled(),
		'repository'           => null !== $repo ? $repo['owner'] . '/' . $repo['repo'] : '',
		'allowed_major'        => oogle_update_allowed_major( OOGLE_VERSION ),
		'cached_release'       => $release,
		'last_error'           => $error,
		'signing_key_ids'      => array_keys( oogle_update_signing_keys() ),
		'sodium_available'     => function_exists( 'sodium_crypto_sign_verify_detached' ),
		'token_defined'        => defined( 'OOGLE_GITHUB_TOKEN' ) && '' !== (string) OOGLE_GITHUB_TOKEN,
		'reserved_child_files' => $reserved,
	);

	/**
	 * Filter the parent's status report, e.g. to add a site's own rows.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $status Status (see oogle_status()).
	 */
	return (array) apply_filters( 'oogle/status', $status );
}

/**
 * Status rows for the Oogle dashboard: label, value, state.
 *
 * @return array<int, array{label: string, value: string, state: string}>
 */
function oogle_status_rows(): array {
	$status  = oogle_status();
	$release = $status['cached_release'];
	$rows    = array(
		array(
			'label' => __( 'Running as', 'oogle' ),
			'value' => $status['is_child']
				/* translators: 1: child theme name, 2: child theme version */
				? sprintf( __( 'Parent of %1$s %2$s', 'oogle' ), $status['child']['name'], $status['child']['version'] )
				: __( 'Active theme (no child)', 'oogle' ),
			'state' => '',
		),
		array(
			'label' => __( 'Updates', 'oogle' ),
			'value' => $status['updates_enabled']
				/* translators: 1: GitHub owner/repository, 2: major version followed */
				? sprintf( __( 'From GitHub Releases (%1$s), following %2$s', 'oogle' ), $status['repository'], null === $status['allowed_major'] ? __( 'any major', 'oogle' ) : $status['allowed_major'] . '.x' )
				: __( 'Off (deployed outside WordPress)', 'oogle' ),
			'state' => $status['updates_enabled'] ? 'ok' : '',
		),
	);
	if ( null !== $release ) {
		$rows[] = array(
			'label' => __( 'Latest checked release', 'oogle' ),
			'value' => $release['version'],
			'state' => version_compare( $release['version'], $status['version'], '>' ) ? 'warn' : 'ok',
		);
	} elseif ( '' !== $status['last_error'] ) {
		$rows[] = array(
			'label' => __( 'Last update check', 'oogle' ),
			'value' => $status['last_error'],
			'state' => 'warn',
		);
	}
	$rows[] = array(
		'label' => __( 'Release signature', 'oogle' ),
		/* translators: %s: comma-separated key ids */
		'value' => sprintf( __( 'Ed25519, trusted key %s', 'oogle' ), implode( ', ', $status['signing_key_ids'] ) ),
		'state' => $status['sodium_available'] ? 'ok' : 'error',
	);
	return $rows;
}

/**
 * Health checks for the Oogle dashboard and Site Health.
 *
 * @return array<int, array{id: string, label: string, status: string, description: string}>
 */
function oogle_status_health(): array {
	$status = oogle_status();
	$checks = array();

	if ( ! $status['sodium_available'] ) {
		$checks[] = array(
			'id'          => 'signature-verifier',
			'label'       => __( 'Theme updates cannot be verified', 'oogle' ),
			'status'      => 'critical',
			'description' => __( 'No Ed25519 implementation is available, so signed theme releases cannot be verified and will not be installed.', 'oogle' ),
		);
	}
	if ( '' !== $status['last_error'] ) {
		$checks[] = array(
			'id'          => 'update-check',
			'label'       => __( 'The last theme update check failed', 'oogle' ),
			'status'      => 'recommended',
			'description' => $status['last_error'],
		);
	}
	if ( null !== $status['cached_release'] && version_compare( $status['cached_release']['version'], $status['version'], '>' ) ) {
		$checks[] = array(
			'id'          => 'update-available',
			/* translators: %s: version */
			'label'       => sprintf( __( 'Oogle Theme %s is available', 'oogle' ), $status['cached_release']['version'] ),
			'status'      => 'recommended',
			'description' => __( 'Update from Dashboard → Updates. The download is checked against its SHA-256 and Ed25519 signature before it is installed.', 'oogle' ),
		);
	}
	if ( $status['is_child'] && 'false' !== $status['child']['update_uri'] ) {
		$checks[] = array(
			'id'          => 'child-update-uri',
			'label'       => __( 'The child theme does not declare "Update URI: false"', 'oogle' ),
			'status'      => 'recommended',
			'description' => __( 'Without it WordPress may offer a wordpress.org theme with the same directory name as an update to the child.', 'oogle' ),
		);
	}
	if ( array() !== $status['reserved_child_files'] ) {
		$checks[] = array(
			'id'          => 'reserved-child-files',
			'label'       => __( 'The child theme ships reserved stylesheet paths', 'oogle' ),
			'status'      => 'recommended',
			/* translators: %s: file paths */
			'description' => sprintf( __( '%s replace the parent\'s editor styles. Rename them (docs/EXTENSION-API.md, "Reserved child file paths").', 'oogle' ), implode( ', ', $status['reserved_child_files'] ) ),
		);
	}
	if ( array() === $checks ) {
		$checks[] = array(
			'id'          => 'ok',
			'label'       => __( 'Oogle Theme is configured correctly', 'oogle' ),
			'status'      => 'good',
			'description' => '',
		);
	}
	return $checks;
}

/**
 * Describes the theme to Oogle Core (contract 1). See Oogle Manager's
 * docs/MODULE-API.md. The theme has no admin screens of its own; Core's
 * Theme section shows these rows and checks.
 *
 * @param mixed $modules Registrations keyed by module id.
 * @return mixed
 */
function oogle_core_module( $modules ) {
	if ( ! is_array( $modules ) ) {
		return $modules;
	}
	$parent                 = wp_get_theme( get_template() );
	$modules['oogle-theme'] = array(
		'id'          => 'oogle-theme',
		'name'        => 'Oogle Theme',
		'version'     => OOGLE_VERSION,
		'contract'    => 1,
		'section'     => 'theme',
		'type'        => 'theme',
		'basename'    => get_template(),
		'description' => __( 'Parent block theme for Oogle sites; each site is a child theme.', 'oogle' ),
		'requires'    => array(
			'php' => (string) $parent->get( 'RequiresPHP' ),
			'wp'  => (string) $parent->get( 'RequiresWP' ),
		),
		'docs_url'    => 'https://github.com/neumanc/oogle-theme#readme',
		'status'      => 'oogle_status_rows',
		'health'      => 'oogle_status_health',
	);
	return $modules;
}
add_filter( 'oogle_core_modules', 'oogle_core_module' );
