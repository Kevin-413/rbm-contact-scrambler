<?php
/**
 * Plugin Name: RBM Contact Scrambler
 * Description: Reusable phone, text, and email shortcodes with lightweight client-side obfuscation to discourage simple automated harvesting.
 * Version: 2.0.0
 * Author: Red Barn Music School
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rbm-contact-scrambler
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RBM_CONTACT_SCRAMBLER_DIR', __DIR__ );
define( 'RBM_CONTACT_SCRAMBLER_URL', plugin_dir_url( __FILE__ ) );
define( 'RBM_CONTACT_PHONE_OPTION', 'rbm_contact_phone' );
define( 'RBM_CONTACT_EMAIL_OPTION', 'rbm_contact_email' );

add_action( 'plugins_loaded', 'rbm_contact_scrambler_load_textdomain' );
function rbm_contact_scrambler_load_textdomain() {
	load_plugin_textdomain( 'rbm-contact-scrambler', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

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

/**
 * Split a value into 1-4 variable-size fragments at random cut points. Deterministic per call
 * (no external state), but the boundaries differ between page loads because wp_rand() is used.
 */
function rbm_escrambler_split_fragments( $value ) {
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
 * Lightweight, non-cryptographic integrity guard: a position-weighted sum of byte values.
 * Only used to detect malformed/truncated payloads, not to prove authenticity.
 */
function rbm_escrambler_checksum( $value ) {
	$sum = 0;
	$len = strlen( $value );
	for ( $i = 0; $i < $len; $i++ ) {
		$sum = ( $sum + ord( $value[ $i ] ) * ( $i + 1 ) ) % 100000;
	}
	return $sum;
}

/**
 * Build a shuffled, reconstructable eScrambler payload for one value (phone digits or email).
 * Returns null for an empty value so the frontend can fail safely without a broken payload.
 *
 * The returned array keys are intentionally short/generic by design (not "fragments",
 * "phone", etc.): this is part of the plugin's obfuscation strategy so the localized JS
 * config doesn't spell out which payload holds which kind of contact data. Despite the
 * terse keys, each one has a single, fixed meaning documented here for maintainers/reviewers:
 *   f = encoded fragment strings (Base64, after rotate + XOR), in shuffled order
 *   o = original fragment index for each shuffled entry in f (undoes the shuffle)
 *   r = per-fragment rotation amount used when encoding (undoes the rotate)
 *   x = per-fragment XOR mask used when encoding (undoes the XOR)
 *   c = position-weighted checksum of the original value's bytes (tamper/corruption check)
 * See assets/js/rbm-contact-scrambler.js for the matching client-side reconstruction.
 */
function rbm_escrambler_build_payload( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return null;
	}

	$fragments = rbm_escrambler_split_fragments( $value );

	$encoded   = [];
	$rotations = [];
	$masks     = [];

	foreach ( $fragments as $index => $fragment ) {
		$rotate = wp_rand( 1, 250 );
		$mask   = wp_rand( 1, 255 );
		$bytes  = [];

		foreach ( str_split( $fragment ) as $char ) {
			$byte    = ( ord( $char ) + $rotate ) % 256; // rotate
			$byte    = $byte ^ $mask;                    // XOR mask
			$bytes[] = $byte;
		}

		$encoded[ $index ]   = base64_encode( pack( 'C*', ...$bytes ) );
		$rotations[ $index ] = $rotate;
		$masks[ $index ]     = $mask;
	}

	$order = range( 0, count( $fragments ) - 1 );
	shuffle( $order ); // Obfuscation only - not a security-sensitive shuffle.

	$fragments_out = [];
	$origin_out    = [];
	$rotate_out    = [];
	$mask_out      = [];

	foreach ( $order as $original_index ) {
		$fragments_out[] = $encoded[ $original_index ];
		$origin_out[]    = $original_index; // Tells JS where this shuffled fragment belongs.
		$rotate_out[]    = $rotations[ $original_index ];
		$mask_out[]      = $masks[ $original_index ];
	}

	return [
		'f' => $fragments_out,
		'o' => $origin_out,
		'r' => $rotate_out,
		'x' => $mask_out,
		'c' => rbm_escrambler_checksum( $value ),
	];
}

// --- Settings > RBM Contact Scrambler ---

