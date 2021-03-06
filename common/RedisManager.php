<?php
namespace Fichat\Common;

use Fichat\Models\AssociationLevel;
use Fichat\Models\RewardTask;
use Fichat\Models\UserAttr;
use Fichat\Utils\RedisClient;
use Fichat\Utils\Utils;
use Phalcon\Cache\Backend\Redis;
use Phalcon\Di;

define('RANK_TYPE_REDPACK', 1);         // 红包排行
define('RANK_TYPE_ASSOCIATION', 2);     // 家族排行榜
define('RANK_TYPE_USERLEV', 3);         // 用户等级庞航


class RedisManager
{

    /** Rank About Functions *****************************************/

    /**
     * 获取排行时间
     *
     */
    public static function getRankInfo($redis)
    {
        $data = $redis->hgetall("rank_info");
        $signTs = Utils::rankSignTS();
        // 检查红包排行
        if (!$data['redpack_rank']) {
            $data['userlevel_rank'] = $signTs;
        }
        // 检查用户等级排行
        if (!$data['userlevel_rank'])
        {
            $data['userlevel_rank'] = $signTs;
        }
    }

    /**
     * 检查排行
     *
     */
    public static function checkRank($redis)
    {
        $signTs = Utils::rankSignTS();
        $rankInfoKey = RedisClient::rankInfoKey();
        $redpackRankKey = RedisClient::redpackRankKey();
        $userLevRankKey = RedisClient::userLevRankKey();
        $redis->eval(CHECK_RANK, [$rankInfoKey, $signTs, $redpackRankKey, $userLevRankKey], 1);
    }

    /**
     * 推入检查
     *
     */
    public static function pushRank($redis, $rankKey, $userID, $score)
    {
        Self::checkRank($redis);
        $redis->zadd($rankKey, $score, $userID);
    }
    

    public static function getRank(\Redis $redis, $type)
    {
        // 检查排行
        $data = array();
        Self::checkRank($redis);
        switch ($type)
        {
            case RANK_TYPE_REDPACK:
            	$key = $rankKey = RedisClient::weekActiveKey();
                break;
            case RANK_TYPE_ASSOCIATION:
                $key = RedisClient::assoicLevRankKey();
                break;
            case RANK_TYPE_USERLEV:
                $key = RedisClient::userLevRankKey();
                break;

        }
        // 检查Key是否存在
        if ($key) {
            $data = $redis->zRevRange($key, 0, 19, true);
            if($type == RANK_TYPE_ASSOCIATION) {
                $data = Utils::sortByKeyAndSameValue($data);
            }
        }
        // 返回数据
        return $data;
    }
	
    /**
     * 更新周用户数据
     * @params opType: 1#添加, 0#减少
     */
	public static function pushWeek(\Redis $redis, $key, $uid, $opType, $opValue)
	{
		$count = $redis->zScore($key, $uid);
		if ($opType == 1) {
			// 添加
			$count += $opValue;
		} else {
			// 减少
			$count -= $opValue;
		}
		$redis->zadd($key, $count, $uid);
	}
	
	public static function pushWeekActive(\Redis $redis, $uid, $amount)
	{
		$key = RedisClient::weekActiveKey();
		$redis->eval(UPDATE_WEEK_ACTIVE, [$key, 99999999999 - $uid, $amount], 1);
	}
	
	/**
	 * 更新日(超级)数据
	 *
	 */
	public static function pushDayBig(\Redis $redis, $key, $id, $value)
	{
		$sendMsg = true;
//		$sendMsg = false;
		// 获取当前Key的最大值
//		$maxReward = $redis->zRevRange($key, 0, 0);
//		if ($maxReward) {
//			$maxId = $maxReward[0];
//			$maxValue = $redis->zScore($key, $maxId);
//			// 如果新值比老值大, 则发送消息
//			if ($value > $maxValue) {
//				$sendMsg = true;
//			}
//		} else {
//			$sendMsg = true;
//		}
		// 推入新的超级数据(红包/悬赏)
		$redis->zAdd($key, $value, $id);
		// 返回是否发送消息
		return $sendMsg;
	}
	
	/**
	 * 获取用户活跃度
	 *
	 */
	public static function getUserActivity(\Redis $redis, $uid)
	{
		$redpackValue = $redis->zScore(RedisClient::weekRedPacketKey(), $uid);
		if (!$redpackValue) { $redpackValue = 0; }
		
		
		$fansValue = $redis->zScore(RedisClient::weekFansKey(), $uid);
		if (!$redpackValue) { $fansValue = 0; }
		
		
		$friendValue = $redis->zScore(RedisClient::weekFriendKey(), $uid);
		if (!$friendValue) { $redpackValue = 0; }
		
		$redpackValue = $redpackValue * 100;
		$fansValue = (int)$fansValue * 100;
		$friendValue = (int)$friendValue * 200;
		
		return $redpackValue + $fansValue + $friendValue;
	}
	

