<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Placetel\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
final class Version000200Date20261020000000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema=$schemaClosure();
  if(!$schema->hasTable('placetel_entries')) {
   $t=$schema->createTable('placetel_entries');
   $t->addColumn('entry_hash',Types::STRING,['length'=>64,'notnull'=>true]);
   $t->addColumn('scope',Types::STRING,['length'=>32,'notnull'=>true]);
   $t->addColumn('entry_key',Types::STRING,['length'=>128,'notnull'=>true]);
   $t->addColumn('record_day',Types::STRING,['length'=>10,'notnull'=>true,'default'=>'']);
   $t->addColumn('payload',Types::TEXT,['notnull'=>true]);
   $t->setPrimaryKey(['entry_hash']);$t->addIndex(['scope','record_day'],'placetel_scope_day');
  }
  return $schema;
 }
}
