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

use Tygh\Registry;
use Tygh\Settings;
use Tygh\Http;
use Tygh\Enum\VendorPayoutApprovalStatuses;
use Tygh\Enum\VendorPayoutTypes;
use Tygh\VendorPayouts;
use Tygh\Enum\YesNo;


if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * Installs ePayco payment processor.
 *
 * @return void
 */
function fn_epayco_install_payment_processors()
{
    /** @var \Tygh\Database\Connection $db */
    $db = Tygh::$app['db'];

    if ($db->getField('SELECT processor_id FROM ?:payment_processors WHERE processor_script = ?s', 'epayco.php')) {
        return;
    }

    $db->query(
        'INSERT INTO ?:payment_processors ?e',
        [
            'processor'          => 'ePayco',
            'processor_script'   => 'epayco.php',
            'processor_template' => 'views/orders/components/payments/epayco.tpl',
            'admin_template'     => 'epayco.tpl',
            'callback'           => 'Y',
            'type'               => 'P',
            'addon'              => 'epayco',
        ]
    );
}
function fn_epayco_uninstall_payment_processors()
{
    /** @var \Tygh\Database\Connection $db */
    $db = Tygh::$app['db'];

    $processor_id = $db->getField(
        'SELECT processor_id FROM ?:payment_processors WHERE processor_script = ?s',
        'epayco.php'
    );

    if (!$processor_id) {
        return;
    }

    $db->query('DELETE FROM ?:payment_processors WHERE processor_id = ?i', $processor_id);
    $db->query(
        'UPDATE ?:payments SET ?u WHERE processor_id = ?i',
        [
            'processor_id'     => 0,
            'processor_params' => '',
            'status'           => 'D',
        ],
        $processor_id
    );
}

function fn_epayco_uninstall_pages_processors()
{
    db_query("DELETE FROM ?:pages WHERE page_id IN ('201')");
}

function fn_epayco_uninstall_page_descriptions_processors()
{
    db_query("DELETE FROM ?:page_descriptions WHERE page_id IN ('201')");
}

/**
 * Hook handler: clears the cart in the session if IPN for placed orders is already received.
 *
 * @param array $auth       Current user session data
 * @param array $user_info  User infromation obtained from ::fn_get_user_short_info
 * @param bool  $first_init True if stored in session data used to log in the user
 */
function fn_epayco_user_init(&$auth, &$user_info, &$first_init)
{
    $orders_list = array();
    if (!empty(Tygh::$app['session']['cart']['processed_order_id'])) {
        $orders_list = array_merge($orders_list, (array)Tygh::$app['session']['cart']['processed_order_id']);
    }
    if (!empty(Tygh::$app['session']['cart']['failed_order_id'])) {
        $orders_list = array_merge($orders_list, (array)Tygh::$app['session']['cart']['failed_order_id']);
    }
    foreach ($orders_list as $order_id) {
        if (fn_is_epayco_ipn_received($order_id)) {
            fn_clear_cart(Tygh::$app['session']['cart']);
            fn_epayco_order_total_is_correct($order_id);
            break;
        }
    }
}


/**
 * Checks if Epayco IPN for the order is received by searching for the IPN receiving time
 * in the order's payment information.
 *
 * @param int $order_id The identifier of the order.
 *
 * @return bool True if IPN was received
 */
function fn_is_epayco_ipn_received($order_id)
{
    $order_info = fn_get_order_info($order_id);

    return $order_info['payment_method']['payment'] == "ePayco";
}


/**
 * Checks the total of the specified order against the session's order total to make sure
 * that the order was placed properly.
 *
 * @param int $order_id The identifier of the order.
 *
 * @return bool True if the order total is correct and matches the session's order total; false otherwise.
 */
