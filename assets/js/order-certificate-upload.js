/* global jQuery */

( function ( $, config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	let orderId = 0;
	let selectedFiles = [];
	let failedFiles = [];
	let checkCount = 0;
	let checkTimer = null;

	function modalElement() {
		return $( '.automaticffl-certificate-modal' ).last();
	}

	function setStatus( message, isError ) {
		const status = modalElement().find( '#automaticffl-upload-status' );
		status
			.text( message || '' )
			.toggleClass( 'automaticffl-error', !! isError );
	}

	function showProgress( percent ) {
		const progress = modalElement().find( '#automaticffl-upload-progress' );
		progress
			.prop( 'hidden', false )
			.val( Math.max( 0, Math.min( 100, percent ) ) );
	}

	function countMessage( template, count ) {
		return String( template ).replace( '%d', count );
	}

	function request( path, options ) {
		const requestOptions = options || {};
		requestOptions.credentials = 'same-origin';
		requestOptions.headers = $.extend(
			{
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json',
			},
			requestOptions.headers || {}
		);

		return window
			.fetch(
				config.restBase.replace( /\/$/, '' ) + path,
				requestOptions
			)
			.then( function ( response ) {
				return response
					.json()
					.catch( function () {
						return {};
					} )
					.then( function ( body ) {
						if ( ! response.ok ) {
							const error = new Error(
								body.message || 'Request failed.'
							);
							error.status = response.status;
							throw error;
						}
						return body;
					} );
			} );
	}

	function fileDescriptors( files ) {
		return files.map( function ( file ) {
			return {
				name: file.name,
				size: file.size,
				type: file.type || 'application/octet-stream',
			};
		} );
	}

	function createSessions( files ) {
		return request( '/orders/' + orderId + '/certificate-uploads', {
			method: 'POST',
			body: JSON.stringify( { files: fileDescriptors( files ) } ),
		} ).then( function ( body ) {
			if (
				! Array.isArray( body.uploads ) ||
				body.uploads.length !== files.length
			) {
				throw new Error(
					'Automatic FFL returned an incomplete upload session.'
				);
			}
			return body.uploads;
		} );
	}

	function uploadFile( file, session, onProgress ) {
		return new Promise( function ( resolve, reject ) {
			const xhr = new window.XMLHttpRequest();
			xhr.open( 'PUT', session.upload_url, true );
			xhr.setRequestHeader(
				'Content-Type',
				session.content_type || file.type || 'application/octet-stream'
			);
			xhr.setRequestHeader(
				'Content-Range',
				'bytes 0-' + ( file.size - 1 ) + '/' + file.size
			);
			xhr.upload.addEventListener( 'progress', function ( event ) {
				if ( event.lengthComputable ) {
					onProgress( event.loaded );
				}
			} );
			xhr.addEventListener( 'load', function () {
				if ( xhr.status >= 200 && xhr.status < 300 ) {
					onProgress( file.size );
					resolve();
				} else {
					reject(
						new Error(
							'Upload failed with status ' + xhr.status + '.'
						)
					);
				}
			} );
			xhr.addEventListener( 'error', function () {
				reject( new Error( 'Upload failed.' ) );
			} );
			xhr.addEventListener( 'abort', function () {
				reject( new Error( 'Upload canceled.' ) );
			} );
			xhr.send( file );
		} );
	}

	function uploadBatch( files, sessions ) {
		const progressByIndex = {};
		const totalBytes = files.reduce( function ( total, file ) {
			return total + file.size;
		}, 0 );
		let nextIndex = 0;
		const failures = [];
		let successes = 0;

		function updateAggregate() {
			const uploaded = Object.keys( progressByIndex ).reduce( function (
				total,
				key
			) {
				return total + progressByIndex[ key ];
			}, 0 );
			showProgress(
				totalBytes ? Math.round( ( uploaded * 100 ) / totalBytes ) : 0
			);
		}

		function worker() {
			const index = nextIndex++;
			if ( index >= files.length ) {
				return Promise.resolve();
			}

			progressByIndex[ index ] = 0;
			return uploadFile(
				files[ index ],
				sessions[ index ],
				function ( loaded ) {
					progressByIndex[ index ] = loaded;
					updateAggregate();
				}
			)
				.then( function () {
					successes++;
				} )
				.catch( function () {
					progressByIndex[ index ] = 0;
					failures.push( files[ index ] );
					updateAggregate();
				} )
				.then( worker );
		}

		const workers = [];
		for ( let i = 0; i < Math.min( 3, files.length ); i++ ) {
			workers.push( worker() );
		}

		return Promise.all( workers ).then( function () {
			return { failures, successes };
		} );
	}

	function beginUpload( files ) {
		const modal = modalElement();
		const start = modal.find( '#automaticffl-start-upload' );
		const retry = modal.find( '#automaticffl-retry-upload' );
		const more = modal.find( '#automaticffl-upload-more' );

		start.prop( 'disabled', true );
		retry.prop( 'hidden', true );
		more.prop( 'hidden', true );
		setStatus( countMessage( config.preparing, files.length ), false );
		showProgress( 0 );

		createSessions( files )
			.then( function ( sessions ) {
				setStatus(
					countMessage( config.uploading, files.length ),
					false
				);
				return uploadBatch( files, sessions );
			} )
			.then( function ( result ) {
				failedFiles = result.failures;
				retry.prop( 'hidden', failedFiles.length === 0 );
				more.prop( 'hidden', false );

				if ( result.successes > 0 && failedFiles.length > 0 ) {
					setStatus(
						config.received + '\n\n' + config.uploadFailed,
						true
					);
					startStatusChecks();
				} else if ( result.successes > 0 ) {
					setStatus( config.received, false );
					startStatusChecks();
				} else if ( failedFiles.length > 0 ) {
					setStatus( config.uploadFailed, true );
				}
			} )
			.catch( function ( error ) {
				failedFiles = files.slice();
				retry.prop( 'hidden', false );
				more.prop( 'hidden', false );
				setStatus( error.message || config.uploadFailed, true );
			} );
	}

	function startStatusChecks() {
		if ( checkTimer ) {
			return;
		}
		if ( checkCount >= Number( config.maxChecks ) ) {
			setStatus( config.timedOut, false );
			return;
		}

		checkTimer = window.setTimeout(
			checkStatus,
			Number( config.checkInterval )
		);
	}

	function checkStatus() {
		checkTimer = null;
		if ( checkCount >= Number( config.maxChecks ) ) {
			setStatus( config.timedOut, false );
			return;
		}

		checkCount++;
		request( '/orders/' + orderId + '/certificate-status', {
			method: 'GET',
		} )
			.then( function ( body ) {
				if ( body.attached ) {
					window.location.reload();
					return;
				}
				if ( checkCount >= Number( config.maxChecks ) ) {
					setStatus( config.timedOut, false );
					return;
				}
				startStatusChecks();
			} )
			.catch( function ( error ) {
				if ( error.status === 401 || error.status === 403 ) {
					setStatus( config.unauthorized, true );
					return;
				}
				if ( checkCount >= Number( config.maxChecks ) ) {
					setStatus( config.timedOut, false );
					return;
				}
				startStatusChecks();
			} );
	}

	$( document ).on( 'click', '#automaticffl-upload-certificate', function () {
		orderId = Number( $( this ).data( 'order-id' ) );
		new $.WCBackboneModal.View( {
			target: 'automaticffl-certificate-upload-modal',
		} );
	} );

	$( document ).on( 'change', '#automaticffl-certificate-files', function () {
		selectedFiles = Array.prototype.slice.call( this.files || [] );
		modalElement()
			.find( '#automaticffl-start-upload' )
			.prop( 'disabled', selectedFiles.length === 0 );
		if ( selectedFiles.length > 0 ) {
			setStatus(
				countMessage( config.selected, selectedFiles.length ),
				false
			);
		}
	} );

	$( document ).on( 'click', '#automaticffl-start-upload', function () {
		if ( selectedFiles.length > 0 ) {
			beginUpload( selectedFiles );
		}
	} );

	$( document ).on( 'click', '#automaticffl-retry-upload', function () {
		if ( failedFiles.length > 0 ) {
			beginUpload( failedFiles.slice() );
		}
	} );

	$( document ).on( 'click', '#automaticffl-upload-more', function () {
		const input = modalElement().find( '#automaticffl-certificate-files' );
		input.val( '' ).trigger( 'click' );
	} );

	$( window ).on( 'beforeunload', function () {
		if ( checkTimer ) {
			window.clearTimeout( checkTimer );
			checkTimer = null;
		}
	} );
} )( jQuery, window.automaticfflOrderCertificateUpload );
