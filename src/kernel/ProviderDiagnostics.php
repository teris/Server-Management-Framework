<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
final class ProviderDiagnostics {
    public static function check(string $provider): array {
        ProviderAccounts::bootstrapShop();
        $modules=Kernel::boot()->modules();$modules->assertEnabled('shop.connections');
        $descriptor=$modules->providers()[$provider]??throw new RuntimeException('Provider module unavailable');
        $client=Kernel::boot()->providers()->get($provider);$connection=$client->connectionId();
        if(!method_exists($client,'test'))throw new RuntimeException('Provider test unavailable');
        Kernel::boot()->database()->prepare('DELETE FROM smf_provider_diagnostics WHERE provider=? AND connection_id=?')->execute([$provider,$connection]);
        Kernel::boot()->database()->prepare("UPDATE shop_integrations SET tested_at=NULL,test_result='pending' WHERE provider=?")->execute([$provider]);
        $report=$client->test();
        if(!hash_equals($connection,\integration_identity($provider)) || !ProviderAccounts::enabled($provider))throw new RuntimeException('Provider changed during diagnostics');
        $capabilities=array_values(array_intersect($descriptor['definition']['capabilities'],$report['capabilities']??['connection.read']));
        $details=array_intersect_key($report['details']??[],array_flip(['api_contract','admin_role_verified','agents','bridge','servers','functions','permissions_source']));
        $db=Kernel::boot()->database();
        $sql='INSERT INTO smf_provider_diagnostics (provider,connection_id,capabilities,details,checked_at) VALUES (?,?,?,?,?)';
        $sql.=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ON DUPLICATE KEY UPDATE connection_id=VALUES(connection_id),capabilities=VALUES(capabilities),details=VALUES(details),checked_at=VALUES(checked_at)':' ON CONFLICT(provider) DO UPDATE SET connection_id=excluded.connection_id,capabilities=excluded.capabilities,details=excluded.details,checked_at=excluded.checked_at';
        $db->prepare($sql)->execute([$provider,$connection,json_encode($capabilities,JSON_THROW_ON_ERROR),json_encode($details,JSON_THROW_ON_ERROR),time()]);
        $db->prepare("UPDATE shop_integrations SET tested_at=CURRENT_TIMESTAMP,test_result='ok' WHERE provider=?")->execute([$provider]);
        Kernel::boot()->audit()->record('provider.checked',$provider,'ok');
        return ['provider'=>$provider,'capabilities'=>$capabilities,'details'=>$details,'message'=>$report['message']??'Verbindung geprüft.'];
    }
    public static function capabilities(string $provider): array {
        $modules=Kernel::boot()->modules();
        if(!$modules->enabled('shop.connections') || !isset($modules->providers()[$provider]) || !ProviderAccounts::enabled($provider))return [];
        $q=Kernel::boot()->database()->prepare('SELECT * FROM smf_provider_diagnostics WHERE provider=?');$q->execute([$provider]);$row=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        if(!$row || $row['checked_at']<time()-86400 || !hash_equals($row['connection_id'],\integration_identity($provider)))return [];
        return json_decode($row['capabilities'],true,64,JSON_THROW_ON_ERROR);
    }
    public static function requireCapabilities(string $provider,array $required): void {
        if(array_diff($required,self::capabilities($provider)))throw new RuntimeException('Erforderliche Providerfähigkeiten fehlen; Anschluss erneut prüfen.');
    }
}
