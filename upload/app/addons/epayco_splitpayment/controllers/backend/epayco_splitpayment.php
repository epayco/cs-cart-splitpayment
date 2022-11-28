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

if ( !defined('AREA') ) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    return;
}

if ($mode == 'queue') {
    if (empty($_REQUEST['order_id'])) { 
        return array(CONTROLLER_STATUS_REDIRECT, 'orders.manage');
    }

    $parent_order_id = $_REQUEST['order_id'];

    $queue_orders = fn_epayco_get_queue($parent_order_id);

    if (is_array($queue_orders)) {

        $orders_data = fn_epayco_get_orders_data($queue_orders);
        $parent_order_data = fn_get_order_info($parent_order_id);

        $parent_order_data['user_data'] = fn_get_user_info($parent_order_data['user_id']);

        $parent_order_data['amount'] = 0;
        foreach ($parent_order_data['products'] as $product) {
            $parent_order_data['amount'] += $product['amount'];
        }

        fn_epayco_splitpayment_set_summa_data($queue_orders, $orders_data);

        $exist_paid = false;
        $pay_step = 0;
        foreach ($queue_orders as $queue_key => $queue) {
            if ($queue['status'] == QUEUE_PAYMENT_ORDERS) {
                $order_ids = implode(',', $queue['order_ids']);
                $pay_step = $queue_key + 1;
                break;

            } elseif ($queue['status'] == PAID_PAYMENT_ORDERS) {
                $queue_orders[$queue_key]['paid'] = true;
                $exist_paid = true;
            }
        }

        if (empty($queue_orders) || empty($order_ids)) {
            return array(CONTROLLER_STATUS_REDIRECT, 'orders.manage');
        }

        Tygh::$app['view']->assign('hide_cart', true);

        Tygh::$app['view']->assign('exist_paid', $exist_paid);

        Tygh::$app['view']->assign('order_info', $parent_order_data);
        Tygh::$app['view']->assign('queue_orders', $queue_orders);
        Tygh::$app['view']->assign('orders_data', $orders_data);
        Tygh::$app['view']->assign('pay_step', $pay_step);

        Tygh::$app['view']->assign('script_proceed', fn_url("payment_notification.pay?payment=epayco_splitpayment&order_ids=$order_ids"));
        Tygh::$app['view']->assign('script_cancel', fn_url("payment_notification.cancel?payment=epayco_splitpayment&order_ids={$parent_order_id}"));

    } else {
        return array(CONTROLLER_STATUS_REDIRECT, 'orders.manage');
    }
}