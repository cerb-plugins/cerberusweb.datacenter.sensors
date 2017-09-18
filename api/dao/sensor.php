<?php
class DAO_DatacenterSensor extends Cerb_ORMHelper {
	const EXTENSION_ID = 'extension_id';
	const FAIL_COUNT = 'fail_count';
	const ID = 'id';
	const IS_DISABLED = 'is_disabled';
	const METRIC = 'metric';
	const METRIC_DELTA = 'metric_delta';
	const METRIC_TYPE = 'metric_type';
	const NAME = 'name';
	const OUTPUT = 'output';
	const PARAMS_JSON = 'params_json';
	const STATUS = 'status';
	const TAG = 'tag';
	const UPDATED = 'updated';
	
	private function __construct() {}

	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		// varchar(255)
		$validation
			->addField(self::EXTENSION_ID)
			->string()
			->setMaxLength(255)
			;
		// tinyint(4)
		$validation
			->addField(self::FAIL_COUNT)
			->uint(1)
			;
		// int(10) unsigned
		$validation
			->addField(self::ID)
			->id()
			->setEditable(false)
			;
		// tinyint(1)
		$validation
			->addField(self::IS_DISABLED)
			->bit()
			;
		// text
		$validation
			->addField(self::METRIC)
			->string()
			->setMaxLength(65535)
			;
		// varchar(64)
		$validation
			->addField(self::METRIC_DELTA)
			->string()
			->setMaxLength(64)
			;
		// varchar(32)
		$validation
			->addField(self::METRIC_TYPE)
			->string()
			->setMaxLength(32)
			;
		// varchar(255)
		$validation
			->addField(self::NAME)
			->string()
			->setMaxLength(255)
			->setRequired(true)
			;
		// text
		$validation
			->addField(self::OUTPUT)
			->string()
			->setMaxLength(65535)
			;
		// text
		$validation
			->addField(self::PARAMS_JSON)
			->string()
			->setMaxLength(65535)
			;
		// char(1)
		$validation
			->addField(self::STATUS)
			->string()
			->setMaxLength(1)
			;
		// varchar(255)
		$validation
			->addField(self::TAG)
			->string()
			->setMaxLength(255)
			->setUnique(get_class())
			;
		// int(10) unsigned
		$validation
			->addField(self::UPDATED)
			->timestamp()
			;

