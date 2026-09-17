/**
 * Oogle rotator — a crossfading photo stack (hero backgrounds).
 *
 * Markup: an element with the class "oogle-rotator" whose direct children are
 * the slides (core Image blocks). The first slide is visible in the HTML and is
 * the LCP candidate; the others carry "oogle-defer" (lazy, low priority) and
 * are display:none until this module queues them, one at a time, so no extra
 * image is requested before the page has loaded. The stable copy sits outside
 * the stack, so nothing meaningful rotates away: the images are decorative.
 *
 * Does nothing under prefers-reduced-motion, without JavaScript, with a single
 * slide, while the tab is hidden, or while the stack is off screen.
 */
const HOLD = 6500; // ms a slide stays before the next fades in over it.
const FADE = 1700; // must match the CSS transition on .is-shown.

const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

if ( ! reduced.matches ) {
	for ( const el of document.querySelectorAll( '.oogle-rotator' ) ) {
		start( el );
	}
}

function start( el ) {
	const slides = [ ...el.children ];
	if ( slides.length < 2 ) {
		return;
	}

	let current = 0;
	let timer = 0;
	let onScreen = true;
	slides[ 0 ].classList.add( 'is-shown' );
	el.classList.add( 'is-active' );

	// Make a slide's image fetchable and resolve once it can be painted.
	const prepare = ( i ) => {
		const slide = slides[ i ];
		slide.classList.add( 'is-queued' );
		const img = slide.querySelector( 'img' );
		if ( ! img ) {
			return Promise.resolve();
		}
		const loaded = img.complete ? Promise.resolve() : new Promise( ( r ) => { img.onload = r; img.onerror = r; } );
		return loaded.then( () => ( img.decode ? img.decode().catch( () => {} ) : undefined ) );
	};

	const show = async () => {
		const next = ( current + 1 ) % slides.length;
		await prepare( next );
		if ( reduced.matches || ! onScreen || document.hidden ) {
			schedule();
			return;
		}
		const out = slides[ current ];
		out.classList.replace( 'is-shown', 'is-under' );
		slides[ next ].classList.add( 'is-shown' );
		current = next;
		setTimeout( () => out.classList.remove( 'is-under' ), FADE + 50 );
		// Fetch the slide after next during the hold, one image in flight at a time.
		prepare( ( current + 1 ) % slides.length );
		schedule();
	};

	const schedule = () => {
		clearTimeout( timer );
		if ( onScreen && ! document.hidden ) {
			timer = setTimeout( show, HOLD );
		}
	};

	document.addEventListener( 'visibilitychange', schedule );
	if ( 'IntersectionObserver' in window ) {
		new IntersectionObserver( ( [ e ] ) => { onScreen = e.isIntersecting; schedule(); }, { threshold: 0.05 } ).observe( el );
	}

	// Nothing beyond the first image is requested until the page has loaded.
	const begin = () => prepare( 1 ).then( schedule );
	if ( document.readyState === 'complete' ) {
		begin();
	} else {
		window.addEventListener( 'load', begin, { once: true } );
	}
}
