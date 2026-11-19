<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;

final class Subscriptions {
 public static function diagnostic(mixed $rows): array {
  if(!is_array($rows)||!array_is_list($rows))throw new RemoteException('Ungültige Abonnement-Antwort.');$out=[];
  foreach($rows as $row){
   if(!is_array($row)||!is_string($row['url']??null))throw new RemoteException('Ungültiges Ereignis-Abonnement.');
   $url=parse_url($row['url']);$host=is_array($url)&&isset($url['host'])?(string)$url['host']:'';$scheme=is_array($url)?($url['scheme']??''):'';$events=[];
   foreach(['incoming','outgoing','accepted','hungup','phone'] as $event)if(($row[$event]??false)===true)$events[]=$event;
   $out[]=['origin'=>$scheme==='https'&&$host!==''?'https://'.$host:'Ungültige oder nicht sichere Adresse','events'=>$events,'numberCount'=>is_array($row['numbers']??null)?count($row['numbers']):null];
  }
  return ['checkedAt'=>gmdate('c'),'subscriptions'=>$out];
 }
}
