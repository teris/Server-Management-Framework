<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(PHP_VERSION_ID<80300){fwrite(STDERR,"PHP 8.3 oder neuer ist erforderlich.\n");exit(2);}
ini_set('zend.exception_ignore_args','1');umask(0077);
require_once dirname(__DIR__).'/src/setup/Installer.php';
$options=getopt('',['prepare','detect','install','help']);$installer=new \SMF\Setup\Installer(dirname(__DIR__));
try{
    if(isset($options['prepare'])){echo "Startschlüssel (24 Stunden gültig):\n".$installer->prepare()."\nDanach /setup.php im Browser öffnen.\n";}
    elseif(isset($options['detect']))echo json_encode((new \SMF\Setup\ProviderDetector())->local(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
    elseif(isset($options['install'])){$input=json_decode(stream_get_contents(STDIN,131072),true,64,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('JSON-Objekt auf Standardeingabe erforderlich.');$token=$input['setup_token']??'';if(!is_string($token))throw new InvalidArgumentException('Startschlüssel ungültig.');unset($input['setup_token']);echo json_encode($installer->install($input,$token),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";}
    else echo "Geführte Browserinstallation: php bin/setup.php --prepare\nLokale Hinweise: php bin/setup.php --detect\nAutomatisierte Neuinstallation: php bin/setup.php --install < /privat/setup.json\nZugangsdaten ausschließlich über geschützte Standardeingabe übergeben.\n";
}catch(Throwable $e){$safe=in_array(get_class($e),[InvalidArgumentException::class,RuntimeException::class],true)&&in_array(basename($e->getFile()),['Installer.php','ProviderDetector.php','setup.php'],true);fwrite(STDERR,($safe?$e->getMessage():'Installation gestoppt. Privates Journal prüfen.')."\n");exit(1);}
