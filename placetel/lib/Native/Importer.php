<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
final class Importer {
 public function __construct(private Store $store,private Secrets $secrets,private Api $api,private Analysis $analysis) {}
 public function tick(): void {
  if($this->store->get('mode','demo')!=='live'||!$this->secrets->get('placetel')||$this->store->get('sync:paused',false)||microtime(true)<$this->store->get('sync:next',0))return;
  $now=gmdate('c');$today=Analysis::local(time())->format('Y-m-d');$coverage=array_column($this->store->rows('live:coverage'),null,'date');$due=!isset($coverage[$today])||time()-Analysis::stamp($coverage[$today]['updatedAt'])>=5;
  $yesterday=Analysis::local(time())->modify('-1 day')->format('Y-m-d');$carry=false;foreach($this->store->rows('live:calls',$yesterday,$yesterday) as $c)if(!$c['endedAt']&&in_array($c['outcome'],['waiting','accepted'],true))$carry=true;
  $priority=$due?$today:($carry&&(!isset($coverage[$yesterday])||time()-Analysis::stamp($coverage[$yesterday]['updatedAt'])>=5)?$yesterday:null);
  $job=$this->store->get('sync:job');
  if($priority&&$job&&$job['kind']==='calls'&&$job['date']!==$priority&&!$this->store->get('sync:suspended')){$this->store->put('sync:suspended',$job);$job=['kind'=>'calls','date'=>$priority,'page'=>1,'rows'=>[]];}
  if(!$job){
   if(time()-$this->store->get('sync:catalogueAt',0)>=60)$job=['kind'=>'agents','page'=>1,'rows'=>[]];
   elseif($priority)$job=['kind'=>'calls','date'=>$priority,'page'=>1,'rows'=>[]];
   else {
    $date=Analysis::local(time())->setTime(0,0)->modify('-1 day');$cutoff=Analysis::local(time())->modify('-12 months')->format('Y-m-d');
    while($date->format('Y-m-d')>=$cutoff){$key=$date->format('Y-m-d');if(!isset($coverage[$key])||$coverage[$key]['status']==='error'||Analysis::stamp($coverage[$key]['updatedAt'])<$date->modify('+1 day')->getTimestamp())break;$date=$date->modify('-1 day');}
    if($date->format('Y-m-d')>=$cutoff)$job=['kind'=>'calls','date'=>$date->format('Y-m-d'),'page'=>1,'rows'=>[]];
    elseif(time()-$this->store->get('live:contactsAtEpoch',0)>=900)$job=['kind'=>'contacts','page'=>1,'rows'=>[]];else return;
    // Interleave directory pages so a year's history cannot starve the phone book.
    if(time()-$this->store->get('live:contactsAtEpoch',0)>=900&&$this->store->get('sync:historyDays',0)%5===0)$job=['kind'=>'contacts','page'=>1,'rows'=>[]];
   }
  }
  $path=match($job['kind']){'agents'=>'/call_center_agents','queues'=>'/call_center_queues','contacts'=>'/contacts',default=>'/call_center_calls?date='.$job['date']};
  $this->store->put('sync:lastAttempt',$now);$this->store->put('sync:progress',($job['date']??$job['kind']).' · Seite '.$job['page']);
  try{
   $rows=$this->api->page($path,$job['page']);$fields=match($job['kind']){'agents'=>['id','name','status','description','priority','extension_sip','extension_pstn'],'queues'=>['id','name','description','priority','ringing_time','num_loops','max_iterations'],'contacts'=>['id','first_name','last_name','company','phone','mobile','phone_work','mobile_work'],default=>['id','started_at','ended_at','left_queue_at','last_updated_at','call_status','answer_status','agent_name','queue_name','from','call_type','iteration']};$rows=array_map(static fn($r)=>array_intersect_key($r,array_flip($fields)),$rows);$hash=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));if($rows&&($job['last']??'')===$hash)throw new RemoteException('Wiederholte Datenseite; Datenstand wird nicht überschrieben.');
   if($rows){$job['rows']=array_merge($job['rows'],$rows);$job['last']=$hash;$job['page']++;if($job['page']>10000||count($job['rows'])>10000)throw new RemoteException('Zu viele Datensätze; Import zur Prüfung angehalten.');$this->store->put('sync:job',$job);}
   else{$this->publish($job);$this->store->remove('sync:job');if($job['kind']==='agents')$this->store->put('sync:job',['kind'=>'queues','page'=>1,'rows'=>[]]);if($job['kind']==='queues')$this->store->put('sync:catalogueAt',time());if($job['kind']==='calls'){$suspended=$this->store->get('sync:suspended');if($suspended)$this->store->put('sync:job',$suspended);$this->store->remove('sync:suspended');}$this->store->put('sync:lastSync',gmdate('c'));}
   $this->store->put('sync:connected',true);$this->store->remove('sync:error');$this->store->put('sync:next',max(microtime(true)+0.5,$this->store->get('api:next',0)));
  }catch(\Throwable $e){$message=$e instanceof RemoteException?$e->getMessage():'Import konnte nicht vollständig abgeschlossen werden.';if($message!==$this->store->get('sync:error'))$this->store->log('live','sync',$message,'error');$this->store->put('sync:error',$message);$this->store->put('sync:connected',false);$this->store->put('sync:next',microtime(true)+max(10,$e instanceof RemoteException?$e->retrySeconds:10));if(!$e instanceof RemoteException||$e->status!==429)$this->store->remove('sync:job');if($e instanceof RemoteException&&in_array($e->status,[401,403],true))$this->store->put('sync:paused',true);}
 }
 private function publish(array $job): void {
  $rows=$job['rows'];$now=gmdate('c');
  if(in_array($job['kind'],['agents','queues'],true)){
   $items=[];foreach($rows as $r){if(!isset($r['id'])||!is_string($r['name']??null))throw new RemoteException('Katalogdatensatz ohne ID oder Namen.');$item=['id'=>(string)$r['id'],'name'=>$r['name'],'description'=>(string)($r['description']??'')];if($job['kind']==='agents')$item['status']=(string)($r['status']??'unknown');foreach(['priority','ringing_time','num_loops','max_iterations'] as $key)if(isset($r[$key])&&is_numeric($r[$key]))$item[$key]=(int)$r[$key];$items[]=$item;}
   $aliases=$this->store->get('live:'.$job['kind'].':aliases',[]);foreach($items as $item)$aliases[$item['id']]=array_values(array_unique(array_merge($aliases[$item['id']]??[],[$item['name']])));$this->store->put('live:'.$job['kind'].':aliases',$aliases);$this->store->put('live:'.$job['kind'],$items);
  }elseif($job['kind']==='calls'){
   $calls=$this->analysis->adapt($rows,$this->store->get('live:agents',[]),$this->store->get('live:queues',[]),['agents'=>$this->store->get('live:agents:aliases',[]),'queues'=>$this->store->get('live:queues:aliases',[])]);
   $invalid=false;foreach($rows as $r)if(!isset($r['id'])||(!is_int($r['id'])&&!is_string($r['id']))||$r['id']===''||!Analysis::iso(is_string($r['started_at']??null)?$r['started_at']:null))$invalid=true;
   $this->store->replaceDay('live:calls',$job['date'],$calls);$this->store->put($job['date'],['date'=>$job['date'],'status'=>$invalid?'unverified':'imported','updatedAt'=>$now,'message'=>$invalid?'API-Zeilen ohne verwertbare ID oder Zeit; Zählung unvollständig.':'Zeitbasis Anrufbeginn; Dauer ab Warteschleifenaustritt als Näherung.'],'live:coverage',$job['date']);
   if($job['date']===Analysis::local(time())->format('Y-m-d'))$this->store->put('sync:lastCallsSync',$now);else $this->store->put('sync:historyDays',$this->store->get('sync:historyDays',0)+1);
  }else{
   $names=[];foreach($rows as $r){$person=trim(implode(' ',array_filter([$r['first_name']??'',$r['last_name']??''],is_string(...))));$label=mb_substr(implode(' · ',array_filter([$person,is_string($r['company']??null)?$r['company']:''])),0,300);if(!$label)continue;foreach(['phone','mobile','phone_work','mobile_work'] as $key){$n=$this->analysis->phone(is_string($r[$key]??null)?$r[$key]:null);if($n)$names[$n][$label]=true;}}
   $map=[];foreach($names as $n=>$labels)$map[$n]=count($labels)===1?array_key_first($labels):'Mehrere Kontakte ('.count($labels).')';$this->store->put('live:contactNames',$map);$this->store->put('live:contactsAt',$now);$this->store->put('live:contactsAtEpoch',time());
  }
 }
}
