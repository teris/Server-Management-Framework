<?php
return static function (PDO $db): void {
    if (!in_array($db->getAttribute(PDO::ATTR_DRIVER_NAME), ['sqlite','mysql'], true)) throw new RuntimeException('Shop storage currently supports SQLite and MySQL/MariaDB; core PostgreSQL remains supported.');
    require_once dirname(__DIR__) . '/legacy/bootstrap.php';
    shop_migrate($db);
};
