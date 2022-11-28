{capture name="cartbox"}

<div id="cart_items">
    <table class="ty-cart-content ty-table" style="margin-top: 0;">
        <thead>
            <tr>
                <th class="ty-cart-content__title ty-left">{__("product")}</th>
                <th class="ty-cart-content__title ty-left">&nbsp;</th>
            </tr>
        </thead>

        <tbody>
            {foreach from=$queue.order_ids item=order_id}
                {assign var="_cart_products" value=$orders_data[$order_id].product_groups[0].products}
                {foreach from=$_cart_products key="key" item="p" name="cart_products"}
                    {if !$p.extra.parent}
                        <tr>
                            <td style="width: 10%" class="ty-cart-content__product-elem ty-cart-content__image-block">
                                <div class="ty-cart-content__image">
                                    {include file="common/image.tpl" image_width="40" image_height="40" images=$p.main_pair no_ids=true}
                                </div>
                            </td>
                            <td style="width: 89%"><a href="{"products.view?product_id=`$p.product_id`"|fn_url}">{$p.product_id|fn_get_product_name nofilter}</a>
                                <p>
                                    <span>{$p.amount}</span><span dir="{$language_direction}">&nbsp;x&nbsp;</span>{include file="common/price.tpl" value=$p.display_price span_id="price_`$key`_`$dropdown_id`" class="none"}
                                </p>
                            </td>

                        </tr>
                    {/if}
                {/foreach}
            {/foreach}
        </tbody>
    </table>
</div>

{/capture}
{include file="common/mainbox_cart.tpl" title=__("cart_items") content=$smarty.capture.cartbox}
