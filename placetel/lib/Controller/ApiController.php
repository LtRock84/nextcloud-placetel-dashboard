<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Controller;
use OCA\Placetel\Service\Access;
use OCA\Placetel\Native\Dashboard;
use OCA\Placetel\Native\RemoteException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
final class ApiController extends Controller {
 public function __construct(IRequest $request,private Access $access,private Dashboard $dashboard,private LoggerInterface $logger){parent::__construct('placetel',$request);}
 #[NoAdminRequired]
 public function forward(string $path): DataResponse {
  if($this->request->getHeader('requesttoken')===''||$this->request->getHeader('OCS-APIRequest')!==''||!$this->request->passesCSRFCheck())return new DataResponse(['error'=>'Nextcloud-Sitzung abgelaufen. Bitte neu laden.'],403);
  $identity=$this->access->identity();$method=$this->request->getMethod();if(!$identity||!$this->access->permits($identity['role'],$method,'/'.$path))return new DataResponse(['error'=>'Für diese Nextcloud-Rolle nicht freigegeben.'],403);
  try{
   $input=[];$query=[];
   if($method==='GET'){foreach(['from','to','agentId','queueId'] as $name){$value=$this->request->getParam($name);if($value!==null){if(!is_string($value)||strlen($value)>128)throw new \InvalidArgumentException('Ungültiger Filter.');$query[$name]=$value;}}}
   else{
    if(!str_starts_with(strtolower($this->request->getHeader('Content-Type')),'application/json'))return new DataResponse(['error'=>'JSON erforderlich.'],415);
    if((int)$this->request->getHeader('Content-Length')>65536)return new DataResponse(['error'=>'Anfrage zu groß.'],413);
    $f=fopen('php://input','rb');$raw=stream_get_contents($f,65537);fclose($f);if($raw===false||strlen($raw)>65536)return new DataResponse(['error'=>'Anfrage zu groß.'],413);
    $object=json_decode($raw===''?'{}':$raw,false,512,JSON_THROW_ON_ERROR);if(!is_object($object))throw new \InvalidArgumentException('JSON-Objekt erforderlich.');$input=json_decode($raw===''?'{}':$raw,true,512,JSON_THROW_ON_ERROR);
   }
   if($path==='settings'&&$method==='POST'){$this->dashboard->locked(fn()=> $this->access->configure($input));$value=['ok'=>true];}else $value=$this->dashboard->execute($method,$path,$input,$query,$identity);
   $response=new DataResponse($value);$response->addHeader('Cache-Control','no-store');return $response;
  }catch(RemoteException $e){return new DataResponse(['error'=>$e->getMessage()],in_array($e->status,[409,429],true)?$e->status:400);}
  catch(\InvalidArgumentException|\JsonException $e){return new DataResponse(['error'=>$e->getMessage()],400);}
  catch(\Throwable){$this->logger->warning('Placetel-Dashboard: Vorgang nicht abgeschlossen. Server-/Datenbankkonfiguration prüfen.',['app'=>'placetel']);return new DataResponse(['error'=>'Vorgang nicht abgeschlossen. Nextcloud-Administrator kontaktieren.'],500);}
 }
}
