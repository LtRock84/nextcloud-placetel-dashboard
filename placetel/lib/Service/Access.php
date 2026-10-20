<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Service;
use OCA\Placetel\Native\Store;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
final class Access {
 public function __construct(private IConfig $config, private IGroupManager $groups, private IUserSession $session,private ?Store $store=null) {}
 public function identity(): ?array {
  $user=$this->session->getUser();
  if ($user===null) return null;
  $uid=$user->getUID();
  if ($this->groups->isAdmin($uid)) return ['user'=>$uid,'role'=>'admin'];
  $settings=$this->store?->get('access')??$this->config->getSystemValue('placetel',[]);
  foreach (['operator'=>'operator_groups','viewer'=>'viewer_groups'] as $role=>$key) {
   $list=$settings[$key]??[];
   if (!is_array($list)) return null;
   foreach ($list as $group) if (is_string($group) && $this->groups->isInGroup($uid,$group)) return ['user'=>$uid,'role'=>$role];
  }
  // Default: all logged-in users may view. Setting viewer_groups limits access.
  if (!array_key_exists('viewer_groups',$settings)) return ['user'=>$uid,'role'=>'viewer'];
  return null;
 }
 public function configure(array $input): void {
  if($this->identity()['role']!=='admin'||!$this->store)throw new \InvalidArgumentException('Administrator erforderlich.');
  $out=[];foreach(['operator_groups','viewer_groups'] as $key){$groups=$input[$key]??null;if($key==='viewer_groups'&&$groups===null)continue;if(!is_array($groups)||!array_is_list($groups)||count($groups)>100)throw new \InvalidArgumentException('Gültige Gruppenliste angeben.');foreach($groups as $group)if(!is_string($group)||strlen($group)>128||!$this->groups->groupExists($group))throw new \InvalidArgumentException('Eine angegebene Nextcloud-Gruppe existiert nicht.');$out[$key]=array_values(array_unique($groups));}$this->store->put('access',$out);
 }
 public function permits(string $role, string $method, string $path): bool {
  $levels=['viewer'=>0,'operator'=>1,'admin'=>2];
  if (!isset($levels[$role])) return false;
  $rules=json_decode(file_get_contents(__DIR__.'/../../appinfo/native-policy.json'),true,512,JSON_THROW_ON_ERROR);
  foreach ($rules as $rule) if ($rule['method']===$method && preg_match('~'.$rule['pattern'].'~D',$path)===1) return $levels[$role]>=$levels[$rule['role']];
  return false;
 }
}
