<?php
class DAO_DatacenterSensor extends C4_ORMHelper {
	const ID = 'id';
	const TAG = 'tag';
	const NAME = 'name';
	const STATUS = 'status';
	const EXTENSION_ID = 'extension_id';
	const PARAMS_JSON = 'params_json';
	const UPDATED = 'updated';
	const FAIL_COUNT = 'fail_count';
	const IS_DISABLED = 'is_disabled';
	const METRIC = 'metric';
	const METRIC_TYPE = 'metric_type';
	const METRIC_DELTA = 'metric_delta';
	const OUTPUT = 'output';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO datacenter_sensor () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Get state before changes
			$object_changes = parent::_getUpdateDeltas($batch_ids, $fields, get_class());

			// Make changes
			parent::_update($batch_ids, 'datacenter_sensor', $fields);
			
			// Send events
			if(!empty($object_changes)) {
				// Local events
				self::_processUpdateEvents($object_changes);
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.datacenter.sensor.update',
						array(
							'objects' => $object_changes,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_SENSOR, $batch_ids);
			}
		}
	}
	
	static function _processUpdateEvents($objects) {
		$db = DevblocksPlatform::getDatabaseService();
		
    	if(is_array($objects))
    	foreach($objects as $object_id => $object) {
    		@$model = $object['model']; /* @var $model Model_DatacenterSensor */
    		@$changes = $object['changes'];
    		
    		if(empty($model) || empty($changes))
    			continue;
    		
    		// Delta
    		@$metric = $changes[DAO_DatacenterSensor::METRIC];
    		
    		if(in_array($model[DAO_DatacenterSensor::METRIC_TYPE], array('updown','decimal','percent','number'))) {
    			$delta = 0;
    			
    			if(!empty($metric)) {
	    			switch($model[DAO_DatacenterSensor::METRIC_TYPE]) {
	    				case 'updown':
	    					$delta = (0 == strcasecmp($metric['to'],'UP')) ? 1 : -1;
	    					break;
	    				case 'number':
	    					$delta = intval($metric['to']) - intval($metric['from']);
	    					break;
	    				case 'decimal':
	    					$delta = floatval($metric['to']) - floatval($metric['from']);
	    					break;
	    				case 'percent':
	    					$delta = intval($metric['to']) - intval($metric['from']);
	    					break;
	    			}
    			}
    			
    			if(isset($model[DAO_DatacenterSensor::METRIC_DELTA])
    				&& $model[DAO_DatacenterSensor::METRIC_DELTA] != $delta) {
		    			$sql = sprintf("UPDATE datacenter_sensor SET metric_delta = %s WHERE id = %d",
		    				$db->qstr($delta),
		    				$model[DAO_DatacenterSensor::ID]
		    			);
		    			$db->Execute($sql);
    			}
    		}
    		
			// This can also detect when the status changes OK->PROBLEM or PROBLEM->OK
    		
    		$statuses = array(
    			'O' => 'OK',
    			'W' => 'Warning',
    			'C' => 'Critical',
    		);
    		
    		@$status = $changes[DAO_DatacenterSensor::STATUS];
    		
    		if(!empty($status) && !empty($model[DAO_DatacenterSensor::STATUS])) {
				/*
				 * Log sensor status (sensor.status.*)
				 */
				$entry = array(
					//{{sensor}} sensor status changed from {{status_from}} to {{status_to}}
					'message' => 'activities.datacenter.sensor.status',
					'variables' => array(
						'sensor' => sprintf("%s", $model[DAO_DatacenterSensor::NAME]),
						'status_from' => sprintf("%s", $statuses[$status['from']]),
						'status_to' => sprintf("%s", $statuses[$status['to']]),
						),
					'urls' => array(
						'sensor' => sprintf("ctx://%s:%d/%s", 'cerberusweb.contexts.datacenter.sensor', $object_id, $model[DAO_DatacenterSensor::NAME]),
						)
				);
				CerberusContexts::logActivity('datacenter.sensor.status', 'cerberusweb.contexts.datacenter.sensor', $object_id, $entry);
    		}
    		
    	} // foreach
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('datacenter_sensor', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_DatacenterSensor[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, tag, name, extension_id, status, updated, fail_count, is_disabled, params_json, metric, metric_type, metric_delta, output ".
			"FROM datacenter_sensor ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_DatacenterSensor	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 *
	 * @param string $tag
	 * @return Model_DatacenterSensor
	 */
	static function getByTag($tag) {
		$objects = self::getWhere(sprintf("%s = %s",
			self::TAG,
			C4_ORMHelper::qstr($tag)
		));
		
		if(empty($objects) || !is_array($objects))
			return null;
		
		return array_shift($objects);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_DatacenterSensor[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_DatacenterSensor();
			$object->id = $row['id'];
			$object->tag = $row['tag'];
			$object->name = $row['name'];
			$object->extension_id = $row['extension_id'];
			$object->status = $row['status'];
			$object->updated = $row['updated'];
			$object->fail_count = $row['fail_count'];
			$object->is_disabled = $row['is_disabled'];
			$object->metric = $row['metric'];
			$object->metric_type = $row['metric_type'];
			$object->metric_delta = $row['metric_delta'];
			$object->output = $row['output'];
			
			@$json = json_decode($row['params_json'], true);
			$object->params = !empty($json) ? $json : array();
			
			$objects[$object->id] = $object;
		}
		
		mysql_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM datacenter_sensor WHERE id IN (%s)", $ids_list));
		
		// Fire event
		/*
	    $eventMgr = DevblocksPlatform::getEventService();
	    $eventMgr->trigger(
	        new Model_DevblocksEvent(
	            'context.delete',
                array(
                	'context' => 'cerberusweb.contexts.',
                	'context_ids' => $ids
                )
            )
	    );
	    */
		
		return true;
	}
	
	public static function random() {
		return parent::_getRandom('datacenter_sensor');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_DatacenterSensor::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"datacenter_sensor.id as %s, ".
			"datacenter_sensor.tag as %s, ".
			"datacenter_sensor.name as %s, ".
			"datacenter_sensor.extension_id as %s, ".
			"datacenter_sensor.status as %s, ".
			"datacenter_sensor.updated as %s, ".
			"datacenter_sensor.fail_count as %s, ".
			"datacenter_sensor.is_disabled as %s, ".
			"datacenter_sensor.params_json as %s, ".
			"datacenter_sensor.metric as %s, ".
			"datacenter_sensor.metric_type as %s, ".
			"datacenter_sensor.metric_delta as %s, ".
			"datacenter_sensor.output as %s ",
				SearchFields_DatacenterSensor::ID,
				SearchFields_DatacenterSensor::TAG,
				SearchFields_DatacenterSensor::NAME,
				SearchFields_DatacenterSensor::EXTENSION_ID,
				SearchFields_DatacenterSensor::STATUS,
				SearchFields_DatacenterSensor::UPDATED,
				SearchFields_DatacenterSensor::FAIL_COUNT,
				SearchFields_DatacenterSensor::IS_DISABLED,
				SearchFields_DatacenterSensor::PARAMS_JSON,
				SearchFields_DatacenterSensor::METRIC,
				SearchFields_DatacenterSensor::METRIC_TYPE,
				SearchFields_DatacenterSensor::METRIC_DELTA,
				SearchFields_DatacenterSensor::OUTPUT
			);
			
		$join_sql = "FROM datacenter_sensor ".
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.datacenter.sensor' AND context_link.to_context_id = datacenter_sensor.id) " : " ").
			(isset($tables['ftcc']) ? "INNER JOIN comment ON (comment.context = 'cerberusweb.contexts.datacenter.sensor' AND comment.context_id = datacenter_sensor.id) " : " ").
			(isset($tables['ftcc']) ? "INNER JOIN fulltext_comment_content ftcc ON (ftcc.id=comment.id) " : " ")
			;
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'datacenter_sensor.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_DatacenterSensor', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'datacenter_sensor',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$from_context = 'cerberusweb.contexts.datacenter.sensor';
		$from_index = 'datacenter_sensor.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		switch($param_key) {
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
			
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		}
		
	}
	
    /**
     * Enter description here...
     *
     * @param array $columns
     * @param DevblocksSearchCriteria[] $params
     * @param integer $limit
     * @param integer $page
     * @param string $sortBy
     * @param boolean $sortAsc
     * @param boolean $withCounts
     * @return array
     */
    static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY datacenter_sensor.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
    		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
		    $rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
            $total = mysql_num_rows($rs);
		}
		
		$results = array();
		$total = -1;
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($row[SearchFields_DatacenterSensor::ID]);
			$results[$object_id] = $result;
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql =
				($has_multiple_values ? "SELECT COUNT(DISTINCT datacenter_sensor.id) " : "SELECT COUNT(datacenter_sensor.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_DatacenterSensor implements IDevblocksSearchFields {
	const ID = 'p_id';
	const TAG = 'p_tag';
	const NAME = 'p_name';
	const EXTENSION_ID = 'p_extension_id';
	const STATUS = 'p_status';
	const UPDATED = 'p_updated';
	const FAIL_COUNT = 'p_fail_count';
	const IS_DISABLED = 'p_is_disabled';
	const PARAMS_JSON = 'p_params_json';
	const METRIC = 'p_metric';
	const METRIC_TYPE = 'p_metric_type';
	const METRIC_DELTA = 'p_metric_delta';
	const OUTPUT = 'p_output';
	
	// Comment Content
	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';

	// Context links
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_WATCHERS = '*_workers';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'datacenter_sensor', 'id', $translate->_('common.id')),
			self::TAG => new DevblocksSearchField(self::TAG, 'datacenter_sensor', 'tag', $translate->_('common.tag'), Model_CustomField::TYPE_SINGLE_LINE),
			self::NAME => new DevblocksSearchField(self::NAME, 'datacenter_sensor', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE),
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'datacenter_sensor', 'extension_id', $translate->_('dao.datacenter_sensor.extension_id')),
			self::STATUS => new DevblocksSearchField(self::STATUS, 'datacenter_sensor', 'status', $translate->_('common.status')),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'datacenter_sensor', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE),
			self::FAIL_COUNT => new DevblocksSearchField(self::FAIL_COUNT, 'datacenter_sensor', 'fail_count', $translate->_('dao.datacenter_sensor.fail_count'), Model_CustomField::TYPE_NUMBER),
			self::IS_DISABLED => new DevblocksSearchField(self::IS_DISABLED, 'datacenter_sensor', 'is_disabled', $translate->_('dao.datacenter_sensor.is_disabled'), Model_CustomField::TYPE_CHECKBOX),
			self::PARAMS_JSON => new DevblocksSearchField(self::PARAMS_JSON, 'datacenter_sensor', 'params_json', null),
			self::METRIC => new DevblocksSearchField(self::METRIC, 'datacenter_sensor', 'metric', $translate->_('dao.datacenter_sensor.metric'), Model_CustomField::TYPE_NUMBER),
			self::METRIC_TYPE => new DevblocksSearchField(self::METRIC_TYPE, 'datacenter_sensor', 'metric_type', $translate->_('dao.datacenter_sensor.metric_type')),
			self::METRIC_DELTA => new DevblocksSearchField(self::METRIC_DELTA, 'datacenter_sensor', 'metric_delta', $translate->_('dao.datacenter_sensor.metric_delta'), Model_CustomField::TYPE_NUMBER),
			self::OUTPUT => new DevblocksSearchField(self::OUTPUT, 'datacenter_sensor', 'output', $translate->_('dao.datacenter_sensor.output'), Model_CustomField::TYPE_SINGLE_LINE),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', '*_workers', $translate->_('common.watchers'), 'WS'),
		);
		
		$tables = DevblocksPlatform::getDatabaseTables();
		if(isset($tables['fulltext_comment_content'])) {
			$columns[self::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT');
		}
		
		// Custom Fields
		$fields = DAO_CustomField::getByContext('cerberusweb.contexts.datacenter.sensor');

		if(is_array($fields))
		foreach($fields as $field_id => $field) {
			$key = 'cf_'.$field_id;
			$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name,$field->type);
		}
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_DatacenterSensor {
	public $id;
	public $tag;
	public $name;
	public $extension_id;
	public $status;
	public $updated;
	public $fail_count;
	public $is_disabled;
	public $params = array();
	public $metric;
	public $metric_type;
	public $metric_delta;
	public $output;
	
	function run() {
		$pass = false;
		$fields = array();
		
		if(null != ($ext = DevblocksPlatform::getExtension($this->extension_id, true))) {
			$pass = $ext->run($this->params, $fields);
			
		} else {
			$fields = array(
				DAO_DatacenterSensor::STATUS => 'C',
				DAO_DatacenterSensor::METRIC => "Can't find sensor type.",
				DAO_DatacenterSensor::OUTPUT => "Can't find sensor type.",
			);
		}
		
		$fields[DAO_DatacenterSensor::UPDATED] = time();
		DAO_DatacenterSensor::update($this->id, $fields);
		
		return $pass;
	}
};

