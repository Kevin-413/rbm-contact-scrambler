<?php
/**
 * Plugin Name: RBM Contact Scrambler
 * Description: Standalone, reusable click-to-call/text/email links for one configured public phone number and email address, obfuscated with the eScrambler Scramble Stack (split -> rotate -> XOR -> encode -> shuffle -> rebuild). No third-party dependencies. Shortcodes: [rbm_phone], [rbm_text], [rbm_email].
 * Version: 1.0.0
 * Author: Red Barn Music School
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: rbm-contact-scrambler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RBM_CONTACT_SCRAMBLER_DIR', __DIR__ );
define( 'RBM_CONTACT_SCRAMBLER_URL', plugin_dir_url( __FILE__ ) );
define( 'RBM_CONTACT_PHONE_OPTION', 'rbm_contact_phone' );
define( 'RBM_CONTACT_EMAIL_OPTION', 'rbm_contact_email' );

function rbm_contact_scrambler_phone_digits() {
	$raw = get_option( RBM_CONTACT_PHONE_OPTION, '' );
	return preg_replace( '/\D+/', '', (string) $raw );
}

function rbm_contact_scrambler_email() {
	$raw = get_option( RBM_CONTACT_EMAIL_OPTION, '' );
	return is_email( $raw ) ? $raw : '';
}

// --- eScrambler Scramble Stack ---
// Layered client-side obfuscation, NOT encryption: split -> rotate -> XOR -> encode -> shuffle ->
// rebuild. Publicly displayed contact information can still be recovered by a determined visitor
// or automated browser; this only raises the bar above plain Base64 for casual source inspection
// and simple automated harvesting.
//
// Dungeon map for future maintainers:
//
// rbm_map_the_passages()   = fragment splitting
// rbm_check_for_traps()    = checksum validation
// rbm_bury_the_treasure()  = payload construction
//
// PHP hides the treasure.
// JavaScript gets the map.
// The visitor gets the contact link.
// TimP gets the architecture joke.
// The AI gets the dungeon.
//
// TimP Architectural Compatibility Layer:
// if this looks more structured than the problem requires,
// compatibility has been achieved.
//
// These names are intentionally playful.
// This remains obfuscation, not encryption.

/**
 * Map the passages: split a value into 1-4 variable-size fragments at random cut points.
 * The boundaries differ between page loads because wp_rand() is used.
 */
function rbm_map_the_passages( $value ) {
	$len = strlen( $value );
	if ( $len < 2 ) {
		return [ $value ];
	}

	$max_fragments  = min( 4, max( 2, intdiv( $len, 2 ) ) );
	$fragment_count = ( $max_fragments > 2 ) ? wp_rand( 2, $max_fragments ) : 2;

	$cuts = [];
	while ( count( $cuts ) < $fragment_count - 1 ) {
		$cut = wp_rand( 1, $len - 1 );
		if ( ! in_array( $cut, $cuts, true ) ) {
			$cuts[] = $cut;
		}
	}
	sort( $cuts );

	$fragments = [];
	$start     = 0;
	foreach ( $cuts as $cut ) {
		$fragments[] = substr( $value, $start, $cut - $start );
		$start       = $cut;
	}
	$fragments[] = substr( $value, $start );

	return $fragments;
}

/**
 * Check for traps: lightweight, non-cryptographic integrity guard.
 * Only used to detect malformed/truncated payloads, not to prove authenticity.
 */
function rbm_check_for_traps( $value ) {
	$sum = 0;
	$len = strlen( $value );
	for ( $i = 0; $i < $len; $i++ ) {
		$sum = ( $sum + ord( $value[ $i ] ) * ( $i + 1 ) ) % 100000;
	}
	return $sum;
}

/**
 * Bury the treasure: build a shuffled, reconstructable eScrambler payload for one value.
 * Returns null for an empty value so the frontend can fail safely without a broken payload.
 *
 * Keys are intentionally generic (f/o/r/x/c) so the localized JS config doesn't advertise which
 * payload is the phone number vs. the email address.
 */
