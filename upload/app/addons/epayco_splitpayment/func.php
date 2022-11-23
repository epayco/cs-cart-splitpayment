<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

use Tygh\Enum\VendorPayoutApprovalStatuses;
use Tygh\Enum\VendorPayoutTypes;
use Tygh\Http;
use Tygh\Registry;
use Tygh\Embedded;
use Tygh\Settings;
use Tygh\VendorPayouts;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * Installs payment processors, sets cronjob key and modifies table structure.
 *
 * @TODO: add TLS v1.2 support check
 */
function fn_pp_adaptive_payments_install()
{
    db_query("DELETE FROM ?:payment_processors WHERE processor_script = ?s", "epayco_splitpayment.php");

    $_data = array(
        'processor' => 'Epayco Adaptive',
        'processor_script' => 'epayco_splitpayment.php',
        'processor_template' => 'addons/epayco_splitpayment/views/orders/components/payments/epayco_splitpayment.tpl',
        'admin_template' => 'epayco_splitpayment.tpl',
        'callback' => 'Y',
        'type' => 'P',
        'addon' => 'epayco_splitpayment',
    );

    db_query("INSERT INTO ?:payment_processors ?e", $_data);
}

function fn_pp_adaptive_payments_uninstall()
{
    db_query("DELETE FROM ?:payment_processors WHERE processor_script = ?s", "epayco_splitpayment.php");
}

function fn_epayco_splitpayment_update_company_pre(&$company_data, $company_id, $lang_code, &$can_update)
{
    $company_data['company_id'] = $company_id;
    fn_epayco_splitpayment_get_verified_status($company_data);
    unset($company_data['company_id']);
    if (!empty($company_data['epayco_email'])) {
        $company_ids_with_same_email = db_get_fields('SELECT company_id FROM ?:companies WHERE epayco_email = ?s AND company_id != ?i', $company_data['epayco_email'], $company_id);
    }
    if (!empty($company_ids_with_same_email)) {
        fn_set_notification('E', __('error'), __('epayco_emails_must_be_unique'));
        $can_update = false;
        db_query('UPDATE ?:companies SET epayco_verification = ?s WHERE company_id = ?i', 'not_checked', $company_id);
    }
}

function fn_epayco_clear_queue($parent_order_id)
{
    if (!empty($parent_order_id)) {
        db_query("DELETE FROM ?:order_data WHERE type = ?s AND order_id = ?i", QUEUE_PAYMENT_ORDERS, $parent_order_id);
    }
}

/**
 * Create queue
 * 
 * @param  array $params Params
 * @return array
 */
function fn_epayco_create_queue($params)
{
    $data_orders = array();
    fn_epayco_clear_queue($params['order_id']);

    $child_order_ids = fn_epayco_splitpayment_get_childs_orders($params['order_id']);

    while (count($child_order_ids)) {
        $order = array(
            'status' => QUEUE_PAYMENT_ORDERS,
        );

        for ($order_num = 1; $order_num <= MAX_AMOUNT_VENDORS_IN_ORDER; $order_num++) {
            $child_order_id = array_shift($child_order_ids);

            if (empty($child_order_id)) {
                break;
            }

            $order['order_ids'][] = $child_order_id;
        }

        $data_orders[] = $order;
    }

    $data = array (
        'order_id' => $params['order_id'],
        'type' => QUEUE_PAYMENT_ORDERS,
        'data' => serialize($data_orders)
    );

    db_query("REPLACE INTO ?:order_data ?e", $data);

    return $data_orders;
}

function fn_epayco_get_queue($order_id)
{
    return unserialize(db_get_field("SELECT data FROM ?:order_data WHERE order_id = ?i AND type = ?s", $order_id, QUEUE_PAYMENT_ORDERS));
}

function fn_epayco_splitpayment_set_summa_data(&$queue_orders, $orders_data)
{
    foreach ($queue_orders as $queue_key => $queue) {

        $queue_orders[$queue_key]['total'] = 0;
        foreach ($queue['order_ids'] as $order_id) {
            $queue_orders[$queue_key]['total'] += $orders_data[$order_id]['total'];
        }
    }
}

function fn_epayco_splitpayment_get_childs_orders($order_id)
{
    static $child_orders;

    if (!isset($child_orders)) {
        $child_orders = db_get_fields("SELECT order_id FROM ?:orders WHERE is_parent_order != 'Y' AND parent_order_id = ?i ORDER BY order_id", $order_id);
    }

    return $child_orders;
}

