/**
 * Quick Links screen: pin, drag to reorder pinned, hide, restore and search shortcuts.
 * Saved per user via core /wp/v2/users/me.
 */
( function () {
	'use strict';

	const root = document.querySelector( '[data-sd-ql]' );
	if ( ! root || ! window.swiftdash ) {
		return;
	}

	const { __, _n, sprintf } = wp.i18n;
	const { toast, confirm } = window.swiftdash;
	const pins = root.querySelector( '[data-sd-ql-list="pinned"]' );
	const pinsEmpty = root.querySelector( '[data-sd-ql-pins-empty]' );
	const hiddenList = root.querySelector( '[data-sd-ql-list="hidden"]' );
	const hiddenSection = root.querySelector( '[data-sd-ql-hidden-section]' );
	const noMatch = root.querySelector( '[data-sd-ql-nomatch]' );
	const count = root.querySelector( '[data-sd-ql-count]' );
	const announcer = root.querySelector( '[data-sd-ql-announce]' );
	const search = document.querySelector( '[data-sd-ql-search]' );
	const restore = document.querySelector( '[data-sd-ql-restore]' );
	const defaults = JSON.parse( root.dataset.sdQlDefaults || '[]' );

	const items = ( list ) => Array.from( ( list || root ).querySelectorAll( '[data-sd-link]' ) );
	const ids = ( list ) => items( list ).map( ( li ) => li.dataset.sdLink );
	const label = ( li ) => li.querySelector( '.sd-ql__label' ).textContent;

	/* ---------- Save (debounced, requests run one after another so the last state wins) ---------- */

	let timer = null;
	let queue = Promise.resolve();

	function save() {
		clearTimeout( timer );
		timer = setTimeout( () => {
			const pinned = ids( pins );
			const value = { order: pinned, pinned, hidden: ids( hiddenList ) };
			queue = queue
				.then( () => wp.apiFetch( { path: '/wp/v2/users/me', method: 'POST', data: { meta: { swiftdash_links: value } } } ) )
				.then( () => toast( __( 'Shortcuts updated.', 'swift-dashboard-analytics' ), 'success' ) )
				.catch( () => toast( __( 'Could not save your shortcuts. Please try again.', 'swift-dashboard-analytics' ), 'error' ) );
		}, 300 );
	}

	/* ---------- View state ---------- */

	// Put a shortcut back in its own section, in catalogue order.
	function home( li ) {
		const list = root.querySelector( '[data-sd-ql-list="' + li.dataset.sdSection + '"]' );
		const next = items( list ).find( ( el ) => +el.dataset.sdIndex > +li.dataset.sdIndex );
		list.insertBefore( li, next || null );
	}

	function setPinned( li, pinned ) {
		li.classList.toggle( 'is-pinned', pinned );
		li.querySelector( '[data-sd-ql-action="pin"]' ).setAttribute( 'aria-pressed', String( pinned ) );
	}

	function refresh() {
		pinsEmpty.hidden = pins.children.length > 0;
		hiddenSection.hidden = ! hiddenList.children.length;
		filter();
	}

	function filter() {
		const q = search.value.trim().toLowerCase();
		let shown = 0;
		items().forEach( ( li ) => {
			li.hidden = !! q && ! li.dataset.sdQlText.includes( q );
			if ( ! li.hidden && ! hiddenList.contains( li ) ) {
				shown++;
			}
		} );
		root.querySelectorAll( '[data-sd-ql-section]' ).forEach( ( section ) => {
			section.hidden = ! items( section ).some( ( li ) => ! li.hidden );
		} );
		noMatch.hidden = ! q || shown > 0;
		count.textContent = q
			? sprintf(
				/* translators: %d: number of shortcuts. */
				_n( '%d shortcut found.', '%d shortcuts found.', shown, 'swift-dashboard-analytics' ),
				shown
			)
			: '';
	}

	function announce( li ) {
		announcer.textContent = sprintf(
			/* translators: 1: shortcut name, 2: new position, 3: number of pinned shortcuts. */
			__( '%1$s moved to position %2$d of %3$d.', 'swift-dashboard-analytics' ),
			label( li ),
			items( pins ).indexOf( li ) + 1,
			pins.children.length
		);
	}

	/* ---------- Actions ---------- */

	const actions = {
		pin( li, btn ) {
			if ( li.classList.contains( 'is-pinned' ) ) {
				setPinned( li, false );
				home( li );
			} else {
				setPinned( li, true );
				pins.appendChild( li );
			}
			btn.focus();
		},
		hide( li ) {
			const next = li.nextElementSibling || li.previousElementSibling;
			hiddenList.appendChild( li );
			refresh();
			( next ? next.querySelector( '[data-sd-ql-action="hide"]' ) : search ).focus();
		},
		show( li ) {
			home( li );
			refresh();
			li.querySelector( '.sd-ql__tile' ).focus();
		},
	};

	root.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-sd-ql-action]' );
		if ( ! btn ) {
			return;
		}
		actions[ btn.dataset.sdQlAction ]( btn.closest( '[data-sd-link]' ), btn );
		refresh();
		save();
	} );

	/* ---------- Reorder pinned: drag, or arrow keys on the handle ---------- */

	window.Sortable.create( pins, {
		handle: '[data-sd-ql-handle]',
		draggable: '[data-sd-link]',
		animation: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 0 : 150,
		ghostClass: 'is-ghost',
		chosenClass: 'is-chosen',
		onEnd( e ) {
			if ( e.oldIndex !== e.newIndex ) {
				announce( e.item );
				save();
			}
		},
	} );

	pins.addEventListener( 'keydown', ( e ) => {
		const handle = e.target.closest( '[data-sd-ql-handle]' );
		const dir = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[ e.key ];
		if ( ! handle || ! dir ) {
			return;
		}
		e.preventDefault();
		const li = handle.closest( '[data-sd-link]' );
		const target = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
		if ( ! target ) {
			return;
		}
		pins.insertBefore( li, dir < 0 ? target : target.nextElementSibling );
		handle.focus();
		announce( li );
		save();
	} );

	search.addEventListener( 'input', filter );

	restore.addEventListener( 'click', () => {
		confirm( {
			title: __( 'Restore default shortcuts?', 'swift-dashboard-analytics' ),
			message: __( 'All shortcuts will be shown again and the recommended ones will be pinned to your dashboard.', 'swift-dashboard-analytics' ),
			confirmLabel: __( 'Restore defaults', 'swift-dashboard-analytics' ),
		} ).then( ( ok ) => {
			if ( ok ) {
				const all = items();
				all.forEach( ( li ) => {
					setPinned( li, false );
					home( li );
				} );
				defaults.forEach( ( id ) => {
					const li = all.find( ( el ) => el.dataset.sdLink === id );
					if ( li ) {
						setPinned( li, true );
						pins.appendChild( li );
					}
				} );
				refresh();
				save();
			}
			restore.focus();
		} );
	} );

	refresh();
}() );
