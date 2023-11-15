{if "MULTIVENDOR"|fn_allowed_for}

    {include file="common/subheader.tpl" title="split_receivers"}
    <div class="control-group hidden">
        <label for="email" class="control-label cm-email">{__("epayco.vendor.")}:</label>
        <div class="controls">
            <input type="text" id="email" name="company_data[epayco_email]" class="input-text" size="32" maxlength="128" value="{$company_data.epayco_email}"/>
            <p class="muted descriptionepayco_email">{__("ttc_epayco.vendor.epayco_email")}</p>
        </div>
    </div>
    <div class="control-group">
        <label for="ppa_first_name" class="control-label">p_cust_id_cliente:</label>
        <div class="controls">
            <input type="text" id="ppa_first_name" name="company_data[ppa_first_name]" class="input-text" size="32" maxlength="128" value="{$company_data.ppa_first_name}"/>
            <p class="muted description">Id del usuario que va a recibir el pago</p>
        </div>
    </div>

{/if}