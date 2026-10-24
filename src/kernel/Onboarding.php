<?php
namespace SMF\Core;
use RuntimeException;
final class Onboarding {
    public static function status(): array {
        ProviderAccounts::bootstrapShop();$modules=Kernel::boot()->modules();$file=\HttpSecurity::privateDirectory('jobs').'/heartbeat.json';$heartbeat=is_file($file)&&!is_link($file)?json_decode(file_get_contents($file),true):null;
        $checks=['mail'=>['label'=>'SMTP-Verbindung geprüft','ready'=>\smtp_ready(),'url'=>'/admin/email'],'worker'=>['label'=>'Worker innerhalb der letzten drei Minuten aktiv','ready'=>is_array($heartbeat)&&is_int($heartbeat['at']??null)&&$heartbeat['at']>time()-180,'url'=>'/admin/einrichtung'],'seller'=>['label'=>'Rechnungssteller vollständig','ready'=>\invoice_seller_ready(),'url'=>'/admin/shop'],'documents'=>['label'=>'Vertrags- und Widerrufsinformationen hinterlegt','ready'=>(bool)(\checkout_documents()['terms_url']??'')&&(bool)(\checkout_documents()['withdrawal_url']??''),'url'=>'/admin/produkte']];
        $providers=[];foreach($modules->providers() as$id=>$definition)$providers[$id]=['enabled'=>ProviderAccounts::enabled($id),'capabilities'=>ProviderDiagnostics::capabilities($id)];
        $products=[];$registry=new ProductRegistry($modules);foreach($registry->catalog() as$id=>$entry){$caps=[];foreach($providers as$provider=>$data)$caps[$provider]=$data['capabilities'];$products[$id]=['blockers'=>$registry->blockers($id,$caps)];}
        return ['checks'=>$checks,'providers'=>$providers,'products'=>$products,'upgrade'=>UpgradeManager::publicState()];
    }
    public static function testMail(): void {
        $actor=\Identity::requireIdentity('backend',true);$email=$actor['record']['email']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Eigene Administratoradresse prüfen.');
        \HttpSecurity::rateLimit('onboarding-testmail',(string)$actor['id'],3,900);
        try{\test_smtp_connection();$mailer=\configured_mailer();$mailer->addAddress($email);$mailer->Subject='Server Management – Einrichtungstest';$mailer->Body='Diese Testmail wurde aus dem Einrichtungsassistenten an deine eigene Administratoradresse gesendet.';if(!$mailer->send())throw new RuntimeException('SMTP rejected message');}
        catch(\Throwable$error){throw new RuntimeException('Die Testmail wurde vom SMTP-Server nicht bestätigt. Verbindung und Absender prüfen.');}
        \shop_meta_set('onboarding_mail_accepted',(string)time());Kernel::boot()->audit()->record('onboarding.mail.accepted',(string)$actor['id'],'ok');
    }
}
