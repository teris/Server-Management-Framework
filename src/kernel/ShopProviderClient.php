<?php
namespace SMF\Core;
final class ShopProviderClient implements ProviderClient {
    private object $client;
    private string $binding;
    private string $revision;
    public function __construct(private string $provider) {
        ProviderAccounts::bootstrapShop();
        $this->client=\provider_client($provider);$this->binding=\ResourceAccess::connectionId($provider);$this->revision=hash('sha256',json_encode(\integration_config($provider),JSON_THROW_ON_ERROR));
    }
    public function connectionId(): string {return $this->binding;}
    public function test(): array { $this->assertCurrent();return $this->client->test(); }
    private function assertCurrent():void {if(!ProviderAccounts::enabled($this->provider) || !hash_equals($this->binding,\ResourceAccess::connectionId($this->provider)) || !hash_equals($this->revision,hash('sha256',json_encode(\integration_config($this->provider),JSON_THROW_ON_ERROR))))throw new \RuntimeException('Provider account changed or disabled; resolve a new adapter');}
    public function call(string $method,string $path,mixed $payload=null): mixed {
        $this->assertCurrent();
        if(!ProviderAccounts::enabled($this->provider))throw new \RuntimeException('Provider disabled');
        if(!hash_equals($this->binding,\ResourceAccess::connectionId($this->provider)))throw new \RuntimeException('Provider account changed; resolve a new adapter');
        if($this->provider==='ispconfig')return $this->client->call(ltrim($path,'/'),is_array($payload)?$payload:['primary_id'=>$payload]);
        return $this->client->call(strtoupper($method),$path,$payload);
    }
}