function fn_epayco_order_total_is_correct($order_id)
{
    $order_info = fn_get_order_info($order_id);
    if (strpos(mb_strtolower($order_info['payment_method']['processor']), 'epayco') !== false) {
        if (!empty(Tygh::$app['session']['cart']['epayco_total'])
            && Tygh::$app['session']['cart']['epayco_total'] != Tygh::$app['session']['cart']['total']
        ) {
            Tygh::$app['session']['cart']['epayco_total'] = Tygh::$app['session']['cart']['total'];

            return true;
        }

        if (!empty(Tygh::$app['session']['cart']['total'])) {
            Tygh::$app['session']['cart']['epayco_total'] = Tygh::$app['session']['cart']['total'];
        }
    }

    return false;

}

function fn_epayco_prepare_checkout_payment_methods(&$cart, &$auth, &$payment_groups)
{
    if (isset($cart['payment_id'])) {
        foreach ($payment_groups as $tab => $payments) {
            foreach ($payments as $payment_id => $payment_data) {
                if (isset(Tygh::$app['session']['pp_epayco_details'])) {
                    if ($payment_id != $cart['payment_id']) {
                        unset($payment_groups[$tab][$payment_id]);
                    } else {
                        $_tab = $tab;
                    }
                }
            }
        }
        if (isset($_tab)) {
            $_payment_groups = $payment_groups[$_tab];
            $payment_groups = array();
            $payment_groups[$_tab] = $_payment_groups;
        }
    }
}

/**
 * Overrides user existence check results for guest customers who returned from Express Checkout
 *
 * @param int $user_id User ID
 * @param array $user_data User authentication data
 * @param boolean $is_exist True if user with specified email already exists
 */
function fn_epayco_is_user_exists_post($user_id, $user_data, &$is_exist)
{
    if (!$user_id && $is_exist) {
        if (isset(Tygh::$app['session']['pp_epayco_details']['token']) &&
            (empty($user_data['register_at_checkout']) || $user_data['register_at_checkout'] != 'Y') &&
            empty($user_data['password1']) && empty($user_data['password2'])) {
            $is_exist = false;
        }
    }
    $orders_list = array();
    if (!empty(Tygh::$app['session']['cart']['processed_order_id'])) {
        $order_id = array_merge($orders_list, (array)Tygh::$app['session']['cart']['processed_order_id']);
        fn_epayco_order_total_is_correct($order_id[0]);
    }

}

/**
 * Provide token and handle errors for checkout with In-Context checkout
 *
 * @param array $cart   Cart data
 * @param array $auth   Authentication data
 * @param array $params Request parameters
 */
function fn_epayco_checkout_place_orders_pre_route(&$cart, $auth, $params)
{
    $cart = empty($cart) ? array() : $cart;
    $payment_id = (empty($params['payment_id']) ? $cart['payment_id'] : $params['payment_id']);
    $processor_data = fn_get_processor_data($payment_id);

    if (!empty($processor_data['processor_script']) && $processor_data['processor_script'] == 'epayco.php' &&
        isset($params['in_context_order']) && $processor_data['processor_params']['in_context'] == 'Y'
    ) {
        // parent order has the smallest identifier of all the processed orders
        $order_id = min($cart['processed_order_id']);
        Tygh::$app['ajax']->assign('token', $order_id);
        exit;
    }
}
function fn_epayco_request($order_ids = array(), $processor_data = array(), $params = array())
{
    $order_ids = array_diff($order_ids, array(''));

    $post_data = fn_epayco_build_post($order_ids, $processor_data);

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
    foreach ($order_info['products'] as $k => $v) {
        $i++;

        $p_description = str_replace('_', ' ', string_sanitize($v['product']));
        $descripcionParts[] = $p_description;
    }
    $p_description = implode(' - ', $descripcionParts);
    $p_url_response = fn_url("payment_notification.response?payment=epayco&order_id=$order_id", AREA, 'current');
    $p_url_confirmation = fn_url("payment_notification.confirmation?payment=epayco&order_id=$order_id", AREA, 'current');

    if (YesNo::toBool($processor_data['processor_params']['p_test_request'])){
        $test_method = 'true';
    }else{
        $test_method = 'false';
    }

    return [
        'key' => $processor_data['processor_params']['p_public_key'],
        'test' =>$test_method,
        'description' => $p_description,
        'order_id' => $order_id,
        'currency' => $order_info['secondary_currency'],
        'total' => $order_info['total'],
        'tax' => $p_tax,
        'sub_total' => $p_amount_base,
        'p_url_response' => $p_url_response,
        'p_url_confirmation' => $p_url_confirmation,
        'p_cust_id_cliente'=>$processor_data['processor_params']['p_cust_id_cliente'],
        'receivers'=>$post_data
    ];
}