		return $validation->getFields();
	}
	
	static function create($fields) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = "INSERT INTO datacenter_sensor () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;

			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_SENSOR, $batch_ids);
			}

			// Make changes
			parent::_update($batch_ids, 'datacenter_sensor', $fields);
			
			// Send events
			if($check_deltas) {
				// Local events
				self::_processUpdateEvents($batch_ids, $fields);
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.datacenter.sensor.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_SENSOR, $batch_ids);
			}
		}
	}
	
	static function _processUpdateEvents($ids, $change_fields) {
		// We only care about these fields, so abort if they aren't referenced

		$observed_fields = array(
			DAO_DatacenterSensor::METRIC,
			DAO_DatacenterSensor::STATUS,
		);
		
		$used_fields = array_intersect($observed_fields, array_keys($change_fields));
		
		if(empty($used_fields))
			return;
		
		// Load records only if they're needed
		
		if(false == ($before_models = CerberusContexts::getCheckpoints(CerberusContexts::CONTEXT_SENSOR, $ids)))
			return;
		
		if(false == ($models = DAO_DatacenterSensor::getIds($ids)))
			return;
		
		$db = DevblocksPlatform::services()->database();
		
		foreach($models as $id => $model) {
			if(!isset($before_models[$id]))
				continue;
			
			$before_model = (object) $before_models[$id];
			
			// Compute deltas
			
			@$metric = $change_fields[DAO_DatacenterSensor::METRIC];

			if($metric == $before_model->metric)
				unset($change_fields[DAO_DatacenterSensor::METRIC]);
			
			if(isset($change_fields[DAO_DatacenterSensor::METRIC])
				&& in_array($model->metric_type, array('updown','decimal','percent','number'))) {
				
				$delta = 0;
				
				if(!is_array($metric) && isset($metric['from']) && isset($metric['to'])) {
					switch($model->metric_type) {
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
				
				// [TODO] This could be done better in DAO::update() ?
				if($model->metric_delta && $model->metric_delta != $delta) {
					$sql = sprintf("UPDATE datacenter_sensor SET metric_delta = %s WHERE id = %d",
						$db->qstr($delta),
						$model->id
					);
					$db->ExecuteMaster($sql);
				}
			}
			
			// [TODO] Merge with 'Record changed'
			// This can also detect when the status changes OK->PROBLEM or PROBLEM->OK
			
			$statuses = array(
				'O' => 'OK',
				'W' => 'Warning',
				'C' => 'Critical',
			);
			
			@$status = $change_fields[DAO_DatacenterSensor::STATUS];
			
			// If the status changed
			if($status != $model->status) {
				
				/*
				 * Log sensor status (sensor.status.*)
				 */
				$entry = array(
					//{{sensor}} sensor status changed from {{status_from}} to {{status_to}}
					'message' => 'activities.datacenter.sensor.status',
					'variables' => array(
						'sensor' => sprintf("%s", $model->name),
						'status_to' => sprintf("%s", $model->status),
						),
					'urls' => array(
						'sensor' => sprintf("ctx://%s:%d/%s", CerberusContexts::CONTEXT_SENSOR, $model->id, $model->name),
						)
				);
				CerberusContexts::logActivity('datacenter.sensor.status', CerberusContexts::CONTEXT_SENSOR, $model->id, $entry);
			}
		}
		
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
		$db = DevblocksPlatform::services()->database();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, tag, name, extension_id, status, updated, fail_count, is_disabled, params_json, metric, metric_type, metric_delta, output ".
			"FROM datacenter_sensor ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_DatacenterSensor
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
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
			Cerb_ORMHelper::qstr($tag)
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
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
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
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM datacenter_sensor WHERE id IN (%s)", $ids_list));
		
		// Fire event
		/*
		$eventMgr = DevblocksPlatform::services()->event();
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
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_DatacenterSensor', $sortBy);
		
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
			
		$join_sql = "FROM datacenter_sensor ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_DatacenterSensor');
	
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
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
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$from_context = CerberusContexts::CONTEXT_SENSOR;
		$from_index = 'datacenter_sensor.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		switch($param_key) {
			case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		}
		
	}
	
	/**
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
		$db = DevblocksPlatform::services()->database();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_DatacenterSensor::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(datacenter_sensor.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_DatacenterSensor extends DevblocksSearchFields {
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

	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'datacenter_sensor.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_SENSOR => new DevblocksSearchFieldContextKeys('datacenter_sensor.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::FULLTEXT_COMMENT_CONTENT:
				return self::_getWhereSQLFromCommentFulltextField($param, Search_CommentContent::ID, CerberusContexts::CONTEXT_SENSOR, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_SENSOR, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_WATCHERS:
				return self::_getWhereSQLFromWatchersField($param, CerberusContexts::CONTEXT_SENSOR, self::getPrimaryKey());
				break;
				
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
		
		return false;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'datacenter_sensor', 'id', $translate->_('common.id'), null, true),
			self::TAG => new DevblocksSearchField(self::TAG, 'datacenter_sensor', 'tag', $translate->_('common.tag'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'datacenter_sensor', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'datacenter_sensor', 'extension_id', $translate->_('dao.datacenter_sensor.extension_id'), null, true),
			self::STATUS => new DevblocksSearchField(self::STATUS, 'datacenter_sensor', 'status', $translate->_('common.status'), null, true),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'datacenter_sensor', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
			self::FAIL_COUNT => new DevblocksSearchField(self::FAIL_COUNT, 'datacenter_sensor', 'fail_count', $translate->_('dao.datacenter_sensor.fail_count'), Model_CustomField::TYPE_NUMBER, true),
			self::IS_DISABLED => new DevblocksSearchField(self::IS_DISABLED, 'datacenter_sensor', 'is_disabled', $translate->_('dao.datacenter_sensor.is_disabled'), Model_CustomField::TYPE_CHECKBOX, true),
			self::PARAMS_JSON => new DevblocksSearchField(self::PARAMS_JSON, 'datacenter_sensor', 'params_json', null, null, false),
			self::METRIC => new DevblocksSearchField(self::METRIC, 'datacenter_sensor', 'metric', $translate->_('dao.datacenter_sensor.metric'), Model_CustomField::TYPE_NUMBER, true),
			self::METRIC_TYPE => new DevblocksSearchField(self::METRIC_TYPE, 'datacenter_sensor', 'metric_type', $translate->_('dao.datacenter_sensor.metric_type'), null, true),
			self::METRIC_DELTA => new DevblocksSearchField(self::METRIC_DELTA, 'datacenter_sensor', 'metric_delta', $translate->_('dao.datacenter_sensor.metric_delta'), Model_CustomField::TYPE_NUMBER, true),
			self::OUTPUT => new DevblocksSearchField(self::OUTPUT, 'datacenter_sensor', 'output', $translate->_('dao.datacenter_sensor.output'), Model_CustomField::TYPE_SINGLE_LINE, true),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', '*_workers', $translate->_('common.watchers'), 'WS', false),
				
			self::FULLTEXT_COMMENT_CONTENT => new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT', false),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_COMMENT_CONTENT]->ft_schema = Search_CommentContent::ID;
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
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

class View_DatacenterSensor extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
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
			SearchFields_DatacenterSensor::ID,
			SearchFields_DatacenterSensor::PARAMS_JSON,
			SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK,
			SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET,
			SearchFields_DatacenterSensor::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
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
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_DatacenterSensor');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_DatacenterSensor', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_DatacenterSensor', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
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
				case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
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
		$context = CerberusContexts::CONTEXT_SENSOR;

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
				
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map);
				break;
				
			case SearchFields_DatacenterSensor::STATUS:
				$label_map = array(
					'O' => 'OK',
					'W' => 'Warning',
					'C' => 'Critical',
				);
				
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map, 'in', 'options[]');
				break;
				
			case SearchFields_DatacenterSensor::IS_DISABLED:
				$counts = $this->_getSubtotalCountForBooleanColumn($context, $column);
				break;
			
			case SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_DatacenterSensor::getFields();
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'change' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::METRIC_DELTA, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'comments' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::FULLTEXT_COMMENT_CONTENT),
				),
			'fail.count' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_DatacenterSensor::FAIL_COUNT),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_DatacenterSensor::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_SENSOR, 'q' => ''],
					]
				),
			'isDisabled' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_DatacenterSensor::IS_DISABLED),
				),
			'metric' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::METRIC, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'metricType' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::METRIC_TYPE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'output' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::OUTPUT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'status' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_DatacenterSensor::STATUS),
					'examples' => array(
						'OK',
						'WARNING',
						'CRITICAL',
						'o',
						'[w,c]',
						'![o]',
					),
				),
			'tag' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::TAG, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'type' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_DatacenterSensor::EXTENSION_ID, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_DatacenterSensor::UPDATED),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_DatacenterSensor::VIRTUAL_WATCHERS),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links');
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_SENSOR, $fields, null);
		
		// Engine/schema examples: Comments
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['comments']['examples'] = $ft_examples;
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'status':
				$field_key = SearchFields_DatacenterSensor::STATUS;
				$oper = null;
				$patterns = array();
				
				CerbQuickSearchLexer::getOperArrayFromTokens($tokens, $oper, $patterns);
				
				$values = array();
				
				if(is_array($patterns))
				foreach($patterns as $pattern) {
					switch(substr(DevblocksPlatform::strLower($pattern),0,1)) {
						case 'o':
							$values['O'] = true;
							break;
						case 'w':
							$values['W'] = true;
							break;
						case 'c':
							$values['C'] = true;
							break;
					}
				}
				
				return new DevblocksSearchCriteria(
					$field_key,
					$oper,
					array_keys($values)
				);
				break;
		
			case 'watchers':
				return DevblocksSearchCriteria::getWatcherParamFromTokens(SearchFields_Domain::VIRTUAL_WATCHERS, $tokens);
				break;
				
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SENSOR);
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
				
			case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_DatacenterSensor::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}
	
	function renderCriteria($field) {
		$tpl = DevblocksPlatform::services()->template();
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
				
			case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_SENSOR);
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
					$output[] = DevblocksPlatform::strEscapeHtml($options[$v]);
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
				
			case SearchFields_DatacenterSensor::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
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
};

class Context_Sensor extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = CerberusContexts::CONTEXT_SENSOR;
	
	static function isReadableByActor($models, $actor) {
		// Everyone can view
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Everyone can modify
		return CerberusContexts::allowEverything($models);
	}
	
	function getDaoClass() {
		return 'DAO_DatacenterSensor';
	}
	
	function getSearchClass() {
		return 'SearchFields_DatacenterSensor';
	}
	
	function getViewClass() {
		return 'View_DatacenterSensor';
	}
	
	function getRandom() {
		return DAO_DatacenterSensor::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
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
			'updated' => $model->updated,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	function getDefaultProperties() {
		return array(
			'status',
			'output',
			'updated',
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
		} elseif(is_array($object)) {
			$object = Cerb_ORMHelper::recastArrayToModel($object, 'Model_DatacenterSensor');
		} else {
			$object = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'tag' => $prefix.$translate->_('common.tag'),
			'metric' => $prefix.$translate->_('dao.datacenter_sensor.metric'),
			'metric_type' => $prefix.$translate->_('dao.datacenter_sensor.metric_type'),
			'metric_delta' => $prefix.$translate->_('dao.datacenter_sensor.metric_delta'),
			'name' => $prefix.$translate->_('common.name'),
			'output' => $prefix.$translate->_('dao.datacenter_sensor.output'),
			'status' => $prefix.$translate->_('common.status'),
			'updated' => $prefix.$translate->_('common.updated'),
			//'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'tag' => Model_CustomField::TYPE_SINGLE_LINE,
			'metric' => Model_CustomField::TYPE_SINGLE_LINE,
			'metric_type' => Model_CustomField::TYPE_SINGLE_LINE,
			'metric_delta' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'output' => Model_CustomField::TYPE_MULTI_LINE,
			'status' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated' => Model_CustomField::TYPE_DATE,
			//'record_url' => ,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = self::ID;
		$token_values['_types'] = $token_types;
		
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
			$url_writer = DevblocksPlatform::services()->url();
			//$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=example.object&id=%d-%s",$object->id, DevblocksPlatform::strToPermalink($object->name)), true);
		}
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'id' => DAO_DatacenterSensor::ID,
			'metric' => DAO_DatacenterSensor::METRIC,
			'metric_type' => DAO_DatacenterSensor::METRIC_TYPE,
			'metric_delta' => DAO_DatacenterSensor::METRIC_DELTA,
			'name' => DAO_DatacenterSensor::NAME,
			'output' => DAO_DatacenterSensor::OUTPUT,
			'status' => DAO_DatacenterSensor::STATUS,
			'tag' => DAO_DatacenterSensor::TAG,
			'updated' => DAO_DatacenterSensor::UPDATED,
		];
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
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
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
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

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
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_DatacenterSensor::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$id = $context_id; // [TODO] Cleanup
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		if(null != ($model = DAO_DatacenterSensor::get($id))) {
			$tpl->assign('model', $model);
		}
		
		// Custom fields
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_SENSOR, false);
		$tpl->assign('custom_fields', $custom_fields);

		if(!empty($model)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_SENSOR, $model->id);
			if(isset($custom_field_values[$id]))
				$tpl->assign('custom_field_values', $custom_field_values[$id]);
		}
		
		// Sensor extensions
		$sensor_manifests = Extension_Sensor::getAll(false);
		$tpl->assign('sensor_manifests', $sensor_manifests);
		
		$tpl->display('devblocks:cerberusweb.datacenter.sensors::sensors/peek.tpl');
	}
};