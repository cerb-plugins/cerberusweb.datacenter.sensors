<?php
class DAO_DatacenterSensor extends C4_ORMHelper {
	const ID = 'id';
	const TAG = 'tag';
	const NAME = 'name';
	const EXTENSION_ID = 'extension_id';
	const SERVER_ID = 'server_id';
	const STATUS = 'status';
	const UPDATED = 'updated';
	const FAIL_COUNT = 'fail_count';
	const IS_DISABLED = 'is_disabled';
	const PARAMS_JSON = 'params_json';
	const METRIC = 'metric';
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
		parent::_update($ids, 'datacenter_sensor', $fields);
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
		$sql = "SELECT id, tag, name, extension_id, server_id, status, updated, fail_count, is_disabled, params_json, metric, output ".
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
	
	static function getByTag($tag) {
		$objects = self::getWhere(sprintf("%s = %s",
			self::TAG,
			C4_ORMHelper::qstr($tag)
		));
		
		if(empty($objects))
			return null;
		
		return key($objects);
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
			$object->server_id = $row['server_id'];
			$object->status = $row['status'];
			$object->updated = $row['updated'];
			$object->fail_count = $row['fail_count'];
			$object->is_disabled = $row['is_disabled'];
			$object->metric = $row['metric'];
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
			"datacenter_sensor.server_id as %s, ".
			"datacenter_sensor.status as %s, ".
			"datacenter_sensor.updated as %s, ".
			"datacenter_sensor.fail_count as %s, ".
			"datacenter_sensor.is_disabled as %s, ".
			"datacenter_sensor.params_json as %s, ".
			"datacenter_sensor.metric as %s, ".
			"datacenter_sensor.output as %s ",
				SearchFields_DatacenterSensor::ID,
				SearchFields_DatacenterSensor::TAG,
				SearchFields_DatacenterSensor::NAME,
				SearchFields_DatacenterSensor::EXTENSION_ID,
				SearchFields_DatacenterSensor::SERVER_ID,
				SearchFields_DatacenterSensor::STATUS,
				SearchFields_DatacenterSensor::UPDATED,
				SearchFields_DatacenterSensor::FAIL_COUNT,
				SearchFields_DatacenterSensor::IS_DISABLED,
				SearchFields_DatacenterSensor::PARAMS_JSON,
				SearchFields_DatacenterSensor::METRIC,
				SearchFields_DatacenterSensor::OUTPUT
			);
			
		$join_sql = "FROM datacenter_sensor ";
		
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
	
		return array(
			'primary_table' => 'datacenter_sensor',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
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
	const SERVER_ID = 'p_server_id';
	const STATUS = 'p_status';
	const UPDATED = 'p_updated';
	const FAIL_COUNT = 'p_fail_count';
	const IS_DISABLED = 'p_is_disabled';
	const PARAMS_JSON = 'p_params_json';
	const METRIC = 'p_metric';
	const OUTPUT = 'p_output';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'datacenter_sensor', 'id', $translate->_('common.id')),
			self::TAG => new DevblocksSearchField(self::TAG, 'datacenter_sensor', 'tag', $translate->_('common.tag')),
			self::NAME => new DevblocksSearchField(self::NAME, 'datacenter_sensor', 'name', $translate->_('common.name')),
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'datacenter_sensor', 'extension_id', $translate->_('dao.datacenter_sensor.extension_id')),
			self::SERVER_ID => new DevblocksSearchField(self::SERVER_ID, 'datacenter_sensor', 'server_id', $translate->_('cerberusweb.datacenter.common.server')),
			self::STATUS => new DevblocksSearchField(self::STATUS, 'datacenter_sensor', 'status', $translate->_('common.status')),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'datacenter_sensor', 'updated', $translate->_('common.updated')),
			self::FAIL_COUNT => new DevblocksSearchField(self::FAIL_COUNT, 'datacenter_sensor', 'fail_count', $translate->_('dao.datacenter_sensor.fail_count')),
			self::IS_DISABLED => new DevblocksSearchField(self::IS_DISABLED, 'datacenter_sensor', 'is_disabled', $translate->_('dao.datacenter_sensor.is_disabled')),
			self::PARAMS_JSON => new DevblocksSearchField(self::PARAMS_JSON, 'datacenter_sensor', 'params_json', null),
			self::METRIC => new DevblocksSearchField(self::METRIC, 'datacenter_sensor', 'metric', $translate->_('dao.datacenter_sensor.metric')),
			self::OUTPUT => new DevblocksSearchField(self::OUTPUT, 'datacenter_sensor', 'output', $translate->_('dao.datacenter_sensor.output')),
		);
		
		// Custom Fields
		$fields = DAO_CustomField::getByContext('cerberusweb.contexts.sensor');

		if(is_array($fields))
		foreach($fields as $field_id => $field) {
			$key = 'cf_'.$field_id;
			$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name);
		}
		
		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;		
	}
};

