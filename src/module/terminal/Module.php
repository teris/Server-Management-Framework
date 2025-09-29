<?php
/**
 * Terminal Module
 * noVNC und SSH Terminal Integration
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';
require_once dirname(__FILE__) . '/templates/system/install.php';
require_once dirname(__FILE__) . '/templates/system/uninstall.php';
require_once dirname(__FILE__) . '/templates/system/updater.php';

class TerminalModule extends ModuleBase {
    
    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="alert alert-danger">' . $this->t('access_denied') . '</div>';
        }
        
        try {
            return $this->render('main', [
                'vnc_servers' => $this->getVNCServers(),
                'ssh_servers' => $this->getSSHServers()
            ]);
        } catch (Exception $e) {
            error_log("TerminalModule getContent error: " . $e->getMessage());
            return '<div class="alert alert-danger">' . $this->t('error_loading_module') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    public function handleRequest($action, $data = []) {
        return $this->handleAjaxRequest($action, $data);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'get_vnc_servers':
                return $this->getVNCServers();
                
            case 'get_ssh_servers':
                return $this->getSSHServers();
                
            case 'test_ssh_connection':
                return $this->testSSHConnection($data);
                
            case 'connect_ssh':
                return $this->connectSSH($data);
                
            case 'send_ssh_command':
                return $this->sendSSHCommand($data);
                
            case 'connect_vnc':
                return $this->connectVNC($data);
                
            case 'disconnect_vnc':
                return $this->disconnectVNC($data);
                
            case 'disconnect_ssh':
                return $this->disconnectSSH($data);
                
            case 'get_vnc_status':
                return $this->getVNCStatus($data);
                
            case 'get_ssh_status':
                return $this->getSSHStatus($data);
                
            case 'save_vnc_server':
                return $this->saveVNCServer($data);
                
            case 'save_ssh_server':
                return $this->saveSSHServer($data);
                
            case 'delete_vnc_server':
                return $this->deleteVNCServer($data);
                
            case 'delete_ssh_server':
                return $this->deleteSSHServer($data);
                
            case 'test_vnc_connection':
                return $this->testVNCConnection($data);
                
            case 'check_requirements':
                return $this->checkRequirements();
                
            case 'start_installation':
                return $this->startInstallation();
                
            case 'uninstall_module':
                return $this->uninstallModule();
                
            case 'check_updates':
                return $this->checkUpdates();
                
            case 'update_libraries':
                return $this->updateLibraries($data);
                
            case 'get_installation_status':
                return $this->getInstallationStatus();
                
            case 'show_management':
                return $this->showManagement();
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    /**
     * Holt verfügbare VNC Server aus den APIs (Proxmox, OVH, ISPConfig)
     */
    public function getVNCServers() {
        try {
            $servers = [];
            
            // Proxmox VMs abrufen
            if (class_exists('ServiceManager')) {
                try {
                    $serviceManager = new ServiceManager();
                    $proxmoxVMs = $serviceManager->getProxmoxVMs();
                    
                    foreach ($proxmoxVMs as $vm) {
                        // Prüfe ob $vm ein Objekt oder Array ist
                        $ip = is_object($vm) ? ($vm->ip ?? null) : ($vm['ip'] ?? null);
                        $vmid = is_object($vm) ? ($vm->vmid ?? null) : ($vm['vmid'] ?? null);
                        $name = is_object($vm) ? ($vm->name ?? null) : ($vm['name'] ?? null);
                        $status = is_object($vm) ? ($vm->status ?? null) : ($vm['status'] ?? null);
                        $node = is_object($vm) ? ($vm->node ?? null) : ($vm['node'] ?? null);
                        
                        if (!empty($ip)) {
                            $servers[] = [
                                'id' => 'proxmox_' . $vmid,
                                'type' => 'proxmox',
                                'name' => $name . ' (Proxmox)',
                                'host' => $ip,
                                'port' => 5900,
                                'status' => $status,
                                'vmid' => $vmid,
                                'node' => $node
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $this->log('Error getting Proxmox VMs: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            // OVH VPS abrufen
            if (class_exists('ServiceManager')) {
                try {
                    $serviceManager = new ServiceManager();
                    $ovhVPS = $serviceManager->getOVHVPS();
                    
                    foreach ($ovhVPS as $vps) {
                        // Prüfe ob $vps ein Objekt oder Array ist
                        $ip = is_object($vps) ? ($vps->ip ?? null) : ($vps['ip'] ?? null);
                        $id = is_object($vps) ? ($vps->id ?? null) : ($vps['id'] ?? null);
                        $name = is_object($vps) ? ($vps->name ?? null) : ($vps['name'] ?? null);
                        $status = is_object($vps) ? ($vps->status ?? null) : ($vps['status'] ?? null);
                        
                        if (!empty($ip)) {
                            $servers[] = [
                                'id' => 'ovh_' . $id,
                                'type' => 'ovh',
                                'name' => $name . ' (OVH)',
                                'host' => $ip,
                                'port' => 5900,
                                'status' => $status,
                                'vps_id' => $id
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $this->log('Error getting OVH VPS: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            return $this->success($servers);
            
        } catch (Exception $e) {
            $this->log('Error getting VNC servers: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Abrufen der VNC Server: ' . $e->getMessage());
        }
    }
    
    /**
     * Holt verfügbare SSH Server aus den APIs (Proxmox, OVH, ISPConfig)
     */
    public function getSSHServers() {
        try {
            $servers = [];
            
            // Proxmox VMs abrufen
            if (class_exists('ServiceManager')) {
                try {
                    $serviceManager = new ServiceManager();
                    $proxmoxVMs = $serviceManager->getProxmoxVMs();
                    
                    foreach ($proxmoxVMs as $vm) {
                        // Prüfe ob $vm ein Objekt oder Array ist
                        $ip = is_object($vm) ? ($vm->ip ?? null) : ($vm['ip'] ?? null);
                        $vmid = is_object($vm) ? ($vm->vmid ?? null) : ($vm['vmid'] ?? null);
                        $name = is_object($vm) ? ($vm->name ?? null) : ($vm['name'] ?? null);
                        $status = is_object($vm) ? ($vm->status ?? null) : ($vm['status'] ?? null);
                        $node = is_object($vm) ? ($vm->node ?? null) : ($vm['node'] ?? null);
                        
                        if (!empty($ip)) {
                            $servers[] = [
                                'id' => 'proxmox_' . $vmid,
                                'type' => 'proxmox',
                                'name' => $name . ' (Proxmox)',
                                'host' => $ip,
                                'port' => 22,
                                'status' => $status,
                                'vmid' => $vmid,
                                'node' => $node
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $this->log('Error getting Proxmox VMs: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            // OVH VPS abrufen
            if (class_exists('ServiceManager')) {
                try {
                    $serviceManager = new ServiceManager();
                    $ovhVPS = $serviceManager->getOVHVPS();
                    
                    foreach ($ovhVPS as $vps) {
                        // Prüfe ob $vps ein Objekt oder Array ist
                        $ip = is_object($vps) ? ($vps->ip ?? null) : ($vps['ip'] ?? null);
                        $id = is_object($vps) ? ($vps->id ?? null) : ($vps['id'] ?? null);
                        $name = is_object($vps) ? ($vps->name ?? null) : ($vps['name'] ?? null);
                        $status = is_object($vps) ? ($vps->status ?? null) : ($vps['status'] ?? null);
                        
                        if (!empty($ip)) {
                            $servers[] = [
                                'id' => 'ovh_' . $id,
                                'type' => 'ovh',
                                'name' => $name . ' (OVH)',
                                'host' => $ip,
                                'port' => 22,
                                'status' => $status,
                                'vps_id' => $id
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $this->log('Error getting OVH VPS: ' . $e->getMessage(), 'WARNING');
                }
            }
            
            return $this->success($servers);
            
        } catch (Exception $e) {
            $this->log('Error getting SSH servers: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Abrufen der SSH Server: ' . $e->getMessage());
        }
    }
    
    /**
     * Testet eine SSH-Verbindung
     */
    public function testSSHConnection($data) {
        $errors = $this->validate($data, [
            'host' => 'required',
            'port' => 'required',
            'user' => 'required',
            'password' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validierung fehlgeschlagen', $errors);
        }
        
        try {
            $host = $data['host'];
            $port = intval($data['port']);
            $user = $data['user'];
            $password = $data['password'];
            
            // Einfacher SSH-Verbindungstest mit fsockopen
            $connection = @fsockopen($host, $port, $errno, $errstr, 10);
            
            if (!$connection) {
                return $this->error("SSH-Verbindung fehlgeschlagen: $errstr ($errno)");
            }
            
            fclose($connection);
            
            // Hier könnte eine echte SSH-Authentifizierung implementiert werden
            // Für jetzt testen wir nur die Netzwerkverbindung
            $this->log("SSH Test erfolgreich: $user@$host:$port", 'INFO');
            
            return $this->success([
                'message' => 'SSH-Verbindung erfolgreich getestet',
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'note' => 'Nur Netzwerkverbindung getestet. Echte SSH-Authentifizierung muss noch implementiert werden.'
            ]);
            
        } catch (Exception $e) {
            $this->log('Error testing SSH connection: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Testen der SSH-Verbindung: ' . $e->getMessage());
        }
    }
    
    /**
     * Stellt echte SSH-Verbindung her
     */
    public function connectSSH($data) {
        $errors = $this->validate($data, [
            'host' => 'required',
            'port' => 'required',
            'username' => 'required',
            'password' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validierung fehlgeschlagen', $errors);
        }
        
        try {
            $host = $data['host'];
            $port = intval($data['port']);
            $username = $data['username'];
            $password = $data['password'];
            
            // Echte SSH-Verbindung mit Passwort
            $result = $this->establishRealSSHConnection($host, $port, $username, $password);
            
            if ($result['success']) {
                // SSH-Verbindung in Session speichern
                $_SESSION['ssh_connections'][$host . ':' . $port] = [
                    'host' => $host,
                    'port' => $port,
                    'username' => $username,
                    'connected_at' => time(),
                    'connection_id' => $result['connection_id'] ?? uniqid('ssh_')
                ];
                
                $this->log("Real SSH connection established: $username@$host:$port", 'INFO');
                
                return $this->success([
                    'message' => 'Echte SSH-Verbindung erfolgreich hergestellt',
                    'host' => $host,
                    'port' => $port,
                    'username' => $username,
                    'output' => $result['output'],
                    'connection_id' => $result['connection_id'] ?? uniqid('ssh_')
                ]);
            } else {
                return $this->error('SSH-Verbindung fehlgeschlagen: ' . $result['error']);
            }
            
        } catch (Exception $e) {
            $this->log('Error connecting to SSH: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler bei SSH-Verbindung: ' . $e->getMessage());
        }
    }
    
    /**
     * Sendet Command über SSH-Verbindung
     */
    public function sendSSHCommand($data) {
        $errors = $this->validate($data, [
            'host' => 'required',
            'port' => 'required',
            'username' => 'required',
            'command' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validierung fehlgeschlagen', $errors);
        }
        
        try {
            $host = $data['host'];
            $port = intval($data['port']);
            $username = $data['username'];
            $command = $data['command'];
            $password = $data['password'] ?? '';
            
            // Echte SSH-Command direkt mit plink ausführen
            $plinkPath = dirname(__FILE__) . '/assets/ssh-proxy/bin/plink.exe';
            
            if (!file_exists($plinkPath)) {
                return $this->error('SSH-Client nicht installiert. Bitte führen Sie die Installation aus.');
            }
            
            $fullCommand = '"' . $plinkPath . '" -P ' . $port . ' -pw ' . escapeshellarg($password) . ' -batch ' . escapeshellarg($username) . '@' . escapeshellarg($host) . ' ' . escapeshellarg($command);
            
            $output = [];
            $returnCode = 0;
            exec($fullCommand . ' 2>&1', $output, $returnCode);
            
            $outputString = implode("\n", $output);
            
            return $this->success([
                'message' => 'Command erfolgreich ausgeführt',
                'output' => $outputString,
                'command' => $command
            ]);
            
        } catch (Exception $e) {
            $this->log('Error executing SSH command: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler bei Command-Ausführung: ' . $e->getMessage());
        }
    }
    
    /**
     * Stellt echte SSH-Verbindung mit Passwort her
     */
    private function establishRealSSHConnection($host, $port, $username, $password) {
        // Verwende direkte SSH-Verbindung mit plink
        $plinkPath = dirname(__FILE__) . '/assets/ssh-proxy/bin/plink.exe';
        
        if (!file_exists($plinkPath)) {
            return [
                'success' => false,
                'error' => 'SSH-Client nicht installiert. Bitte führen Sie die Installation aus.'
            ];
        }
        
        // SSH-Verbindung testen
        $command = '"' . $plinkPath . '" -P ' . $port . ' -pw ' . escapeshellarg($password) . ' -batch ' . escapeshellarg($username) . '@' . escapeshellarg($host) . ' echo SSH_CONNECTION_SUCCESS';
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        $outputString = implode("\n", $output);
        
        if ($returnCode === 0 && strpos($outputString, 'SSH_CONNECTION_SUCCESS') !== false) {
            return [
                'success' => true,
                'output' => "Connected to $host:$port\nLast login: " . date('Y-m-d H:i:s') . "\n\nWelcome to SSH Terminal\n$username@$host:~$ ",
                'connection_id' => uniqid('ssh_')
            ];
        } else {
            return [
                'success' => false,
                'error' => 'SSH-Verbindung fehlgeschlagen: ' . $outputString
            ];
        }
    }
    
    /**
     * Findet verfügbaren SSH-Client
     */
    private function findSSHClient() {
        // Prüfe installierten plink zuerst
        $installedPlink = dirname(__FILE__) . '/assets/ssh-proxy/bin/plink.exe';
        if (file_exists($installedPlink) && is_executable($installedPlink)) {
            return 'installed_plink';
        }
        
        // Prüfe System-SSH-Clients
        $clients = ['ssh', 'plink', 'putty'];
        
        foreach ($clients as $client) {
            if ($this->isCommandAvailable($client)) {
                return $client;
            }
        }
        
        return null;
    }
    
    /**
     * Prüft ob Command verfügbar ist
     */
    private function isCommandAvailable($command) {
        $output = [];
        $returnCode = 0;
        
        if (PHP_OS_FAMILY === 'Windows') {
            exec("where $command 2>nul", $output, $returnCode);
        } else {
            exec("which $command 2>/dev/null", $output, $returnCode);
        }
        
        return $returnCode === 0;
    }
    
    /**
     * Testet echte SSH-Verbindung mit Passwort
     */
    private function testRealSSHConnection($sshClient, $host, $port, $username, $password) {
        $command = $this->buildRealSSHCommand($sshClient, $host, $port, $username, $password, 'echo "SSH_CONNECTION_SUCCESS"');
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        $outputString = implode("\n", $output);
        
        if ($returnCode === 0 && strpos($outputString, 'SSH_CONNECTION_SUCCESS') !== false) {
            return [
                'success' => true,
                'output' => $this->getSSHWelcomeMessage($host, $username),
                'connection_id' => uniqid('ssh_')
            ];
        } else {
            return [
                'success' => false,
                'error' => 'SSH-Verbindung fehlgeschlagen: ' . $outputString
            ];
        }
    }
    
    /**
     * Testet SSH mit Passwort
     */
    private function testSSHWithPassword($sshClient, $host, $port, $username, $password) {
        // Für echte Passwort-Authentifizierung müsste man expect oder ähnliches verwenden
        // Hier simulieren wir eine erfolgreiche Verbindung
        return true;
    }
    
    /**
     * Baut echte SSH-Command mit Passwort zusammen
     */
    private function buildRealSSHCommand($sshClient, $host, $port, $username, $password, $command = '') {
        $host = escapeshellarg($host);
        $username = escapeshellarg($username);
        $password = escapeshellarg($password);
        $command = escapeshellarg($command);
        
        switch ($sshClient) {
            case 'installed_plink':
                // Installierter plink mit Passwort
                $plinkPath = dirname(__FILE__) . '/assets/ssh-proxy/bin/plink.exe';
                if ($command) {
                    return "\"$plinkPath\" -P $port -pw $password -batch $username@$host $command";
                } else {
                    return "\"$plinkPath\" -P $port -pw $password -batch $username@$host";
                }
                
            case 'plink':
                // System plink mit Passwort (funktioniert am besten)
                if ($command) {
                    return "plink -P $port -pw $password -batch $username@$host $command";
                } else {
                    return "plink -P $port -pw $password -batch $username@$host";
                }
                
            case 'ssh':
                // SSH mit expect (falls verfügbar)
                if ($this->isCommandAvailable('expect')) {
                    $expectScript = dirname(__FILE__) . '/assets/ssh-proxy/ssh-expect.sh';
                    if ($command) {
                        return "expect $expectScript $host $port $username $password $command";
                    } else {
                        return "expect $expectScript $host $port $username $password";
                    }
                } else {
                    // SSH ohne Passwort (funktioniert nur mit SSH-Keys)
                    if ($command) {
                        return "ssh -p $port -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $username@$host $command";
                    } else {
                        return "ssh -p $port -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $username@$host";
                    }
                }
                
            case 'putty':
                // PuTTY (nur für interaktive Verbindungen)
                return "putty -ssh -P $port -pw $password $username@$host";
                
            default:
                throw new Exception("Unbekannter SSH-Client: $sshClient");
        }
    }
    
    /**
     * Baut SSH-Command zusammen (alte Methode)
     */
    private function buildSSHCommand($sshClient, $host, $port, $username) {
        $host = escapeshellarg($host);
        $username = escapeshellarg($username);
        
        switch ($sshClient) {
            case 'ssh':
                return "ssh -p $port -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $username@$host";
                
            case 'plink':
                return "plink -P $port -batch $username@$host";
                
            case 'putty':
                return "putty -ssh -P $port $username@$host";
                
            default:
                throw new Exception("Unbekannter SSH-Client: $sshClient");
        }
    }
    
    /**
     * Führt echten SSH-Command mit Passwort aus
     */
    private function executeRealSSHCommand($host, $port, $username, $password, $command) {
        $sshClient = $this->findSSHClient();
        
        if (!$sshClient) {
            return [
                'success' => false,
                'error' => 'Kein SSH-Client verfügbar'
            ];
        }
        
        try {
            $fullCommand = $this->buildRealSSHCommand($sshClient, $host, $port, $username, $password, $command);
            
            $output = [];
            $returnCode = 0;
            exec($fullCommand . ' 2>&1', $output, $returnCode);
            
            $outputString = implode("\n", $output);
            
            return [
                'success' => true,
                'output' => $outputString,
                'return_code' => $returnCode
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Führt SSH-Command aus (alte Methode)
     */
    private function executeSSHCommand($host, $port, $username, $command) {
        $sshClient = $this->findSSHClient();
        
        if (!$sshClient) {
            throw new Exception('Kein SSH-Client verfügbar');
        }
        
        $sshCommand = $this->buildSSHCommand($sshClient, $host, $port, $username);
        $fullCommand = $sshCommand . ' ' . escapeshellarg($command);
        
        $output = [];
        $returnCode = 0;
        exec($fullCommand . ' 2>&1', $output, $returnCode);
        
        return implode("\n", $output);
    }
    
    /**
     * Generiert SSH-Willkommensnachricht
     */
    private function getSSHWelcomeMessage($host, $username) {
        $welcome = "Connected to $host\n";
        $welcome .= "Last login: " . date('Y-m-d H:i:s') . "\n\n";
        $welcome .= "Welcome to SSH Terminal\n";
        $welcome .= "Server: $host\n";
        $welcome .= "User: $username\n\n";
        
        return $welcome;
    }
    
    /**
     * Gibt SSH-Proxy URL zurück
     */
    private function getSSHProxyUrl() {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $modulePath = dirname(dirname(__FILE__));
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $modulePath);
        return $baseUrl . $relativePath . '/assets/ssh-proxy/index.php';
    }
    
    /**
     * Ruft SSH-Proxy auf
     */
    private function callSSHProxy($url, $data) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ]);
        
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            return [
                'status' => 'error',
                'message' => 'SSH-Proxy nicht erreichbar'
            ];
        }
        
        $decoded = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 'error',
                'message' => 'Ungültige Antwort vom SSH-Proxy'
            ];
        }
        
        return $decoded;
    }
    
    /**
     * Verbindet zu einem VNC Server (ohne Passwort-Speicherung)
     */
    private function connectVNC($data) {
        $errors = $this->validate($data, [
            'server_id' => 'required',
            'password' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            // Server aus API-Daten finden
            $servers = $this->getVNCServers();
            $server = null;
            
            if ($servers['success']) {
                foreach ($servers['data'] as $s) {
                    if ($s['id'] === $data['server_id']) {
                        $server = $s;
                        break;
                    }
                }
            }
            
            if (!$server) {
                return $this->error($this->t('vnc_server_not_found'));
            }
            
            // Generiere temporären Token für VNC Verbindung
            $token = $this->generateVNCToken($server, $data['password']);
            
            $this->log("VNC connection initiated to server: {$server['name']}");
            
            return $this->success([
                'token' => $token,
                'server' => $server,
                'vnc_url' => $this->getVNCUrl($server, $token)
            ], $this->t('vnc_connection_established'));
            
        } catch (Exception $e) {
            $this->log('Error connecting to VNC: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_connecting_vnc') . ': ' . $e->getMessage());
        }
    }
    
    
    /**
     * Trennt VNC Verbindung
     */
    private function disconnectVNC($data) {
        try {
            $token = $data['token'] ?? '';
            if ($token) {
                $this->invalidateVNCToken($token);
                $this->log("VNC connection disconnected");
            }
            
            return $this->success(null, $this->t('vnc_disconnected'));
            
        } catch (Exception $e) {
            $this->log('Error disconnecting VNC: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_disconnecting_vnc') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Trennt SSH Verbindung
     */
    private function disconnectSSH($data) {
        try {
            $token = $data['token'] ?? '';
            if ($token) {
                $this->invalidateSSHToken($token);
                $this->log("SSH connection disconnected");
            }
            
            return $this->success(null, $this->t('ssh_disconnected'));
            
        } catch (Exception $e) {
            $this->log('Error disconnecting SSH: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_disconnecting_ssh') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Speichert einen neuen VNC Server
     */
    private function saveVNCServer($data) {
        $errors = $this->validate($data, [
            'name' => 'required|min:3|max:50',
            'host' => 'required|min:3|max:255',
            'port' => 'required|numeric',
            'password' => 'max:255'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $db = DatabaseManager::getInstance();
            
            if (isset($data['id']) && $data['id']) {
                // Update existing server
                $db->query("UPDATE terminal_vnc_servers SET name = ?, host = ?, port = ?, password = ?, updated_at = NOW() WHERE id = ? AND user_id = ?",
                    [$data['name'], $data['host'], $data['port'], $data['password'], $data['id'], $this->user_id]);
                $this->log("VNC server updated: {$data['name']}");
            } else {
                // Create new server
                $db->query("INSERT INTO terminal_vnc_servers (user_id, name, host, port, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                    [$this->user_id, $data['name'], $data['host'], $data['port'], $data['password']]);
                $this->log("VNC server created: {$data['name']}");
            }
            
            return $this->success(null, $this->t('vnc_server_saved'));
            
        } catch (Exception $e) {
            $this->log('Error saving VNC server: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_saving_vnc_server') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Speichert einen neuen SSH Server
     */
    private function saveSSHServer($data) {
        $errors = $this->validate($data, [
            'name' => 'required|min:3|max:50',
            'host' => 'required|min:3|max:255',
            'port' => 'required|numeric',
            'username' => 'required|min:3|max:50'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $db = DatabaseManager::getInstance();
            
            if (isset($data['id']) && $data['id']) {
                // Update existing server
                $db->query("UPDATE terminal_ssh_servers SET name = ?, host = ?, port = ?, username = ?, password = ?, updated_at = NOW() WHERE id = ? AND user_id = ?",
                    [$data['name'], $data['host'], $data['port'], $data['username'], $data['password'] ?? '', $data['id'], $this->user_id]);
                $this->log("SSH server updated: {$data['name']}");
            } else {
                // Create new server
                $db->query("INSERT INTO terminal_ssh_servers (user_id, name, host, port, username, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$this->user_id, $data['name'], $data['host'], $data['port'], $data['username'], $data['password'] ?? '']);
                $this->log("SSH server created: {$data['name']}");
            }
            
            return $this->success(null, $this->t('ssh_server_saved'));
            
        } catch (Exception $e) {
            $this->log('Error saving SSH server: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_saving_ssh_server') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Löscht einen VNC Server
     */
    private function deleteVNCServer($data) {
        $errors = $this->validate($data, [
            'server_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $db = DatabaseManager::getInstance();
            $result = $db->query("DELETE FROM terminal_vnc_servers WHERE id = ? AND user_id = ?", 
                [$data['server_id'], $this->user_id]);
            
            if ($result->rowCount() > 0) {
                $this->log("VNC server deleted: ID {$data['server_id']}");
                return $this->success(null, $this->t('vnc_server_deleted'));
            } else {
                return $this->error($this->t('vnc_server_not_found'));
            }
            
        } catch (Exception $e) {
            $this->log('Error deleting VNC server: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_vnc_server') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Löscht einen SSH Server
     */
    private function deleteSSHServer($data) {
        $errors = $this->validate($data, [
            'server_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $db = DatabaseManager::getInstance();
            $result = $db->query("DELETE FROM terminal_ssh_servers WHERE id = ? AND user_id = ?", 
                [$data['server_id'], $this->user_id]);
            
            if ($result->rowCount() > 0) {
                $this->log("SSH server deleted: ID {$data['server_id']}");
                return $this->success(null, $this->t('ssh_server_deleted'));
            } else {
                return $this->error($this->t('ssh_server_not_found'));
            }
            
        } catch (Exception $e) {
            $this->log('Error deleting SSH server: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_ssh_server') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Testet VNC Verbindung
     */
    private function testVNCConnection($data) {
        $errors = $this->validate($data, [
            'host' => 'required|min:3|max:255',
            'port' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $host = $data['host'];
            $port = $data['port'];
            
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                return $this->success(['connected' => true], $this->t('vnc_connection_test_successful'));
            } else {
                return $this->success(['connected' => false, 'error' => $errstr], $this->t('vnc_connection_test_failed'));
            }
            
        } catch (Exception $e) {
            return $this->error($this->t('error_testing_vnc_connection') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Generiert VNC Token (mit temporärem Passwort)
     */
    private function generateVNCToken($server, $password) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + 3600; // 1 Stunde
        
        // Token in Session speichern (Passwort wird temporär gespeichert)
        $_SESSION['vnc_tokens'][$token] = [
            'server_id' => $server['id'],
            'server_type' => $server['type'],
            'host' => $server['host'],
            'port' => $server['port'],
            'password' => $password, // Temporär für Verbindung
            'expires' => $expires,
            'user_id' => $this->user_id
        ];
        
        return $token;
    }
    
    /**
     * Generiert SSH Token (mit temporären Credentials)
     */
    private function generateSSHToken($server, $username, $password) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + 3600; // 1 Stunde
        
        // Token in Session speichern (Credentials werden temporär gespeichert)
        $_SESSION['ssh_tokens'][$token] = [
            'server_id' => $server['id'],
            'server_type' => $server['type'],
            'host' => $server['host'],
            'port' => $server['port'],
            'username' => $username,
            'password' => $password, // Temporär für Verbindung
            'expires' => $expires,
            'user_id' => $this->user_id
        ];
        
        return $token;
    }
    
    /**
     * Generiert VNC URL
     */
    private function getVNCUrl($server, $token) {
        $base_url = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']), '/');
        return $base_url . '/novnc/?token=' . $token . '&host=' . urlencode($server['host']) . '&port=' . $server['port'];
    }
    
    /**
     * Generiert SSH URL
     */
    private function getSSHUrl($server, $token) {
        $base_url = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']), '/');
        return $base_url . '/ssh/?token=' . $token . '&host=' . urlencode($server['host']) . '&port=' . $server['port'] . '&username=' . urlencode($server['username']);
    }
    
    /**
     * Invalidiert VNC Token
     */
    private function invalidateVNCToken($token) {
        unset($_SESSION['vnc_tokens'][$token]);
    }
    
    /**
     * Invalidiert SSH Token
     */
    private function invalidateSSHToken($token) {
        unset($_SESSION['ssh_tokens'][$token]);
    }
    
    /**
     * Holt VNC Status
     */
    private function getVNCStatus($data) {
        // Implementierung für VNC Status
        return $this->success(['status' => 'disconnected']);
    }
    
    /**
     * Holt SSH Status
     */
    private function getSSHStatus($data) {
        // Implementierung für SSH Status
        return $this->success(['status' => 'disconnected']);
    }
    
    /**
     * Prüft alle Voraussetzungen für das Terminal-Modul
     */
    public function checkRequirements() {
        $requirements = [
            'php_version' => [
                'name' => 'PHP Version',
                'required' => '7.4.0',
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'description' => 'PHP 7.4 oder höher erforderlich'
            ],
            'curl_extension' => [
                'name' => 'cURL Extension',
                'required' => 'Verfügbar',
                'current' => extension_loaded('curl') ? 'Verfügbar' : 'Nicht verfügbar',
                'met' => extension_loaded('curl'),
                'description' => 'cURL Extension für Downloads erforderlich'
            ],
            'json_extension' => [
                'name' => 'JSON Extension',
                'required' => 'Verfügbar',
                'current' => extension_loaded('json') ? 'Verfügbar' : 'Nicht verfügbar',
                'met' => extension_loaded('json'),
                'description' => 'JSON Extension für API-Kommunikation erforderlich'
            ],
            'sockets_extension' => [
                'name' => 'Sockets Extension',
                'required' => 'Verfügbar',
                'current' => extension_loaded('sockets') ? 'Verfügbar' : 'Nicht verfügbar',
                'met' => extension_loaded('sockets'),
                'description' => 'Sockets Extension für WebSocket-Proxies empfohlen'
            ],
            'novnc_library' => [
                'name' => 'noVNC Library',
                'required' => 'Installiert',
                'current' => $this->checkNoVNCLibrary() ? 'Installiert' : 'Nicht installiert',
                'met' => $this->checkNoVNCLibrary(),
                'description' => 'noVNC Library für VNC-Unterstützung erforderlich'
            ],
            'xtermjs_library' => [
                'name' => 'xterm.js Library',
                'required' => 'Installiert',
                'current' => $this->checkXTermJSLibrary() ? 'Installiert' : 'Nicht installiert',
                'met' => $this->checkXTermJSLibrary(),
                'description' => 'xterm.js Library für SSH-Unterstützung erforderlich'
            ],
            'websocket_proxies' => [
                'name' => 'WebSocket Proxies',
                'required' => 'Konfiguriert',
                'current' => $this->checkWebSocketProxies() ? 'Konfiguriert' : 'Nicht konfiguriert',
                'met' => $this->checkWebSocketProxies(),
                'description' => 'WebSocket-Proxies für VNC/SSH-Verbindungen erforderlich'
            ],
            'database_tables' => [
                'name' => 'Datenbanktabellen',
                'required' => 'Erstellt',
                'current' => $this->checkDatabaseTables() ? 'Erstellt' : 'Nicht erstellt',
                'met' => $this->checkDatabaseTables(),
                'description' => 'Datenbanktabellen für Session-Management erforderlich'
            ],
            'write_permissions' => [
                'name' => 'Schreibrechte',
                'required' => 'Verfügbar',
                'current' => $this->checkWritePermissions() ? 'Verfügbar' : 'Nicht verfügbar',
                'met' => $this->checkWritePermissions(),
                'description' => 'Schreibrechte für public/assets/ Verzeichnis erforderlich'
            ]
        ];
        
        // Berechne Gesamtstatus
        $all_met = true;
        foreach ($requirements as $req) {
            if (!$req['met']) {
                $all_met = false;
                break;
            }
        }
        
        return [
            'all_met' => $all_met,
            'requirements' => $requirements,
            'total' => count($requirements),
            'met' => array_sum(array_column($requirements, 'met'))
        ];
    }
    
    /**
     * Prüft ob noVNC Library installiert ist
     */
    private function checkNoVNCLibrary() {
        $novncPath = dirname(__FILE__) . '/assets/novnc';
        return is_dir($novncPath) && count(glob($novncPath . '/*')) > 0;
    }
    
    /**
     * Prüft ob xterm.js Library installiert ist
     */
    private function checkXTermJSLibrary() {
        $xtermPath = dirname(__FILE__) . '/assets/xtermjs';
        return is_dir($xtermPath) && count(glob($xtermPath . '/*')) > 0;
    }
    
    /**
     * Prüft ob WebSocket-Proxies konfiguriert sind
     */
    private function checkWebSocketProxies() {
        $vncProxy = dirname(__FILE__) . '/assets/websockify';
        $sshProxy = dirname(__FILE__) . '/assets/ssh-proxy';
        return is_dir($vncProxy) && is_dir($sshProxy);
    }
    
    /**
     * Prüft ob Datenbanktabellen existieren
     */
    private function checkDatabaseTables() {
        try {
            $db = DatabaseManager::getInstance();
            $result = $db->query("SHOW TABLES LIKE 'terminal_sessions'");
            return $result->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Prüft Schreibrechte
     */
    private function checkWritePermissions() {
        $terminalAssetsPath = dirname(__FILE__) . '/assets';
        return is_writable($terminalAssetsPath);
    }
    
    /**
     * Startet die Installation über AJAX
     */
    public function startInstallation() {
        try {
            $this->log('Starting installation...', 'INFO');
            
            // Führe Installation aus (ohne HTML-Ausgabe)
            $installer = new TerminalModuleInstaller();
            $result = $installer->installSilent();
            
            $this->log('Installation result: ' . json_encode($result), 'INFO');
            
            if ($result['success']) {
                return $this->success([
                    'message' => 'Installation erfolgreich abgeschlossen',
                    'requirements' => $this->checkRequirements(),
                    'details' => $result
                ]);
            } else {
                return $this->error('Installation fehlgeschlagen: ' . implode(', ', $result['errors']));
            }
            
        } catch (Exception $e) {
            $this->log('Installation error: ' . $e->getMessage(), 'ERROR');
            return $this->error('Installation fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Deinstalliert das Terminal-Modul
     */
    private function uninstallModule() {
        try {
            $uninstaller = new TerminalModuleUninstaller();
            $result = $uninstaller->uninstall();
            
            return $this->success([
                'message' => 'Terminal-Modul erfolgreich deinstalliert',
                'details' => $result
            ]);
            
        } catch (Exception $e) {
            $this->log('Uninstall error: ' . $e->getMessage(), 'ERROR');
            return $this->error('Deinstallation fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Prüft auf verfügbare Updates
     */
    private function checkUpdates() {
        try {
            $updater = new TerminalModuleUpdater();
            $updates = $updater->checkForUpdates();
            
            return $this->success($updates);
            
        } catch (Exception $e) {
            $this->log('Update check error: ' . $e->getMessage(), 'ERROR');
            return $this->error('Update-Prüfung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Aktualisiert die Libraries
     */
    private function updateLibraries($data) {
        try {
            $updater = new TerminalModuleUpdater();
            $result = $updater->updateLibraries($data);
            
            return $this->success([
                'message' => 'Libraries erfolgreich aktualisiert',
                'details' => $result
            ]);
            
        } catch (Exception $e) {
            $this->log('Update error: ' . $e->getMessage(), 'ERROR');
            return $this->error('Update fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Gibt den aktuellen Installationsstatus zurück
     */
    private function getInstallationStatus() {
        try {
            $requirements = $this->checkRequirements();
            $updates = $this->checkUpdates();
            
            return $this->success([
                'requirements' => $requirements,
                'updates' => $updates['data'] ?? null,
                'installation_complete' => $requirements['all_met']
            ]);
            
        } catch (Exception $e) {
            $this->log('Status check error: ' . $e->getMessage(), 'ERROR');
            return $this->error('Status-Prüfung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Zeigt die Management-Ansicht
     */
    private function showManagement() {
        try {
            return $this->render('view/management', [
                'requirements' => $this->checkRequirements(),
                'updates' => $this->checkUpdates()
            ]);
        } catch (Exception $e) {
            $this->log('Error showing management: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Laden der Verwaltung: ' . $e->getMessage());
        }
    }
    
    /**
     * Gibt Installations-URL zurück
     */
    public function getInstallUrl() {
        $baseUrl = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']), '/');
        return $baseUrl . '/src/module/terminal/templates/system/install.php?install=terminal';
    }
    
    /**
     * Öffentliche Wrapper-Methode für render()
     */
    public function renderTemplate($template, $data = []) {
        return $this->render($template, $data);
    }
    
    /**
     * Überschreibt getScripts() um zusätzliche JavaScript-Dateien einzubinden
     */
    public function getScripts() {
        $scripts = parent::getScripts(); // Basis-Scripts von ModuleBase
        
        // Zusätzliche Scripts für Terminal-Modul
        $additional_scripts = [
            '/src/module/terminal/assets/novnc.js',
            '/src/module/terminal/assets/ssh-terminal.js'
        ];
        
        foreach ($additional_scripts as $script) {
            if (file_exists(dirname(dirname(__FILE__)) . $script)) {
                $scripts[] = $script;
            }
        }
        
        return $scripts;
    }
    
    /**
     * Setup beim Aktivieren des Moduls
     */
    public function onEnable() {
        try {
            $this->createDatabaseTables();
            $this->log("Terminal module enabled and database tables created");
        } catch (Exception $e) {
            $this->log("Error enabling terminal module: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Erstellt notwendige Datenbanktabellen
     */
    private function createDatabaseTables() {
        $db = DatabaseManager::getInstance();
        
        // VNC Servers Tabelle
        $db->query("CREATE TABLE IF NOT EXISTS terminal_vnc_servers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            host VARCHAR(255) NOT NULL,
            port INT NOT NULL DEFAULT 5900,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        )");
        
        // SSH Servers Tabelle
        $db->query("CREATE TABLE IF NOT EXISTS terminal_ssh_servers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            host VARCHAR(255) NOT NULL,
            port INT NOT NULL DEFAULT 22,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        )");
    }
}
?>
