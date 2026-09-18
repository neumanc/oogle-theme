/**
 * Oogle Theme — browser regression suite (dev only; excluded from the release zip).
 *
 * Drives real browsers (Playwright) against a WordPress lab that has the theme active
 * and the fixture page from tests/browser/fixture.php. Checks the 1.0.1 fixes end to
 * end: outline button contrast in a Cover (new and legacy markup) in every interactive
 * state, mosaic captions (pointer, keyboard, touch), reveal (initial, dynamic, tall,
 * reduced motion, no IntersectionObserver, repeated init), the rotator control, plus a
 * keyboard walk, horizontal overflow, console/page errors and an axe scan on the pages
 * given as extra arguments.
 *
 *   OOGLE_QA_MODULES=~/.oogle/qa/node_modules node tests/browser/regression.mjs http://localhost:8892 [/path ...]
 *   BROWSERS=chromium,webkit,firefox (default: all three that are installed)
 */
import { createRequire } from 'node:module';
import path from 'node:path';
import os from 'node:os';

const QA = process.env.OOGLE_QA_MODULES || path.join( os.homedir(), '.oogle/qa/node_modules' );
const require = createRequire( path.join( QA, 'x.js' ) );
const playwright = require( 'playwright' );
const axeSource = require( 'axe-core' ).source;

const base = ( process.argv[ 2 ] || 'http://localhost:8892' ).replace( /\/$/, '' );
const pages = process.argv.slice( 3 );
const browsers = ( process.env.BROWSERS || 'chromium,webkit,firefox' ).split( ',' );
let pass = 0, fail = 0;
let warn = 0;
const t = ( name, ok, detail = '' ) => { ok ? pass++ : fail++; console.log( `${ ok ? 'PASS' : 'FAIL' }  ${ name }${ detail ? `  [${ detail }]` : '' }` ); };
const w = ( name, ok, detail = '' ) => { ok ? pass++ : warn++; console.log( `${ ok ? 'PASS' : 'WARN' }  ${ name }${ detail ? `  [${ detail }]` : '' }` ); };

const lum = ( c ) => { const [ r, g, b ] = c.map( ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4; } ); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
const rgb = ( s ) => { const m = s.match( /[\d.]+/g ) || [ 0, 0, 0, 1 ]; return { c: m.slice( 0, 3 ).map( Number ), a: m[ 3 ] === undefined ? 1 : Number( m[ 3 ] ) }; };
// WordPress core's speculative loading is refused by WebKit on plain-http labs; not a theme message.
const benign = ( text ) => /Prefetch request denied: URL must be secure/.test( text );
const tabKey = ( name ) => ( name === 'webkit' ? 'Alt+Tab' : 'Tab' );
const contrast = ( fg, bg ) => { const a = lum( rgb( fg ).c ), b = lum( rgb( bg ).c ); return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 ); };

async function buttonState( page, sel, state ) {
	const link = page.locator( sel ).first();
	await link.scrollIntoViewIfNeeded();
	await page.mouse.move( 0, 0 );
	await page.evaluate( () => document.activeElement && document.activeElement.blur() );
	if ( state === 'hover' ) { await link.hover(); }
	if ( state === 'focus' ) { await page.keyboard.press( 'Tab' ); await link.focus(); }
	if ( state === 'active' ) { const b = await link.boundingBox(); await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2 ); await page.mouse.down(); }
	await page.waitForTimeout( 250 );
	const r = await link.evaluate( ( el ) => { const cs = getComputedStyle( el ); return { color: cs.color, bg: cs.backgroundColor }; } );
	if ( state === 'active' ) { await page.mouse.up(); }
	return r;
}

