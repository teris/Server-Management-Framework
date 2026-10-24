<?php
namespace SMF\Core;
/** Preserves the legacy named methods while using the shared JSON remote account. */
final class IspconfigSoapBridge {
    private object $client;
    public function __construct(){ProviderAccounts::bootstrapShop();$this->client=new \IspconfigProvider();}
    public function __call(string $method,array $arguments): mixed {
        array_shift($arguments); // Legacy SOAP session belongs to this adapter, never to a request.
        if($method==='logout'){$this->client->close();return true;}
        if(!preg_match('/^[a-z][a-z0-9_]+_(get(?:_[a-z0-9_]+)?|add|update|delete)$/D',$method,$match))throw new \RuntimeException('Unsupported ISPConfig compatibility method');
        $kind=$match[1];$params=[];
        if(str_starts_with($kind,'get')) {
            $name=match($method){'server_get'=>'server_id','client_get_by_groupid'=>'group_id','mail_domain_get_by_domain'=>'domain',default=>'primary_id'};
            if(count($arguments)>1)throw new \RuntimeException('Ambiguous ISPConfig compatibility arguments');
            $params[$name]=$arguments[0]??[];
        }elseif($kind==='add')$params=['client_id'=>$arguments[0]??0,'params'=>$arguments[1]??[]];
        elseif($kind==='update')$params=['client_id'=>$arguments[0]??0,'primary_id'=>$arguments[1]??0,'params'=>$arguments[2]??[]];
        else $params=['primary_id'=>$arguments[0]??0];
        return $this->client->call($method,$params);
    }
}
