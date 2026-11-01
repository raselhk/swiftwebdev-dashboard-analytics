/**
 * SwiftWebDev Dashboard app shell behaviour. No build step; exposes one global: window.swiftdash.
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const data = window.swiftdashData || {};
	const app = document.querySelector( '.sd-app' );
	if ( ! app ) {
		return;
	}

	let prefs = Object.assign( { hidden: [], upgrade_snooze: 0 }, data.prefs );

	/* ---------- Helpers ---------- */

	const iconUrl = ( () => {
		const use = app.querySelector( 'svg.sd-icon use' );
		return use ? use.getAttribute( 'href' ).split( '#' )[ 0 ] : '';
	} )();

	function icon( name ) {
		return '<svg class="sd-icon" aria-hidden="true" focusable="false"><use href="' + iconUrl + '#sd-i-' + name + '"></use></svg>';
	}

	function escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function savePrefs( changes ) {
		prefs = Object.assign( {}, prefs, changes );
		return apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { swiftdash_prefs: prefs } },
		} );
	}

	/* ---------- Toasts (top right, auto-dismiss) ---------- */

	const toastBox = app.querySelector( '.sd-toasts' );
	const toastIcons = { success: 'check-circle', error: 'warning-circle', info: 'info' };

	function toast( message, type = 'info' ) {
		const el = document.createElement( 'div' );
		el.className = 'sd-toast sd-toast--' + type;
		el.setAttribute( 'role', 'error' === type ? 'alert' : 'status' );
		el.innerHTML =
			icon( toastIcons[ type ] || 'info' ) +
			'<p class="sd-toast__text">' + escapeHtml( message ) + '</p>' +
			'<button type="button" class="sd-icon-btn" aria-label="' + escapeHtml( __( 'Dismiss', 'swiftwebdev-dashboard-analytics' ) ) + '">' + icon( 'x' ) + '</button>';
		el.querySelector( 'button' ).addEventListener( 'click', () => el.remove() );
		toastBox.appendChild( el );
		if ( 'error' !== type ) {
			setTimeout( () => el.remove(), 4500 );
		}
	}

	/* ---------- Confirmation dialog ---------- */

	const confirmDialog = document.getElementById( 'sd-confirm' );

	function confirm( { title, message, confirmLabel, danger = false } ) {
		return new Promise( ( resolve ) => {
			confirmDialog.querySelector( '#sd-confirm-title' ).textContent = title;
			confirmDialog.querySelector( '#sd-confirm-text' ).textContent = message;
			const ok = confirmDialog.querySelector( '[data-sd-confirm-ok]' );
			ok.textContent = confirmLabel || __( 'Confirm', 'swiftwebdev-dashboard-analytics' );
			ok.className = 'sd-btn ' + ( danger ? 'sd-btn--danger' : 'sd-btn--primary' );
			confirmDialog.returnValue = '';
			confirmDialog.addEventListener( 'close', () => resolve( 'confirm' === confirmDialog.returnValue ), { once: true } );
			confirmDialog.showModal();
		} );
	}

	/* ---------- Dropdowns ---------- */

	function closeDropdowns( except ) {
		app.querySelectorAll( '[data-sd-dropdown][aria-expanded="true"]' ).forEach( ( btn ) => {
			if ( btn !== except ) {
				btn.setAttribute( 'aria-expanded', 'false' );
				document.getElementById( btn.getAttribute( 'aria-controls' ) ).hidden = true;
			}
		} );
	}

	app.querySelectorAll( '[data-sd-dropdown]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
			const open = 'true' !== btn.getAttribute( 'aria-expanded' );
			closeDropdowns( btn );
			btn.setAttribute( 'aria-expanded', String( open ) );
			const menu = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			menu.hidden = ! open;
			if ( open ) {
				const first = menu.querySelector( 'a, button' );
				if ( first ) {
					first.focus();
				}
			}
		} );
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( ! e.target.closest( '.sd-menu' ) ) {
			closeDropdowns();
		}
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( 'Escape' !== e.key ) {
			return;
		}
		const openBtn = app.querySelector( '[data-sd-dropdown][aria-expanded="true"]' );
		if ( openBtn ) {
			closeDropdowns();
			openBtn.focus();
		}
	} );

	/* ---------- Modals ---------- */

	app.querySelectorAll( '[data-sd-modal-open]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => document.getElementById( btn.dataset.sdModalOpen ).showModal() );
	} );

	/* ---------- Customize panel (widget visibility, saved per user) ---------- */

	const customize = app.querySelector( '[data-sd-customize]' );
	if ( customize ) {
		const dialog = customize.closest( 'dialog' );
		const boxes = () => customize.querySelectorAll( 'input[type="checkbox"]' );

		dialog.addEventListener( 'close', () => {
			if ( 'save' !== dialog.returnValue ) {
				boxes().forEach( ( box ) => ( box.checked = ! prefs.hidden.includes( box.value ) ) );
				return;
			}
			const onScreen = Array.from( boxes() ).map( ( box ) => box.value );
			const hidden = prefs.hidden.filter( ( id ) => ! onScreen.includes( id ) );
			boxes().forEach( ( box ) => {
				if ( ! box.checked ) {
					hidden.push( box.value );
				}
				const card = app.querySelector( '[data-sd-widget="' + box.value + '"]' );
				if ( card ) {
					card.hidden = ! box.checked;
				}
			} );
			savePrefs( { hidden } )
				.then( () => toast( __( 'Dashboard updated.', 'swiftwebdev-dashboard-analytics' ), 'success' ) )
				.catch( () => toast( __( 'Could not save your dashboard layout. Please try again.', 'swiftwebdev-dashboard-analytics' ), 'error' ) );
		} );

		customize.querySelector( '[data-sd-customize-reset]' ).addEventListener( 'click', () => {
			boxes().forEach( ( box ) => ( box.checked = true ) );
		} );
	}

	/* ---------- Period picker: reload with the chosen period ---------- */

	document.querySelectorAll( '[data-sd-range-form]' ).forEach( ( form ) => {
		form.addEventListener( 'change', () => form.submit() );
	} );

	/* ---------- Add-on notice: hide for 30 days ---------- */
	document.querySelectorAll( '[data-sd-dismiss-upgrade]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			document.querySelectorAll( '[data-sd-upgrade]' ).forEach( ( el ) => el.remove() );
			savePrefs( { upgrade_snooze: Math.floor( Date.now() / 1000 ) + 30 * 86400 } ).catch( () => {} );
		} );
	} );

	/* ---------- Sortable tables + client pagination ---------- */

	app.querySelectorAll( 'table[data-sd-table]' ).forEach( ( table ) => {
		const tbody = table.tBodies[ 0 ];
		const pageSize = parseInt( table.dataset.sdPageSize || '0', 10 );
		const pager = pageSize ? table.closest( '.sd-card__body, .sd-table-block' ).querySelector( '.sd-pagination' ) : null;
		let page = 1;

		function paginate() {
			if ( ! pager ) {
				return;
			}
			const rows = Array.from( tbody.rows );
			const pages = Math.max( 1, Math.ceil( rows.length / pageSize ) );
			page = Math.min( page, pages );
			rows.forEach( ( row, i ) => ( row.hidden = i < ( page - 1 ) * pageSize || i >= page * pageSize ) );
			pager.querySelector( '[data-sd-page-status]' ).textContent = sprintf(
				/* translators: 1: current page, 2: total pages. */
				__( 'Page %1$d of %2$d', 'swiftwebdev-dashboard-analytics' ),
				page,
				pages
			);
			pager.querySelector( '[data-sd-page="prev"]' ).disabled = page <= 1;
			pager.querySelector( '[data-sd-page="next"]' ).disabled = page >= pages;
		}

		if ( pager ) {
			pager.querySelector( '[data-sd-page="prev"]' ).addEventListener( 'click', () => {
				page--;
				paginate();
			} );
			pager.querySelector( '[data-sd-page="next"]' ).addEventListener( 'click', () => {
				page++;
				paginate();
			} );
		}

		table.querySelectorAll( 'th .sd-sort' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const th = btn.closest( 'th' );
				const index = Array.from( th.parentNode.children ).indexOf( th );
				const dir = 'ascending' === th.getAttribute( 'aria-sort' ) ? 'descending' : 'ascending';
				table.querySelectorAll( 'th[aria-sort]' ).forEach( ( other ) => other.setAttribute( 'aria-sort', 'none' ) );
				th.setAttribute( 'aria-sort', dir );
				const value = ( row ) => {
					const cell = row.cells[ index ];
					return cell.dataset.sort !== undefined ? parseFloat( cell.dataset.sort ) : cell.textContent.trim().toLowerCase();
				};
				const rows = Array.from( tbody.rows ).sort( ( a, b ) => {
					const x = value( a );
					const y = value( b );
					const cmp = 'number' === typeof x ? x - y : String( x ).localeCompare( String( y ) );
					return 'ascending' === dir ? cmp : -cmp;
				} );
				rows.forEach( ( row ) => tbody.appendChild( row ) );
				page = 1;
				paginate();
			} );
		} );

		paginate();
	} );

	/* ---------- Header search: SwiftWebDev Dashboard + WordPress destinations, then core /wp/v2/search ---------- */

	const search = app.querySelector( '[data-sd-search]' );
	if ( search ) {
		const input = search.querySelector( 'input' );
		const list = search.querySelector( '[role="listbox"]' );
		let timer = null;
		let request = 0;
		let active = -1;

		function options() {
			return Array.from( list.querySelectorAll( '[role="option"]' ) );
		}

		function highlight( index ) {
			const opts = options();
			active = Math.max( -1, Math.min( index, opts.length - 1 ) );
			opts.forEach( ( opt, i ) => opt.classList.toggle( 'is-highlighted', i === active ) );
			if ( active >= 0 ) {
				input.setAttribute( 'aria-activedescendant', opts[ active ].id );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function show( groups ) {
			let html = '';
			let n = 0;
			groups.forEach( ( group ) => {
				if ( ! group.items.length ) {
					return;
				}
				html += '<li class="sd-menu__group" role="presentation">' + escapeHtml( group.label ) + '</li>';
				group.items.forEach( ( item ) => {
					html += '<li role="option" id="sd-search-opt-' + n++ + '" class="sd-menu__item" data-url="' + escapeHtml( item.url ) + '">' + escapeHtml( item.label ) + '</li>';
				} );
			} );
			list.innerHTML = html || '<li class="sd-menu__empty" role="presentation">' + escapeHtml( __( 'No results found.', 'swiftwebdev-dashboard-analytics' ) ) + '</li>';
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			highlight( -1 );
		}

		function hide() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		input.addEventListener( 'input', () => {
			const q = input.value.trim();
			clearTimeout( timer );
			if ( ! q ) {
				hide();
				return;
			}
			const lower = q.toLowerCase();
			const pages = ( data.destinations || [] ).filter( ( d ) => d.label.toLowerCase().includes( lower ) ).slice( 0, 5 );
			const toolsGroup = { label: __( 'Go to', 'swiftwebdev-dashboard-analytics' ), items: pages };
			show( [ toolsGroup ] );
			timer = setTimeout( () => {
				const id = ++request;
				apiFetch( { path: '/wp/v2/search?per_page=5&search=' + encodeURIComponent( q ) } )
					.then( ( results ) => {
						if ( id !== request ) {
							return;
						}
						const content = results.map( ( r ) => ( {
							label: r.title || __( '(no title)', 'swiftwebdev-dashboard-analytics' ),
							url: 'post' === r.type ? 'post.php?post=' + r.id + '&action=edit' : r.url,
						} ) );
						show( [ toolsGroup, { label: __( 'Content', 'swiftwebdev-dashboard-analytics' ), items: content } ] );
					} )
					.catch( () => {} );
			}, 250 );
		} );

		input.addEventListener( 'keydown', ( e ) => {
			if ( list.hidden ) {
				return;
			}
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				highlight( active + 1 );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				highlight( active - 1 );
			} else if ( 'Enter' === e.key && active >= 0 ) {
				e.preventDefault();
				window.location.href = options()[ active ].dataset.url;
			} else if ( 'Escape' === e.key ) {
				hide();
			}
		} );

		list.addEventListener( 'mousedown', ( e ) => {
			const opt = e.target.closest( '[role="option"]' );
			if ( opt ) {
				window.location.href = opt.dataset.url;
			}
		} );

		input.addEventListener( 'blur', () => setTimeout( hide, 150 ) );
	}

	/* ---------- Settings form: Save disabled until changed, saved via core /wp/v2/settings ---------- */

	const form = app.querySelector( '[data-sd-settings-form]' );
	if ( form ) {
		const submit = form.querySelector( '[type="submit"]' );
		const error = form.querySelector( '.sd-form__error' );
		const read = () => {
			const values = {};
			form.querySelectorAll( '[name]' ).forEach( ( el ) => {
				values[ el.name ] = 'checkbox' === el.type ? el.checked : el.value.trim();
			} );
			return values;
		};
		let saved = JSON.stringify( read() );

		const markDirty = () => {
			submit.disabled = JSON.stringify( read() ) === saved;
		};
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'input', markDirty );

		// Menu logo: media library chooser writes the attachment id into a hidden field.
		const logo = form.querySelector( '[data-sd-logo]' );
		if ( logo ) {
			const field = logo.querySelector( 'input[name="brand_logo"]' );
			const preview = logo.querySelector( '.sd-logo-field__preview' );
			const reset = logo.querySelector( '[data-sd-logo-reset]' );
			let frame = null;
			const setLogo = ( id, url ) => {
				field.value = String( id );
				preview.src = url;
				reset.hidden = ! id;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			};
			logo.querySelector( '[data-sd-logo-choose]' ).addEventListener( 'click', () => {
				// wp.media loads after this file, so look it up on click.
				if ( ! frame && wp.media ) {
					frame = wp.media( {
						title: __( 'Choose a menu logo', 'swiftwebdev-dashboard-analytics' ),
						button: { text: __( 'Use this image', 'swiftwebdev-dashboard-analytics' ) },
						library: { type: 'image' },
						multiple: false,
					} );
					frame.on( 'select', () => {
						const img = frame.state().get( 'selection' ).first().toJSON();
						const sizes = img.sizes || {};
						setLogo( img.id, ( sizes.thumbnail || sizes.full || img ).url );
					} );
				}
				if ( frame ) {
					frame.open();
				}
			} );
			reset.addEventListener( 'click', () => setLogo( 0, logo.dataset.default ) );
		}

		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const values = read();
			error.hidden = true;
			submit.disabled = true;
			submit.classList.add( 'is-busy' );
			apiFetch( { path: '/wp/v2/settings', method: 'POST', data: { swiftdash_settings: values } } )
				.then( () => {
					saved = JSON.stringify( values );
					toast( __( 'Settings saved.', 'swiftwebdev-dashboard-analytics' ), 'success' );
				} )
				.catch( ( err ) => {
					error.textContent = sprintf(
						/* translators: %s: error message from the server. */
						__( 'Settings were not saved: %s', 'swiftwebdev-dashboard-analytics' ),
						( err && err.message ) || __( 'unknown error', 'swiftwebdev-dashboard-analytics' )
					);
					error.hidden = false;
					submit.disabled = false;
				} )
				.finally( () => submit.classList.remove( 'is-busy' ) );
		} );
	}

	/* ---------- Error panel retry: widgets listen for swiftdash:retry ---------- */

	app.addEventListener( 'click', ( e ) => {
		const retry = e.target.closest( '[data-sd-retry]' );
		if ( retry ) {
			retry.closest( '[data-sd-widget]' ).dispatchEvent( new CustomEvent( 'swiftdash:retry', { bubbles: true } ) );
		}
	} );

	window.swiftdash = { toast, confirm, icon, savePrefs };
}() );