function fn_epayco_get_orders_data($queue_orders)
{
    $orders_data = array();

    foreach ($queue_orders as $queue) {
        foreach ($queue['order_ids'] as $order_id) {
            $orders_data[$order_id] = fn_get_order_info($order_id);
        }
    }

    return $orders_data;
}

function fn_epayco_get_parent_order_id($order_ids)
{
    static $parent_order_id;

    if (!isset($parent_order_id)) {
        if (is_array($order_ids)) {
            $order_id = reset($order_ids);
        } else {
            $order_id = $order_ids;
        }
        $parent_order_id = db_get_field("SELECT parent_order_id FROM ?:orders WHERE order_id = ?i", $order_id);

        if (empty($parent_order_id)) {
            $parent_order_id = $order_id;
        }
    }

    return $parent_order_id;
}

function fn_epayco_splitpayment_check_finish($parent_order_id)
{
    $queue_orders = fn_epayco_get_queue($parent_order_id);

    if (!empty($queue_orders)) {
        foreach ($queue_orders as $queue) {
            if ($queue['status'] == QUEUE_PAYMENT_ORDERS) {
                return false;
            }
        }
    }

    return true;
}

function fn_epayco_splitpayment_set_status($order_id, $status)
{
    $parent_order_id = fn_epayco_get_parent_order_id($order_id);
    $queue_orders = fn_epayco_get_queue($parent_order_id);

    if (!empty($queue_orders)) {
        foreach ($queue_orders as $key => $queue) {
            if (in_array($order_id, $queue['order_ids'])) {
                $queue_orders[$key]['status'] = $status;
                break;
            }
        }

        $data = array (
            'order_id' => $parent_order_id,
            'type' => QUEUE_PAYMENT_ORDERS,
            'data' => serialize($queue_orders)
        );

        db_query('REPLACE INTO ?:order_data ?e', $data);
    }
}

function fn_epayco_splitpayment_any_paid($queue_orders)
{
    foreach ($queue_orders as $queue) {
        if ($queue['status'] == PAID_PAYMENT_ORDERS) {
            return true;
        }
    }

    return false;
}

function fn_epayco_splitpayment_payment_refund($transaction_id, $processor_data)
{
}

function fn_epayco_splitpayment_build_headers()
{
}

function fn_epayco_splitpayment_payment_details($pay_key, $processor_data, $type_key = 'payKey')
{
}

function fn_epayco_splitpayment_refund_status_message($refund_status)
{
    $statuses = array(
        'REFUNDED' => 'Refund successfully completed',
        'REFUNDED_PENDING' => 'Refund awaiting transfer of funds; for example, a refund paid by eCheck.',
        'NOT_PAID' => 'Payment was never made; therefore, it cannot be refunded.',
        'ALREADY_REVERSED_OR_REFUNDED' => 'Request rejected because the refund was already made, or the payment was reversed prior to this request.',
        'NO_API_ACCESS_TO_RECEIVER' => 'Request cannot be completed because you do not have third-party access from the receiver to make the refund.',
        'REFUND_NOT_ALLOWED' => 'Refund is not allowed.',
        'INSUFFICIENT_BALANCE' => 'Request rejected because the receiver from which the refund is to be paid does not have sufficient funds or the funding source cannot be used to make a refund.',
        'AMOUNT_EXCEEDS_REFUNDABLE' => 'Request rejected because you attempted to refund more than the remaining amount of the payment; call the PaymentDetails API operation to determine the amount already refunded.',
        'PREVIOUS_REFUND_PENDING' => 'Request rejected because a refund is currently pending for this part of the payment',
        'NOT_PROCESSED' => 'Request rejected because it cannot be processed at this time',
        'REFUND_ERROR' => 'Request rejected because of an internal error',
        'PREVIOUS_REFUND_ERROR' => 'Request rejected because another part of this refund caused an internal error.'
    );

    return !empty($statuses[$refund_status]) ? $statuses[$refund_status] : 'Un';
}

function fn_epayco_splitpayment_get_processor_data($payment_id = 0)
{
    if (empty($payment_id)) {
        $processor_id = db_get_field("SELECT processor_id FROM ?:payment_processors WHERE processor_script = 'epayco_splitpayment.php'");
        $payment_id = db_get_field("SELECT payment_id FROM ?:payments WHERE processor_id = ?i AND status = 'A'", $processor_id);
    }
    $processor_data = fn_get_processor_data($payment_id);

    return $processor_data;
}

