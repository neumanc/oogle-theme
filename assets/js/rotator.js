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
 * Because the rotation starts by itself, lasts longer than five seconds and
 * runs beside the page's content, the module adds one pause/play control
 * inside the stack (WCAG 2.2.2 Pause, Stop, Hide). Labels come from the
 * script-module data inc/assets.php prints; English fallbacks are built in.
 *
 * Does nothing under prefers-reduced-motion, without JavaScript, with a single
 * slide, while the tab is hidden, while the stack is off screen, or while
 * paused by the visitor.
 */
const HOLD = 6500; // ms a slide stays before the next fades in over it.
const FADE = 1700; // must match the CSS transition on .is-shown.
const TOGGLE = 'oogle-rotator__toggle';

const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

const labels = ( () => {
	const defaults = { pause: 'Pause slideshow', play: 'Play slideshow' };
	try {
		const node = document.getElementById( 'wp-script-module-data-oogle-rotator' );
		return node ? { ...defaults, ...JSON.parse( node.textContent ) } : defaults;
	} catch {
		return defaults;
	}
} )();

if ( ! reduced.matches ) {
	for ( const el of document.querySelectorAll( '.oogle-rotator' ) ) {
		start( el );
	}
}

function start( el ) {
	const slides = [ ...el.children ].filter( ( child ) => ! child.classList.contains( TOGGLE ) );
	if ( slides.length < 2 || el.classList.contains( 'is-active' ) ) {
		return;
	}

	let current = 0;
	let timer = 0;
	let onScreen = true;
	let paused = false;
	let busy = false; // A crossfade is being prepared; ignore re-entrant schedules.
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
		if ( busy ) {
			return;
		}
		busy = true;
		const next = ( current + 1 ) % slides.length;
		await prepare( next );
		busy = false;
		if ( reduced.matches || paused || ! onScreen || document.hidden ) {
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
		if ( ! paused && onScreen && ! document.hidden ) {
			timer = setTimeout( show, HOLD );
		}
	};

	// Pause / play control: the one mechanism WCAG 2.2.2 asks for.
	const toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.className = TOGGLE;
	toggle.setAttribute( 'aria-pressed', 'false' );
	const setState = () => {
		toggle.setAttribute( 'aria-pressed', paused ? 'true' : 'false' );
		toggle.setAttribute( 'aria-label', paused ? labels.play : labels.pause );
		toggle.innerHTML = paused
			? '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>'
			: '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>';
		el.classList.toggle( 'is-paused', paused );
	};
	toggle.addEventListener( 'click', () => {
		paused = ! paused;
		setState();
		schedule();
	} );
	setState();
	el.append( toggle );

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
