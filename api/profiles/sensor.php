<?php
/***********************************************************************
| Cerb(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2012, WebGroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_ProfilesSensor extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$request = DevblocksPlatform::getHttpRequest();
		$translate = DevblocksPlatform::getTranslationService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // sensor
		@$identifier = array_shift($stack);
		
		if(is_numeric($identifier)) {
			$id = intval($identifier);
		} elseif(preg_match("#.*?\-(\d+)$#", $identifier, $matches)) {
			@$id = intval($matches[1]);
		} else {
			@$id = intval($identifier);
		}
		
		if(null != ($sensor = DAO_DatacenterSensor::get($id)))
			$tpl->assign('sensor', $sensor);

		// Remember the last tab/URL
		
		$point = 'cerberusweb.datacenter.sensor.tab';
		$tpl->assign('point', $point);

		@$selected_tab = array_shift($stack);
		
		if(null == $selected_tab) {
			$selected_tab = $visit->get($point, '');
		}
		$tpl->assign('selected_tab', $selected_tab);

		// Custom fields
		
		$custom_fields = DAO_CustomField::getAll();
		$tpl->assign('custom_fields', $custom_fields);
		
		// Properties
		
		$properties = array();
		
		$properties['status'] = array(
			'label' => ucfirst($translate->_('common.status')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->status,
		);
		
		$properties['updated'] = array(
			'label' => ucfirst($translate->_('common.updated')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $sensor->updated,
		);
		
		if(null != ($mft_sensor_type = DevblocksPlatform::getExtension($sensor->extension_id, false))) {
			$properties['type'] = array(
				'label' => ucfirst($translate->_('common.type')),
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'value' => $mft_sensor_type->name,
			);
		}
		
		$properties['tag'] = array(
			'label' => ucfirst($translate->_('common.tag')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->tag,
		);
		
		$properties['is_disabled'] = array(
			'label' => ucfirst($translate->_('dao.datacenter_sensor.is_disabled')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $sensor->is_disabled,
		);
		
		$properties['fail_count'] = array(
			'label' => ucfirst($translate->_('dao.datacenter_sensor.fail_count')),
			'type' => Model_CustomField::TYPE_NUMBER,
			'value' => $sensor->fail_count,
		);
		
		$properties['metric_type'] = array(
			'label' => ucfirst($translate->_('dao.datacenter_sensor.metric_type')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->metric_type,
		);
		
		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.datacenter.sensor', $sensor->id)) or array();
		
		foreach($custom_fields as $cf_id => $cfield) {
			if(!isset($values[$cf_id]))
				continue;
		
			$properties['cf_' . $cf_id] = array(
				'label' => $cfield->name,
				'type' => $cfield->type,
				'value' => $values[$cf_id],
			);
		}
		
		$tpl->assign('properties', $properties);
		
		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.sensor');
		$tpl->assign('macros', $macros);
		
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, 'cerberusweb.contexts.datacenter.sensor');
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/profile.tpl');		
	}
};