<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
final class Directory {
 public function __construct(private Store $store,private Secrets $secrets,private Api $api,private Analysis $analysis) {}
 public function state(): array {
  $c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);$matches=[];
  if($c['enabled']&&$this->store->get('mode','demo')==='live')foreach($this->store->rows('dispolive-cache') as $r)if($r['origin']===$c['origin']){$match=$r;unset($match['origin']);$matches[$r['number']]=$match;}
  return ['origin'=>$c['origin'],'version'=>'v1.0.6','configured'=>$c['origin']!==''&&$this->secrets->get('dispolive:'.parse_url($c['origin'],PHP_URL_HOST))!==null,'enabled'=>$c['enabled'],'connected'=>$c['enabled']&&$this->store->get('dispolive:connected',false),'error'=>$this->store->get('dispolive:error'),'lastSuccess'=>$this->store->get('dispolive:lastSuccess'),'pending'=>count($this->store->get('dispolive:queue',[]))+count($this->store->rows('dispolive-new')),'matches'=>(object)$matches];
 }
 public function connect(array $input): void {
  $origin=Api::origin($input['origin']??null);$old=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);$key='dispolive:'.parse_url($origin,PHP_URL_HOST);
  $token=($input['useStored']??false)?$this->secrets->get($key):($input['token']??null);if(!is_string($token)||strlen($token)<8||strlen($token)>4096||preg_match('/\s/',$token))throw new \InvalidArgumentException('Gültigen DispoLive-API-Schlüssel eingeben.');
  $this->secrets->save($key,$token);$this->store->put('dispolive:revision',bin2hex(random_bytes(12)));$this->store->put('dispolive:config',['origin'=>$origin,'enabled'=>$old['origin']===$origin&&$old['enabled']]);$this->store->remove('dispolive:error');$this->store->remove('dispolive:paused');$this->store->put('dispolive:connected',false);$this->store->put('dispolive:queue',[]);$this->store->log($this->store->get('mode','demo'),'dispolive','Optionalen DispoLive-Zugang verschlüsselt gespeichert. Aktivierung erfolgt über den Schalter.');
 }
 public function toggle(mixed $enabled): void {
  if(!is_bool($enabled))throw new \InvalidArgumentException('DispoLive-Schalter angeben.');$c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);
  if($enabled&&(!$c['origin']||!$this->secrets->get('dispolive:'.parse_url($c['origin'],PHP_URL_HOST))))throw new \InvalidArgumentException('Zuerst DispoLive-Adresse und Schlüssel einrichten.');
  $this->store->put('dispolive:revision',bin2hex(random_bytes(12)));$c['enabled']=$enabled;$this->store->put('dispolive:config',$c);$this->store->put('dispolive:queue',[]);$this->store->put('dispolive:connected',false);$this->store->remove('dispolive:paused');$this->store->remove('dispolive:error');$this->store->log($this->store->get('mode','demo'),'dispolive',$enabled?'DispoLive-Zusatzfunktion eingeschaltet.':'DispoLive ausgeschaltet; es werden keine weiteren Rufnummern übertragen.');
 }
 public function disconnect(): void {
  $c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);$this->toggle(false);if($c['origin'])$this->secrets->remove('dispolive:'.parse_url($c['origin'],PHP_URL_HOST));
 }
 public function request(mixed $numbers,bool $force=false): void {
  if(!is_array($numbers)||!array_is_list($numbers)||count($numbers)>100)throw new \InvalidArgumentException('Höchstens 100 Rufnummern angeben.');$c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);if(!$c['enabled']||$this->store->get('mode','demo')!=='live'||!$force)return;if(!$this->store->get('dispolive:eventLookupV1',false)){$this->store->put('dispolive:queue',[]);$this->store->put('dispolive:eventLookupV1',true);}$queue=$this->store->get('dispolive:queue',[]);
  foreach($numbers as $value){if(!is_string($value))throw new \InvalidArgumentException('Ungültige Rufnummer.');$phone=$this->analysis->phone($value);if(!$phone)continue;$key=$c['origin'].':'.$phone;$old=$this->store->get($key,null,'dispolive-cache');if($force||!$old||Analysis::stamp($old['expiresAt'])<=time())$queue[$phone]=true;}
  $this->store->put('dispolive:queue',array_slice($queue,0,3000,true));
 }
 /** Only newly received incoming calls may enqueue an automatic lookup. */
 public function enqueueIncoming(array $call,string $event): void {
  $c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);
  if($event!=='IncomingCall'||($call['direction']??'')!=='in'||!$c['enabled']||$this->store->get('mode','demo')!=='live')return;
  $phone=$this->analysis->phone($call['caller']??null);$id=$call['id']??null;if(!$phone||!is_string($id)||!preg_match('/^[a-f0-9]{64}$/D',$id))return;
  $old=$this->store->get($c['origin'].':'.$phone,null,'dispolive-cache');$names=$this->store->get('live:contactNames',[]);if(!empty($old['contacts'])||!empty($names[$phone]))return;
  $this->store->put($id,['id'=>$id,'number'=>$phone,'origin'=>$c['origin'],'revision'=>$this->store->get('dispolive:revision'),'receivedAt'=>gmdate('c')],'dispolive-new',gmdate('Y-m-d'));
 }
 public function tick(): void {
  $c=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);if(!$c['enabled']||$this->store->get('mode','demo')!=='live'||$this->store->get('dispolive:paused',false)||microtime(true)<$this->store->get('dispolive:next',0))return;
  // Discard the pre-upgrade backlog once; it contains historical callers.
  if(!$this->store->get('dispolive:eventLookupV1',false)){$this->store->put('dispolive:queue',[]);$this->store->put('dispolive:eventLookupV1',true);}
  $queue=$this->store->get('dispolive:queue',[]);$phone=null;
  if($queue){$phone=(string)array_key_first($queue);unset($queue[$phone]);$this->store->put('dispolive:queue',$queue);}
  else {foreach($this->store->rows('dispolive-new') as $item){$this->store->remove($item['id'],'dispolive-new');if(($item['origin']??'')!==$c['origin']||($item['revision']??null)!==$this->store->get('dispolive:revision')||time()-Analysis::stamp($item['receivedAt']??'')>900)continue;$phone=$item['number'];$prior=$this->store->get($c['origin'].':'.$phone,null,'dispolive-cache');if(!empty($prior['contacts'])){$phone=null;continue;}break;}}
  if(!$phone)return;$key=$c['origin'].':'.$phone;$old=$this->store->get($key,null,'dispolive-cache');
  try{
   $rows=$this->api->dispolive($c['origin'],$phone);$contacts=[];
   foreach($rows as $r){if(!is_array($r)||!is_string($r['name']??null)||!trim($r['name']))throw new RemoteException('DispoLive-Treffer ohne gültigen Namen.');$returned=[];foreach(['phone','tel','mobile','mobil'] as $field)if(is_string($r[$field]??null)&&($n=$this->analysis->phone($r[$field])))$returned[]=$n;if($returned&&!in_array($phone,$returned,true))throw new RemoteException('Rufnummer im DispoLive-Treffer stimmt nicht überein.');$contact=['id'=>is_string($r['_id']??null)?mb_substr($r['_id'],0,200):null,'name'=>mb_substr(trim($r['name']),0,300),'type'=>is_string($r['type']??null)?mb_substr($r['type'],0,80):'Kontakt'];$id=$contact['id']?json_encode($contact,JSON_THROW_ON_ERROR):'unidentified:'.count($contacts);$contacts[$id]=$contact;}
   $now=gmdate('c');$this->store->put($key,['origin'=>$c['origin'],'number'=>$phone,'contacts'=>array_values($contacts),'status'=>$contacts?'matched':'none','checkedAt'=>$now,'expiresAt'=>gmdate('c',time()+($contacts?900:120))],'dispolive-cache',gmdate('Y-m-d'));$this->store->put('dispolive:connected',true);$this->store->put('dispolive:lastSuccess',$now);$this->store->remove('dispolive:error');
  }catch(\Throwable $e){$message=$e instanceof RemoteException?$e->getMessage():'DispoLive-Ergebnis nicht zuverlässig verarbeitbar.';$retry=$e instanceof RemoteException?$e->retrySeconds:30;$this->store->put('dispolive:error',$message);$this->store->put('dispolive:connected',false);$this->store->put('dispolive:next',microtime(true)+$retry);if($e instanceof RemoteException&&in_array($e->status,[401,403],true))$this->store->put('dispolive:paused',true);$this->store->put($key,['origin'=>$c['origin'],'number'=>$phone,'contacts'=>$old['contacts']??[],'status'=>'error','message'=>$message,'checkedAt'=>gmdate('c'),'expiresAt'=>gmdate('c',time()+$retry)],'dispolive-cache',gmdate('Y-m-d'));}
 }
}
