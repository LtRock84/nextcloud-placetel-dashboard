<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\Http\Client\IClientService;
use OCP\Lock\{ILockingProvider,LockedException};
final class Api {
 public function __construct(private IClientService $http,private Store $store,private Secrets $secrets,private ILockingProvider $locks) {}
 public function placetel(string $path,string $method='GET',?array $body=null,?string $token=null): mixed {
  if(!preg_match('~^/(me|call_center_agents(?:/\d+)?|call_center_queues(?:/\d+)?|call_center_calls|contacts|routings(?:/[A-Za-z0-9%+_.-]+)?|routing_plans(?:/\d+/simulate)?)(?:\?[A-Za-z0-9%&=+_.:-]+)?$~D',$path))throw new \InvalidArgumentException('Nicht freigegebener API-Endpunkt.');
  if(!in_array($method,['GET','PUT'],true)||$method==='PUT'&&!preg_match('~^/(call_center_(agents|queues)/\d+|routings/[A-Za-z0-9%+_.-]+)$~D',$path))throw new \InvalidArgumentException('Nicht freigegebene Schreibfunktion.');
  $next=$this->store->get('api:next',0);if(microtime(true)<$next)throw new RemoteException('API wartet auf das nächste Zeitfenster.',429,max(1,(int)ceil($next-microtime(true))));
  $token??=$this->secrets->get('placetel');if(!$token)throw new RemoteException('Bitte zuerst einen Placetel-API-Schlüssel einrichten.',401);
  $this->store->put('api:next',microtime(true)+0.5);
  try{$r=$this->http->newClient()->request($method,'https://api.placetel.de/v2'.$path,['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json','Content-Type'=>'application/json'],'timeout'=>15,'connect_timeout'=>5,'allow_redirects'=>false,'http_errors'=>false]+($body===null?[]:['body'=>json_encode($body,JSON_THROW_ON_ERROR)]));}catch(\Throwable){throw new RemoteException('Placetel ist nicht erreichbar oder hat nicht rechtzeitig geantwortet.');}
  $remaining=$r->getHeader('RateLimit-Remaining');$reset=$r->getHeader('RateLimit-Reset');
  if($remaining!==''&&(int)$remaining<=1)$this->store->put('api:next',microtime(true)+min(3600,max(1,(int)$reset)));
  $status=$r->getStatusCode();if($status<200||$status>=300){$retry=$r->getHeader('Retry-After');$seconds=ctype_digit($retry)?(int)$retry:max(1,(int)(strtotime($retry)-time()));$seconds=min(3600,max(10,$seconds));if($status===429)$this->store->put('api:next',microtime(true)+$seconds);throw new RemoteException(match($status){401=>'API-Schlüssel ungültig oder abgelaufen.',403=>'Keine Berechtigung für diese Placetel-Funktion.',429=>'Placetel begrenzt die Abrufrate. Es wird automatisch gewartet.',default=>'Placetel-Abruf fehlgeschlagen (HTTP '.$status.').'},$status,$seconds);}
  $raw=(string)$r->getBody();if(strlen($raw)>4000000)throw new RemoteException('Placetel-Antwort zu groß.');try{return json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new RemoteException('Placetel hat keine gültige JSON-Antwort geliefert.');}
 }
 public function page(string $path,int $page): array {
  $rows=$this->placetel($path.(str_contains($path,'?')?'&':'?').'per_page=100&page='.$page);
  if(!is_array($rows)||!array_is_list($rows))throw new RemoteException('Unerwartetes Antwortformat der Placetel-API.');foreach($rows as $r)if(!is_array($r))throw new RemoteException('Ungültiger API-Datensatz.');return $rows;
 }
 public static function origin(mixed $value): string {
  if(!is_string($value)||!preg_match('~^https://([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)\.dispolive\.de/?$~D',$value))throw new \InvalidArgumentException('Eine HTTPS-Mandantenadresse wie https://firma.dispolive.de ohne Pfad angeben.');return rtrim($value,'/');
 }
 private function dispoliveLocked(callable $fn): mixed {
  try{$this->locks->acquireLock('placetel-dispolive-api',ILockingProvider::LOCK_EXCLUSIVE);}catch(LockedException){throw new RemoteException('DispoLive-Abruf läuft bereits.',429,1,true);}
  try{return $fn();}finally{$this->locks->releaseLock('placetel-dispolive-api',ILockingProvider::LOCK_EXCLUSIVE);}
 }
 public function dispoliveNotify(string $origin,string $phone,string $desktopPhone,string $company): void {$this->dispoliveLocked(fn()=> $this->sendNotification($origin,$phone,$desktopPhone,$company));}
 private function sendNotification(string $origin,string $phone,string $desktopPhone,string $company): void {
  $origin=self::origin($origin);$token=$this->secrets->get('dispolive:'.parse_url($origin,PHP_URL_HOST));if(!$token)throw new RemoteException('DispoLive-Schlüssel fehlt.',401);
  $next=$this->store->get('dispolive:next',0);if(microtime(true)<$next)throw new RemoteException('DispoLive wartet auf das nächste Zeitfenster.',429,max(1,(int)ceil($next-microtime(true))),true);$this->store->put('dispolive:next',microtime(true)+0.5);
  $body=['phone'=>$phone,'notification'=>true,'desktopPhone'=>$desktopPhone];if($company!=='')$body['firmenKrz']=$company;
  try{$r=$this->http->newClient()->post($origin.'/custom/open-api/telefonanlage/contactFindByPhone',['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json','Content-Type'=>'application/json','dispolive-api-version'=>'v1.0.6'],'body'=>json_encode($body,JSON_THROW_ON_ERROR),'timeout'=>3,'connect_timeout'=>2,'allow_redirects'=>false,'http_errors'=>false]);}catch(\Throwable){throw new RemoteException('DispoLive-Zustellergebnis unbekannt.');}
  $status=$r->getStatusCode();if($status<200||$status>=300){$retry=min(90,max(1,(int)$r->getHeader('Retry-After')));if($status===429)$this->store->put('dispolive:next',microtime(true)+$retry);throw new RemoteException('DispoLive-Benachrichtigung nicht bestätigt.',$status,$retry);}
 }
 public function dispolive(string $origin,string $phone,?string $token=null): array {return $this->dispoliveLocked(fn()=> $this->lookup($origin,$phone,$token));}
 private function lookup(string $origin,string $phone,?string $token): array {
  $origin=self::origin($origin);$token??=$this->secrets->get('dispolive:'.parse_url($origin,PHP_URL_HOST));if(!$token)throw new \InvalidArgumentException('DispoLive-Schlüssel fehlt.');
  $next=$this->store->get('dispolive:next',0);if(microtime(true)<$next)throw new RemoteException('DispoLive wartet auf das nächste Zeitfenster.',429,max(1,(int)ceil($next-microtime(true))));$this->store->put('dispolive:next',microtime(true)+0.5);
  try{$r=$this->http->newClient()->post($origin.'/custom/open-api/telefonanlage/contactFindByPhone',['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json','Content-Type'=>'application/json','dispolive-api-version'=>'v1.0.6'],'body'=>json_encode(['phone'=>$phone,'notification'=>false],JSON_THROW_ON_ERROR),'timeout'=>12,'connect_timeout'=>5,'allow_redirects'=>false,'http_errors'=>false]);}catch(\Throwable){throw new RemoteException('DispoLive ist nicht erreichbar.');}
  $status=$r->getStatusCode();if($status<200||$status>=300){$retry=min(3600,max(30,(int)$r->getHeader('Retry-After')));$this->store->put('dispolive:next',microtime(true)+$retry);throw new RemoteException(match($status){401=>'DispoLive-Schlüssel ungültig.',403=>'DispoLive-Schlüssel hat keine Berechtigung.',429=>'DispoLive begrenzt die Abrufrate.',default=>'DispoLive-Suche fehlgeschlagen.'},$status,$retry);}
  $raw=(string)$r->getBody();if(strlen($raw)>2000000)throw new RemoteException('DispoLive-Antwort zu groß.');$json=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($json['data']??null)||!array_is_list($json['data']))throw new RemoteException('Unerwartetes DispoLive-Ergebnis.');return $json['data'];
 }
}
