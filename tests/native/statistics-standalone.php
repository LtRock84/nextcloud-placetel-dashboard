<?php
declare(strict_types=1);
// Synthetic fixtures only; no Nextcloud installation or network access.
namespace OCP { interface IPhoneNumberUtil { public function convertToStandardFormat($phoneNumber, $defaultRegion); } }
namespace {
 $analysisPath=__DIR__.'/../../../nextcloud/placetel/lib/Native/Analysis.php';
 if(!is_file($analysisPath))$analysisPath=__DIR__.'/../../placetel/lib/Native/Analysis.php';
 require $analysisPath;
 final class SyntheticPhones implements \OCP\IPhoneNumberUtil {
  public function convertToStandardFormat($value,$region) {
   $clean=preg_replace('/[^+0-9]/','',$value);
   if(str_starts_with($clean,'00'))$clean='+'.substr($clean,2);
   if(str_starts_with($clean,'0'))$clean='+49'.substr($clean,1);
   return strlen($clean)>=10&&str_starts_with($clean,'+')?$clean:null;
  }
 }
 function equivalent($a,$b): bool {
  if(is_numeric($a)&&is_numeric($b))return abs((float)$a-(float)$b)<0.00001;
  if(is_array($a)&&is_array($b)){if(array_keys($a)!==array_keys($b)){ksort($a);ksort($b);if(array_keys($a)!==array_keys($b))return false;}foreach($a as $k=>$v)if(!equivalent($v,$b[$k]))return false;return true;}
  return $a===$b;
 }
 $fixture=json_decode(file_get_contents(__DIR__.'/../native-parity.json'),true,512,JSON_THROW_ON_ERROR);
 $a=new \OCA\Placetel\Native\Analysis(new SyntheticPhones());$n=0;
 foreach($fixture['stats'] as $f){if(!equivalent($a->statistics($f['calls'],$f['coverage'],$f['options']),$f['expected']))throw new \RuntimeException('statistics parity: '.$f['name']);$n++;}
 $f=$fixture['adapter'];if(!equivalent($a->adapt($f['rows'],$f['agents'],$f['queues'],$f['aliases']),$f['expected']))throw new \RuntimeException('adapter parity');
 $viewsPath=dirname($analysisPath).'/Views.php';require $viewsPath;
 $v=new \OCA\Placetel\Native\Views($a);$f=$fixture['report'];
 if(!equivalent($v->report($f['calls'],$f['queues'],$f['coverage'],$f['states'],$f['from'],$f['to'],$f['asOf']),$f['expected']))throw new \RuntimeException('callback report parity');
 echo 'PASS '.($n+2)." native statistics/adapter/report parity cases\n";
}
