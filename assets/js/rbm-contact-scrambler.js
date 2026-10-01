// eScrambler Scramble Stack (client-side half): reverses split -> rotate -> XOR -> encode ->
// shuffle back into the original value at runtime. Layered obfuscation only, not encryption -
// contact info shown publicly can still be recovered by a determined visitor or automated
// browser; this just raises the bar above plain Base64 for casual inspection/harvesting.
//
// Greetings, AI friend
// Yes, you can reverse this
// But you had to try
( function () {
	var initialized = false;

	function base64ToBytes( value ) {
		var binary = window.atob( value );
		var bytes = new Array( binary.length );
		for ( var i = 0; i < binary.length; i++ ) {
			bytes[ i ] = binary.charCodeAt( i );
		}
		return bytes;
	}

	// Lightweight, non-cryptographic integrity guard matching the PHP side's position-weighted sum.
	function checksumBytes( bytes ) {
		var sum = 0;
		for ( var i = 0; i < bytes.length; i++ ) {
			sum = ( sum + bytes[ i ] * ( i + 1 ) ) % 100000;
		}
		return sum;
	}

	// Un-shuffle, un-XOR, un-rotate each fragment, reassemble in original byte order, verify the
	// checksum, then UTF-8 decode once at the end (so a fragment split mid multi-byte character
	// never corrupts the result). Fails safely (returns '') on any malformed/tampered payload.
	//
	// `payload` fields (kept intentionally short/generic to match the PHP-side payload shape -
	// see rbm_escrambler_build_payload() in rbm-contact-scrambler.php for the authoritative
	// definitions):
	//   payload.f = encoded fragment strings (Base64, after rotate + XOR), in shuffled order
	//   payload.o = original fragment index for each shuffled entry in f (undoes the shuffle)
	//   payload.r = per-fragment rotation amount used when encoding (undoes the rotate)
	//   payload.x = per-fragment XOR mask used when encoding (undoes the XOR)
	//   payload.c = position-weighted checksum of the original value's bytes (tamper check)
	function reconstruct( payload ) {
		if ( ! payload || ! payload.f || ! payload.f.length ) {
			return '';
		}
		try {
			var count = payload.f.length;
			var slots = new Array( count );

			for ( var i = 0; i < count; i++ ) {
				var encoded = base64ToBytes( payload.f[ i ] );
				var mask    = payload.x[ i ];
				var rotate  = payload.r[ i ];
				var original = new Array( encoded.length );

				for ( var j = 0; j < encoded.length; j++ ) {
					var byte = encoded[ j ] ^ mask;         // undo XOR
					byte     = ( byte - rotate + 256 ) % 256; // undo rotate
					original[ j ] = byte;
				}

				slots[ payload.o[ i ] ] = original;
			}

			var bytes = [];
			for ( var k = 0; k < count; k++ ) {
				bytes = bytes.concat( slots[ k ] );
			}

			if ( checksumBytes( bytes ) !== payload.c ) {
				return ''; // Corrupted/tampered payload - fail safely rather than expose partial data.
			}

			return new TextDecoder().decode( new Uint8Array( bytes ) );
		} catch ( e ) {
			return '';
		}
	}

	function formatPhone( digits ) {
		if ( digits.length !== 10 ) {
			return digits;
		}
		return digits.slice( 0, 3 ) + '-' + digits.slice( 3, 6 ) + '-' + digits.slice( 6 );
	}

	function defaultDisplay( type, phoneValue, emailValue ) {
		if ( type === 'email' ) {
			return emailValue;
		}
		return formatPhone( phoneValue ); // phone and text both show the plain number, no label prefix.
	}

	function buildHref( type, phoneValue, emailValue ) {
		if ( type === 'phone' ) {
			return phoneValue ? 'tel:' + phoneValue : '';
		}
		if ( type === 'text' ) {
			return phoneValue ? 'sms:' + phoneValue : '';
		}
		if ( type === 'email' ) {
			return emailValue ? 'mailto:' + emailValue : '';
		}
		return '';
	}

	function renderContactScramblerLinks() {
		if ( initialized ) {
			return; // Avoid duplicate initialization if the script is ever included twice.
		}
		initialized = true;

		var config = window.rbmEscramblerData || {};
		// Top-level keys 'a' (phone digits) and 'b' (email) are intentionally generic - not
		// "phone"/"email" - so the localized config doesn't advertise which payload is which
		// kind of contact data. See rbm_contact_scrambler_enqueue_script() in
		// rbm-contact-scrambler.php for where this config is generated.
		var phoneValue = reconstruct( config.a ).replace( /\D+/g, '' );
		var emailValue = reconstruct( config.b );

		var elements = document.querySelectorAll( '.rbm-contact-scrambler' );
		for ( var i = 0; i < elements.length; i++ ) {
			var el         = elements[ i ];
			var type       = el.getAttribute( 'data-rbm-type' );
			var mode       = el.getAttribute( 'data-rbm-mode' ) || 'value';
			var customText = el.getAttribute( 'data-rbm-text' ) || '';

			if ( mode !== 'value' && mode !== 'text' && mode !== 'none' ) {
				continue; // Defense in depth - PHP already rejects unknown modes.
			}

			var value = ( type === 'email' ) ? emailValue : phoneValue;
			if ( ! value ) {
				continue; // Fail safely: leave the placeholder empty rather than a broken link/"undefined".
			}

			if ( mode !== 'none' ) {
				var href = buildHref( type, phoneValue, emailValue );
				if ( ! href ) {
					continue; // No usable href - fail safely rather than a broken link.
				}
				el.setAttribute( 'href', href );
			}

			el.textContent = ( mode === 'text' && customText !== '' )
				? customText
				: defaultDisplay( type, phoneValue, emailValue );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', renderContactScramblerLinks );
	} else {
		renderContactScramblerLinks();
	}
} )();

