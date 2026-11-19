<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;

/** Skill-based queue membership. All public writes require an explicit preview. */
final class Skills {
 public function __construct(private Store $store,private Api $api) {}
 private function wait(): void {
  $delay=(float)$this->store->get('api:next',0)-microtime(true);
  if($delay>2)throw new RemoteException('Placetel begrenzt die Abrufrate. Bitte später erneut versuchen.',429,(int)ceil($delay));
  if($delay>0)usleep((int)ceil($delay*1000000)+1000);
 }
 private function get(string $path): mixed {$this->wait();return $this->api->placetel($path);}
 private static function id(mixed $id): string {
  if(!(is_int($id)||is_string($id))||!ctype_digit((string)$id)||(float)$id<1||(float)$id>2147483647)throw new \InvalidArgumentException('Ungültige Placetel-ID.');
  return (string)(int)$id;
 }
 private function agent(mixed $id): string {
  $id=self::id($id);$known=$this->store->get('live:agents',[]);
  if(!in_array($id,array_map('strval',array_column($known,'id')),true))throw new \InvalidArgumentException('Agent nicht gefunden. Bitte zuerst die Agenten aktualisieren.');return $id;
 }
 private static function queueIds(mixed $ids): array {
  if(!is_array($ids)||!array_is_list($ids)||count($ids)>1000)throw new RemoteException('Ungültige Warteschlangen im Skill.');
  $out=[];foreach($ids as $id){$id=self::id($id);if(isset($out[$id]))throw new RemoteException('Doppelte Warteschlange im Skill.');$out[$id]=true;}
  $out=array_keys($out);sort($out,SORT_NUMERIC);return array_map('strval',$out);
 }
 private function snapshot(string $agent): array {
  if($this->store->get('mode','demo')!=='live')throw new \InvalidArgumentException('Die Placetel-Skills stehen nur im Echtbetrieb zur Verfügung.');
  $catalogue=[];$hashes=[];
  for($page=1;$page<=100;$page++){
   $rows=$this->get('/call_center_skills?per_page=100&page='.$page);
   if(!is_array($rows)||!array_is_list($rows))throw new RemoteException('Ungültiger Skill-Katalog.');
   if(!$rows)break;
   $hash=Routing::digest($rows);if(isset($hashes[$hash]))throw new RemoteException('Wiederholte Skill-Seite. Keine Änderung möglich.');$hashes[$hash]=true;
   foreach($rows as $row){if(!is_array($row)||!is_string($row['name']??null))throw new RemoteException('Ungültiger Skill.');$id=self::id($row['id']??null);if(isset($catalogue[$id]))throw new RemoteException('Doppelte Skill-ID.');$catalogue[$id]=['id'=>$id,'name'=>mb_substr($row['name'],0,200),'queueIds'=>self::queueIds($row['queue_ids']??null)];}
   if($page===100)throw new RemoteException('Skill-Katalog zu groß.');
  }
  $rows=$this->get('/call_center_agents/'.$agent.'/skills');if(!is_array($rows)||!array_is_list($rows))throw new RemoteException('Ungültige Agenten-Skills.');$current=[];
  foreach($rows as $row){if(!is_array($row))throw new RemoteException('Ungültige Agenten-Skills.');$id=self::id($row['skill_id']??null);$level=$row['skill_level']??null;if(!isset($catalogue[$id])||isset($current[$id])||!is_int($level)||$level< -2147483648||$level>2147483647||self::queueIds($row['queue_ids']??null)!==$catalogue[$id]['queueIds'])throw new RemoteException('Agenten-Skills und Skill-Katalog stimmen nicht überein. Bitte erneut laden.');$current[$id]=$level;}
  ksort($catalogue,SORT_NUMERIC);ksort($current,SORT_NUMERIC);$list=array_values($catalogue);return ['agentId'=>$agent,'skills'=>$list,'current'=>(object)$current,'catalogueHash'=>Routing::digest($list)];
 }
 public function load(mixed $agent): array {return $this->snapshot($this->agent($agent));}
 private function changes(array $snapshot,mixed $desired): array {
  if(!is_array($desired)||!array_is_list($desired)||count($desired)>1000)throw new \InvalidArgumentException('Ungültige Skill-Auswahl.');
  $catalogue=[];foreach($snapshot['skills'] as $skill)$catalogue[$skill['id']]=$skill;
  $wanted=[];foreach($desired as $item){if(!is_array($item))throw new \InvalidArgumentException('Ungültige Skill-Auswahl.');$id=self::id($item['skillId']??null);$level=$item['level']??null;if(!isset($catalogue[$id])||isset($wanted[$id])||!is_int($level)||$level< -2147483648||$level>2147483647)throw new \InvalidArgumentException('Ungültiger Skill oder ungültige Stufe.');$wanted[$id]=$level;}
  $before=(array)$snapshot['current'];$ids=array_unique(array_merge(array_keys($before),array_keys($wanted)));sort($ids,SORT_NUMERIC);$changes=[];
  foreach($ids as $id){$old=$before[$id]??null;$new=$wanted[$id]??null;if($old!==$new)$changes[]=$catalogue[$id]+['before'=>$old,'after'=>$new];}return $changes;
 }
 public function preview(mixed $agent,mixed $desired,string $user): array {
  $agent=$this->agent($agent);$snapshot=$this->snapshot($agent);$changes=$this->changes($snapshot,$desired);if(!$changes)throw new \InvalidArgumentException('Keine Änderungen ausgewählt.');
  $token=bin2hex(random_bytes(24));$this->store->put('skills:preview:'.$token,['user'=>$user,'agent'=>$agent,'snapshot'=>Routing::digest($snapshot),'changes'=>$changes,'expires'=>time()+300]);return ['token'=>$token,'changes'=>$changes];
 }
 public function apply(mixed $token,string $user): void {
  if(!is_string($token)||!preg_match('/^[a-f0-9]{48}$/D',$token))throw new \InvalidArgumentException('Ungültige Vorschau.');$key='skills:preview:'.$token;$preview=$this->store->get($key);$this->store->remove($key);
  if(!is_array($preview)||($preview['user']??null)!==$user||($preview['expires']??0)<time())throw new \InvalidArgumentException('Vorschau abgelaufen. Bitte erneut prüfen.');
  $agent=$this->agent($preview['agent']);$current=$this->snapshot($agent);if(!hash_equals($preview['snapshot'],Routing::digest($current)))throw new \InvalidArgumentException('Skills wurden seit der Vorschau verändert. Bitte erneut prüfen.');
  $this->store->put('skills:manual:'.$agent,gmdate('c'));$this->store->put('skills:running:'.$agent,['kind'=>'manual','at'=>gmdate('c')]);try{$this->writeChanges($agent,$preview['changes']);}finally{$this->store->remove('skills:running:'.$agent);}
  $this->store->log('live','skill-change','Skill-Änderung für Agent '.$agent.' bestätigt.','ok',['before'=>json_encode(array_column($preview['changes'],'before','id')),'after'=>json_encode(array_column($preview['changes'],'after','id'))]);
 }
 private function writeChanges(string $agent,array $changes): void {
  $done=0;try{foreach($changes as $change){$this->wait();$path='/call_center_agents/'.$agent.'/skills/'.$change['id'];$result=$change['after']===null?$this->api->placetel($path,'DELETE'):$this->api->placetel($path,'PUT',['skill_level'=>$change['after']]);if($change['after']!==null&&(!is_array($result)||(string)($result['skill_id']??'')!==$change['id']||($result['skill_level']??null)!==$change['after']||self::queueIds($result['queue_ids']??null)!==$change['queueIds']))throw new RemoteException('Skill-Änderung nicht bestätigt.');$done++;}}
  catch(\Throwable $e){$this->store->log('live','skill-change','Skill-Änderung teilweise oder nicht bestätigt ('.$done.' bestätigt). Anlagenstand prüfen; keine automatische Wiederholung.','error');throw new RemoteException('Änderung teilweise oder nicht bestätigt ('.$done.' bestätigt). Bitte Anlagenstand neu laden; keine automatische Wiederholung.');}
 }
 /** Check plan skill IDs and retain all affected queues for approval display. */
 public function validatePlan(mixed $agent,mixed $desired): array {$id=$this->agent($agent);$snapshot=$this->snapshot($id);$this->changes($snapshot,$desired);return $snapshot;}
 /** Cursor is persisted before writes; uncertain results are never replayed automatically. */
 public function scheduled(string $agent,array $desired,string $occurrence,string $catalogueHash): void {
  $agent=$this->agent($agent);$snapshot=$this->snapshot($agent);if(!hash_equals($catalogueHash,Routing::digest($snapshot['skills'])))throw new \InvalidArgumentException('Die Skill-Warteschlangen-Zuordnung hat sich seit der Planung geändert.');$changes=$this->changes($snapshot,$desired);
  $this->store->put('skills:cursor:'.$agent,$occurrence);$this->store->put('skills:running:'.$agent,['kind'=>'schedule','at'=>gmdate('c')]);try{$this->writeChanges($agent,$changes);}finally{$this->store->remove('skills:running:'.$agent);}
  $this->store->log('live','skill-schedule','Geplante Skill-Zuordnung für Agent '.$agent.' angewendet.','ok');
 }
}
