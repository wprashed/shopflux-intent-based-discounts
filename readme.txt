=== Intent-Based Discounts ===
Contributors: wprashed
Tags: woocommerce, coupon, discounts, exit-intent, cart
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically offer WooCommerce discount coupons when exit intent or inactivity is detected.

== Description ==

Intent-Based Discounts helps reduce cart abandonment by showing a targeted discount offer at the right moment.

Features:

* Exit-intent detection on desktop devices.
* Inactivity-based trigger with configurable delay.
* One-click coupon apply via AJAX.
* Automatic WooCommerce coupon creation/sync from plugin settings.
* Configurable modal title, message, and CTA button text.
* Optional once-per-session offer display.
* Translation-ready text domain: `intent-based-discounts`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through the WordPress Plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Ensure WooCommerce is active.
4. Go to `Settings > Intent Discounts` and configure your coupon and modal content.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Will it create a coupon automatically? =

Yes. The configured coupon code is created or updated automatically when settings are saved.

= Can I show the popup only once? =

Yes. Enable `Show Once Per Browser Session` in plugin settings.

== Screenshots ==

1. Plugin settings screen.
2. Frontend discount offer modal.

== Changelog ==

= 1.0.0 =
* Initial release.
* Exit-intent and inactivity discount triggers.
* WooCommerce coupon auto-create/sync.
* AJAX coupon apply flow with cart redirect fallback.
* Admin settings for trigger behavior and modal content.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
