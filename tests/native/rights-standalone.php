<?php
declare(strict_types=1);
namespace OCP { interface IConfig {} interface IGroupManager {} interface IUserSession {} interface IUserManager {} }
namespace OCA\Placetel\Native { class Store {public array $data=[];public function get($key){return $this->data[$key]??null;}public function put($key,$value){$this->data[$key]=$value;}} }
namespace {
 $path=__DIR__.'/../../../nextcloud/placetel/lib/Service/Access.php';if(!is_file($path))$path=__DIR__.'/../../placetel/lib/Service/Access.php';require $path;
 class Config implements \OCP\IConfig {public array $settings=[];public function getSystemValue($k,$default){return $this->settings;}}
 class Groups implements \OCP\IGroupManager {public function isAdmin($id){return $id==='admin';}public function groupExists($id){return in_array($id,['management','team','reports'],true);}public function isInGroup($id,$g){return in_array($id,['management'=>['boss'],'team'=>['alice'],'reports'=>['alice','bob']][$g]??[],true);} }
 class Users implements \OCP\IUserManager {public function get($id){return in_array($id,['admin','boss','alice','bob'],true)?new User($id):null;}}
 class User {public function __construct(private string $id){}public function getUID(){return $this->id;}}
 class Session implements \OCP\IUserSession {public string $id='';public function getUser(){return new User($this->id);}}
 function verify($ok,$label){if(!$ok)throw new \RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
 $c=new Config();$s=new Session();$g=new Groups();$access=new \OCA\Placetel\Service\Access($c,$g,$s);$none=array_fill_keys(\OCA\Placetel\Service\Access::AREAS,'none');
 $c->settings=['management_groups'=>['management'],'matrix'=>['default'=>$none,'groups'=>['team'=>array_replace($none,['callbacks'=>'write']),'reports'=>array_replace($none,['statistics'=>'read'])],'users'=>['bob'=>array_replace($none,['events'=>'read'])]]];
 $s->id='admin';verify($access->identity()['role']==='admin','Nextcloud administrator has full access');
 $s->id='boss';verify($access->identity()['role']==='admin','management group has full access');
 $s->id='alice';$a=$access->identity();verify($a['permissions']['callbacks']==='write'&&$a['permissions']['statistics']==='read','highest group rights combined');
 verify($access->permitsArea($a,'POST','/operations/callbacks/complete')&&!$access->permitsArea($a,'GET','/statistics','overview')&&$access->permitsArea($a,'GET','/statistics'),'server enforces section permissions');
 $c->settings['matrix']['default']=array_fill_keys(\OCA\Placetel\Service\Access::AREAS,'read');verify($access->identity()['permissions']['overview']==='none','matching group can hide an area from a permissive default');$c->settings['matrix']['default']=$none;
 $s->id='bob';$b=$access->identity();verify($b['permissions']['statistics']==='none'&&$b['permissions']['events']==='read','user rule overrides group');
 verify(!$access->permitsArea($b,'POST','/operations/callbacks/complete')&&!$access->permitsArea($b,'GET','/permissions/options'),'read-only user cannot write or enumerate users');
 $s->id='charlie';verify($access->identity()===null,'unassigned user denied app');
 $c->settings=['operator_groups'=>['team']];$s->id='alice';verify($access->identity()['role']==='operator','legacy operator remains valid');$s->id='charlie';verify($access->identity()['role']==='viewer','legacy default read preserved');
 $s->id='admin';$store=new \OCA\Placetel\Native\Store();$managed=new \OCA\Placetel\Service\Access($c,$g,$s,$store,new Users());
 $rules=['management_groups'=>['management'],'matrix'=>['default'=>$none,'groups'=>['team'=>array_replace($none,['callbacks'=>'write'])],'users'=>['bob'=>array_replace($none,['events'=>'read'])]]];$managed->configure($rules);verify(isset($store->data['access']['matrix']),'configuration stored in app database');
 $s->id='boss';$rejected=false;try{$managed->configure(['management_groups'=>['team'],'matrix'=>$rules['matrix']]);}catch(\InvalidArgumentException){$rejected=true;}verify($rejected,'management user cannot remove own management access');
 $rejected=false;try{$managed->configure(['operator_groups'=>[],'viewer_groups'=>null]);}catch(\InvalidArgumentException){$rejected=true;}verify($rejected,'legacy configuration cannot bypass management protection');
 $s->id='alice';$identity=$managed->identity();$data=['hosting'=>[],'agents'=>[['name'=>'Synthetic']], 'queues'=>[], 'plans'=>[], 'assignments'=>[], 'overrides'=>[], 'audit'=>[['private'=>'audit']], 'queueSnapshot'=>['private'=>'caller'], 'operations'=>['routing'=>['profiles'=>[['private'=>'route']]],'notify'=>['calls'=>[['private'=>'call']],'total'=>1],'contactNames'=>['private'=>'name']], 'dispolive'=>['origin'=>'https://example.invalid','popups'=>[]]];
 $result=$managed->filterBootstrap($identity,$data);verify(!isset($result['queueSnapshot'])&&$result['audit']===[]&&$result['operations']['notify']['calls']===[]&&$result['operations']['routing']['profiles']===[],'bootstrap hides denied sections');
 echo "RIGHTS_STANDALONE_COMPLETE\n";
}
