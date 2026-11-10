/**
 * Navigation search shared by the dashboard header and the toolbar: commands (Quick Links + admin
 * menu) instantly, then content and users from swiftdash/v1/search. Exposes window.swiftdashSearch.
 */
( function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;

	function escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Wire one search box.
	 *
	 * @param {Object} els  input, panel, list (listbox), status (live region), root (focus leaving it closes).
	 * @param {Object} opts commands (array, or function returning a Promise of one, called once),
	 *                      adminUrl, iconUrl, userId (moves the old browser-only recent list once),
	 *                      getRecent(), saveRecent( list, fetchOptions ) => Promise, onEscape() (optional).
	 * @return {Object} { hide }
	 */
	window.swiftdashSearch = function ( els, opts ) {
		const { input, panel, list, status, root } = els;
		let commands = Array.isArray( opts.commands ) ? opts.commands : null;
		let loading = null;
		let shown = [];
		let active = -1;
		let timer = null;
		let request = 0;

		function icon( name ) {
			return '<svg class="sd-icon" aria-hidden="true" focusable="false"><use href="' + opts.iconUrl + '#sd-i-' + name + '"></use></svg>';
		}

		// Recently opened results, saved per account in swiftdash_prefs (admin pages of this site only, max 5).
		const isAdminUrl = ( url ) => !! opts.adminUrl && 0 === String( url ).indexOf( opts.adminUrl );
		const recent = {
			get() {
				return opts.getRecent() || [];
			},
			merge( items ) {
				const seen = {};
				return items
					.filter( ( r ) => r && r.label && isAdminUrl( r.url ) && ! seen[ r.url ] && ( seen[ r.url ] = true ) )
					.slice( 0, 5 )
					.map( ( r ) => ( { label: String( r.label ), url: r.url, hint: String( r.hint || '' ), icon: r.icon || 'arrow-square-out', tone: r.tone || 'neutral' } ) );
			},
			// keepalive: the save survives the page navigating away right after.
			add( item ) {
				opts.saveRecent( recent.merge( [ item ].concat( recent.get() ) ), { keepalive: true } ).catch( () => {} );
			},
		};

		// One-time move from the 1.0.x browser-only list (localStorage) to the account.
		if ( opts.userId ) {
			try {
				const key = 'swiftdash_recent_' + opts.userId;
				const local = JSON.parse( window.localStorage.getItem( key ) || '[]' );
				if ( Array.isArray( local ) && local.length ) {
					opts.saveRecent( recent.merge( recent.get().concat( local ) ) )
						.then( () => window.localStorage.removeItem( key ) )
						.catch( () => {} );
				}
			} catch ( e ) {}
		}

		// Exact > starts with > a word starts with > parent menu starts with > contains > every word found > description.
		// Menu entries rank on their own name: "Settings: General" ranks on "general", not on "Settings".
		function score( item, q ) {
			const full = item.label.toLowerCase();
			const label = full.includes( ': ' ) ? full.slice( full.lastIndexOf( ': ' ) + 2 ) : full;
			if ( label === q ) {
				return 100;
			}
			if ( label.startsWith( q ) ) {
				return 80;
			}
			if ( label.split( /[\s/&-]+/ ).some( ( word ) => word.startsWith( q ) ) ) {
				return 60;
			}
			if ( full.startsWith( q ) ) {
				return 50;
			}
			if ( full.includes( q ) ) {
				return 40;
			}
			if ( q.split( /\s+/ ).every( ( word ) => full.includes( word ) ) ) {
				return 30;
			}
			return ( item.hint || '' ).toLowerCase().includes( q ) ? 20 : 0;
		}

		function mark( text, q ) {
			const at = q ? text.toLowerCase().indexOf( q ) : -1;
			if ( at < 0 ) {
				return escapeHtml( text );
			}
			return escapeHtml( text.slice( 0, at ) ) + '<mark>' + escapeHtml( text.slice( at, at + q.length ) ) + '</mark>' + escapeHtml( text.slice( at + q.length ) );
		}

		function fallbacks( raw ) {
			const base = opts.adminUrl || '';
			const q = encodeURIComponent( raw );
			return [
				{ label: __( 'All posts', 'swiftwebdev-dashboard-analytics' ), icon: 'note-fill', tone: 'sky', url: base + 'edit.php?s=' + q },
				{ label: __( 'All pages', 'swiftwebdev-dashboard-analytics' ), icon: 'files-fill', tone: 'purple', url: base + 'edit.php?post_type=page&s=' + q },
				{ label: __( 'Media library', 'swiftwebdev-dashboard-analytics' ), icon: 'images-fill', tone: 'green', url: base + 'upload.php?mode=list&s=' + q },
			];
		}

		function highlight( index, scroll = true ) {
			const options = list.querySelectorAll( '[role="option"]' );
			active = options.length ? ( index + options.length ) % options.length : -1;
			options.forEach( ( opt, i ) => {
				opt.classList.toggle( 'is-highlighted', i === active );
				opt.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
			} );
			if ( active < 0 ) {
				input.removeAttribute( 'aria-activedescendant' );
				return;
			}
			input.setAttribute( 'aria-activedescendant', options[ active ].id );
			if ( scroll ) {
				options[ active ].scrollIntoView( { block: 'nearest' } );
			}
		}

		// Groups: { label, items } | { loading: text } | { note: text }.
		function render( groups, q ) {
			let html = '';
			shown = [];
			groups.forEach( ( group ) => {
				if ( group.loading ) {
					html += '<li class="sd-search__loading" role="presentation"><span class="sd-search__spinner" aria-hidden="true"></span>' + escapeHtml( group.loading ) + '</li>';
					return;
				}
				if ( group.note ) {
					html += '<li class="sd-menu__empty" role="presentation">' + escapeHtml( group.note ) + '</li>';
					return;
				}
				if ( ! group.items.length ) {
					return;
				}
				html += '<li class="sd-menu__group" role="presentation">' + escapeHtml( group.label ) + '</li>';
				group.items.forEach( ( item ) => {
					const n = shown.push( item ) - 1;
					const media = item.thumb
						? '<img class="sd-search__thumb" src="' + escapeHtml( item.thumb ) + '" alt="" width="32" height="32" loading="lazy">'
						: '<span class="sd-search__chip sd-ql-tone--' + escapeHtml( item.tone || 'neutral' ) + '">' + icon( item.icon || 'arrow-square-out' ) + '</span>';
					html +=
						'<li role="option" id="' + list.id.replace( '-results', '' ) + '-opt-' + n + '" class="sd-search__opt" aria-selected="false" data-i="' + n + '">' +
						media +
						'<span class="sd-search__text"><span class="sd-search__label">' + mark( item.label, q ) + '</span>' +
						( item.hint ? '<span class="sd-search__hint">' + escapeHtml( item.hint ) + '</span>' : '' ) +
						'</span>' +
						( item.status ? '<span class="sd-badge sd-search__badge">' + escapeHtml( item.status ) + '</span>' : '' ) +
						'<span class="sd-search__enter" aria-hidden="true">' + escapeHtml( __( 'Open', 'swiftwebdev-dashboard-analytics' ) ) + '</span>' +
						'</li>';
				} );
			} );
			list.innerHTML = html || '<li class="sd-menu__empty" role="presentation">' + escapeHtml( __( 'Type at least 2 letters to search posts, pages and users.', 'swiftwebdev-dashboard-analytics' ) ) + '</li>';
			panel.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			highlight( 0, false );
			if ( ! groups.some( ( group ) => group.loading ) ) {
				/* translators: %d: number of search results. */
				status.textContent = sprintf( _n( '%d result', '%d results', shown.length, 'swiftwebdev-dashboard-analytics' ), shown.length );
			}
		}

		function hide() {
			clearTimeout( timer );
			request++;
			panel.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			active = -1;
		}

		function go( item, newTab ) {
			if ( ! item ) {
				return;
			}
			recent.add( item );
			if ( newTab ) {
				window.open( item.url, '_blank', 'noopener' );
				return;
			}
			window.location.href = item.url;
		}

		// Commands given as a loader (toolbar) are fetched the first time the search opens.
		function loadCommands() {
			if ( ! loading ) {
				loading = Promise.resolve( opts.commands() )
					.then( ( items ) => ( commands = items || [] ) )
					.catch( () => ( commands = [] ) )
					.then( () => document.activeElement === input && update() );
			}
		}

		function update() {
			const raw = input.value.trim();
			const q = raw.toLowerCase();
			clearTimeout( timer );
			const id = ++request;
			if ( ! commands ) {
				loadCommands();
				render( [ { loading: __( 'Loading…', 'swiftwebdev-dashboard-analytics' ) } ], q );
				return;
			}
			if ( ! q ) {
				render( [
					{ label: __( 'Recent', 'swiftwebdev-dashboard-analytics' ), items: recent.get() },
					{ label: __( 'Quick actions', 'swiftwebdev-dashboard-analytics' ), items: commands.filter( ( c ) => c.suggest ) },
				], '' );
				return;
			}
			const top = commands
				.map( ( c ) => [ score( c, q ), c ] )
				.filter( ( s ) => s[ 0 ] )
				.sort( ( a, b ) => b[ 0 ] - a[ 0 ] )
				.slice( 0, 6 )
				.map( ( s ) => s[ 1 ] );
			const goTo = { label: __( 'Go to', 'swiftwebdev-dashboard-analytics' ), items: top };
			if ( q.length < 2 ) {
				render( [ goTo ], q );
				return;
			}
			render( [ goTo, { loading: __( 'Searching posts, pages and users…', 'swiftwebdev-dashboard-analytics' ) } ], q );
			timer = setTimeout( () => {
				wp.apiFetch( { path: '/swiftdash/v1/search?q=' + encodeURIComponent( raw ) } )
					.then( ( res ) => {
						if ( id !== request ) {
							return;
						}
						const content = res.content || [];
						const users = res.users || [];
						const groups = [
							goTo,
							{ label: __( 'Content', 'swiftwebdev-dashboard-analytics' ), items: content },
							{ label: __( 'Users', 'swiftwebdev-dashboard-analytics' ), items: users },
						];
						if ( ! top.length && ! content.length && ! users.length ) {
							/* translators: %s: what the user searched for. */
							groups.push( { note: sprintf( __( 'No results for “%s”.', 'swiftwebdev-dashboard-analytics' ), raw ) } );
							groups.push( { label: __( 'Search for it in', 'swiftwebdev-dashboard-analytics' ), items: fallbacks( raw ) } );
						}
						render( groups, q );
					} )
					.catch( () => {
						if ( id === request ) {
							render( [ goTo, { note: __( 'Could not search content right now. Please try again.', 'swiftwebdev-dashboard-analytics' ) } ], q );
						}
					} );
			}, 200 );
		}

		input.addEventListener( 'input', update );
		input.addEventListener( 'focus', () => panel.hidden && update() );

		input.addEventListener( 'keydown', ( e ) => {
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				if ( panel.hidden ) {
					update();
					return;
				}
				highlight( active + ( 'ArrowDown' === e.key ? 1 : -1 ) );
			} else if ( 'Enter' === e.key && ! panel.hidden && active >= 0 ) {
				e.preventDefault();
				go( shown[ active ], e.ctrlKey || e.metaKey );
			} else if ( 'Escape' === e.key ) {
				e.preventDefault();
				if ( ! panel.hidden ) {
					hide();
				} else if ( input.value ) {
					input.value = '';
				} else if ( opts.onEscape ) {
					opts.onEscape();
				} else {
					input.blur();
				}
			} else if ( 'Tab' === e.key ) {
				hide();
			}
		} );

		// Keep focus in the input while clicking a result.
		list.addEventListener( 'mousedown', ( e ) => e.preventDefault() );
		list.addEventListener( 'click', ( e ) => {
			const opt = e.target.closest( '[role="option"]' );
			if ( opt ) {
				go( shown[ +opt.dataset.i ], e.ctrlKey || e.metaKey );
			}
		} );
		list.addEventListener( 'auxclick', ( e ) => {
			const opt = e.target.closest( '[role="option"]' );
			if ( opt && 1 === e.button ) {
				go( shown[ +opt.dataset.i ], true );
			}
		} );
		list.addEventListener( 'mousemove', ( e ) => {
			const opt = e.target.closest( '[role="option"]' );
			if ( opt && +opt.dataset.i !== active ) {
				highlight( +opt.dataset.i, false );
			}
		} );

		root.addEventListener( 'focusout', ( e ) => {
			if ( ! root.contains( e.relatedTarget ) ) {
				hide();
			}
		} );

		return { hide };
	};
}() );
