<?php
namespace SMF\Setup;
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Discovery reads known markers and explicitly supplied HTTPS endpoints only. */
final class ProviderDetector {
    private float $deadline=0;
    public function __construct(private ?\Closure $transport = null) {}

    public function local(array $roots = []): array {
        $markers = [
            'ispconfig' => ['/usr/local/ispconfig/interface/web/remote/json.php', '/usr/local/ispconfig/server/lib/config.inc.php'],
            'ogp' => ['/var/www/ogp/ogp_api.php', '/var/www/html/ogp/ogp_api.php'],
        ];
        foreach (['ispconfig','ogp'] as $id) if (!empty($roots[$id])) {
            if (!is_string($roots[$id]) || !str_starts_with($roots[$id], '/') || str_contains($roots[$id], "\0")) throw new \InvalidArgumentException('Ungültiger lokaler Panelpfad.');
            $markers[$id][] = rtrim($roots[$id], '/') . ($id === 'ogp' ? '/ogp_api.php' : '/remote/json.php');
        }
        $result = [];
        foreach ($markers as $id => $paths) {
            $found = false;
            foreach ($paths as $path) if (@is_file($path)) { $found = true; break; }
            $result[$id] = ['state'=>$found ? 'local_found' : 'unknown', 'message'=>$found ? 'Lokaler Installationshinweis gefunden; API und Zugang noch prüfen.' : 'Kein zugänglicher lokaler Hinweis. Ein externes Panel oder eingeschränkte Dateirechte bleiben möglich.'];
        }
        return $result;
    }

    public static function baseUrl(string $url): string {
        $p = parse_url($url);
        if (!$p || !filter_var($url,FILTER_VALIDATE_URL) || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || preg_match('/[\x00-\x20\\\\]/', $url)) throw new \InvalidArgumentException('Eine HTTPS-Paneladresse ohne Zugangsdaten oder Query angeben.');
        return rtrim($url, '/');
    }

