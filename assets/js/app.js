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

	let prefs = Object.assign( { hidden: [], upgrade_snooze: 0, review_snooze: 0, welcomed: false, recent: [], pro_tips_hidden: false }, data.prefs );

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

	// Extra fetch options, e.g. { keepalive: true } when the page is about to unload.
	function savePrefs( changes, extra ) {
		prefs = Object.assign( {}, prefs, changes );
		return apiFetch( Object.assign( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { swiftdash_prefs: prefs } },
		}, extra ) );
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

	/* ---------- PRO tips on the AI cards: hide all of them for this user ---------- */
	document.querySelectorAll( '[data-sd-hide-pro-tips]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			document.querySelectorAll( '[data-sd-pro-tip]' ).forEach( ( el ) => el.remove() );
			savePrefs( { pro_tips_hidden: true } ).catch( () => {} );
		} );
	} );

	/* ---------- Review request: leave a review / already did = for good, maybe later = 30 days ---------- */
	document.querySelectorAll( '[data-sd-review-done], [data-sd-review-later]' ).forEach( ( el ) => {
		el.addEventListener( 'click', () => {
			const now = Math.floor( Date.now() / 1000 );
			document.querySelectorAll( '[data-sd-review]' ).forEach( ( card ) => card.remove() );
			savePrefs( { review_snooze: el.hasAttribute( 'data-sd-review-later' ) ? now + 30 * 86400 : 2147483647 } ).catch( () => {} );
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

	/* ---------- Header search: shared with the toolbar (search.js) ---------- */

	const search = app.querySelector( '[data-sd-search]' );
	if ( search && window.swiftdashSearch ) {
		window.swiftdashSearch(
			{
				root: search,
				input: search.querySelector( 'input' ),
				panel: search.querySelector( '.sd-search__panel' ),
				list: search.querySelector( '[role="listbox"]' ),
				status: search.querySelector( '[data-sd-search-status]' ),
			},
			{
				commands: data.commands || [],
				adminUrl: data.adminUrl,
				iconUrl,
				userId: data.userId || 0,
				getRecent: () => prefs.recent || [],
				saveRecent: ( recent, extra ) => savePrefs( { recent }, extra ),
			}
		);
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

		// Report address: empty (site admin email) or a valid email. Errors show in red under the form.
		const email = form.querySelector( '[name="report_email"]' );
		const emailOk = () => {
			const ok = ! email || ! email.value.trim() || email.checkValidity();
			if ( email ) {
				email.setAttribute( 'aria-invalid', ok ? 'false' : 'true' );
			}
			error.textContent = ok ? '' : __( 'Enter a valid email address for the weekly report, or leave it empty.', 'swiftwebdev-dashboard-analytics' );
			error.hidden = ok;
			return ok;
		};
		if ( email ) {
			email.addEventListener( 'input', () => 'true' === email.getAttribute( 'aria-invalid' ) && emailOk() );
		}

		const test = form.querySelector( '[data-sd-report-test]' );
		if ( test ) {
			test.addEventListener( 'click', () => {
				if ( ! emailOk() ) {
					email.focus();
					return;
				}
				test.disabled = true;
				test.classList.add( 'is-busy' );
				apiFetch( { path: '/swiftdash/v1/report/test', method: 'POST', data: { email: email.value.trim() } } )
					/* translators: %s: email address. */
					.then( ( res ) => toast( sprintf( __( 'Test report sent to %s.', 'swiftwebdev-dashboard-analytics' ), res.to ), 'success' ) )
					.catch( ( err ) => toast( ( err && err.message ) || __( 'Could not send the test report. Please try again.', 'swiftwebdev-dashboard-analytics' ), 'error' ) )
					.finally( () => {
						test.disabled = false;
						test.classList.remove( 'is-busy' );
					} );
			} );
		}

		// First-run welcome card: "Yes" turns on open after login, both answers save the report choice.
		const welcome = app.querySelector( '[data-sd-welcome]' );
		if ( welcome ) {
			const buttons = welcome.querySelectorAll( '[data-sd-welcome-answer]' );
			buttons.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const values = Object.assign( JSON.parse( saved ), { weekly_report: welcome.querySelector( '[data-sd-welcome-report]' ).checked } );
					if ( 'yes' === btn.dataset.sdWelcomeAnswer ) {
						values.open_after_login = true;
					}
					buttons.forEach( ( b ) => ( b.disabled = true ) );
					btn.classList.add( 'is-busy' );
					apiFetch( { path: '/wp/v2/settings', method: 'POST', data: { swiftdash_settings: values } } )
						.then( () => savePrefs( { welcomed: true } ) )
						.then( () => ( window.location.href = welcome.dataset.dashboard ) )
						.catch( ( err ) => {
							toast( ( err && err.message ) || __( 'Could not save your choice. Please try again.', 'swiftwebdev-dashboard-analytics' ), 'error' );
							buttons.forEach( ( b ) => ( b.disabled = false ) );
							btn.classList.remove( 'is-busy' );
						} );
				} );
			} );
		}

		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			if ( ! emailOk() ) {
				email.focus();
				return;
			}
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
