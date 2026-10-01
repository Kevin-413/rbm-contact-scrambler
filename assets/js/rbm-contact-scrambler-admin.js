/* Settings > RBM Contact Scrambler admin page behavior: live preview, validation, and
 * copy-to-clipboard for the shortcode table. Translatable strings are supplied via
 * wp_localize_script() as window.rbmEscramblerAdminData.strings (see
 * rbm_contact_scrambler_enqueue_admin_assets() in rbm-contact-scrambler.php). */
( function () {
	var phoneInput = document.getElementById( 'rbm_contact_phone' );
	var emailInput = document.getElementById( 'rbm_contact_email' );
	if ( ! phoneInput || ! emailInput ) {
		return; // Not on the settings page.
	}

	var strings = ( window.rbmEscramblerAdminData && window.rbmEscramblerAdminData.strings ) || {};
	var types = [ 'phone', 'text', 'email' ];

	function digitsOnly( value ) {
		return String( value ).replace( /\D+/g, '' );
	}

	function isValidEmail( value ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value );
	}

	// blur = validate the field the user just left; never validates on every keystroke.
	function validate() {
		var phone = phoneInput.value.trim();
		var email = emailInput.value.trim();
		var digits = digitsOnly( phone );
		var messages = [];
		var hasError = false;

		phoneInput.classList.remove( 'rbm-escrambler-field-error' );
		emailInput.classList.remove( 'rbm-escrambler-field-error' );

		if ( ! phone ) {
			messages.push( '<p class="rbm-escrambler-validation-warning">' + strings.phoneBlank + '</p>' );
		} else if ( digits.length < 7 || digits.length > 15 ) {
			messages.push( '<p class="rbm-escrambler-validation-warning">' + strings.phoneInvalid + '</p>' );
			phoneInput.classList.add( 'rbm-escrambler-field-error' );
			hasError = true;
		}

		if ( ! email ) {
			messages.push( '<p class="rbm-escrambler-validation-warning">' + strings.emailBlank + '</p>' );
		} else if ( ! isValidEmail( email ) ) {
			messages.push( '<p class="rbm-escrambler-validation-warning">' + strings.emailInvalid + '</p>' );
			emailInput.classList.add( 'rbm-escrambler-field-error' );
			hasError = true;
		}

		types.forEach( function ( type ) {
			var input = document.getElementById( 'rbm-escrambler-' + type + '-custom-text' );
			if ( input && input.value.trim() === '' ) {
				var message = ( strings.customTextBlank || '' ).replace( '%s', type );
				messages.push( '<p class="rbm-escrambler-validation-warning">' + message + '</p>' );
				input.classList.add( 'rbm-escrambler-field-error' );
				hasError = true;
			} else if ( input ) {
				input.classList.remove( 'rbm-escrambler-field-error' );
			}
		} );

		if ( messages.length === 0 ) {
			messages.push( '<p class="rbm-escrambler-validation-ok">' + strings.noErrors + '</p>' );
		}

		document.getElementById( 'rbm-escrambler-validation-messages' ).innerHTML = messages.join( '' );

		// Cosmetic only: match native WP notice colors to the same messages/rules above.
		var box = document.getElementById( 'rbm-escrambler-validation' );
		box.classList.remove( 'notice-info', 'notice-warning', 'notice-success' );
		box.classList.add( hasError ? 'notice-warning' : ( messages.length && messages[0].indexOf( 'validation-ok' ) === -1 ? 'notice-warning' : 'notice-success' ) );

		return ! hasError;
	}

	// input = preview only, no validation/error styling while the user is typing.
	function updatePreview() {
		var phone = phoneInput.value || '';
		var email = emailInput.value || '';
		var digits = digitsOnly( phone );

		[ 'phone', 'text' ].forEach( function ( type ) {
			var valuePreview = document.getElementById( 'rbm-escrambler-' + type + '-value-preview' );
			var nonePreview  = document.getElementById( 'rbm-escrambler-' + type + '-none-preview' );
			if ( valuePreview ) {
				valuePreview.textContent = phone;
				valuePreview.href = digits ? ( type === 'phone' ? 'tel:' : 'sms:' ) + digits : '#';
			}
			if ( nonePreview ) {
				nonePreview.textContent = phone;
			}
			var textPreview = document.getElementById( 'rbm-escrambler-' + type + '-text-preview' );
			if ( textPreview ) {
				textPreview.href = digits ? ( type === 'phone' ? 'tel:' : 'sms:' ) + digits : '#';
			}
		} );

		var emailValuePreview = document.getElementById( 'rbm-escrambler-email-value-preview' );
		var emailNonePreview  = document.getElementById( 'rbm-escrambler-email-none-preview' );
		var emailTextPreview  = document.getElementById( 'rbm-escrambler-email-text-preview' );
		if ( emailValuePreview ) {
			emailValuePreview.textContent = email;
			emailValuePreview.href = email ? 'mailto:' + email : '#';
		}
		if ( emailNonePreview ) {
			emailNonePreview.textContent = email;
		}
		if ( emailTextPreview ) {
			emailTextPreview.href = email ? 'mailto:' + email : '#';
		}
	}

	function updateCustomText( type ) {
		var input = document.getElementById( 'rbm-escrambler-' + type + '-custom-text' );
		var code = document.getElementById( 'rbm-escrambler-' + type + '-text-code' );
		var preview = document.getElementById( 'rbm-escrambler-' + type + '-text-preview' );
		var value = input.value || '';
		var shortcode = type === 'phone' ? 'rbm_phone' : ( type === 'text' ? 'rbm_text' : 'rbm_email' );
		code.textContent = '[' + shortcode + ' mode="text" text="' + value.replace( /"/g, '&quot;' ) + '"]';
		preview.textContent = value;
	}

	types.forEach( function ( type ) {
		var input = document.getElementById( 'rbm-escrambler-' + type + '-custom-text' );
		if ( input ) {
			input.addEventListener( 'input', function () {
				updateCustomText( type );
			} );
			input.addEventListener( 'blur', validate );
		}
	} );

	phoneInput.addEventListener( 'input', updatePreview );
	emailInput.addEventListener( 'input', updatePreview );
	phoneInput.addEventListener( 'blur', validate );
	emailInput.addEventListener( 'blur', validate );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		if ( ! button ) {
			return;
		}

		var isCopyButton = button.hasAttribute( 'data-rbm-escrambler-copy' ) || button.hasAttribute( 'data-rbm-escrambler-dynamic-copy' );
		if ( ! isCopyButton ) {
			return;
		}

		// Copy always validates first and always includes an explicit mode="..." in the
		// generated shortcode; it never copies a shortcode with a missing/blank mode.
		if ( ! validate() ) {
			document.getElementById( 'rbm-escrambler-copy-status' ).textContent = strings.fixFieldFirst;
			return;
		}

		var value = button.getAttribute( 'data-rbm-escrambler-copy' );
		var dynamicType = button.getAttribute( 'data-rbm-escrambler-dynamic-copy' );
		if ( dynamicType ) {
			value = document.getElementById( 'rbm-escrambler-' + dynamicType + '-text-code' ).textContent;
		}

		if ( ! value ) {
			return;
		}

		var status = document.getElementById( 'rbm-escrambler-copy-status' );

		function done() {
			status.textContent = ( strings.copied || '' ).replace( '%s', value );
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( value ).then( done );
		} else {
			var temp = document.createElement( 'textarea' );
			temp.value = value;
			document.body.appendChild( temp );
			temp.select();
			document.execCommand( 'copy' );
			document.body.removeChild( temp );
			done();
		}
	} );

	updatePreview();
} )();