    public function probe(string $id, array $input): array {
        $this->deadline=microtime(true)+8;
                if(in_array($id,['proxmox','ovh'],true))return $this->extraProvider($id,$input);
        if (!in_array($id, ['ispconfig','ogp'], true)) throw new \InvalidArgumentException('Unbekanntes Panelsystem.');
        if (empty($input['url'])) return ['state'=>'not_configured','enabled'=>false,'message'=>'Nicht ausgewählt.'];
        $url = self::baseUrl($input['url']);
        $endpoint = $url . ($id === 'ogp' ? '/ogp_api.php' : '/remote/json.php');
        try {
            if ($id === 'ogp') {
                $token = $input['token'] ?? '';
                $r = $this->request($endpoint . '?token/test', ['token'=>$token ?: str_repeat('0',64)], false, $input['ca_file'] ?? '');
                if ($r['status'] === 404) return $this->result('endpoint_missing', 'OGP-API unter dieser Adresse nicht gefunden. Paneladresse und API-Konfiguration prüfen.');
                $body = json_decode($r['body'], true);
                if (!is_array($body) || !isset($body['status']) || !is_string($body['message'] ?? null)) return $this->result('unrecognized', 'Antwort ist keine unterstützte OGP-API-Antwort.');
                if ((string)$body['status'] === '401' || $r['status'] === 403) return $this->result('access_denied', 'API erreichbar; Freigabe der Shop-IP im OGP-Panel prüfen.');
                if ($token === '') return $this->result('credentials_required', 'OGP-API erkannt. Vorhandenen API-Token angeben.');
                if ($r['status'] !== 200 || (string)$body['status'] !== '200') return $this->result('authentication_failed', 'OGP-API erkannt; Token wurde nicht bestätigt.');
                if ($body['message'] !== 'admin') return $this->result('permission_denied', 'Token ist gültig; für die Betreiberanbindung fehlen Administratorrechte.');
                $list = $this->request($endpoint . '?server/list', ['token'=>$token], false, $input['ca_file'] ?? '');
                $nodes = json_decode($list['body'], true);
                if ($list['status'] !== 200 || (string)($nodes['status'] ?? '') !== '200' || !is_array($nodes['message'] ?? null)) return $this->result('permission_denied', 'Token bestätigt; die Serverliste konnte nicht gelesen werden.');
                return $this->result('ready', 'OGP-API und Betreiberzugang geprüft. Die Shop-Bereitstellung für Gameserver benötigt das geplante Gameservermodul.') + ['agent_count'=>count($nodes['message']),'capabilities'=>['authentication','server_list'],'game_provisioning'=>false];
            }
            if (empty($input['username']) || empty($input['password'])) return $this->result('credentials_required', 'Remote-Benutzer und Passwort für die ISPConfig-API angeben.');
            $login = $this->request($endpoint . '?login', ['username'=>$input['username'],'password'=>$input['password'],'client_login'=>false], true, $input['ca_file'] ?? '');
            if ($login['status'] === 404) return $this->result('endpoint_missing', 'ISPConfig-JSON-API nicht gefunden. Basisadresse ohne /remote/json.php angeben.');
            $body = json_decode($login['body'], true);
            if (!is_array($body) || !isset($body['code'])) return $this->result('unrecognized', 'Antwort ist keine unterstützte ISPConfig-API-Antwort.');
            if ($login['status'] !== 200 || $body['code'] !== 'ok' || !is_string($body['response'] ?? null) || $body['response'] === '') return $this->result('authentication_failed', 'ISPConfig-API erkannt; Remote-Zugang wurde nicht bestätigt.');
            $session = $body['response'];
            try {
                $server = $this->request($endpoint . '?server_get', ['session_id'=>$session,'server_id'=>(int)($input['server_id'] ?? 1)], true, $input['ca_file'] ?? '');
                $body = json_decode($server['body'], true);
                if ($server['status'] !== 200 || ($body['code'] ?? '') !== 'ok' || empty($body['response'])) return $this->result('permission_denied', 'Anmeldung bestätigt; Server-ID und Leseberechtigung server_get prüfen.');
                return $this->result('ready', 'ISPConfig-API, Anmeldung und Serverzugriff geprüft. Produktspezifische Berechtigungen werden separat eingerichtet.') + ['capabilities'=>['authentication','server_read']];
            } finally {
                try { $this->request($endpoint . '?logout', ['session_id'=>$session], true, $input['ca_file'] ?? ''); } catch (\Throwable) {}
            }
        } catch (\Throwable) { return $this->result('unreachable', 'Verbindung nicht überprüfbar. Adresse, Erreichbarkeit, Zertifikat und gegebenenfalls eigene CA prüfen.'); }
    }

