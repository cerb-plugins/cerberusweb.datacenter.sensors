<?php
abstract class Extension_Sensor extends DevblocksExtension {
	const ID = 'cerberusweb.datacenter.sensor';
	
	static function getAll($as_instances=false) {
		$results = DevblocksPlatform::getExtensions('cerberusweb.datacenter.sensor', $as_instances);
		
		// Sorting
		if($as_instances)
			DevblocksPlatform::sortObjects($results, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($results, 'name');
		
		return $results;
	}
	
	abstract function renderConfig($params=array());
	abstract function run($params, &$fields);
};

class WgmDatacenterSensorsSensorExternal extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/external/config.tpl');
	}
	
	function run($params, &$fields) {
		return TRUE;
	}
};

class WgmDatacenterSensorsSensorHttp extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/http/config.tpl');
	}
	
	function run($params, &$fields) {
		if(!extension_loaded('curl')) {
			$error = "The 'curl' PHP extension is required.";
			$fields[DAO_DatacenterSensor::STATUS] = 'C';
			$fields[DAO_DatacenterSensor::METRIC] = $error;
			$fields[DAO_DatacenterSensor::OUTPUT] = $error;
			return FALSE;
		}
		
		$ch = curl_init();
		$success = false;
		
		@$url = $params['url'];
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_exec($ch);
		
		$info = curl_getinfo($ch);
		$status = $info['http_code'];
		
		if(200 == $status) {
			$success = true;
			$output = $status;
		} else {
			$success = false;
			$output = curl_error($ch);
		}
		
		curl_close($ch);
		
		$fields = array(
			DAO_DatacenterSensor::STATUS => ($success?'O':'C'),
			DAO_DatacenterSensor::METRIC => ($success?1:0),
			DAO_DatacenterSensor::OUTPUT => $output,
		);		
		
		return $success;
	}
};

class WgmDatacenterSensorsSensorPort extends Extension_Sensor {
	function renderConfig($params=array()) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/port/config.tpl');
	}
	
	function run($params, &$fields) {
		if(!extension_loaded('curl')) {
			$error = "The 'curl' PHP extension is required.";
			$fields[DAO_DatacenterSensor::STATUS] = 'C';
			$fields[DAO_DatacenterSensor::METRIC] = $error;
			$fields[DAO_DatacenterSensor::OUTPUT] = $error;
			return FALSE;
		}
		
		$errno = null;
		$errstr = null;
		
		@$host = $params['host'];
		@$port = intval($params['port']);
		
		if(false !== (@$conn = fsockopen($host, $port, $errno, $errstr, 10))) {
			$success = true;
			$output = fgets($conn);
			fclose($conn);
		} else {
			$success = false;
			$output = $errstr;
		}
		
		$fields = array(
			DAO_DatacenterSensor::STATUS => ($success?'O':'C'),
			DAO_DatacenterSensor::METRIC => ($success?1:0),
			DAO_DatacenterSensor::OUTPUT => $output,
		);		
		
		return $success;
	}
};

if (class_exists('CerberusCronPageExtension')):
class Cron_WgmDatacenterSensors extends CerberusCronPageExtension {
	public function run() {
		$logger = DevblocksPlatform::getConsoleLog("Sensors");
		$logger->info("Started");

		// Only non-disabled sensors that need to run, up to a max number, longest since updated, not external
		$sensors = DAO_DatacenterSensor::getWhere(
			sprintf("%s = 0 AND %s != %s",
				DAO_DatacenterSensor::IS_DISABLED,
				DAO_DatacenterSensor::EXTENSION_ID,
				C4_ORMHelper::qstr('cerberusweb.datacenter.sensor.external')
			),
			DAO_DatacenterSensor::UPDATED,
			true,
			100
		);
		
		foreach($sensors as $sensor) {
			$pass = $sensor->run();
			$logger->info($sensor->name . ': ' . ($pass===true ? 'PASS' : 'FAIL'));
		}
		
		$logger->info("Finished");
	}
	
	public function configure($instance) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		//$tpl->display('devblocks:cerberusweb.datacenter.sensors::cron/config.tpl');
	}
	
	public function saveConfigurationAction() {
		//@$example_waitdays = DevblocksPlatform::importGPC($_POST['example_waitdays'], 'integer');
		//$this->setParam('example_waitdays', $example_waitdays);
	}
};
endif;

if (class_exists('Extension_DatacenterTab')):
class WgmDatacenterSensorsDatacenterTab extends Extension_DatacenterTab {
	function showTab() {
		$tpl = DevblocksPlatform::getTemplateService();
		
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = 'datacenter_sensors';
		$defaults->class_name = 'View_DatacenterSensor';
		$defaults->renderSubtotals = SearchFields_DatacenterSensor::STATUS;
		
		if(null != ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			$tpl->assign('view', $view);
		}
		
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::datacenter/sensors/tab.tpl');		
	}
	
	function renderConfigExtensionAction() {
		@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'], 'string', ''); 
		@$sensor_id = DevblocksPlatform::importGPC($_REQUEST['sensor_id'], 'integer', 0); 
		
		if(null == ($ext = DevblocksPlatform::getExtension($extension_id, true))) /* @var $ext Extension_DatacenterSensor */
			return;
		
		$params = array();
		
		if(null != ($sensor = DAO_DatacenterSensor::get($sensor_id)))
			$params = $sensor->params;
		
		$ext->renderConfig($params);
	}
}
endif;

