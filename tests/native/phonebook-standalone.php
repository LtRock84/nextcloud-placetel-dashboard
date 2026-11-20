<?php
declare(strict_types=1);
namespace OCA\Placetel\Native {
 final class RemoteException extends \RuntimeException {public function __construct(string $message,public int $status=0){parent::__construct($message);}}
 final class Store {public array $data=['mode'=>'live'];public function get($key,$fallback=null){return $this->data[$key]??$fallback;}public function put($key,$value){$this->data[$key]=$value;}public function log(...$args):void{}public function remove($key){unset($this->data[$key]);}}
 final class Api {public array $rows=[1=>['id'=>1,'first_name'=>'Anna','last_name'=>'Muster','phone_work'=>'0301234','address'=>'Alte Strasse','blocked'=>true,'tags'=>['Test']]];public array $writes=[];public function page($path,$page){return $page===1?array_values($this->rows):[];}public function placetel($path,$method='GET',$body=null){if($method==='GET')return $this->rows[(int)basename($path)]??null;$this->writes[]=[$path,$method,$body];if($method==='POST'){$this->rows[2]=['id'=>2]+$body;return $this->rows[2];}if($method==='PUT')return $this->rows[(int)basename($path)]=['id'=>(int)basename($path)]+$body;if($method==='DELETE'){unset($this->rows[(int)basename($path)]);return null;}throw new \RuntimeException('unexpected');}}
}
namespace {
 $native=__DIR__.'/../../../nextcloud/placetel/lib/Native/';if(!is_dir($native))$native=__DIR__.'/../../placetel/lib/Native/';require $native.'Phonebook.php';
 function check($ok,$label){if(!$ok)throw new \RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}function rejects($fn){try{$fn();return false;}catch(\Throwable){return true;}}
 $s=new \OCA\Placetel\Native\Store();$a=new \OCA\Placetel\Native\Api();$p=new \OCA\Placetel\Native\Phonebook($s,$a);$r=$p->page(1);check(count($r['contacts'])===1&&$r['more']&& !$p->page(2)['more'],'read contact page');$c=$r['contacts'][0];
 $s->put('sync:job',['kind'=>'contacts','page'=>2]);$p->change(['operation'=>'update','id'=>$c['id'],'version'=>$c['version'],'contact'=>['first_name'=>'Anna','last_name'=>'Neu','company'=>'']]);check($a->rows[1]['address']==='Alte Strasse'&&$a->rows[1]['blocked']===true&&$a->rows[1]['tags']===['Test'],'update preserves other provider fields');check(!isset($s->data['sync:job'])&&$s->data['live:contactsAtEpoch']===0,'contact import invalidated after write');
 $fresh=$p->page(1)['contacts'][0];$p->change(['operation'=>'update','id'=>$fresh['id'],'version'=>$fresh['version'],'contact'=>['phone'=>'123']]);check($a->rows[1]['first_name']==='Anna'&&$a->rows[1]['last_name']==='Neu','partial edits preserve unchanged fields');
 $n=count($a->writes);check(rejects(fn()=> $p->change(['operation'=>'delete','id'=>'1','version'=>$c['version']]))&&count($a->writes)===$n,'stale delete is rejected without write');
 check(rejects(fn()=> $p->change(['operation'=>'create','contact'=>['first_name'=>'','company'=>'']])),'missing name rejected');
 $p->change(['operation'=>'create','contact'=>['first_name'=>'Test','company'=>'']]);check(isset($a->rows[2]),'create contact');$fresh=$p->page(1)['contacts'][0];$p->change(['operation'=>'delete','id'=>$fresh['id'],'version'=>$fresh['version']]);check(!isset($a->rows[1]),'delete confirmed contact');
 $s->put('mode','demo');$n=count($a->writes);check(rejects(fn()=> $p->change(['operation'=>'create','contact'=>['first_name'=>'Test']]))&&count($a->writes)===$n,'demo cannot write provider');echo "PHONEBOOK_COMPLETE\n";
}
