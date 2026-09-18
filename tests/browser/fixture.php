<?php
/**
 * Oogle Theme — browser regression fixture (dev only; excluded from the release zip).
 *
 * Creates or updates the page /oogle-regression/ with the markup the browser suite
 * (tests/browser/regression.mjs) inspects: the Hero (cover) pattern as shipped and a
 * copy carrying the pre-1.0.1 "has-base-color" outline button, four mosaic tiles
 * (unlinked, lightbox, linked, caption link), reveal elements (below the fold, tall,
 * stagger), a three-slide rotator and a single-slide rotator.
 * Run inside a WordPress install with the theme (or a child) active:
 *   wp eval-file tests/browser/fixture.php
 */
$placeholder = get_theme_file_uri( 'assets/img/placeholder.svg' );
$attachments = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'numberposts' => 3, 'fields' => 'ids' ) );
$img = function ( int $i ) use ( $attachments, $placeholder ) {
	if ( isset( $attachments[ $i ] ) ) {
		return array( 'id' => (int) $attachments[ $i ], 'url' => wp_get_attachment_image_url( (int) $attachments[ $i ], 'large' ) );
	}
	return array( 'id' => 0, 'url' => $placeholder );
};
$a = $img( 0 );
$b = $img( 1 );
$c = $img( 2 );

ob_start();
include get_template_directory() . '/patterns/hero-cover.php';
$hero = trim( preg_replace( '/^<\?php.*?\?>\s*/s', '', ob_get_clean() ) );
$hero_new = str_replace( 'class="wp-block-cover alignfull oogle-lcp"', 'class="wp-block-cover alignfull oogle-lcp oogle-reg-hero-new"', $hero );
$hero_old = str_replace(
	array( 'class="wp-block-cover alignfull oogle-lcp"', '{"className":"is-style-outline"}', 'class="wp-block-button__link wp-element-button" href="#">Secondary action' ),
	array( 'class="wp-block-cover alignfull oogle-reg-hero-old"', '{"className":"is-style-outline","textColor":"base"}', 'class="wp-block-button__link has-base-color has-text-color wp-element-button" href="#">Secondary action' ),
	str_replace( 'oogle-lcp', 'oogle-reg-hero-old-marker', $hero )
);
$hero_old = str_replace( 'oogle-reg-hero-old-marker', 'oogle-reg-hero-old', $hero_old );

$tile = function ( string $cls, array $m, string $caption, string $extra_attrs = '', string $wrap_open = '', string $wrap_close = '' ) {
	return '<!-- wp:image {"id":' . $m['id'] . ',"sizeSlug":"large","linkDestination":"none"' . $extra_attrs . ',"className":"' . $cls . '"} -->'
		. '<figure class="wp-block-image size-large ' . $cls . '">' . $wrap_open . '<img src="' . esc_url( $m['url'] ) . '" alt="Fixture photo" class="wp-image-' . $m['id'] . '"/>' . $wrap_close . '<figcaption class="wp-element-caption">' . $caption . '</figcaption></figure>'
		. '<!-- /wp:image -->';
};
$mosaic = '<!-- wp:group {"className":"is-style-mosaic oogle-mosaic--captions oogle-reg-mosaic","layout":{"type":"grid","columnCount":2}} -->'
	. '<div class="wp-block-group is-style-mosaic oogle-mosaic--captions oogle-reg-mosaic">'
	. $tile( 'oogle-reg-tile-plain', $a, 'Plain tile caption (no link, lightbox off)' )
	. $tile( 'oogle-reg-tile-lightbox', $b, 'Lightbox tile caption', ',"lightbox":{"enabled":true}' )
	. $tile( 'oogle-reg-tile-linked', $c, 'Linked tile caption', '', '<a href="https://example.com/linked">', '</a>' )
	. $tile( 'oogle-reg-tile-caption-link', $a, 'Caption with a <a href="https://example.com/caption">caption link</a>' )
	. '</div><!-- /wp:group -->';

$para = fn( string $cls, string $text ) => '<!-- wp:paragraph {"className":"' . $cls . '"} --><p class="' . $cls . '">' . $text . '</p><!-- /wp:paragraph -->';
$spacer = '<!-- wp:spacer {"height":"120vh"} --><div style="height:120vh" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
$reveal = $para( 'oogle-reveal oogle-reg-reveal-above', 'Reveal element in the first screen.' )
	. $spacer
	. $para( 'oogle-reveal oogle-reg-reveal-below', 'Reveal element below the fold.' )
	. '<!-- wp:group {"className":"oogle-reveal--stagger oogle-reg-reveal-stagger","layout":{"type":"constrained"}} --><div class="wp-block-group oogle-reveal--stagger oogle-reg-reveal-stagger">' . $para( 'oogle-reg-stagger-child', 'Stagger child one.' ) . $para( 'oogle-reg-stagger-child', 'Stagger child two.' ) . '</div><!-- /wp:group -->'
	. '<!-- wp:group {"className":"oogle-reveal oogle-reg-reveal-tall","style":{"dimensions":{"minHeight":"150vh"}},"layout":{"type":"constrained"}} --><div class="wp-block-group oogle-reveal oogle-reg-reveal-tall" style="min-height:150vh">' . $para( '', 'A section taller than the viewport.' ) . '</div><!-- /wp:group -->'
	. '<!-- wp:group {"className":"oogle-reg-dynamic-host","layout":{"type":"constrained"}} --><div class="wp-block-group oogle-reg-dynamic-host">' . $para( '', 'Dynamic content is inserted here by the test.' ) . '</div><!-- /wp:group -->';

$slide = fn( array $m, string $cls ) => '<!-- wp:image {"id":' . $m['id'] . ',"sizeSlug":"large","className":"' . $cls . '"} --><figure class="wp-block-image size-large ' . $cls . '"><img src="' . esc_url( $m['url'] ) . '" alt="" class="wp-image-' . $m['id'] . '"/></figure><!-- /wp:image -->';
$rotator = '<!-- wp:group {"className":"oogle-rotator oogle-reg-rotator","style":{"dimensions":{"minHeight":"40vh"}},"layout":{"type":"default"}} --><div class="wp-block-group oogle-rotator oogle-reg-rotator" style="min-height:40vh">' . $slide( $a, 'oogle-lcp' ) . $slide( $b, 'oogle-defer' ) . $slide( $c, 'oogle-defer' ) . '</div><!-- /wp:group -->'
	. '<!-- wp:group {"className":"oogle-rotator oogle-reg-rotator-single","style":{"dimensions":{"minHeight":"20vh"}},"layout":{"type":"default"}} --><div class="wp-block-group oogle-rotator oogle-reg-rotator-single" style="min-height:20vh">' . $slide( $a, 'oogle-lcp' ) . '</div><!-- /wp:group -->';

$content = $hero_new . $hero_old . $mosaic . $rotator . $reveal;

$existing = get_posts( array( 'post_type' => 'page', 'name' => 'oogle-regression', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) );
$args     = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Oogle regression fixture', 'post_name' => 'oogle-regression', 'post_content' => $content );
if ( $existing ) {
	$args['ID'] = (int) $existing[0];
	$id         = wp_update_post( $args );
} else {
	$id = wp_insert_post( $args );
}
update_post_meta( $id, '_wp_page_template', 'page-no-title' );
echo get_permalink( $id ) . "\n";