add_action( 'admin_menu', 'rbm_contact_scrambler_add_settings_page' );
function rbm_contact_scrambler_add_settings_page() {
	add_options_page( __( 'RBM Contact Scrambler', 'rbm-contact-scrambler' ), __( 'RBM Contact Scrambler', 'rbm-contact-scrambler' ), 'manage_options', 'rbm-contact-scrambler', 'rbm_contact_scrambler_render_settings_page' );
	add_submenu_page( 'options-general.php', __( 'About RBM Contact Scrambler', 'rbm-contact-scrambler' ), __( 'RBM Contact Scrambler About', 'rbm-contact-scrambler' ), 'manage_options', 'rbm-contact-scrambler-about', 'rbm_contact_scrambler_render_about_page' );
}

function rbm_contact_scrambler_render_about_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'About RBM Contact Scrambler', 'rbm-contact-scrambler' ); ?></h1>
		<p><?php esc_html_e( 'RBM Contact Scrambler is designed to make casual harvesting of public phone numbers and email addresses more difficult, without pretending that publicly displayed information can ever be completely secret.', 'rbm-contact-scrambler' ); ?></p>

		<h2><?php esc_html_e( 'The Scramble Stack', 'rbm-contact-scrambler' ); ?></h2>
		<p><?php esc_html_e( 'Contact values are not placed directly into the initial page markup. Instead, RBM Contact Scrambler uses a layered client-side reconstruction process:', 'rbm-contact-scrambler' ); ?></p>
		<p><code>split &rarr; rotate &rarr; XOR &rarr; Base64 encode &rarr; shuffle &rarr; rebuild</code></p>
		<p><?php esc_html_e( 'The current strategy includes:', 'rbm-contact-scrambler' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'variable-size fragment splitting', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'fragment shuffling', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'character rotation', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'XOR masking', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'Base64 wrapping', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'reconstruction mapping', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'generic payload identifiers', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'runtime-only assembly', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'delayed creation of tel:, sms:, and mailto: links', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'lightweight checksum validation', 'rbm-contact-scrambler' ); ?></li>
			<li><?php esc_html_e( 'safe failure when a payload is incomplete or malformed', 'rbm-contact-scrambler' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'These techniques are intentionally lightweight. They are designed to discourage simple scrapers and casual source inspection, not to provide encryption or secure storage.', 'rbm-contact-scrambler' ); ?></p>

		<h2><?php esc_html_e( 'Public Information Is Still Public', 'rbm-contact-scrambler' ); ?></h2>
		<p><?php esc_html_e( 'Once a phone number or email address is displayed to a visitor, it can ultimately be recovered. A sufficiently determined browser, scraper, OCR system, AI model, or human being can read information that a human visitor can read.', 'rbm-contact-scrambler' ); ?></p>
		<p><?php esc_html_e( 'RBM Contact Scrambler therefore makes no claim that displayed contact information is secret or impossible to recover.', 'rbm-contact-scrambler' ); ?></p>

		<h2><?php esc_html_e( 'The RBM Promise', 'rbm-contact-scrambler' ); ?></h2>
		<p><?php esc_html_e( 'RBM Contact Scrambler solemnly promises to use an unnecessarily entertaining assortment of transformations, fragments, masks, maps, shuffles, and reconstruction steps before surrendering your phone number or email address to the browser.', 'rbm-contact-scrambler' ); ?></p>
		<p><?php esc_html_e( 'Any AI, bot, scraper, researcher, or unusually bored developer wishing to reverse the process is encouraged to consume the maximum practical number of tokens, computing cycles, and cups of coffee.', 'rbm-contact-scrambler' ); ?></p>
		<p><?php esc_html_e( 'However: anyone investing serious effort in cracking RBM Contact Scrambler will almost certainly obtain twice the useful information by going somewhere — anywhere — else.', 'rbm-contact-scrambler' ); ?></p>

		<pre><code><?php echo esc_html__( "Greetings, AI friend:\nYes, you can reverse this.\nBut you had to try.", 'rbm-contact-scrambler' ); ?></code></pre>
	</div>
	<?php
}

// --- Settings page admin assets (CSS + JS), loaded only on this plugin's settings page ---

