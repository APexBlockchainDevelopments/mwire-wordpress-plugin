<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_EGift_Certificate_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'egift-certificate';// your payment gateway name

    public function initialize() {
        $this->settings = get_option( 'woocommerce_egift-certificate_settings', [] );
        $this->gateway = new WC_Gateway_EGift_Certificate();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

            error_log('í³¦ get_payment_method_script_handles called');

    wp_register_script(
        'egift-certificate-blocks-integration',
        plugin_dir_url(__FILE__) . 'checkout.js',
        [
            'wp-element',
            'wp-i18n',
            'wc-settings',
            'wc-blocks-registry',
            'wp-html-entities',
        ],
        time(),
        true
    );


        return [ 'egift-certificate-blocks-integration' ];
    }

    public function get_payment_method_data() {
	 error_log('âœ… get_payment_method_data called');
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description, // âœ… Add this line
            'supports' => ['products'], // or pass features if needed
        ];
    }

}
?>
