<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
final class Dashboard {
 public function __construct(private Store $store,private Secrets $secrets,private Api $api,private Analysis $analysis,private Views $views,private Importer $importer,private Directory $directory,private Routing $routing,private Demo $demo,private ILockingProvider $locks,private Popups $popups) {}
 public function locked(callable $fn): mixed {
  try{$this->locks->acquireLock('placetel-dashboard-native',ILockingProvider::LOCK_EXCLUSIVE);}catch(LockedException){throw new RemoteException('Ein Abruf läuft. Bitte gleich erneut versuchen.',409,1);}
  try{return $fn();}finally{$this->locks->releaseLock('placetel-dashboard-native',ILockingProvider::LOCK_EXCLUSIVE);}
 }
 public function mode(): string{return $this->store->get('mode','demo');}
 public function tick(): void {
  if($this->mode()==='demo'){$this->demo->seed();return;}
  $this->popups->tick();$routing=$this->routing->tick();if(!$routing)$this->importer->tick();$this->directory->tick($this->calls(true));
 }
 private function calls(bool $recent=false): array {
  $from=$recent?Analysis::local(time())->setTime(0,0)->modify('-1 day')->format('Y-m-d'):null;$rows=$this->store->rows($this->mode().':calls',$from);
  if($this->mode()==='live')foreach(['agents'=>'agent','queues'=>'queue'] as $kind=>$field){$aliases=$this->store->get('live:'.$kind.':aliases',[]);foreach($rows as &$row){if($row[$field.'Id']||!($row[$field.'Name']??''))continue;$ids=[];foreach($aliases as $id=>$names)if(in_array($row[$field.'Name'],$names,true))$ids[]=(string)$id;if(count($ids)===1)$row[$field.'Id']=$ids[0];}unset($row);}
  return $rows;
 }
 private function asOf(): string{return $this->mode()==='demo'?gmdate('c'):($this->store->get('sync:lastCallsSync')??$this->store->get('sync:lastSync')??gmdate('c'));}
 public function bootstrap(array $identity): array {
  try{$this->locked(fn()=> $this->tick());}catch(RemoteException $e){if($e->status!==409)throw $e;}
  $mode=$this->mode();$demo=$mode==='demo';$agents=$this->store->get($mode.':agents',[]);$queues=$this->store->get($mode.':queues',[]);$last=$demo?gmdate('c'):$this->store->get('sync:lastSync');$lastCalls=$demo?gmdate('c'):$this->store->get('sync:lastCallsSync');$configured=$this->secrets->get('placetel')!==null;
  $notify=$mode==='live'?$this->store->rows('notify-calls'):[];usort($notify,static fn($a,$b)=>strcmp($b['lastReceivedAt'],$a['lastReceivedAt']));
  return ['status'=>['mode'=>$mode,'connected'=>$demo||$this->store->get('sync:connected',false),'account'=>$demo?'Placetel Dashboard · Demo':$this->store->get('account:name'),'syncing'=>false,'lastSync'=>$last,'lastAttempt'=>$this->store->get('sync:lastAttempt'),'error'=>$demo?null:$this->store->get('sync:error'),'nextRequestAt'=>($next=$this->store->get('sync:next'))?gmdate('c',(int)$next):null,'historyProgress'=>$this->store->get('sync:progress'),'lastCallsSync'=>$lastCalls,'capabilities'=>['assignments'=>false,'verifiedStatistics'=>$demo,'liveCalls'=>true,'callStatistics'=>true,'agentManagement'=>true,'queueManagement'=>true]],'agents'=>$agents,'queues'=>$queues,'plans'=>[],'assignments'=>(object)[],'overrides'=>(object)[],'audit'=>$this->store->get($mode.':audit',[]),'queueSnapshot'=>$this->views->queues($this->calls(true),$queues,$agents,$this->store->rows($mode.':coverage'),$lastCalls,$demo||$this->store->get('sync:connected',false)),'operations'=>['alerts'=>$this->store->get($mode.':alerts',['warningSeconds'=>60,'criticalSeconds'=>120,'sound'=>false]),'contactNames'=>(object)$this->store->get($mode.':contactNames',[]),'contactsAt'=>$this->store->get($mode.':contactsAt'),'contactsError'=>null,'routing'=>$this->routing->state(),'notify'=>['configured'=>!$demo&&$this->secrets->get('notify')!==null,'lastReceivedAt'=>$this->store->get('notify:lastReceivedAt'),'calls'=>array_slice($notify,0,50),'total'=>count($notify)]],'dispolive'=>$this->directory->state()+['popups'=>$this->popups->state()],'csrf'=>'','network'=>['lan'=>false,'urls'=>[]],'hosting'=>['native'=>true,'managedSecrets'=>false,'configured'=>$configured,'role'=>$identity['role'],'user'=>$identity['user'],'background'=>'nextcloud-cron'],'access'=>$this->store->get('access')];
 }
 public function execute(string $method,string $path,array $input,array $query,array $identity): mixed {
  if($method==='GET'&&$path==='bootstrap')return $this->bootstrap($identity);
  if($method==='GET'&&$path==='health')return ['app'=>'placetel-dashboard','version'=>'0.2.0','native'=>true];
  if($method==='GET'&&$path==='coverage')return $this->store->rows($this->mode().':coverage');
  if($method==='GET'&&in_array($path,['statistics','operations/report'],true)){
   $from=$query['from']??Analysis::local(time())->format('Y-m-d');$to=$query['to']??$from;$calls=$this->calls();$coverage=$this->store->rows($this->mode().':coverage');
   if($path==='operations/report')return $this->views->report($calls,$this->store->get($this->mode().':queues',[]),$coverage,$this->store->get($this->mode().':callbacks',[]),$from,$to,$this->asOf());
   foreach(['agentId'=>'agents','queueId'=>'queues'] as $filter=>$kind)if(($query[$filter]??'')&&!in_array($query[$filter],array_column($this->store->get($this->mode().':'.$kind,[]),'id'),true))throw new \InvalidArgumentException('Agent oder Warteschleife nicht gefunden.');
   return $this->analysis->statistics($calls,$coverage,['from'=>$from,'to'=>$to,'asOf'=>$this->asOf(),'agentId'=>$query['agentId']??'','queueId'=>$query['queueId']??'','source'=>$this->mode()==='live'?'placetel':null]);
  }
  return $this->locked(function()use($method,$path,$input,$identity){$value=$this->mutate($method,$path,$input);$this->store->log($this->mode(),'nextcloud-request','Nextcloud '.$identity['user'].': '.$method.' /'.$path);return $value??['ok'=>true];});
 }
 private function connect(#[\SensitiveParameter] mixed $token): void {
  if(!is_string($token)||strlen($token)<8||strlen($token)>4096||preg_match('/\s/',$token))throw new \InvalidArgumentException('Gültigen Placetel-API-Schlüssel ohne Leerzeichen eingeben.');
  $me=$this->api->placetel('/me','GET',null,$token);if(!is_array($me)||($me['platform']??'')!=='PROFI')throw new \InvalidArgumentException('Kein bestätigtes Placetel-Profi-Konto.');$id=(string)($me['main_account_id']??$me['id']??'');if($id==='')throw new \InvalidArgumentException('Keine eindeutige Konto-ID erhalten.');$previous=$this->store->get('account:id');if($previous!==null&&$previous!==$id)throw new \InvalidArgumentException('Diese Installation enthält Daten eines anderen Placetel-Kontos. Kontowechsel erfordert eine getrennte Datenmigration.');
  $this->store->transaction(function()use($token,$id,$me){$this->secrets->save('placetel',$token);$this->store->put('account:id',$id);$this->store->put('account:name',is_string($me['name']??null)?mb_substr($me['name'],0,200):'Placetel Profi');$this->store->put('mode','live');$this->store->put('sync:connected',true);$this->store->remove('sync:paused');$this->store->remove('sync:error');$this->store->remove('sync:job');$this->store->remove('sync:suspended');$this->store->remove('sync:next');$this->store->remove('sync:catalogueAt');$this->store->log('live','connection','Placetel-Konto geprüft. API-Schlüssel verschlüsselt in Nextcloud gespeichert.');});
 }
 private function mutate(string $method,string $path,#[\SensitiveParameter] array $input): mixed {
  switch($path){
   case 'connect':$this->connect($input['token']??null);return null;
   case 'disconnect':$this->store->put('dispolive:revision',bin2hex(random_bytes(12)));$this->secrets->remove('placetel');$this->store->put('mode','demo');$this->store->remove('sync:job');$this->store->remove('sync:suspended');return null;
   case 'mode':if(!in_array($input['mode']??null,['demo','live'],true))throw new \InvalidArgumentException('Ungültiger Modus.');if($input['mode']==='live'&&!$this->secrets->get('placetel'))throw new \InvalidArgumentException('Zuerst Placetel verbinden.');$this->store->put('dispolive:revision',bin2hex(random_bytes(12)));$this->store->put('mode',$input['mode']);return null;
   case 'assignments':case 'plans':throw new \InvalidArgumentException('Agenten-Warteschleifenzuordnung und Skills sind mangels bestätigter API gesperrt.');
   case 'dispolive/connect':$this->directory->connect($input);return null;
   case 'dispolive/disconnect':$this->directory->disconnect();return null;
   case 'dispolive/toggle':$this->directory->toggle($input['enabled']??null);return null;
   case 'dispolive/popups':$this->popups->configure($input);return null;
   case 'dispolive/resolve':$this->directory->request($input['numbers']??null,($input['force']??false)===true);return null;
   case 'operations/alerts':if(!is_int($input['warningSeconds']??null)||!is_int($input['criticalSeconds']??null)||$input['warningSeconds']<1||$input['criticalSeconds']<=$input['warningSeconds']||$input['criticalSeconds']>86400||!is_bool($input['sound']??null))throw new \InvalidArgumentException('Aufsteigende Warnschwellen zwischen 1 und 86400 Sekunden angeben.');$this->store->put($this->mode().':alerts',array_intersect_key($input,array_flip(['warningSeconds','criticalSeconds','sound'])));return null;
   case 'operations/callback':$this->saveCallback($input);return null;
   case 'operations/notify':if($this->mode()!=='live'||!is_string($input['secret']??null)||strlen($input['secret'])<24||strlen($input['secret'])>1024)throw new \InvalidArgumentException('Im Echtbetrieb ein gemeinsames Geheimnis mit mindestens 24 Zeichen angeben.');$this->secrets->save('notify',$input['secret']);$this->store->put('dispolive:revision',bin2hex(random_bytes(12)));return null;
   case 'operations/routing/load':$this->routing->load();return null;
   case 'operations/routing/profile':return $this->routing->save($input);
   case 'operations/routing/preview':return $this->routing->preview($this->text($input,'id'));
   case 'operations/routing/apply':$this->routing->apply($this->text($input,'id'),$this->text($input,'token'));return null;
   case 'operations/routing/simulate':return $this->routing->simulate($this->text($input,'planId'),$this->text($input,'at'));
  }
  if($method==='POST'&&preg_match('~^(agents|queues)/([A-Za-z0-9_-]+)$~D',$path,$m)){$this->updateResource($m[1],$m[2],$input);return null;}
  if($method==='DELETE'&&preg_match('~^operations/routing/profile/([A-Za-z0-9_-]+)$~D',$path,$m)){$this->routing->remove($m[1]);return null;}
  throw new \InvalidArgumentException('Endpunkt nicht verfügbar.');
 }
 private function text(array $input,string $key): string {if(!is_string($input[$key]??null)||strlen($input[$key])>200)throw new \InvalidArgumentException('Ungültige Eingabe.');return $input[$key];}
 private function saveCallback(array $input): void {
  if(!is_string($input['key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['key'])||!in_array($input['state']??null,['open','done','later'],true)||!is_string($input['note']??null)||mb_strlen($input['note'])>1000)throw new \InvalidArgumentException('Ungültiger Rückrufstatus.');$found=null;foreach($this->calls() as $c)if($c['id']===($input['latestId']??null)&&$c['outcome']==='missed')$found=$c;
  if(!$found||hash('sha256',json_encode([$found['queueId'],$this->analysis->phone($found['caller'])??$found['id']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))!==$input['key'])throw new \InvalidArgumentException('Rückrufdatensatz nicht gefunden.');$states=$this->store->get($this->mode().':callbacks',[]);$states[$input['key']]=array_intersect_key($input,array_flip(['latestId','state','note']));$this->store->put($this->mode().':callbacks',$states);
 }
 private function waitApi(): void {$delay=$this->store->get('api:next',0)-microtime(true);if($delay>1.5)throw new RemoteException('API-Zeitfenster noch nicht frei. Bitte später erneut prüfen.',429,(int)ceil($delay));if($delay>0)usleep((int)($delay*1000000)+1000);}
 private function updateResource(string $kind,string $id,array $patch): void {
  $allowed=$kind==='agents'?['name','description','status','priority']:['name','description','priority','ringing_time','num_loops','max_iterations'];if(!$patch||array_diff(array_keys($patch),$allowed))throw new \InvalidArgumentException('Nicht unterstützte Änderung.');
  foreach($patch as $key=>$value){if(in_array($key,['priority','ringing_time','num_loops','max_iterations'],true)){if(!is_int($value)||$value<0||$value>2147483647)throw new \InvalidArgumentException('Nichtnegative int32-Zahl eingeben.');}elseif(!is_string($value)||mb_strlen($value)>500||$key==='name'&&!trim($value))throw new \InvalidArgumentException('Ungültiger Text.');}
  if(isset($patch['status'])&&!in_array($patch['status'],['online','offline','pause','working','dail_out'],true))throw new \InvalidArgumentException('Nicht dokumentierter Agentenstatus.');$key=$this->mode().':'.$kind;$items=$this->store->get($key,[]);$before=null;foreach($items as $item)if($item['id']===$id)$before=$item;if(!$before)throw new \InvalidArgumentException('Agent oder Warteschleife nicht gefunden.');
  if($this->mode()==='live'){
   if(!ctype_digit($id))throw new \InvalidArgumentException('Ungültige Placetel-ID.');$payload=$patch;
   if($kind==='agents'){$raw=null;$started=microtime(true);for($page=1;$page<=100&&microtime(true)-$started<10;$page++){$this->waitApi();$rows=$this->api->page('/call_center_agents',$page);foreach($rows as $r)if((string)($r['id']??'')===$id)$raw=$r;if($raw||!$rows)break;}if(!is_string($raw['extension_sip']??null)||!is_string($raw['extension_pstn']??null))throw new \InvalidArgumentException('Erforderliche Anschlussfelder nicht vollständig gelesen. Keine Änderung gesendet.');$payload=['extension_sip'=>$raw['extension_sip'],'extension_pstn'=>$raw['extension_pstn']]+$patch;}
   $this->waitApi();try{$result=$this->api->placetel('/call_center_'.$kind.'/'.$id,'PUT',$payload);if((string)($result['id']??'')!==$id)throw new RemoteException('Änderungsantwort enthält eine andere ID.');foreach($patch as $name=>$v)if(!array_key_exists($name,$result)||(string)$result[$name]!== (string)$v)throw new RemoteException('Anlagenantwort bestätigt die Änderung nicht.');}
   catch(\Throwable $e){$this->store->remove('sync:catalogueAt');$this->store->log('live','update-'.$kind,'Änderung nicht bestätigt. Anlagenstand prüfen; keine automatische Wiederholung.','error');throw $e;}
   $this->store->remove('sync:catalogueAt');
  }
  foreach($items as &$item)if($item['id']===$id)$item=array_replace($item,$patch);unset($item);$this->store->put($key,$items);$aliases=$this->store->get($key.':aliases',[]);foreach($items as $item)$aliases[$item['id']]=array_values(array_unique(array_merge($aliases[$item['id']]??[],[$item['name']])));$this->store->put($key.':aliases',$aliases);$this->store->log($this->mode(),'update-'.$kind,'Änderung bestätigt.','ok',['before'=>json_encode($before),'after'=>json_encode($patch)]);
 }
 public function notificationsTick(): void {$this->locked(fn()=> $this->popups->workerTick());}
 public function maintenance(): void {$this->locked(function(){$this->tick();$this->store->prune();});}
}
