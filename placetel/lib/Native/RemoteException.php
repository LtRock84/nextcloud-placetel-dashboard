<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Native;
final class RemoteException extends \RuntimeException {
 public function __construct(string $message,public int $status=0,public int $retrySeconds=10,public bool $beforeRequest=false){parent::__construct($message);}
}
