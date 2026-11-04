<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;

/** Separate call-level sources. No matching of IDs or telephone numbers across APIs. */
final class IncomingCalls {
 public function __construct(private Store $store,private Analysis $analysis,private Api $api) {}
 private function day(mixed $day): string {
  if(!is_string($day)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day))throw new \InvalidArgumentException('Ungültiger Tag.');
  $zone=new \DateTimeZone(Analysis::ZONE);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,$zone);
  if(!$d||$d->format('Y-m-d')!==$day||$day>(new \DateTimeImmutable('now',$zone))->format('Y-m-d'))throw new \InvalidArgumentException('Ungültiger Tag.');return $day;
 }
 private function status(array $rows,string $mode): array {
  $states=$this->store->get($mode.':incoming-status',[]);foreach($rows as &$row){$key=hash('sha256',json_encode([$row['source'],$row['id']],JSON_THROW_ON_ERROR));$saved=$states[$key]??[];$row['state']=$saved['state']??'open';$row['note']=$saved['note']??'';}unset($row);return $rows;
 }
 public function sync(mixed $value,string $mode): void {
  $day=$this->day($value);if($mode!=='live')throw new \InvalidArgumentException('Anrufimport ist nur im Echtbetrieb verfügbar.');
  $all=[];$seen=[];$pages=[];$started=microtime(true);
  for($page=1;$page<=101;$page++){
   if(microtime(true)-$started>20)throw new RemoteException('Anrufliste benötigt zu lange; bisherige Daten bleiben erhalten.');
   $delay=$this->store->get('api:next',0)-microtime(true);if($delay>1.5)throw new RemoteException('API-Zeitfenster noch nicht frei. Bitte später erneut versuchen.',429,(int)ceil($delay));if($delay>0)usleep((int)($delay*1000000)+1000);
   $batch=$this->api->page('/calls?filter%5Bdate%5D='.$day,$page);if(!$batch)break;
   $fingerprint=hash('sha256',json_encode($batch,JSON_THROW_ON_ERROR));if(isset($pages[$fingerprint]))throw new RemoteException('Wiederholte Anrufseite; bisherige Daten bleiben erhalten.');$pages[$fingerprint]=true;
   if($page===101||count($all)+count($batch)>10000)throw new RemoteException('Zu viele Anrufe; bisherige Daten bleiben erhalten.');
   foreach($batch as $r){$id=$r['id']??null;$at=Analysis::iso(is_string($r['received_at']??null)?$r['received_at']:null);$type=strtolower((string)($r['type']??''));if((!is_string($id)&&!is_int($id))||(string)$id===''||!$at||Analysis::local(Analysis::stamp($at))->format('Y-m-d')!==$day)throw new RemoteException('Anrufdaten unvollständig oder außerhalb des Tags; bisherige Daten bleiben erhalten.');
    $num=$r['to_number']['number']??null;$destination=is_array($num)?($num['e164']??$num['number']??null):null;
    $row=['id'=>(string)$id,'source'=>'placetel-calls','caller'=>$this->analysis->phone(is_string($r['from_number']??null)?$r['from_number']:null),'destination'=>$this->analysis->phone(is_string($destination)?$destination:null),'at'=>$at,'timeBasis'=>'provider','outcome'=>in_array($type,['accepted','missed','voicemail','blocked'],true)?$type:'unknown'];
    $key=(string)$id;if(isset($seen[$key])&&$seen[$key]!==$row)throw new RemoteException('Widersprüchliche Anruf-ID; bisherige Daten bleiben erhalten.');if(!isset($seen[$key])){$all[]=$row;$seen[$key]=$row;}
   }
  }
  $this->store->replaceDay('live:incoming',$day,$all);$this->store->put($day,['date'=>$day,'updatedAt'=>gmdate('c'),'status'=>'imported'],'live:incoming-coverage',$day);
 }
 public function report(mixed $value,string $mode): array {
  $day=$this->day($value);$calls=$this->store->rows($mode.':incoming',$day,$day);$notify=[];
  if($mode==='live')foreach($this->store->rows('notify-calls') as $c){$at=Analysis::iso(is_string($c['firstReceivedAt']??null)?$c['firstReceivedAt']:null);if(!$at||($c['direction']??'')!=='in'||Analysis::local(Analysis::stamp($at))->format('Y-m-d')!==$day)continue;
   $type=strtolower((string)($c['hangupType']??''));$accepted=in_array('CallAccepted',$c['events']??[],true);$outcome=$accepted&&$type!==''&&$type!=='accepted'?'unknown':($accepted||$type==='accepted'?'accepted':(in_array($type,['missed','voicemail','blocked'],true)?$type:'unknown'));
   $notify[]=['id'=>(string)$c['id'],'source'=>'notify','caller'=>$this->analysis->phone($c['caller']??null),'destination'=>$this->analysis->phone($c['to']??null),'at'=>$at,'timeBasis'=>'received','outcome'=>$outcome];
  }
  return ['day'=>$day,'calls'=>$this->status($calls,$mode),'notify'=>$this->status($notify,$mode),'coverage'=>$this->store->get($day,null,$mode.':incoming-coverage')];
 }
 public function save(array $input,string $mode): void {
  $day=$this->day($input['day']??null);$source=$input['source']??null;$id=$input['id']??null;$state=$input['state']??null;$note=$input['note']??null;
  if(!in_array($source,['placetel-calls','notify'],true)||!is_string($id)||strlen($id)>128||!in_array($state,['open','later','done'],true)||!is_string($note)||mb_strlen($note)>1000)throw new \InvalidArgumentException('Ungültiger Rückrufstatus.');
  $report=$this->report($day,$mode);$found=false;foreach(array_merge($report['calls'],$report['notify']) as $row)if($row['source']===$source&&$row['id']===$id&&$row['outcome']==='missed')$found=true;
  if(!$found)throw new \InvalidArgumentException('Verpasster Anruf nicht gefunden.');$states=$this->store->get($mode.':incoming-status',[]);$key=hash('sha256',json_encode([$source,$id],JSON_THROW_ON_ERROR));$states[$key]=['state'=>$state,'note'=>$note];$this->store->put($mode.':incoming-status',$states);
 }
}
