<div class="ty-checkout-summary" id="checkout_info_summary_{$block.snapping_id}">
    <table class="ty-checkout-summary__block">
        <tbody>
            <tr>
                <td class="ty-checkout-summary__item">{$order_info.amount} {__("items")}</td>
                <td class="ty-checkout-summary__item ty-right" data-ct-checkout-summary="items">
                    <span>{include file="common/price.tpl" value=$order_info.display_subtotal}</span>
                </td>
            </tr>

            <tr>
                <td class="ty-checkout-summary__item">{__("shipping")}</td>
                <td class="ty-checkout-summary__item ty-right" data-ct-checkout-summary="shipping">
                    {if !$order_info.display_shipping_cost|floatval}
                        <span>{__("free_shipping")}</span>
                    {else}
                        <span>{include file="common/price.tpl" value=$order_info.display_shipping_cost}</span>
                    {/if}
                </td>
            </tr>

            {if ($order_info.subtotal_discount|floatval)}
                <tr>
                    <td class="ty-checkout-summary__item">{__("order_discount")}</td>
                    <td class="ty-checkout-summary__item ty-right discount-price" data-ct-checkout-summary="order-discount">
                        <span>{include file="common/price.tpl" value="-`$order_info.subtotal_discount`"}</span>
                    </td>
                </tr>
            {/if}

            {if $order_info.payment_surcharge|floatval && !$take_surcharge_from_vendor}
                <tr>
                    <td class="ty-checkout-summary__item">{$order_info.payment_surcharge_title|default:__("payment_surcharge")}</td>
                    <td class="ty-checkout-summary__item ty-right" data-ct-checkout-summary="payment-surcharge">
                        <span>{include file="common/price.tpl" value=$order_info.payment_surcharge}</span>
                    </td>
                </tr>
                {math equation="x+y" x=$order_info.total y=$order_info.payment_surcharge assign="_total"}
            {/if}

            {if $order_info.taxes}
                <tr>
                    <td class="ty-checkout-summary__item ty-checkout-summary__taxes">{__("taxes")}</td>
                    <td class="ty-checkout-summary__item">&nbsp;</td>
                </tr>
                {foreach from=$order_info.taxes item="tax"}
                    <tr>
                        <td class="ty-checkout-summary__item" data-ct-checkout-summary="tax-name {$tax.description}">
                            <div class="ty-checkout-summary__item">{$tax.description} ({include file="common/modifier.tpl" mod_value=$tax.rate_value mod_type=$tax.rate_type}{if $tax.price_includes_tax == "Y" && ($settings.Appearance.cart_prices_w_taxes != "Y" || $settings.Checkout.tax_calculation == "subtotal")} {__("included")}{/if})</div>
                        </td>
                        <td class="ty-checkout-summary__item ty-right" data-ct-checkout-summary="taxes">
                            <span class="ty-checkout-summary__taxes-amount">{include file="common/price.tpl" value=$tax.tax_subtotal}</span>
                        </td>
                    </tr>
                {/foreach}
            {/if}

            <tr>
                <td colspan="2" class="ty-checkout-summary__item">
                    {include file="views/checkout/components/promotion_coupon.tpl"}
                </td>
            </tr>
        </tbody>
        <tbody>
            <tr>
                <th class="ty-checkout-summary__total" colspan="2" data-ct-checkout-summary="order-total">
                    <div>
                        {__("order_total")}
                        <span class="ty-checkout-summary__total-sum">{include file="common/price.tpl" value=$order_info.total}</span>
                    </div>
                </th>
            </tr>
        </tbody>
    </table>
<!--order_info_summary_{$block.snapping_id}--></div>