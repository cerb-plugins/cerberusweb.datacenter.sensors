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

class Event_SensorMacro extends AbstractEvent_Sensor {
	const ID = 'event.macro.sensor';
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->_event_id = self::ID;
	}
	
	static function trigger($trigger_id, $sensor_id, $variables=array()) {
		$events = DevblocksPlatform::getEventService();
		$events->trigger(
	        new Model_DevblocksEvent(
	            self::ID,
                array(
                    'sensor_id' => $sensor_id,
                    '_variables' => $variables,
                	'_whisper' => array(
                		'_trigger_id' => array($trigger_id),
                	),
                )
            )
		);
	}
};