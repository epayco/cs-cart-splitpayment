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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    /*$order_id = $_REQUEST['order_id'];
    $session_id = Tygh::$app['session']->getID();
    Tygh::$app['session']->resetID($session_id);
    $cart = & Tygh::$app['session']['cart'];
    $auth = & Tygh::$app['session']['auth'];
    list($order_id, $process_payment) = fn_place_order($cart, $auth);
    $payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $order_id);
    $processor_data = fn_get_payment_method_data($payment_id);
    $order_info = fn_get_order_info($order_id);*/
    return;
}

if ($mode == 'checkout') {

	if (isset($_COOKIE['last_adaptive_paykey'])) {

	    Tygh::$app['view']->assign('ap_paykey', $_COOKIE['last_adaptive_paykey']);
	    fn_set_cookie('last_adaptive_paykey', '', -1);
	    unset($_COOKIE['last_adaptive_paykey']);

	}

}

if ($mode == 'complete') {
    $order_id =  $_REQUEST['order_id'];
    $payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $order_id);
    $processor_data = fn_get_payment_method_data($payment_id);
    fn_epayco_splitpayment_request(array($order_id), $processor_data);
}