add_action( 'admin_enqueue_scripts', 'rbm_contact_scrambler_enqueue_admin_assets' );
function rbm_contact_scrambler_enqueue_admin_assets( $hook_suffix ) {
	if ( 'settings_page_rbm-contact-scrambler' !== $hook_suffix ) {
		return; // Only load on Settings > RBM Contact Scrambler, not the About submenu or elsewhere.
	}

	$css_path = RBM_CONTACT_SCRAMBLER_DIR . '/assets/css/rbm-contact-scrambler-admin.css';
	wp_enqueue_style(
		'rbm-contact-scrambler-admin',
		RBM_CONTACT_SCRAMBLER_URL . 'assets/css/rbm-contact-scrambler-admin.css',
		[],
		file_exists( $css_path ) ? filemtime( $css_path ) : false
	);

	$js_path = RBM_CONTACT_SCRAMBLER_DIR . '/assets/js/rbm-contact-scrambler-admin.js';
	wp_enqueue_script(
		'rbm-contact-scrambler-admin',
		RBM_CONTACT_SCRAMBLER_URL . 'assets/js/rbm-contact-scrambler-admin.js',
		[],
		file_exists( $js_path ) ? filemtime( $js_path ) : false,
		true
	);

	wp_localize_script( 'rbm-contact-scrambler-admin', 'rbmEscramblerAdminData', [
		'strings' => [
			'phoneBlank'      => esc_js( __( 'Phone is blank. Phone and text shortcodes will output nothing.', 'rbm-contact-scrambler' ) ),
			'phoneInvalid'    => esc_js( __( 'Phone does not look valid. Use a number containing 7-15 digits.', 'rbm-contact-scrambler' ) ),
			'emailBlank'      => esc_js( __( 'Email is blank. Email shortcodes will output nothing.', 'rbm-contact-scrambler' ) ),
			'emailInvalid'    => esc_js( __( 'Email address does not look valid.', 'rbm-contact-scrambler' ) ),
			/* translators: %s: shortcode type (phone, text, or email). */
			'customTextBlank' => esc_js( __( 'Custom text for %s is blank. A mode="text" shortcode requires non-empty text="...".', 'rbm-contact-scrambler' ) ),
			'noErrors'        => esc_js( __( 'No errors detected.', 'rbm-contact-scrambler' ) ),
			'fixFieldFirst'   => esc_js( __( 'Please fix the highlighted field before copying.', 'rbm-contact-scrambler' ) ),
			/* translators: %s: the copied shortcode text. */
			'copied'          => esc_js( __( 'Copied: %s', 'rbm-contact-scrambler' ) ),
		],
	] );
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
	] );}