class View_DatacenterSensor extends C4_AbstractView implements IAbstractView_Subtotals {
	const DEFAULT_ID = 'datacenter_sensor';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('datacenter.sensors.common.sensors');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_DatacenterSensor::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_DatacenterSensor::STATUS,
			SearchFields_DatacenterSensor::NAME,
			SearchFields_DatacenterSensor::UPDATED,
			SearchFields_DatacenterSensor::METRIC_DELTA,
			SearchFields_DatacenterSensor::OUTPUT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_DatacenterSensor::CONTEXT_LINK,
			SearchFields_DatacenterSensor::CONTEXT_LINK_ID,
			SearchFields_DatacenterSensor::ID,
			SearchFields_DatacenterSensor::PARAMS_JSON,
		));
		
		$this->addParamsHidden(array(
			SearchFields_DatacenterSensor::CONTEXT_LINK,
			SearchFields_DatacenterSensor::CONTEXT_LINK_ID,
			SearchFields_DatacenterSensor::ID,
			SearchFields_DatacenterSensor::PARAMS_JSON,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_DatacenterSensor::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_DatacenterSensor', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_DatacenterSensor', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable();
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Strings
				case SearchFields_DatacenterSensor::EXTENSION_ID:
				case SearchFields_DatacenterSensor::IS_DISABLED:
				case SearchFields_DatacenterSensor::STATUS:
					$pass = true;
					break;
					
				case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_DatacenterSensor::EXTENSION_ID:
				$label_map = array();
				$manifests = DevblocksPlatform::getExtensions('cerberusweb.datacenter.sensor', false);
				if(is_array($manifests))
				foreach($manifests as $k => $mft) {
					$label_map[$k] = $mft->name;
				}
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_DatacenterSensor', $column, $label_map);
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				$label_map = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_DatacenterSensor', $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_DatacenterSensor', $column);
				break;
			
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_DatacenterSensor', 'cerberusweb.contexts.datacenter.sensor', $column);
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_DatacenterSensor', $column);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_DatacenterSensor', $column, 'datacenter_sensor.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.datacenter.sensor');
		$tpl->assign('custom_fields', $custom_fields);
		
		// Sensors
		$sensor_manifests = Extension_Sensor::getAll(false);
		$tpl->assign('sensor_manifests', $sensor_manifests);

		$tpl->assign('view_template', 'devblocks:cerberusweb.datacenter.sensors::sensors/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
			
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}
	
	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_DatacenterSensor::EXTENSION_ID:
			case SearchFields_DatacenterSensor::METRIC:
			case SearchFields_DatacenterSensor::METRIC_TYPE:
			case SearchFields_DatacenterSensor::NAME:
			case SearchFields_DatacenterSensor::OUTPUT:
			case SearchFields_DatacenterSensor::TAG:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_DatacenterSensor::ID:
			case SearchFields_DatacenterSensor::FAIL_COUNT:
			case SearchFields_DatacenterSensor::METRIC_DELTA:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_DatacenterSensor::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				$options = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_DatacenterSensor::IS_DISABLED:
				parent::_renderCriteriaParamBoolean($param);
				break;
			
			case SearchFields_DatacenterSensor::STATUS:
				$options = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$output = array();
				
				foreach($values as $v) {
					$output[] = $options[$v];
				}
				
				echo implode(' or ', $output);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_DatacenterSensor::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_DatacenterSensor::EXTENSION_ID:
			case SearchFields_DatacenterSensor::METRIC:
			case SearchFields_DatacenterSensor::METRIC_TYPE:
			case SearchFields_DatacenterSensor::NAME:
			case SearchFields_DatacenterSensor::OUTPUT:
			case SearchFields_DatacenterSensor::TAG:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_DatacenterSensor::FAIL_COUNT:
			case SearchFields_DatacenterSensor::ID:
			case SearchFields_DatacenterSensor::METRIC_DELTA:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_DatacenterSensor::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
	
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_DatacenterSensor::EXAMPLE] = 'some value';
					break;
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_DatacenterSensor::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_DatacenterSensor::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			DAO_DatacenterSensor::update($batch_ids, $change_fields);

			// Custom Fields
			self::_doBulkSetCustomFields('cerberusweb.contexts.datacenter.sensor', $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Sensor extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = 'cerberusweb.contexts.datacenter.sensor';
	
	function getRandom() {
		return DAO_DatacenterSensor::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=sensor&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$model = DAO_DatacenterSensor::get($context_id);
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($model->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $model->id,
			'name' => $model->name,
			'permalink' => $url,
		);
	}
	
	function getContext($object, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Sensor:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(self::ID);

		// Polymorph
		if(is_numeric($object)) {
			$object = DAO_DatacenterSensor::get($object);
		} elseif($object instanceof Model_DatacenterSensor) {
			// It's what we want already.
		} else {
			$object = null;
		}
		
		// Token labels
		$token_labels = array(
			'id' => $prefix.$translate->_('common.id'),
			'tag' => $prefix.$translate->_('common.tag'),
			'metric' => $prefix.$translate->_('dao.datacenter_sensor.metric'),
			'metric_type' => $prefix.$translate->_('dao.datacenter_sensor.metric_type'),
			'metric_delta' => $prefix.$translate->_('dao.datacenter_sensor.metric_delta'),
			'name' => $prefix.$translate->_('common.name'),
			'output' => $prefix.$translate->_('dao.datacenter_sensor.output'),
			'status' => $prefix.$translate->_('common.status'),
			'updated|date' => $prefix.$translate->_('common.updated'),
			//'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		if(is_array($fields))
		foreach($fields as $cf_id => $field) {
			$token_labels['custom_'.$cf_id] = $prefix.$field->name;
		}

		// Token values
		$token_values = array();
		
		$token_values['_context'] = self::ID;
		
		if($object) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $object->name;
			
			$status_options = array(
				'O' => 'OK',
				'W' => 'Warning',
				'C' => 'Critical',
			);
			
			$token_values['id'] = $object->id;
			$token_values['tag'] = $object->tag;
			$token_values['metric'] = $object->metric;
			$token_values['metric_type'] = $object->metric_type;
			$token_values['metric_delta'] = $object->metric_delta;
			$token_values['name'] = $object->name;
			$token_values['output'] = $object->output;
			$token_values['status'] = isset($status_options[$object->status]) ? $status_options[$object->status] : '';
			$token_values['updated'] = $object->updated;
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			//$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=example.object&id=%d-%s",$object->id, DevblocksPlatform::strToPermalink($object->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = Context_Sensor::ID;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->view_columns = array(
			SearchFields_DatacenterSensor::NAME,
			SearchFields_DatacenterSensor::STATUS,
			SearchFields_DatacenterSensor::OUTPUT,
			SearchFields_DatacenterSensor::METRIC_DELTA,
			SearchFields_DatacenterSensor::UPDATED,
		);
		$view->addParams(array(
		), true);
		$view->renderSortBy = SearchFields_DatacenterSensor::UPDATED;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		$view->renderFilters = false;
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array()) {
		$view_id = str_replace('.','_',$this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_DatacenterSensor::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_DatacenterSensor::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$id = $context_id; // [TODO] Cleanup
		
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
		
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/peek.tpl');
	}
};