function fn_epayco_build_post($order_ids, $processor_data)
{

    $ip = fn_get_ip();
    $epayco_currency = $processor_data['processor_params']['currency'];

    if ($processor_data['processor_params']['user_currency'] == 'Y') {
        $epayco_currency = CART_SECONDARY_CURRENCY;
    }

    $collect_payouts = Registry::get('addons.epayco.collect_payouts') == 'Y';

    if (!is_array($order_ids)) {
        $order_ids = array($order_ids);
    }
    $old_orders = $order_ids;
    $parent_order_id = fn_epayco_get_parent_order_id($order_ids);
    $str_order_ids = implode(',', $order_ids);
    if(is_countable($parent_order_id) && count($parent_order_id)>0 ){
        $order_ids = db_get_fields("SELECT order_id FROM ?:orders WHERE parent_order_id = ?i", $parent_order_id);
    }else{
        if(isset($parent_order_id)){
            $order_ids = db_get_fields("SELECT order_id FROM ?:orders WHERE parent_order_id = ?i", $parent_order_id);
        }
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
        $base_iva = round($order_info['total'],2)-round($p_tax,2);
        $vendor_fee = fn_epayco_calc_admin_fee($order_data,$base_iva);
       if(is_null($secondary_email)){
              $post_data_fee=0; 
              $post_data_id=$processor_data['processor_params']['p_cust_id_cliente'];
        }else{
            $post_data_fee=number_format(fn_format_price_by_currency($vendor_fee, CART_PRIMARY_CURRENCY, $epayco_currency),2);
            $post_data_id = $secondary_email;
        }
        if ($vendor_fee) {
            $post_data["id"] = $post_data_id;
            $post_data["total"] =round($order_info['total'],2);
            $post_data["iva"] = round($p_tax,2);
            $post_data["base_iva"] = round($base_iva,2);
             
            $post_data["fee"] = $post_data_fee;
            array_push($receiversData, $post_data);
        }
    }
    return $receiversData;
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


function fn_epayco_calc_admin_fee($order_data,$base_iva)
{
    if (fn_epayco_get_company_base_for_commission($order_data['company_id']) == 'O') {
        $total_to_fee = $order_data['total'];
    } else {
        $total_to_fee = $order_data['subtotal'];
    }

    $commission = db_get_row(
        "SELECT c.commission_amount, c.commission, c.commission_type, p.fixed_commission"
        . " FROM ?:vendor_payouts as c"
        . " LEFT JOIN ?:vendor_plans as p ON c.plan_id = p.plan_id"
        . ' WHERE company_id = ?i'
        . ' AND order_id = ?i', $order_data['company_id'], $order_data['order_id']
    );
    $admin_fee = isset($commission['commission_amount']) ? $commission['commission_amount'] : 0;
    if( isset($commission['commission']) && floatval($commission['commission'])>0.0) {
        $admin_fee = (($base_iva*$commission['commission'])/100)+floatval($commission['fixed_commission']);
    }else{
        $admin_fee = $order_data['total'] - !empty($admin_fee) ? $admin_fee : 0;
    }
    // hold back vendor payouts
    if (Registry::get('addons.epayco.collect_payouts') == 'Y') {
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

    return $admin_fee;
}

function fn_epayco_get_company_base_for_commission($company_id)
{
    $base_for_commission = db_get_field("SELECT epayco_base_for_commission FROM ?:companies WHERE company_id = ?i", $company_id);

    if (empty($base_for_commission)) {
        $base_for_commission = Registry::get('addons.epayco.count_vendor_commission_on_basis');
    }

    return $base_for_commission;
}


function string_sanitize($string, $force_lowercase = true, $anal = false) {

    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]","}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;","â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "_", $clean);
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
    return $clean;
}
