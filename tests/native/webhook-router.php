<?php
declare(strict_types=1);
if(getenv('PLACETEL_DISPOSABLE_TEST')!=='1'){http_response_code(503);exit;}
if(($_SERVER['REQUEST_URI']??'')==='/ready'){echo 'READY';exit;}
define('OC_CONSOLE',true);require '/var/www/html/lib/base.php';
use OCA\Placetel\Native\{Store,Secrets,Analysis,Api,Popups};
use OCP\Http\Client\{IClientService,IClient};
$app=new \OCP\AppFramework\App('placetel');$c=$app->getContainer();$store=$c->get(Store::class);$secrets=$c->get(Secrets::class);$analysis=$c->get(Analysis::class);$locks=\OCP\Server::get(\OCP\Lock\ILockingProvider::class);
$real=\OCP\Server::get(IClientService::class);
$handler=static function($request,$options){$file='/tmp/popup-http-requests.json';$rows=json_decode(file_get_contents($file),true);$rows[]=['path'=>$request->getUri()->getPath(),'body'=>json_decode((string)$request->getBody(),true)];file_put_contents($file,json_encode($rows));return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],'{"data":[{"name":"Synthetic Caller"}]}'));};
$http=new class($real,$handler) implements IClientService{public function __construct(private IClientService $real,private \Closure $mock){}public function newClient(?callable $handler=null):IClient{return $this->real->newClient($handler??$this->mock);}};
$api=new Api($http,$store,$secrets,$locks);$popups=new Popups($store,$secrets,$api,$analysis,$locks);
$controller=new \OCA\Placetel\Controller\NotifyController(\OCP\Server::get(\OCP\IRequest::class),$secrets,$store,$analysis,$locks,$popups);
$response=$controller->receive();http_response_code($response->getStatus());echo $response->getStatus()===200?'OK':'REJECTED';