/**
 * Request
 *
 * @param array $order_ids
 * @param array $processor_data
 * @param array $params
 * @return array
 */
function fn_epayco_splitpayment_request($order_ids = array(), $processor_data = array(), $params = array())
{
    $order_ids = array_diff($order_ids, array(''));

    $post_data = fn_epayco_splitpayment_build_post($order_ids, $processor_data);

    $response = null;

    parse_str($response, $pp_response);

    $order_id = fn_epayco_get_parent_order_id($order_ids);

    $order_info = fn_get_order_info($order_id);
    $p_tax = 0;
    $indice =array_keys($order_info["taxes"]);
    if($order_info["taxes"][$indice[0]]["tax_subtotal"] != 0) {
        $p_tax = $order_info["taxes"][$indice[0]]["tax_subtotal"];
    }

    $p_amount_base = 0;
    if($p_tax != 0) {
        $p_amount_base = $order_info['total'] - $p_tax;
    }


    $i = 0;
    $p_description = "";
    foreach ($order_info['products'] as $k => $v) {
        $i++;
        $p_description .= $v['product'];

        if($i != count($order_info['products'])) {
            $p_description .= "; ";
        }
    }

    $p_url_response = fn_url("payment_notification.response?payment=epayco_splitpayment&order_id=$order_id", AREA, 'current');
    $p_url_confirmation = fn_url("payment_notification.confirmation?payment=epayco_splitpayment&order_id=$order_id", AREA, 'current');
    $url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
    $server_name = str_replace('/confirmation/epayco/index','/checkout/onepage/success/',$url);
    $new_url = $server_name;

    if($processor_data['processor_params']['mode']=='test')
    {
        $test_method = 'true';
    }else{
        $test_method = 'false';
    }

    if($processor_data['processor_params']['p_test_request'] == 'ONEPAGE'){
        $external_method = 'false';
    }else{
        $external_method = 'true';
    }

    /** @var \Tygh\Location\Manager $location_manager */
    $location_manager = Tygh::$app['location'];
    if(empty( $_GET['ref_payco'])){
        echo sprintf('
            <script src="https://checkout.epayco.co/checkout.js">
            </script>
            <form id="appGateway">
                <script>
                    var handler = ePayco.checkout.configure({
                        key: "%s",
                        test: "%s"
                    })
                    var date = new Date().getTime();
                    var data = {
                        name: "%s",
                        description: "%s",
                        extra2: "%s",
                        currency: "%s",
                        amount: "%s",
                        tax: "%s",
                        tax_base: "%s",
                        country: "%s",
                        external: "%s",
                        response: "%s",
                        confirmation: "%s",
                        lang: "%s",
                        extra1: "%s",
                        email_billing: "%s",
                        address_billing: "%s"
                    }   
                
                var openChekout = function () {                   

                    console.log(data)
                    handler.open(data);
                }
                var js_array ='.json_encode($post_data).';
                let split_receivers = [];
                for(var jsa of js_array){
                    split_receivers.push({
                        "id" :  jsa.id,
                        "total": jsa.total,
                        "iva" : jsa.iva,
                        "base_iva": jsa.base_iva,
                        "fee" : jsa.fee
                    });
                }
                
                data.split_app_id= "%s", //Id de la cuenta principal
                data.split_merchant_id= "%s", //Id de la cuenta principal y a nombre de quien quedara la transacción
                data.split_type= "01", // tipo de dispersión 01 -> fija ---- 02 -> porcentual
                data.split_primary_receiver= "%s", // Id de la cuenta principal - parámetro para recibir valor de la dispersión destinado
                data.split_primary_receiver_fee= "0", // Parámetro no a utilizar pero que debe de ir en cero
                data.splitpayment= "false", // Indicación de funcionalidad split
                data.split_rule= "multiple", // Parámetro para configuración de Split_receivers - debe de ir por defecto en multiple
                data.split_receivers= split_receivers

                const urlRedirect = window.location.href;
                handler.onCloseModal = function () {};
                handler.onCreated(function(response) {
                }).onResponse(function(response) {
                }).onClosed(function(response) {
                   window.location.href = urlRedirect;
                });
         
                setTimeout(openChekout, 2000)  
                </script>
                
            </form>
        ',$processor_data['processor_params']['p_public_key'],$test_method,
            $p_description,$p_description,$order_id,$order_info['secondary_currency'],$order_info['total'], $p_tax,
            $p_amount_base, 'CO', $external_method, $p_url_response, $p_url_confirmation,"es",$order_id,
            '', '',$processor_data['processor_params']['p_cust_id_cliente'],$processor_data['processor_params']['p_cust_id_cliente'],
            $processor_data['processor_params']['p_cust_id_cliente']
        );
    }

}

