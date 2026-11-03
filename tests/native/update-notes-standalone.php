<?php
declare(strict_types=1);
// Synthetic standalone service test: no Nextcloud account or live data.
namespace OCP { interface IConfig { public function getUserValue($userId,$appName,$key,$default='');public function setUserValue($userId,$appName,$key,$value,$preCondition=null); } }
namespace OCP\App { interface IAppManager { public function getAppVersion(string $appId,bool $useCache=true): string; } }
namespace {
 $path=__DIR__.'/../../../nextcloud/placetel/lib/Service/UpdateNotes.php';if(!is_file($path))$path=__DIR__.'/../../placetel/lib/Service/UpdateNotes.php';require $path;
 final class FakeConfig implements \OCP\IConfig { public array $values=[];public function getUserValue($userId,$appName,$key,$default=''){return $this->values[$userId][$appName][$key]??$default;}public function setUserValue($userId,$appName,$key,$value,$preCondition=null){$this->values[$userId][$appName][$key]=$value;} }
 final class FakeApps implements \OCP\App\IAppManager {public string $version='0.2.5';public function getAppVersion(string $appId,bool $useCache=true): string{return $this->version;} }
 function check(bool $value,string $name):void {if(!$value)throw new \RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
 $config=new FakeConfig();$apps=new FakeApps();$notes=new \OCA\Placetel\Service\UpdateNotes($config,$apps);
 $a=$notes->forUser('alice');$b=$notes->forUser('bob');check($a['pending']&&$b['pending']&&$a['version']==='0.2.5','new users receive current notes');
 $notes->acknowledge('alice','0.2.5');check(!$notes->forUser('alice')['pending'],'acknowledged version stays dismissed');
 check($notes->forUser('bob')['pending'],'acknowledgement isolated per user');
 $notes=new \OCA\Placetel\Service\UpdateNotes($config,$apps);check(!$notes->forUser('alice')['pending'],'acknowledgement persists across service instances');
 $config->setUserValue('carol','placetel','update_notes_seen','0.2.3');$releases=$notes->forUser('carol')['releases'];check(array_column($releases,'version')===['0.2.5','0.2.4'],'skipped versions shown newest first');
 $error=false;try{$notes->acknowledge('bob','0.2.4');}catch(\InvalidArgumentException){$error=true;}check($error&&$notes->forUser('bob')['pending'],'stale tab cannot acknowledge newer version');
 $apps->version='0.2.6';$next=$notes->forUser('alice');check($next['pending']&&$next['version']==='0.2.6'&&$next['releases'][0]['version']==='0.2.6','new version reappears with fallback');
 $apps->version='0.2.4';check(!$notes->forUser('alice')['pending'],'downgrade does not overwrite a newer acknowledgement');
 echo "UPDATE_NOTES_STANDALONE_COMPLETE\n";
}
