<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Command;
use OCA\Placetel\Native\Dashboard;
use OCA\Placetel\Native\RemoteException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
final class Notifications extends Command {
 public function __construct(private Dashboard $dashboard){parent::__construct();}
 protected function configure(): void {
  $this->setName('placetel:notifications:work')->setDescription('Verarbeitet ausschließlich die optionale DispoLive-Popup-Warteschlange.')
   ->addOption('seconds',null,InputOption::VALUE_REQUIRED,'Laufzeit in Sekunden; 0 = dauerhaft, Standard = ein Durchlauf.','once');
 }
 protected function execute(InputInterface $input,OutputInterface $output): int {
  $option=$input->getOption('seconds');if($option!=='once'&&(!is_string($option)||!ctype_digit($option)||(float)$option>86400)){$output->writeln('seconds: 0 bis 86400 oder once angeben.');return Command::INVALID;}
  $started=microtime(true);$once=$option==='once';$seconds=$once?0:(int)$option;
  do {
   try{$this->dashboard->notificationsTick();}catch(RemoteException $e){if($e->status!==409){$output->writeln('Popup-Verarbeitung nicht abgeschlossen; App-Status prüfen.');return Command::FAILURE;}}
   catch(\Throwable){$output->writeln('Popup-Verarbeitung nicht abgeschlossen; Nextcloud-Konfiguration prüfen.');return Command::FAILURE;}
   if($once)break;usleep(1000000);
  }while($seconds===0||microtime(true)-$started<$seconds);
  $output->writeln('Popup-Warteschlange geprüft.');return Command::SUCCESS;
 }
}
