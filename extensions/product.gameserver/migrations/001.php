<?php
return static function(PDO $db):void {$s=json_decode(file_get_contents(dirname(__DIR__).'/schema.json'),true,64,JSON_THROW_ON_ERROR);foreach($s['tables'] as $name=>$table)$db->exec(SchemaManager::createTable($name,$table,$db->getAttribute(PDO::ATTR_DRIVER_NAME)));};
