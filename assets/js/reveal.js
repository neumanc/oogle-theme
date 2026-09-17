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

if ( ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches && 'IntersectionObserver' in window ) {
	const root = document.documentElement;
	const io = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					io.unobserve( entry.target );
				}
			}
		},
		{ rootMargin: '0px 0px -10% 0px', threshold: 0.12 }
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
