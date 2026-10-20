<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
final class Routing {
 public function __construct(private Store $store,private Api $api) {}
 private function mode(): string{return $this->store->get('mode','demo');}
 private function key(string $name): string{return $this->mode().':routing:'.$name;}
 public function state(): array{return ['routes'=>$this->store->get($this->key('routes'),[]),'plans'=>$this->store->get($this->key('plans'),[]),'profiles'=>$this->store->get($this->key('profiles'),[]),'loadedAt'=>$this->store->get($this->key('loadedAt'))];}
 public static function digest(mixed $value): string {if(is_array($value)){if(!array_is_list($value))ksort($value);foreach($value as &$v)if(is_array($v))$v=self::canonical($v);unset($v);}return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
 private static function canonical(array $v): array {if(!array_is_list($v))ksort($v);foreach($v as &$n)if(is_array($n))$n=self::canonical($n);return $v;}
 public function load(): void {
  if($this->mode()==='demo'){$this->store->put($this->key('routes'),[['id'=>'100','name'=>'Beispiel-Hotline']]);$this->store->put($this->key('plans'),[['id'=>'200','name'=>'Normalbetrieb'],['id'=>'201','name'=>'Feiertag / Notfall']]);$this->store->put($this->key('loadedAt'),gmdate('c'));return;}
  if(!$this->store->get('routing:load'))$this->store->put('routing:load',['kind'=>'routes','page'=>1,'routes'=>[],'plans'=>[]]);
 }
 public function save(array $input): array {
  $s=$this->state();if(!is_string($input['label']??null)||!trim($input['label'])||mb_strlen($input['label'])>120||!in_array($input['routingId']??null,array_column($s['routes'],'id'),true)||!in_array($input['planId']??null,array_column($s['plans'],'id'),true)||!ctype_digit($input['planId'])||(float)$input['planId']>2147483647)throw new \InvalidArgumentException('Bezeichnung, vorhandenes Routing und Zielplan wählen.');
  $at=$input['activateAt']??null;if($at!==null){$at=Analysis::iso(is_string($at)?$at:null);if(!$at||Analysis::stamp($at)<=time())throw new \InvalidArgumentException('Ausführungszeitpunkt muss mit Zeitzone in der Zukunft liegen.');}
  foreach($s['profiles'] as $p)if($at&&$p['state']==='pending'&&$p['routingId']===$input['routingId']&&$p['activateAt']===$at)throw new \InvalidArgumentException('Zu diesem Zeitpunkt ist bereits ein Wechsel gespeichert.');
  $p=['id'=>bin2hex(random_bytes(16)),'label'=>trim($input['label']),'routingId'=>$input['routingId'],'planId'=>$input['planId'],'activateAt'=>$at,'state'=>$at?'pending':'ready'];$s['profiles'][]=$p;$this->store->put($this->key('profiles'),$s['profiles']);$this->store->log($this->mode(),'routing-profile','Routingvorlage gespeichert'.($at?' – Ausführung im Nextcloud-Cron-Takt.':'.'));return $p;
 }
 public function remove(string $id): void {$s=$this->state();$found=false;$out=[];foreach($s['profiles'] as $p){if($p['id']===$id){if($p['state']==='executing')throw new \InvalidArgumentException('Umschaltung läuft.');$found=true;}else $out[]=$p;}if(!$found)throw new \InvalidArgumentException('Vorlage nicht gefunden.');$this->store->put($this->key('profiles'),$out);}
 private function profile(string $id): array {foreach($this->state()['profiles'] as $p)if($p['id']===$id&&$p['state']!=='executing')return $p;throw new \InvalidArgumentException('Vorlage nicht verfügbar.');}
 private function route(string $id): array {$route=$this->mode()==='demo'?$this->store->get('demo:route:'.$id,['id'=>$id,'plan'=>['id'=>'200']]):$this->api->placetel('/routings/'.rawurlencode($id));if(!is_array($route)||(string)($route['id']??'')!==$id)throw new RemoteException('Anlagenantwort enthält eine andere Routing-ID.');return $route;}
 public function preview(string $id): array {$p=$this->profile($id);$before=$this->route($p['routingId']);$token=bin2hex(random_bytes(24));$this->store->put('preview:'.$token,['profileId'=>$id,'hash'=>self::digest($before),'expires'=>time()+300]);return ['token'=>$token,'before'=>$before,'target'=>['plan'=>['id'=>(int)$p['planId']]],'profile'=>$p];}
 private function update(string $id,string $state,string $message): void {$out=[];foreach($this->state()['profiles'] as $p){if($p['id']===$id){$p['state']=$state;$p['message']=$message;$p['executingAt']=$state==='executing'?time():null;}$out[]=$p;}$this->store->put($this->key('profiles'),$out);}
 public function apply(string $id,?string $token=null,bool $scheduled=false): void {
  $p=$this->profile($id);$preview=$token?$this->store->get('preview:'.$token):null;if(!$scheduled&&(!$preview||$preview['profileId']!==$id||$preview['expires']<time()))throw new \InvalidArgumentException('Vorschau abgelaufen. Bitte neu prüfen.');$before=$this->route($p['routingId']);if($preview&&self::digest($before)!==$preview['hash'])throw new \InvalidArgumentException('Routing seit Vorschau verändert. Bitte neu prüfen.');
  if($this->mode()==='live'){$delay=$this->store->get('api:next',0)-microtime(true);if($delay>1.5)throw new RemoteException('API-Zeitfenster noch nicht frei. Bitte kurz warten.',429,(int)ceil($delay));if($delay>0)usleep((int)($delay*1000000)+1000);}
  $this->update($id,'executing','Umschaltung läuft.');$payload=['plan'=>['id'=>(int)$p['planId']]];if($token)$this->store->remove('preview:'.$token);
  try{$result=$this->mode()==='demo'?['id'=>$p['routingId']]+$payload:$this->api->placetel('/routings/'.rawurlencode($p['routingId']),'PUT',$payload);if((string)($result['id']??'')!==$p['routingId']||(string)($result['plan']['id']??'')!==$p['planId'])throw new RemoteException('Zielplan nicht bestätigt.');if($this->mode()==='demo')$this->store->put('demo:route:'.$p['routingId'],$result);$this->update($id,'done','Zielplan bestätigt.');$this->store->log($this->mode(),'routing','Routing angewendet.','ok',['before'=>json_encode($before),'after'=>json_encode($payload)]);}
  catch(\Throwable $e){$this->update($id,'error','Nicht bestätigt. Anlagenstand prüfen; keine automatische Wiederholung.');$this->store->log($this->mode(),'routing','Routing nicht bestätigt; keine automatische Wiederholung.','error');throw $e;}
 }
 public function simulate(string $id,string $at): mixed {if(!ctype_digit($id)||!in_array($id,array_column($this->state()['plans'],'id'),true)||!Analysis::iso($at))throw new \InvalidArgumentException('Plan oder Zeitpunkt ungültig.');return $this->mode()==='demo'?['id'=>$id,'name'=>'Beispielsimulation','rings_for'=>'Demo-Ziel']:$this->api->placetel('/routing_plans/'.$id.'/simulate?time='.rawurlencode($at));}
 public function tick(): bool {
  $profiles=$this->state()['profiles'];foreach($profiles as $p)if($p['state']==='executing'&&time()-($p['executingAt']??0)>120)$this->update($p['id'],'error','Ausführung unterbrochen. Anlagenstand prüfen; keine Wiederholung.');
  $due=array_values(array_filter($profiles,static fn($p)=>$p['state']==='pending'&&$p['activateAt']&&Analysis::stamp($p['activateAt'])<=time()));usort($due,static fn($a,$b)=>strcmp($b['activateAt'],$a['activateAt']));
  if($due){$latest=$due[0];foreach($due as $older)if($older['routingId']===$latest['routingId']&&$older['id']!==$latest['id'])$this->update($older['id'],'skipped','Durch neuere fällige Umschaltung ersetzt.');try{$this->apply($latest['id'],null,true);}catch(RemoteException $e){if($e->status!==429)$this->update($latest['id'],'error','Vorprüfung fehlgeschlagen; keine automatische Schreibwiederholung.');}return true;}
  $job=$this->store->get('routing:load');if(!$job||$this->mode()!=='live')return false;
  try{$rows=$this->api->page($job['kind']==='routes'?'/routings':'/routing_plans',$job['page']);$hash=self::digest($rows);if($rows&&($job['last']??'')===$hash)throw new RemoteException('Wiederholte Routingseite.');if($rows){foreach($rows as $r){if(!isset($r['id']))throw new RemoteException('Routing ohne ID.');$job[$job['kind']][]=['id'=>(string)$r['id'],'name'=>(string)($r['name']??$r['id'])];}$job['last']=$hash;$job['page']++;if($job['page']>1000)throw new RemoteException('Zu viele Routingseiten.');}
   elseif($job['kind']==='routes'){$job['kind']='plans';$job['page']=1;unset($job['last']);}
   else{foreach(['routes','plans'] as $key)$this->store->put($this->key($key),$job[$key]);$this->store->put($this->key('loadedAt'),gmdate('c'));$this->store->remove('routing:load');return true;}$this->store->put('routing:load',$job);
  }catch(RemoteException $e){if($e->status!==429){$this->store->remove('routing:load');$this->store->log('live','routing-load',$e->getMessage(),'error');}}return true;
 }
}
