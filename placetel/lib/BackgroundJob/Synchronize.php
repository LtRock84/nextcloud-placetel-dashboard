<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\BackgroundJob;
use OCA\Placetel\Native\Dashboard;
use OCA\Placetel\Native\RemoteException;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;
final class Synchronize extends TimedJob {
 public function __construct(ITimeFactory $time,private Dashboard $dashboard,private LoggerInterface $logger){parent::__construct($time);$this->setInterval(60);$this->setAllowParallelRuns(false);}
 protected function run($argument): void {
  $start=microtime(true);try{for($i=0;$i<6&&microtime(true)-$start<35;$i++){$this->dashboard->maintenance();if($i<5)usleep(600000);}}
  catch(RemoteException){}catch(\Throwable){$this->logger->warning('Placetel-Hintergrundabruf nicht abgeschlossen.',['app'=>'placetel']);}
 }
}
