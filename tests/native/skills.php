<?php
declare(strict_types=1);
// Standalone synthetic provider; never connects to a real telephone system.
namespace OCA\Placetel\Native {
 final class RemoteException extends \RuntimeException {public function __construct(string $m,public int $status=0,public int $retrySeconds=0){parent::__construct($m);}}
 final class Store {public array $data=['mode'=>'live','live:agents'=>[['id'=>'1']]];public array $logs=[];public function get(string $k,mixed $d=null): mixed{return $this->data[$k]??$d;}public function put(string $k,mixed $v): void{$this->data[$k]=$v;}public function remove(string $k): void{unset($this->data[$k]);}public function log(string $mode,string $action,string $message,string $status='ok',array $extra=[]): void{$this->logs[]=[$mode,$action,$message,$status];}}
 final class Routing {public static function digest(mixed $value): string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}}
 final class Api {
  public array $catalogue=[['id'=>10,'name'=>'Support','queue_ids'=>[21,22]],['id'=>11,'name'=>'Other','queue_ids'=>[23]]];public array $current=[['skill_id'=>10,'skill_level'=>2,'queue_ids'=>[21,22]]];public array $writes=[];public bool $fail=false;
  public function placetel(string $path,string $method='GET',?array $body=null): mixed {
   if($path==='/call_center_skills?per_page=100&page=1')return $this->catalogue;
   if($path==='/call_center_skills?per_page=100&page=2')return [];
   if($path==='/call_center_agents/1/skills')return $this->current;
   if(preg_match('~^/call_center_agents/1/skills/(\d+)$~',$path,$m)){$this->writes[]=[$method,$path,$body];if($this->fail)throw new RemoteException('synthetic failure');$id=(int)$m[1];$this->current=array_values(array_filter($this->current,fn($s)=>$s['skill_id']!==$id));if($method==='DELETE')return null;foreach($this->catalogue as $s)if($s['id']===$id){$r=['skill_id'=>$id,'skill_level'=>$body['skill_level'],'queue_ids'=>$s['queue_ids']];$this->current[]=$r;return $r;}}
   throw new \RuntimeException('Unexpected fixture path');
  }
 }
}
namespace {
 $native=__DIR__.'/../../../nextcloud/placetel/lib/Native/';if(!is_dir($native))$native=__DIR__.'/../../placetel/lib/Native/';
 require $native.'Skills.php';require $native.'SkillSchedules.php';require $native.'Subscriptions.php';
 use OCA\Placetel\Native\{Skills,SkillSchedules,Subscriptions,Store,Api};
 function check(bool $v,string $label): void{if(!$v)throw new \RuntimeException($label);echo "PASS $label\n";}
 function rejects(callable $f): bool{try{$f();return false;}catch(\Throwable){return true;}}
 $store=new Store();$api=new Api();$skills=new Skills($store,$api);
 $snapshot=$skills->load('1');check(count($snapshot['skills'])===2&&$snapshot['skills'][0]['queueIds']===['21','22'],'load full catalogue and shared queues');
 $preview=$skills->preview('1',[['skillId'=>'10','level'=>5],['skillId'=>'11','level'=>3]],'admin');check(count($preview['changes'])===2&&$preview['changes'][0]['queueIds']===['21','22'],'preview includes shared queues');check(count($api->writes)===0,'preview makes no writes');
 check(rejects(fn()=> $skills->apply($preview['token'],'other')),'preview bound to user');
 $preview=$skills->preview('1',[['skillId'=>'10','level'=>5]],'admin');$api->current[0]['skill_level']=4;check(rejects(fn()=> $skills->apply($preview['token'],'admin'))&&count($api->writes)===0,'stale preview rejected');
 $preview=$skills->preview('1',[['skillId'=>'10','level'=>5],['skillId'=>'11','level'=>3]],'admin');$skills->apply($preview['token'],'admin');check(count($api->writes)===2&&$api->current[0]['skill_level']===5,'confirmed changes applied');check(rejects(fn()=> $skills->apply($preview['token'],'admin')),'preview token is single use');
 $preview=$skills->preview('1',[],'admin');$api->fail=true;check(rejects(fn()=> $skills->apply($preview['token'],'admin'))&&end($store->logs)[3]==='error','failed change is logged and not retried');
 $api->fail=false;$api->current[0]['queue_ids']=[21];check(rejects(fn()=> $skills->load('1')),'inconsistent queue mappings rejected');
 $api->current=[['skill_id'=>10,'skill_level'=>2,'queue_ids'=>[21,22]]];$scheduler=new SkillSchedules($store,$skills);
 $base=['agentId'=>'1','label'=>'Morning','kind'=>'weekly','weekdays'=>[1,2,3,4,5],'date'=>'','start'=>'09:00','end'=>'12:00','catalogueHash'=>$skills->load('1')['catalogueHash'],'skills'=>[['skillId'=>'10','level'=>5]]];
 $saved=$scheduler->save($base);check(count($scheduler->all())===1,'weekly schedule saved');
 check(rejects(fn()=> $scheduler->save(array_replace($base,['label'=>'Overlap','start'=>'11:00','end'=>'13:00']))),'overlap rejected');
 $scheduler->save(array_replace($base,['label'=>'Afternoon','start'=>'12:00','end'=>'17:00']));check(count($scheduler->all())===2,'adjacent schedules allowed');
 $scheduler->remove($saved['id']);check(count($scheduler->all())===1,'removal keeps other plan');
 $sample=['id'=>'abc','agentId'=>'1','label'=>'Morning','kind'=>'weekly','weekdays'=>[1,2,3,4,5,6,7],'date'=>'','start'=>'09:00','end'=>'17:00','skills'=>[],'createdAt'=>'2025-01-01T00:00:00Z'];
 $now=new \DateTimeImmutable('2026-10-25T12:00:00+01:00');$occurrence=SkillSchedules::latest([$sample],$now);check($occurrence['at']==='2026-10-25T08:00:00Z','latest occurrence respects Europe/Berlin');
 $spring=array_replace($sample,['start'=>'02:30']);$occurrence=SkillSchedules::latest([$spring],new \DateTimeImmutable('2026-03-29T05:00:00+02:00'));check($occurrence['at']==='2026-03-29T01:30:00Z','spring DST gap shifts once to valid time');
 check(SkillSchedules::latest([array_replace($sample,['enabled'=>false])],$now)===null,'paused plan does not run');
 $nowLocal=new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin'));$before=$nowLocal->modify('-2 minutes');$sample['kind']='once';$sample['date']=$before->format('Y-m-d');$sample['start']=$before->format('H:i');$sample['end']=$nowLocal->format('H:i');$sample['skills']=[['skillId'=>'10','level'=>7]];$sample['catalogueHash']=$saved['catalogueHash'];$store->put('live:skill-plans',[$sample]);$store->remove('skills:manual:1');$store->remove('skills:cursor:1');$writes=count($api->writes);check($scheduler->tick()&&count($api->writes)===$writes+1,'scheduled skill change executed');check(!$scheduler->tick()&&count($api->writes)===$writes+1,'scheduled change is not replayed');
 $store->remove('skills:cursor:1');$store->put('skills:manual:1',gmdate('c'));$writes=count($api->writes);check(!$scheduler->tick()&&count($api->writes)===$writes,'manual change suppresses earlier schedule');$store->remove('skills:manual:1');
 $store->put('skills:running:1',['kind'=>'schedule']);$store->put('skills:cursor:1',gmdate('c'));check(!$scheduler->tick()&&!$store->get('skills:running:1')&&end($store->logs)[3]==='error','interrupted write is logged and never replayed');$store->remove('skills:cursor:1');
 $api->catalogue[0]['queue_ids'][]=24;$api->current[0]['queue_ids'][]=24;$writes=count($api->writes);check($scheduler->tick()&&count($api->writes)===$writes&&end($store->logs)[3]==='error','changed skill-to-queue mapping blocks scheduled write');
 $diagnostic=Subscriptions::diagnostic([['url'=>'https://test:secret@example.test/private-token?key=hidden','incoming'=>true,'hungup'=>true,'numbers'=>['1','2']]]);$encoded=json_encode($diagnostic);check(!str_contains($encoded,'secret')&&!str_contains($encoded,'private-token')&&!str_contains($encoded,'hidden')&&$diagnostic['subscriptions'][0]['origin']==='https://example.test','webhook diagnostic redacts path query and credentials');
 check(rejects(fn()=>Subscriptions::diagnostic(['unexpected'])),'malformed subscriptions rejected');
 echo "SKILLS_TEST_COMPLETE\n";
}