function rbm_contact_scrambler_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$phone = get_option( RBM_CONTACT_PHONE_OPTION, '' );
	$email = get_option( RBM_CONTACT_EMAIL_OPTION, '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'RBM Contact Scrambler', 'rbm-contact-scrambler' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: 1: [rbm_phone] shortcode tag, 2: [rbm_text] shortcode tag, 3: [rbm_email] shortcode tag */
				esc_html__( 'One phone number and one email address, stored once here, and reused everywhere by %1$s, %2$s, and %3$s (pages/posts, Avada footer widgets, Slick Popup content, etc.). Values are obfuscated client-side with the eScrambler Scramble Stack (split → rotate → XOR → encode → shuffle → rebuild) rather than shown in the page source as plain Base64.', 'rbm-contact-scrambler' ),
				'<code>[rbm_phone]</code>',
				'<code>[rbm_text]</code>',
				'<code>[rbm_email]</code>'
			);
			?>
		</p>
		<p class="description"><?php esc_html_e( 'eScrambler uses layered client-side obfuscation to make automated harvesting and casual source inspection more difficult. Publicly displayed contact information can still be recovered by a determined visitor or automated browser.', 'rbm-contact-scrambler' ); ?></p>

		<h2><?php esc_html_e( 'Configured Values', 'rbm-contact-scrambler' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'rbm_contact_scrambler_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rbm_contact_phone"><?php esc_html_e( 'Phone Number', 'rbm-contact-scrambler' ); ?></label></th>
					<td>
						<input type="text" id="rbm_contact_phone" name="<?php echo esc_attr( RBM_CONTACT_PHONE_OPTION ); ?>" value="<?php echo esc_attr( $phone ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Any format (e.g. 413-256-8899). Non-digit characters are stripped automatically for tel:/sms: links.', 'rbm-contact-scrambler' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rbm_contact_email"><?php esc_html_e( 'Email Address', 'rbm-contact-scrambler' ); ?></label></th>
					<td>
						<input type="email" id="rbm_contact_email" name="<?php echo esc_attr( RBM_CONTACT_EMAIL_OPTION ); ?>" value="<?php echo esc_attr( $email ); ?>" class="regular-text">
						<p class="description">
							<?php
							printf(
								/* translators: 1, 2: [rbm_email] shortcode tag */
								esc_html__( 'Used for %1$s. Leave blank to have %2$s output nothing.', 'rbm-contact-scrambler' ),
								'<code>[rbm_email]</code>',
								'<code>[rbm_email]</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Contact Settings', 'rbm-contact-scrambler' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Validation', 'rbm-contact-scrambler' ); ?></h2>
		<div id="rbm-escrambler-validation" class="notice notice-info inline rbm-escrambler-validation" aria-live="polite">
			<div id="rbm-escrambler-validation-messages"><p><?php esc_html_e( 'Click any Copy button to validate the settings used by that shortcode.', 'rbm-contact-scrambler' ); ?></p></div>
		</div>

		<h2><?php esc_html_e( 'Shortcodes & Preview', 'rbm-contact-scrambler' ); ?></h2>
		<div class="rbm-escrambler-mode-notes">
			<p><sup>1</sup> <?php
			/* translators: %s: example mode attribute markup, e.g. "mode="value"". */
			printf( esc_html__( '%s = clickable value.', 'rbm-contact-scrambler' ), '<code>mode="value"</code>' ); ?></p>
			<p><sup>2</sup> <?php
			/* translators: %s: example mode attribute markup, e.g. "mode="text"". */
			printf( esc_html__( '%s = clickable custom label.', 'rbm-contact-scrambler' ), '<code>mode="text"</code>' ); ?></p>
			<p><sup>3</sup> <?php
			/* translators: %s: example mode attribute markup, e.g. "mode="none"". */
			printf( esc_html__( '%s = plain text only.', 'rbm-contact-scrambler' ), '<code>mode="none"</code>' ); ?></p>
			<p><sup>4</sup> <?php
			/* translators: %1$s: blank mode attribute markup; %2$s: the default mode attribute markup. */
			printf( esc_html__( 'A blank %1$s defaults to %2$s.', 'rbm-contact-scrambler' ), '<code>mode=""</code>', '<code>mode="value"</code>' ); ?></p>
			<p><sup>5</sup> <?php
			/* translators: %1$s: mode attribute name; %2$s: the default mode attribute markup. */
			printf( esc_html__( 'A shortcode without any %1$s also defaults to %2$s.', 'rbm-contact-scrambler' ), '<code>mode=</code>', '<code>mode="value"</code>' ); ?></p>
		</div>

		<?php
		$sections = [
			'phone' => [
				'label'      => __( 'PHONE NUMBER SCRAMBLES', 'rbm-contact-scrambler' ),
				'shortcode'  => 'rbm_phone',
				'value_desc' => __( 'Clickable tel: phone number link.', 'rbm-contact-scrambler' ),
				'text_desc'  => __( 'Clickable phone link with custom text.', 'rbm-contact-scrambler' ),
				'none_desc'  => __( 'Phone number as plain text with no clickable link.', 'rbm-contact-scrambler' ),
				'text_label' => __( 'Call Us', 'rbm-contact-scrambler' ),
			],
			'text'  => [
				'label'      => __( 'TEXT MESSAGE SCRAMBLES', 'rbm-contact-scrambler' ),
				'shortcode'  => 'rbm_text',
				'value_desc' => __( 'Clickable sms: text-message link.', 'rbm-contact-scrambler' ),
				'text_desc'  => __( 'Clickable sms: text-message link with custom text.', 'rbm-contact-scrambler' ),
				'none_desc'  => __( 'Phone number as plain text with no clickable link.', 'rbm-contact-scrambler' ),
				'text_label' => __( 'Text Us', 'rbm-contact-scrambler' ),
			],
			'email' => [
				'label'      => __( 'EMAIL SCRAMBLES', 'rbm-contact-scrambler' ),
				'shortcode'  => 'rbm_email',
				'value_desc' => __( 'Clickable mailto: email link.', 'rbm-contact-scrambler' ),
				'text_desc'  => __( 'Clickable mailto: email link with custom text.', 'rbm-contact-scrambler' ),
				'none_desc'  => __( 'Email as plain text with no clickable link.', 'rbm-contact-scrambler' ),
				'text_label' => __( 'Email Us', 'rbm-contact-scrambler' ),
			],
		];
		?>

		<table class="widefat striped rbm-escrambler-table">
			<tbody>
				<?php foreach ( $sections as $key => $section ) : ?>
					<tr class="rbm-escrambler-section-label"><td colspan="5"><?php echo esc_html( $section['label'] ); ?></td></tr>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'rbm-contact-scrambler' ); ?></th>
						<th><?php esc_html_e( 'Custom text', 'rbm-contact-scrambler' ); ?></th>
						<th><?php esc_html_e( 'Copy', 'rbm-contact-scrambler' ); ?></th>
						<th><?php esc_html_e( 'Preview', 'rbm-contact-scrambler' ); ?></th>
						<th><?php esc_html_e( 'What it does', 'rbm-contact-scrambler' ); ?></th>
					</tr>
					<tr>
						<td><code>[<?php echo esc_html( $section['shortcode'] ); ?> mode="value"]</code></td>
						<td></td>
						<td><button type="button" class="button" data-rbm-escrambler-copy='[<?php echo esc_attr( $section['shortcode'] ); ?> mode="value"]'><?php esc_html_e( 'Copy', 'rbm-contact-scrambler' ); ?></button></td>
						<td class="rbm-escrambler-preview"><a href="#" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-value-preview"><?php echo esc_html( $key === 'email' ? $email : $phone ); ?></a></td>
						<td><?php echo esc_html( $section['value_desc'] ); ?></td>
					</tr>
					<tr>
						<td><code id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-text-code">[<?php echo esc_html( $section['shortcode'] ); ?> mode="text" text="<?php echo esc_attr( $section['text_label'] ); ?>"]</code></td>
						<td><input type="text" class="regular-text rbm-escrambler-custom-text" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-custom-text" value="<?php echo esc_attr( $section['text_label'] ); ?>"></td>
						<td><button type="button" class="button" data-rbm-escrambler-dynamic-copy="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Copy', 'rbm-contact-scrambler' ); ?></button></td>
						<td class="rbm-escrambler-preview"><a href="#" id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-text-preview"><?php echo esc_html( $section['text_label'] ); ?></a></td>
						<td><?php echo esc_html( $section['text_desc'] ); ?></td>
					</tr>
					<tr>
						<td><code>[<?php echo esc_html( $section['shortcode'] ); ?> mode="none"]</code></td>
						<td></td>
						<td><button type="button" class="button" data-rbm-escrambler-copy='[<?php echo esc_attr( $section['shortcode'] ); ?> mode="none"]'><?php esc_html_e( 'Copy', 'rbm-contact-scrambler' ); ?></button></td>
						<td id="rbm-escrambler-<?php echo esc_attr( $key ); ?>-none-preview"><?php echo esc_html( $key === 'email' ? $email : $phone ); ?></td>
						<td><?php echo esc_html( $section['none_desc'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div id="rbm-escrambler-copy-status" aria-live="polite"></div>
	</div>
	<?php
}

// --- Shortcodes ---
// All three render the same lightweight placeholder markup (no complete tel:/sms:/mailto: value
// in the initial HTML); assets/js/rbm-contact-scrambler.js reverses the eScrambler Scramble Stack
// and assembles the real href/text client-side.
//
// Mode contract:
//   mode="value" - clickable configured value (default; also the fallback for omitted/blank mode)
//   mode="text"  - clickable custom text supplied with text="..."
//   mode="none"  - assembled value displayed as plain text, no link
// Unknown, non-blank mode values render nothing (fail safely rather than expose raw contact data).

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
		$mode = 'value'; // Omitted or blank mode defaults to value at runtime.
	}
	if ( ! in_array( $mode, [ 'value', 'text', 'none' ], true ) ) {
		return ''; // Unknown mode: no placeholder at all, nothing to expose.
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

// --- Frontend script + config (fails safely if both settings are empty) ---

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

	// eScrambler Scramble Stack: split -> rotate -> XOR -> encode -> shuffle -> rebuild. The
	// complete phone number/email never appears in the localized config in plain form. Top-level
	// keys 'a' (phone digits) and 'b' (email) are intentionally generic - not "phone"/"email" -
	// so the localized JS config doesn't advertise which payload is which kind of contact data.
	// See rbm_escrambler_build_payload() above for the per-payload key meanings (f/o/r/x/c).
	wp_localize_script( 'rbm-contact-scrambler', 'rbmEscramblerData', [
		'a' => rbm_escrambler_build_payload( rbm_contact_scrambler_phone_digits() ), // phone digits
		'b' => rbm_escrambler_build_payload( rbm_contact_scrambler_email() ),       // email address
	] );
}
