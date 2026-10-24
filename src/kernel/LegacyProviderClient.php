<?php
namespace SMF\Core;
use RuntimeException;
final class LegacyProviderClient implements ProviderClient {
    public function __construct(private string $provider,private ?object $manager=null) {}
    public function connectionId(): string { return \ResourceAccess::connectionId($this->provider); }
    public function call(string $method,string $path,mixed $payload=null): mixed {
        $manager=$this->manager??=new \ServiceManager();
        $result=match($this->provider){
            'proxmox'=>$manager->ProxmoxAPI(strtolower($method),$path,$payload),
            'ovh'=>$manager->OVHAPI(strtolower($method),$path,$payload),
            'ispconfig'=>$manager->IspconfigAPI(strtolower($method),$path,$payload),
            default=>throw new RuntimeException('Unsupported legacy provider operation')
        };
        if(!\ProviderResult::succeeded($result))throw new RuntimeException('Provider request not confirmed');
        return is_array($result)?($result['data']??$result):$result;
    }
}