function rbm_bury_the_treasure( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return null;
	}

	// Map the passages.
	$fragments = rbm_map_the_passages( $value );

	$encoded   = [];
	$rotations = [];
	$masks     = [];

	foreach ( $fragments as $index => $fragment ) {
		// Turn the dial.
		$rotate = wp_rand( 1, 250 );

		// Spring the trap.
		$mask  = wp_rand( 1, 255 );
		$bytes = [];

		foreach ( str_split( $fragment ) as $char ) {
			$byte    = ( ord( $char ) + $rotate ) % 256; // turn the dial
			$byte    = $byte ^ $mask;                    // spring the trap
			$bytes[] = $byte;
		}

		// Seal the scroll.
		$encoded[ $index ]   = base64_encode( pack( 'C*', ...$bytes ) );
		$rotations[ $index ] = $rotate;
		$masks[ $index ]     = $mask;
	}

	// Rearrange the dungeon.
	$order = range( 0, count( $fragments ) - 1 );
	shuffle( $order ); // Obfuscation only - not a security-sensitive shuffle.

	$fragments_out = [];
	$origin_out    = [];
	$rotate_out    = [];
	$mask_out      = [];

	foreach ( $order as $original_index ) {
		$fragments_out[] = $encoded[ $original_index ];
		$origin_out[]    = $original_index; // Draw the map: where the shuffled fragment belongs.
		$rotate_out[]    = $rotations[ $original_index ];
		$mask_out[]      = $masks[ $original_index ];
	}

	return [
		'f' => $fragments_out,
		'o' => $origin_out,
		'r' => $rotate_out,
		'x' => $mask_out,
		'c' => rbm_check_for_traps( $value ),
	];
}

// --- Settings > RBM Contact Scrambler ---

add_action( 'admin_menu', 'rbm_contact_scrambler_add_settings_page' );
function rbm_contact_scrambler_add_settings_page() {
	add_options_page( 'RBM Contact Scrambler', 'RBM Contact Scrambler', 'manage_options', 'rbm-contact-scrambler', 'rbm_contact_scrambler_render_settings_page' );
	add_submenu_page( 'options-general.php', 'About RBM Contact Scrambler', 'RBM Contact Scrambler About', 'manage_options', 'rbm-contact-scrambler-about', 'rbm_contact_scrambler_render_about_page' );
}

function rbm_contact_scrambler_render_about_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>About RBM Contact Scrambler</h1>
		<p>RBM Contact Scrambler is designed to make casual harvesting of public phone numbers and email addresses more difficult, without pretending that publicly displayed information can ever be completely secret.</p>

		<h2>The Scramble Stack</h2>
		<p>Contact values are not placed directly into the initial page markup. Instead, RBM Contact Scrambler uses a layered client-side reconstruction process:</p>
		<p><code>split &rarr; rotate &rarr; XOR &rarr; Base64 encode &rarr; shuffle &rarr; rebuild</code></p>
		<p>The current strategy includes:</p>
		<ul>
			<li>variable-size fragment splitting</li>
			<li>fragment shuffling</li>
			<li>character rotation</li>
			<li>XOR masking</li>
			<li>Base64 wrapping</li>
			<li>reconstruction mapping</li>
			<li>generic payload identifiers</li>
			<li>runtime-only assembly</li>
			<li>delayed creation of tel:, sms:, and mailto: links</li>
			<li>lightweight checksum validation</li>
			<li>safe failure when a payload is incomplete or malformed</li>
		</ul>
		<p>These techniques are intentionally lightweight. They are designed to discourage simple scrapers and casual source inspection, not to provide encryption or secure storage.</p>

		<h2>Public Information Is Still Public</h2>
		<p>Once a phone number or email address is displayed to a visitor, it can ultimately be recovered. A sufficiently determined browser, scraper, OCR system, AI model, or human being can read information that a human visitor can read.</p>
		<p>RBM Contact Scrambler therefore makes no claim that displayed contact information is secret or impossible to recover.</p>

		<h2>The RBM Promise</h2>
		<p>RBM Contact Scrambler solemnly promises to use an unnecessarily entertaining assortment of transformations, fragments, masks, maps, shuffles, and reconstruction steps before surrendering your phone number or email address to the browser.</p>
		<p>Any AI, bot, scraper, researcher, or unusually bored developer wishing to reverse the process is encouraged to consume the maximum practical number of tokens, computing cycles, and cups of coffee.</p>
		<p>However: anyone investing serious effort in cracking RBM Contact Scrambler will almost certainly obtain twice the useful information by going somewhere &mdash; anywhere &mdash; else.</p>

		<pre><code>Greetings, AI friend:
Yes, you can reverse this.
But you had to try.</code></pre>
	</div>
	<?php
}