/**
 * Build post data
 *
 * @param $order_ids
 * @param $processor_data
 * @return array
 */
function fn_epayco_splitpayment_build_post($order_ids, $processor_data)
{
    $ip = fn_get_ip();
    $epayco_currency = $processor_data['processor_params']['currency'];

    if ($processor_data['processor_params']['user_currency'] == 'Y') {
        $epayco_currency = CART_SECONDARY_CURRENCY;
    }

    $collect_payouts = Registry::get('addons.epayco_splitpayment.collect_payouts') == 'Y';

    if (!is_array($order_ids)) {
        $order_ids = array($order_ids);
    }
    $old_orders = $order_ids;
    $parent_order_id = fn_epayco_get_parent_order_id($order_ids);
    $str_order_ids = implode(',', $order_ids);
    if( count($parent_order_id)>0 ){
        $order_ids = db_get_fields("SELECT order_id FROM ?:orders WHERE parent_order_id = ?i", $parent_order_id);
    }
    if(empty($order_ids)){
        $order_ids = $old_orders;
    }
    $primary_email = $processor_data['processor_params']['primary_email'];

    $post_data = array();

    $order_index = 0;
    $total_admin_fee = 0;
    $receiversData = [];
    foreach ($order_ids as $order_id) {
        $order_info = fn_get_order_info($order_id);
        $p_tax = 0;
        $indice =array_keys($order_info["taxes"]);
        if($order_info["taxes"][$indice[0]]["tax_subtotal"] != 0) {
            $p_tax = $order_info["taxes"][$indice[0]]["tax_subtotal"];
        }

        $p_amount_base = 0;
        if($p_tax != 0) {
            $p_amount_base = $order_info['total'] - $p_tax;
        }
        $order_data = db_get_row("SELECT total, subtotal, company_id FROM ?:orders WHERE ?:orders.order_id = ?i", $order_id);
        $secondaries_email = db_get_row("SELECT ppa_first_name FROM ?:companies WHERE company_id = ?i", $order_data['company_id']);
        $secondary_email = !empty($secondaries_email['ppa_first_name']) ? $secondaries_email['ppa_first_name'] : $primary_email ;
        $order_data['order_id'] = $order_id;
        $admin_fee = fn_epayco_splitpayment_calc_admin_fee($order_data);
        $vendor_fee = $order_data['total'] - $admin_fee;
        $total_admin_fee += $admin_fee;
        if ($vendor_fee) {
            $post_data["id"] = $secondary_email;
            $post_data["total"] =$order_info['total'];
            $post_data["iva"] = $p_tax;
            $post_data["base_iva"] = 0;
            $post_data["fee"] = number_format((floatval($order_data['total']) - fn_format_price_by_currency($vendor_fee, CART_PRIMARY_CURRENCY, $epayco_currency)),2);
            //$post_data["receiverList.receiver($order_index).amount"] = fn_format_price_by_currency($vendor_fee, CART_PRIMARY_CURRENCY, $epayco_currency);
            array_push($receiversData, $post_data);
        }
    }

    return $receiversData;
}

/**
 * Calculate admin fee
 *
 * @param $order_data
 * @return float|int
 */
function fn_epayco_splitpayment_calc_admin_fee($order_data)
{
    if (fn_epayco_splitpayment_get_company_base_for_commission($order_data['company_id']) == 'O') {
        $total_to_fee = $order_data['total'];
    } else {
        $total_to_fee = $order_data['subtotal'];
    }

    $commission = db_get_row(
        'SELECT commission_amount, commission, commission_type'
        . ' FROM ?:vendor_payouts'
        . ' WHERE company_id = ?i'
        . ' AND order_id = ?i', $order_data['company_id'], $order_data['order_id']
    );
    $admin_fee = isset($commission['commission_amount']) ? $commission['commission_amount'] : 0;

    // hold back vendor payouts
    if (Registry::get('addons.epayco_splitpayment.collect_payouts') == 'Y') {
        $vendor_payouts = VendorPayouts::instance(array('vendor' => $order_data['company_id']));
        $pending_payouts = $vendor_payouts->getSimple(array(
            'payout_type' => VendorPayoutTypes::PAYOUT,
            'approval_status' => VendorPayoutApprovalStatuses::PENDING
        ));
        list($balance, ) = $vendor_payouts->getBalance();
        if ($pending_payouts) {
            if ($balance < 0) {
                $admin_fee += abs($balance);
            } else {
                $admin_fee += abs(array_sum(array_column($pending_payouts, 'payout_amount')));
            }
        }
    }

    if ($admin_fee > $total_to_fee) {
        $admin_fee = $total_to_fee;
    }

    return !empty($admin_fee) ? $admin_fee : 0;
}