    public static function userAttrKey()
    {
        return "user_attr";
    }

    public static function getUserAttr(\Redis $redis)
    {
        $key = RedisClient::userAttrKey();
        $userAttr = $redis->zRange($key, 0, -1, true);
        if ($userAttr) {
            return $userAttr;
        } else {
            // 获取数据库中的UserAttr
            $mysqlUserAttr = UserAttr::find()->toArray();
            // 获取基础数据
            // -- 服务器PHP版本5.5.9无法使用...$args, 后面升级到5.6以上可以修改此部分逻辑
            foreach($mysqlUserAttr as $value)
            {
                $userAttr[$value['level']] = $value['exp'];
                $redis->zAdd($key, $value['exp'], $value['level']);
            }
        }
        return $userAttr;
    }

    public static function getAssociationLevel(\Redis $redis)
    {
        $key = RedisClient::associationKey();
        $assocLevel = $redis->zRange($key, 0, -1, true);
        if ($assocLevel) {
            return $assocLevel;
        } else {
            // 获取数据库中的UserAttr
            $mysqlAssociationLevel = AssociationLevel::find()->toArray();
            // 获取基础数据
            // -- 服务器PHP版本5.5.9无法使用...$args, 后面升级到5.6以上可以修改此部分逻辑
            foreach($mysqlAssociationLevel as $value)
            {
                $assocLevel[$value['level']] = $value['exp'];
                $redis->zAdd($key, $value['exp'], $value['level']);
            }
        }
        return $assocLevel;
    }

    /** RedPack About Functions *****************************************/

    /**
     * 创建红包数据
     *
     */
    public static function createCacheRedPack($redis, $userId, $redPackId, $data)
    {
        // 创建Key
        $key = RedisClient::redpack_key($redPackId);
        // 数据存储到redis中
        $redis->hMset($key, $data);
        // 设定时间
	    $cacheKeepTime = 86401;
	    // $cacheKeepTime = 11;
        $redis->expire($key, $cacheKeepTime);
        // 推送数据到排行榜中
        Self::pushRedPackInRank($redis, $userId, $data['amount']);
    }
    
    /**
     * 存储红包分配的金额
     *
     */
    public static function saveRedPackDistAmount($redis, $key, $redpackDistAmountList)
    {
    	// 返回抢到的金额
	    return $redis->eval(SAVE_REDPACK_DIST_AMOUNT, [$key, json_encode($redpackDistAmountList)], 1);
    }
    
    /**
     * 获取抢红包的权限
     *
     */
    public static function getGrabRedpackPerm($redis, $redPackId, $redPackNum, $uid)
    {
        $key = RedisClient::grab_redpack_key($redPackId);
        $lastGrabPerm = $redis->eval(GET_GRAB_REDPACK_PERM, [$key, $uid, $redPackNum], 1);
        if ($lastGrabPerm === false) {
            return false;
        } else {
            return $lastGrabPerm;
        }
    }
    
    /**
     * 更新红包数据(余额, 状态)
     *
     */
    public static function updateRedpack($redis, $redpackId, $grabAmount)
    {
    	$key = RedisClient::redpack_key($redpackId);
    	$result = $redis->eval(UPDATE_REDPACK, [$key, $grabAmount], 1);
    	if (is_bool($result)) {
    		return false;
	    } else {
    	    $result = json_decode($result, true);
    	    $result['balance'] = round((float)$result['balance'], 2);
    	    $result['status'] = (int)$result['status'];
    	    return $result;
	    }
    }
    
    /**
     * 抢红包
     *
     */
    public static function grabRedpack($redis, $redpackId, $redPackNum)
    {
    	// 红包金额Key
        $key = RedisClient::redpack_dist_key($redpackId);
	    // 执行结果
	    return round($redis->eval(GRAB_REDPACK, [$key, $redPackNum], 2), 2);
    }

    /**
     * 推送红包金额到排行榜
     * @params $redis:  Redis对像
     * @params $userId: 用户ID
     * @params $amount: 数量
     * @params $op:     操作类型, 1#增加, 2#减少
     */
    public static function pushRedPackInRank(\Redis $redis, $userId, $amount, $op = 1)
    {
        $key = RedisClient::redpackRankKey();
        // 获取本周玩家的消费数量
        $weekAmount = $redis->zScore($key, $userId);
        $weekAmount = $weekAmount ? $weekAmount : 0;
        if ($op == 1) {
            $weekAmount += $amount;
        } else {
            $weekAmount -= $amount;
            $weekAmount == 0 ? 0 : $weekAmount;
        }
        RedisManager::pushRank($redis, $key, $userId, $weekAmount);
    }

