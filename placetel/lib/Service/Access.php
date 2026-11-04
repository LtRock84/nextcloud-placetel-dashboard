<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Service;
use OCA\Placetel\Native\Store;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
final class Access {
 public const AREAS=['overview','agents','callbacks','routing','events','queues','schedule','statistics','settings'];
 public function __construct(private IConfig $config,private IGroupManager $groups,private IUserSession $session,private ?Store $store=null,private ?IUserManager $users=null) {}
 private static function all(string $value): array{return array_fill_keys(self::AREAS,$value);}
 private function settings(): array {$v=$this->store?->get('access')??$this->config->getSystemValue('placetel',[]);return is_array($v)?$v:[];}
 private function inAny(string $uid,array $groups): bool {foreach($groups as $group)if(is_string($group)&&$this->groups->isInGroup($uid,$group))return true;return false;}
 public function identity(): ?array {
  $user=$this->session->getUser();if($user===null)return null;$uid=$user->getUID();$settings=$this->settings();
  if($this->groups->isAdmin($uid)||$this->inAny($uid,$settings['management_groups']??[]))return ['user'=>$uid,'role'=>'admin','permissions'=>self::all('write')];
  if(!isset($settings['matrix'])){
   if($this->inAny($uid,$settings['operator_groups']??[]))return ['user'=>$uid,'role'=>'operator','permissions'=>self::all('write')];
   if(!array_key_exists('viewer_groups',$settings)||$this->inAny($uid,$settings['viewer_groups']??[]))return ['user'=>$uid,'role'=>'viewer','permissions'=>self::all('read')];
   return null;
  }
  $matrix=$settings['matrix'];$levels=['none'=>0,'read'=>1,'write'=>2];$permissions=$matrix['default'];$matched=false;$groupRights=self::all('none');
  foreach($matrix['groups'] as $id=>$rule)if($this->groups->isInGroup($uid,$id)){$matched=true;foreach(self::AREAS as $area)if($levels[$rule[$area]]>$levels[$groupRights[$area]])$groupRights[$area]=$rule[$area];}
  if($matched)$permissions=$groupRights;
  if(isset($matrix['users'][$uid]))$permissions=$matrix['users'][$uid];
  if(!array_filter($permissions,static fn($level)=>$level!=='none'))return null;
  return ['user'=>$uid,'role'=>in_array('write',$permissions,true)?'operator':'viewer','permissions'=>$permissions];
 }
 public function editable(): array {
  $s=$this->settings();if(isset($s['matrix']))return ['management_groups'=>$s['management_groups']??[],'matrix'=>$s['matrix']];
  $default=self::all(array_key_exists('viewer_groups',$s)?'none':'read');$groups=[];
  foreach($s['viewer_groups']??[] as $id)$groups[$id]=self::all('read');
  foreach($s['operator_groups']??[] as $id)$groups[$id]=self::all('write');
  return ['management_groups'=>[],'matrix'=>['default'=>$default,'groups'=>(object)$groups,'users'=>(object)[]]];
 }
 public function options(string $search=''): array {
  if(($this->identity()['role']??null)!=='admin'||!$this->users)throw new \InvalidArgumentException('Administrator erforderlich.');
  if(strlen($search)>128)throw new \InvalidArgumentException('Suchbegriff zu lang.');
  $groups=[];foreach($this->groups->search($search,500,0) as $g)$groups[]=['id'=>$g->getGID(),'name'=>$g->getDisplayName()];
  $users=[];foreach($this->users->search($search,500,0) as $u)$users[]=['id'=>$u->getUID(),'name'=>$u->getDisplayName()];
  return ['groups'=>$groups,'users'=>$users,'limit'=>500];
 }
 public function configure(array $input): void {
  if(($this->identity()['role']??null)!=='admin'||!$this->store)throw new \InvalidArgumentException('Administrator erforderlich.');
  if(!array_key_exists('matrix',$input)){
   if(isset($this->settings()['matrix'])||!$this->groups->isAdmin($this->identity()['user']))throw new \InvalidArgumentException('Bitte die aktuelle Rechteverwaltung verwenden.');
   $out=[];foreach(['operator_groups','viewer_groups'] as $key){$groups=$input[$key]??null;if($key==='viewer_groups'&&$groups===null)continue;if(!is_array($groups)||!array_is_list($groups)||count($groups)>100)throw new \InvalidArgumentException('Gültige Gruppenliste angeben.');foreach($groups as $group)if(!is_string($group)||strlen($group)>128||!$this->groups->groupExists($group))throw new \InvalidArgumentException('Eine angegebene Nextcloud-Gruppe existiert nicht.');$out[$key]=array_values(array_unique($groups));}$this->store->put('access',$out);return;
  }
  $management=$input['management_groups']??null;$matrix=$input['matrix'];if(!is_array($management)||!array_is_list($management)||count($management)<1||count($management)>100||!is_array($matrix))throw new \InvalidArgumentException('Bitte mindestens eine Geschäftsführungsgruppe auswählen.');
  foreach($management as $g)if(!is_string($g)||!$this->groups->groupExists($g))throw new \InvalidArgumentException('Geschäftsführungsgruppe nicht gefunden.');
  $uid=$this->identity()['user'];if(!$this->groups->isAdmin($uid)&&!$this->inAny($uid,$management))throw new \InvalidArgumentException('Die eigene Geschäftsführungsgruppe muss erhalten bleiben.');
  $validate=static function($rule){if(!is_array($rule)||array_diff(array_keys($rule),self::AREAS)||array_diff(self::AREAS,array_keys($rule)))throw new \InvalidArgumentException('Für jeden Bereich ein Recht auswählen.');foreach($rule as $v)if(!in_array($v,['none','read','write'],true))throw new \InvalidArgumentException('Ungültiges Recht.');return $rule;};
  $default=$validate($matrix['default']??null);$out=['default'=>$default,'groups'=>[],'users'=>[]];
  foreach(['groups','users'] as $kind){$rules=$matrix[$kind]??null;if(!is_array($rules)||count($rules)>500)throw new \InvalidArgumentException('Ungültige Zuordnungen.');foreach($rules as $id=>$rule){$id=(string)$id;if($id===''||strlen($id)>128||($kind==='groups'?!$this->groups->groupExists($id):(!$this->users||!$this->users->get($id))))throw new \InvalidArgumentException('Ausgewählte Gruppe oder Benutzer nicht gefunden.');$out[$kind][$id]=$validate($rule);}}
  $this->store->put('access',['management_groups'=>array_values(array_unique($management)),'matrix'=>$out]);
 }
 public function permits(string $role,string $method,string $path): bool {
  $levels=['viewer'=>0,'operator'=>1,'admin'=>2];if(!isset($levels[$role]))return false;
  $rules=json_decode(file_get_contents(__DIR__.'/../../appinfo/native-policy.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($rules as $rule)if($rule['method']===$method&&preg_match('~'.$rule['pattern'].'~D',$path)===1)return $levels[$role]>=$levels[$rule['role']];return false;
 }
 public function permitsArea(array $identity,string $method,string $path,string $scope=''): bool {
  if(!$this->permits($identity['role'],$method,$path))return false;
  if($identity['role']==='admin')return true;$permissions=$identity['permissions'];
  if(in_array($path,['/bootstrap','/health','/updates','/updates/seen'],true))return true;
  if($path==='/dispolive/resolve')return count(array_filter(['overview','callbacks','events','statistics'],static fn($x)=>($permissions[$x]??'none')!=='none'))>0;
  if($path==='/coverage')return ($permissions['statistics']??'none')!=='none'||($permissions['settings']??'none')!=='none';
  $area=match(true){$path==='/statistics'=>$scope==='overview'?'overview':'statistics',$path==='/operations/report'=>$scope==='statistics'?'statistics':'callbacks',str_starts_with($path,'/operations/callback')||str_starts_with($path,'/operations/incoming')=>'callbacks',str_starts_with($path,'/operations/routing')=>'routing',str_starts_with($path,'/agents/')=>'agents',str_starts_with($path,'/queues/')=>'queues',default=>'settings'};
  return ($permissions[$area]??'none')=== 'write'||($method==='GET'&&($permissions[$area]??'none')==='read');
 }
 public function filterBootstrap(array $identity,array $value): array {
  $p=$identity['permissions'];$can=static fn(string $area)=>($p[$area]??'none')!=='none';
  $value['hosting']['permissions']=$p;
  if($identity['role']==='admin')return $value;
  $value['access']=null;
  if(!$can('overview'))unset($value['queueSnapshot']);
  if(!$can('overview')&&!$can('agents')&&!$can('statistics'))$value['agents']=[];
  if(!$can('overview')&&!$can('queues')&&!$can('statistics')&&!$can('callbacks')&&!$can('agents'))$value['queues']=[];
  if(!$can('schedule')){$value['plans']=[];$value['assignments']=(object)[];$value['overrides']=(object)[];}
  if(!$can('settings'))$value['audit']=[];
  if(!$can('routing'))$value['operations']['routing']=['routes'=>[],'plans'=>[],'profiles'=>[],'loadedAt'=>null];
  if(!$can('events')&&!$can('statistics')){$value['operations']['notify']['calls']=[];$value['operations']['notify']['total']=0;}
  if(!$can('overview')&&!$can('callbacks')&&!$can('events')&&!$can('statistics')){$value['operations']['contactNames']=(object)[];unset($value['dispolive']);}
  elseif(!$can('settings')){$value['dispolive']['origin']='';$value['dispolive']['popups']=null;}
  return $value;
 }
}
