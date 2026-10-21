/**
 * Sheet Music Librarian front end: instant instrument/search filtering,
 * remembered instrument, "New"/"Updated" badges, and "download all parts" as a zip.
 * The server renders every piece and marks filtered ones hidden, so this only toggles.
 */
( function () {
	'use strict';

	var INSTRUMENT_KEY = 'osmInstrument';
	var RECENT_DAYS = 14;          // badge window for a first-time visitor
	var VISIT_GAP = 30 * 60;       // seconds of inactivity that start a new visit

	function storageGet( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function storageSet( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
	}

	function nowSeconds() {
		return Math.floor( Date.now() / 1000 );
	}

	// Start of the visitor's previous visit to this page (0 if none). Kept stable
	// for the whole visit so badges don't vanish on reload.
	function previousVisit() {
		var key = 'osmVisits:' + window.location.pathname;
		var now = nowSeconds();
		var visits = {};
		try {
			visits = JSON.parse( storageGet( key ) ) || {};
		} catch ( e ) {}

		if ( ! visits.last || now - visits.last > VISIT_GAP ) {
			visits.prev = visits.last || 0;
		}
		visits.last = now;
		storageSet( key, JSON.stringify( visits ) );
		return visits.prev || 0;
	}

	function addBadges( library, since ) {
		library.querySelectorAll( '.osm-piece' ).forEach( function ( piece ) {
			var label = null;
			if ( Number( piece.dataset.published ) > since ) {
				label = 'New';
			} else if ( Number( piece.dataset.modified ) > since ) {
				label = 'Updated';
			}
			if ( ! label ) return;

			var badge = document.createElement( 'span' );
			badge.className = 'osm-badge osm-badge-' + label.toLowerCase();
			badge.textContent = label;
			piece.querySelector( 'h3' ).appendChild( badge );
		} );
	}

	// Same rule as the server: a file shows for the selected instrument, its
	// children, and its parents.
	function isAncestor( parents, ancestor, id ) {
		if ( ! ancestor ) return false;
		for ( var depth = 0; id && depth < 50; depth++ ) {
			id = parents[ id ] || 0;
			if ( id === ancestor ) return true;
		}
		return false;
	}

	function fileMatches( parents, selected, instrument ) {
		return ! selected ||
			instrument === selected ||
			isAncestor( parents, selected, instrument ) ||
			isAncestor( parents, instrument, selected );
	}

	function applyFilter( library ) {
		var parents = library._osmParents;
		var select = library.querySelector( '.osm-instrument-select' );
		var search = library.querySelector( '.osm-search' );
		var selected = select ? parseInt( select.value, 10 ) || 0 : 0;
		var words = search ? search.value.toLowerCase().split( /\s+/ ).filter( Boolean ) : [];
		var shownPieces = 0;
		var parts = [];

		library.querySelectorAll( '.osm-piece' ).forEach( function ( piece ) {
			var files = Array.prototype.slice.call( piece.querySelectorAll( '.osm-file' ) );
			var shownFiles = files.filter( function ( file ) {
				var show = fileMatches( parents, selected, parseInt( file.dataset.instrument, 10 ) || 0 );
				file.hidden = ! show;
				return show;
			} );

			var composer = piece.querySelector( '.osm-meta-composer' );
			var text = ( piece.querySelector( '.osm-piece-title' ).textContent + ' ' +
				( composer ? composer.textContent : '' ) ).toLowerCase();
			var show = words.every( function ( w ) {
				return text.indexOf( w ) !== -1;
			} ) && ( ! selected || shownFiles.length > 0 );

			piece.hidden = ! show;
			if ( show ) {
				shownPieces++;
				shownFiles.forEach( function ( file ) {
					parts.push( { link: file, piece: piece } );
				} );
			}
		} );

		library.querySelector( '.osm-no-results' ).hidden = shownPieces > 0;

		// "Download all parts" only makes sense once an instrument is chosen.
		var button = library.querySelector( '.osm-download-all' );
		library._osmParts = parts;
		if ( button ) {
			var name = selected ? select.options[ select.selectedIndex ].text.replace( /^(—\s*)+/, '' ) : '';
			library._osmInstrumentName = name;
			button.hidden = ! selected || ! parts.length;
			button.textContent = 'Download all ' + name + ' parts (' + parts.length + ')';
		}
	}

	// ---- Zip (stored, uncompressed: PDFs are already compressed) ----

	var CRC_TABLE = ( function () {
		var table = new Uint32Array( 256 );
		for ( var n = 0; n < 256; n++ ) {
			var c = n;
			for ( var k = 0; k < 8; k++ ) {
				c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
			}
			table[ n ] = c >>> 0;
		}
		return table;
	} )();

	function crc32( bytes ) {
		var c = 0xffffffff;
		for ( var i = 0; i < bytes.length; i++ ) {
			c = CRC_TABLE[ ( c ^ bytes[ i ] ) & 0xff ] ^ ( c >>> 8 );
		}
		return ( c ^ 0xffffffff ) >>> 0;
	}

	function buildZip( files ) {
		var encoder = new TextEncoder();
		var now = new Date();
		var dosTime = ( now.getHours() << 11 ) | ( now.getMinutes() << 5 ) | ( now.getSeconds() >> 1 );
		var dosDate = ( ( now.getFullYear() - 1980 ) << 9 ) | ( ( now.getMonth() + 1 ) << 5 ) | now.getDate();
		var chunks = [];
		var central = [];
		var centralSize = 0;
		var offset = 0;

		files.forEach( function ( file ) {
			var name = encoder.encode( file.name );
			var crc = crc32( file.data );
			var size = file.data.length;

			var local = new DataView( new ArrayBuffer( 30 ) );
			local.setUint32( 0, 0x04034b50, true );  // local file header
			local.setUint16( 4, 20, true );          // version needed
			local.setUint16( 6, 0x0800, true );      // UTF-8 file names
			local.setUint16( 8, 0, true );           // stored
			local.setUint16( 10, dosTime, true );
			local.setUint16( 12, dosDate, true );
			local.setUint32( 14, crc, true );
			local.setUint32( 18, size, true );
			local.setUint32( 22, size, true );
			local.setUint16( 26, name.length, true );
			chunks.push( local.buffer, name, file.data );

			var entry = new DataView( new ArrayBuffer( 46 ) );
			entry.setUint32( 0, 0x02014b50, true );  // central directory header
			entry.setUint16( 4, 20, true );          // version made by
			entry.setUint16( 6, 20, true );          // version needed
			entry.setUint16( 8, 0x0800, true );
			entry.setUint16( 10, 0, true );
			entry.setUint16( 12, dosTime, true );
			entry.setUint16( 14, dosDate, true );
			entry.setUint32( 16, crc, true );
			entry.setUint32( 20, size, true );
			entry.setUint32( 24, size, true );
			entry.setUint16( 28, name.length, true );
			entry.setUint32( 42, offset, true );     // offset of local header
			central.push( entry.buffer, name );
			centralSize += 46 + name.length;

			offset += 30 + name.length + size;
		} );

		var end = new DataView( new ArrayBuffer( 22 ) );
		end.setUint32( 0, 0x06054b50, true );        // end of central directory
		end.setUint16( 8, files.length, true );
		end.setUint16( 10, files.length, true );
		end.setUint32( 12, centralSize, true );
		end.setUint32( 16, offset, true );

		return new Blob( chunks.concat( central, [ end.buffer ] ), { type: 'application/zip' } );
	}

	function safeName( name ) {
		return name.replace( /[\\/:*?"<>|]+/g, '-' ).replace( /\s+/g, ' ' ).trim();
	}

	function extensionOf( link ) {
		if ( link.dataset.ext ) return link.dataset.ext;
		var match = new URL( link.href, window.location.href ).pathname.match( /\.[A-Za-z0-9]{1,5}$/ );
		return match ? match[ 0 ] : '';
	}

	async function downloadAll( library, button, status ) {
		var parts = library._osmParts || [];
		if ( ! parts.length ) return;

		button.disabled = true;
		var used = {};
		var files = [];

		try {
			for ( var i = 0; i < parts.length; i++ ) {
				status.textContent = 'Preparing ' + ( i + 1 ) + ' of ' + parts.length + '…';
				var link = parts[ i ].link;
				var response = await fetch( link.href, { credentials: 'same-origin' } );
				if ( ! response.ok ) throw new Error( 'HTTP ' + response.status );

				var base = safeName( parts[ i ].piece.querySelector( '.osm-piece-title' ).textContent + ' - ' + link.textContent );
				var name = base + extensionOf( link );
				for ( var n = 2; used[ name.toLowerCase() ]; n++ ) {
					name = base + ' (' + n + ')' + extensionOf( link );
				}
				used[ name.toLowerCase() ] = true;

				files.push( { name: name, data: new Uint8Array( await response.arrayBuffer() ) } );
			}

			var url = URL.createObjectURL( buildZip( files ) );
			var a = document.createElement( 'a' );
			a.href = url;
			a.download = safeName( library.dataset.zipName + ' - ' + library._osmInstrumentName ) + '.zip';
			document.body.appendChild( a );
			a.click();
			a.remove();
			setTimeout( function () {
				URL.revokeObjectURL( url );
			}, 60000 );
			status.textContent = '';
		} catch ( e ) {
			status.textContent = 'Sorry, the zip couldn’t be created. Please download the parts individually.';
		}

		button.disabled = false;
	}

	function init( library, since ) {
		var form = library.querySelector( '.osm-filter-form' );
		var select = library.querySelector( '.osm-instrument-select' );
		var search = library.querySelector( '.osm-search' );
		var submit = library.querySelector( '.osm-filter-submit' );
		var button = library.querySelector( '.osm-download-all' );
		var status = library.querySelector( '.osm-download-status' );

		try {
			library._osmParents = JSON.parse( library.dataset.instrumentParents || '{}' );
		} catch ( e ) {
			library._osmParents = {};
		}

		if ( submit ) submit.hidden = true;

		if ( select ) {
			// An instrument in the URL wins; otherwise restore the visitor's last choice.
			var params = new URLSearchParams( window.location.search );
			var saved = storageGet( INSTRUMENT_KEY );
			if ( params.has( 'osm_instrument_filter' ) ) {
				storageSet( INSTRUMENT_KEY, select.value );
			} else if ( saved !== null && select.querySelector( 'option[value="' + saved.replace( /\D/g, '' ) + '"]' ) ) {
				select.value = saved.replace( /\D/g, '' );
			}

			select.addEventListener( 'change', function () {
				storageSet( INSTRUMENT_KEY, select.value );
				applyFilter( library );
			} );
		}

		if ( search ) {
			search.addEventListener( 'input', function () {
				applyFilter( library );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			applyFilter( library );
		} );

		if ( button ) {
			button.addEventListener( 'click', function () {
				downloadAll( library, button, status );
			} );
		}

		addBadges( library, since );
		applyFilter( library );
	}

	function start() {
		var libraries = document.querySelectorAll( '.osm-library' );
		if ( ! libraries.length ) return;

		var since = previousVisit() || nowSeconds() - RECENT_DAYS * 86400;
		libraries.forEach( function ( library ) {
			init( library, since );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
