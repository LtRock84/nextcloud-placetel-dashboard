<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;

/** Saved schedule changes are applied only by an explicit user-created plan. */
final class SkillSchedules {
 private const ZONE='Europe/Berlin';
 public function __construct(private Store $store,private Skills $skills) {}
 public function all(): array{return $this->store->get('live:skill-plans',[]);}
 private static function day(string $date): \DateTimeImmutable {
  $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone(self::ZONE));
  if(!$d||$d->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Ungültiges Datum.');return $d;
 }
 private static function matches(array $p,\DateTimeImmutable $date): bool{return $p['kind']==='once'?$p['date']===$date->format('Y-m-d'):(in_array((int)$date->format('N'),$p['weekdays'],true)&&!in_array($date->format('Y-m-d'),$p['excludedDates']??[],true));}
 private static function overlap(array $a,array $b): bool {
  if($a['start'] >= $b['end']||$b['start'] >= $a['end'])return false;
  if($a['kind']==='once')return self::matches($b,self::day($a['date']));
  if($b['kind']==='once')return self::matches($a,self::day($b['date']));
  return (bool)array_intersect($a['weekdays'],$b['weekdays']);
 }
 public function save(array $input): array {
  if($this->store->get('mode','demo')!=='live')throw new \InvalidArgumentException('Einsatzplanung nur im Echtbetrieb.');
  $agent=$input['agentId']??null;$label=$input['label']??null;$kind=$input['kind']??null;$days=$input['weekdays']??null;$date=$input['date']??null;$start=$input['start']??null;$end=$input['end']??null;$desired=$input['skills']??null;$enabled=$input['enabled']??true;$afterEnd=$input['afterEnd']??'keep';
  if(!is_string($agent)||!is_string($label)||!trim($label)||mb_strlen($label)>120||!in_array($kind,['weekly','once'],true)||!is_array($days)||!array_is_list($days)||!is_bool($enabled)||!in_array($afterEnd,['keep','restore','set'],true)||!is_string($date)||!is_string($start)||!is_string($end)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$start)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$end)||$start >= $end)throw new \InvalidArgumentException('Bitte gültige Zeitblockdaten eingeben.');
  if(count($days)>7||count(array_unique($days))!==count($days))throw new \InvalidArgumentException('Ungültige Wochentage.');foreach($days as $day)if(!is_int($day)||$day<1||$day>7)throw new \InvalidArgumentException('Ungültige Wochentage.');
  if($kind==='weekly'&&!$days)throw new \InvalidArgumentException('Mindestens einen Wochentag auswählen.');if($kind==='once')self::day($date);
  $snapshot=$this->skills->validatePlan($agent,$desired);$agent=$snapshot['agentId'];if(!is_string($input['catalogueHash']??null)||!hash_equals($snapshot['catalogueHash'],$input['catalogueHash']))throw new \InvalidArgumentException('Skill-Zuordnungen wurden verändert. Bitte Skills erneut laden.');$endSkills=$input['endSkills']??[];if($afterEnd==='set'){$endSnapshot=$this->skills->validatePlan($agent,$endSkills);if(!hash_equals($snapshot['catalogueHash'],$endSnapshot['catalogueHash']))throw new \InvalidArgumentException('Skill-Katalog wurde verändert. Bitte erneut laden.');}$plans=$this->all();$id=$input['id']??null;if($id!==null&&(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)))throw new \InvalidArgumentException('Ungültige Plan-ID.');
  $found=$id===null;foreach($plans as $old)if($old['id']===$id)$found=true;if(!$found)throw new \InvalidArgumentException('Zeitblock nicht gefunden.');$original=null;foreach($plans as $old)if($old['id']===$id)$original=$old;
  $parentId=$input['exceptionOf']??null;$occurrence=$input['occurrenceDate']??null;$parent=null;
  if($parentId!==null){if($id!==null||!is_string($parentId)||!is_string($occurrence)||$kind!=='once'||$date!==$occurrence)throw new \InvalidArgumentException('Ungültige Serienausnahme.');$day=self::day($occurrence);foreach($plans as $candidate)if($candidate['id']===$parentId)$parent=$candidate;if(!$parent||$parent['kind']!=='weekly'||$parent['agentId']!==$agent||!self::matches($parent,$day))throw new \InvalidArgumentException('Serientermin nicht gefunden oder bereits geändert.');$now=new \DateTimeImmutable('now',new \DateTimeZone(self::ZONE));[$h,$m]=array_map('intval',explode(':',$parent['start']));if($day->setTime($h,$m)<=$now||$day->setTime(...array_map('intval',explode(':',$start)))<=$now)throw new \InvalidArgumentException('Einzelne Serientermine können nur vor ihrem Beginn geändert werden.');if(($this->store->get('skills:restore:'.$agent)['planId']??null)===$parentId)throw new \InvalidArgumentException('Diese Serie hat gerade einen aktiven Zeitblock. Bitte später erneut versuchen.');}
  if(($original['exceptionOf']??null)!==null&&($kind!=='once'||$date!==$original['date']||$agent!==$original['agentId']))throw new \InvalidArgumentException('Datum und Agent einer Serienausnahme können nicht geändert werden.');
  if(($original['exceptionOf']??null)!==null){$now=new \DateTimeImmutable('now',new \DateTimeZone(self::ZONE));$day=self::day($original['date']);if($day->setTime(...array_map('intval',explode(':',$original['start'])))<=$now||$day->setTime(...array_map('intval',explode(':',$start)))<=$now)throw new \InvalidArgumentException('Einzelne Serientermine können nur vor ihrem Beginn geändert werden.');}
  if($id!==null)foreach($plans as $old)if($old['id']===$id&&(($this->store->get('skills:restore:'.$old['agentId'])['planId']??null)===$id))throw new \InvalidArgumentException('Dieser Zeitblock ist gerade aktiv. Bitte nach seinem Ende bearbeiten oder entfernen.');
  $chosen=array_fill_keys(array_map(static fn($s)=>(string)$s['skillId'],$desired),true);$summary=array_values(array_filter($snapshot['skills'],static fn($s)=>isset($chosen[$s['id']])));
  $plan=['id'=>$id??bin2hex(random_bytes(16)),'agentId'=>$agent,'label'=>trim($label),'kind'=>$kind,'weekdays'=>$days,'date'=>$date,'start'=>$start,'end'=>$end,'enabled'=>$enabled,'afterEnd'=>$afterEnd,'endSkills'=>$afterEnd==='set'?$endSkills:[],'skills'=>$desired,'skillSummary'=>$summary,'catalogueHash'=>Routing::digest($snapshot['skills']),'createdAt'=>gmdate('c')];
  if($original&&$original['kind']==='weekly'&&$kind!=='weekly'&&!empty($original['excludedDates']))throw new \InvalidArgumentException('Eine Serie mit Ausnahmen kann nicht in einen Einzeltermin geändert werden.');
  if($original&&$kind==='weekly')$plan['excludedDates']=$original['excludedDates']??[];if($original&&isset($original['exceptionOf']))$plan['exceptionOf']=$original['exceptionOf'];if($parent){$plan['exceptionOf']=$parentId;foreach($plans as &$old)if($old['id']===$parentId){$old['excludedDates']=array_values(array_unique(array_merge($old['excludedDates']??[],[$occurrence])));}unset($old);}
  foreach($plans as $old)if($enabled&&($old['enabled']??true)&&$old['id']!==$plan['id']&&$old['agentId']===$agent&&self::overlap($old,$plan))throw new \InvalidArgumentException('Zeitblock überschneidet sich mit „'.$old['label'].'“.');
  $plans=array_values(array_filter($plans,static fn($p)=>$p['id']!==$plan['id']));$plans[]=$plan;$this->store->put('live:skill-plans',$plans);$this->store->log('live','skill-plan','Zeitblock für Agent '.$agent.' gespeichert.');return $plan;
 }
 public function remove(string $id): void {
  $plans=$this->all();$found=false;foreach($plans as $p)if($p['id']===$id)$found=true;if(!$found)throw new \InvalidArgumentException('Zeitblock nicht gefunden.');$removed=array_values(array_filter($plans,static fn($p)=>$p['id']===$id||($p['exceptionOf']??null)===$id));$remaining=array_values(array_filter($plans,static fn($p)=>$p['id']!==$id&&($p['exceptionOf']??null)!==$id));foreach($removed as $p)if((($this->store->get('skills:restore:'.$p['agentId'])['planId']??null)===$p['id']))$this->store->remove('skills:restore:'.$p['agentId']);$this->store->put('live:skill-plans',$remaining);$this->store->log('live','skill-plan','Zeitblock entfernt; Zuordnungen bleiben bestehen.');
 }
 public static function latest(array $plans,\DateTimeImmutable $now): ?array {
  $now=$now->setTimezone(new \DateTimeZone(self::ZONE));$latest=null;
  foreach($plans as $p){if(!($p['enabled']??true))continue;$days=[];if($p['kind']==='once')$days[]=self::day($p['date']);else for($n=0;$n<8;$n++)$days[]=$now->setTime(0,0)->modify('-'.$n.' days');
   foreach($days as $day){if(!self::matches($p,$day))continue;[$hour,$minute]=array_map('intval',explode(':',$p['start']));$at=$day->setTime($hour,$minute);if($at>$now||$at->getTimestamp()<strtotime($p['createdAt']))continue;[$endHour,$endMinute]=array_map('intval',explode(':',$p['end']));$end=$day->setTime($endHour,$endMinute);$stamp=$at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');if(!$latest||strcmp($stamp,$latest['at'])>0)$latest=['plan'=>$p,'at'=>$stamp,'endAt'=>$end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')];}
  }return $latest;
 }
 public function tick(): bool {
  if($this->store->get('mode','demo')!=='live')return false;$plans=$this->all();$agents=array_unique(array_merge(array_map('strval',array_column($plans,'agentId')),array_map('strval',array_column($this->store->get('live:agents',[]),'id'))));$now=new \DateTimeImmutable('now',new \DateTimeZone(self::ZONE));
  foreach($agents as $agent){if($this->store->get('skills:running:'.$agent)){$this->store->remove('skills:running:'.$agent);$this->store->log('live','skill-schedule','Unterbrochene Skill-Änderung für Agent '.$agent.'. Anlagenstand prüfen; keine automatische Wiederholung.','error');}
   $restore=$this->store->get('skills:restore:'.$agent);if(is_array($restore)&&strtotime((string)($restore['endAt']??''))<=$now->getTimestamp()){
    $manual=(int)strtotime((string)$this->store->get('skills:manual:'.$agent,''));if($manual>=(int)strtotime((string)$restore['startedAt'])){$this->store->remove('skills:restore:'.$agent);$this->store->log('live','skill-schedule','Manuelle Änderung bleibt bestehen; vorheriger Zustand nicht wiederhergestellt.','info');return true;}
    try{$this->skills->restore($agent,$restore);}catch(RemoteException $e){if($e->status!==429){$this->store->remove('skills:restore:'.$agent);$this->store->log('live','skill-schedule','Wiederherstellung nicht bestätigt. Anlagenstand prüfen; keine automatische Wiederholung.','error');}}catch(\Throwable){$this->store->remove('skills:restore:'.$agent);$this->store->log('live','skill-schedule','Wiederherstellung übersprungen oder nicht bestätigt. Anlagenstand prüfen.','error');}return true;
   }
   $latest=self::latest(array_values(array_filter($plans,static fn($p)=>$p['agentId']===$agent)),$now);if(!$latest)continue;$after=max((int)strtotime((string)$this->store->get('skills:manual:'.$agent,'')),(int)strtotime((string)$this->store->get('skills:cursor:'.$agent,'')));if(strtotime($latest['at'])<=$after)continue;
   $endMode=$latest['plan']['afterEnd']??'keep';$needsRestore=in_array($endMode,['restore','set'],true);if($needsRestore&&strtotime($latest['endAt'])<=$now->getTimestamp()){$this->store->put('skills:cursor:'.$agent,$latest['at']);$this->store->log('live','skill-schedule','Verpasster Zeitblock bereits beendet; keine nachträgliche Änderung gesendet.','info');return true;}
   $info=$needsRestore?['planId'=>$latest['plan']['id'],'endAt'=>$latest['endAt']]+($endMode==='set'?['target'=>$latest['plan']['endSkills']??[]]:[]):null;
   try{$this->skills->scheduled($agent,$latest['plan']['skills'],$latest['at'],$latest['plan']['catalogueHash']??'',$info);}catch(RemoteException $e){if($e->status!==429){$this->store->put('skills:cursor:'.$agent,$latest['at']);$this->store->log('live','skill-schedule','Geplanter Wechsel nicht bestätigt. Bitte Anlagenstand prüfen.','error');}}catch(\Throwable){$this->store->put('skills:cursor:'.$agent,$latest['at']);$this->store->log('live','skill-schedule','Geplanter Wechsel nicht bestätigt. Bitte Anlagenstand prüfen.','error');}return true;
  }return false;
 }
}
