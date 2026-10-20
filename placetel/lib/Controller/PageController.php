<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Controller;
use OCA\Placetel\Service\Access;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;
final class PageController extends Controller {
 public function __construct(IRequest $request, private Access $access, private IURLGenerator $urls) {parent::__construct('placetel',$request);}
 #[NoAdminRequired]
 #[NoCSRFRequired]
 public function index(): Response {
  $identity=$this->access->identity();
  if ($identity===null) return new DataDisplayResponse('Kein Zugriff auf Telefonie.',403);
  Util::addScript('placetel','main');
  return new TemplateResponse('placetel','main',[
   'api'=>$this->urls->linkToRoute('placetel.api.forward',['path'=>'bootstrap']),
   'role'=>$identity['role'],
   'help'=>$this->urls->linkToRoute('placetel.page.help',['name'=>'ereignisanbindung']),
  ]);
 }
 #[NoAdminRequired]
 #[NoCSRFRequired]
 public function help(string $name): Response {
  if ($this->access->identity()===null) return new DataDisplayResponse('Kein Zugriff.',403);
  if (!in_array($name,['ereignisanbindung','support-anfrage','fehler-melden'],true)) return new DataDisplayResponse('Nicht gefunden.',404);
  return new DataDisplayResponse(file_get_contents(__DIR__.'/../../help/'.$name.'.txt'),200,['Content-Type'=>'text/plain; charset=utf-8','Cache-Control'=>'no-store']);
 }
}
