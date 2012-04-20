{$page_context = 'cerberusweb.contexts.datacenter.sensor'}
{$page_context_id = $sensor->id}

<h1>{$sensor->name}</h1>

<fieldset class="properties">
	<legend>{'datacenter.sensors.common.sensor'|devblocks_translate|capitalize}</legend>
	
	<form action="{devblocks_url}{/devblocks_url}" method="post" style="margin-bottom:5px;">

		{foreach from=$properties item=v key=k name=props}
			<div class="property">
				{if $k == 'server'}
					<b>{$v.label|capitalize}:</b>
					<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_SERVER}&context_id={$v.server->id}',null,false,'500');">{$v.server->name}</a>
				{elseif $k == 'status'}
					<b>{'common.status'|devblocks_translate|capitalize}:</b>
					<div class="badge badge-lightgray">
					{if $sensor->status == "W"}
						<span style="color:rgb(204,154,0);font-weight:bold;">Warning</span>
					{elseif $sensor->status == "C"}
						<span style="color:rgb(200,0,0);font-weight:bold;">Critical</span>
					{else}
						<span style="color:rgb(0,180,0);font-weight:bold;">OK</span>
					{/if}
					</div>
				{else}
					{include file="devblocks:cerberusweb.core::internal/custom_fields/profile_cell_renderer.tpl"}
				{/if}
			</div>
			{if $smarty.foreach.props.iteration % 3 == 0 && !$smarty.foreach.props.last}
				<br clear="all">
			{/if}
		{/foreach}
		
		<br clear="all">
	
		<div class="property">
			<b>{'dao.datacenter_sensor.output'|devblocks_translate|capitalize}:</b>
{if strstr($sensor->output,"\n")}
<pre style="margin:0px;margin-left:20px;">{$sensor->output}</pre>
{else}
{$sensor->output|escape|nl2br nofilter}
{/if}
		</div>
		
		<br clear="all">
		
		<!-- Toolbar -->
		<div>
			<span>
			{$object_watchers = DAO_ContextLink::getContextLinks($page_context, array($page_context_id), CerberusContexts::CONTEXT_WORKER)}
			{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=$page_context context_id=$page_context_id full=true}
			</span>		

			<!-- Macros -->
			{devblocks_url assign=return_url full=true}c=datacenter.sensors&tab=sensor&id={$page_context_id}{/devblocks_url}
			{include file="devblocks:cerberusweb.core::internal/macros/display/button.tpl" context=$page_context context_id=$page_context_id macros=$macros return_url=$return_url}		
		
			<!-- Edit -->
			<button type="button" id="btnDatacenterSensorEdit"><span class="cerb-sprite sprite-document_edit"></span> Edit</button>
		</div>
	
	</form>
	
	{if $pref_keyboard_shortcuts}
	<small>
		{$translate->_('common.keyboard')|lower}:
		(<b>e</b>) {'common.edit'|devblocks_translate|lower}
		{if !empty($macros)}(<b>m</b>) {'common.macros'|devblocks_translate|lower} {/if}
		(<b>1-9</b>) change tab
	</small> 
	{/if}
</fieldset>

<div>
{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div>
{include file="devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div id="datacenterSensorTabs">
	<ul>
		{$point = 'cerberusweb.datacenter.sensor.tab'}
		{$tabs = [activity, comments, links]}
		
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabActivityLog&scope=target&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">{'common.activity_log'|devblocks_translate|capitalize}</a></li>		
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextComments&context={$page_context}&id={$page_context_id}&point={$point}{/devblocks_url}">{'common.comments'|devblocks_translate|capitalize}</a></li>
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextLinks&context={$page_context}&id={$page_context_id}&point={$point}{/devblocks_url}">{'common.links'|devblocks_translate}</a></li>
	</ul>
</div> 
<br>

{$selected_tab_idx=0}
{foreach from=$tabs item=tab_label name=tabs}
	{if $tab_label==$selected_tab}{$selected_tab_idx = $smarty.foreach.tabs.index}{/if}
{/foreach}

<script type="text/javascript">
	$(function() {
		var tabs = $("#datacenterSensorTabs").tabs( { selected:{$selected_tab_idx} } );
		
		$('#btnDatacenterSensorEdit').bind('click', function() {
			$popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={$page_context}&context_id={$page_context_id}',null,false,'550');
			$popup.one('datacenter_sensor_save', function(event) {
				event.stopPropagation();
				document.location.href = '{devblocks_url}c=datacenter.sensors&a=sensor&id={$page_context_id}{/devblocks_url}-{$sensor->name|devblocks_permalink}';
			});
		});
		
		{include file="devblocks:cerberusweb.core::internal/macros/display/menu_script.tpl"}
	});
</script>

<script type="text/javascript">
{if $pref_keyboard_shortcuts}
$(document).keypress(function(event) {
	if(event.altKey || event.ctrlKey || event.shiftKey || event.metaKey)
		return;
	
	if($(event.target).is(':input'))
		return;

	hotkey_activated = true;
	
	switch(event.which) {
		case 49:  // (1) tab cycle
		case 50:  // (2) tab cycle
		case 51:  // (3) tab cycle
		case 52:  // (4) tab cycle
		case 53:  // (5) tab cycle
		case 54:  // (6) tab cycle
		case 55:  // (7) tab cycle
		case 56:  // (8) tab cycle
		case 57:  // (9) tab cycle
		case 58:  // (0) tab cycle
			try {
				idx = event.which-49;
				$tabs = $("#datacenterSensorTabs").tabs();
				$tabs.tabs('select', idx);
			} catch(ex) { } 
			break;
		case 101:  // (E) edit
			try {
				$('#btnDatacenterSensorEdit').click();
			} catch(ex) { } 
			break;
		case 109:  // (M) macros
			try {
				$('#btnDisplayMacros').click();
			} catch(ex) { } 
			break;
		default:
			// We didn't find any obvious keys, try other codes
			hotkey_activated = false;
			break;
	}
	
	if(hotkey_activated)
		event.preventDefault();
});
{/if}
</script>
