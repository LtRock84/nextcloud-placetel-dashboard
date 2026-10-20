<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
final class Demo {
 public function __construct(private Store $store) {}
 public function seed(): void {
  if(!$this->store->get('demo:agents'))$this->store->put('demo:agents',[['id'=>'a1','name'=>'Anna Weber','status'=>'online'],['id'=>'a2','name'=>'Ben Fischer','status'=>'working'],['id'=>'a3','name'=>'Clara Neumann','status'=>'online'],['id'=>'a4','name'=>'David Hoffmann','status'=>'pause'],['id'=>'a5','name'=>'Emma Schneider','status'=>'offline']]);
  if(!$this->store->get('demo:queues'))$this->store->put('demo:queues',[['id'=>'q1','name'=>'Kundenservice'],['id'=>'q2','name'=>'Vertrieb'],['id'=>'q3','name'=>'Technik']]);
  $today=Analysis::local(time())->setTime(0,0);if($this->store->get('demo:seedDay')!==$today->format('Y-m-d')){
   for($d=61;$d>=0;$d--){$day=$today->modify('-'.$d.' days');$date=$day->format('Y-m-d');$rows=[];for($i=0;$i<12;$i++){$start=$day->setTime(8+intdiv($i,2),5+($i%2)*25);if($start->getTimestamp()>time())continue;$at=$start->format('c');$end=$start->modify('+'.(60+$i*30).' seconds')->format('c');$rows[]=['id'=>'demo:'.$date.':'.$i,'rootId'=>'demo:'.$date.':'.$i,'agentId'=>'a'.(1+$i%5),'queueId'=>'q'.(1+$i%3),'caller'=>$i===11?null:'+4930123456'.sprintf('%02d',10+$i%8),'offeredAt'=>$at,'acceptedAt'=>$i%4===0?null:$at,'endedAt'=>$end,'outcome'=>$i%4===0?'missed':'accepted','durationVerified'=>true];}$this->store->replaceDay('demo:calls',$date,$rows);$this->store->put($date,['date'=>$date,'status'=>'complete','updatedAt'=>gmdate('c')],'demo:coverage',$date);}
   $this->store->put('demo:seedDay',$today->format('Y-m-d'));
  }
  $now=time();$date=$today->format('Y-m-d');foreach([1,2] as $i)$this->store->put('demo:waiting:'.$i,['id'=>'demo:waiting:'.$i,'rootId'=>'demo:waiting:'.$i,'agentId'=>'','queueId'=>'q'.$i,'caller'=>'+493012345678','offeredAt'=>gmdate('c',$now-25*$i),'acceptedAt'=>null,'endedAt'=>null,'outcome'=>'waiting','durationVerified'=>false],'demo:calls',$date);
 }
}