// Controller
class Page_Sensors extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$response = DevblocksPlatform::getHttpResponse();
		$active_worker = CerberusApplication::getActiveWorker();

		// Path
		$stack = $response->path;
		@array_shift($stack); // datacenter.domains
		@$module = array_shift($stack); // domain

		switch($module) {
			case 'sensor':
				@$sensor_id = intval(array_shift($stack)); // id
				if(is_numeric($sensor_id) && null != ($sensor = DAO_DatacenterSensor::get($sensor_id)))
					$tpl->assign('sensor', $sensor);
				
				// Remember the last tab/URL
				if(null == ($selected_tab = @$response->path[3])) {
					$selected_tab = $visit->get('cerberusweb.datacenter.sensor.tab', '');
				}
				$tpl->assign('selected_tab', $selected_tab);
				
				$tab_manifests = DevblocksPlatform::getExtensions('cerberusweb.datacenter.sensor.tab', false);
				DevblocksPlatform::sortObjects($tab_manifests, 'name');
				$tpl->assign('tab_manifests', $tab_manifests);

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
				
				if(!empty($sensor->server_id)) {
					if(null != ($server = DAO_Server::get($sensor->server_id))) {
						$properties['server'] = array(
							'label' => ucfirst($translate->_('cerberusweb.datacenter.common.server')),
							'type' => null,
							'server' => $server,
						);
					}
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
				
				$tpl->display('devblocks:cerberusweb.datacenter.sensors::datacenter/sensors/display/index.tpl');		
				break;
				
			default:
				break;
		}
		
	}
	
	// Post	
	function doQuickSearchAction() {
        @$type = DevblocksPlatform::importGPC($_POST['type'],'string'); 
        @$query = DevblocksPlatform::importGPC($_POST['query'],'string');

        $query = trim($query);
        
        $visit = CerberusApplication::getVisit(); /* @var $visit CerberusVisit */
		$active_worker = CerberusApplication::getActiveWorker();
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = 'datacenter_sensors';
		$defaults->class_name = 'View_DatacenterSensor';
		
		$view = C4_AbstractViewLoader::getView('datacenter_sensors', $defaults);
		
        $visit->set('sensors_quick_search_type', $type);
        
        $params = array();
        
        switch($type) {
            case "name":
		        if($query && false===strpos($query,'*'))
		            $query = '*' . $query . '*';
                $params[SearchFields_DatacenterSensor::NAME] = new DevblocksSearchCriteria(SearchFields_DatacenterSensor::NAME,DevblocksSearchCriteria::OPER_LIKE,strtolower($query));               
                break;

            case "comments_all":
            	$params[SearchFields_DatacenterSensor::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchCriteria(SearchFields_DatacenterSensor::FULLTEXT_COMMENT_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'all'));               
                break;
                
            case "comments_phrase":
            	$params[SearchFields_DatacenterSensor::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchCriteria(SearchFields_DatacenterSensor::FULLTEXT_COMMENT_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'phrase'));               
                break;
        }
        
        $view->addParams($params, true);
        $view->renderPage = 0;
        
        C4_AbstractViewLoader::setView($view->id, $view);
        
        DevblocksPlatform::redirect(new DevblocksHttpResponse(array('datacenter','sensors')));
	}	
	
	function showPeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(null != ($model = DAO_DatacenterSensor::get($id))) {
			$tpl->assign('model', $model);
		}
		
		// Custom fields
		
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.datacenter.sensor'); 
		$tpl->assign('custom_fields', $custom_fields);

		if(!empty($model)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.datacenter.sensor', $model->id);
			if(isset($custom_field_values[$id]))
				$tpl->assign('custom_field_values', $custom_field_values[$id]);
		}
		
		// Sensor extensions
		$sensor_manifests = Extension_Sensor::getAll(false);
		$tpl->assign('sensor_manifests', $sensor_manifests);
		
		// Servers
		$servers = DAO_Server::getAll();
		$tpl->assign('servers', $servers);
		
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/peek.tpl');
	}
	
	function savePeekAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		@$tag = DevblocksPlatform::importGPC($_REQUEST['tag'],'string','');
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
		@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'],'string','');
		@$server_id = DevblocksPlatform::importGPC($_REQUEST['server_id'],'integer',0);
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', array());
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if($do_delete && !empty($id)) { // delete
			// [TODO] ACL
			DAO_DatacenterSensor::delete($id);
			
		} else {
			$tag = strtolower($tag);
			
			// Make sure the tag is unique
			if(!empty($tag)) {
				$result = DAO_DatacenterSensor::getByTag($tag);
				// If we matched the tag and it's not this object
				if(!empty($result) && $result->id != $id)
					$tag = null;
			}
			
			$fields = array(
				DAO_DatacenterSensor::NAME => $name,
				DAO_DatacenterSensor::TAG => (!empty($tag) ? $tag : uniqid()),
				DAO_DatacenterSensor::SERVER_ID => $server_id,
				DAO_DatacenterSensor::EXTENSION_ID => $extension_id,
				DAO_DatacenterSensor::PARAMS_JSON => json_encode($params),
			);
			
			if(!empty($id)) { // update
				DAO_DatacenterSensor::update($id, $fields);
				
			} else { // create
				$id = DAO_DatacenterSensor::create($fields);
				
				@$is_watcher = DevblocksPlatform::importGPC($_REQUEST['is_watcher'],'integer',0);
				if($is_watcher)
					CerberusContexts::addWatchers('cerberusweb.contexts.datacenter.sensor', $id, $active_worker->id);
			}
			
			// Custom field saves
			@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost('cerberusweb.contexts.datacenter.sensor', $id, $field_ids);
		}
		
		// Reload view?
		if(!empty($view_id)) {
			if(null != ($view = C4_AbstractViewLoader::getView($view_id)))
				$view->render(); 
		}
	}	
};