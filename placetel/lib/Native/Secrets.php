<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
use OCP\Security\ICrypto;
final class Secrets {
 public function __construct(private Store $store,private ICrypto $crypto) {}
 public function get(string $name): ?string {
  $value=$this->store->get($name,null,'secrets');return is_string($value)?$this->crypto->decrypt($value):null;
 }
 public function save(string $name,#[\SensitiveParameter] string $value): void {
  if(strlen($value)<8||strlen($value)>4096||preg_match('/\s/',$value))throw new \InvalidArgumentException('Gültigen Schlüssel ohne Leerzeichen eingeben.');
  $this->store->put($name,$this->crypto->encrypt($value),'secrets');
 }
 public function remove(string $name): void {$this->store->remove($name,'secrets');}
}
