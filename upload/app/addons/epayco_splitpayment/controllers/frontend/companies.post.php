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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    return;
}

if ($mode == 'catalog') {
    $params = $_REQUEST;
    $params['status'] = 'A';
    $params['get_description'] = 'Y';

    $companies = Registry::get('view')->getTemplateVars('companies');

    foreach ($companies as $company_id => $company) {
        $show_vendor_verification = Registry::get('addons.epayco_splitpayment.display_verification_status_on_storefront');
        if ($show_vendor_verification == 'Y' && $company['epayco_verification'] == 'verified') {
            $companies[$company_id]['epayco_verification_status']['main_pair'] = fn_get_image_pairs(0, 'epayco_ver_image', 'M', false, true, DESCR_SL);
            if (!empty($companies[$company_id]['epayco_verification_status']['main_pair'])) {
                $width = Registry::get('addons.epayco_splitpayment.epayco_verified_image_width');
                $height = Registry::get('addons.epayco_splitpayment.epayco_verified_image_height');
                $companies[$company_id]['epayco_verification_status']['width'] = $width ?: 60;
                $companies[$company_id]['epayco_verification_status']['height'] = $height ?: 60;
            } else {
                $companies[$company_id]['epayco_verification_status']['verified'] = 'verified';
            }
        }
    }
    Tygh::$app['view']->assign('companies', $companies);
}