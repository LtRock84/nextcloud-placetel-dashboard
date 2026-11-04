<?php
declare(strict_types=1);
namespace OCP { interface IPhoneNumberUtil { public function convertToStandardFormat($phoneNumber,$defaultRegion); } }
namespace OCA\Placetel\Native {
 class RemoteException extends \RuntimeException {public function __construct(string $message,int $status=0,int $retry=0){parent::__construct($message,$status);}}
 class Store {
  public array $items=[];
  public function get(string $key,mixed $fallback=null,string $scope='config'):mixed{return $this->items[$scope][$key]??$fallback;}
  public function put(string $key,mixed $value,string $scope='config',string $day=''):void{$this->items[$scope][$key]=$value;}
  public function rows(string $scope,?string $from=null,?string $to=null):array{return array_values(array_filter($this->items[$scope]??[],static fn($r)=>$from===null||str_starts_with($r['at']??'',$from)));}
  public function replaceDay(string $scope,string $day,array $values):void{$this->items[$scope]=array_column($values,null,'id');}
 }
 class Api {public array $pages=[];public function page(string $path,int $page):array {if(($this->pages[$page]??null)==='error')throw new RemoteException('fixture failure');return $this->pages[$page]??[];}}
}
namespace {
 $native=__DIR__.'/../../../nextcloud/placetel/lib/Native';if(!is_file($native.'/Analysis.php'))$native=__DIR__.'/../../placetel/lib/Native';require $native.'/Analysis.php';require $native.'/IncomingCalls.php';
 final class Phones implements \OCP\IPhoneNumberUtil {public function convertToStandardFormat($n,$region){$n=preg_replace('/[^+0-9]/','',$n);return str_starts_with($n,'0')?'+49'.substr($n,1):($n?:null);}}
 function check(bool $value,string $name):void{if(!$value)throw new \RuntimeException($name);echo "PASS $name\n";}
 $store=new \OCA\Placetel\Native\Store();$api=new \OCA\Placetel\Native\Api();$analysis=new \OCA\Placetel\Native\Analysis(new Phones());$app=new \OCA\Placetel\Native\IncomingCalls($store,$analysis,$api);
 $day='2026-10-20';$row=['id'=>1,'type'=>'missed','from_number'=>'03012345678','received_at'=>$day.'T08:00:00Z'];$api->pages=[1=>[$row],2=>[array_replace($row,['id'=>2,'type'=>'accepted'])],3=>[]];$app->sync($day,'live');$r=$app->report($day,'live');check(count($r['calls'])===2&&$r['calls'][0]['state']==='open','full paginated import');
 $app->save(['day'=>$day,'source'=>'placetel-calls','id'=>'1','state'=>'done','note'=>'fixture'],'live');$r=$app->report($day,'live');check($r['calls'][0]['state']==='done'&&$r['calls'][0]['note']==='fixture','manual status persists');
 $api->pages=[1=>[$row],2=>'error'];try{$app->sync($day,'live');throw new \RuntimeException('failure not rejected');}catch(\OCA\Placetel\Native\RemoteException){}check(count($app->report($day,'live')['calls'])===2,'partial result never replaces data');
 $api->pages=[1=>[$row],2=>[$row]];try{$app->sync($day,'live');throw new \RuntimeException('duplicate page not rejected');}catch(\OCA\Placetel\Native\RemoteException){}check(count($app->report($day,'live')['calls'])===2,'repeated page rejected');
 $id=str_repeat('a',64);$store->items['notify-calls'][$id]=['id'=>$id,'firstReceivedAt'=>$day.'T08:00:00Z','direction'=>'in','caller'=>'03012345678','to'=>'03012345679','events'=>['IncomingCall','CallAccepted','HungUp'],'hangupType'=>'accepted'];$r=$app->report($day,'live');check(count($r['notify'])===1&&$r['notify'][0]['outcome']==='accepted'&&$r['calls'][0]['outcome']==='missed','notify remains separate');
 try{$app->save(['day'=>$day,'source'=>'notify','id'=>$id,'state'=>'done','note'=>''],'live');throw new \RuntimeException('accepted writable');}catch(\InvalidArgumentException){}check(true,'accepted calls cannot become callback state');
}
