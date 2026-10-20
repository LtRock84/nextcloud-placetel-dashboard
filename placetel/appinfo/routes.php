<?php
declare(strict_types=1);
return ['routes' => [
 ['name'=>'notify#receive','url'=>'/notify','verb'=>'POST'],
 ['name'=>'page#index','url'=>'/','verb'=>'GET'],
 ['name'=>'page#help','url'=>'/help/{name}','verb'=>'GET','requirements'=>['name'=>'[a-z-]+']],
 ['name'=>'api#forward','url'=>'/api/{path}','verb'=>'GET','requirements'=>['path'=>'.+']],
 ['name'=>'api#forward','url'=>'/api/{path}','verb'=>'POST','postfix'=>'post','requirements'=>['path'=>'.+']],
 ['name'=>'api#forward','url'=>'/api/{path}','verb'=>'DELETE','postfix'=>'delete','requirements'=>['path'=>'.+']],
]];
