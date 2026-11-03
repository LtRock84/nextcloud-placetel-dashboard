<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Service;
use OCP\App\IAppManager;
use OCP\IConfig;

/** User-specific acknowledgement; release notes are shipped with the app. */
final class UpdateNotes {
 public function __construct(private IConfig $config,private IAppManager $apps) {}
 private function version(): string {
  $version=$this->apps->getAppVersion('placetel',false);
  if(!preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/D',$version))throw new \RuntimeException('Ungültige App-Version.');
  return $version;
 }
 public function forUser(string $user): array {
  $version=$this->version();$seen=$this->config->getUserValue($user,'placetel','update_notes_seen','');
  if($seen!==''&&version_compare($seen,$version,'>='))return ['version'=>$version,'pending'=>false,'releases'=>[]];
  $entries=json_decode(file_get_contents(__DIR__.'/../../appinfo/release-notes.json'),true,512,JSON_THROW_ON_ERROR);
  $notes=[];
  foreach($entries as $entry){
   if(!is_array($entry)||!is_string($entry['version']??null)||!is_array($entry['items']??null))continue;
   if(version_compare($entry['version'],$version,'>'))continue;
   // A first-time reader sees the current release; later readers get all missed releases.
   if($seen===''?$entry['version']!==$version:version_compare($entry['version'],$seen,'<='))continue;
   $notes[]=$entry;
  }
  usort($notes,static fn($a,$b)=>version_compare($b['version'],$a['version']));
  if(!$notes)$notes=[['version'=>$version,'items'=>[['title'=>'Update installiert','description'=>'Die App wurde auf eine neue Version aktualisiert.']]]];
  return ['version'=>$version,'pending'=>true,'releases'=>$notes];
 }
 public function acknowledge(string $user,mixed $version): void {
  if(!is_string($version)||$version!==$this->version())throw new \InvalidArgumentException('Die App-Version hat sich geändert. Bitte die Seite neu laden.');
  $this->config->setUserValue($user,'placetel','update_notes_seen',$version);
 }
}
