/* Swift Dash analytics: apply the date range on change; keep the active tab visible on narrow screens. */
( function () {
	const form = document.querySelector( '.swiftdash [data-sd-range-form]' );
	if ( form ) {
		form.addEventListener( 'change', () => form.submit() );
	}
	const nav = document.querySelector( '.swiftdash .sd-an-tabs' );
	const active = nav && nav.querySelector( '[aria-current="page"]' );
	if ( active && nav.scrollWidth > nav.clientWidth ) {
		nav.scrollLeft = active.offsetLeft - 16;
	}
} )();
