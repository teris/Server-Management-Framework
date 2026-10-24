<?php
return static function(array $payload,\SMF\Core\JobContext $context):void {\SMF\Core\ProviderAccounts::bootstrapShop();if(!\SMF\Core\ShopSettings::allowLive())return;$limit=3;$context->checkpoint('running',[]);run_hosting_domain_checks($limit);run_hosting_mail_dns_jobs($limit);};
