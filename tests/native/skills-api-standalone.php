<?php
declare(strict_types=1);
namespace OCP\Http\Client {interface IClientService {public function newClient(): mixed;}}
namespace OCP\Lock {interface ILockingProvider {} class LockedException extends \Exception {}}
namespace OCA\Placetel\Native {
 final class Store {public array $data=[];public function get(string $k,mixed $d=null): mixed{return $this->data[$k]??$d;}public function put(string $k,mixed $v): void{$this->data[$k]=$v;}}
 final class Secrets {public function get(string $k): string{return 'fixture-secret';}}
 final class RemoteException extends \RuntimeException {public function __construct(string $m,public int $status=0,public int $retrySeconds=0,public bool $beforeRequest=false){parent::__construct($m);}}
}
namespace {
 $path=__DIR__.'/../../../nextcloud/placetel/lib/Native/Api.php';if(!is_file($path))$path=__DIR__.'/../../placetel/lib/Native/Api.php';require $path;
 final class Response {public function __construct(private int $status,private string $body){}public function getStatusCode(): int{return $this->status;}public function getHeader(string $h): string{return '';}public function getBody(): string{return $this->body;}}
 final class Client {public array $calls=[];public function request(string $method,string $url,array $options): Response{$this->calls[]=[$method,$url,$options];return $method==='DELETE'?new Response(204,''):new Response(200,'[]');}}
 final class Service implements \OCP\Http\Client\IClientService {public function __construct(public Client $client){}public function newClient(): mixed{return $this->client;}}
 final class Locks implements \OCP\Lock\ILockingProvider {}
 function check(bool $ok,string $label): void{if(!$ok)throw new \RuntimeException($label);echo "PASS $label\n";}
 function rejected(callable $f): bool{try{$f();return false;}catch(\InvalidArgumentException){return true;}}
 $store=new \OCA\Placetel\Native\Store();$client=new Client();$api=new \OCA\Placetel\Native\Api(new Service($client),$store,new \OCA\Placetel\Native\Secrets(),new Locks());
 foreach(['/call_center_skills?per_page=100&page=1','/call_center_agents/1/skills','/subscriptions'] as $path){$store->data=[];check($api->placetel($path)===[],'allowed read '.$path);}
 $store->data=[];check($api->placetel('/call_center_agents/1/skills/10','PUT',['skill_level'=>3])===[],'skill PUT allowed');
 $store->data=[];check($api->placetel('/call_center_agents/1/skills/10','DELETE')===null,'documented empty 204 DELETE accepted');
 foreach([['DELETE','/call_center_agents/1'],['PUT','/subscriptions'],['DELETE','/subscriptions/1'],['PUT','/call_center_agents/1/skills/10/extra']] as [$method,$path]){$store->data=[];check(rejected(fn()=> $api->placetel($path,$method,[])),'unapproved write rejected');}
 echo "SKILLS_API_COMPLETE\n";
}
