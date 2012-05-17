<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmSensor">
<input type="hidden" name="c" value="datacenter.sensors">
<input type="hidden" name="a" value="savePeek">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">

<fieldset class="peek">
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
		
		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="middle" align="right">{$translate->_('common.watchers')|capitalize}: </td>
			<td width="100%">
				{if empty($model->id)}
					<label><input type="checkbox" name="is_watcher" value="1"> {'common.watchers.add_me'|devblocks_translate}</label>
				{else}
					{$object_watchers = DAO_ContextLink::getContextLinks('cerberusweb.contexts.datacenter.sensor', array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context='cerberusweb.contexts.datacenter.sensor' context_id=$model->id full=true}
				{/if}
			</td>
		</tr>
		
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{* Comment *}
{if !empty($last_comment)}
	{include file="devblocks:cerberusweb.core::internal/comments/comment.tpl" readonly=true comment=$last_comment}
{/if}

<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmSensor','{$view_id}', false, 'datacenter_sensor_save');"><span class="cerb-sprite2 sprite-tick-circle"></span> {$translate->_('common.save_changes')|capitalize}</button>
{if $model->id && $active_worker->is_superuser}<button type="button" onclick="if(confirm('Permanently delete this sensor?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmSensor','{$view_id}'); } "><span class="cerb-sprite2 sprite-minus-circle"></span> {$translate->_('common.delete')|capitalize}</button>{/if}

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
