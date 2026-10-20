<?php
declare(strict_types=1);
if(getenv('PLACETEL_DISPOSABLE_TEST')!=='1'){fwrite(STDERR,'Nur Wegwerf-Testinstanz.'.PHP_EOL);exit(2);}
define('OC_CONSOLE',true);require '/var/www/html/lib/base.php';
use OCA\Placetel\Native\{Store,Secrets,Api,Analysis,Popups};
use OCP\Http\Client\{IClientService,IClient};
$app=new \OCP\AppFramework\App('placetel');$container=$app->getContainer();$store=$container->get(Store::class);$secrets=$container->get(Secrets::class);$analysis=$container->get(Analysis::class);$checks=[];$requests=[];$response=200;$fail=false;$sendingPersisted=false;
function checkPopup(bool $ok,string $name): void {global $checks;$checks[]=['test'=>$name,'passed'=>$ok];echo($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)throw new \RuntimeException($name);}
function clearPopupRows(): void {global $store;foreach(['dispolive-popups','notify-calls'] as $scope)foreach($store->rows($scope) as $r)$store->remove($r['id'],$scope);$store->remove('dispolive:next');}
$real=\OCP\Server::get(IClientService::class);
$handler=function($request,$options)use(&$requests,&$response,&$fail,&$sendingPersisted,$store){$body=json_decode((string)$request->getBody(),true);$requests[]=['body'=>$body,'path'=>$request->getUri()->getPath(),'version'=>$request->getHeaderLine('dispolive-api-version'),'redirects'=>$options['allow_redirects']??null];foreach($store->rows('dispolive-popups') as $row)if($row['state']==='sending')$sendingPersisted=true;if($fail)throw new \GuzzleHttp\Exception\ConnectException('Synthetic unavailable transport',$request);return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response($response,['Content-Type'=>'application/json','Retry-After'=>'1'],'{"data":[],"diagnostic":"DO_NOT_EXPOSE_SYNTHETIC_RESPONSE"}'));};
$factory=new class($real,$handler) implements IClientService {public function __construct(private IClientService $real,private \Closure $mock){}public function newClient(?callable $handler=null):IClient{return $this->real->newClient($handler??$this->mock);}};
$api=new Api($factory,$store,$secrets,\OCP\Server::get(\OCP\Lock\ILockingProvider::class));$popups=new Popups($store,$secrets,$api,$analysis,\OCP\Server::get(\OCP\Lock\ILockingProvider::class));
$config=['enabled'=>true,'desktopPhone'=>'100','firmenKrz'=>'SYN','calledNumbers'=>[]];
$call=function(string $id,array $patch=[])use($store):array{$call=array_replace(['id'=>hash('sha256','popup-fixture-'.$id),'caller'=>'+493012345678','to'=>'+493011111111','direction'=>'in','events'=>['IncomingCall'],'firstReceivedAt'=>gmdate('c'),'lastReceivedAt'=>gmdate('c')],$patch);$store->put($call['id'],$call,'notify-calls',gmdate('Y-m-d'));return $call;};
$get=fn($c)=>$store->get($c['id'],null,'dispolive-popups');
try {
 clearPopupRows();$store->remove('dispolive:popups');checkPopup(!$popups->state()['enabled'],'popups default off');
 $rejected=false;try{$popups->configure($config);}catch(\InvalidArgumentException){$rejected=true;}checkPopup($rejected,'cannot enable without live integration');
 $store->put('mode','live');$store->put('dispolive:config',['origin'=>'https://fixture.dispolive.de','enabled'=>true]);$secrets->save('dispolive:fixture.dispolive.de','synthetic-popup-fixture-key');$secrets->remove('notify');
 $rejected=false;try{$popups->configure($config);}catch(\InvalidArgumentException){$rejected=true;}checkPopup($rejected,'requires signed event setup');$secrets->save('notify','fixture-native-notify-signature-secret');
 $rejected=false;try{$popups->configure(array_replace($config,['desktopPhone'=>'']));}catch(\InvalidArgumentException){$rejected=true;}checkPopup($rejected,'empty destination cannot broadcast');
 $popups->configure($config);checkPopup(count($requests)===0,'saving settings sends no notification');
 $a=$call('anonymous',['caller'=>null]);$popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup($get($a)['state']==='skipped'&&count($requests)===0,'anonymous caller does not trigger lookup');
 $a=$call('out',['direction'=>'out','events'=>['OutgoingCall']]);$popups->enqueue($a,'OutgoingCall');checkPopup($get($a)===null,'outgoing event never queues popup');
 $a=$call('first');$popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup($get($a)['state']==='sent'&&count($requests)===1,'new incoming call delivered immediately');
 checkPopup($requests[0]['body']===['phone'=>'+493012345678','notification'=>true,'desktopPhone'=>'100','firmenKrz'=>'SYN'],'documented popup payload with explicit destination and company');
 checkPopup($requests[0]['version']==='v1.0.6'&&$requests[0]['redirects']===false,'pinned version and no redirects');checkPopup($sendingPersisted,'sending claim persisted before external side effect');
 $popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup(count($requests)===1,'same call ID delivered only once');
 $store->remove('dispolive:next');$b=$call('second');$popups->enqueue($b,'IncomingCall');$popups->tick();checkPopup(count($requests)===2&&$get($b)['state']==='sent','same phone on new call gets new notification');
 $again=new Popups($store,$secrets,$api,$analysis,\OCP\Server::get(\OCP\Lock\ILockingProvider::class));$again->enqueue($a,'IncomingCall');$again->tick();checkPopup(count($requests)===2,'restart retains delivery deduplication');
 clearPopupRows();$a=$call('accepted');$popups->enqueue($a,'IncomingCall');$a['events'][]='CallAccepted';$store->put($a['id'],$a,'notify-calls',gmdate('Y-m-d'));$popups->tick();checkPopup($get($a)['state']==='skipped','accepted call cancels pending popup');
 clearPopupRows();$a=$call('ended');$popups->enqueue($a,'IncomingCall');$a['events'][]='HungUp';$store->put($a['id'],$a,'notify-calls',gmdate('Y-m-d'));$popups->tick();checkPopup($get($a)['state']==='skipped','ended call cancels pending popup');
 clearPopupRows();$popups->configure(array_replace($config,['calledNumbers'=>['03011111111']]));$a=$call('filtered');$popups->enqueue($a,'IncomingCall');checkPopup($get($a)['state']==='pending','target number normalization matches');$b=$call('other',['to'=>'+493022222222']);$popups->enqueue($b,'IncomingCall');checkPopup($get($b)['state']==='skipped','unselected called number excluded');
 $popups->configure(array_replace($config,['enabled'=>false]));checkPopup($get($a)['state']==='skipped','disable cancels existing pending notification');
 clearPopupRows();$popups->configure(array_replace($config,['firmenKrz'=>'']));$a=$call('no-company');$popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup(!array_key_exists('firmenKrz',end($requests)['body']),'optional company omitted');
 clearPopupRows();$popups->configure($config);$a=$call('revision');$popups->enqueue($a,'IncomingCall');$store->put('dispolive:revision','changed-fixture-key');$popups->tick();checkPopup($get($a)['state']==='skipped','credential replacement cancels old pending item');
 clearPopupRows();$a=$call('integration-off');$popups->enqueue($a,'IncomingCall');$store->put('dispolive:config',['origin'=>'https://fixture.dispolive.de','enabled'=>false]);$popups->tick();checkPopup($get($a)['state']==='skipped','disabling addon cancels pending send');$store->put('dispolive:config',['origin'=>'https://fixture.dispolive.de','enabled'=>true]);
 clearPopupRows();$a=$call('quick-toggle');$popups->enqueue($a,'IncomingCall');$dir=new \OCA\Placetel\Native\Directory($store,$secrets,$api,$analysis);$dir->toggle(false);$dir->toggle(true);$popups->tick();checkPopup($get($a)['state']==='skipped','quick disable and reenable cannot resurrect queued popup');
 clearPopupRows();$a=$call('local-busy');$popups->enqueue($a,'IncomingCall');
 $code='define("OC_CONSOLE",true);require "/var/www/html/lib/base.php";$l=\\OCP\\Server::get(\\OCP\\Lock\\ILockingProvider::class);$l->acquireLock("placetel-dispolive-api",\\OCP\\Lock\\ILockingProvider::LOCK_EXCLUSIVE);echo "LOCK_HELD".PHP_EOL;flush();usleep(1500000);$l->releaseLock("placetel-dispolive-api",\\OCP\\Lock\\ILockingProvider::LOCK_EXCLUSIVE);';
 $hold=proc_open([PHP_BINARY,'-r',$code],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/tmp/popup-lock-fixture.log','a']],$pipes);if(!is_resource($hold)||trim(fgets($pipes[1]))!=='LOCK_HELD')throw new \RuntimeException('Lock fixture unavailable');
 try{$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='pending'&&$get($a)['attempts']===0&&count($requests)===$before,'local lookup contention does not consume delivery attempts');}finally{fclose($pipes[1]);proc_close($hold);}
 $row=$get($a);$row['nextAt']=0;$store->put($a['id'],$row,'dispolive-popups',gmdate('Y-m-d'));$popups->tick();checkPopup($get($a)['state']==='sent'&&$get($a)['attempts']===1,'popup sends after local contention resolves');
 clearPopupRows();$fail=true;$a=$call('timeout');$popups->enqueue($a,'IncomingCall');$popups->tick();$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='uncertain'&&count($requests)===$before,'timeout result uncertain and never blindly retried');$fail=false;
 clearPopupRows();$response=500;$a=$call('server-error');$popups->enqueue($a,'IncomingCall');$popups->tick();$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='uncertain'&&count($requests)===$before,'HTTP500 not automatically duplicated');
 clearPopupRows();$response=403;$a=$call('denied');$popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup($get($a)['state']==='error','permission denial visible');
 clearPopupRows();$response=429;$a=$call('rate');$popups->enqueue($a,'IncomingCall');$popups->tick();checkPopup($get($a)['state']==='pending'&&$get($a)['attempts']===1,'explicit rate rejection safely queued');
 $row=$get($a);$row['nextAt']=0;$store->put($a['id'],$row,'dispolive-popups',gmdate('Y-m-d'));$store->remove('dispolive:next');$response=200;$popups->tick();checkPopup($get($a)['state']==='sent'&&$get($a)['attempts']===2,'rate-limited call retried while still ringing');
 clearPopupRows();$a=$call('attempt-limit');$popups->enqueue($a,'IncomingCall');$row=$get($a);$row['attempts']=3;$store->put($a['id'],$row,'dispolive-popups',gmdate('Y-m-d'));$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='error'&&count($requests)===$before,'retry attempts bounded');
 clearPopupRows();$a=$call('expired');$popups->enqueue($a,'IncomingCall');$row=$get($a);$row['receivedAt']=gmdate('c',time()-100);$store->put($a['id'],$row,'dispolive-popups',gmdate('Y-m-d'));$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='expired'&&count($requests)===$before,'old queued calls not replayed');
 clearPopupRows();$a=$call('crash');$popups->enqueue($a,'IncomingCall');$row=$get($a);$row['state']='sending';$row['updatedAt']=gmdate('c',time()-35);$store->put($a['id'],$row,'dispolive-popups',gmdate('Y-m-d'));$before=count($requests);$popups->tick();checkPopup($get($a)['state']==='uncertain'&&count($requests)===$before,'interrupted send after restart marked uncertain');
 clearPopupRows();$a=$call('late-event',['firstReceivedAt'=>gmdate('c',time()-100)]);$popups->enqueue($a,'IncomingCall');checkPopup($get($a)['state']==='skipped','old received event cannot backfill popup');
 checkPopup(!str_contains(json_encode($popups->state()),'DO_NOT_EXPOSE_SYNTHETIC_RESPONSE')&&!str_contains(json_encode($popups->state()),'synthetic-popup-fixture-key'),'status never leaks remote body or API key');
 checkPopup($container->get(\OCA\Placetel\Command\Notifications::class)->getName()==='placetel:notifications:work','public Nextcloud command registers worker');
}finally{
 clearPopupRows();$store->put('mode','demo');$secrets->remove('dispolive:fixture.dispolive.de');foreach(['dispolive:config','dispolive:revision','dispolive:popups','dispolive:popupWorkerAt'] as $key)$store->remove($key);
 file_put_contents('/tmp/native-popup-results.json',json_encode(['checks'=>$checks,'count'=>count($checks),'passed'=>count(array_filter($checks,static fn($r)=>$r['passed']))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
echo 'NATIVE_POPUPS_COMPLETE'.PHP_EOL;
