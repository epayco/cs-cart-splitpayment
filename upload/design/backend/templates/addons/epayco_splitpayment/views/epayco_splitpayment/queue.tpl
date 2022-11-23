{capture name="mainbox"}
    {capture name="tabsbox"}
        <div id="content_general">
            <div class="row-fluid">
                <div class="span8">
                    {foreach from=$queue_orders item=queue key=index}
                    {assign var="pay" value=($pay_step==$index+1)}
                        <form name="payments_form_{$index}" action="{""|fn_url}" method="post" class="payments-form">

                            <input type="hidden" id="order_ids" name="order_ids" value="{','|implode:$queue.order_ids}">

                            <div>
                                <h2 class="ty-step__title{if $pay}-active{/if}{if $complete && !$pay}-complete{/if} clearfix">
                                    <span class="float-left">{$index+1}</span>
                                    {if $queue.paid}
                                        <span class="ty-step__title-txt">{__("epayco_splitpayment_paid")} {include file="common/price.tpl" value=$_total|default:$queue.total}</span>
                                    {else}
                                        <span class="ty-step__title-txt">{__("epayco_splitpayment_pay")} {include file="common/price.tpl" value=$_total|default:$queue.total}</span>
                                    {/if}
                                    {if $queue.paid}<span class="float-left">{include_ext file="common/icon.tpl" class="icon-ok"}</span>{/if}
                                </h2>
                                <div id="step_{$index+1}_body" class="{*if !$pay}hidden{/if*} clearfix">
                                    <table width="100%" class="table table-middle table--relative">
                                        <thead>
                                        <tr>
                                            <th width="50%">{__("product")}</th>
                                            <th width="10%">{__("price")}</th>
                                            <th width="10%" class="right">&nbsp;{__("quantity")}</th>
                                        </tr>
                                        </thead>
                                        {foreach from=$queue.order_ids item=order_id}
                                            {assign var="_cart_products" value=$orders_data[$order_id].product_groups[0].products}
                                            {foreach from=$_cart_products key="key" item="p" name="cart_products"}
                                                {if !$p.extra.parent}
                                                    <div id="cart_items">
                                                        <tr>
                                                            <td>
                                                                <div class="order-product-image">
                                                                    {include file="common/image.tpl" image=$p.main_pair image_id=$p.main_pair.image_id image_width=50 no_ids=true}
                                                                </div>
                                                                <div class="order-product-info">
                                                                    {if !$p.deleted_product}
                                                                        <a>
                                                                    {/if}
                                                                    {$p.product nofilter}
                                                                    {if !$p.deleted_product}
                                                                        </a>
                                                                    {/if}
                                                                    <div class="products-hint">
                                                                        {if $p.product_code}<p>{__("sku")}
                                                                            :{$p.product_code}</p>
                                                                        {/if}
                                                                    </div>
                                                                </div>
                                                                <td class="nowrap">
                                                                    {if $p.extra.exclude_from_calculate}{__("free")}{else}{include file="common/price.tpl" value=$p.price}{/if}
                                                                </td>
                                                                <td class="center">
                                                                    &nbsp;{$p.amount}<br/>
                                                                </td>
                                                            </td>
                                                        </tr>
                                                    </div>
                                                {/if}
                                            {/foreach}
                                        {/foreach}
                                    </table>
                                    <div class="ty-checkout-buttons {if !$pay}hidden{/if}">
                                        {include file="buttons/button.tpl" but_href=$script_proceed but_text=__("epayco_splitpayment_pay") but_meta="btn btn-primary" but_role="action"}
                                        {if !$exist_paid}
                                            &nbsp;{include file="buttons/button.tpl" but_href=$script_cancel but_text=__("epayco_splitpayment_cancel") but_meta="btn" but_role="action"}
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </form>
                    {/foreach}
                </div>
                <div class="span4">
                    <div class="well orders-right-pane form-horizontal">
                        {* Shipping info *}
                        {if $order_info.shipping}
                            <div class="control-group shift-top">
                                <div class="control-label">
                                    {include file="common/subheader.tpl" title=__("shipping_information")}
                                </div>
                            </div>
                            {foreach from=$order_info.shipping item="shipping" key="shipping_id" name="f_shipp"}
                                <div class="control-group">
                                    <span> {$shipping.group_name|default:__("none")}</span>
                                </div>
                                <div class="control-group">
                                    <div class="control-label">{__("method")}</div>
                                    <div id="tygh_shipping_info" class="controls">
                                        {$shipping.shipping}
                                    </div>
                                </div>
                            {/foreach}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
        {capture name="mainbox_title"}<span class="ty-checkout__title">{__("epayco_splitpayment_progress_payment_order")}
            &nbsp;
            {include_ext file="common/icon.tpl"
                class="ty-icon-lock ty-checkout__title-icon"
            }
            </span>
        {/capture}
    {/capture}
    {include file="common/tabsbox.tpl" content=$smarty.capture.tabsbox track=true}
{/capture}

{capture name="sidebar"}
    {* Customer info *}
    {include file="views/order_management/components/profiles_info.tpl" user_data=$order_info location="I"}
{/capture}

{assign var="title" value=__("place_order")}

{include file="common/mainbox.tpl" title=$title content=$smarty.capture.mainbox sidebar=$smarty.capture.sidebar sidebar_position="left"}
