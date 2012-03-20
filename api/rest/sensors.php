<?php
class ChRest_Sensors extends Extension_RestController implements IExtensionRestController {
	function getAction($stack) {
		@$action = array_shift($stack);
		
		// Looking up a single ID?
		if(is_numeric($action)) {
			$this->getId(intval($action));
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function putAction($stack) {
		@$action = array_shift($stack);
		
		// Looking up a single ID?
		if(is_numeric($action)) {
			$this->putId(intval($action));
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function postAction($stack) {
		@$action = array_shift($stack);
		
		if(is_numeric($action) && !empty($stack)) {
			$id = intval($action);
			$action = array_shift($stack);
			
			switch($action) {
// 				case 'note':
// 					$this->postNote($id);
// 					break;
			}
			
		} else {
			switch($action) {
				case 'bulk_update':
					$this->postBulkUpdate();
					break;
				case 'create':
					$this->postCreate();
					break;
				case 'search':
					$this->postSearch();
					break;
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function deleteAction($stack) {
		$id = array_shift($stack);

		if(null == ($sensor = DAO_DatacenterSensor::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid sensor ID %d", $id));

		DAO_DatacenterSensor::delete($id);

		$result = array('id' => $id);
		$this->success($result);		
	}

	function translateToken($token, $type='dao') {
		$tokens = array();
		
		if('dao'==$type) {
			$tokens = array(
				'metric' => DAO_DatacenterSensor::METRIC,
				'name' => DAO_DatacenterSensor::NAME,
				'output' => DAO_DatacenterSensor::OUTPUT,
				'status' => DAO_DatacenterSensor::STATUS,
				'type' => DAO_DatacenterSensor::EXTENSION_ID,
				'updated' => DAO_DatacenterSensor::UPDATED,
			);
		} else {
			$tokens = array(
				'id' => SearchFields_DatacenterSensor::ID,
				'metric' => SearchFields_DatacenterSensor::METRIC,
				'name' => SearchFields_DatacenterSensor::NAME,
				'output' => SearchFields_DatacenterSensor::OUTPUT,
				'status' => SearchFields_DatacenterSensor::STATUS,
				'type' => SearchFields_DatacenterSensor::EXTENSION_ID,
				'updated' => SearchFields_DatacenterSensor::UPDATED,
			);
		}
		
		if(isset($tokens[$token]))
			return $tokens[$token];
		
		return NULL;
	}

	function getContext($id) {
		$labels = array();
		$values = array();
		$context = CerberusContexts::getContext('cerberusweb.contexts.sensor', $id, $labels, $values, null, true);

//		unset($values['initial_message_content']);

		return $values;
	}
	
	function getId($id) {
		$worker = $this->getActiveWorker();
		
		// ACL
//		if(!$worker->hasPriv('...'))
//			$this->error("Access denied.");

		$container = $this->search(array(
			array('id', '=', $id),
		));
		
		if(is_array($container) && isset($container['results']) && isset($container['results'][$id]))
			$this->success($container['results'][$id]);

		// Error
		$this->error(self::ERRNO_CUSTOM, sprintf("Invalid sensor id '%d'", $id));
	}
	
	function search($filters=array(), $sortToken='id', $sortAsc=1, $page=1, $limit=10) {
		$worker = $this->getActiveWorker();

		$custom_field_params = $this->_handleSearchBuildParamsCustomFields($filters, 'cerberusweb.contexts.sensor');
		$params = $this->_handleSearchBuildParams($filters);
		$params = array_merge($params, $custom_field_params);
				
		// Sort
		$sortBy = $this->translateToken($sortToken, 'search');
		$sortAsc = !empty($sortAsc) ? true : false;
		
		// Search
		list($results, $total) = DAO_DatacenterSensor::search(
			!empty($sortBy) ? array($sortBy) : array(),
			$params,
			$limit,
			max(0,$page-1),
			$sortBy,
			$sortAsc,
			true
		);
		
		$objects = array();
		
		foreach($results as $id => $result) {
			$values = $this->getContext($id);
			$objects[$id] = $values;
		}
		
		$container = array(
			'total' => $total,
			'count' => count($objects),
			'page' => $page,
			'results' => $objects,
		);
		
		return $container;		
	}	
	
	function postBulkUpdate() {
		$worker = $this->getActiveWorker();

		$payload = $this->getPayload();
		$xml = simplexml_load_string($payload);
		
		$dict_tags_to_ids = array();
		
		foreach($xml->sensor as $eSensor) {
			@$sensor_id = (string) $eSensor['id'];
			@$name = (string) $eSensor->name;
			@$metric = (string) $eSensor->metric;
			//@$metric_type = (string) $eSensor->metric_type;
			@$output = (string) $eSensor->output;
			
			if(!is_numeric($sensor_id)) {
				if(isset($dict_tags_to_ids[$sensor_id])) {
					$sensor_id = $dict_tags_to_ids[$sensor_id];
				} else {
					// Look up by tag
					$tag = $sensor_id;
					
					// Look it up or create it
					if(null == ($sensor_id = DAO_DatacenterSensor::getByTag($tag))) {
						$fields = array(
							DAO_DatacenterSensor::TAG => $tag,
							DAO_DatacenterSensor::NAME => (!empty($name) ? $name : $tag),
							DAO_DatacenterSensor::EXTENSION_ID => Extension_Sensor::ID,
							DAO_DatacenterSensor::PARAMS_JSON => json_encode(array()),
						);
						$sensor_id = DAO_DatacenterSensor::create($fields);
					}
					
					if(is_numeric($sensor_id) && !empty($sensor_id))
						$dict_tags_to_ids[$tag] = $sensor_id;
				}
			}

			if(is_numeric($sensor_id) && !empty($sensor_id)) {
				$fields = array(
					DAO_DatacenterSensor::OUTPUT => $output,
					DAO_DatacenterSensor::METRIC => $metric,
					DAO_DatacenterSensor::UPDATED => time(),
				);
				
				if(!empty($name))
					$fields[DAO_DatacenterSensor::NAME] = $name;
				
				DAO_DatacenterSensor::update($sensor_id, $fields);
			}
		}
		
		$this->success(array());
	}
	
	function postSearch() {
		$worker = $this->getActiveWorker();
		
		// ACL
//		if(!$worker->hasPriv('core.addybook'))
//			$this->error(self::ERRNO_ACL);

		$container = $this->_handlePostSearch();
		
		$this->success($container);
	}
	
	function putId($id) {
		$worker = $this->getActiveWorker();
		
		// Validate the ID
		if(null == ($sensor = DAO_DatacenterSensor::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid sensor ID '%d'", $id));
			
		// ACL
		//if(!($worker->hasPriv('core.tasks.actions.update_all') || $sensor->worker_id == $worker->id))
		//	$this->error(self::ERRNO_ACL);
			
		$putfields = array(
			'metric' => 'string',
			'name' => 'string',
			'output' => 'string',
			'status' => 'string',
			'updated' => 'timestamp',
		);

		$fields = array();

		foreach($putfields as $putfield => $type) {
			if(!isset($_POST[$putfield]))
				continue;
			
			@$value = DevblocksPlatform::importGPC($_POST[$putfield], 'string', '');
			
			if(null == ($field = self::translateToken($putfield, 'dao'))) {
				$this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $putfield));
			}
			
			// Sanitize
			$value = DevblocksPlatform::importVar($value, $type);
						
//			switch($field) {
//				case DAO_Worker::PASSWORD:
//					$value = md5($value);
//					break;
//			}
			
			$fields[$field] = $value;
		}
		
		if(!isset($fields[DAO_DatacenterSensor::UPDATED]))
			$fields[DAO_DatacenterSensor::UPDATED] = time();
		
		// Handle custom fields
		$customfields = $this->_handleCustomFields($_POST);
		if(is_array($customfields))
			DAO_CustomFieldValue::formatAndSetFieldValues('cerberusweb.contexts.sensor', $id, $customfields, true, true, true);
		
		// Check required fields
//		$reqfields = array(DAO_Address::EMAIL);
//		$this->_handleRequiredFields($reqfields, $fields);

		// Update
		DAO_DatacenterSensor::update($id, $fields);
		$this->getId($id);
	}
	
	function postCreate() {
		$worker = $this->getActiveWorker();
		
		// ACL
		//if(!$worker->hasPriv('core.tasks.actions.create'))
		//	$this->error(self::ERRNO_ACL);
		
		$postfields = array(
			'metric' => 'string',
			'name' => 'string',
			'output' => 'string',
			'server_id' => 'integer',
			'status' => 'string',
			'type' => 'string',
			'updated' => 'timestamp',
		);

		$fields = array();
		
		foreach($postfields as $postfield => $type) {
			if(!isset($_POST[$postfield]))
				continue;
				
			@$value = DevblocksPlatform::importGPC($_POST[$postfield], 'string', '');
				
			if(null == ($field = self::translateToken($postfield, 'dao'))) {
				$this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $postfield));
			}

			// Sanitize
			$value = DevblocksPlatform::importVar($value, $type);
			
			switch($field) {
				case 'type':
					$field = DAO_DatacenterSensor::EXTENSION_ID;
					$ext_id = null;
					
					switch($value) {
						case 'external':
							$ext_id = 'cerberusweb.datacenter.sensor.external';
							break;
						case 'http':
							$ext_id = 'cerberusweb.datacenter.sensor.http';
							break;
						case 'port':
							$ext_id = 'cerberusweb.datacenter.sensor.port';
							break;
						default:
							// Allow custom sensors as long as they're well-formed
							if(null != ($ext = DevblocksPlatform::getExtension($value, true))) {
								if(is_a($ext,'Extension_Sensor'))
									$ext_id = $ext->id;
							}
							break;
					}
					
					$fields[$field] = $ext_id;
					break;
					
				default:
					$fields[$field] = $value;
					break;
			}
		}

		// Defaults
		if(!isset($fields[DAO_DatacenterSensor::UPDATED]))
			$fields[DAO_DatacenterSensor::UPDATED] = time();
		
		// Check required fields
		$reqfields = array(
			DAO_DatacenterSensor::NAME, 
			DAO_DatacenterSensor::EXTENSION_ID, 
		);
		$this->_handleRequiredFields($reqfields, $fields);
		
		// Create
		if(false != ($id = DAO_DatacenterSensor::create($fields))) {
			// Handle custom fields
			$customfields = $this->_handleCustomFields($_POST);
			if(is_array($customfields))
				DAO_CustomFieldValue::formatAndSetFieldValues('cerberusweb.contexts.sensor', $id, $customfields, true, true, true);
			
			$this->getId($id);
		}
	}

};