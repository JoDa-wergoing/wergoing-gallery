/**
 * expand-gallery.js
 *
 * Opens a HiGallery album in PhotoSwipe when the user clicks an album card.
 * PhotoSwipe is loaded from the local plugin assets — no external CDN.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.higallery-album-card' ).forEach( function ( card ) {
		card.addEventListener( 'click', function () {
			const images = JSON.parse( this.dataset.images || '[]' );

			if ( images.length === 0 ) {
				return;
			}

			// PhotoSwipe UMD builds are enqueued via wp_enqueue_script()
			// and exposed as globals — no dynamic import() needed.
			if ( typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined' ) {
				console.warn( 'weRgoing Gallery: PhotoSwipe is not loaded.' );
				return;
			}

			const lightbox = new PhotoSwipeLightbox( {
				dataSource: images,
				pswpModule: PhotoSwipe,
			} );

			lightbox.init();
			lightbox.loadAndOpen( 0 );
		} );
	} );
} );
