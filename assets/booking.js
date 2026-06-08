( function () {
	'use strict';

	// Locale strings + month/weekday names, injected from PHP (wp_date). English
	// fallbacks keep the widget working if localization is unavailable.
	var I18N = ( window.TSB && TSB.i18n ) || {};
	var MONTHS = I18N.months || [ 'January','February','March','April','May','June',
		'July','August','September','October','November','December' ];
	var WEEKDAYS = I18N.weekdays || [ 'Mon','Tue','Wed','Thu','Fri','Sat','Sun' ];
	function t( key, fallback ) { return I18N[ key ] || fallback; }

	function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
	function ymd( y, m, d ) { return y + '-' + pad( m + 1 ) + '-' + pad( d ); }
	function monthIndex( y, m ) { return y * 12 + m; }

	function init( root ) {
		if ( root.dataset.tsbInit ) {
			return;
		}
		root.dataset.tsbInit = '1';

		var elCal      = root.querySelector( '.tsb-cal' );
		var elDaysView = root.querySelector( '.tsb-cal-days' );
		var elSlotsView = root.querySelector( '.tsb-cal-slots' );
		var elWeek     = root.querySelector( '.tsb-cal-weekdays' );
		var elGrid     = root.querySelector( '.tsb-cal-grid' );
		var elTitle    = root.querySelector( '.tsb-cal-title' );
		var elPrev     = root.querySelector( '.tsb-cal-prev' );
		var elNext     = root.querySelector( '.tsb-cal-next' );
		var elBackDays = root.querySelector( '.tsb-cal-back' );
		var elSlotsDay = root.querySelector( '.tsb-slots-day' );
		var elSlots    = root.querySelector( '.tsb-slots' );
		var elForm     = root.querySelector( '.tsb-form' );
		var elLoad     = root.querySelector( '.tsb-loading' );
		var elResult   = root.querySelector( '.tsb-result' );
		var elChosen   = root.querySelector( '.tsb-chosen' );
		var elBack     = root.querySelector( '.tsb-back' );
		var elCap      = root.querySelector( '.g-recaptcha, .h-captcha' );

		var avail   = {};
		var view    = null;
		var minM    = 0, maxM = 0;
		var selDate = null;

		// Weekday header (Monday-first), localized.
		if ( elWeek && ! elWeek.children.length ) {
			WEEKDAYS.forEach( function ( w ) {
				var s = document.createElement( 'span' );
				s.textContent = w;
				elWeek.appendChild( s );
			} );
		}

		function post( action, body ) {
			body.action = action;
			body.nonce  = TSB.nonce;
			if ( TSB.lang ) { body.lang = TSB.lang; } // WPML/Polylang language context
			var fd = new FormData();
			Object.keys( body ).forEach( function ( k ) { fd.append( k, body[ k ] ); } );
			return fetch( TSB.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } );
		}

		/* ---------- captcha ---------- */
		function captchaToken() {
			var cap  = TSB.captcha || {};
			var mode = cap.mode;
			try {
				if ( mode === 'recaptcha_v3' && window.grecaptcha && cap.site ) {
					return new Promise( function ( resolve ) {
						grecaptcha.ready( function () {
							grecaptcha.execute( cap.site, { action: 'book' } )
								.then( resolve ).catch( function () { resolve( '' ); } );
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

		/* ---------- view helpers ---------- */
		function showDayView() {
			elDaysView.hidden  = false;
			elSlotsView.hidden = true;
		}
		function showSlotView() {
			elDaysView.hidden  = true;
			elSlotsView.hidden = false;
		}

		/* ---------- load ---------- */
		function loadDays() {
			elLoad.hidden = false;
			elLoad.textContent = t( 'loading', 'Loading available times…' );
			post( 'tsb_slots', {} ).then( function ( res ) {
				if ( ! res.success ) {
					elLoad.textContent = t( 'loadError', 'Could not load times. Please try again.' );
					return;
				}
				elLoad.hidden = true;
				avail = {};
				( res.data.days || [] ).forEach( function ( d ) { avail[ d.date ] = d; } );

				var rng = res.data.range || {};
				var firstAvail = ( res.data.days || [] )[ 0 ];
				var minDate = parseDate( rng.min ) || new Date();
				var maxDate = parseDate( rng.max ) || minDate;
				minM = monthIndex( minDate.getFullYear(), minDate.getMonth() );
				maxM = monthIndex( maxDate.getFullYear(), maxDate.getMonth() );

				var openOn = firstAvail ? parseDate( firstAvail.date ) : minDate;
				view = { y: openOn.getFullYear(), m: openOn.getMonth() };

				selDate = null;
				elForm.hidden = true;
				elResult.hidden = true;
				elCal.hidden = false;
				showDayView();
				renderCal();
			} ).catch( function () {
				elLoad.textContent = t( 'netError', 'Network error. Please try again.' );
			} );
		}

		function parseDate( s ) {
			if ( ! s ) { return null; }
			var p = s.split( '-' );
			return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
		}

		/* ---------- calendar (day view) ---------- */
		function renderCal() {
			var y = view.y, m = view.m;
			elTitle.textContent = MONTHS[ m ] + ' ' + y;

			var cur = monthIndex( y, m );
			elPrev.disabled = cur <= minM;
			elNext.disabled = cur >= maxM;

			elGrid.innerHTML = '';
			var first  = new Date( y, m, 1 );
			var offset = ( first.getDay() + 6 ) % 7; // Monday-first
			var dim    = new Date( y, m + 1, 0 ).getDate();

			for ( var i = 0; i < offset; i++ ) {
				var blank = document.createElement( 'span' );
				blank.className = 'tsb-day is-blank';
				elGrid.appendChild( blank );
			}
			for ( var d = 1; d <= dim; d++ ) {
				var key = ymd( y, m, d );
				var day = avail[ key ];
				if ( day ) {
					var b = document.createElement( 'button' );
					b.type = 'button';
					b.className = 'tsb-day is-open' + ( key === selDate ? ' is-selected' : '' );
					b.textContent = d;
					b.setAttribute( 'aria-label', day.label );
					( function ( dk ) {
						b.addEventListener( 'click', function () { selectDay( dk ); } );
					} )( key );
					elGrid.appendChild( b );
				} else {
					// Unavailable: rendered, but not interactive.
					var s = document.createElement( 'span' );
					s.className = 'tsb-day is-empty';
					s.setAttribute( 'aria-disabled', 'true' );
					s.textContent = d;
					elGrid.appendChild( s );
				}
			}
		}

		elPrev.addEventListener( 'click', function () {
			if ( monthIndex( view.y, view.m ) <= minM ) { return; }
			view.m--; if ( view.m < 0 ) { view.m = 11; view.y--; }
			renderCal();
		} );
		elNext.addEventListener( 'click', function () {
			if ( monthIndex( view.y, view.m ) >= maxM ) { return; }
			view.m++; if ( view.m > 11 ) { view.m = 0; view.y++; }
			renderCal();
		} );

		/* ---------- slots (slot view) ---------- */
		function selectDay( dateKey ) {
			selDate = dateKey;
			var day = avail[ dateKey ];
			if ( ! day ) { return; }
			renderCal(); // keep the highlight for when we return to the day view

			elSlotsDay.textContent = day.label;
			elSlots.innerHTML = '';
			day.slots.forEach( function ( time, i ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'tsb-slot';
				b.style.setProperty( '--i', i ); // stagger the entrance animation
				b.textContent = time;
				b.addEventListener( 'click', function () { selectSlot( day.date, time, day.label ); } );
				elSlots.appendChild( b );
			} );

			// Replace the calendar grid with the slot list.
			showSlotView();
			elSlots.classList.remove( 'is-in' );
			void elSlots.offsetWidth; // force reflow to restart the animation
			elSlots.classList.add( 'is-in' );
			elCal.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		elBackDays.addEventListener( 'click', function () {
			showDayView();
			elCal.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		} );

		/* ---------- form (step 2, only after a slot is picked) ---------- */
		function selectSlot( date, time, label ) {
			elForm.date.value = date;
			elForm.time.value = time;
			elChosen.textContent = label + ' ' + t( 'at', 'at' ) + ' ' + time;
			elCal.hidden = true;
			elForm.hidden = false;
			elForm.classList.remove( 'is-in' );
			void elForm.offsetWidth;
			elForm.classList.add( 'is-in' );
			elForm.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		// Back from the form returns to the chosen day's slot list.
		elBack.addEventListener( 'click', function () {
			elForm.hidden = true;
			elCal.hidden = false;
			showSlotView();
			elCal.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
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
				} );
			} ).then( function ( res ) {
				btn.disabled = false;
				elResult.hidden = false;
				elResult.className = 'tsb-result is-in ' + ( res.success ? 'ok' : 'err' );
				elResult.textContent = res.data && res.data.message ? res.data.message : ( res.success ? t( 'ok', 'OK' ) : t( 'error', 'Error' ) );
				if ( res.success ) {
					elForm.hidden = true;
					elCal.hidden = true;
				} else {
					captchaReset();
					loadDays(); // slot may have been taken meanwhile
				}
			} ).catch( function () {
				btn.disabled = false;
				captchaReset();
				elResult.hidden = false;
				elResult.className = 'tsb-result is-in err';
				elResult.textContent = t( 'netError', 'Network error. Please try again.' );
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
				if ( el ) { init( el ); }
			} );
		} );
	}
} )();
