/**
 * SwiftWebDev Dashboard card order: drag (SortableJS) or keyboard (handle + ArrowUp/ArrowDown), saved per user
 * in the swiftdash_layout user meta. Cards move up and down only; their width never changes.
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const grid = document.querySelector( '[data-sd-sortable]' );
	if ( ! grid || ! window.Sortable || ! window.swiftdash ) {
		return;
	}

	const screen = grid.dataset.sdSortable;
	const live = document.querySelector( '[data-sd-sort-live]' );
	const layout = Object.assign( { dashboard: [] }, window.swiftdashLayout );
	const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const cards = () => Array.from( grid.children ).filter( ( el ) => el.matches( '.sd-widget' ) );
	const visible = () => cards().filter( ( el ) => ! el.hidden );
	let timer = 0;

	function save( ids ) {
		layout[ screen ] = ids;
		return wp.apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { swiftdash_layout: layout } },
		} );
	}

	function saveOrder() {
		clearTimeout( timer );
		timer = setTimeout( () => {
			save( cards().map( ( el ) => el.dataset.sdWidget ) )
				.then( () => window.swiftdash.toast( __( 'Layout saved', 'swiftwebdev-dashboard-analytics' ), 'success' ) )
				.catch( () => window.swiftdash.toast( __( 'Could not save the card order. Please try again.', 'swiftwebdev-dashboard-analytics' ), 'error' ) );
		}, 400 );
	}

	/*
	 * No holes: when a card does not fit the rest of its row (a card was hidden or moved), the card
	 * before it widens to the row end. Spans come from the stylesheet for the current width.
	 */
	function fillRows() {
		const items = Array.from( grid.children ).filter( ( el ) => ! el.hidden && ( el.matches( '.sd-widget' ) || el.matches( '.sd-promo' ) ) );
		items.forEach( ( el ) => ( el.style.gridColumn = '' ) );
		const spans = items.map( ( el ) => {
			const style = getComputedStyle( el );
			const match = /span\s+(\d+)/.exec( style.gridColumnStart + ' ' + style.gridColumnEnd );
			return match ? Math.min( 12, Number( match[ 1 ] ) ) : 12; // The promo runs 1 / -1.
		} );
		let used = 0;
		const widen = ( i ) => {
			if ( i >= 0 && used > 0 && used < 12 ) {
				items[ i ].style.gridColumn = 'span ' + ( spans[ i ] + 12 - used );
			}
		};
		spans.forEach( ( span, i ) => {
			if ( used + span > 12 ) {
				widen( i - 1 );
				used = 0;
			}
			used = ( used + span ) % 12;
		} );
		widen( items.length - 1 );
	}
	fillRows();
	// Cards shown or hidden (Customize), moved, or the promo dismissed; style changes are not watched.
	new MutationObserver( ( records ) => {
		if ( records.some( ( r ) => r.target === grid || r.target.parentElement === grid ) ) {
			fillRows();
		}
	} ).observe( grid, { childList: true, subtree: true, attributeFilter: [ 'hidden' ] } );
	let gridWidth = 0;
	new ResizeObserver( ( [ entry ] ) => {
		if ( entry.contentRect.width !== gridWidth ) {
			gridWidth = entry.contentRect.width;
			fillRows();
		}
	} ).observe( grid );

	window.Sortable.create( grid, {
		handle: '.sd-drag',
		draggable: '.sd-widget',
		animation: reduced ? 0 : 150,
		ghostClass: 'sd-widget--ghost',
		chosenClass: 'sd-widget--chosen',
		forceFallback: true,
		fallbackClass: 'sd-widget--drag',
		onEnd( evt ) {
			if ( evt.oldIndex !== evt.newIndex ) {
				saveOrder();
			}
		},
	} );

	/* Keyboard: the focused handle moves its card one visible position up or down. */
	grid.addEventListener( 'keydown', ( evt ) => {
		const handle = evt.target.closest( '[data-sd-drag]' );
		if ( ! handle || ( 'ArrowUp' !== evt.key && 'ArrowDown' !== evt.key ) ) {
			return;
		}
		evt.preventDefault();
		const card = handle.closest( '.sd-widget' );
		const list = visible();
		const index = list.indexOf( card );
		const target = list[ 'ArrowUp' === evt.key ? index - 1 : index + 1 ];
		if ( ! target ) {
			return;
		}
		grid.insertBefore( card, 'ArrowUp' === evt.key ? target : target.nextSibling );
		handle.focus();
		/* translators: 1: new position, 2: number of cards. */
		live.textContent = sprintf( __( 'Moved to position %1$s of %2$s', 'swiftwebdev-dashboard-analytics' ), visible().indexOf( card ) + 1, list.length );
		saveOrder();
	} );

	/* "Reset layout" in the Customize panel: default order, saved order cleared. */
	const foot = document.querySelector( '[data-sd-customize] .sd-dialog__foot' );
	if ( foot ) {
		const reset = document.createElement( 'button' );
		reset.type = 'button';
		reset.className = 'sd-btn sd-btn--tertiary';
		reset.textContent = __( 'Reset layout', 'swiftwebdev-dashboard-analytics' );
		foot.firstElementChild.after( reset );
		reset.addEventListener( 'click', () => {
			const byId = {};
			cards().forEach( ( el ) => ( byId[ el.dataset.sdWidget ] = el ) );
			// The promo banner keeps its slot: the same number of cards stays above it.
			const promo = grid.querySelector( ':scope > .sd-promo' );
			const above = promo ? cards().filter( ( el ) => el.compareDocumentPosition( promo ) & Node.DOCUMENT_POSITION_FOLLOWING ).length : 0;
			grid.dataset.sdDefault.split( ',' ).forEach( ( id ) => byId[ id ] && grid.appendChild( byId[ id ] ) );
			if ( promo ) {
				grid.insertBefore( promo, cards()[ above ] || null );
			}
			clearTimeout( timer );
			save( [] )
				.then( () => window.swiftdash.toast( __( 'Layout reset', 'swiftwebdev-dashboard-analytics' ), 'success' ) )
				.catch( () => window.swiftdash.toast( __( 'Could not reset the card order. Please try again.', 'swiftwebdev-dashboard-analytics' ), 'error' ) );
		} );
	}
}() );
