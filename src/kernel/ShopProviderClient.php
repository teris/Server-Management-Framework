<?php
namespace SMF\Core;
final class ShopProviderClient implements ProviderClient {
    private object $client;
    private string $binding;
    public function __construct(private string $provider) {
        ProviderAccounts::bootstrapShop();
        $this->client=\provider_client($provider);$this->binding=\ResourceAccess::connectionId($provider);
    }
    public function connectionId(): string {return $this->binding;}
    public function call(string $method,string $path,mixed $payload=null): mixed {
        if(!hash_equals($this->binding,\ResourceAccess::connectionId($this->provider)))throw new \RuntimeException('Provider account changed; resolve a new adapter');
        if($this->provider==='ispconfig')return $this->client->call(ltrim($path,'/'),is_array($payload)?$payload:['primary_id'=>$payload]);
        return $this->client->call(strtoupper($method),$path,$payload);
    }
}
