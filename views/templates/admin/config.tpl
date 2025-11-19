<form method="post">
    <label>Kintsugi API Key</label>
    <input type="text" name="KINTSUGI_API_KEY" value="{$kintsugi_api_key|escape:'html'}" style="width:350px"/><br/>
    <label>Kintsugi Org ID</label>
    <input type="text" name="KINTSUGI_ORG_ID" value="{$kintsugi_org_id|escape:'html'}" style="width:350px"/><br/>
    <input type="submit" name="submit_pskintsugitax" value="Save" class="button"/>
</form>