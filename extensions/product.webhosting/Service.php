<?php
namespace SMF\Modules\Webhosting;
final class Service {
    public function provision(array $order,array &$job,array $profile,array $data): void {
        \SMF\Core\ProviderDiagnostics::requireCapabilities('ispconfig',["connection.read", "web.read", "client.create", "web.create"]);
        \provision_ispconfig($order,$job,$profile,$data);
    }
}
