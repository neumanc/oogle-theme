<?php
/**
 * Oogle Theme — editor allow-list composition tests (dev only; excluded from the release zip).
 *
 * Runs the real `allowed_block_types_all` filter chain with constructed editor contexts.
 * Run inside a WordPress install with the theme active:  wp eval-file tests/editor-allowlist.php
 */
$GLOBALS['oogle_t'] = array( 'pass' => 0, 'fail' => 0 );
function t( string $name, bool $ok, string $detail = '' ): void {
	$GLOBALS['oogle_t'][ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $detail ? "  [$detail]" : '' ) . "\n";
}

register_post_type( 'oogle_test_project', array( 'public' => true, 'show_in_rest' => true, 'label' => 'Project (test)' ) );
register_post_type( 'oogle_test_internal', array( 'public' => false, 'show_in_rest' => false, 'show_ui' => true, 'label' => 'Internal (test)' ) );
$post_id  = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'allow-list test', 'post_status' => 'draft' ) );
$page_id  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'allow-list test', 'post_status' => 'draft' ) );
$proj_id  = wp_insert_post( array( 'post_type' => 'oogle_test_project', 'post_title' => 'allow-list test', 'post_status' => 'draft' ) );
$int_id   = wp_insert_post( array( 'post_type' => 'oogle_test_internal', 'post_title' => 'allow-list test', 'post_status' => 'draft' ) );
$tpl_id   = wp_insert_post( array( 'post_type' => 'wp_template', 'post_title' => 'allow-list test', 'post_status' => 'publish', 'post_name' => 'oogle-test-tpl' ) );
$part_id  = wp_insert_post( array( 'post_type' => 'wp_template_part', 'post_title' => 'allow-list test', 'post_status' => 'publish', 'post_name' => 'oogle-test-part' ) );
$block_id = wp_insert_post( array( 'post_type' => 'wp_block', 'post_title' => 'allow-list test', 'post_status' => 'publish' ) );
$nav_id   = wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_title' => 'allow-list test', 'post_status' => 'publish' ) );

$ctx  = fn( int $id, string $name = 'core/edit-post' ) => new WP_Block_Editor_Context( array( 'post' => get_post( $id ), 'name' => $name ) );
$site = new WP_Block_Editor_Context( array( 'name' => 'core/edit-site' ) );
$run  = fn( $incoming, WP_Block_Editor_Context $c ) => apply_filters( 'allowed_block_types_all', $incoming, $c );

echo "== incoming values ==\n";
$r = $run( true, $ctx( $post_id ) );
t( 'post editor, incoming true → theme list (array with core/paragraph, without core/archives)', is_array( $r ) && in_array( 'core/paragraph', $r, true ) && ! in_array( 'core/archives', $r, true ), is_array( $r ) ? count( $r ) . ' blocks' : var_export( $r, true ) );
$r = $run( null, $ctx( $post_id ) );
t( 'post editor, incoming null → theme list', is_array( $r ) && in_array( 'core/paragraph', $r, true ) );
$r = $run( false, $ctx( $post_id ) );
t( 'post editor, incoming false → stays false (never broadened)', false === $r );
$r = $run( array( 'core/paragraph', 'core/archives', 'acme/form' ), $ctx( $post_id ) );
t( 'post editor, incoming array → intersection (paragraph kept; archives and acme/form not added by theme)', $r === array( 'core/paragraph' ), wp_json_encode( $r ) );
$r = $run( array( 'core/paragraph', 'core/heading' ), $ctx( $post_id ) );
t( 'incoming array never widened to the theme list', is_array( $r ) && 2 === count( $r ) && ! in_array( 'core/group', $r, true ) );