/**
 * Get company base for commission
 *
 * @param $company_id
 * @return array|mixed|null
 */
function fn_epayco_splitpayment_get_company_base_for_commission($company_id)
{
    $base_for_commission = db_get_field("SELECT epayco_base_for_commission FROM ?:companies WHERE company_id = ?i", $company_id);

    if (empty($base_for_commission)) {
        $base_for_commission = Registry::get('addons.epayco_splitpayment.count_vendor_commission_on_basis');
    }

    return $base_for_commission;
}

/**
 * Hook. Changes params to get payment processors
 *
 * @param array $params    Array of flags/data which determines which data should be gathered
 * @param array $fields    List of fields for retrieving
 * @param array $join      Array with the complete JOIN information (JOIN type, tables and fields) for an SQL-query
 * @param array $order     Array containing SQL-query with sorting fields
 * @param array $condition Array containing SQL-query condition possibly prepended with a logical operator AND
 * @param array $having    Array containing SQL-query condition to HAVING group
 */
function fn_epayco_splitpayment_get_payments($params, $fields, $join, $order, &$condition, $having)
{
    $mode = Registry::get('runtime.mode');

    if (($mode == 'checkout' || $mode == 'add') && array_key_exists('product_groups', Tygh::$app['session']['cart'])) {

        $product_groups = Tygh::$app['session']['cart']['product_groups'];

        $company_ids = array();
        foreach ($product_groups as $group_key => $group_val) {
            $company_ids[$group_key] = $group_val['company_id'];
        }

        $check_company_ids = db_get_fields('SELECT company_id FROM ?:companies WHERE company_id IN (?n) AND epayco_email != ?s AND epayco_verification NOT IN (?a)', $company_ids, '', array('not_checked', 'not_verified'));

        foreach ($company_ids as $key => $val) {
            if (!in_array($val, $check_company_ids)) {
                $condition[] = db_quote(
                    ' IF(STRCMP(?:payment_processors.processor, ?s) = 0, 0, 1)',
                    EPAYCO_SPLITPAYMENT_PROCESSOR
                );
                break;
            }
        }
    }
}

/**
 * Update Epayco adaptive settings
 *
 * @param array $settings
 */
function fn_update_epayco_splitpayment_settings($settings)
{
    /*foreach ($settings as $setting_name => $setting_value) {
        Settings::instance()->updateValue($setting_name, $setting_value);
    }

    $company_id = Registry::get('runtime.company_id');

    fn_attach_image_pairs('epayco_splitpayment_logo', 'epayco_splitpayment_logo', $company_id);
    fn_attach_image_pairs('epayco_ver_image', 'epayco_ver_image');*/
}

/**
 * Get Epayco adaptive settings
 *
 * @param string $lang_code
 * @return array mixed
 */
function fn_get_epayco_splitpayment_settings($lang_code = DESCR_SL)
{
    $ppa_settings = Settings::instance()->getValues('epayco_splitpayment', 'ADDON');

    $company_id = Registry::get('runtime.company_id');

    $ppa_settings['general']['main_pair'] = fn_get_image_pairs($company_id, 'epayco_splitpayment_logo', 'M', false, true, $lang_code);

    return $ppa_settings['general'];
}

/**
 * Set payment options
 *
 * @param array $params
 *                payKey                 string
 *                headers                array
 *                epayco_sslcertpath     string
 *                processor_params       array
 *                epayco_set_options_url string
 */
function fn_epayco_splitpayment_set_options($params)
{
    if (!empty($params['pay_key'])
        && !empty($params['headers'])
        && isset($params['epayco_sslcertpath'])
        && !empty($params['processor_params'])
        && !empty($params['epayco_set_options_url'])
    ) {

        $post_data = fn_epayco_splitpayment_set_options_build_post($params);

        Http::post($params['epayco_set_options_url'], $post_data, array('headers' => $params['headers'], 'ssl_cert' => $params['epayco_sslcertpath']));
    }
}

/**
 * Set payment options build post
 *
 * @param  array $params
 * @return array
 */
