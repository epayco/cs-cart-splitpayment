{if "MULTIVENDOR"|fn_allowed_for && ($company_name || $company_id) && $settings.Vendors.display_vendor == "Y"}
    <div class="ty-control-group{if !$capture_options_vs_qty} product-list-field{/if} epayco-splitpayment-vendor-name {if !empty($product.epayco_verification.verified) && $product.epayco_verification.verified == "verified"}epayco-splitpayment-vendor-name-text{/if}">
        <label class="ty-control-group__label">{__("vendor")}:</label>
        <span class="ty-control-group__item"><a href="{"companies.products?company_id=`$company_id`"|fn_url}">{if $company_name}{$company_name}{else}{$company_id|fn_get_company_name}{/if}</a></span>
        {hook name="companies:product_company_data"}
            {if !empty($product.epayco_verification.main_pair)}
                {include file="common/image.tpl" image_width=$product.epayco_verification.width image_height=$product.epayco_verification.height obj_id=$object_id images=$product.epayco_verification.main_pair}
            {elseif !empty($product.epayco_verification.verified) && $product.epayco_verification.verified == "verified"}
                <span class="ty-control-group__item">{__("verified_by_epayco")}</span>
            {/if}
        {/hook}
    </div>
{/if}
