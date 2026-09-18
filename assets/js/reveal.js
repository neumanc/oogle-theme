/**
 * Oogle reveal — progressive scroll-entrance enhancement.
 *
 * Adds "is-visible" to elements with the class "oogle-reveal" (or any
 * "oogle-reveal--*" variant) when they enter the viewport. All motion is CSS
 * (see assets/css/base.css). Nothing here runs when the user prefers reduced
 * motion, and content is fully visible without JavaScript: the hiding rules
 * only apply once <html> carries the "oogle-js-reveal" class, which this
 * module adds right before observing.
 */
const SEL = '[class*="oogle-reveal"]';
const RATIO = 0.12; // Share of the element that must be in view before it reveals.

if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches && 'IntersectionObserver' in window ) {
	const root = document.documentElement;
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

	const observe = ( scope ) => {
		for ( const el of scope.querySelectorAll( SEL ) ) {
			if ( ! el.classList.contains( 'is-visible' ) ) {
				// Anything already in view on load is shown immediately (no flash on the first screen).
				const r = el.getBoundingClientRect();
				if ( r.top < window.innerHeight * 0.9 && r.bottom > 0 ) {
					el.classList.add( 'is-visible' );
				} else {
					io.observe( el );
				}
			}
		}
	};

	root.classList.add( 'oogle-js-reveal' );
	observe( document );
}
