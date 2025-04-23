<?php
/**
 *  LICENSE: This file is subject to the terms and conditions defined in
 *  file 'LICENSE', which is part of this source code package.
 *
 * @copyright 2025 Copyright(c) - All rights reserved.
 * @author    Austin Patkos / APex / mwire Development Team
 * @package   mwire
 * @version   1.0.4
 */

defined('ABSPATH') || exit;

return [
    'enabled' => [
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable mwire', 'woocommerce'),
        'default' => 'yes',
    ],
    'api_id' => [
        'title' => __('API ID', 'woocommerce'),
        'type' => 'text',
        'description' => __('Get your API credentials from mwire.co.', 'woocommerce'),
        'default' => '',
        'required'=> true,
        'desc_tip' => true,
    ],
    'debug' => [
        'title' => __('Debug log', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable logging', 'woocommerce'),
        'default' => 'no',
        'description' => sprintf(__('Log events. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce'), '<code>'.WC_Log_Handler_File::get_log_file_path('egift-certificate').'</code>'),
    ],
    'merchant_id' => [
        'title' => __('Merchant ID', 'woocommerce'),
        'type' => 'text',
        'description' => __('Enter or copy your merchant ID. If you do not have these please contact info@mwire.co.', 'woocommerce'),
        'default' => '',
        'desc_tip' => true,
    ],
    'api_key' => [
        'title' => __('API Key', 'woocommerce'),
        'type' => 'password',
        'description' => __('Get your API credentials from mwire. If you do not have these please contact info@mwire.co.', 'woocommerce'),
        'default' => '',
        'desc_tip' => true,
    ],

];