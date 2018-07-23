<?php

/**
 * Read services
 */
define('BASE_DIR', dirname(__DIR__));
define('APP_DIR', BASE_DIR . '/daka-mini-api');
define('DOMAIN_NAME', 'http://60.205.1.4:8888');

include APP_DIR . '/config/global_define.php';
include APP_DIR . '/config/config_dev.php';
include APP_DIR . '/lib/aliyun-oss-sdk-2.2.3.phar';

use Fichat\Library\ApiProcessor;

use Phalcon\Mvc\Micro;
use Phalcon\Events\Manager as EventManager;

/** 中间件 */
use Fichat\Middleware\SignMiddleware;
use Fichat\Middleware\ResponseMiddleware;

// 测试事物异常
use Phalcon\Mvc\Model\Transaction\Manager as TcManager;
use Phalcon\Mvc\Model\Transaction\Failed as TcFailed;

// 测试用库引用
use Fichat\Utils;
use Fichat\Utils\SwooleConn;

// 初始化事件管理器
$eventManager = new EventManager();
// 初始化微应用
$app = new Micro();
// 绑定DI到应用
$app->setDI($di);

/** Event PROCESS BEGIN  =================================== */

// 签名 检查
$eventManager->attach('micro', new SignMiddleware());
$app->before(new SignMiddleware());

// 响应 处理
$eventManager->attach('micro', new ResponseMiddleware());
$app->after(new ResponseMiddleware());


// 注册事件管理器
$app->setEventsManager($eventManager);
/** Event PROCESS END    =================================== */


// API接口文件

/*
 * TODO 帐号相关
 */

// 微信登录
$app->post('/_API/_wxLogin', function() use ($di){
	return ApiProcessor::wxLogin($di);
});

// 拉取用户微信信息
$app->post('/_API/_wxUpUserInfo', function() use ($di){
	return ApiProcessor::wxInitUserInfo($di);
});

$app->post('/_API/_upload', function() use ($di){
	return ApiProcessor::uploadFile($di);
});

$app->post('/_API/_checkBalance', function() use ($di){
	return ApiProcessor::checkBalance($di);
});

$app->post('/_API/_publishTask', function() use ($di){
	return ApiProcessor::publishTask($di);
});



$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    return ['error_code'=>'E0404', 'message' => 'not found this page'];
});

$app->handle();