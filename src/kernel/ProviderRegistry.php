<?php
namespace SMF\Core;
use Closure;
use RuntimeException;
final class ProviderRegistry {
    private array $factories=[];
    private array $instances=[];
    public function register(string $id,Closure $factory): void {
        if(!in_array($id,['proxmox','ispconfig','ovh','ogp'],true))throw new RuntimeException('Unknown provider');
        if(isset($this->instances[$id]))throw new RuntimeException('Provider already resolved');$this->factories[$id]=$factory;
    }
    public function get(string $id): ProviderClient {
        if(!isset($this->instances[$id]))$this->instances[$id]=isset($this->factories[$id])?($this->factories[$id])():(ProviderAccounts::shopActive() && $id!=='ogp'?new ShopProviderClient($id):new LegacyProviderClient($id));
        return $this->instances[$id];
    }
    public function execute(string $provider,string $method,string $path,mixed $payload=null): mixed {
        if(PHP_SAPI!=='cli')\Identity::requireIdentity('backend',true);
        return $this->get($provider)->call($method,$path,$payload);
    }
}
