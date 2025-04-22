<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Austin Patkos / APex / mwire Development Team
 * @package   mwire-crypto-payments
 * @version   1.0.3
 */

class WC_Gateway_EGift_Certificate extends WC_Payment_Gateway_CC
{
    const META_EGIFT_PIN = '_egift_pin';

    /**
     * Whether or not logging is enabled
     *
     * @var bool
     */
    public static $log_enabled = false;

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    public static $log = false;

    /**
     * @var string
     */
    protected $eGiftPaymentUrl = 'https://egiftcert.paynup.com';

    /**
     * @var string
     */
    protected $eGiftWidgetScript = 'https://egiftcert-widget.paynup.com/index.js';

    /**
     * @var string
     */
    protected $apiUrl = 'https://api.paynup.com';

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string
     */
    protected $apiID;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'egift-certificate';
        $this->has_fields         = true;
        $this->order_button_text  = __('continue', 'woocommerce');
        $this->method_title       = __('mwire', 'woocommerce');
        $this->method_description = __('Use mwire to interchange for goods', 'woocommerce');
        $this->supports           = [
            'products',
        ];

        if (defined('EGIFT_PAYMENT_URL')) {
            $this->eGiftPaymentUrl = EGIFT_PAYMENT_URL;
        }

        if (defined('PAYNUP_API_URL')) {
            $this->apiUrl = PAYNUP_API_URL;
        }

        if (defined('EGIFT_WIDGET_SCRIPT')) {
            $this->eGiftWidgetScript = EGIFT_WIDGET_SCRIPT;
        }

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = "Credit/Debit via USDC";
        // Define user set variables.
        if (empty($this->description)) {
            $this->description = <<<HTML
        You will be redirected to a secure third-party service where you can purchase crypto and then send as payment.;
        HTML;
        }

        $this->debug  = 'yes' === $this->get_option('debug', 'no');
        $this->apiID  = $this->get_option('api_id');
        $this->apiKey = $this->get_option('api_key');

        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_egift-ipn', [$this, 'ipnHandler']);

