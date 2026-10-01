=== RBM Contact Scrambler ===
Contributors: redbarnmusicschool
Tags: contact, obfuscation, phone, email, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple shortcodes for phone, text-message, and email links that use client-side obfuscation to make casual automated harvesting more difficult.

== Description ==

RBM Contact Scrambler provides simple shortcodes for displaying phone numbers, text-message links, and email addresses while making casual automated harvesting more difficult.

Contact values are stored once in WordPress settings and reused anywhere shortcodes are supported. The plugin does not place the configured phone number or email address directly into the initial page markup. Instead, it uses a lightweight client-side reconstruction process that splits, transforms, shuffles, and rebuilds the value when the page is loaded.

Available shortcodes:

* `[rbm_phone]` — phone link or phone number
* `[rbm_text]` — text-message link or phone number
* `[rbm_email]` — email link or email address

Supported modes:

* `mode="value"` — clickable contact value
* `mode="text"` — clickable custom label using `text="..."`
* `mode="none"` — plain text with no link

RBM Contact Scrambler is designed to discourage simple scraping and casual source inspection. **It is obfuscation, not encryption**, and any contact information displayed to a visitor can ultimately be recovered.

The plugin calls no external service, account, API, or tracking system. Phone number validation optionally uses a bundled, local, open-source PHP library (`giggsey/libphonenumber-for-php`, installed via Composer) — it ships with the plugin and runs entirely on the server with no network calls; see "Phone and email validation" below.

RBM Contact Scrambler is designed to work alongside your existing WordPress security, CAPTCHA, and anti-spam plugins. No additional security plugin is required for its standard operation.

= Shortcodes and modes =

Each shortcode supports a `mode` attribute:

* `mode="value"` — clickable link showing the configured value (default; also used when `mode` is omitted or blank).
* `mode="text"` — clickable link with custom text supplied via `text="..."`.
* `mode="none"` — the assembled value displayed as plain text, with no link.

`[rbm_phone]`

* `[rbm_phone mode="value"]` — clickable `tel:` link showing the phone number.
* `[rbm_phone mode="text" text="Call Us"]` — clickable `tel:` link with custom text.
* `[rbm_phone mode="none"]` — plain text, no `tel:` link.

`[rbm_text]`

* `[rbm_text mode="value"]` — clickable `sms:` link showing the phone number.
* `[rbm_text mode="text" text="Text Us"]` — clickable `sms:` link with custom text.
* `[rbm_text mode="none"]` — plain text, no `sms:` link.

`[rbm_email]`

* `[rbm_email mode="value"]` — clickable `mailto:` link showing the email address.
* `[rbm_email mode="text" text="Email Us"]` — clickable `mailto:` link with custom text.
* `[rbm_email mode="none"]` — plain text, no `mailto:` link.

An unknown, non-blank `mode` value renders nothing (fails safely). Any shortcode also renders nothing if its underlying setting is empty (fails safely, never outputs a broken link).

= Phone and email validation =

Settings > RBM Contact Scrambler validates both fields on save:

* Email is validated with WordPress's built-in `is_email()`.
* Phone numbers are validated with `giggsey/libphonenumber-for-php` (bundled under `vendor/`, installed via Composer) when present. US is assumed as the default country for numbers without an explicit international prefix (e.g. `413-256-8899`); numbers with an explicit `+<country code>` prefix (e.g. `+44 20 7946 0958`) are validated against that country instead. If the `vendor/` dependency is ever missing, phone validation falls back to a lightweight character-set and digit-count check.

Invalid, non-blank input is rejected with an inline error message, and the previously saved value is kept so a malformed submission can never overwrite a working setting. Blank is always allowed; the corresponding shortcode then renders nothing.

== Installation ==

1. Upload the `rbm-contact-scrambler` folder to `/wp-content/plugins/`, or install the plugin through the WordPress admin Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Settings > RBM Contact Scrambler and enter your phone number and email address.
4. Use `[rbm_phone]`, `[rbm_text]`, and `[rbm_email]` in any post, page, or widget that supports shortcodes.

The `vendor/` directory (containing `giggsey/libphonenumber-for-php` for phone validation) ships with the plugin, so no `composer install` step is required after upload. If you maintain this plugin from source and regenerate `vendor/`, run `composer install --no-dev` from the plugin directory first.

== Frequently Asked Questions ==

= Does this encrypt my phone number or email address? =

No. The plugin obfuscates the values with a layered split/rotate/XOR/encode/shuffle scheme so they are not stored or transmitted as plain text in the page markup, but the original value can still be reconstructed by a determined visitor or automated browser. Treat this as a deterrent against casual scraping, not as encryption or secure storage.

= Does this plugin call any external service or track visitors? =

No. No external service, analytics, tracking script, or third-party API is used or required. Phone number validation uses a bundled, local, open-source library (no network calls); all obfuscation and reassembly logic ships with the plugin and runs entirely in WordPress and the visitor's browser.

= Do I need a security plugin, CAPTCHA, or anti-spam plugin in addition to this one? =

No. RBM Contact Scrambler is designed to work alongside your existing WordPress security, CAPTCHA, and anti-spam plugins. No additional security plugin is required for its standard operation.

= What happens if I use an invalid mode value? =

The shortcode renders nothing (an empty string). This is intentional fail-safe behavior so an invalid attribute never produces a broken or misleading link.

= What happens if the phone number or email address setting is empty? =

The corresponding shortcode renders nothing rather than an empty or broken link.

= Where do I configure the phone number and email address? =

Settings > RBM Contact Scrambler. The settings page also shows a live shortcode reference and preview table with copy-to-clipboard buttons for all nine shortcode/mode combinations.

== Changelog ==

= 2.1.0 =
* Added server-side validation for the Phone Number and Email Address settings: email is validated with `is_email()`; phone numbers are validated with the bundled `giggsey/libphonenumber-for-php` library (US assumed by default, explicit international prefixes honored), falling back to a lightweight character-set/digit-count check if that dependency isn't present.
* Invalid, non-blank settings are now rejected on save with an inline error message, and the previously saved value is preserved so a malformed submission can't overwrite a working setting.
* Documented that the plugin is designed to work alongside existing WordPress security, CAPTCHA, and anti-spam plugins, and does not require an additional security plugin for standard operation.

= 2.0.0 =
* Internationalized all admin and user-visible strings with the `rbm-contact-scrambler` text domain.
* Added `Requires at least` and `Requires PHP` headers.
* Moved inline admin CSS and JavaScript into properly enqueued plugin asset files, loaded only on this plugin's settings page.
* Added explanatory comments documenting the eScrambler Scramble Stack's internal field names for reviewers, without changing the obfuscation behavior.

= 1.0.0 =
* Initial release: `[rbm_phone]`, `[rbm_text]`, and `[rbm_email]` shortcodes with `value`, `text`, and `none` modes, and the eScrambler Scramble Stack obfuscation.

== Upgrade Notice ==

= 2.1.0 =
Adds server-side validation to the Phone Number and Email Address settings (including real phone-number validation via a bundled library). No shortcode behavior, obfuscation payload keys, or front-end styling changes.

= 2.0.0 =
No behavior changes for site visitors or existing shortcode usage; internal code readability, translations, and asset loading improvements only.
