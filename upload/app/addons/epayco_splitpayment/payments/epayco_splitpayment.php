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
use Tygh\Registry;
use Tygh\VendorPayouts;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

include_once (Registry::get('config.dir.addons') . 'epayco_splitpayment/payments/epayco_splitpayment_func.php');

if (defined('PAYMENT_NOTIFICATION')) {

    if (empty($_REQUEST['order_ids'])) { // internal error
        $action = (AREA == 'A') ? 'orders.manage' : '';
        $ref_payco = $_GET['ref_payco'];
        if($_REQUEST['x_ref_payco']){
            $validationData = $_REQUEST;
            $confirmation = true;
        }
        if(!empty($ref_payco)){
            $url = 'https://secure.epayco.co/validation/v1/reference/'.$ref_payco;
            $responseData =  @file_get_contents($url);
            $jsonData = @json_decode($responseData, true);
            $validationData = $jsonData['data'];
        }
        if(empty($validationData)){
            fn_redirect(fn_url($action));
        }else{
            $x_signature = trim($validationData['x_signature']);
            $x_cod_transaction_state = (int)trim($validationData['x_cod_transaction_state']);
            $x_ref_payco = trim($validationData['x_ref_payco']);
            $x_transaction_id = trim($validationData['x_transaction_id']);
            $x_amount = trim($validationData['x_amount']);
            $x_currency_code = trim($validationData['x_currency_code']);
            $x_test_request = trim($validationData['x_test_request']);
            $x_approval_code = trim($validationData['x_extra1']);
            $x_franchise = trim($validationData['x_franchise']);
            $order_id_ = trim($validationData['x_extra1']);
            // states success
            $statusSuccess = array(1, 3);
            $order_id = !empty($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : (int)$order_id_;
            // Get the processor data
            $payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $order_id);
            $processor_data = fn_get_payment_method_data($payment_id);

            $order_info = fn_get_order_info($order_id);
            $pp_response = array();

            if(!empty($order_id) && !empty($validationData)){
                $signature = hash('sha256',
                    trim($processor_data['processor_params']['p_cust_id_cliente']).'^'
                    .trim($processor_data['processor_params']['p_key']).'^'
                    .$x_ref_payco.'^'
                    .$x_transaction_id.'^'
                    .$x_amount.'^'
                    .$x_currency_code
                );
                $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
                $isTestMode = $isTestTransaction == "yes" ? "true" : "false";
                $isTestPluginMode = $processor_data['processor_params']['p_test_request']  == 'TRUE' ? "yes" : "no";

                if(floatval($order_info['total']) == floatval($x_amount)){
                    if("yes" == $isTestPluginMode){
                        $validation = true;
                    }
                    if("no" == $isTestPluginMode ){
                        if($x_approval_code != "000000" && $x_cod_transaction_state == 1){
                            $validation = true;
                        }else{
                            if($x_cod_transaction_state != 1){
                                $validation = true;
                            }else{
                                $validation = false;
                            }
                        }

                    }
                }else{
                    $validation = false;
                }

                if($signature == $x_signature && $validation){
                    switch ($x_cod_transaction_state) {
                        case 1: {
                            $pp_response['order_status'] = 'C';
                        } break;
                        case 2: {
                            $pp_response['order_status'] = 'D';
                        } break;
                        case 3: {
                            $pp_response['order_status'] = 'Y';
                        } break;
                        case 4: {
                            $pp_response['order_status'] = 'I';
                        } break;
                        case 6: {
                            $pp_response['order_status'] = 'I';
                        } break;
                        case 10:{
                            $pp_response['order_status'] = 'I';
                        } break;
                        case 11:{
                            $pp_response['order_status'] = 'I';
                        } break;
                        default: {
                            $pp_response['order_status'] = 'O';
                        } break;
                    }

                    $pp_response['reason_text'] = $_REQUEST['x_response_reason_text'];
                    $pp_response['transaction_id'] = $_REQUEST['x_transaction_id'];
                    if (fn_check_payment_script('epayco_splitpayment.php', $order_id)) {
                        fn_update_order_payment_info($order_id, $pp_response);
                        fn_change_order_status($order_id, $pp_response['order_status'], '', false);
                    }
                }else{
                    $pp_response['order_status'] = 'F';
                    $pp_response['reason_text'] = __('text_transaction_declined');
                    if (fn_check_payment_script('epayco_splitpayment.php', $order_id)) {
                        fn_update_order_payment_info($order_id, $pp_response);
                        fn_change_order_status($order_id, $pp_response['order_status'], '', false);
                    }
                }

            }else{
                $pp_response['order_status'] = 'F';
                $pp_response['reason_text'] = __('text_transaction_declined');
                if (fn_check_payment_script('epayco_splitpayment.php', $order_id)) {
                    fn_update_order_payment_info($order_id, $pp_response);
                    fn_change_order_status($order_id, $pp_response['order_status'], '', false);
                }
            }
            fn_finish_payment($order_id, $pp_response);
            if($confirmation){
                echo "code response: ".$x_cod_transaction_state;
            }else{
                $redirect_url = 'checkout.complete?order_id=' . $order_id . '&ref_payco='.$x_ref_payco;
                fn_redirect($redirect_url,false);
                //fn_order_placement_routines('route', $order_id);
            }

            exit;
        }

    }

    $order_ids = explode(',', $_REQUEST['order_ids']);
    $parent_order_id = fn_epayco_get_parent_order_id($order_ids);
    $payment_id = !empty($_REQUEST['payment_id']) ? $_REQUEST['payment_id'] : '';
    $processor_data = fn_epayco_splitpayment_get_processor_data($payment_id);

    if ($mode == 'return') {

        $pay_key = $_REQUEST['payKey'];

        if (empty($pay_key)) {
            $action = (AREA == 'A') ? 'orders.manage' : '';
            fn_redirect(fn_url($action));
        }

        $payment_details = fn_epayco_splitpayment_payment_details($pay_key, $processor_data);
        $queue_orders = fn_epayco_get_queue($parent_order_id);

        if (!empty($queue_orders) && !fn_epayco_splitpayment_any_paid($queue_orders)) {
            $pp_response['order_status'] = $processor_data['processor_params']['statuses']['pending_payment'];
            $pp_response['reason_text'] = '';

            fn_finish_payment($parent_order_id, $pp_response);
            fn_clear_cart(Tygh::$app['session']['cart']);
        }

        if (isset($processor_data['processor_params']['in_context'])
            && $processor_data['processor_params']['in_context'] == 'Y'
            && AREA == 'C'
        ) {
            fn_epayco_splitpayment_start_payments($order_ids);
        }

        foreach($order_ids as $order_index => $order_id) {

            $order_info = fn_get_order_info($order_id);

            // check against transaction_id to prevent double processing
            if (!empty($order_info['payment_info']['transaction_id'])) {
                continue;
            }

            // the last item in the paymentInfoList contains the admin fee
            $create_withdrawal =
                isset($payment_details["paymentInfoList_paymentInfo({$order_index})_receiver_email"])
                && $payment_details["paymentInfoList_paymentInfo({$order_index})_receiver_email"] != $processor_data['processor_params']['primary_email'];

            if (fn_epayco_splitpayment_check_payment_complete($payment_details, $order_index)) {
                $pp_response['order_status'] = 'P';
                $pp_response['reason_text'] = 'Success';

                $create_withdrawal = $create_withdrawal && true;

            } elseif (fn_epayco_splitpayment_check_payment_pending($payment_details, $order_index)) {
                $pp_response['order_status'] = 'O';
                $pp_response['reason_text'] = 'Status transaction: PENDING';

                if (isset($payment_details["paymentInfoList_paymentInfo($order_index)_pendingReason"])) {
                    $pp_response['reason_text'] .= '; Reason: ' . $payment_details["paymentInfoList_paymentInfo($order_index)_pendingReason"];
                }

                $create_withdrawal = $create_withdrawal && true;

            } else {
                $pp_response['order_status'] = 'F';
                $pp_response['reason_text'] = !empty($payment_details['error(0)_message']) ? $payment_details['error(0)_message'] : 'Failed';
            }

            $pp_response['transaction_id'] = isset($payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionId"]) ? $payment_details["paymentInfoList_paymentInfo($order_index)_senderTransactionId"] : '';

            if ($create_withdrawal) {
                $payouts_manager = VendorPayouts::instance(array('vendor' => $order_info['company_id']));

                $payouts_manager->update(array(
                    'company_id' => $order_info['company_id'],
                    'payout_type' => VendorPayoutTypes::WITHDRAWAL,
                    'approval_status' => VendorPayoutApprovalStatuses::COMPLETED,
                    'payout_amount' => $payment_details["paymentInfoList_paymentInfo($order_index)_receiver_amount"],
                    'comments' => __('addons.epayco_splitpayment.withdrawal_for_the_order', array(
                        '[order_id]' => $order_id,
                    )),
                ));

                if (Registry::get('addons.epayco_splitpayment.collect_payouts') == 'Y') {
                    $pending_payouts = $payouts_manager->getSimple(array(
                        'payout_type' => VendorPayoutTypes::PAYOUT,
                        'approval_status' => VendorPayoutApprovalStatuses::PENDING
                    ));

                    foreach ($pending_payouts as $payout_data) {
                        $payouts_manager->update(array(
                            'approval_status' => VendorPayoutApprovalStatuses::COMPLETED
                        ), $payout_data['payout_id']);
                    }
                }
            }

            fn_finish_payment($order_id, $pp_response);
            fn_epayco_splitpayment_set_status($order_id, PAID_PAYMENT_ORDERS);
        }

        fn_epayco_splitpayment_next($order_ids);
        
    } elseif ($mode == 'cancel') { // Completely cancels operation
        fn_epayco_clear_queue($parent_order_id);

        $pp_response['order_status'] = 'N';
        $pp_response['reason_text'] = __('text_transaction_cancelled');
        fn_finish_payment($parent_order_id, $pp_response);

        fn_order_placement_routines('route', $parent_order_id);

    } elseif ($mode == 'skip') {

        $pp_response['order_status'] = 'F';
        $pp_response['reason_text'] = __('text_transaction_cancelled');

        foreach($order_ids as $order_id) {
            fn_finish_payment($order_id, $pp_response);
        }

        fn_epayco_splitpayment_next($order_ids);

    } elseif ($mode == 'pay') {
        fn_epayco_splitpayment_start_payments($order_ids);
        fn_epayco_splitpayment_request($order_ids, $processor_data);
    }

} else { // First running the payment script
    $order_ids = explode(',', $_REQUEST['order_ids']);
    // store additional order data
    if (!empty($order_id)) {
        db_query('REPLACE INTO ?:order_data ?m', array(
            // add payment data
            array('order_id' => $order_id,  'type' => 'S', 'data' => TIME),
            // store order nonce
            array('order_id' => $order_id,  'type' => 'E', 'data' => $order_id)
        ));
    }

    // process payment notification
    $pp_response = array(
        'order_status' => 'Y',
        'transaction_id' => $order_id
    );
    fn_finish_payment($order_id, $pp_response);
    //$params['total_items'] = db_get_field("SELECT COUNT(a.item_id) FROM ?:access_restriction as a WHERE a.type IN (?a)", $types[$params['selected_section']]);
    fn_epayco_splitpayment_request(array($order_id), $processor_data);
}