    /**
     * 将群组的等级经验推入到排行中
     * @params redis:   Redis对象
     * @params groupId: 群组ID(环信, 见association表中的group_id)
     * @params level:   家族的等级
     * @params exp:     家族的经验
     *
     */
    public static function pushAssocLevToRank($redis, $groupId, $level, $exp)
    {

        $assocLevels = Self::getAssociationLevel($redis);
        // 获取总的经验值
        $sumExp = 0;
        foreach ($assocLevels as $lev => $level_exp)
        {
            if ($level == $lev){
                break;
            }
            $sumExp += $level_exp;
        }
        $sumExp += $exp;
        // 将玩家的等级经验推送到玩家的等级榜中
        RedisManager::pushRank($redis, RedisClient::assoicLevRankKey(), $groupId, $sumExp);
    }

    /**
     * 将玩家的等级经验推入到排行中
     *
     */
    public static function pushLevExpToRank($redis, $userId, $level, $exp)
    {
        $userAttr = RedisManager::getUserAttr($redis);
        // 获取总的经验值
        $sumExp = 0;
        foreach ($userAttr as $lev => $level_exp)
        {
            if ($level == $lev){
                break;
            }
            $sumExp += $level_exp;
        }
        $sumExp += $exp;
        // 将玩家的等级经验推送到玩家的等级榜中
        RedisManager::pushRank($redis, RedisClient::userLevRankKey(), 99999999999 - $userId, $sumExp);
    }
	
	/** Reward Task About Functions *****************************************/

	/**
	 * 获取用户所有任务
	 *
	 */
	public static function getUserRewardTasks(\Redis $redis, $groupId)
	{
		// 构建Key
		$key = RedisClient::userRewardTaskKey($groupId);
		$rewardTasks= $redis->hGetAll($key);
		// UserRewardTask Exist Ever
		if ($rewardTasks) {         // Exist
			// 反序列化ids
			$tasks_ids = unserialize($rewardTasks['ids']);
			// 获取加载了的和未加载的Key
			$rewardTaskKeys = RedisClient::clipLoadKeys($redis, $groupId, array_keys($tasks_ids));
			// 获取所有已经加载了的Key的数据
			$inCacheTasks = RedisClient::mHgetAll($redis, array_values($rewardTaskKeys['in_cache']));
			$rewardTaskData1 = array();
			// 将Redis中的数据拿出来组装, 并返回
			foreach ($inCacheTasks as $key => $task) {
				$task['id'] = Self::getIdByRewardKey($key);
				array_push($rewardTaskData1, $task);
			}
			
			// 从MySQL中加载不在Redis中的数据
			$rewardTaskData2 = array();
			if ($rewardTaskKeys['no_cache']) {
				$rewardTaskData2 = DBManager::getRewardTasks($rewardTaskKeys['no_cache']);
				foreach ($rewardTaskData2 as $noCacheTask) {
					// 存储悬赏任务缓存
					Self::saveRewardTask($redis, $noCacheTask);
				}
			}
			// 合并
			return array_merge($rewardTaskData1, $rewardTaskData2);
		} else {                    // Not Exist
			// 获取MySQL中的用户悬赏任务数据
			return Self::createUserRewardTaskDATA($redis, $groupId);
		}
	}
	
	/** 从MySQL中拉取数据返回, 并保存在Redis中 */
	public static function createUserRewardTaskDATA(\Redis $redis, $groupId)
	{
		// 获取MySQL中的用户悬赏任务数据
		$data = DBManager::getUserRewardTasks($groupId);
		$ids = array();
		$tasks_ids = array();
		// 初始化用户所有悬赏任务的数据
		foreach ($data as $rewardTask) {
			// 操作次数
			$id = $rewardTask['id'];
			$opCount = $rewardTask['click_count'] + $rewardTask['share_count'];
			// 存储悬赏任务缓存
			Self::saveRewardTask($redis, $rewardTask);
			$ids[$id] = $opCount;
			array_push($tasks_ids, $id);
		}
		// ID操作索引
		$ids = serialize($ids);
		$up_ts = microtime();
		// MD5值
		$md5 = md5($ids);
		$userTaskKey = RedisClient::userRewardTaskKey($groupId);
		// 存储
		$redis->eval(CHECK_SAVE_USER_REWARD, [$userTaskKey, $ids, $up_ts, $md5], 1);
		// 返回结果
		return $data;
	}
	
	
	/**
	 * 保存悬赏任务到Redis中
	 * 将悬赏任务数据保存到Redis中, 并设置它的过期时间(ExpireTime)
	 * ExpireTime = end_ts + 86400 - time()
	 * @params $redis
	 * @params rewardTask 悬赏任务数组
	 *
	 */
	public static function saveRewardTask(\Redis $redis, $rewardTask)
	{
		$id = $rewardTask['id'];
		$groupId = $rewardTask['group_id'];
		// 悬赏任务Key
		$taskKey = RedisClient::rewardTaskKey($groupId, $id);
		$taskData = $rewardTask;
		// 去除ID和owner_id
		unset($taskData['id']);
//		unset($taskData['owner_id']);
		$duration = $taskData['end_time'] - time();
		if ($duration < 0) { $duration = 0; }
		$expireTime = $duration + 86400;
		// 保存
		$redis->hmset($taskKey, $taskData);
		$redis->expire($taskKey, $expireTime);
		return $taskData;
	}
	
