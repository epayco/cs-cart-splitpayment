{assign var="profile_fields" value="I"|fn_get_profile_fields}
{if $profile_fields.B}
    <h4 class="ty-order-info__title">{__("billing_address")}:</h4>

    <ul id="tygh_billing_adress" class="ty-order-info__profile-field clearfix">
        {foreach from=$profile_fields.B item="field"}
            {assign var="value" value=$order_info|fn_get_profile_field_value:$field}
            {if $value}
                <li class="ty-order-info__profile-field-item {$field.field_name|replace:"_":"-"}">{$value}</li>
            {/if}
        {/foreach}
    </ul>

    <hr class="shipping-adress__delim">
{/if}

{if $profile_fields.S}
    <h4 class="ty-order-info__title">{__("shipping_address")}:</h4>
    <ul id="tygh_shipping_adress" class="ty-order-info__profile-field clearfix">
        {foreach from=$profile_fields.S item="field"}
            {assign var="value" value=$order_info|fn_get_profile_field_value:$field}
            {if $value}
                <li class="ty-order-info__profile-field-item {$field.field_name|replace:"_":"-"}">{$value}</li>
            {/if}
        {/foreach}
    </ul>
    <hr class="shipping-adress__delim">
{/if}

{assign var="block_wrap" value="checkout_order_info_`$block.snapping_id`_wrap" scope="parent"}