class Model_DatacenterSensor {
	public $id;
	public $tag;
	public $name;
	public $extension_id;
	public $server_id;
	public $status;
	public $updated;
	public $fail_count;
	public $is_disabled;
	public $params = array();
	public $metric;
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
			SearchFields_DatacenterSensor::NAME,
			SearchFields_DatacenterSensor::STATUS,
			SearchFields_DatacenterSensor::UPDATED,
			SearchFields_DatacenterSensor::OUTPUT,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_DatacenterSensor::PARAMS_JSON,
		));
		
		$this->addParamsHidden(array(
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
				case SearchFields_DatacenterSensor::SERVER_ID:
				case SearchFields_DatacenterSensor::STATUS:
					$pass = true;
					break;
					
// 				case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
// 					$pass = true;
// 					break;
					
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
				
			case SearchFields_DatacenterSensor::SERVER_ID:
				$label_map = array();
				$servers = DAO_Server::getAll();
				if(is_array($servers))
				foreach($servers as $server)
					$label_map[$server->id] = $server->name;
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_DatacenterSensor', $column, $label_map);
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				$label_map = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$counts = $this->_getSubtotalCountForStringColumn('DAO_DatacenterSensor', $column, $label_map);
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_DatacenterSensor', $column);
				break;
			
// 			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
// 				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_DatacenterSensor', $column);
// 				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_DatacenterSensor', $column, 'p.id');
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
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.sensor');
		$tpl->assign('custom_fields', $custom_fields);
		
		// Servers
		$servers = DAO_Server::getAll();
		$tpl->assign('servers', $servers);

		// Sensors
		$sensor_manifests = Extension_Sensor::getAll(false);
		$tpl->assign('sensor_manifests', $sensor_manifests);

		$tpl->assign('view_template', 'devblocks:cerberusweb.datacenter.sensors::sensors/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_DatacenterSensor::EXTENSION_ID:
			case SearchFields_DatacenterSensor::METRIC:
			case SearchFields_DatacenterSensor::NAME:
			case SearchFields_DatacenterSensor::OUTPUT:
			case SearchFields_DatacenterSensor::TAG:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_DatacenterSensor::ID:
			case SearchFields_DatacenterSensor::FAIL_COUNT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_DatacenterSensor::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_DatacenterSensor::SERVER_ID:
				$options = array();
				
				$servers = DAO_Server::getAll();
				foreach($servers as $server)
					$options[$server->id] = $server->name;
				
				$field = new stdClass();
				$field->options = $options;
				$tpl->assign('field', $field);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__cfield_picklist.tpl');
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				$options = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$field = new stdClass();
				$field->options = $options;
				$tpl->assign('field', $field);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__cfield_picklist.tpl');
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
			case SearchFields_DatacenterSensor::NAME:
			case SearchFields_DatacenterSensor::OUTPUT:
			case SearchFields_DatacenterSensor::TAG:
				// force wildcards if none used on a LIKE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
				
			case SearchFields_DatacenterSensor::FAIL_COUNT:
			case SearchFields_DatacenterSensor::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_DatacenterSensor::UPDATED:
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');

				if(empty($from)) $from = 0;
				if(empty($to)) $to = 'today';

				$criteria = new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
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
			self::_doBulkSetCustomFields('cerberusweb.contexts.sensor', $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}			
};

class Context_Sensor extends Extension_DevblocksContext {
	const ID = 'cerberusweb.contexts.sensor';
	
	function getRandom() {
		return DAO_DatacenterSensor::random();
	}
	
	function getMeta($context_id) {
		$model = DAO_DatacenterSensor::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$friendly = DevblocksPlatform::strToPermalink($model->name);
		
		return array(
			'id' => $model->id,
			'name' => $model->name,
			'permalink' => '', //$url_writer->writeNoProxy(sprintf("c=example.objects&action=profile&id=%d",$context_id), true),
		);
	}
	
	function getContext($object, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Sensor:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext('cerberusweb.contexts.sensor');

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
		
		if($object) {
			$token_values['id'] = $object->id;
			$token_values['tag'] = $object->tag;
			$token_values['metric'] = $object->metric;
			$token_values['name'] = $object->name;
			$token_values['output'] = $object->output;
			$token_values['status'] = $object->status;
			$token_values['updated'] = $object->updated;
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			//$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=example.object&id=%d-%s",$object->id, DevblocksPlatform::strToPermalink($object->name)), true);
			
			$token_values['custom'] = array();
			
			$field_values = array_shift(DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.sensor', $object->id));
			if(is_array($field_values) && !empty($field_values)) {
				foreach($field_values as $cf_id => $cf_val) {
					if(!isset($fields[$cf_id]))
						continue;
					
					// The literal value
					if(null != $object)
						$token_values['custom'][$cf_id] = $cf_val;
					
					// Stringify
					if(is_array($cf_val))
						$cf_val = implode(', ', $cf_val);
						
					if(is_string($cf_val)) {
						if(null != $object)
							$token_values['custom_'.$cf_id] = $cf_val;
					}
				}
			}
		}

		// Example link
		// [TODO] Use the following code if you want to link to another context
//		@$assignee_id = $object->worker_id;
//		$merge_token_labels = array();
//		$merge_token_values = array();
//		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $assignee_id, $merge_token_labels, $merge_token_values, '', true);
//
//		CerberusContexts::merge(
//			'assignee_',
//			'Assignee:',
//			$merge_token_labels,
//			$merge_token_values,
//			$token_labels,
//			$token_values
//		);			
		
		return true;
	}

	function getChooserView() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		// View
		$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->view_columns = array(
			SearchFields_DatacenterSensor::NAME,
			SearchFields_DatacenterSensor::STATUS,
			SearchFields_DatacenterSensor::OUTPUT,
			SearchFields_DatacenterSensor::UPDATED,
		);
		$view->addParams(array(
		), true);
		$view->renderSortBy = SearchFields_DatacenterSensor::UPDATED;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
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
};