for ( const name of browsers ) {
	let browser;
	try { browser = await playwright[ name ].launch(); } catch ( e ) { console.log( `SKIP  ${ name }: ${ e.message.split( '\n' )[ 0 ] }` ); continue; }
	console.log( `\n== ${ name } ==` );
	for ( const [ label, viewport ] of [ [ 'desktop', { width: 1280, height: 800 } ], [ 'mobile', { width: 375, height: 740 } ] ] ) {
		const ctx = await browser.newContext( { viewport, hasTouch: label === 'mobile', isMobile: label === 'mobile' && name === 'chromium' } );
		const page = await ctx.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
		page.on( 'console', ( m ) => { if ( m.type() === 'error' && ! benign( m.text() ) ) errors.push( 'console: ' + m.text() ); } );
		await page.goto( `${ base }/oogle-regression/`, { waitUntil: 'load' } );

		// --- Hero outline button: every state must keep text/background distinct and contrast >= 4.5.
		for ( const variant of [ 'new', 'old' ] ) {
			const sel = `.oogle-reg-hero-${ variant } .wp-block-button.is-style-outline > .wp-block-button__link`;
			for ( const state of [ 'rest', 'hover', 'focus', 'active' ] ) {
				const s = await buttonState( page, sel, state );
				const transparent = rgb( s.bg ).a === 0;
				const ratio = transparent ? null : contrast( s.color, s.bg );
				const ok = transparent || ( ratio >= 4.5 && s.color !== s.bg );
				t( `${ label } hero (${ variant } markup) outline button ${ state }: text ≠ background, contrast ok`, ok, `color=${ s.color } bg=${ s.bg }${ ratio ? ' ratio=' + ratio.toFixed( 2 ) : ' (transparent fill over scrim)' }` );
			}
		}

		// --- Mosaic captions.
		const cap = ( cls ) => page.locator( `.oogle-reg-mosaic .${ cls } figcaption` );
		const opacity = async ( cls ) => Number( await cap( cls ).evaluate( ( el ) => getComputedStyle( el ).opacity ) );
		await page.mouse.move( 0, 0 );
		await page.locator( '.oogle-reg-mosaic' ).scrollIntoViewIfNeeded();
		await page.waitForTimeout( 600 );
		t( `${ label } mosaic: unlinked, lightbox-off tile caption visible at rest`, ( await opacity( 'oogle-reg-tile-plain' ) ) === 1 );
		const hoverCapable = label === 'desktop';
		const restLinked = await opacity( 'oogle-reg-tile-linked' );
		t( `${ label } mosaic: linked tile caption at rest ${ hoverCapable ? 'folded (hover-capable)' : 'visible (hover: none)' }`, hoverCapable ? restLinked === 0 : restLinked === 1, `opacity=${ restLinked }` );
		const restLb = await opacity( 'oogle-reg-tile-lightbox' );
		t( `${ label } mosaic: lightbox tile caption at rest ${ hoverCapable ? 'folded' : 'visible' }`, hoverCapable ? restLb === 0 : restLb === 1, `opacity=${ restLb }` );
		if ( hoverCapable ) {
			await page.locator( '.oogle-reg-tile-linked img' ).hover();
			await page.waitForTimeout( 500 );
			t( `${ label } mosaic: linked tile caption on hover`, ( await opacity( 'oogle-reg-tile-linked' ) ) === 1 );
			await page.mouse.move( 0, 0 );
			await page.locator( '.oogle-reg-tile-linked a' ).first().focus();
			await page.waitForTimeout( 500 );
			t( `${ label } mosaic: linked tile caption on keyboard focus`, ( await opacity( 'oogle-reg-tile-linked' ) ) === 1 );
			await page.evaluate( () => document.activeElement.blur() );
			const lbButton = page.locator( '.oogle-reg-tile-lightbox button' ).first();
			if ( await lbButton.count() ) {
				await lbButton.focus();
				await page.waitForTimeout( 500 );
				t( `${ label } mosaic: lightbox tile caption on keyboard focus of the Enlarge button`, ( await opacity( 'oogle-reg-tile-lightbox' ) ) === 1 );
				await page.evaluate( () => document.activeElement.blur() );
			} else {
				t( `${ label } mosaic: lightbox tile renders core's Enlarge button`, false, 'no button found' );
			}
			await page.locator( '.oogle-reg-tile-caption-link figcaption a' ).focus();
			await page.waitForTimeout( 500 );
			const pe = await cap( 'oogle-reg-tile-caption-link' ).evaluate( ( el ) => getComputedStyle( el ).pointerEvents );
			t( `${ label } mosaic: caption link tile — caption shown on focus and clickable (pointer-events ${ pe })`, ( await opacity( 'oogle-reg-tile-caption-link' ) ) === 1 && pe !== 'none' );
			await page.evaluate( () => document.activeElement.blur() );
		}

		// --- Reveal: initial, below fold, stagger, tall, dynamic, repeated.
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.waitForTimeout( 300 );
		const op = async ( sel ) => Number( await page.locator( sel ).first().evaluate( ( el ) => getComputedStyle( el ).opacity ) );
		const cls = async ( sel ) => page.locator( sel ).first().evaluate( ( el ) => el.className );
		t( `${ label } reveal: module registered the elements (oogle-reveal-ready present)`, ( await cls( '.oogle-reg-reveal-below' ) ).includes( 'oogle-reveal-ready' ) );
		t( `${ label } reveal: element below the fold hidden before scrolling`, ( await op( '.oogle-reg-reveal-below' ) ) === 0 );
		await page.locator( '.oogle-reg-reveal-below' ).scrollIntoViewIfNeeded();
		await page.waitForTimeout( 900 );
		t( `${ label } reveal: element below the fold visible after scrolling`, ( await op( '.oogle-reg-reveal-below' ) ) === 1 );
		await page.locator( '.oogle-reg-reveal-stagger' ).scrollIntoViewIfNeeded();
		await page.waitForTimeout( 1200 );
		t( `${ label } reveal: stagger children visible after scrolling`, ( await op( '.oogle-reg-stagger-child' ) ) === 1 );
		await page.evaluate( () => { const el = document.querySelector( '.oogle-reg-reveal-tall' ); window.scrollTo( 0, el.getBoundingClientRect().top + window.scrollY - 200 ); } );
		await page.waitForTimeout( 1200 );
		t( `${ label } reveal: element taller than the viewport reveals on entry`, ( await op( '.oogle-reg-reveal-tall' ) ) === 1 );
		await page.evaluate( () => {
			const host = document.querySelector( '.oogle-reg-dynamic-host' );
			const p = document.createElement( 'p' ); p.className = 'oogle-reveal oogle-reg-dynamic'; p.textContent = 'Inserted after load.';
			host.append( p );
		} );
		await page.waitForTimeout( 300 );
		t( `${ label } reveal: dynamically inserted matching element stays VISIBLE without registration`, ( await op( '.oogle-reg-dynamic' ) ) === 1 && ! ( await cls( '.oogle-reg-dynamic' ) ).includes( 'oogle-reveal-ready' ) );
		await page.evaluate( () => { window.scrollTo( 0, 0 ); document.querySelector( '.oogle-reg-dynamic-host' ).dispatchEvent( new Event( 'oogle:reveal', { bubbles: true } ) ); } );
		await page.waitForTimeout( 1000 ); // the hide transition (0.7 s) must finish
		t( `${ label } reveal: after oogle:reveal the inserted element is registered and hidden while off screen`, ( await cls( '.oogle-reg-dynamic' ) ).includes( 'oogle-reveal-ready' ) && ( await op( '.oogle-reg-dynamic' ) ) === 0 );
		await page.evaluate( () => document.querySelector( '.oogle-reg-dynamic-host' ).dispatchEvent( new Event( 'oogle:reveal', { bubbles: true } ) ) );
		await page.locator( '.oogle-reg-dynamic' ).scrollIntoViewIfNeeded();
		await page.waitForTimeout( 900 );
		t( `${ label } reveal: registered dynamic element reveals on scroll (repeated registration harmless)`, ( await op( '.oogle-reg-dynamic' ) ) === 1 );

		// --- Rotator control.
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		const toggle = page.locator( '.oogle-reg-rotator .oogle-rotator__toggle' );
		t( `${ label } rotator: pause control present once, aria-pressed=false, labelled`, ( await toggle.count() ) === 1 && ( await toggle.getAttribute( 'aria-pressed' ) ) === 'false' && !! ( await toggle.getAttribute( 'aria-label' ) ), await toggle.getAttribute( 'aria-label' ) || '' );
		t( `${ label } rotator: single-slide stack gets no control`, ( await page.locator( '.oogle-reg-rotator-single .oogle-rotator__toggle' ).count() ) === 0 );
		const box = await toggle.boundingBox();
		t( `${ label } rotator: control target ≥ 44×44`, !! box && box.width >= 44 && box.height >= 44, box ? `${ Math.round( box.width ) }×${ Math.round( box.height ) }` : 'no box' );
		await toggle.click();
		t( `${ label } rotator: click pauses (aria-pressed=true, is-paused)`, ( await toggle.getAttribute( 'aria-pressed' ) ) === 'true' && ( await cls( '.oogle-reg-rotator' ) ).includes( 'is-paused' ) );
		await toggle.focus();
		await page.keyboard.press( 'Enter' );
		t( `${ label } rotator: keyboard Enter resumes`, ( await toggle.getAttribute( 'aria-pressed' ) ) === 'false' );
		// Reach the control by keyboard (focus-visible heuristics differ for script focus): Shift+Tab away, Tab back.
		await page.keyboard.press( name === 'webkit' ? 'Alt+Shift+Tab' : 'Shift+Tab' );
		await page.keyboard.press( tabKey( name ) );
		const focused = await page.evaluate( () => document.activeElement && document.activeElement.className );
		const outline = await toggle.evaluate( ( el ) => getComputedStyle( el ).outlineStyle );
		t( `${ label } rotator: control has a visible focus indicator when reached by keyboard`, focused === 'oogle-rotator__toggle' && outline !== 'none', `focused=${ focused } outline-style=${ outline }` );

		// --- Overflow and errors on the fixture.
		t( `${ label } fixture: no horizontal overflow`, await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 ) );
		t( `${ label } fixture: no console or page errors`, errors.length === 0, errors.join( ' | ' ).slice( 0, 300 ) );

		// --- Reduced motion: no reveal registration, everything visible, no rotator control.
		const rmCtx = await browser.newContext( { viewport, reducedMotion: 'reduce' } );
		const rm = await rmCtx.newPage();
		await rm.goto( `${ base }/oogle-regression/`, { waitUntil: 'load' } );
		await rm.waitForTimeout( 500 );
		const rmHidden = await rm.evaluate( () => [ ...document.querySelectorAll( '[class*="oogle-reveal"]' ) ].filter( ( el ) => getComputedStyle( el ).opacity !== '1' ).length );
		t( `${ label } reduced motion: every reveal element visible without scrolling`, rmHidden === 0, `${ rmHidden } hidden` );
		t( `${ label } reduced motion: nothing registered (no oogle-reveal-ready)`, ( await rm.locator( '.oogle-reveal-ready' ).count() ) === 0 );
		t( `${ label } reduced motion: rotator does not rotate and adds no control`, ( await rm.locator( '.oogle-rotator__toggle' ).count() ) === 0 && ! ( await rm.locator( '.oogle-reg-rotator' ).evaluate( ( el ) => el.classList.contains( 'is-active' ) ) ) );
		await rmCtx.close();

		// --- IntersectionObserver unavailable: everything visible.
		const noIoCtx = await browser.newContext( { viewport } );
		await noIoCtx.addInitScript( () => { delete window.IntersectionObserver; } );
		const noIo = await noIoCtx.newPage();
		await noIo.goto( `${ base }/oogle-regression/`, { waitUntil: 'load' } );
		await noIo.waitForTimeout( 500 );
		const noIoHidden = await noIo.evaluate( () => [ ...document.querySelectorAll( '[class*="oogle-reveal"]' ) ].filter( ( el ) => getComputedStyle( el ).opacity !== '1' ).length );
		t( `${ label } no IntersectionObserver: every reveal element visible`, noIoHidden === 0 && ( await noIo.locator( '.oogle-reveal-ready' ).count() ) === 0, `${ noIoHidden } hidden` );
		await noIoCtx.close();

		// --- Core interactive blocks on the site: navigation overlay (mobile), accordion, lightbox.
		if ( pages.length ) {
			const ip = await ctx.newPage();
			const ierr = [];
			ip.on( 'pageerror', ( e ) => ierr.push( e.message ) );
			await ip.goto( base + pages[ 0 ], { waitUntil: 'networkidle' } );
			if ( label === 'mobile' ) {
				const open = ip.locator( '.wp-block-navigation__responsive-container-open' ).first();
				if ( await open.count() ) {
					await open.click();
					await ip.waitForTimeout( 400 );
					const shown = await ip.locator( '.wp-block-navigation__responsive-container.is-menu-open' ).count();
					await ip.keyboard.press( 'Escape' );
					await ip.waitForTimeout( 400 );
					const closed = await ip.locator( '.wp-block-navigation__responsive-container.is-menu-open' ).count();
					t( `${ label } navigation overlay opens on tap and closes on Escape`, shown === 1 && closed === 0, `open=${ shown } afterEscape=${ closed }` );
				} else {
					t( `${ label } navigation overlay: open button present`, false );
				}
			}
			const acc = ip.locator( '.wp-block-accordion-heading button, .wp-block-accordion-trigger, .wp-block-details > summary' ).first();
			if ( await acc.count() ) {
				await acc.scrollIntoViewIfNeeded();
				const before = await acc.evaluate( ( el ) => el.getAttribute( 'aria-expanded' ) ?? String( el.parentElement.open ) );
				await acc.click();
				await ip.waitForTimeout( 400 );
				const after = await acc.evaluate( ( el ) => el.getAttribute( 'aria-expanded' ) ?? String( el.parentElement.open ) );
				t( `${ label } accordion toggles on click`, before !== after && after === 'true', `${ before } → ${ after }` );
			}
			const lb = ip.locator( '.wp-block-image .lightbox-trigger' ).first();
			if ( await lb.count() ) {
				await lb.scrollIntoViewIfNeeded();
				await lb.click();
				await ip.waitForTimeout( 600 );
				const opened = await ip.locator( '.wp-lightbox-overlay.active' ).count();
				await ip.keyboard.press( 'Escape' );
				await ip.waitForTimeout( 600 );
				const closedLb = await ip.locator( '.wp-lightbox-overlay.active' ).count();
				t( `${ label } lightbox opens and closes on Escape`, opened === 1 && closedLb === 0, `open=${ opened } afterEscape=${ closedLb }` );
			}
			const siteToggle = ip.locator( '.oogle-rotator__toggle' ).first();
			if ( await siteToggle.count() ) {
				await ip.evaluate( () => window.scrollTo( 0, 0 ) );
				await ip.waitForTimeout( 300 );
				const hit = await siteToggle.evaluate( ( el ) => { const r = el.getBoundingClientRect(); const top = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 ); return top === el || el.contains( top ); } );
				t( `${ label } site rotator control is reachable by pointer (topmost element at its centre)`, hit );
			}
			t( `${ label } interactive blocks: no page errors`, ierr.length === 0, ierr.join( ' | ' ).slice( 0, 200 ) );
			await ip.close();
		}

		// --- Site pages: keyboard walk, overflow, errors, axe.
		for ( const p of pages ) {
			const errs = [];
			const sp = await ctx.newPage();
			sp.on( 'pageerror', ( e ) => errs.push( 'pageerror: ' + e.message ) );
			sp.on( 'console', ( m ) => { if ( m.type() === 'error' && ! benign( m.text() ) ) errs.push( 'console: ' + m.text() ); } );
			const resp = await sp.goto( base + p, { waitUntil: 'load' } );
			t( `${ label } ${ p }: HTTP 200`, !! resp && resp.status() === 200, String( resp && resp.status() ) );
			// Site pages carry child-theme and content layout the parent does not own: overflow there is reported, not failed.
			w( `${ label } ${ p }: no horizontal overflow (site content)`, await sp.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 ), await sp.evaluate( () => `scrollWidth=${ document.documentElement.scrollWidth} clientWidth=${ document.documentElement.clientWidth }` ) );
			if ( label === 'desktop' ) {
				let visibleFocus = 0, steps = 0;
				for ( let i = 0; i < 12; i++ ) {
					await sp.keyboard.press( tabKey( name ) );
					const info = await sp.evaluate( () => { const el = document.activeElement; if ( ! el || el === document.body ) return null; const cs = getComputedStyle( el ); const r = el.getBoundingClientRect(); return { tag: el.tagName, outline: cs.outlineStyle !== 'none' && cs.outlineWidth !== '0px', shadow: cs.boxShadow !== 'none', visible: r.width > 0 && r.height > 0 }; } );
					if ( info ) { steps++; if ( info.visible && ( info.outline || info.shadow ) ) visibleFocus++; }
				}
				t( `${ label } ${ p }: keyboard walk reaches focusable controls with a visible focus indicator`, steps > 0 && visibleFocus === steps, `${ visibleFocus }/${ steps }` );
			}
			t( `${ label } ${ p }: no console or page errors`, errs.length === 0, errs.join( ' | ' ).slice( 0, 300 ) );
			await sp.close();
			// axe runs with motion disabled: mid-fade opacity would otherwise be scored as low contrast.
			const axeCtx = await browser.newContext( { viewport, reducedMotion: 'reduce' } );
			const ap = await axeCtx.newPage();
			await ap.goto( base + p, { waitUntil: 'networkidle' } );
			await ap.waitForTimeout( 800 ); // let core's Interactivity API hydrate (lightbox trigger labels are bound client-side)
			await ap.addScriptTag( { content: axeSource } );
			const axe = await ap.evaluate( async () => { const r = await window.axe.run( document, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] } ); return r.violations.map( ( v ) => `${ v.id } (${ v.impact }, ${ v.nodes.length })` ); } );
			t( `${ label } ${ p }: axe (WCAG 2.2 AA) violations`, axe.length === 0, axe.join( '; ' ) );
			await axeCtx.close();
		}
		await ctx.close();
	}
	await browser.close();
}
console.log( `\nRESULT: ${ pass } passed, ${ fail } failed, ${ warn } warnings (site content, informational)` );
process.exit( fail > 0 ? 1 : 0 );
