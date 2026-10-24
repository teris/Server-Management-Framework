<?php
namespace SMF\Core;
interface ProviderClient {
    public function call(string $method,string $path,mixed $payload=null): mixed;
    public function connectionId(): string;
}