echo "== contexts ==\n";
$r = $run( true, $ctx( $page_id ) );
t( 'page editor → restricted', is_array( $r ) );
$r = $run( true, $ctx( $proj_id ) );
t( 'public custom post type editor → restricted', is_array( $r ) );
$r = $run( true, $ctx( $int_id ) );
t( 'non-public, non-REST post type → not restricted (true passes through)', true === $r );
$r = $run( true, $site );
t( 'Site Editor (core/edit-site, no post) → untouched', true === $r );
$r = $run( array( 'core/paragraph' ), $site );
t( 'Site Editor with an upstream array → upstream array returned as-is', $r === array( 'core/paragraph' ) );
$r = $run( true, $ctx( $tpl_id ) );
t( 'wp_template in a post-editor context → untouched', true === $r );
$r = $run( true, $ctx( $part_id ) );
t( 'wp_template_part in a post-editor context → untouched', true === $r );
$r = $run( true, $ctx( $block_id ) );
t( 'wp_block (synced pattern) → untouched', true === $r );
$r = $run( true, $ctx( $nav_id ) );
t( 'wp_navigation → untouched', true === $r );
$r = $run( true, $ctx( $post_id, 'core/edit-widgets' ) );
t( 'widgets editor context with a post → untouched', true === $r );
$r = $run( true, $ctx( $post_id, 'core/customize-widgets' ) );
t( 'customizer widgets context → untouched', true === $r );
$r = $run( true, new WP_Block_Editor_Context( array( 'name' => 'core/edit-post' ) ) );
t( 'post editor context without a post → untouched', true === $r );

echo "== other plugins and the theme filter ==\n";
$plugin = fn() => array( 'core/paragraph', 'core/list', 'core/archives' );
add_filter( 'allowed_block_types_all', $plugin, 9 );
$r = $run( true, $ctx( $post_id ) );
remove_filter( 'allowed_block_types_all', $plugin, 9 );
t( 'plugin restricting before the theme (priority 9) → intersection, plugin\'s extra block not restored', $r === array( 'core/paragraph', 'core/list' ), wp_json_encode( $r ) );
$deny = '__return_false';
add_filter( 'allowed_block_types_all', $deny, 9 );
$r = $run( true, $ctx( $post_id ) );
remove_filter( 'allowed_block_types_all', $deny, 9 );
t( 'plugin denying everything before the theme → still false', false === $r );
$after = fn( $a ) => is_array( $a ) ? array_merge( $a, array( 'acme/form' ) ) : $a;
add_filter( 'allowed_block_types_all', $after, 20 );
$r = $run( true, $ctx( $post_id ) );
remove_filter( 'allowed_block_types_all', $after, 20 );
t( 'plugin after the theme (priority 20) may still add its block', is_array( $r ) && in_array( 'acme/form', $r, true ) );
$widen = fn( $b ) => array_merge( $b, array( 'acme/form' ) );
add_filter( 'oogle/editor/allowed_blocks', $widen );
$r = $run( true, $ctx( $post_id ) );
t( 'oogle/editor/allowed_blocks adds a third-party block', is_array( $r ) && in_array( 'acme/form', $r, true ) );
$r = $run( array( 'core/paragraph' ), $ctx( $post_id ) );
remove_filter( 'oogle/editor/allowed_blocks', $widen );
t( 'oogle/editor/allowed_blocks cannot widen an upstream array', $r === array( 'core/paragraph' ) );
$lift = '__return_true';
add_filter( 'oogle/editor/allowed_blocks', $lift );
$r1 = $run( true, $ctx( $post_id ) );
$r2 = $run( array( 'core/paragraph' ), $ctx( $post_id ) );
remove_filter( 'oogle/editor/allowed_blocks', $lift );
t( 'oogle/editor/allowed_blocks returning true lifts the theme restriction (true stays true)', true === $r1 );
t( 'oogle/editor/allowed_blocks returning true keeps an upstream array', $r2 === array( 'core/paragraph' ) );
$off = '__return_false';
add_filter( 'oogle/editor/restrict_post_type', $off );
$r = $run( true, $ctx( $post_id ) );
remove_filter( 'oogle/editor/restrict_post_type', $off );
t( 'oogle/editor/restrict_post_type false → post editor not restricted', true === $r );

foreach ( array( $post_id, $page_id, $proj_id, $int_id, $tpl_id, $part_id, $block_id, $nav_id ) as $id ) {
	wp_delete_post( $id, true );
}
$pass = $GLOBALS['oogle_t']['pass']; $fail = $GLOBALS['oogle_t']['fail'];
echo "\nRESULT: $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
