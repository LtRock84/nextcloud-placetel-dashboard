<?php
declare(strict_types=1);
namespace OCP {interface IConfig{} interface IGroupManager{} interface IUserSession{} interface IUserManager{} interface IRequest{}}
namespace Psr\Log {interface LoggerInterface{}}
namespace OCP\AppFramework {class Controller {protected \OCP\IRequest $request;public function __construct($app,$request){$this->request=$request;}}}
namespace OCP\AppFramework\Http {class DataResponse {public function __construct(public mixed $data,public int $status=200){}public function addHeader($key,$value){}}}
namespace OCA\Placetel\Native {
 class Store {}
 class RemoteException extends \Exception {public int $status=500;}
 class Dashboard {public function execute($method,$path,$input,$query,$identity){if($path==='bootstrap')return ['hosting'=>[],'agents'=>[],'queues'=>[],'plans'=>[],'assignments'=>[],'overrides'=>[],'audit'=>[['secret'=>'audit']],'queueSnapshot'=>['secret'=>'waiting'],'operations'=>['routing'=>['profiles'=>[['secret'=>'route']]],'notify'=>['calls'=>[['secret'=>'call']],'total'=>1],'contactNames'=>[]],'dispolive'=>[]];if($path==='statistics')return ['calls'=>[['secret'=>'call']],'detailCalls'=>[['secret'=>'detail']]];if($path==='operations/report')return ['callbacks'=>[['secret'=>'callback']],'reachability'=>[['count'=>1]]];return ['ok'=>true];}}
}
namespace OCA\Placetel\Service {class UpdateNotes{}}
namespace {
 $root=__DIR__.'/../../../nextcloud/placetel';if(!is_file($root.'/lib/Service/Access.php'))$root=__DIR__.'/../../placetel';require $root.'/lib/Service/Access.php';require $root.'/lib/Controller/ApiController.php';
 class Config implements \OCP\IConfig {public array $data;public function getSystemValue($key,$default){return $this->data;}}
 class Group implements \OCP\IGroupManager {public function isAdmin($id){return false;}public function isInGroup($id,$group){return false;}}
 class User {public function getUID(){return 'fixture';}}
 class Session implements \OCP\IUserSession {public function getUser(){return new User();}}
 class Request implements \OCP\IRequest {public function __construct(public string $method,public array $params=[]){}public function getHeader($name){return $name==='requesttoken'?'fixture-token':'';}public function passesCSRFCheck(){return true;}public function getMethod(){return $this->method;}public function getParam($name,$default=null){return $this->params[$name]??$default;}}
 class Logger implements \Psr\Log\LoggerInterface{public function warning($message,$context=[]){}}
 function check($condition,$name){if(!$condition)throw new \RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
 $none=array_fill_keys(\OCA\Placetel\Service\Access::AREAS,'none');$config=new Config();$config->data=['matrix'=>['default'=>array_replace($none,['overview'=>'read','statistics'=>'none']),'groups'=>[],'users'=>[]]];$access=new \OCA\Placetel\Service\Access($config,new Group(),new Session());
 $request=new Request('GET');$controller=new \OCA\Placetel\Controller\ApiController($request,$access,new \OCA\Placetel\Native\Dashboard(),new Logger(),new \OCA\Placetel\Service\UpdateNotes());
 $res=$controller->forward('bootstrap');check($res->status===200&&$res->data['operations']['notify']['calls']===[],'bootstrap strips restricted events');
 $res=$controller->forward('statistics');check($res->status===403,'statistics endpoint blocked without section permission');
 $request->params=['scope'=>'overview'];$res=$controller->forward('statistics');check($res->status===200&&$res->data['calls']===[]&&$res->data['detailCalls']===[],'overview statistics strip call details');
 $request->params=['scope'=>'overview','from'=>'2020-01-01','to'=>'2020-01-01'];check($controller->forward('statistics')->status===403,'overview cannot fetch historical statistics');
 $request->method='POST';$request->params=[];check($controller->forward('operations/callbacks/complete')->status===403,'denied write blocked before processing');
 $config->data['matrix']['default']=array_replace($none,['statistics'=>'read']);$request->method='GET';$request->params=['scope'=>'statistics'];$res=$controller->forward('operations/report');check($res->status===200&&$res->data['callbacks']===[]&&count($res->data['reachability'])===1,'statistics report strips denied callbacks');
 check($controller->forward('permissions/options')->status===403,'user enumeration admin-only');
 echo "PERMISSIONS_CONTROLLER_COMPLETE\n";
}
