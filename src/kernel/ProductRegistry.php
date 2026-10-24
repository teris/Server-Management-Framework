<?php
namespace SMF\Core;
use RuntimeException;
final class ProductRegistry {
    public function __construct(private ModuleRegistry $modules) {}
    public function catalog(): array { return $this->modules->products(); }
    public function blockers(string $type, array $verifiedCapabilities): array {
        $entry=$this->catalog()[$type]??null;
        if(!$entry)return ['PRODUCT_MODULE_UNAVAILABLE'];
        $missing=[];
        foreach($entry['definition']['requires_capabilities'] as $provider=>$capabilities){
            if(!isset($this->modules->providers()[$provider])){$missing[]='PROVIDER_UNAVAILABLE:'.$provider;continue;}
            foreach($capabilities as $capability)if(!in_array($capability,$verifiedCapabilities[$provider]??[],true))$missing[]='CAPABILITY_REQUIRED:'.$provider.':'.$capability;
        }
        return $missing;
    }
    public function service(string $type): object {
        $entry=$this->catalog()[$type]??throw new RuntimeException('Product module unavailable');
        $factory=$this->modules->runtime($entry);
        if(!is_callable($factory))throw new RuntimeException('Product factory required');
        $service=$factory();if(!is_object($service))throw new RuntimeException('Product service required');return $service;
    }
}
