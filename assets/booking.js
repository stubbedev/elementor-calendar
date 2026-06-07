( function () {
	'use strict';

	function init( root ) {
		if ( root.dataset.tsbInit ) {
			return;
		}
		root.dataset.tsbInit = '1';

		var elDays   = root.querySelector( '.tsb-days' );
		var elSlots  = root.querySelector( '.tsb-slots' );
		var elForm   = root.querySelector( '.tsb-form' );
		var elLoad   = root.querySelector( '.tsb-loading' );
		var elResult = root.querySelector( '.tsb-result' );
		var elChosen = root.querySelector( '.tsb-chosen' );
		var elBack   = root.querySelector( '.tsb-back' );
		var elCap    = root.querySelector( '.g-recaptcha, .h-captcha' );
		var data     = { days: [] };

		function post( action, body ) {
			body.action = action;
			body.nonce  = TSB.nonce;
			var fd = new FormData();
			Object.keys( body ).forEach( function ( k ) {
				fd.append( k, body[ k ] );
			} );
			return fetch( TSB.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } );
		}

		// Resolve a captcha token (Promise), scoped to this widget instance.
		function captchaToken() {
			var cap  = TSB.captcha || {};
			var mode = cap.mode;
			try {
				if ( mode === 'recaptcha_v3' && window.grecaptcha && cap.site ) {
					return new Promise( function ( resolve ) {
						grecaptcha.ready( function () {
							grecaptcha.execute( cap.site, { action: 'book' } )
								.then( resolve )
								.catch( function () { resolve( '' ); } );
						} );
					} );
				}
				if ( mode === 'recaptcha' && window.grecaptcha ) {
					var rs = document.querySelectorAll( '.g-recaptcha' );
					var ri = Array.prototype.indexOf.call( rs, elCap );
					return Promise.resolve( grecaptcha.getResponse( ri < 0 ? 0 : ri ) );
				}
				if ( mode === 'hcaptcha' && window.hcaptcha ) {
					var hs = document.querySelectorAll( '.h-captcha' );
					var hi = Array.prototype.indexOf.call( hs, elCap );
					return Promise.resolve( hcaptcha.getResponse( hi < 0 ? 0 : hi ) );
				}
			} catch ( e ) {}
			return Promise.resolve( '' );
		}

		function captchaReset() {
			var mode = TSB.captcha && TSB.captcha.mode;
			try {
				if ( mode === 'recaptcha' && window.grecaptcha ) {
					grecaptcha.reset();
				} else if ( mode === 'hcaptcha' && window.hcaptcha ) {
					hcaptcha.reset();
				}
			} catch ( e ) {}
		}

		function loadDays() {
			elLoad.hidden = false;
			elLoad.textContent = 'Henter ledige tider…';
			post( 'tsb_slots', {} ).then( function ( res ) {
				if ( ! res.success ) {
					elLoad.textContent = 'Kunne ikke hente tider. Prøv igen.';
					return;
				}
				elLoad.hidden = true;
				data = res.data;
				renderDays();
			} ).catch( function () {
				elLoad.textContent = 'Netværksfejl.';
			} );
		}

		function renderDays() {
			elDays.innerHTML = '';
			elSlots.hidden = true;
			elForm.hidden = true;
			if ( ! data.days.length ) {
				elDays.textContent = 'Ingen ledige tider i øjeblikket.';
				elDays.hidden = false;
				return;
			}
			data.days.forEach( function ( d, i ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'tsb-day';
				b.textContent = d.label;
				b.addEventListener( 'click', function () { selectDay( i ); } );
				elDays.appendChild( b );
			} );
			elDays.hidden = false;
		}

		function selectDay( i ) {
			var d = data.days[ i ];
			elSlots.innerHTML = '';
			var head = document.createElement( 'p' );
			head.className = 'tsb-slots-head';
			head.textContent = d.label;
			elSlots.appendChild( head );
			d.slots.forEach( function ( t ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'tsb-slot';
				b.textContent = t;
				b.addEventListener( 'click', function () { selectSlot( d.date, t, d.label ); } );
				elSlots.appendChild( b );
			} );
			elSlots.hidden = false;
			elSlots.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		function selectSlot( date, time, label ) {
			elForm.date.value = date;
			elForm.time.value = time;
			elChosen.textContent = label + ' kl. ' + time;
			elForm.hidden = false;
			elSlots.hidden = true;
			elDays.hidden = true;
			elForm.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		elBack.addEventListener( 'click', function () {
			elForm.hidden = true;
			elDays.hidden = false;
		} );

		elForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = elForm.querySelector( '.tsb-submit' );
			btn.disabled = true;
			captchaToken().then( function ( token ) {
			return post( 'tsb_book', {
				date: elForm.date.value,
				time: elForm.time.value,
				name: elForm.name.value,
				email: elForm.email.value,
				phone: elForm.phone.value,
				message: elForm.message.value,
				tsb_hp: elForm.tsb_hp ? elForm.tsb_hp.value : '',
				captcha_token: token
			} ); } ).then( function ( res ) {
				btn.disabled = false;
				elResult.hidden = false;
				elResult.className = 'tsb-result ' + ( res.success ? 'ok' : 'err' );
				elResult.textContent = res.data && res.data.message ? res.data.message : ( res.success ? 'OK' : 'Fejl' );
				if ( res.success ) {
					elForm.hidden = true;
				} else {
					captchaReset();
					loadDays(); // slot may have been taken meanwhile
				}
			} ).catch( function () {
				btn.disabled = false;
				captchaReset();
				elResult.hidden = false;
				elResult.className = 'tsb-result err';
				elResult.textContent = 'Netværksfejl. Prøv igen.';
			} );
		} );

		loadDays();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.tsb' ).forEach( init );
	} );

	// Elementor editor live preview.
	if ( window.elementorFrontend ) {
		window.addEventListener( 'elementor/frontend/init', function () {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/tsb_booking.default', function ( $scope ) {
				var el = $scope[ 0 ].querySelector( '.tsb' );
				if ( el ) {
					init( el );
				}
			} );
		} );
	}
} )();
