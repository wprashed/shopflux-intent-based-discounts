<?php
/**
 * Plugin Name: ShopFlux Intent-Based Discounts
 * Description: Auto-offer WooCommerce discount coupons when exit intent or inactivity is detected.
 * Version: 1.0.0
 * Author: Rashed Hossain Bappy
 * Author URI: https://rashed.blog
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: shopflux-intent-based-discounts
 */

if (! defined('ABSPATH')) {
    exit;
}

final class ShopFlux_Intent_Based_Discounts {
    private const OPTION_KEY = 'shopflux_ibd_settings';
    private const NONCE_ACTION = 'shopflux_ibd_offer_nonce';
    private const APPLY_NONCE_ACTION = 'shopflux_ibd_apply_coupon_nonce';

    public function __construct() {
        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        if (! $this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('template_redirect', [$this, 'apply_coupon_from_query']);

        add_action('wp_ajax_shopflux_ibd_get_offer', [$this, 'ajax_get_offer']);
        add_action('wp_ajax_nopriv_shopflux_ibd_get_offer', [$this, 'ajax_get_offer']);

        add_action('wp_ajax_shopflux_ibd_apply_offer', [$this, 'ajax_apply_offer']);
        add_action('wp_ajax_nopriv_shopflux_ibd_apply_offer', [$this, 'ajax_apply_offer']);
    }

    public function register_admin_menu() {
        add_options_page(
            __('Intent-Based Discounts', 'shopflux-intent-based-discounts'),
            __('Intent Discounts', 'shopflux-intent-based-discounts'),
            'manage_options',
            'shopflux-intent-based-discounts',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'shopflux_ibd_settings_group',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );

        add_settings_section(
            'shopflux_ibd_general_section',
            __('General Settings', 'shopflux-intent-based-discounts'),
            function () {
                echo '<p>' . esc_html__('Configure when and how the discount offer appears.', 'shopflux-intent-based-discounts') . '</p>';
            },
            'shopflux-intent-based-discounts'
        );

        $fields = [
            'enabled' => __('Enable Plugin', 'shopflux-intent-based-discounts'),
            'exit_intent_enabled' => __('Enable Exit Intent Trigger', 'shopflux-intent-based-discounts'),
            'inactivity_enabled' => __('Enable Inactivity Trigger', 'shopflux-intent-based-discounts'),
            'inactivity_seconds' => __('Inactivity Delay (seconds)', 'shopflux-intent-based-discounts'),
            'coupon_code' => __('Coupon Code', 'shopflux-intent-based-discounts'),
            'discount_type' => __('Discount Type', 'shopflux-intent-based-discounts'),
            'discount_amount' => __('Discount Amount', 'shopflux-intent-based-discounts'),
            'modal_title' => __('Modal Title', 'shopflux-intent-based-discounts'),
            'modal_message' => __('Modal Message', 'shopflux-intent-based-discounts'),
            'button_label' => __('Button Label', 'shopflux-intent-based-discounts'),
            'once_per_session' => __('Show Once Per Browser Session', 'shopflux-intent-based-discounts'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [$this, 'render_field'],
                'shopflux-intent-based-discounts',
                'shopflux_ibd_general_section',
                ['key' => $key]
            );
        }
    }

    public function sanitize_settings($input): array {
        if (! is_array($input)) {
            $input = [];
        }

        $input = wp_unslash($input);
        $defaults = $this->default_settings();
        $output = $defaults;

        $output['enabled'] = ! empty($input['enabled']) ? 1 : 0;
        $output['exit_intent_enabled'] = ! empty($input['exit_intent_enabled']) ? 1 : 0;
        $output['inactivity_enabled'] = ! empty($input['inactivity_enabled']) ? 1 : 0;
        $output['inactivity_seconds'] = max(5, (int) ($input['inactivity_seconds'] ?? $defaults['inactivity_seconds']));
        $output['coupon_code'] = $this->sanitize_coupon_code($input['coupon_code'] ?? $defaults['coupon_code']);

        $discount_type = sanitize_text_field($input['discount_type'] ?? $defaults['discount_type']);
        $allowed_types = ['percent', 'fixed_cart'];
        $output['discount_type'] = in_array($discount_type, $allowed_types, true) ? $discount_type : 'percent';

        $output['discount_amount'] = max(1, (float) ($input['discount_amount'] ?? $defaults['discount_amount']));
        $output['modal_title'] = sanitize_text_field($input['modal_title'] ?? $defaults['modal_title']);
        $output['modal_message'] = sanitize_textarea_field($input['modal_message'] ?? $defaults['modal_message']);
        $output['button_label'] = sanitize_text_field($input['button_label'] ?? $defaults['button_label']);
        $output['once_per_session'] = ! empty($input['once_per_session']) ? 1 : 0;

        $this->sync_coupon($output);

        return $output;
    }

    public function render_field(array $args) {
        $settings = $this->get_settings();
        $key = $args['key'];
        $name = self::OPTION_KEY . '[' . $key . ']';
        $value = $settings[$key] ?? '';

        switch ($key) {
            case 'enabled':
            case 'exit_intent_enabled':
            case 'inactivity_enabled':
            case 'once_per_session':
                printf(
                    '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
                    esc_attr($name),
                    checked((int) $value, 1, false),
                    esc_html__('Yes', 'shopflux-intent-based-discounts')
                );
                break;

            case 'inactivity_seconds':
                echo '<input type="number" min="5" step="1" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="small-text" />';
                break;

            case 'discount_type':
                echo '<select name="' . esc_attr($name) . '">';
                printf(
                    '<option value="percent" %1$s>%2$s</option>',
                    selected($value, 'percent', false),
                    esc_html__('Percentage', 'shopflux-intent-based-discounts')
                );
                printf(
                    '<option value="fixed_cart" %1$s>%2$s</option>',
                    selected($value, 'fixed_cart', false),
                    esc_html__('Fixed Cart', 'shopflux-intent-based-discounts')
                );
                echo '</select>';
                break;

            case 'discount_amount':
                echo '<input type="number" min="1" step="0.01" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="small-text" />';
                break;

            case 'modal_message':
                echo '<textarea name="' . esc_attr($name) . '" rows="4" cols="50" class="large-text">' . esc_textarea((string) $value) . '</textarea>';
                break;

            default:
                echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" class="regular-text" />';
                break;
        }
    }

    public function render_settings_page() {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Intent-Based Discounts', 'shopflux-intent-based-discounts'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('shopflux_ibd_settings_group');
                do_settings_sections('shopflux-intent-based-discounts');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function woocommerce_missing_notice() {
        if (! current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('Intent-Based Discounts requires WooCommerce to be active.', 'shopflux-intent-based-discounts') . '</p></div>';
    }

    public function enqueue_frontend_assets() {
        if (is_admin() || ! $this->is_offer_enabled()) {
            return;
        }

        wp_enqueue_style(
            'shopflux-ibd-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'shopflux-ibd-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            [],
            '1.0.0',
            true
        );

        $settings = $this->get_settings();

        wp_localize_script('shopflux-ibd-frontend', 'ShopFluxIBDConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'exitIntentEnabled' => (bool) $settings['exit_intent_enabled'],
            'inactivityEnabled' => (bool) $settings['inactivity_enabled'],
            'inactivitySeconds' => (int) $settings['inactivity_seconds'],
            'oncePerSession' => (bool) $settings['once_per_session'],
            'storageKey' => 'shopflux_ibd_offer_shown',
            'i18n' => [
                'close' => __('Close', 'shopflux-intent-based-discounts'),
                'fallback' => __('Go to cart manually', 'shopflux-intent-based-discounts'),
                'applying' => __('Applying...', 'shopflux-intent-based-discounts'),
            ],
        ]);
    }

    public function ajax_get_offer() {
        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'shopflux-intent-based-discounts')], 403);
        }

        if (! $this->is_offer_enabled()) {
            wp_send_json_error(['message' => __('Offer disabled.', 'shopflux-intent-based-discounts')], 400);
        }

        $settings = $this->get_settings();
        $coupon_code = $this->sanitize_coupon_code($settings['coupon_code']);
        $apply_url = add_query_arg(
            [
                'shopflux_ibd_apply_coupon' => $coupon_code,
                'shopflux_ibd_apply_nonce' => wp_create_nonce(self::APPLY_NONCE_ACTION),
            ],
            wc_get_cart_url()
        );

        wp_send_json_success([
            'couponCode' => $coupon_code,
            'title' => $settings['modal_title'],
            'message' => $settings['modal_message'],
            'buttonLabel' => $settings['button_label'],
            'applyUrl' => esc_url_raw($apply_url),
        ]);
    }

    public function ajax_apply_offer() {
        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'shopflux-intent-based-discounts')], 403);
        }

        if (! function_exists('WC') || ! WC()->cart) {
            wp_send_json_error(['message' => __('Cart not available.', 'shopflux-intent-based-discounts')], 400);
        }

        $coupon_code = $this->sanitize_coupon_code(wp_unslash($_POST['couponCode'] ?? ''));
        if (empty($coupon_code)) {
            wp_send_json_error(['message' => __('Missing coupon code.', 'shopflux-intent-based-discounts')], 400);
        }

        $settings = $this->get_settings();
        if ($coupon_code !== $this->sanitize_coupon_code($settings['coupon_code'])) {
            wp_send_json_error(['message' => __('Invalid coupon code.', 'shopflux-intent-based-discounts')], 400);
        }

        if (WC()->cart->has_discount($coupon_code)) {
            wp_send_json_success(['message' => __('Coupon already applied.', 'shopflux-intent-based-discounts')]);
        }

        $applied = WC()->cart->apply_coupon($coupon_code);
        if (! $applied) {
            wp_send_json_error(['message' => __('Could not apply coupon.', 'shopflux-intent-based-discounts')], 400);
        }

        wp_send_json_success([
            'message' => __('Coupon applied.', 'shopflux-intent-based-discounts'),
            'redirectUrl' => wc_get_cart_url(),
        ]);
    }

    public function apply_coupon_from_query() {
        if (! isset($_GET['shopflux_ibd_apply_coupon'])) {
            return;
        }

        if (! function_exists('WC') || ! WC()->cart) {
            return;
        }

        if (! $this->is_offer_enabled()) {
            return;
        }

        if (! isset($_GET['shopflux_ibd_apply_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['shopflux_ibd_apply_nonce'])), self::APPLY_NONCE_ACTION)) {
            return;
        }

        $coupon_code = $this->sanitize_coupon_code(wp_unslash($_GET['shopflux_ibd_apply_coupon']));
        if (empty($coupon_code)) {
            return;
        }

        if (! WC()->cart->has_discount($coupon_code)) {
            WC()->cart->apply_coupon($coupon_code);
        }

        wp_safe_redirect(remove_query_arg(['shopflux_ibd_apply_coupon', 'shopflux_ibd_apply_nonce']));
        exit;
    }

    private function sync_coupon(array $settings): void {
        $coupon_code = $this->sanitize_coupon_code($settings['coupon_code'] ?? '');

        if (empty($coupon_code) || ! function_exists('wc_get_coupon_id_by_code')) {
            return;
        }

        $coupon = new WC_Coupon($coupon_code);
        
        if (! $coupon->get_id()) {
            $coupon->set_code($coupon_code);
            $coupon->set_individual_use(false);
            $coupon->set_usage_limit(0);
            $coupon->set_usage_limit_per_user(0);
            $coupon->set_free_shipping(false);
            $coupon->set_exclude_sale_items(false);
        }

        $coupon->set_discount_type($settings['discount_type']);
        $coupon->set_amount($settings['discount_amount']);
        $coupon->save();
    }

    private function get_settings(): array {
        return wp_parse_args(
            get_option(self::OPTION_KEY, []),
            $this->default_settings()
        );
    }

    private function default_settings(): array {
        return [
            'enabled' => 1,
            'exit_intent_enabled' => 1,
            'inactivity_enabled' => 1,
            'inactivity_seconds' => 45,
            'coupon_code' => 'STAY10',
            'discount_type' => 'percent',
            'discount_amount' => 10,
            'modal_title' => __('Wait! Here is 10% Off', 'shopflux-intent-based-discounts'),
            'modal_message' => __('Use this coupon before checkout to save on your order.', 'shopflux-intent-based-discounts'),
            'button_label' => __('Apply Discount', 'shopflux-intent-based-discounts'),
            'once_per_session' => 1,
        ];
    }

    private function is_offer_enabled(): bool {
        $settings = $this->get_settings();
        return ! empty($settings['enabled']);
    }

    private function sanitize_coupon_code($coupon_code): string {
        $coupon_code = sanitize_text_field((string) $coupon_code);
        $coupon_code = strtoupper($coupon_code);
        $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', $coupon_code);

        return (string) $coupon_code;
    }

    private function is_woocommerce_active(): bool {
        return class_exists('WooCommerce') || function_exists('WC');
    }
}

new ShopFlux_Intent_Based_Discounts();
