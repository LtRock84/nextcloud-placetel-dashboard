<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\Lock\{ILockingProvider,LockedException};

/** Enqueue under the receive lock; dispatch is separately serialized. No history backfill. */
final class Popups {
 private const SCOPE='dispolive-popups';
 public function __construct(private Store $store,private Secrets $secrets,private Api $api,private Analysis $analysis,private ILockingProvider $locks) {}
 private function locked(callable $fn): mixed {
  try{$this->locks->acquireLock('placetel-dispolive-popups',ILockingProvider::LOCK_EXCLUSIVE);}catch(LockedException){throw new RemoteException('Popup-Versand läuft bereits.',409,1);}
  try{return $fn();}finally{$this->locks->releaseLock('placetel-dispolive-popups',ILockingProvider::LOCK_EXCLUSIVE);}
 }
 private function rows(): array {return $this->store->rows(self::SCOPE,gmdate('Y-m-d',time()-172800));}
 public function config(): array {return $this->store->get('dispolive:popups',['enabled'=>false,'desktopPhone'=>'','firmenKrz'=>'','calledNumbers'=>[]]);}
 private function fingerprint(): string {
  $directory=$this->store->get('dispolive:config',['origin'=>'']);
  return hash('sha256',json_encode([$directory['origin'],$this->store->get('dispolive:revision'),$this->config()],JSON_THROW_ON_ERROR));
 }
 public function state(): array {
  $rows=$this->rows();usort($rows,static fn($a,$b)=>strcmp($b['receivedAt'],$a['receivedAt']));
  $pending=count(array_filter($rows,static fn($r)=>in_array($r['state'],['pending','sending'],true)));
  $recent=array_map(static fn($r)=>array_intersect_key($r,array_flip(['id','state','receivedAt','updatedAt','attempts','message'])),array_slice($rows,0,15));
  return $this->config()+['pending'=>$pending,'recent'=>$recent,'workerLastSeen'=>$this->store->get('dispolive:popupWorkerAt')];
 }
 public function configure(array $input): void {$this->locked(fn()=> $this->saveConfig($input));}
 private function saveConfig(array $input): void {
  if(!is_bool($input['enabled']??null)||!is_string($input['desktopPhone']??null)||!is_string($input['firmenKrz']??null)||!is_array($input['calledNumbers']??null)||!array_is_list($input['calledNumbers'])||count($input['calledNumbers'])>50)throw new \InvalidArgumentException('Popup-Einstellungen vollständig angeben.');
  $phone=trim($input['desktopPhone']);$company=trim($input['firmenKrz']);
  if(strlen($phone)>64||($phone!==''&&!preg_match('~^[0-9+() ./-]+$~D',$phone))||mb_strlen($company)>40||preg_match('/[\x00-\x1f\x7f]/',$company))throw new \InvalidArgumentException('Arbeitsplatz-Telefon oder Firmenkürzel ungültig.');
  $numbers=[];foreach($input['calledNumbers'] as $n){if(!is_string($n)||!($normal=$this->analysis->phone($n)))throw new \InvalidArgumentException('Zielrufnummern im internationalen Format angeben.');$numbers[]=$normal;}
  $directory=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);
  if($input['enabled']&&($this->store->get('mode','demo')!=='live'||!$directory['enabled']||!$directory['origin']||!$this->secrets->get('dispolive:'.parse_url($directory['origin'],PHP_URL_HOST))||!$this->secrets->get('notify')||$phone===''||!preg_match('/\d/',$phone)))throw new \InvalidArgumentException('Für Popups zuerst Echtbetrieb, DispoLive, Signaturgeheimnis und ein Arbeitsplatz-Telefon einrichten.');
  $next=['enabled'=>$input['enabled'],'desktopPhone'=>$phone,'firmenKrz'=>$company,'calledNumbers'=>array_values(array_unique($numbers))];
  if($next!==$this->config())foreach($this->rows() as $r)if($r['state']==='pending')$this->finish($r,'skipped','Einstellungen geändert; alte Meldung nicht nachgesendet.');
  $this->store->put('dispolive:popups',$next);
 }
 public function enqueue(array $call,string $event): void {
  if($event!=='IncomingCall'||$call['direction']!=='in'||$this->store->get($call['id'],null,self::SCOPE))return;
  $c=$this->config();$d=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);
  if(!$c['enabled']||!$d['enabled']||$this->store->get('mode','demo')!=='live')return;
  $row=['id'=>$call['id'],'fingerprint'=>$this->fingerprint(),'receivedAt'=>gmdate('c'),'updatedAt'=>gmdate('c'),'state'=>'pending','attempts'=>0,'nextAt'=>microtime(true),'message'=>'Zur unmittelbaren Zustellung vorgemerkt.'];
  $reason=null;if(time()-Analysis::stamp($call['firstReceivedAt'])>=90)$reason='Anrufereignis zu alt; kein nachträgliches Popup.';if(!$call['caller'])$reason='Unterdrückte oder ungültige Anrufernummer; kein Popup.';
  if($c['calledNumbers']&&!in_array($this->analysis->phone($call['to']),$c['calledNumbers'],true))$reason='Angerufene Rufnummer nicht für Popups ausgewählt.';
  if(in_array('CallAccepted',$call['events'],true)||in_array('HungUp',$call['events'],true))$reason='Anruf bereits angenommen oder beendet; keine verspätete Meldung.';
  $this->store->put($call['id'],$row,self::SCOPE,gmdate('Y-m-d'));if($reason)$this->finish($row,'skipped',$reason);
 }
 private function finish(array $row,string $state,string $message): void {
  $row['state']=$state;$row['message']=$message;$row['updatedAt']=gmdate('c');$this->store->put($row['id'],$row,self::SCOPE,substr($row['receivedAt'],0,10));
 }
 public function tick(): void {$this->locked(fn()=> $this->dispatch());}
 private function dispatch(): void {
  $rows=$this->rows();usort($rows,static fn($a,$b)=>strcmp($a['receivedAt'],$b['receivedAt']));
  $c=$this->config();$d=$this->store->get('dispolive:config',['origin'=>'','enabled'=>false]);
  foreach($rows as $row){
   if($row['state']==='sending'){if(time()-Analysis::stamp($row['updatedAt'])>=30)$this->finish($row,'uncertain','Prozess während Versand unterbrochen; keine automatische Wiederholung.');continue;}
   if($row['state']!=='pending')continue;
   if(!$c['enabled']||!$d['enabled']||$this->store->get('mode','demo')!=='live'||$row['fingerprint']!==$this->fingerprint()){$this->finish($row,'skipped','Integration ausgeschaltet oder Zugang/Ziel geändert.');continue;}
   if(time()-Analysis::stamp($row['receivedAt'])>=90){$this->finish($row,'expired','Meldung älter als 90 Sekunden; nicht nachgesendet.');continue;}
   $call=$this->store->get($row['id'],null,'notify-calls');if(!$call||!$call['caller']||in_array('HungUp',$call['events'],true)||in_array('CallAccepted',$call['events'],true)){$this->finish($row,'skipped','Anruf nicht mehr klingelnd oder ohne erkennbare Nummer.');continue;}
   $next=max($row['nextAt'],$this->store->get('dispolive:next',0));$delay=$next-microtime(true);if($delay>0.55)return;if($delay>0)usleep((int)ceil($delay*1000000)+1000);
   if($row['attempts']>=3){$this->finish($row,'error','Maximale Zahl zurückgewiesener Zustellversuche erreicht.');continue;}
   $row['attempts']++;$this->finish($row,'sending','Zustellung läuft.');
   try{$this->api->dispoliveNotify($d['origin'],$call['caller'],$c['desktopPhone'],$c['firmenKrz']);$this->finish($row,'sent','DispoLive hat die Benachrichtigungsanfrage bestätigt.');}
   catch(\Throwable $e){
    if($e instanceof RemoteException&&$e->beforeRequest){$row['attempts']--;$row['nextAt']=microtime(true)+max(1,$e->retrySeconds);$this->finish($row,'pending','Lokaler DispoLive-Abruf läuft; kein externer Zustellversuch gezählt.');}
    elseif($e instanceof RemoteException&&$e->status===429){$row['nextAt']=microtime(true)+max(1,$e->retrySeconds);$this->finish($row,'pending','DispoLive hat die Anfrage begrenzt; erneuter Versuch nur solange der Anruf klingelt.');}
    elseif($e instanceof RemoteException&&in_array($e->status,[400,401,403,404,422],true))$this->finish($row,'error','DispoLive hat die Anfrage abgelehnt (HTTP '.$e->status.'). Einstellungen/Berechtigung prüfen.');
    else $this->finish($row,'uncertain','Zustellergebnis unbekannt; zur Vermeidung doppelter Popups keine automatische Wiederholung.');
   }
   return;
  }
 }
 public function workerTick(): void {$this->store->put('dispolive:popupWorkerAt',gmdate('c'));$this->tick();}
}
