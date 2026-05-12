# ShopFlux - Intent-Based Discounts

Intent-Based Discounts is a WooCommerce extension that automatically offers a coupon when users show exit intent or stay inactive.

## Features

- Exit-intent trigger (desktop).
- Inactivity trigger (configurable timeout).
- AJAX coupon apply with fallback cart URL.
- Auto-create/update coupon from settings.
- Configurable modal content.
- Option to show once per browser session.
- Internationalization-ready strings (`intent-based-discounts`).

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce (active)

## Installation

1. Place this directory in `wp-content/plugins/intent-based-discounts`.
2. Activate **Intent-Based Discounts** in WordPress admin.
3. Go to **Settings > Intent Discounts**.
4. Configure coupon and trigger settings, then save.

## Notes

- If WooCommerce is not active, the plugin shows an admin notice and frontend behavior is disabled.
- Coupon settings are synchronized when plugin settings are saved.

## License

GPLv2 or later. See [GNU GPL v2](https://www.gnu.org/licenses/gpl-2.0.html).
