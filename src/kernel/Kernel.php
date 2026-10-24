<?php
namespace SMF\Core;
use PDO;
final class Kernel {
    private static ?self $instance = null;
    private Container $container;
    private function __construct() {
        $this->container = new Container();
        $this->container->bind('database', fn() => \HttpSecurity::connection());
        $this->container->bind('modules', fn($c) => new ModuleRegistry($c->get('database'), dirname(__DIR__,2) . '/extensions', \VersionFile::version(file_get_contents(dirname(__DIR__) . '/sys.conf.php'))));
        $this->container->bind('audit', fn($c) => new AuditLog($c->get('database')));
        $this->container->bind('jobs', fn($c) => new JobQueue($c->get('database'), $c->get('modules'), $c->get('audit')));
        $this->container->bind('providers', fn() => new ProviderRegistry());
    }
    public static function boot(): self { return self::$instance ??= new self(); }
    public static function reset(): void { self::$instance = null; }
    public function database(): PDO { return $this->container->get('database'); }
    public function modules(): ModuleRegistry { return $this->container->get('modules'); }
    public function audit(): AuditLog { return $this->container->get('audit'); }
    public function jobs(): JobQueue { return $this->container->get('jobs'); }
    public function providers(): ProviderRegistry { return $this->container->get('providers'); }
}
