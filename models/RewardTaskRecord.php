<?php
namespace Fichat\Models;

use Fichat\Common\DBManager;
use Fichat\Common\RedisManager;
use Fichat\Utils\RedisClient;
use Phalcon\Di;
use Phalcon\Mvc\Model;

class RewardTaskRecord extends Base
{
	public $id;
	public $task_id;
	public $op_type;
	public $join_members = '';
	public $uid;
	public $status;
	public $create_time;
	public $update_time;
	
	public function initialize() {
		parent::initialize();
		
		$this->belongsTo('uid', __NAMESPACE__ . '\User', 'id', array(
			'alias' => 'user'
		));
		$this->belongsTo('task_id', __NAMESPACE__ . '\RewardTask', 'id', array(
			'alias' => 'rewardTask'
		));
	}
	
}