<?php
declare(strict_types=1);
namespace OCP { interface IConfig {} interface IGroupManager {} interface IUserSession {} }
namespace {
 $path=__DIR__.'/../../../nextcloud/placetel/lib/Service/Access.php';if(!is_file($path))$path=__DIR__.'/../../placetel/lib/Service/Access.php';require $path;
 final class ConfigStub implements \OCP\IConfig {} final class GroupsStub implements \OCP\IGroupManager {} final class SessionStub implements \OCP\IUserSession {}
 $access=new \OCA\Placetel\Service\Access(new ConfigStub(),new GroupsStub(),new SessionStub());
 foreach(['viewer'=>false,'operator'=>true,'admin'=>true] as $role=>$expected){if($access->permits($role,'POST','/operations/callbacks/complete')!==$expected)throw new \RuntimeException('wrong permission for '.$role);echo 'PASS '.$role.PHP_EOL;}
 if($access->permits('admin','GET','/operations/callbacks/complete')||$access->permits('operator','POST','/operations/callbacks/complete/extra'))throw new \RuntimeException('unexpected route allowed');
 echo "CALLBACK_PERMISSIONS_COMPLETE\n";
}
