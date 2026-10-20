<?php
declare(strict_types=1);
// Run only inside the named disposable Nextcloud test container, never a real installation.
if(getenv('PLACETEL_DISPOSABLE_TEST')!=='1'){fwrite(STDERR,'Nur in expliziter Wegwerf-Testinstanz ausführen.'.PHP_EOL);exit(2);}
define('OC_CONSOLE',true);require '/var/www/html/lib/base.php';
use OCA\Placetel\Native\{Store,Secrets,Api,Analysis,Views,Importer,Directory,Routing,Demo,Dashboard,RemoteException,Popups};
use OCP\Http\Client\{IClientService,IClient};
$app=new \OCP\AppFramework\App('placetel');$container=$app->getContainer();$store=$container->get(Store::class);$secrets=$container->get(Secrets::class);$analysis=$container->get(Analysis::class);$views=$container->get(Views::class);$checks=[];
function verify(bool $ok,string $name): void {global $checks;$checks[]=['test'=>$name,'passed'=>$ok];echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)throw new \RuntimeException($name);}
function equivalent(mixed $a,mixed $b): bool {if(is_numeric($a)&&is_numeric($b))return abs((float)$a-(float)$b)<0.00001;if(is_array($a)&&is_array($b)){if(array_keys($a)!==array_keys($b)){ksort($a);ksort($b);if(array_keys($a)!==array_keys($b))return false;}foreach($a as $k=>$v)if(!equivalent($v,$b[$k]))return false;return true;}return $a===$b;}
try {
 $store->remove('dispolive:config');$secrets->remove('dispolive:fixture.dispolive.de');
 $fixture=json_decode(file_get_contents('/tmp/native-parity.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($fixture['phones'] as $f)verify($analysis->phone($f['value'])===$f['expected'],'phone normalization '.$f['value']);
 foreach($fixture['stats'] as $f){$actual=$analysis->statistics($f['calls'],$f['coverage'],$f['options']);verify(equivalent($actual,$f['expected']),'statistics parity '.$f['name']);}
 $f=$fixture['adapter'];verify(equivalent($analysis->adapt($f['rows'],$f['agents'],$f['queues'],$f['aliases']),$f['expected']),'adapter parity: ring attempts, aliases, estimates');
 $f=$fixture['report'];verify(equivalent($views->report($f['calls'],$f['queues'],$f['coverage'],$f['states'],$f['from'],$f['to'],$f['asOf']),$f['expected']),'callback and reachability parity');
 $secrets->save('unit','fixture-only-not-a-real-key');verify($secrets->get('unit')==='fixture-only-not-a-real-key','encrypted credential round trip');verify(!str_contains($store->get('unit','', 'secrets'),'fixture-only-not-a-real-key'),'credential not stored as plaintext');$secrets->remove('unit');verify($secrets->get('unit')===null,'credential removal');
 $real=\OCP\Server::get(IClientService::class);$requests=[];$account='9001';$patched=null;
 $handler=function($request,$options)use(&$requests,&$account,&$patched){$uri=$request->getUri();$path=$uri->getPath();$method=$request->getMethod();parse_str($uri->getQuery(),$query);$page=(int)($query['page']??1);$requests[]=[$method,$path];$body=null;$status=200;
  if($path==='/v2/me')$body=['id'=>$account,'platform'=>'PROFI','name'=>'Synthetic Placetel account'];
  elseif($path==='/v2/call_center_agents')$body=$page===1?[['id'=>1,'name'=>'Demo Agent','status'=>'online','extension_sip'=>'100','extension_pstn'=>'']]:[];
  elseif($path==='/v2/call_center_queues')$body=$page===1?[['id'=>10,'name'=>'Demo Queue','priority'=>1]]:[];
  elseif($path==='/v2/call_center_calls'){
   $body=[];if($page===1&&($query['date']??'')===Analysis::local(time())->format('Y-m-d')){
    $r=['id'=>11,'from'=>'+493012345678','started_at'=>gmdate('c',time()-600),'left_queue_at'=>gmdate('c',time()-500),'ended_at'=>gmdate('c',time()-300),'last_updated_at'=>gmdate('c'),'call_status'=>'ended','answer_status'=>'Answer','call_type'=>'dail_in','agent_name'=>'Demo Agent','queue_name'=>'Demo Queue','iteration'=>1];$body=[$r,array_replace($r,['id'=>12,'call_status'=>'not_answered','answer_status'=>'NoAnswer'])];
   }
  }elseif($path==='/v2/contacts')$body=[];
  elseif($path==='/v2/call_center_agents/1'&&$method==='PUT'){$patched=json_decode((string)$request->getBody(),true);$body=['id'=>1]+$patched;}
  elseif(str_ends_with($path,'contactFindByPhone')){$payload=json_decode((string)$request->getBody(),true);if(($payload['notification']??null)!==false)throw new \RuntimeException('Unexpected notification');$body=['data'=>[['_id'=>'fixture-1','name'=>'Synthetic contact','phone'=>$payload['phone'],'type'=>'institution']]];}
  else{$status=404;$body=['error'=>'No synthetic route'];}
  return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response($status,['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR)));
 };
 $factory=new class($real,$handler) implements IClientService {public function __construct(private IClientService $real,private \Closure $mock){}public function newClient(?callable $handler=null): IClient{return $this->real->newClient($handler??$this->mock);}};
 $api=new Api($factory,$store,$secrets,\OCP\Server::get(\OCP\Lock\ILockingProvider::class));$directory=new Directory($store,$secrets,$api,$analysis);$importer=new Importer($store,$secrets,$api,$analysis);$routing=new Routing($store,$api);$demo=new Demo($store);$dashboard=new Dashboard($store,$secrets,$api,$analysis,$views,$importer,$directory,$routing,$demo,\OCP\Server::get(\OCP\Lock\ILockingProvider::class),new Popups($store,$secrets,$api,$analysis,\OCP\Server::get(\OCP\Lock\ILockingProvider::class)));$who=['user'=>'synthetic-admin','role'=>'admin'];
 verify($directory->state()['origin']===''&&!$directory->state()['enabled'],'new installation has no DispoLive tenant or enabled addon');$before=count($requests);$directory->tick([]);verify(count($requests)===$before,'disabled addon sends no requests');
 $dashboard->execute('POST','connect',['token'=>'fixture-native-placetel-key'],[],$who);verify($store->get('mode')==='live'&&$secrets->get('placetel')!==null,'self-service Placetel key connects and encrypts');
 $store->remove('api:next');$account='9002';$rejected=false;try{$dashboard->execute('POST','connect',['token'=>'fixture-other-account-key'],[],$who);}catch(\InvalidArgumentException){$rejected=true;}verify($rejected&&$store->get('account:id')==='9001','different account cannot mix historical data');$account='9001';
 for($i=0;$i<14;$i++){$store->remove('api:next');$store->remove('sync:next');$importer->tick();}
 verify(count($store->get('live:agents',[]))===1&&count($store->get('live:queues',[]))===1,'native paginated catalog import');verify(count($store->rows('live:calls'))===2,'native call import publishes full day only');
 $today=Analysis::local(time())->format('Y-m-d');$stats=$dashboard->execute('GET','statistics',[],['from'=>$today,'to'=>$today],$who);verify($stats['accepted']===1&&$stats['uniqueCallers']===1&&$stats['estimateKnown']===1,'native real-data model filters ring attempts');
 $store->remove('api:next');$dashboard->execute('POST','agents/1',['status'=>'pause'],[],$who);verify(($patched['extension_sip']??null)==='100'&&($patched['extension_pstn']??null)===''&&$patched['status']==='pause','agent PUT preserves both required extension fields');
 $directory->connect(['origin'=>'https://fixture.dispolive.de','token'=>'fixture-native-dispolive-key']);verify(!$directory->state()['enabled']&&$directory->state()['configured'],'addon key storage does not opt in');$directory->toggle(true);$store->remove('dispolive:next');$directory->request(['+493012345678']);$directory->tick([]);$matches=$directory->state()['matches'];verify(isset($matches->{'+493012345678'})&&$matches->{'+493012345678'}['contacts'][0]['name']==='Synthetic contact','optional native lookup uses notification false');
 $before=count($requests);$directory->toggle(false);$directory->request(['+493012345678'],true);$directory->tick([]);verify(count($requests)===$before&&count((array)$directory->state()['matches'])===0,'switch off hides matches and stops network');
 $secrets->save('notify','fixture-native-notify-signature-secret');
 verify(!str_contains(json_encode($dashboard->bootstrap($who),JSON_THROW_ON_ERROR),'fixture-native-placetel-key'),'bootstrap never exposes saved API key');
 $rate=false;try{$api->placetel('/me');}catch(RemoteException $e){$rate=$e->status===429;}verify($rate,'shared API rate state applies to separate requests');
} finally {
 $store->put('mode','demo');$secrets->remove('placetel');$secrets->remove('dispolive:fixture.dispolive.de');
 foreach(['account:id','account:name','live:agents','live:queues','live:agents:aliases','live:queues:aliases','sync:job','sync:suspended','sync:catalogueAt','sync:lastCallsSync','sync:lastSync','sync:connected','sync:paused','sync:error','sync:progress','sync:next','api:next','dispolive:config','dispolive:queue','dispolive:connected','dispolive:lastSuccess','dispolive:paused','dispolive:error','dispolive:next'] as $key)$store->remove($key);
 foreach($store->rows('live:calls') as $c)$store->remove($c['id'],'live:calls');foreach($store->rows('live:coverage') as $c)$store->remove($c['date'],'live:coverage');$store->remove('https://fixture.dispolive.de:+493012345678','dispolive-cache');
 file_put_contents('/tmp/native-contract-results.json',json_encode(['checks'=>$checks,'count'=>count($checks),'passed'=>count(array_filter($checks,static fn($r)=>$r['passed']))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
echo 'NATIVE_CONTRACT_COMPLETE'.PHP_EOL;
