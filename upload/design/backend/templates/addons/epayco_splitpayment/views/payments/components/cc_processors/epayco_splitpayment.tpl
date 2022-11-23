{include file="common/subheader.tpl" title=__("epayco_workflow") target="#ppa_workflow"}
<div id="ppa_workflow" class="collapse in">

    <div class="control-group">
        <label class="control-label cm-required" for="primary_email">{__("split_app_id")}:</label>
        <div class="controls">
            <input type="text" name="payment_data[processor_params][primary_email]" id="primary_email" value="{$processor_params.primary_email}" class="input-text" />
            <p class="muted description">{__("split_app_id")}</p>
        </div>
    </div>

    <input type="hidden" name="payment_data[processor_params][in_context]" value="N" />
</div>

{$are_credentials_filled = $processor_params.username && $processor_params.password}
{include file="common/subheader.tpl" title=__("epayco_credentials") meta="{if $are_credentials_filled}collapsed{/if}" target="#ppa_credentials"}
<div id="ppa_credentials" class="collapse {if $are_credentials_filled}out{else}in{/if}">
    <div class="control-group">
        <label class="control-label" for="p_cust_id_cliente">P_CUST_ID_CLIENTE:</label>
        <div class="controls">
            <input type="text" name="payment_data[processor_params][p_cust_id_cliente]" 
                id="p_cust_id_cliente"
                value="{$processor_params.p_cust_id_cliente}"/>
        </div>
    </div>
    <div class="control-group">
        <label class="control-label" for="p_public_key">PUBLIC_KEY:</label>
        <div class="controls">
            <input type="text" name="payment_data[processor_params][p_public_key]" 
                id="p_public_key"
                value="{$processor_params.p_public_key}"/>
        </div>
    </div>
    <div class="control-group">
        <label class="control-label" for="p_key">P_KEY:</label>
        <div class="controls">
            <input type="text" name="payment_data[processor_params][p_key]" id="p_key" 
            value="{$processor_params.p_key}"/>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="p_test_request">TEST_REQUEST:</label>
        <div class="controls">
            <select name="payment_data[processor_params][p_test_request]" id="p_test_request">
                {if $processor_params.p_test_request == 'STANDART'}
                    <option value="STANDART" selected="selected">STANDART</option>
                    <option value="ONEPAGE">ONEPAGE</option>
                {else}
                    <option value="STANDART">STANDART</option>
                    <option value="ONEPAGE" selected="selected">ONEPAGE</option>
                {/if}
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label">{__("test_live_mode")}:</label>
        <div class="controls">
            <label class="radio inline">
                <input class="cm-switch-availability cm-switch-inverse cm-switch-visibility" id="sw_block_app_id" type="radio" value="test" name="payment_data[processor_params][mode]" {if $processor_params.mode == "test" || !$processor_params.mode} checked="checked"{/if}>
                {__("test")}
            </label>
            <label class="radio inline">
                <input class="cm-switch-availability cm-switch-visibility" id="sw_block_app_id" type="radio" value="live" name="payment_data[processor_params][mode]" {if $processor_params.mode == "live"} checked="checked"{/if}>
                {__("live")}
            </label>
        </div>
    </div>

</div>
