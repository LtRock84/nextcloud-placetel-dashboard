<?php
declare(strict_types=1);
if(getenv('PLACETEL_DISPOSABLE_TEST')!=='1'){fwrite(STDERR,'Nur Wegwerf-Testinstanz.'.PHP_EOL);exit(2);}
define('OC_CONSOLE',true);require '/var/www/html/lib/base.php';
use OCA\Placetel\Native\{Store,Secrets,Popups};
$app=new \OCP\AppFramework\App('placetel');$container=$app->getContainer();$store=$container->get(Store::class);$secrets=$container->get(Secrets::class);$popups=$container->get(Popups::class);$checks=[];$process=null;
function webhookCheck(bool $ok,string $name):void {global $checks;$checks[]=['test'=>$name,'passed'=>$ok];echo($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)throw new \RuntimeException($name);}
function requestPopup(array $data,?string $signature=null):int {global $store;$store->remove('dispolive:next');$raw=http_build_query($data);$signature??=hash_hmac('sha256',$raw,'fixture-native-notify-signature-secret');$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nX-Placetel-Signature: ".$signature."\r\n",'content'=>$raw,'ignore_errors'=>true,'timeout'=>8]]);file_get_contents('http://127.0.0.1:44328/notify',false,$ctx);$headers=function_exists('http_get_last_response_headers')?http_get_last_response_headers():(get_defined_vars()['http_response_header']??[]);preg_match('/\s(\d{3})\s/',$headers[0],$m);return (int)$m[1];}
$requests=static fn()=>json_decode(file_get_contents('/tmp/popup-http-requests.json'),true);
try{
 $store->put('mode','live');$store->put('dispolive:config',['origin'=>'https://fixture.dispolive.de','enabled'=>true]);$secrets->save('dispolive:fixture.dispolive.de','synthetic-webhook-fixture-key');$secrets->save('notify','fixture-native-notify-signature-secret');$popups->configure(['enabled'=>true,'desktopPhone'=>'100','firmenKrz'=>'SYN','calledNumbers'=>[]]);file_put_contents('/tmp/popup-http-requests.json','[]');
 $process=proc_open([PHP_BINARY,'-S','127.0.0.1:44328','/tmp/webhook-router.php'],[0=>['file','/dev/null','r'],1=>['file','/tmp/popup-router.log','a'],2=>['file','/tmp/popup-router.log','a']],$pipes);if(!is_resource($process))throw new \RuntimeException('Fixture server did not start');
 for($i=0;$i<40;$i++){if(@file_get_contents('http://127.0.0.1:44328/ready')==='READY')break;usleep(50000);}if($i===40)throw new \RuntimeException('Fixture server not ready');
 $data=['call_id'=>hash('sha256','integrated-first'),'event'=>'IncomingCall','direction'=>'in','from'=>'+493012345678','to'=>'+493011111111'];
 webhookCheck(requestPopup($data)===200&&count($requests())===1,'signed HTTP event reaches real controller and mocked DispoLive delivery');
 webhookCheck($requests()[0]['body']===['phone'=>'+493012345678','notification'=>true,'desktopPhone'=>'100','firmenKrz'=>'SYN'],'full webhook flow forwards documented notification payload');
 webhookCheck(requestPopup($data)===200&&count($requests())===1,'identical signed HTTP delivery does not duplicate notification');
 webhookCheck(requestPopup($data+['iteration'=>'2'])===200&&count($requests())===1,'changed event body for same call ID does not duplicate popup');
 $data['call_id']=hash('sha256','integrated-second');webhookCheck(requestPopup($data)===200&&count($requests())===2,'new signed call ID for same phone sends second popup');
 $data['call_id']=hash('sha256','integrated-forged');webhookCheck(requestPopup($data,str_repeat('0',64))===401&&count($requests())===2,'forged signature cannot cause external side effect');
 $data['call_id']=hash('sha256','integrated-outgoing');$data['event']='OutgoingCall';$data['direction']='out';webhookCheck(requestPopup($data)===200&&count($requests())===2,'signed outgoing HTTP event produces no popup');
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 $store->put('mode','demo');$secrets->remove('dispolive:fixture.dispolive.de');foreach(['dispolive:config','dispolive:popups','dispolive:next'] as $k)$store->remove($k);foreach(['dispolive-popups','notify-calls'] as $scope)foreach($store->rows($scope) as $r)$store->remove($r['id'],$scope);
 file_put_contents('/tmp/native-webhook-results.json',json_encode(['checks'=>$checks,'count'=>count($checks),'passed'=>count(array_filter($checks,static fn($r)=>$r['passed']))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
echo 'NATIVE_WEBHOOK_COMPLETE'.PHP_EOL;
