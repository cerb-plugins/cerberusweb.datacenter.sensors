<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesSensor extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$request = DevblocksPlatform::getHttpRequest();
		$translate = DevblocksPlatform::getTranslationService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // sensor
		@$id = intval(array_shift($stack));
		
		if(null != ($sensor = DAO_DatacenterSensor::get($id)))
			$tpl->assign('sensor', $sensor);

		// Remember the last tab/URL
		
		$point = 'cerberusweb.datacenter.sensor.tab';
		$tpl->assign('point', $point);

		// Properties
		
		$properties = array();
		
		$properties['status'] = array(
			'label' => mb_ucfirst($translate->_('common.status')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->status,
		);
		
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $sensor->updated,
		);
		
		if(null != ($mft_sensor_type = DevblocksPlatform::getExtension($sensor->extension_id, false))) {
			$properties['type'] = array(
				'label' => mb_ucfirst($translate->_('common.type')),
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'value' => $mft_sensor_type->name,
			);
		}
		
		$properties['tag'] = array(
			'label' => mb_ucfirst($translate->_('common.tag')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->tag,
		);
		
		$properties['is_disabled'] = array(
			'label' => mb_ucfirst($translate->_('dao.datacenter_sensor.is_disabled')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $sensor->is_disabled,
		);
		
		$properties['fail_count'] = array(
			'label' => mb_ucfirst($translate->_('dao.datacenter_sensor.fail_count')),
			'type' => Model_CustomField::TYPE_NUMBER,
			'value' => $sensor->fail_count,
		);
		
		$properties['metric_type'] = array(
			'label' => mb_ucfirst($translate->_('dao.datacenter_sensor.metric_type')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $sensor->metric_type,
		);
		
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_SENSOR, $sensor->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields(CerberusContexts::CONTEXT_SENSOR, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets(CerberusContexts::CONTEXT_SENSOR, $sensor->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			CerberusContexts::CONTEXT_SENSOR => array(
				$sensor->id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_SENSOR,
						$sensor->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
		
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_SENSOR);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/profile.tpl');
	}
};