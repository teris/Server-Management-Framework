<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
$key = $_GET['mod'] ?? '';
if (!is_string($key) || !in_array($key, ResourceAccess::MODULES, true)) HttpSecurity::abort(404, 'Modul nicht gefunden.');
ResourceAccess::requireLegacyModule($key);
$selected = ModuleLoader::getInstance()->getModule($key);
if (!$selected) HttpSecurity::abort(404, 'Modul nicht gefunden.');
echo $selected->getContent();
