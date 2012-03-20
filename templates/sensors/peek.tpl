<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmSensor">
<input type="hidden" name="c" value="datacenter">
<input type="hidden" name="a" value="handleTabAction">
<input type="hidden" name="tab" value="cerberusweb.datacenter.tab.sensors">
<input type="hidden" name="action" value="savePeek">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">

<fieldset>
	<legend>{'common.properties'|devblocks_translate}</legend>
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" nowrap="nowrap" valign="top" align="right">{'common.name'|devblocks_translate|capitalize}:</td>
			<td width="99%">
				<input type="text" name="name" value="{$model->name}" style="width:98%;">
			</td>
		</tr>
		
		<tr>
			<td width="1%" nowrap="nowrap" valign="top" align="right">{'common.tag'|devblocks_translate|capitalize}:</td>
			<td width="99%">
				<input type="text" name="tag" value="{$model->tag}" style="width:98%;">
			</td>
		</tr>
		
		{if !empty($sensor_manifests)}
		<tr>
			<td width="1%" nowrap="nowrap" valign="top" align="right">{'dao.datacenter_sensor.extension_id'|devblocks_translate|capitalize}:</td>
			<td width="99%">
				<select name="extension_id">
					{foreach from=$sensor_manifests item=sensor_manifest key=k}
						<option value="{$k}" {if $k==$model->extension_id}selected="selected"{/if}>{$sensor_manifest->name}</option>
					{/foreach}
				</select>
				
				<div class="params" style="margin:0px 5px;padding:5px;background-color:rgb(245,245,245);">
					{if isset($sensor_manifests.{$model->extension_id})}
						{$sensor_ext_id = $model->extension_id}
					{else}
						{$sensor_ext_id = 'cerberusweb.datacenter.sensor.external'}
					{/if}
					
					{$sensor_ext = $sensor_manifests.{$sensor_ext_id}->createInstance()}
					{if method_exists($sensor_ext,'renderConfig')}
						{$sensor_ext->renderConfig($model->params)}
					{/if}
				</div>
			</td>
		</tr>
		{/if}
		
		{if !empty($servers)}
		<tr>
			<td width="1%" nowrap="nowrap" valign="top" align="right">{'cerberusweb.datacenter.common.server'|devblocks_translate}:</td>
			<td width="99%">
				<select name="server_id">
					<option value=""></option>
					{foreach from=$servers item=server key=server_id}
						<option value="{$server_id}" {if $server_id==$model->server_id}selected="selected"{/if}>{$server->name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		{/if}
		
		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="middle" align="right">{$translate->_('common.watchers')|capitalize}: </td>
			<td width="100%">
				{if empty($model->id)}
					<label><input type="checkbox" name="is_watcher" value="1"> {'common.watchers.add_me'|devblocks_translate}</label>
				{else}
					{$object_watchers = DAO_ContextLink::getContextLinks('cerberusweb.contexts.sensor', array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context='cerberusweb.contexts.sensor' context_id=$model->id full=true}
				{/if}
			</td>
		</tr>
		
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset>
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{* Comment *}
{if !empty($last_comment)}
	{include file="devblocks:cerberusweb.core::internal/comments/comment.tpl" readonly=true comment=$last_comment}
{/if}

{*
<fieldset>
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="5" cols="45" style="width:98%;"></textarea>
	<div class="notify" style="display:none;">
		<b>{'common.notify_watchers_and'|devblocks_translate}:</b>
		<button type="button" class="chooser_notify_worker"><span class="cerb-sprite sprite-view"></span></button>
		<ul class="chooser-container bubbles" style="display:block;"></ul>
	</div>
</fieldset>
*}

<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmSensor','{$view_id}', false, 'sensor_save');"><span class="cerb-sprite2 sprite-tick-circle-frame"></span> {$translate->_('common.save_changes')|capitalize}</button>
{if $model->id && $active_worker->is_superuser}<button type="button" onclick="if(confirm('Permanently delete this sensor?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmSensor','{$view_id}'); } "><span class="cerb-sprite2 sprite-minus-circle-frame"></span> {$translate->_('common.delete')|capitalize}</button>{/if}

{if $model->id}
<div style="float:right;">
	<b>ID:</b> {$model->id}
</div>
{/if}

</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{'datacenter.sensors.common.sensor'|devblocks_translate|capitalize}");
		$(this).find('textarea[name=comment]').keyup(function() {
			if($(this).val().length > 0) {
				$(this).next('DIV.notify').show();
			} else {
				$(this).next('DIV.notify').hide();
			}
		});
		$('#frmSensor button.chooser_notify_worker').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','notify_worker_ids', { autocomplete:true });
		});
		
		$(this).find('select[name=extension_id]').change(function() {
			genericAjaxGet($(this).next('DIV.params'), 'c=datacenter&a=handleTabAction&tab=cerberusweb.datacenter.tab.sensors&action=renderConfigExtension&extension_id=' + $(this).val() + "&sensor_id={$model->id}");
		});
		
		$(this).find('input:text:first').focus();
	} );
</script>
