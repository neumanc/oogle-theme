/**
 * Oogle reveal — progressive scroll-entrance enhancement.
 *
 * Adds "is-visible" to elements with the class "oogle-reveal" (or any
 * "oogle-reveal--*" variant) when they enter the viewport. All motion is CSS
 * (assets/css/reveal.css). Nothing here runs when the user prefers reduced
 * motion or without IntersectionObserver, and content is fully visible
 * without JavaScript: the hiding rules apply only to elements this module has
 * registered, which it marks with "oogle-reveal-ready" the moment it starts
 * observing them. Anything matching the selector that arrives later (a
 * plugin, an Interactivity API region, infinite scroll) therefore stays
 * visible until it is registered — never hidden by accident.
 *
 * Dynamic integrations register new markup by dispatching a bubbling
 * "oogle:reveal" event on the inserted container (or on document):
 *   node.dispatchEvent( new Event( 'oogle:reveal', { bubbles: true } ) );
 * Registering the same element twice is a no-op.
 */
const SEL = '[class*="oogle-reveal"]';
const READY = 'oogle-reveal-ready';
const RATIO = 0.12; // Share of the element that must be in view before it reveals.

if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches && 'IntersectionObserver' in window ) {
	const io = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				// A section taller than the viewport can never reach the ratio
				// (its ratio tops out at viewport / height), so anything at
				// least half a viewport tall reveals as soon as it enters.
				const tall = entry.rootBounds && entry.boundingClientRect.height >= entry.rootBounds.height * 0.5;
				if ( entry.isIntersecting && ( entry.intersectionRatio >= RATIO || tall ) ) {
					entry.target.classList.add( 'is-visible' );
					io.unobserve( entry.target );
				}
			}
		},
		{ rootMargin: '0px 0px -10% 0px', threshold: [ 0, RATIO ] }
	);

	const register = ( el ) => {
		if ( el.classList.contains( READY ) ) {
			return;
		}
		// Anything already in view is shown immediately (no flash on the first screen).
		const r = el.getBoundingClientRect();
		if ( r.top < window.innerHeight * 0.9 && r.bottom > 0 ) {
			el.classList.add( READY, 'is-visible' );
		} else {
			el.classList.add( READY );
			io.observe( el );
		}
	};

	const observe = ( scope ) => {
		if ( scope instanceof Element && scope.matches( SEL ) ) {
			register( scope );
		}
		for ( const el of scope.querySelectorAll( SEL ) ) {
			register( el );
		}
	};

	document.documentElement.classList.add( 'oogle-js-reveal' );
	observe( document );
	document.addEventListener( 'oogle:reveal', ( e ) => observe( e.target instanceof Element ? e.target : document ) );
}