	/**
	 * 根据赏金任务的Key获取它的ID
	 *
	 */
	public static function getIdByRewardKey($key)
	{
		$keyInfo = explode(':', $key);
		return (int)$keyInfo[1];
	}
	
	/**
	 * 创建任务操作记录
	 *
	 */
	public static function createTmpRewardTaskRecord(\Redis $redis, $rewardRecord)
	{
		/**
		 * 创建临时悬赏任务
		 * KEYS[1]: 临时悬赏任务ID维护KEY
		 * ARGV[1]: reward_record_json
		 * ARGV[2]: key_prex
		 * ARGV[3]: expires
		 */
		$prex = RedisClient::rewardTaskRecordTmpPrex();
		$incrKey = RedisClient::rewardTaskRecordTmpIncrKey();
		return $redis->eval(SAVE_TMP_REWARD_RECORD, [$incrKey, json_encode($rewardRecord), $prex, 60], 1);
	}
	
	/**
	 * 更新任务操作
	 * $di, $uid, $taskId, $opType, $opAmount
	 */
	public static function updateOpRewardTask($di, $opStatus, $groupId, $taskId, $opType, $opAmount, $parentId = 0, $comsPercent = 0)
	{
		$key = RedisClient::rewardTaskKey($groupId, $taskId);
		$redis = RedisClient::create($di->get('config')['redis']);
		$parentKey = "";
		if ($parentId) {
			// 获取父任务数据
			$parentRewadTask = RewardTask::findOne($di, $parentId);
			if ($parentRewadTask) {
				// 父任务的Key
				$parentKey = RedisClient::getRewardTaskKeyByID($redis, $parentId);
			}
		}
		// 返回新的余额
		if ($opStatus == 1) {
			$result = $redis->eval(OP_REWARD_TASK, [$key, $parentKey, $opType, ((float)$opAmount) * 100, $comsPercent], 2);
			if ($result == -100) {
				$opStatus = 3;
				$result = $redis->eval(OP_REWARD_TASK_NOREWARD, [$key, $parentKey, $opType], 2);
			}
		} else {
			$result = $redis->eval(OP_REWARD_TASK_NOREWARD, [$key, $parentKey, $opType], 2);
		}
		// 关闭套接字连接
		$redis -> close();
		if ($result) {
			return [
				'opStatus' =>$opStatus,
				'opCount' => (int)$result[0],
				'getAmount' => (float)$result[1] * 0.01
			];
		} else {
			return false;
		}
	}
	
	/**
	 * 更新系统任务操作
	 * $di, $uid, $taskId, $opType, $opAmount
	 */
	public static function updateOpRewardSystemTask($di, $opStatus, $taskId, $opType, $opAmount)
	{
		$key = RedisClient::rewardTaskKey(0, $taskId);
		$redis = RedisClient::create($di->get('config')['redis']);
		// 返回新的余额
		if ($opStatus == 1) {
			$result = $redis->eval(OP_SYS_REWARD_TASK, [$key, $opType, ((float)$opAmount) * 100], 1);
		} else {
			$result = $redis->eval(OP_SYS_REWARD_TASK_NOREWARD, [$key, $opType], 1);
		}
		// 关闭套接字连接
		$redis -> close();
		if ($result && $result != '-100') {
			return [
				'opStatus' =>$opStatus,
				'opCount' => (int)$result[0],
                'getAmount' => (float)$result[1] * 0.01
			];
		} else {
			return false;
		}
	}
	
	/**
	 * 保存任务记录到Redis中
	 *
	 */
	public static function saveRewardTaskRecord($di, $rewardTaskRecord, $expire)
	{
		$data = $rewardTaskRecord->toArray();
		$redis = RedisClient::create($di->get('config')['redis']);
		$key = RedisClient::rewardTaskRecordKey($data['task_id'], $data['uid'], $data['op_type'], $data['id']);
		unset($data['task_id']);
		unset($data['id']);
		$redis->hMset($key, $data);
		$redis->expire($key, $expire);
		$redis->close();
		return $data;
	}
	
}