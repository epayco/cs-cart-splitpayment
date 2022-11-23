{if !empty($company.epayco_verification_status.main_pair)}
    {include file="common/image.tpl" image_width=$company.epayco_verification_status.width image_height=$company.epayco_verification_status.height obj_id=$object_id images=$company.epayco_verification_status.main_pair class="vendor-catalog-verification"}
{elseif !empty($company.epayco_verification_status.verified) && $company.epayco_verification_status.verified == 'verified'}
    <span class="vendor-catalog-verification">&nbsp;{__('verified_by_epayco')}</span>
{/if}