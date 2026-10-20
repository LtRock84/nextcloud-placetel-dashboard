<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\IDBConnection;
final class Store {
 public function __construct(private IDBConnection $db) {}
 public function transaction(callable $fn): mixed {$this->db->beginTransaction();try{$value=$fn();$this->db->commit();return $value;}catch(\Throwable $e){$this->db->rollBack();throw $e;}}
 public function get(string $key,mixed $fallback=null,string $scope='config'): mixed {
  $q=$this->db->getQueryBuilder();$q->select('payload')->from('placetel_entries')->where($q->expr()->eq('entry_hash',$q->createNamedParameter(hash('sha256',$scope.':'.$key))));
  $r=$q->executeQuery();$value=$r->fetchOne();$r->closeCursor();return $value===false?$fallback:json_decode($value,true,512,JSON_THROW_ON_ERROR);
 }
 public function put(string $key,mixed $value,string $scope='config',string $day=''): void {
  if(strlen($key)>128 || strlen($scope)>32)throw new \InvalidArgumentException('Interner Datensatzschlüssel zu lang.');
  $hash=hash('sha256',$scope.':'.$key);$q=$this->db->getQueryBuilder();$q->select('entry_hash')->from('placetel_entries')->where($q->expr()->eq('entry_hash',$q->createNamedParameter($hash)));$result=$q->executeQuery();$exists=$result->fetchOne()!==false;$result->closeCursor();$q=$this->db->getQueryBuilder();
  $json=json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if($exists){$q->update('placetel_entries')->set('payload',$q->createNamedParameter($json))->set('record_day',$q->createNamedParameter($day))->where($q->expr()->eq('entry_hash',$q->createNamedParameter($hash)));}
  else{$q->insert('placetel_entries')->values(['entry_hash'=>$q->createNamedParameter($hash),'scope'=>$q->createNamedParameter($scope),'entry_key'=>$q->createNamedParameter($key),'record_day'=>$q->createNamedParameter($day),'payload'=>$q->createNamedParameter($json)]);}
  $q->executeStatement();
 }
 public function remove(string $key,string $scope='config'): void {
  $q=$this->db->getQueryBuilder();$q->delete('placetel_entries')->where($q->expr()->eq('entry_hash',$q->createNamedParameter(hash('sha256',$scope.':'.$key))));$q->executeStatement();
 }
 public function rows(string $scope,?string $from=null,?string $to=null): array {
  $q=$this->db->getQueryBuilder();$q->select('payload')->from('placetel_entries')->where($q->expr()->eq('scope',$q->createNamedParameter($scope)));
  if($from!==null)$q->andWhere($q->expr()->gte('record_day',$q->createNamedParameter($from)));
  if($to!==null)$q->andWhere($q->expr()->lte('record_day',$q->createNamedParameter($to)));
  $r=$q->executeQuery();$out=[];while($row=$r->fetch())$out[]=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$r->closeCursor();return $out;
 }
 public function replaceDay(string $scope,string $day,array $values): void {
  $this->db->beginTransaction();
  try{$q=$this->db->getQueryBuilder();$q->delete('placetel_entries')->where($q->expr()->eq('scope',$q->createNamedParameter($scope)))->andWhere($q->expr()->eq('record_day',$q->createNamedParameter($day)));$q->executeStatement();foreach($values as $row)$this->put((string)$row['id'],$row,$scope,$day);$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function prune(): void {
  $cutoff=(new \DateTimeImmutable('today',new \DateTimeZone('Europe/Berlin')))->modify('-12 months')->format('Y-m-d');
  $q=$this->db->getQueryBuilder();$q->delete('placetel_entries')->where($q->expr()->neq('record_day',$q->createNamedParameter('')))->andWhere($q->expr()->lt('record_day',$q->createNamedParameter($cutoff)));$q->executeStatement();
  $cutoff=(new \DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d');
  foreach(['dispolive-cache','dispolive-popups'] as $scope){$q=$this->db->getQueryBuilder();$q->delete('placetel_entries')->where($q->expr()->eq('scope',$q->createNamedParameter($scope)))->andWhere($q->expr()->lt('record_day',$q->createNamedParameter($cutoff)));$q->executeStatement();}
 }
 public function log(string $mode,string $action,string $message,string $status='ok',array $extra=[]): void {
  $key=$mode.':audit';$rows=$this->get($key,[]);array_unshift($rows,['id'=>(int)(microtime(true)*1000000),'at'=>gmdate('c'),'action'=>$action,'message'=>$message,'status'=>$status]+$extra);$this->put($key,array_slice($rows,0,100));
 }
}
