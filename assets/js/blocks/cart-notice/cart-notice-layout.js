/** Match the product column without relocating or changing WooCommerce's DOM. */
export const alignCartNotice = ( mount ) => {
	const cart = mount?.nextElementSibling;
	if ( ! cart?.classList.contains( 'wp-block-woocommerce-cart' ) ) {
		return () => {};
	}
	let observedColumn;
	let observer;
	const sync = () => {
		const column = cart.querySelector( '.wc-block-cart-items' ) || cart.querySelector( '.wc-block-cart__main' );
		if ( column !== observedColumn ) {
			if ( observedColumn ) observer?.unobserve( observedColumn );
			if ( column ) observer?.observe( column );
			observedColumn = column;
		}
		if ( ! column ) return;
		const bounds = column.getBoundingClientRect();
		const container = mount.getBoundingClientRect();
		if ( bounds.width <= 0 || container.width <= 0 ) return;
		const rtl = window.getComputedStyle( mount ).direction === 'rtl';
		const offset = Math.max( 0, rtl ? container.right - bounds.right : bounds.left - container.left );
		const width = Math.min( bounds.width, container.width - offset );
		if ( width <= 0 ) return;
		mount.style.setProperty( '--automaticffl-cart-notice-width', `${ width }px` );
		mount.style.setProperty( '--automaticffl-cart-notice-offset', `${ offset }px` );
	};
	if ( typeof window.ResizeObserver === 'function' ) {
		observer = new window.ResizeObserver( sync );
		observer.observe( cart );
		observer.observe( mount );
	}
	window.addEventListener( 'resize', sync );
	sync();
	return () => {
		observer?.disconnect();
		window.removeEventListener( 'resize', sync );
		mount.style.removeProperty( '--automaticffl-cart-notice-width' );
		mount.style.removeProperty( '--automaticffl-cart-notice-offset' );
	};
};