        add_filter(
            'woocommerce_order_details_after_order_table_items',
            static function (WC_Order $order) {
                $pin = $order->get_meta(self::META_EGIFT_PIN);
                if ($pin) {
                    echo <<<HTML
        <tr>
        <th scope="row">
        eGift Certificate
        </th>
        <td>
        <b style="font-size: 120%; color: brown">$pin<b>
        </td>
        </tr>
        HTML;
                }

            }
        );
    }

    /**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     *
     * @return bool was anything saved?
     */
    public function process_admin_options()
    {
        $saved = parent::process_admin_options();

        if ('yes' !== $this->get_option('debug', 'no')) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->clear('egift-certificate');
        }

        return $saved;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     */
    public function process_payment($order_id)  //Do we need this? Get rid of this function since it will be done async?
    {
        /** @var WC_Order $order */
        $order = wc_get_order($order_id);
    
        // If a PIN already exists, skip external redemption logic
        if ($order->get_meta(self::META_EGIFT_PIN)) {
            $pin = $this->get_post_data()['egift-certificate-pin'] ?? null;
    
            if ($pin === $order->get_meta(self::META_EGIFT_PIN)) {
                return $this->redeemPurchasedPin($order);
            }
    
            wc_add_notice('Invalid eGiftCertificate, try again with a valid PIN.', 'error');
    
            return [
                'result' => 'failure',
            ];
        }
    
        // ✅ Create POST payload
        $payload = [
            'userId'     => $order->get_billing_email(), // You can modify this as needed
            'receiverId' => get_option('admin_email'), // or some custom field
            'amount'     => intval($order->get_total()), // e.g., convert $25.00 to 2500
            'orderId' => $order_id
        ];
    
        // ✅ Send POST request
        $response = wp_remote_post('https://api.mwire.co/prod/create-new-agreement', [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => 15,
        ]);
    
        if (is_wp_error($response)) {
            wc_add_notice('Could not initiate payment agreement. Please try again later.', 'error');
            return ['result' => 'failure'];
        }
    
            // Set order to on-hold status (so inventory is reserved)
    	$order->update_status('on-hold', 'Awaiting crypto payment processing');
    
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
    

    /**
     * After payment hook to render initialize the widget
     */
    public function afterPayment()//Do we need this? Get rid of this function since it will be done async?
    {
        global $wp;

        /** @var WC_Order $order */
        $order = wc_get_order($wp->query_vars['order-pay']);

        if ($order->get_payment_method() === $this->id && ! isset($_GET['pay_for_order'])) {
            $params = json_encode($this->getParams($order));

            $checkout = $order->get_checkout_payment_url();

            echo <<<HTML
        <script>
        const startEgiftCertificate = () => typeof eGiftCertificate === 'undefined' ?
        setTimeout(startEgiftCertificate, 100) : eGiftCertificate.onEvent(function(e){
                switch (e.name) {
                    case 'CLOSE':
                        window.location = '$checkout';
                        break;
                }
        }).start($params);
        startEgiftCertificate();
        </script>
        HTML;
        }
    }


    public function processPaymentOnNewOrder(WC_Order $order)
    {

        error_log('✅ processPaymentOnNewOrder was called');
        $order->add_order_note('✅ Custom on-hold flow triggered.');

                // Mark the order as on-hold (waiting for manual payment)
        $order->update_status('on-hold', 'Please Check your email for further instructions.');

        // Reduce stock levels
        wc_reduce_stock_levels($order->get_id());

        // Remove cart contents
        WC()->cart->empty_cart();	
            return [
                'result'   => 'success',
                'redirect' => $redirectUrl,
        ];
    }

    public function getParams(WC_Order $order)
    {
        $token = eGiftCertificate_JWT::encode(
            [
                'jti' => $order->get_id(),
                'iss' => $this->apiID,
                'iat' => (new DateTime('-2minutes'))->getTimestamp(),
                'exp' => (new DateTime('+4hours'))->getTimestamp(),
            ],
            $this->apiKey
        );

        $redirectUrl = $this->get_return_url($order);
        if ($this->get_option('redeem_in_store') === 'yes') {
            $redirectUrl = $order->get_checkout_payment_url();
        }

        $params = [
            'token'          => $token,
            'orderNumber'    => $order->get_id(),
            'amount'         => $order->get_total(),
            'receiptEmail'   => $order->get_billing_email(),
            'customerName'   => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
            'customerPhone'  => $order->get_billing_phone(),
            'billingAddress' => $order->get_billing_address_1(),
            'billingZipCode' => $order->get_billing_postcode(),
            'billingCity'    => $order->get_billing_city(),
            'billingState'   => $order->get_billing_state(),
            'redirectUrl'    => $redirectUrl,
            'IPNHandlerUrl'  => wc()->api_request_url('egift-ipn'),
            'autoRedirect'   => true,
            'autoRedeem'     => true,
            'qrCode'         => $this->get_option('qr_code') === 'yes',
            'version'        => getVersion(),
        ];

        return $params;
    }

    public function redeemPurchasedPin(WC_Order $order)
    {
        $pin = $order->get_meta(self::META_EGIFT_PIN);

        if ( ! $pin) {
            return [];
        }

        $mutation = <<<GraphQL
        mutation (\$pin: String!){
        pins {
            redeem(input: {pin: \$pin})
        }
        }
    GraphQL;

            $egiftGateway = new WC_Gateway_EGift_Certificate();

            $token = eGiftCertificate_JWT::encode(
                [
                    'jti'    => wp_generate_uuid4(),
                    'iss'    => $egiftGateway->get_option('api_id'),
                    'iat'    => (new DateTime('-2minutes'))->getTimestamp(),
                    'exp'    => (new DateTime('+4hours'))->getTimestamp(),
                    'ip'     => get_the_user_ip(),
                    'agent'  => wc_get_user_agent(),
                    'origin' => isset($_SERVER['HTTP_HOST']) ? wc_clean(wp_unslash($_SERVER['HTTP_HOST'])) : '',
                ],
                $egiftGateway->get_option('api_key')
            );

            $body     = json_encode(['query' => $mutation, 'variables' => ['pin' => $pin]]);
            $response = wp_remote_post(
                $this->apiUrl,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $token),
                    ],
                    'body'    => $body,
                ]
            );

            if (isset($response['body'])) {
                $data = @json_decode($response['body'], true);
                if (isset($data['data']['pins']['redeem']) && $data['data']['pins']['redeem']) {
                    // Remove cart
                    WC()->cart->empty_cart();

                    return [
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($order),
                    ];
                }
            }

            return [];
    }

    public function ipnHandler()
    {
        $token = @file_get_contents('php://input');

        if ( ! $token) {
            self::log('IPN received without a token in the body', 'error');
            wp_die('IPN Request Failure', 'eGiftCertificate IPN', ['response' => 500]);
        }

        try {
            $payload = eGiftCertificate_JWT::decode($token, $this->apiKey, ['HS256']);
        } catch (\Exception $exception) {
            self::log('IPN received with invalid token', 'error');
            wp_die($exception->getMessage(), 'eGiftCertificate IPN', ['response' => 500]);
            exit;
        }

        if (isset($payload, $payload->orderNumber)) {
            $order = wc_get_order($payload->orderNumber);

            //allow up to 10 cents of difference in amount
            $diff = abs($payload->amount - $order->get_total());
            if ($payload->iss !== $this->apiID
                || ! $order
                || $diff > 0.10
            ) {
                self::log('IPN received with invalid token content, invalid order or amount', 'error');
                wp_die('IPN does not match with any existent order', 'eGiftCertificate IPN', ['response' => 500]);
                exit;
            }

            if ($payload->status === 'SOLD') {
                self::log('IPN received with SOLD status of eGiftCertificate');
                $order->add_order_note(sprintf('eGiftCertificate obtained: %s', $payload->pin));
                $order->add_meta_data(self::META_EGIFT_PIN, $payload->pin);
                $order->save_meta_data();
                header('Status: 200 OK');
                exit;
            }

            if ($payload->status === 'USED' && $order->get_meta(self::META_EGIFT_PIN) === $payload->pin) {
                self::log('IPN received with USED status of eGiftCertificate');
                $order->add_order_note('eGiftCertificate validated & redeemed successfully');
                $order->payment_complete($payload->pin);
                header('Status: 200 OK');
                exit;
            }
        } else {
            self::log('IPN received with invalid payload');
            wp_die('Invalid IPN Payload', 'eGiftCertificate IPN', ['response' => 500]);
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = include __DIR__.DIRECTORY_SEPARATOR.'settings.php';
    }

    /**
     * @return bool
     */
    public function has_fields()
    {
        return is_checkout() && is_wc_endpoint_url('order-pay');
    }

    /**
     * Frontend Form for PIN Redemption
     */
    public function form()
    {
        if ( ! $this->has_fields()) {
            $description = $this->get_description();
            if ($description) {
                echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
            }

            return;
        }

        global $wp;
        $order_id = $wp->query_vars['order-pay'];
        $order    = new WC_Order($order_id);

        if ( ! $order->get_meta(self::META_EGIFT_PIN)) {
            echo $this->description;

            return;
        }

        $fields = [];

        $autoRedeem = null;
        if ($this->get_option('auto_redeem') === 'yes') {
            $autoRedeem = <<<HTML
        <script>
        window.addEventListener('load', function(){
            document.getElementById("place_order").click();
        })
        </script>
        HTML;

        }

        $description = null;
        if ($description = $this->get_option('description_redeem_v2')) {
            $description = <<<HTML
        <p>
        $description
        </p>
        HTML;

        }

        $default_fields = [
            'pin-field' => '<p class="form-row form-row-wide">
				<label for="'.esc_attr($this->id).'-pin">'.esc_html__('eGift Certificate', 'woocommerce').'&nbsp;<span class="required">*</span></label>
				<input value="'.$order->get_meta(self::META_EGIFT_PIN).'" id="'.esc_attr($this->id)
                           .'-pin" required="required" style="font-size:18px" class="input-text" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" '
                           .$this->field_name('pin').' />
			    '.$autoRedeem.'
			    '.$description.'
			    <script>
            document.getElementById("payment_method_egift-certificate").click();
        </script>
			</p>',
        ];

        $fields = wp_parse_args($fields, apply_filters('woocommerce_egift_form_fields', $default_fields, $this->id));
        ?>

        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
            <?php
            foreach ($fields as $field) {
                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
            }
            ?>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'. Possible values:
     *                        emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log($message, $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, ['source' => 'egift-certificate']);
        }
    }
}


add_action('woocommerce_before_main_content', 'mwire_custom_top_thankyou_notice', 5);

function mwire_custom_top_thankyou_notice() {
    if (!is_order_received_page()) return;

    $order_id = absint(get_query_var('order-received'));
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'egift-certificate') return;

    echo '<div class="woocommerce-notice woocommerce-info" style="font-size: 1.15em; background: #fff3cd; border-left: 4px solid #ffeeba; padding: 16px; margin: 20px 0;">
        ⚠️ <strong>Your order is not yet complete.</strong><br>
        Please check your email inbox for further instructions to complete your payment.
    </div>';
}


add_action('template_redirect', 'mwire_top_notice_render');

function mwire_top_notice_render() {
    if (!is_order_received_page()) return;

    $order_id = absint(get_query_var('order-received'));
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'egift-certificate') return;

    add_action('wp_body_open', function () {
        echo '<div class="woocommerce-notice woocommerce-info" style="font-size: 1.15em; background: #fff3cd; border-left: 4px solid #ffeeba; padding: 16px; margin: 20px;">
            ⚠️ <strong>Your order is not yet complete.</strong><br>
            Please check your email inbox for further instructions to complete your payment.
        </div>';
    }, 0);
}

