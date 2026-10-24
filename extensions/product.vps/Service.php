<?php
namespace SMF\Modules\Vps;
final class Service {
    public function provision(array $order,array &$job,array $profile,array $data): void {
        \SMF\Core\ProviderDiagnostics::requireCapabilities('proxmox',["connection.read", "node.read", "pool.read", "vm.create"]);
        if(($profile['virtualization']??'qemu')==='lxc')\provision_lxc($order,$job,$profile,$data);else \provision_proxmox($order,$job,$profile,$data);
    }
}
