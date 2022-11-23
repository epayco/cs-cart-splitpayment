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

fn_define('MAX_AMOUNT_VENDORS_IN_ORDER', 5); // You can have at most 1-5 secondary receivers.
fn_define('QUEUE_PAYMENT_ORDERS', 'M');
fn_define('SKIP_PAYMENT_ORDERS', 'S');
fn_define('PAID_PAYMENT_ORDERS', 'P');
fn_define('RESPONSE_SUCCESS', 'Success');
fn_define('EPAYCO_SPLITPAYMENT_PROCESSOR', 'Epayco Splitpayment');

