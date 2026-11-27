<?php
declare(strict_types=1);
namespace OCA\Placetel\Native {
 class Store {public array $data=['mode'=>'live','dispolive:config'=>['origin'=>'https://fixture.dispolive.de','enabled'=>true],'dispolive:revision'=>'test'];function get($k,$fallback=null,$scope='config'){return $this->data[$scope.':'.$k]??($scope==='config'?($this->data[$k]??$fallback):$fallback);}function put($k,$v,$scope='config',$day=''){ $this->data[$scope.':'.$k]=$v;}function remove($k,$scope='config'){unset($this->data[$scope.':'.$k],$this->data[$k]);}function rows($scope){$out=[];foreach($this->data as $k=>$v)if(str_starts_with($k,$scope.':'))$out[]=$v;return $out;}function log(...$x){}}
 class Secrets {function get($k){return 'fixture';}}
 class Analysis {function phone($v){return is_string($v)&&str_starts_with($v,'+')?$v:null;}static function stamp($v){return strtotime($v)?:0;}}
 class RemoteException extends \RuntimeException {public int $status=0;public int $retrySeconds=30;}
 class Api {public array $seen=[];function dispolive($origin,$phone){$this->seen[]=$phone;return [];}}
}
namespace {
 require __DIR__.'/../../placetel/lib/Native/Directory.php';
 $s=new \OCA\Placetel\Native\Store();$a=new \OCA\Placetel\Native\Api();$d=new \OCA\Placetel\Native\Directory($s,new \OCA\Placetel\Native\Secrets(),$a,new \OCA\Placetel\Native\Analysis());
 function ok($yes,$label){if(!$yes)throw new \RuntimeException($label);echo "PASS $label\n";}
 $s->put('dispolive:queue',['+491111'=>true]);$d->tick();ok(!$a->seen,'legacy queue never backfills');
 $d->request(['+492222']);$d->tick();ok(!$a->seen,'implicit historical requests ignored');
 $call=['id'=>str_repeat('a',64),'caller'=>'+493333','direction'=>'in'];$d->enqueueIncoming($call,'IncomingCall');$d->tick();ok($a->seen===['+493333'],'new incoming number queried');$d->tick();ok(count($a->seen)===1,'old calls not repeatedly queried');
 $s->put('https://fixture.dispolive.de:+494444',['contacts'=>[['name'=>'Example']]],'dispolive-cache');$d->enqueueIncoming(['id'=>str_repeat('b',64),'caller'=>'+494444','direction'=>'in'],'IncomingCall');$d->tick();ok(count($a->seen)===1,'known caller not queried');
 $d->enqueueIncoming(['id'=>str_repeat('d',64),'caller'=>'+494444','direction'=>'in'],'IncomingCall');$d->tick();ok(count($a->seen)===1,'repeated delivery with known name makes no request');
 $d->enqueueIncoming(['id'=>str_repeat('c',64),'caller'=>'+495555','direction'=>'out'],'OutgoingCall');$d->tick();ok(count($a->seen)===1,'outgoing call ignored');
 $d->request(['+496666'],true);$d->tick();ok(count($a->seen)===2,'explicit manual lookup remains available');
}
