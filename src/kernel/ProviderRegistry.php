<?php
namespace SMF\Core;
use Closure;
use RuntimeException;
final class ProviderRegistry {
    private array $factories=[];
    private array $instances=[];
    private array $owners=[];
    public function __construct(private ?ModuleRegistry $modules=null) {}
    public function register(string $id,Closure $factory): void {
        if(!preg_match('/^[a-z][a-z0-9_.-]{0,79}$/D',$id))throw new RuntimeException('Invalid provider identity');
        if(isset($this->instances[$id]))throw new RuntimeException('Provider already resolved');$this->factories[$id]=$factory;
    }
    public function get(string $id): ProviderClient {
        if(isset($this->owners[$id]))$this->modules->assertEnabled($this->owners[$id]);
        $contribution=$this->modules?->providers()[$id]??null;
        if($this->modules && isset($this->modules->catalog()['provider.'.$id]) && $this->modules->installed('provider.'.$id))$this->modules->assertEnabled('provider.'.$id);
        if(!isset($this->instances[$id])) {
            if(isset($this->factories[$id]))$client=($this->factories[$id])();
            elseif($contribution){$factory=$this->modules->runtime($contribution);if(!is_callable($factory))throw new RuntimeException('Provider factory required');$client=$factory();}
            elseif(in_array($id,['proxmox','ispconfig','ovh'],true))$client=ProviderAccounts::shopActive()?new ShopProviderClient($id):new LegacyProviderClient($id);
            else throw new RuntimeException('Provider module unavailable');
            if(!$client instanceof ProviderClient)throw new RuntimeException('Invalid provider adapter');
            $this->instances[$id]=$client;
            if($contribution)$this->owners[$id]=$contribution['module'];
        }
        return $this->instances[$id];
    }
    public function invalidate(string $id):void {unset($this->instances[$id],$this->owners[$id]);}
    public function descriptors(): array { return $this->modules?->providers()??[]; }
    public function execute(string $provider,string $method,string $path,mixed $payload=null): mixed {
        if(PHP_SAPI!=='cli')\Identity::requireIdentity('backend',true);
        return $this->get($provider)->call($method,$path,$payload);
    }
}
