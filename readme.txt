=== RBM Contact Scrambler ===
Contributors: redbarnmusic
Tags: phone, email, obfuscation, click-to-call, contact
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Reusable, obfuscated click-to-call/text/email shortcodes for one configured public phone number
and email address. No third-party plugin or theme dependencies.

== Description ==

RBM Contact Scrambler stores one public phone number and one public email address, and exposes
them anywhere shortcodes are supported via `[rbm_phone]`, `[rbm_text]`, and `[rbm_email]`.

Values are never placed in the initial page markup as plaintext. Instead, the plugin uses a
layered client-side obfuscation process called the eScrambler Scramble Stack:

`split -> rotate -> XOR -> Base64 encode -> shuffle -> rebuild`

The current strategy includes variable-size fragment splitting, fragment shuffling, character
rotation, XOR masking, Base64 wrapping, a shuffled reconstruction map, generic (non-descriptive)
payload identifiers, runtime-only assembly of `tel:`/`sms:`/`mailto:` links, and a lightweight
checksum so malformed/incomplete payloads fail safely instead of exposing partial data.

This is obfuscation, not encryption or secure storage. Publicly displayed contact information can
still be recovered by a determined visitor or automated browser; the goal is only to make casual
source inspection and simple automated harvesting more difficult.

= Shortcode mode contract =

* `mode="value"` - clickable configured value (default; also used when mode is omitted or blank)
* `mode="text"` - clickable custom text supplied with `text="..."`
* `mode="none"` - assembled value displayed as plain text, no link

[rbm_phone mode="value"]                    Clickable tel: link showing the phone number.
[rbm_phone mode="text" text="Call Us"]      Clickable tel: link with custom text.
[rbm_phone mode="none"]                     Plain text, no tel: link.

[rbm_text mode="value"]                     Clickable sms: link showing the phone number.
[rbm_text mode="text" text="Text Us"]       Clickable sms: link with custom text.
[rbm_text mode="none"]                      Plain text, no sms: link.

[rbm_email mode="value"]                    Clickable mailto: link showing the email address.
[rbm_email mode="text" text="Email Us"]     Clickable mailto: link with custom text.
[rbm_email mode="none"]                     Plain text, no mailto: link.

`[rbm_phone]` / `[rbm_text]` alone display only the phone number (no "Text us at" prefix - labels
belong to the surrounding page content).

An unknown, non-blank mode renders nothing (fails safely). Any shortcode renders nothing usable
if its setting is empty (fails safely, no broken link).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/rbm-contact-scrambler`, or install the plugin
   through the WordPress Plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Settings > RBM Contact Scrambler and enter your phone number and email address.
4. Use `[rbm_phone]`, `[rbm_text]`, and `[rbm_email]` (with an optional `mode=` and `text=`)
   anywhere shortcodes are supported.

See Settings > RBM Contact Scrambler About for a fuller explanation of the Scramble Stack.

== Frequently Asked Questions ==

= Is this encryption? =

No. It is layered client-side obfuscation intended to raise the bar above plain Base64 for casual
source inspection and simple automated harvesting. It does not make the information secret.

= What happens if I use an unknown mode value? =

The shortcode renders nothing rather than exposing the raw configured value.

= What if I leave a setting blank? =

The corresponding shortcode renders nothing usable (fails safely, no broken link).

== Changelog ==

= 1.0.0 =
* Initial public release.
* eScrambler Scramble Stack (split/rotate/XOR/encode/shuffle/rebuild) client-side obfuscation.
* `mode="value"|"text"|"none"` shortcode contract for `[rbm_phone]`, `[rbm_text]`, `[rbm_email]`.
* Settings page with live shortcode preview/Copy for all nine shortcode forms.
* About page describing the Scramble Stack.