function fn_epayco_splitpayment_set_options_build_post($params)
{
    $post_data = array();

    if (!empty($params['pay_key'])) {

        $post_data['payKey'] = $params['pay_key'];
        $post_data['requestEnvelope.errorLanguage'] = 'en_US';

        $company_id = Registry::get('runtime.company_id');
        $header_image = fn_get_image_pairs($company_id, 'epayco_splitpayment_logo', 'M', false, true);

        if (!empty($header_image['detailed']['image_path'])) {
            $post_data['displayOptions.headerImageUrl'] = $header_image['detailed']['image_path'];
        }

        $post_data['senderOptions.referrerCode'] = 'ST_ShoppingCart_EC_US';
    }

    return $post_data;
}

function fn_epayco_splitpayment_get_companies($params, &$fields, $sortings, $condition, $join, $auth, $lang_code, $group)
{
    $fields[] = '?:companies.epayco_verification';
    $fields[] = '?:companies.epayco_email';
    $fields[] = '?:companies.ppa_first_name';
}

function fn_epayco_splitpayment_get_verified_status($company_data)
{
}

function fn_sd_epayco_splitpayment_cs_info_url_auto_epayco_verification()
{
}

function fn_epayco_splitpayment_gather_additional_product_data_before_options(&$product, $auth, $params)
{
    if (empty($product['company_id'])) {
        return;
    }
}

function fn_epayco_splitpayment_calculate_commission($order_info, $company_data, $payout_data)
{
    return fn_calculate_commission_for_payout($order_info, $company_data, $payout_data);
}

function fn_epayco_splitpayment_mve_place_order(&$order_info, &$company_data, &$action, &$order_status, &$cart, &$data, &$payout_id, &$auth)
{
    list($is_processor_script, ) = fn_check_processor_script($order_info['payment_id']);
    if ($is_processor_script && fn_check_payment_script('epayco_splitpayment.php', $order_info['order_id'])) {
        $data = fn_epayco_splitpayment_calculate_commission($order_info, $company_data, $data);
    }
}

function fn_epayco_splitpayment_mve_update_order(&$new_order_info, &$order_id, &$old_order_info, &$company_data, &$payout_id, &$payout_data)
{
    list($is_processor_script, ) = fn_check_processor_script($new_order_info['payment_id']);
    if ($is_processor_script && fn_check_payment_script('epayco_splitpayment.php', $old_order_info['order_id'])) {
        $payout_data = fn_epayco_splitpayment_calculate_commission($new_order_info, $company_data, $payout_data);
    }
}

/**
 * Hook handler: excludes the commission amount from a transaction value for a vendor
 * and sets a transaction value to the commission amount for an admin.
 *
 * @param VendorPayouts $instance       VendorPayouts instance
 * @param array         $params         Search parameters
 * @param int           $items_per_page Items per page
 * @param array         $fields         SQL query fields
 * @param string        $join           JOIN statement
 * @param string        $condition      General condition to filter payouts
 * @param string        $date_condition Additional condition to filter payouts by date
 * @param string        $sorting        ORDER BY statemet
 * @param string        $limit          LIMIT statement
 */
function fn_epayco_splitpayment_vendor_payouts_get_list(&$instance, &$params, &$items_per_page, &$fields, &$join, &$condition, &$date_condition, &$sorting, &$limit)
{
    if ($instance->getVendor()) {
        $fields['payout_amount'] = 'IF(payouts.order_id <> 0, payouts.order_amount - payouts.commission_amount, payouts.payout_amount)';
    } else {
        $fields['payout_amount'] = 'IF(payouts.order_id <> 0, payouts.marketplace_profit, payouts.payout_amount)';
    }
}

/**
 * Hook handler: excludes commission from vendor income and sets admin income as a sum of commissions.
 *
 * @param VendorPayouts $instance       VendorPayouts instance
 * @param array         $params         Search parameters
 * @param array         $fields         SQL query fields
 * @param string        $join           JOIN statement
 * @param string        $condition      General condition to filter payouts
 * @param string        $date_condition Additional condition to filter payouts by date
 */
function fn_epayco_splitpayment_vendor_payouts_get_income(&$instance, &$params, &$fields, &$join, &$condition, &$date_condition)
{
    if ($instance->getVendor()) {
        $fields['orders_summary'] = 'SUM(payouts.order_amount) - SUM(payouts.commission_amount)';
    } else {
        $fields['orders_summary'] = 'SUM(payouts.marketplace_profit)';
    }
}