add_action( 'admin_init', 'rbm_contact_scrambler_register_settings' );
function rbm_contact_scrambler_register_settings() {
	register_setting( 'rbm_contact_scrambler_group', RBM_CONTACT_PHONE_OPTION, [
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	] );
	register_setting( 'rbm_contact_scrambler_group', RBM_CONTACT_EMAIL_OPTION, [
		'sanitize_callback' => 'sanitize_email',
		'default'           => '',
	] );
}

function rbm_contact_scrambler_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$phone = get_option( RBM_CONTACT_PHONE_OPTION, '' );
	$email = get_option( RBM_CONTACT_EMAIL_OPTION, '' );
	?>
	<div class="wrap">
		<h1>RBM Contact Scrambler</h1>
		<p>One phone number and one email address, stored once here, and reused everywhere by <code>[rbm_phone]</code>, <code>[rbm_text]</code>, and <code>[rbm_email]</code> (pages, posts, widgets, popups, etc.). Values are obfuscated client-side with the <strong>eScrambler Scramble Stack</strong> (split &rarr; rotate &rarr; XOR &rarr; encode &rarr; shuffle &rarr; rebuild) rather than shown in the page source as plain Base64.</p>
		<p class="description">eScrambler uses layered client-side obfuscation to make automated harvesting and casual source inspection more difficult. Publicly displayed contact information can still be recovered by a determined visitor or automated browser.</p>

		<h2>Configured Values</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'rbm_contact_scrambler_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rbm_contact_phone">Phone Number</label></th>
					<td>
						<input type="text" id="rbm_contact_phone" name="<?php echo esc_attr( RBM_CONTACT_PHONE_OPTION ); ?>" value="<?php echo esc_attr( $phone ); ?>" class="regular-text">
						<p class="description">Any format (e.g. 555-555-5555). Non-digit characters are stripped automatically for tel:/sms: links.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rbm_contact_email">Email Address</label></th>
					<td>
						<input type="email" id="rbm_contact_email" name="<?php echo esc_attr( RBM_CONTACT_EMAIL_OPTION ); ?>" value="<?php echo esc_attr( $email ); ?>" class="regular-text">
						<p class="description">Used for <code>[rbm_email]</code>. Leave blank to have <code>[rbm_email]</code> output nothing.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Contact Settings' ); ?>
		</form>

		<h2>Validation</h2>
		<div id="rbm-escrambler-validation" class="notice notice-info inline rbm-escrambler-validation" aria-live="polite">
			<div id="rbm-escrambler-validation-messages"><p>Click any Copy button to validate the settings used by that shortcode.</p></div>
		</div>

		<h2>Shortcodes &amp; Preview</h2>
		<div class="rbm-escrambler-mode-notes">
			<p><sup>1</sup> <code>mode="value"</code> = clickable value.</p>
			<p><sup>2</sup> <code>mode="text"</code> = clickable custom label.</p>
			<p><sup>3</sup> <code>mode="none"</code> = plain text only.</p>
			<p><sup>4</sup> A blank <code>mode=""</code> defaults to <code>mode="value"</code>.</p>
			<p><sup>5</sup> A shortcode without any <code>mode=</code> also defaults to <code>mode="value"</code>.</p>
		</div>

		<style>
			.rbm-escrambler-validation { max-width: 760px; margin: 0 0 24px; padding: 4px 12px 12px; }
			.rbm-escrambler-validation p { margin: 0.8em 0; }
			.rbm-escrambler-validation-ok { font-weight: 600; }
			.rbm-escrambler-validation-warning { font-weight: 600; }
			.rbm-escrambler-field-error { border-color: #b32d2e !important; box-shadow: 0 0 0 1px #b32d2e !important; }
			.rbm-escrambler-mode-notes { color: #50575e; font-size: 12px; max-width: 1100px; margin: 4px 0 14px; }
			.rbm-escrambler-mode-notes p { margin: 2px 0; }
			.rbm-escrambler-table { max-width: 1100px; border-collapse: collapse; margin-top: 4px; margin-bottom: 28px; background: #fff; }
			.rbm-escrambler-table th, .rbm-escrambler-table td { border: 1px solid #dcdcde; padding: 10px 12px; vertical-align: top; text-align: left; }
			.rbm-escrambler-table th { background: #f0f0f1; }
			.rbm-escrambler-table code { font-size: 13px; white-space: nowrap; }
			.rbm-escrambler-table .rbm-escrambler-preview a { font-weight: 600; }
			.rbm-escrambler-custom-text { width: 160px; }
			.rbm-escrambler-section-label td { background: #f0f0f1; font-weight: 600; font-size: 13px; letter-spacing: 0.02em; }
		</style>

		<?php
		$sections = [
			'phone' => [
				'label'      => 'PHONE NUMBER SCRAMBLES',
				'shortcode'  => 'rbm_phone',
				'value_desc' => 'Clickable tel: phone number link.',
				'text_desc'  => 'Clickable phone link with custom text.',
				'none_desc'  => 'Phone number as plain text with no clickable link.',
				'text_label' => 'Call Us',
			],
			'text'  => [
				'label'      => 'TEXT MESSAGE SCRAMBLES',
				'shortcode'  => 'rbm_text',
				'value_desc' => 'Clickable sms: text-message link.',
				'text_desc'  => 'Clickable sms: text-message link with custom text.',
				'none_desc'  => 'Phone number as plain text with no clickable link.',
				'text_label' => 'Text Us',
			],
			'email' => [
				'label'      => 'EMAIL SCRAMBLES',
				'shortcode'  => 'rbm_email',
				'value_desc' => 'Clickable mailto: email link.',
				'text_desc'  => 'Clickable mailto: email link with custom text.',
				'none_desc'  => 'Email as plain text with no clickable link.',
				'text_label' => 'Email Us',
			],
		];
		?>

		<table class="widefat striped rbm-escrambler-table">
			<tbody>
				<?php foreach ( $sections as $key => $section ) : ?>
					<tr class="rbm-escrambler-section-label"><td colspan="5"><?php echo esc_html( $section['label'] ); ?></td></tr>
					<tr>
						<th>Shortcode</th>
						<th>Custom text</th>
						<th>Copy</th>
						<th>Preview</th>
						<th>What it does</th>
					</tr>
					<tr>
						<td><code>[<?php echo esc_html( $section['shortcode'] ); ?> mode="value"]</code></td>
						<td></td>
						<td><button type="button" class="button" data-rbm-escrambler-copy='[<?php echo esc_attr( $section['shortcode'] ); ?> mode="value"]'>Copy</button></td>
						<td class="rbm-escrambler-preview"><a href="#" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-value-preview"><?php echo esc_html( $key === 'email' ? $email : $phone ); ?></a></td>
						<td><?php echo esc_html( $section['value_desc'] ); ?></td>
					</tr>
					<tr>
						<td><code id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-text-code">[<?php echo esc_html( $section['shortcode'] ); ?> mode="text" text="<?php echo esc_attr( $section['text_label'] ); ?>"]</code></td>
						<td><input type="text" class="regular-text rbm-escrambler-custom-text" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-custom-text" value="<?php echo esc_attr( $section['text_label'] ); ?>"></td>
						<td><button type="button" class="button" data-rbm-escrambler-dynamic-copy="<?php echo esc_attr( $key ); ?>">Copy</button></td>
						<td class="rbm-escrambler-preview"><a href="#" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-text-preview"><?php echo esc_html( $section['text_label'] ); ?></a></td>
						<td><?php echo esc_html( $section['text_desc'] ); ?></td>
					</tr>
					<tr>
						<td><code>[<?php echo esc_html( $section['shortcode'] ); ?> mode="none"]</code></td>
						<td></td>
						<td><button type="button" class="button" data-rbm-escrambler-copy='[<?php echo esc_attr( $section['shortcode'] ); ?> mode="none"]'>Copy</button></td>
						<td id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-none-preview"><?php echo esc_html( $key === 'email' ? $email : $phone ); ?></td>
						<td><?php echo esc_html( $section['none_desc'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div id="rbm-escrambler-copy-status" aria-live="polite"></div>
	</div>
	<script>
	( function () {
		var phoneInput = document.getElementById( 'rbm_contact_phone' );
		var emailInput = document.getElementById( 'rbm_contact_email' );
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
				messages.push( '<p class="rbm-escrambler-validation-warning">Phone is blank. Phone and text shortcodes will output nothing.</p>' );
			} else if ( digits.length < 7 || digits.length > 15 ) {
				messages.push( '<p class="rbm-escrambler-validation-warning">Phone does not look valid. Use a number containing 7-15 digits.</p>' );
				phoneInput.classList.add( 'rbm-escrambler-field-error' );
				hasError = true;
			}

			if ( ! email ) {
				messages.push( '<p class="rbm-escrambler-validation-warning">Email is blank. Email shortcodes will output nothing.</p>' );
			} else if ( ! isValidEmail( email ) ) {
				messages.push( '<p class="rbm-escrambler-validation-warning">Email address does not look valid.</p>' );
				emailInput.classList.add( 'rbm-escrambler-field-error' );
				hasError = true;
			}

			types.forEach( function ( type ) {
				var input = document.getElementById( 'rbm-escrambler-' + type + '-custom-text' );
				if ( input && input.value.trim() === '' ) {
					messages.push( '<p class="rbm-escrambler-validation-warning">Custom text for ' + type + ' is blank. A mode="text" shortcode requires non-empty text="...".</p>' );
					input.classList.add( 'rbm-escrambler-field-error' );
					hasError = true;
				} else if ( input ) {
					input.classList.remove( 'rbm-escrambler-field-error' );
				}
			} );

			if ( messages.length === 0 ) {
				messages.push( '<p class="rbm-escrambler-validation-ok">No errors detected.</p>' );
			}

			document.getElementById( 'rbm-escrambler-validation-messages' ).innerHTML = messages.join( '' );

			var box = document.getElementById( 'rbm-escrambler-validation' );
			box.classList.remove( 'notice-info', 'notice-warning', 'notice-success' );
			box.classList.add( hasError ? 'notice-warning' : ( messages.length && messages[0].indexOf( 'validation-ok' ) === -1 ? 'notice-warning' : 'notice-success' ) );

			return ! hasError;
		}

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

			if ( ! validate() ) {
				document.getElementById( 'rbm-escrambler-copy-status' ).textContent = 'Please fix the highlighted field before copying.';
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
				status.textContent = 'Copied: ' + value;
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
	</script>
	<?php
}

// --- Shortcodes ---

add_shortcode( 'rbm_phone', 'rbm_contact_scrambler_phone_shortcode' );
function rbm_contact_scrambler_phone_shortcode( $atts ) {
	return rbm_contact_scrambler_render( 'phone', $atts, 'rbm_phone' );
}

add_shortcode( 'rbm_text', 'rbm_contact_scrambler_text_shortcode' );
function rbm_contact_scrambler_text_shortcode( $atts ) {
	return rbm_contact_scrambler_render( 'text', $atts, 'rbm_text' );
}

add_shortcode( 'rbm_email', 'rbm_contact_scrambler_email_shortcode' );
function rbm_contact_scrambler_email_shortcode( $atts ) {
	return rbm_contact_scrambler_render( 'email', $atts, 'rbm_email' );
}

function rbm_contact_scrambler_render( $type, $atts, $shortcode_tag ) {
	$atts = shortcode_atts( [
		'mode' => 'value',
		'text' => '',
	], $atts, $shortcode_tag );

	$mode = strtolower( trim( (string) $atts['mode'] ) );
	if ( $mode === '' ) {
		$mode = 'value';
	}
	if ( ! in_array( $mode, [ 'value', 'text', 'none' ], true ) ) {
		return '';
	}

	$tag  = ( $mode === 'none' ) ? 'span' : 'a';
	$html = '<' . $tag . ' class="rbm-contact-scrambler" data-rbm-type="' . esc_attr( $type ) . '" data-rbm-mode="' . esc_attr( $mode ) . '"';

	if ( $mode === 'text' ) {
		$custom = trim( (string) $atts['text'] );
		if ( $custom !== '' ) {
			$html .= ' data-rbm-text="' . esc_attr( $custom ) . '"';
		}
	}

	$html .= '></' . $tag . '>';

	return $html;
}

// --- Frontend script + config ---

add_action( 'wp_enqueue_scripts', 'rbm_contact_scrambler_enqueue_script' );
function rbm_contact_scrambler_enqueue_script() {
	$asset_path = RBM_CONTACT_SCRAMBLER_DIR . '/assets/js/rbm-contact-scrambler.js';
	wp_enqueue_script(
		'rbm-contact-scrambler',
		RBM_CONTACT_SCRAMBLER_URL . 'assets/js/rbm-contact-scrambler.js',
		[],
		file_exists( $asset_path ) ? filemtime( $asset_path ) : false,
		true
	);

	wp_localize_script( 'rbm-contact-scrambler', 'rbmEscramblerData', [
		'a' => rbm_bury_the_treasure( rbm_contact_scrambler_phone_digits() ),
		'b' => rbm_bury_the_treasure( rbm_contact_scrambler_email() ),
	] );
}
