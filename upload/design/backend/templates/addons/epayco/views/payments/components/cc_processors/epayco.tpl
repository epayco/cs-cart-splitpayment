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
    <label class="control-label" for="p_private_key">PRIVATE_KEY:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][p_private_key]" 
            id="p_private_key"
            value="{$processor_params.p_private_key}"/>
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
            <option value="N" {if $processor_params.p_test_request === "YesNo::NO"|enum}selected="selected"{/if}>{__("producción")}</option>
            <option value="Y" {if $processor_params.p_test_request === "YesNo::YES"|enum}selected="selected"{/if}>{__("prueba")}</option>
        </select>
    </div>
</div>