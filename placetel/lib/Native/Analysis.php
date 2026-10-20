<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\IPhoneNumberUtil;
final class Analysis {
 public const ZONE='Europe/Berlin';
 public function __construct(private IPhoneNumberUtil $phones) {}
 public static function iso(?string $value): ?string {
  if(!$value||!preg_match('/[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}:?\d{2})$/',$value))return null;
  try{$date=new \DateTimeImmutable($value);$errors=\DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))return null;return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');}catch(\Throwable){return null;}
 }
 public static function stamp(?string $value): float {try{return $value?(float)(new \DateTimeImmutable($value))->format('U.u'):NAN;}catch(\Throwable){return NAN;}}
 public static function local(float $seconds): \DateTimeImmutable {return (new \DateTimeImmutable('@'.(int)$seconds))->setTimezone(new \DateTimeZone(self::ZONE));}
 public function phone(?string $value): ?string {
  if(!$value||preg_match('/^(anonymous|unknown|private|restricted|unbekannt|unterdrückt)$/iu',trim($value)))return null;
  return $this->phones->convertToStandardFormat(preg_replace('/^00/','+',trim($value)),'DE');
 }
 public static function range(string $from,string $to,string $asOf): array {
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$to))throw new \InvalidArgumentException('Ungültiger Datumsbereich.');
  $zone=new \DateTimeZone(self::ZONE);$start=\DateTimeImmutable::createFromFormat('!Y-m-d',$from,$zone);$last=\DateTimeImmutable::createFromFormat('!Y-m-d',$to,$zone);
  if(!$start||!$last||$start->format('Y-m-d')!==$from||$last->format('Y-m-d')!==$to||$last<$start||$start->diff($last)->days>366)throw new \InvalidArgumentException('Bitte einen gültigen Zeitraum von höchstens 367 Tagen wählen.');
  $end=min((float)$last->modify('+1 day')->format('U'),self::stamp($asOf));$dates=[];
  for($d=$start;$d<=$last&&$d->getTimestamp()<$end;$d=$d->modify('+1 day'))$dates[]=$d->format('Y-m-d');
  return [(float)$start->format('U'),$end,$dates];
 }
 public static function coverage(array $coverage,array $dates): array {
  $map=array_column($coverage,null,'date');$out=['complete'=>0,'imported'=>0,'expected'=>count($dates),'missing'=>[],'unverified'=>[]];
  foreach($dates as $day){$status=$map[$day]['status']??null;if($status==='complete')$out['complete']++;if(in_array($status,['complete','imported'],true))$out['imported']++;if($status===null||$status==='error')$out['missing'][]=$day;if($status==='unverified')$out['unverified'][]=$day;}return $out;
 }
 public function adapt(array $rows,array $agents,array $queues,array $aliases): array {
  $resolve=static function($name,$items,$known){$ids=[];foreach($items as $i)if($i['name']===$name)$ids[$i['id']]=true;foreach($known as $id=>$names)if(in_array($name,$names,true))$ids[$id]=true;return count($ids)===1?(string)array_key_first($ids):'';};$out=[];
  foreach($rows as $r){$started=self::iso($r['started_at']??null);if(!isset($r['id'])||(!is_int($r['id'])&&!is_string($r['id']))||$r['id']===''||!$started)continue;$status=strtolower((string)($r['call_status']??''));$answer=strtolower((string)($r['answer_status']??''));$ended=self::iso($r['ended_at']??null);$left=self::iso($r['left_queue_at']??null);$answered=$answer==='answer'&&!in_array($status,['not_answered','cancelled'],true);
   $c=['id'=>'placetel:'.$r['id'],'rootId'=>'placetel:'.$r['id'],'source'=>'placetel','agentId'=>$resolve($r['agent_name']??null,$agents,$aliases['agents']??[]),'queueId'=>$resolve($r['queue_name']??null,$queues,$aliases['queues']??[]),'agentName'=>(string)($r['agent_name']??''),'queueName'=>(string)($r['queue_name']??''),'caller'=>is_string($r['from']??null)?$r['from']:null,'offeredAt'=>$started,'referenceAt'=>$started,'acceptedAt'=>null,'endedAt'=>$ended,'durationStart'=>$answered&&$left&&self::stamp($left)>=self::stamp($started)?$left:null,'durationVerified'=>false,'outcome'=>$answered?'accepted':(in_array($status,['unassigned','assigned'],true)&&!$ended?'waiting':(in_array($status,['ended','not_answered','cancelled'],true)?'missed':'unknown')),'callType'=>strtolower((string)($r['call_type']??'')),'callStatus'=>$status,'answerStatus'=>$answer,'iteration'=>(string)($r['iteration']??''),'updatedAt'=>self::iso($r['last_updated_at']??null)??$started];
   if(!isset($out[$c['id']])||self::stamp($c['updatedAt'])>=self::stamp($out[$c['id']]['updatedAt']))$out[$c['id']]=$c;
  }return array_values($out);
 }
 public function statistics(array $records,array $coverage,array $options): array {
  ['from'=>$from,'to'=>$to,'asOf'=>$asOf]=$options;[$start,$end,$dates]=self::range($from,$to,$asOf);$now=self::stamp($asOf);$live=($options['source']??null)==='placetel'||count(array_filter($records,static fn($c)=>($c['source']??null)==='placetel'))>0;
  $at=static fn($c)=>$c['acceptedAt']??$c['referenceAt']??$c['offeredAt'];$unique=[];foreach($records as $c)$unique[$c['id']]=$c;
  $records=array_values(array_filter($unique,static fn($c)=>(!isset($c['source'])||($c['callType']??'')==='dail_in')&&(!($options['agentId']??'')||$c['agentId']===$options['agentId'])&&(!($options['queueId']??'')||$c['queueId']===$options['queueId'])));
  $calls=[];$distinct=[];$known=[];$estimates=[];$anonymous=0;$completed=0;
  foreach($records as $c){$t=self::stamp($at($c));if($c['outcome']!=='accepted'||$t<$start||$t>=$end)continue;$a=self::stamp($c['acceptedAt']);$b=self::stamp($c['endedAt']);$d=self::stamp($c['durationStart']??null);
   $c['seconds']=$c['durationVerified']&&is_finite($a)&&is_finite($b)&&$b>=$a&&$b<=$now?$b-$a:null;
   $c['estimatedSeconds']=isset($c['source'])&&is_finite($d)&&is_finite($b)&&$b>=$d&&$b<=$now?$b-$d:null;$c['normalizedCaller']=$this->phone($c['caller']);
   if($c['normalizedCaller'])$distinct[$c['normalizedCaller']]=true;else $anonymous++;if($c['seconds']!==null)$known[]=$c['seconds'];if($c['estimatedSeconds']!==null)$estimates[]=$c['estimatedSeconds'];if(is_finite($b)&&$b<=$now)$completed++;$calls[]=$c;
  }
  usort($calls,static fn($a,$b)=>self::stamp($at($b))<=>self::stamp($at($a)));$hours=[];$tod=[];
  for($h=0;$h<24;$h++)$tod[]=['key'=>(string)$h,'label'=>sprintf('%02d:00',$h),'accepted'=>0,'minutes'=>0];
  for($t=$start;$t<$end;$t+=3600){$local=self::local($t);$hours[(int)$t]=['key'=>$local->format('Y-m-d\TH:i:s.vP'),'label'=>$local->format('d.m. H:i P'),'accepted'=>0,'minutes'=>0];}
  foreach($calls as $c){$t=self::stamp($at($c));$key=(int)($start+floor(($t-$start)/3600)*3600);if(isset($hours[$key]))$hours[$key]['accepted']++;$tod[(int)self::local($t)->format('G')]['accepted']++;}
  foreach($records as $c){$begin=$c['durationVerified']?$c['acceptedAt']:(isset($c['source'])?($c['durationStart']??null):null);$a=self::stamp($begin);$b=self::stamp($c['endedAt']);if($c['outcome']!=='accepted'||!is_finite($a)||!is_finite($b)||$b<$a||$b>$now)continue;
   for($t=max($a,$start),$stop=min($b,$end);$t<$stop;){$key=(int)($start+floor(($t-$start)/3600)*3600);$next=min($stop,$key+3600);$minutes=($next-$t)/60;if(isset($hours[$key]))$hours[$key]['minutes']+=$minutes;$tod[(int)self::local($t)->format('G')]['minutes']+=$minutes;$t=$next;}
  }
  $failed=$ongoing=$waiting=$unmappedAgents=$unmappedQueues=0;
  foreach($records as $c){$t=self::stamp($at($c));if($c['outcome']==='missed'&&self::stamp($c['offeredAt'])>=$start&&self::stamp($c['offeredAt'])<$end)$failed++;if($end===$now&&$c['outcome']==='accepted'&&$t<$end&&$t>$end-86400&&(!$c['endedAt']||self::stamp($c['endedAt'])>$now))$ongoing++;if($c['outcome']==='waiting'&&self::stamp($c['offeredAt'])<$end&&!$c['endedAt'])$waiting++;}
  foreach($calls as $c){if(!$c['agentId'])$unmappedAgents++;if(!$c['queueId'])$unmappedQueues++;}
  return ['from'=>$from,'to'=>$to,'asOf'=>$asOf,'accepted'=>count($calls),'uniqueCallers'=>count($distinct),'anonymousCalls'=>$anonymous,'durationSeconds'=>array_sum($known),'averageSeconds'=>$known?array_sum($known)/count($known):null,'durationKnown'=>count($known),'completed'=>$completed,'ongoing'=>$ongoing,'waiting'=>$waiting,'estimatedDurationSeconds'=>array_sum($estimates),'estimatedAverageSeconds'=>$estimates?array_sum($estimates)/count($estimates):null,'estimateKnown'=>count($estimates),'timeBasis'=>$live?'started':'accepted','durationBasis'=>$live?'queue-exit-estimate':'verified','failedAttempts'=>$failed,'unmappedAgents'=>$unmappedAgents,'unmappedQueues'=>$unmappedQueues,'coverage'=>self::coverage($coverage,$dates),'hourly'=>array_values($hours),'timeOfDay'=>$tod,'calls'=>$calls];
 }
}
