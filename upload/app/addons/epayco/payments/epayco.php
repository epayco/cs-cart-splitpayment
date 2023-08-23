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
use Tygh\Enum\OrderStatuses;
use Tygh\Enum\YesNo;
use Tygh\Http;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (defined('PAYMENT_NOTIFICATION')) {

    $confirmation = false;
    if($mode == 'confirmation'){
        $validationData = $_REQUEST;
        $confirmation = true;
    }
    if($mode == 'response'){
        $ref_payco = $_GET['ref_payco'];
        $url = 'https://secure.epayco.io/validation/v1/reference/'.$ref_payco;
        $responseData =  @file_get_contents($url);
        $jsonData = @json_decode($responseData, true);
        $validationData = $jsonData['data'];
    }

    if(!empty($validationData)){
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
    }else{
        $order_id_ = null;
    }


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
        $isTestPluginMode = YesNo::toBool($processor_data['processor_params']['p_test_request'])  ? "yes" : "no";

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
                    $pp_response['order_status'] = OrderStatuses::COMPLETE;
                } break;
                case 2: {
                    $pp_response['order_status'] = OrderStatuses::DECLINED;
                } break;
                case 3: {
                    $pp_response['order_status'] = 'Y';
                } break;
                case 6:
                case 10:
                case 11:
                case 4: {
                    $pp_response['order_status'] = OrderStatuses::CANCELED;
                } break;
                default: {
                    $pp_response['order_status'] = OrderStatuses::OPEN;
                } break;
            }

            $pp_response['reason_text'] = $_REQUEST['x_response_reason_text'];
            $pp_response['transaction_id'] = $_REQUEST['x_transaction_id'];
            if (fn_check_payment_script('epayco.php', $order_id)) {
                fn_update_order_payment_info($order_id, $pp_response);
                fn_change_order_status($order_id, $pp_response['order_status'], '', false);
            }
        }else{
            $pp_response['order_status'] = OrderStatuses::FAILED;
	        $pp_response['reason_text'] = __('text_transaction_declined');
	        if (fn_check_payment_script('epayco.php', $order_id)) {
                fn_update_order_payment_info($order_id, $pp_response);
                fn_change_order_status($order_id, $pp_response['order_status'], '', false);
            }
        }

    }else{
	    $pp_response['order_status'] = OrderStatuses::FAILED;
	    $pp_response['reason_text'] = __('text_transaction_declined');
	    if (fn_check_payment_script('epayco.php', $order_id)) {
            fn_update_order_payment_info($order_id, $pp_response);
            fn_change_order_status($order_id, $pp_response['order_status'], '', false);
        }
    }

    fn_finish_payment($order_id, $pp_response);
    if($confirmation){
        echo "code response: ".$x_cod_transaction_state;
    }else{
        fn_order_placement_routines('route', $order_id);
    }
    exit;
} else {

    /** @var \Tygh\Location\Manager $location_manager */
    $location_manager = Tygh::$app['location'];

    $type_checkout_mode = "true";
    

    $data = fn_epayco_request(array($order_id), $processor_data);
    $data['billAddress'] = $location_manager->getLocationField($order_info, 'address', '', BILLING_ADDRESS_PREFIX);
    $data['payerPhone'] = $location_manager->getLocationField($order_info, 'phone', '', BILLING_ADDRESS_PREFIX);

    $epaycoButtonImage ="https://multimedia.epayco.co/epayco-landing/btns/Boton-epayco-color1.png";

    echo sprintf('
        <style>
            .loader-container{
                position: relative;
                padding: 20px;
                color: #ff5700;
            }
            .epayco-subtitle{
                font-size: 14px;
            }
            .epayco-button-render{
                transition: all 500ms cubic-bezier(0.000, 0.445, 0.150, 1.025);
                transform: scale(1.1);
                box-shadow: 0 0 4px rgba(0,0,0,0);
            }
            .epayco-button-render:hover {
                transform: scale(1.2);
            }           
        </style>
            <div class="loader-container">
                <div class="loading"></div>
            </div>
            <p style="text-align: center;" class="epayco-title">
                <span class="animated-points">Loading payment method ...</span>
            </p> 
            <center>
                <form id="appGateway">
                <script src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js"></script>
                    <script>
                        var handler = ePayco.checkout.configure({
                            key: "%s",
                            test: "%s"
                        });
                        var data = {
                                name: "%s",
                                description: "%s",
                                invoice: "%s",
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
                                address_billing: "%s",
                                mobilephone_billing: "%s",
                            }
                        var js_array ='.json_encode($data['receivers']).';
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
                        data.splitpayment= "true", // Indicación de funcionalidad split
                        data.split_rule= "multiple", // Parámetro para configuración de Split_receivers - debe de ir por defecto en multiple
                        data.split_receivers= split_receivers
                        
                    </script>
                    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
                    <script>
                        handler.open(data)
                        window.onload = function() {
                            document.addEventListener("contextmenu", function(e){
                                e.preventDefault();
                            }, false);
                        } 
                        $(document).keydown(function (event) {
                            if (event.keyCode == 123) {
                                return false;
                            } else if (event.ctrlKey && event.shiftKey && event.keyCode == 73) {        
                                return false;
                            }
                        });
                    </script>
                </form>
                
            </center>
            ',$data['key']
            ,$data['test'], $data['description'],$data['description'],$data['order_id'],$data['currency'],$data['total'], $data["tax"],
        $data["sub_total"], $order_info["b_country"], $type_checkout_mode, $data['p_url_response'], $data['p_url_confirmation'],"es",$data['order_id'],
        $order_info['email'], $data['billAddress'], $data['payerPhone'],$data['p_cust_id_cliente'],$data['p_cust_id_cliente'],$data['p_cust_id_cliente']
    );
    exit;
}
exit;
