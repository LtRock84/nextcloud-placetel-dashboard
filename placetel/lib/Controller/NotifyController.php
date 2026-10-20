<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Controller;
use OCA\Placetel\Native\Secrets;
use OCA\Placetel\Native\Store;
use OCA\Placetel\Native\Analysis;
use OCA\Placetel\Native\Popups;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
final class NotifyController extends Controller {
 public function __construct(IRequest $request,private Secrets $secrets,private Store $store,private Analysis $analysis,private ILockingProvider $locks,private Popups $popups){parent::__construct('placetel',$request);}
 #[PublicPage]
 #[NoCSRFRequired]
 public function receive(): DataDisplayResponse {
  $reply=static fn($status)=>new DataDisplayResponse($status===200?'OK':'Ereignis nicht angenommen',$status,['Content-Type'=>'text/plain','Cache-Control'=>'no-store']);
  try{
   $secret=$this->secrets->get('notify');if(!$secret)return $reply(503);
   if(!str_starts_with(strtolower($this->request->getHeader('Content-Type')),'application/x-www-form-urlencoded'))return $reply(415);
   if((int)$this->request->getHeader('Content-Length')>16384)return $reply(413);$f=fopen('php://input','rb');$raw=stream_get_contents($f,16385);fclose($f);if($raw===false||strlen($raw)>16384)return $reply(413);
   $signature=$this->request->getHeader('X-Placetel-Signature');if(!preg_match('/^[a-f0-9]{64}$/iD',$signature)||!hash_equals(hash_hmac('sha256',$raw,$secret),strtolower($signature)))return $reply(401);
   $data=[];foreach(explode('&',$raw) as $pair){$parts=explode('=',$pair,2);$key=urldecode($parts[0]);if(array_key_exists($key,$data))return $reply(400);$data[$key]=urldecode($parts[1]??'');}
   $id=strtolower($data['call_id']??'');$event=$data['event']??'';$direction=$data['direction']??'';
   if(!preg_match('/^[a-f0-9]{64}$/D',$id)||!in_array($event,['IncomingCall','OutgoingCall','CallAccepted','HungUp'],true)||!in_array($direction,['in','out'],true)||$event==='IncomingCall'&&$direction!=='in'||$event==='OutgoingCall'&&$direction!=='out'||$event==='CallAccepted'&&$direction!=='in')return $reply(400);
   $duration=null;if($event==='HungUp'&&array_key_exists('duration',$data)){if(!ctype_digit($data['duration'])||(float)$data['duration']>366*86400)return $reply(400);$duration=(int)$data['duration'];}
   $until=microtime(true)+0.5;do{try{$this->locks->acquireLock('placetel-dashboard-notify',ILockingProvider::LOCK_EXCLUSIVE);break;}catch(LockedException $e){if(microtime(true)>=$until)throw $e;usleep(25000);}}while(true);
   try{$hash=hash('sha256',$raw);if($this->store->get($hash,null,'notify-events'))return $reply(200);$old=$this->store->get($id,null,'notify-calls');$now=gmdate('c');$day=gmdate('Y-m-d');$call=['id'=>$id,'caller'=>$this->analysis->phone($data['from']??null),'to'=>mb_substr($data['to']??'',0,200),'peer'=>isset($data['peer'])?mb_substr($data['peer'],0,200):($old['peer']??null),'direction'=>$direction,'firstReceivedAt'=>$old['firstReceivedAt']??$now,'lastReceivedAt'=>$now,'events'=>array_values(array_unique(array_merge($old['events']??[],[$event]))),'durationSeconds'=>$duration??$old['durationSeconds']??null,'hangupType'=>$event==='HungUp'?mb_substr($data['type']??'',0,100):($old['hangupType']??null)];$this->store->transaction(function()use($id,$call,$day,$hash,$now,$event){$this->store->put($id,$call,'notify-calls',$day);$this->store->put($hash,true,'notify-events',$day);$this->store->put('notify:lastReceivedAt',$now);$this->popups->enqueue($call,$event);});
}
   finally{$this->locks->releaseLock('placetel-dashboard-notify',ILockingProvider::LOCK_EXCLUSIVE);}
   // Persist and release reception lock before bounded external delivery.
   try{$this->popups->tick();}catch(\Throwable){}
   return $reply(200);
  }catch(\Throwable){return $reply(503);}
 }
}
