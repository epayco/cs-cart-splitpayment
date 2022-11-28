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

use Tygh\Http;
use Tygh\Registry;
use Tygh\Embedded;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_epayco_splitpayment_next($order_ids)
{
    $parent_order_id = fn_epayco_get_parent_order_id($order_ids);

    if (fn_epayco_splitpayment_check_finish($parent_order_id)) {

        $route = 'route';
        if (count($order_ids) == 1) {
            $order_info = fn_get_order_info(reset($order_ids));
            $route = $order_info['repaid'] ? 'repay' : 'route';
        }

        fn_epayco_clear_queue($parent_order_id);
        fn_order_placement_routines($route, $parent_order_id, false);

    } else {
        $action = "epayco_splitpayment.queue?order_id=$parent_order_id";
        fn_redirect(fn_url($action));
    }
}

function fn_epayco_splitpayment_start_payments($order_ids)
{
    $idata = array();
    foreach($order_ids as $order_id) {
        $idata[] = array(
            'order_id' => $order_id,
            'type' => 'S',
            'data' => TIME,
        );
    }

    db_query("REPLACE INTO ?:order_data ?m", $idata);
}

function fn_epayco_splitpayment_check_payment_complete($payment_details, $order_index)
{
    return isset($payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionStatus"]) && $payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionStatus"] == 'COMPLETED';
}

function fn_epayco_splitpayment_check_payment_pending($payment_details, $order_index)
{
    return isset($payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionStatus"]) && $payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionStatus"] == 'PENDING';
}
