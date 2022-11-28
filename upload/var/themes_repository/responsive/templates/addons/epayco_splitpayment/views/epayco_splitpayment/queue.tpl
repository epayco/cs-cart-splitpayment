-{hook name="epayco_splitpayment:queue"}
{/hook}

{if $order_info.payment_method.processor_params.in_context == 'Y'}
    {assign var="pop_up" value="true"}
{/if}
{foreach from=$queue_orders item=queue key=index}
    {assign var="pay" value=($pay_step==$index+1)}
    <form name="payments_form_{$index}" action="{""|fn_url}" method="post" class="payments-form">

        <input type="hidden" id="order_ids" name="order_ids" value="{','|implode:$queue.order_ids}">

        <div class="ty-step__container{if $pay}-active{/if}">

            <h3 class="ty-step__title{if $pay}-active{/if}{if $queue.paid && !$pay}-complete{/if} clearfix">
                <span class="ty-step__title-left">{if $queue.paid}{include_ext file="common/icon.tpl" class="ty-icon-ok ty-step__title-icon"}{else}{$index+1}{/if}</span>
                {include_ext file="common/icon.tpl" class="ty-icon-down-micro ty-step__title-arrow"}

                {if $queue.paid}
                    <span class="ty-step__title-txt">{__("epayco_splitpayment_paid")} {include file="common/price.tpl" value=$_total|default:$queue.total}</span>
                {else}
                    <span class="ty-step__title-txt">{__("epayco_splitpayment_pay")} {include file="common/price.tpl" value=$_total|default:$queue.total}</span>
                {/if}
            </h3>

            <div id="step_{$index+1}_body" class="ty-step__body{if $pay}-active{/if} {if !$pay}hidden{/if} clearfix">

                <div class="clearfix">
                    <div class="checkout__block">
                        {include file="addons/epayco_splitpayment/views/epayco_splitpayment/items.tpl" queue=$queue}
                    </div>
                </div>

                <div class="ty-checkout-buttons">
                    {include file="buttons/button.tpl" but_href=$script_proceed but_text=__("epayco_splitpayment_pay") but_role="text" but_id="place_order_`$index`" but_target_form="payments_form_`$index`" but_meta="ty-btn__secondary"}
                    {if !$exist_paid}
                        &nbsp;{include file="buttons/button.tpl" but_href=$script_cancel but_text=__("epayco_splitpayment_cancel") but_role="text"}
                    {/if}
                </div>
            </div>
        </div>
    </form>
{/foreach}

{capture name="mainbox_title"}<span class="ty-checkout__title">{__("epayco_splitpayment_progress_payment_order")}&nbsp;{include_ext file="common/icon.tpl" class="ty-icon-lock ty-checkout__title-icon"}</span>{/capture}