    private function extraProvider(string $id,array $input): array {
        try{
            if($id==='proxmox'){
                if(empty($input['url']))return $this->result('not_configured','Nicht ausgewählt.');
                $base=self::baseUrl($input['url']);$headers=[];$ca=$input['ca_file']??'';
                if(($input['auth_mode']??'password')==='token'){
                    if(empty($input['token_id']) || empty($input['token_secret']))return $this->result('credentials_required','Proxmox-Token-ID und Secret fehlen.');
                    if(preg_match('/[\r\n]/',$input['token_id'].$input['token_secret']))throw new \RuntimeException('Invalid token header');
                    $headers[]='Authorization: PVEAPIToken='.$input['token_id'].'='.$input['token_secret'];
                }else{
                    if(empty($input['username']) || empty($input['password']))return $this->result('credentials_required','Proxmox-Benutzer einschließlich Realm und Passwort angeben.');
                    $r=$this->request($base.'/api2/json/access/ticket',['username'=>$input['username'],'password'=>$input['password']],false,$ca);
                    $data=json_decode($r['body'],true)['data']??[];
                    if($r['status']!==200 || !is_string($data['ticket']??null) || str_starts_with($data['ticket'],'PVE:!tfa!'))return $this->result('authentication_failed','Proxmox-Anmeldung wurde nicht bestätigt; für Zwei-Faktor-Konten einen API-Token verwenden.');
                    $headers[]='Cookie: PVEAuthCookie='.rawurlencode($data['ticket']);
                }
                $r=$this->request($base.'/api2/json/nodes',[],false,$ca,'GET',$headers);
                if($r['status']===401)return $this->result('authentication_failed','Proxmox-Zugang wurde abgelehnt.');
                if($r['status']===403)return $this->result('permission_denied','Proxmox verweigert die Node-Leseberechtigung.');
                $nodes=json_decode($r['body'],true)['data']??null;
                if($r['status']!==200 || !is_array($nodes))return $this->result('unrecognized','Keine unterstützte Proxmox-Antwort.');
                return $this->result('ready','Proxmox-Zugang und Nodeliste geprüft. Ziel-Node, Pool und Produktrechte anschließend separat einrichten.')+['capabilities'=>['authentication','node_read']];
            }
            if(empty($input['application_key']))return $this->result('not_configured','Nicht ausgewählt.');
            if(empty($input['application_secret']) || empty($input['consumer_key']))return $this->result('credentials_required','OVH-Anwendungsschlüssel, Secret und Consumer Key angeben.');
            $base=['ovh-eu'=>'https://eu.api.ovh.com/1.0','ovh-ca'=>'https://ca.api.ovh.com/1.0','ovh-us'=>'https://api.us.ovhcloud.com/1.0'][$input['endpoint']??'ovh-eu']??throw new \InvalidArgumentException('Invalid OVH endpoint');
            $r=$this->request($base.'/auth/time',[],false,'','GET');$timestamp=json_decode($r['body'],true);if($r['status']!==200 || !is_int($timestamp))throw new \RuntimeException('OVH clock unavailable');
            $url=$base.'/domain/zone';$signature='$1$'.sha1($input['application_secret'].'+'.$input['consumer_key'].'+GET+'.$url.'++'.$timestamp);
            $headers=['X-Ovh-Application: '.$input['application_key'],'X-Ovh-Consumer: '.$input['consumer_key'],'X-Ovh-Timestamp: '.$timestamp,'X-Ovh-Signature: '.$signature];
            foreach($headers as$header)if(preg_match('/[\r\n]/',$header))throw new \RuntimeException('Invalid OVH header');
            $r=$this->request($url,[],false,'','GET',$headers);
            if($r['status']===401)return $this->result('authentication_failed','OVH-Schlüssel wurde nicht bestätigt.');
            if($r['status']===403)return $this->result('permission_denied','OVH verweigert GET /domain/zone. API-Freigaben prüfen.');
            if($r['status']!==200 || !is_array(json_decode($r['body'],true)))return $this->result('unrecognized','Keine unterstützte OVH-Zonenliste.');
            return $this->result('ready','OVH-Zugriff auf die DNS-Zonenliste geprüft. Registrierungen und VPS-Funktionen benötigen separate Freigaben.')+['capabilities'=>['authentication','zone_read']];
        }catch(\Throwable){return $this->result('unreachable','Provideradresse, Zugang, Zertifikat und Erreichbarkeit prüfen.');}
    }

    private function result(string $state, string $message): array { return ['state'=>$state,'enabled'=>$state === 'ready','message'=>$message]; }

    private function request(string $url, array $data, bool $json, string $ca, string $method="POST", array $headers=[]): array {
        if ($this->transport) return ($this->transport)($url, $data, $json, $ca, $method, $headers);
        if (!extension_loaded('curl')) throw new \RuntimeException('cURL fehlt.');
        if ($ca !== '' && (!is_file($ca) || !is_readable($ca))) throw new \RuntimeException('CA-Datei nicht lesbar.');
        $curl = curl_init($url); $body = '';
        $budget=max(1,min(5000,(int)(($this->deadline-microtime(true))*1000)));
        $options = [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data),CURLOPT_HTTPHEADER=>['Content-Type: '.($json ? 'application/json' : 'application/x-www-form-urlencoded')],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT_MS=>min(2000,$budget),CURLOPT_TIMEOUT_MS=>$budget,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function ($handle, $chunk) use (&$body) { if (strlen($body)+strlen($chunk)>1048576) return 0; $body.=$chunk; return strlen($chunk); }];
        if ($method==='GET'){unset($options[CURLOPT_POST],$options[CURLOPT_POSTFIELDS]);$options[CURLOPT_HTTPGET]=true;}
        $options[CURLOPT_HTTPHEADER]=array_merge($options[CURLOPT_HTTPHEADER],$headers);
        if ($ca !== '') $options[CURLOPT_CAINFO] = $ca;
        try { curl_setopt_array($curl,$options); if (curl_exec($curl) === false) throw new \RuntimeException('Transport fehlgeschlagen.'); return ['status'=>curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'body'=>$body]; }
        finally { curl_close($curl); }
    }
}
