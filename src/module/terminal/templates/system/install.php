<?php
/**
 * Terminal Module Installation Script
 * Automatische Installation der notwendigen Abhängigkeiten
 */

class TerminalModuleInstaller {
    private $modulePath;
    private $assetsPath;
    private $errors = [];
    private $warnings = [];
    private $success = [];
    private $showOutput = true;
    
    /**
     * Logging-Methode
     */
    private function log($message, $level = 'INFO') {
        // Einfaches Logging - in Produktion könnte hier ein echtes Log-System verwendet werden
        error_log("[TerminalModuleInstaller] $level: $message");
    }
    
    public function __construct() {
        $this->modulePath = dirname(dirname(dirname(__FILE__)));
        $this->assetsPath = dirname(dirname(dirname(__FILE__))) . '/assets';
    }
    
    /**
     * Hauptinstallation
     */
    public function install() {
        // Prüfe ob wir im Modul-Kontext sind (nur bei direktem Aufruf HTML ausgeben)
        $isDirectCall = isset($_GET['install']) && $_GET['install'] === 'terminal';
        
        if ($isDirectCall) {
            echo "<h2>Terminal Module Installation</h2>\n";
        }
        
        // 1. Abhängigkeiten prüfen
        $this->checkDependencies();
        
        // 2. Verzeichnisse erstellen
        $this->createDirectories();
        
        // 3. Libraries herunterladen
        $this->downloadLibraries();
        
        // 4. WebSocket-Proxies erstellen
        $this->createWebSocketProxies();
        
        // 5. Konfiguration erstellen
        $this->createConfiguration();
        
        // 6. Datenbanktabellen erstellen
        $this->createDatabaseTables();
        
        // 7. Berechtigungen setzen
        $this->setPermissions();
        
        // 8. Installation abschließen
        $this->finalizeInstallation();
        
        // 9. Ergebnisse anzeigen
        $this->showResults();
    }
    
    /**
     * Installation ohne HTML-Ausgabe (für AJAX)
     */
    public function installSilent() {
        // HTML-Output deaktivieren für AJAX
        $this->showOutput = false;
        
        // 1. Abhängigkeiten prüfen
        $this->checkDependencies();
        
        // 2. Verzeichnisse erstellen
        $this->createDirectories();
        
        // 3. Libraries herunterladen
        $this->downloadLibraries();
        
        // 4. WebSocket-Proxies erstellen
        $this->createWebSocketProxies();
        
        // 5. Konfiguration erstellen
        $this->createConfiguration();
        
        // 6. Datenbanktabellen erstellen
        $this->createDatabaseTables();
        
        // 7. Berechtigungen setzen
        $this->setPermissions();
        
        // 8. Installation abschließen
        $this->finalizeInstallation();
        
        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'success_messages' => $this->success
        ];
    }
    
    /**
     * Abhängigkeiten prüfen
     */
    private function checkDependencies() {
        $isDirectCall = isset($_GET['install']) && $_GET['install'] === 'terminal';
        
        if ($this->showOutput) {
            echo "<h3>1. Abhängigkeiten prüfen...</h3>\n";
        }
        
        // PHP Version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = "PHP 7.4 oder höher erforderlich. Aktuelle Version: " . PHP_VERSION;
        } else {
            $this->success[] = "PHP Version OK: " . PHP_VERSION;
        }
        
        // cURL Extension
        if (!extension_loaded('curl')) {
            $this->errors[] = "cURL Extension erforderlich";
        } else {
            $this->success[] = "cURL Extension verfügbar";
        }
        
        // JSON Extension
        if (!extension_loaded('json')) {
            $this->errors[] = "JSON Extension erforderlich";
        } else {
            $this->success[] = "JSON Extension verfügbar";
        }
        
        // WebSocket Support
        if (!extension_loaded('sockets')) {
            $this->warnings[] = "Sockets Extension nicht verfügbar - WebSocket-Proxies funktionieren möglicherweise nicht";
        } else {
            $this->success[] = "Sockets Extension verfügbar";
        }
        
        // Assets-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($this->assetsPath)) {
            if (mkdir($this->assetsPath, 0755, true)) {
                $this->success[] = "Assets-Verzeichnis erstellt: " . $this->assetsPath;
            } else {
                $this->errors[] = "Fehler beim Erstellen des Assets-Verzeichnisses: " . $this->assetsPath;
            }
        }
        
        // Schreibrechte prüfen
        if (!is_writable($this->assetsPath)) {
            $this->errors[] = "Keine Schreibrechte für assets/ Verzeichnis: " . $this->assetsPath;
        } else {
            $this->success[] = "Schreibrechte für assets/ OK";
        }
        
        // CLI-Tools prüfen
        $this->checkCLITools();
        
        // Prüfe heruntergeladene Libraries
        $this->checkDownloadedLibraries();
    }
    
    /**
     * Prüft verfügbare CLI-Tools
     */
    private function checkCLITools() {
        $tools = [
            'wget' => 'wget --version',
            'curl' => 'curl --version',
            'unzip' => 'unzip -v',
            'zip' => 'zip -v'
        ];
        
        // Windows-spezifische Tools
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $tools['powershell'] = 'powershell -Command "Get-Host"';
            $tools['icacls'] = 'icacls /?';
        }
        
        $availableTools = [];
        $missingTools = [];
        
        foreach ($tools as $tool => $command) {
            $output = [];
            $returnCode = 0;
            exec($command . " 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                $availableTools[] = $tool;
                $this->success[] = "CLI-Tool verfügbar: $tool";
            } else {
                $missingTools[] = $tool;
                $this->warnings[] = "CLI-Tool nicht verfügbar: $tool";
            }
        }
        
        // Prüfe ob mindestens ein Download-Tool verfügbar ist
        $downloadTools = array_intersect($availableTools, ['wget', 'curl']);
        if (empty($downloadTools)) {
            $this->errors[] = "Kein Download-Tool verfügbar (wget oder curl erforderlich)";
        } else {
            $this->success[] = "Download-Tools verfügbar: " . implode(', ', $downloadTools);
        }
        
        // Prüfe ob mindestens ein Archiv-Tool verfügbar ist
        $archiveTools = array_intersect($availableTools, ['unzip', 'zip']);
        if (empty($archiveTools)) {
            $this->warnings[] = "Kein Archiv-Tool verfügbar (unzip oder zip empfohlen)";
        } else {
            $this->success[] = "Archiv-Tools verfügbar: " . implode(', ', $archiveTools);
        }
        
        // Prüfe exec() Funktion
        if (!function_exists('exec')) {
            $this->errors[] = "exec() Funktion ist deaktiviert - CLI-Tools können nicht verwendet werden";
        } else {
            $this->success[] = "exec() Funktion verfügbar";
        }
        
        // Prüfe shell_exec() Funktion
        if (!function_exists('shell_exec')) {
            $this->warnings[] = "shell_exec() Funktion ist deaktiviert - Alternative Download-Methoden werden verwendet";
        } else {
            $this->success[] = "shell_exec() Funktion verfügbar";
        }
        
        // Prüfe system() Funktion
        if (!function_exists('system')) {
            $this->warnings[] = "system() Funktion ist deaktiviert - Alternative Download-Methoden werden verwendet";
        } else {
            $this->success[] = "system() Funktion verfügbar";
        }
    }
    
    /**
     * Prüft heruntergeladene Libraries
     */
    private function checkDownloadedLibraries() {
        // Prüfe noVNC
        $novncPath = $this->assetsPath . '/novnc';
        if (is_dir($novncPath) && count(glob($novncPath . '/*')) > 0) {
            $this->success[] = "noVNC Library gefunden";
        } else {
            $this->warnings[] = "noVNC Library nicht gefunden - wird heruntergeladen";
        }
        
        // Prüfe xterm.js
        $xtermPath = $this->assetsPath . '/xtermjs';
        if (is_dir($xtermPath) && count(glob($xtermPath . '/*')) > 0) {
            $this->success[] = "xterm.js Library gefunden";
        } else {
            $this->warnings[] = "xterm.js Library nicht gefunden - wird heruntergeladen";
        }
    }
    
    /**
     * Verzeichnisse erstellen
     */
    private function createDirectories() {
        if ($this->showOutput) {
            echo "<h3>2. Verzeichnisse erstellen...</h3>\n";
        }
        
        $directories = [
            $this->assetsPath . '/novnc',
            $this->assetsPath . '/xtermjs',
            $this->assetsPath . '/websockify',
            $this->assetsPath . '/ssh-proxy',
            $this->modulePath . '/config'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->success[] = "Verzeichnis erstellt: " . basename($dir);
                } else {
                    $this->errors[] = "Fehler beim Erstellen von: " . $dir;
                }
            } else {
                $this->success[] = "Verzeichnis existiert bereits: " . basename($dir);
            }
        }
    }
    
    /**
     * Libraries herunterladen
     */
    private function downloadLibraries() {
        if ($this->showOutput) {
            echo "<h3>3. Libraries herunterladen...</h3>\n";
        }
        
        // noVNC herunterladen
        $this->downloadNoVNC();
        
        // xterm.js herunterladen
        $this->downloadXTermJS();
    }
    
    /**
     * noVNC herunterladen
     */
    private function downloadNoVNC() {
        // Spezifische noVNC URLs (verschiedene Versionen als Fallback)
        $novncUrls = [
            'https://github.com/novnc/noVNC/archive/refs/tags/v1.4.0.zip',
            'https://github.com/novnc/noVNC/archive/refs/heads/master.zip',
            'https://github.com/novnc/noVNC/archive/refs/tags/v1.3.0.zip'
        ];
        
        $novncPath = $this->assetsPath . '/novnc/novnc.zip';
        $success = false;
        
        foreach ($novncUrls as $url) {
            $this->log("Versuche noVNC Download von: $url", 'INFO');
            
            // Versuche zuerst mit cURL
            if ($this->downloadFile($url, $novncPath)) {
                if ($this->extractZip($novncPath, $this->assetsPath . '/novnc/')) {
                    $this->success[] = "noVNC erfolgreich heruntergeladen von: " . basename($url);
                    unlink($novncPath); // ZIP-Datei löschen
                    $success = true;
                    break;
                } else {
                    $this->warnings[] = "Fehler beim Entpacken von noVNC von: " . basename($url);
                }
            } else {
                // Fallback: Versuche mit wget/curl über exec()
                if ($this->downloadWithExec($url, $novncPath, 'noVNC')) {
                    $success = true;
                    break;
                }
            }
        }
        
        if (!$success) {
            $this->errors[] = "Fehler beim Herunterladen von noVNC (alle URLs versucht)";
        }
    }
    
    /**
     * xterm.js herunterladen
     */
    private function downloadXTermJS() {
        // Spezifische xterm.js URLs (verschiedene Versionen als Fallback)
        $xtermUrls = [
            'https://github.com/xtermjs/xterm.js/archive/refs/heads/master.zip',
            'https://github.com/xtermjs/xterm.js/archive/refs/tags/5.3.0.zip',
            'https://github.com/xtermjs/xterm.js/archive/refs/tags/5.2.1.zip',
            'https://github.com/xtermjs/xterm.js/archive/refs/tags/5.1.0.zip'
        ];
        
        $xtermPath = $this->assetsPath . '/xtermjs/xterm.zip';
        $success = false;
        
        foreach ($xtermUrls as $url) {
            $this->log("Versuche xterm.js Download von: $url", 'INFO');
            
            // Versuche zuerst mit cURL
            if ($this->downloadFile($url, $xtermPath)) {
                $this->log("Download erfolgreich, versuche Entpacken...", 'INFO');
                if ($this->extractZip($xtermPath, $this->assetsPath . '/xtermjs/')) {
                    $this->success[] = "xterm.js erfolgreich heruntergeladen von: " . basename($url);
                    unlink($xtermPath); // ZIP-Datei löschen
                    $success = true;
                    break;
                } else {
                    $this->warnings[] = "Fehler beim Entpacken von xterm.js von: " . basename($url);
                }
            } else {
                $this->log("cURL Download fehlgeschlagen, versuche exec()...", 'INFO');
                // Fallback: Versuche mit wget/curl über exec()
                if ($this->downloadWithExec($url, $xtermPath, 'xterm.js')) {
                    $success = true;
                    break;
                }
            }
        }
        
        if (!$success) {
            $this->errors[] = "Fehler beim Herunterladen von xterm.js (alle URLs versucht)";
        }
    }
    
    /**
     * Download mit exec() als Fallback
     */
    private function downloadWithExec($url, $path, $name) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Prüfe verfügbare Tools und erstelle intelligente Kommando-Liste
        $commands = $this->getAvailableDownloadCommands($url, $path);
        
        foreach ($commands as $command) {
            $output = [];
            $returnCode = 0;
            exec($command . " 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($path) && filesize($path) > 0) {
                if ($this->extractZip($path, dirname($path) . '/')) {
                    $this->success[] = "$name erfolgreich heruntergeladen (exec)";
                    unlink($path);
                    return true;
                }
            }
        }
        
        // Fehlermeldung wird in der Hauptmethode hinzugefügt
        return false;
    }
    
    /**
     * Erstellt eine Liste verfügbarer Download-Kommandos
     */
    private function getAvailableDownloadCommands($url, $path) {
        $commands = [];
        
        // Prüfe verfügbare Tools mit erweiterten Optionen
        $tools = [
            'wget' => "wget --timeout=300 --tries=3 --user-agent='TerminalModule/1.0' -O \"$path\" \"$url\"",
            'curl' => "curl -L --connect-timeout 300 --max-time 300 --user-agent 'TerminalModule/1.0' -o \"$path\" \"$url\""
        ];
        
        // Windows-spezifische Tools
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $tools['powershell'] = "powershell -Command \"Invoke-WebRequest -Uri '$url' -OutFile '$path' -TimeoutSec 300 -UserAgent 'TerminalModule/1.0'\"";
            $tools['curl_windows'] = "curl.exe -L --connect-timeout 300 --max-time 300 --user-agent 'TerminalModule/1.0' -o \"$path\" \"$url\"";
        }
        
        // Teste jedes Tool und füge es zur Liste hinzu
        foreach ($tools as $tool => $command) {
            if ($this->isToolAvailable($tool)) {
                $commands[] = $command;
            }
        }
        
        return $commands;
    }
    
    /**
     * Prüft ob ein CLI-Tool verfügbar ist
     */
    private function isToolAvailable($tool) {
        $testCommands = [
            'wget' => 'wget --version',
            'curl' => 'curl --version',
            'powershell' => 'powershell -Command "Get-Host"',
            'curl_windows' => 'curl.exe --version'
        ];
        
        if (!isset($testCommands[$tool])) {
            return false;
        }
        
        $output = [];
        $returnCode = 0;
        exec($testCommands[$tool] . " 2>&1", $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * WebSocket-Proxies erstellen
     */
    private function createWebSocketProxies() {
        if ($this->showOutput) {
            echo "<h3>4. WebSocket-Proxies erstellen...</h3>\n";
        }
        
        // VNC WebSocket-Proxy
        $this->createVNCProxy();
        
        // SSH WebSocket-Proxy
        $this->createSSHProxy();
    }
    
    /**
     * VNC WebSocket-Proxy erstellen
     */
    private function createVNCProxy() {
        $proxyContent = '<?php
/**
 * VNC WebSocket-Proxy
 * Konvertiert WebSocket-Verbindungen zu VNC-TCP-Verbindungen
 */

// Einfacher WebSocket-Proxy für VNC
// In Produktion sollte hier ein echter WebSocket-Server implementiert werden

header("Content-Type: application/json");
echo json_encode([
    "status" => "proxy_created",
    "message" => "VNC WebSocket-Proxy erstellt",
    "note" => "Für Produktion muss ein echter WebSocket-Server implementiert werden"
]);
?>';
        
        $proxyFile = $this->assetsPath . '/websockify/index.php';
        if (file_put_contents($proxyFile, $proxyContent)) {
            $this->success[] = "VNC WebSocket-Proxy erstellt";
        } else {
            $this->errors[] = "Fehler beim Erstellen des VNC WebSocket-Proxies";
        }
    }
    
    /**
     * SSH WebSocket-Proxy erstellen
     */
    private function createSSHProxy() {
        // SSH-Client installieren
        $this->installSSHClient();
        
        // SSH-Proxy erstellen
        $proxyContent = '<?php
/**
 * SSH WebSocket-Proxy
 * Echte SSH-Verbindungen über WebSocket
 */

// SSH-Client-Pfad
$plinkPath = dirname(__FILE__) . "/bin/plink.exe";

// Prüfe ob plink installiert ist
if (!file_exists($plinkPath)) {
    header("Content-Type: application/json");
    echo json_encode([
        "status" => "error",
        "message" => "SSH-Client nicht installiert",
        "error" => "plink.exe nicht gefunden"
    ]);
    exit;
}

// SSH-Verbindung herstellen
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Ungültige JSON-Daten"]);
        exit;
    }
    
    $action = $data["action"] ?? "";
    
    switch ($action) {
        case "connect":
            $result = connectSSH($data);
            echo json_encode($result);
            break;
            
        case "command":
            $result = executeSSHCommand($data);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(["status" => "error", "message" => "Unbekannte Aktion"]);
    }
    exit;
}

function connectSSH($data) {
    global $plinkPath;
    
    $host = $data["host"] ?? "";
    $port = $data["port"] ?? 22;
    $username = $data["username"] ?? "";
    $password = $data["password"] ?? "";
    
    if (empty($host) || empty($username) || empty($password)) {
        return [
            "status" => "error",
            "message" => "Host, Username und Password sind erforderlich"
        ];
    }
    
    // SSH-Verbindung testen
    $command = "\"$plinkPath\" -P $port -pw " . escapeshellarg($password) . " -batch " . escapeshellarg($username) . "@" . escapeshellarg($host) . " echo SSH_CONNECTION_SUCCESS";
    
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);
    
    $outputString = implode("\\n", $output);
    
    if ($returnCode === 0 && strpos($outputString, "SSH_CONNECTION_SUCCESS") !== false) {
        return [
            "status" => "success",
            "message" => "SSH-Verbindung erfolgreich",
            "output" => "Connected to $host:$port\\nLast login: " . date("Y-m-d H:i:s") . "\\n\\nWelcome to SSH Terminal\\n$username@$host:~$ "
        ];
    } else {
        return [
            "status" => "error",
            "message" => "SSH-Verbindung fehlgeschlagen",
            "error" => $outputString
        ];
    }
}

function executeSSHCommand($data) {
    global $plinkPath;
    
    $host = $data["host"] ?? "";
    $port = $data["port"] ?? 22;
    $username = $data["username"] ?? "";
    $password = $data["password"] ?? "";
    $command = $data["command"] ?? "";
    
    if (empty($command)) {
        return [
            "status" => "error",
            "message" => "Kein Command angegeben"
        ];
    }
    
    // SSH-Command ausführen
    $fullCommand = "\"$plinkPath\" -P $port -pw " . escapeshellarg($password) . " -batch " . escapeshellarg($username) . "@" . escapeshellarg($host) . " " . escapeshellarg($command);
    
    $output = [];
    $returnCode = 0;
    exec($fullCommand . " 2>&1", $output, $returnCode);
    
    return [
        "status" => "success",
        "message" => "Command ausgeführt",
        "output" => implode("\\n", $output),
        "command" => $command
    ];
}

// Test-Seite
?>
<!DOCTYPE html>
<html>
<head>
    <title>SSH Terminal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .terminal { 
            width: 800px; 
            height: 400px; 
            border: 1px solid #ccc; 
            font-family: monospace; 
            padding: 10px; 
            background: #000; 
            color: #0f0; 
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .form-group { margin: 10px 0; }
        label { display: inline-block; width: 120px; }
        input { padding: 5px; width: 200px; }
        button { padding: 10px 20px; margin: 5px; }
    </style>
</head>
<body>
    <h1>SSH Terminal</h1>
    
    <form id="sshForm">
        <div class="form-group">
            <label>Host:</label>
            <input type="text" name="host" value="135.125.128.230" required>
        </div>
        
        <div class="form-group">
            <label>Port:</label>
            <input type="number" name="port" value="22" required>
        </div>
        
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" value="root" required>
        </div>
        
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        
        <div class="form-group">
            <button type="submit">SSH Verbindung</button>
            <button type="button" onclick="testCommand()">Command testen</button>
        </div>
    </form>
    
    <div id="terminal" class="terminal"></div>
    
    <script>
        let currentConnection = null;
        
        function addToTerminal(text) {
            const terminal = document.getElementById("terminal");
            terminal.textContent += text;
            terminal.scrollTop = terminal.scrollHeight;
        }
        
        document.getElementById("sshForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                action: "connect",
                host: formData.get("host"),
                port: parseInt(formData.get("port")),
                username: formData.get("username"),
                password: formData.get("password")
            };
            
            addToTerminal("Connecting to SSH...\\n");
            
            fetch("", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    currentConnection = {
                        host: formData.get("host"),
                        port: formData.get("port"),
                        username: formData.get("username"),
                        password: formData.get("password")
                    };
                    
                    addToTerminal("✓ " + data.message + "\\n");
                    if (data.output) {
                        addToTerminal(data.output + "\\n");
                    }
                } else {
                    addToTerminal("✗ " + data.message + "\\n");
                }
            })
            .catch(error => {
                addToTerminal("✗ Fehler: " + error.message + "\\n");
            });
        });
        
        function testCommand() {
            if (!currentConnection) {
                alert("Bitte zuerst SSH-Verbindung herstellen");
                return;
            }
            
            const command = prompt("Command eingeben:", "ls -la");
            if (!command) return;
            
            addToTerminal("$ " + command + "\\n");
            
            const data = {
                action: "command",
                ...currentConnection,
                command: command
            };
            
            fetch("", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    addToTerminal(data.output + "\\n");
                } else {
                    addToTerminal("✗ Error: " + data.message + "\\n");
                }
            })
            .catch(error => {
                addToTerminal("✗ Fehler: " + error.message + "\\n");
            });
        }
    </script>
</body>
</html>';
        
        $proxyFile = $this->assetsPath . '/ssh-proxy/index.php';
        if (file_put_contents($proxyFile, $proxyContent)) {
            $this->success[] = "SSH WebSocket-Proxy erstellt";
        } else {
            $this->errors[] = "Fehler beim Erstellen des SSH WebSocket-Proxies";
        }
    }
    
    /**
     * SSH-Client installieren
     */
    private function installSSHClient() {
        $binDir = $this->assetsPath . '/ssh-proxy/bin';
        
        // Bin-Verzeichnis erstellen
        if (!is_dir($binDir)) {
            if (!mkdir($binDir, 0755, true)) {
                $this->errors[] = "Fehler beim Erstellen des bin-Verzeichnisses";
                return;
            }
        }
        
        $plinkPath = $binDir . '/plink.exe';
        
        // Prüfe ob plink bereits installiert ist
        if (file_exists($plinkPath)) {
            $this->success[] = "SSH-Client bereits installiert";
            return;
        }
        
        // Lade plink herunter
        $plinkUrl = 'https://the.earth.li/~sgtatham/putty/latest/w64/plink.exe';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $content = file_get_contents($plinkUrl, false, $context);
        
        if ($content === false) {
            $this->errors[] = "Download von plink fehlgeschlagen";
            return;
        }
        
        if (file_put_contents($plinkPath, $content) === false) {
            $this->errors[] = "Speichern von plink fehlgeschlagen";
            return;
        }
        
        // Ausführbar machen
        chmod($plinkPath, 0755);
        
        // Teste Installation
        $output = [];
        $returnCode = 0;
        exec('"' . $plinkPath . '" -V 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->success[] = "SSH-Client erfolgreich installiert";
        } else {
            $this->errors[] = "SSH-Client Installation fehlgeschlagen";
        }
    }
    
    /**
     * Konfiguration erstellen
     */
    private function createConfiguration() {
        if ($this->showOutput) {
            echo "<h3>5. Konfiguration erstellen...</h3>\n";
        }
        
        $configContent = '<?php
/**
 * Terminal Module Konfiguration
 */

return [
    "vnc" => [
        "enabled" => true,
        "default_port" => 5900,
        "websocket_path" => "/websockify",
        "timeout" => 30
    ],
    "ssh" => [
        "enabled" => true,
        "default_port" => 22,
        "websocket_path" => "/ssh-proxy",
        "timeout" => 30
    ],
    "security" => [
        "max_connections" => 5,
        "session_timeout" => 3600,
        "require_authentication" => true
    ]
];
?>';
        
        $configFile = $this->modulePath . '/config/config.php';
        if (file_put_contents($configFile, $configContent)) {
            $this->success[] = "Konfigurationsdatei erstellt";
        } else {
            $this->errors[] = "Fehler beim Erstellen der Konfigurationsdatei";
        }
    }
    
    /**
     * Datenbanktabellen erstellen
     */
    private function createDatabaseTables() {
        if ($this->showOutput) {
            echo "<h3>6. Datenbanktabellen erstellen...</h3>\n";
        }
        
        try {
            $db = DatabaseManager::getInstance();
            
            // Terminal Sessions Tabelle (für temporäre Verbindungen)
            $db->query("CREATE TABLE IF NOT EXISTS terminal_sessions (
                id VARCHAR(32) PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('vnc', 'ssh') NOT NULL,
                server_id INT NOT NULL,
                connection_data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at)
            )");
            
            $this->success[] = "Datenbanktabellen erstellt";
            
        } catch (Exception $e) {
            $this->errors[] = "Fehler beim Erstellen der Datenbanktabellen: " . $e->getMessage();
        }
    }
    
    /**
     * Berechtigungen setzen
     */
    private function setPermissions() {
        if ($this->showOutput) {
            echo "<h3>7. Berechtigungen setzen...</h3>\n";
        }
        
        $directories = [
            $this->assetsPath . '/novnc',
            $this->assetsPath . '/xtermjs',
            $this->assetsPath . '/websockify',
            $this->assetsPath . '/ssh-proxy'
        ];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                // Versuche zuerst mit chmod()
                if (chmod($dir, 0755)) {
                    $this->success[] = "Berechtigungen gesetzt für: " . basename($dir);
                } else {
                    // Fallback: Versuche mit exec()
                    $this->setPermissionsWithExec($dir);
                }
            }
        }
    }
    
    /**
     * Berechtigungen mit exec() setzen
     */
    private function setPermissionsWithExec($dir) {
        // Erstelle intelligente Kommando-Liste basierend auf verfügbaren Tools
        $commands = $this->getAvailablePermissionCommands($dir);
        
        foreach ($commands as $command) {
            $output = [];
            $returnCode = 0;
            exec($command . " 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->success[] = "Berechtigungen gesetzt für: " . basename($dir) . " (exec)";
                return true;
            }
        }
        
        $this->warnings[] = "Konnte Berechtigungen nicht setzen für: " . basename($dir);
        return false;
    }
    
    /**
     * Erstellt eine Liste verfügbarer Berechtigungs-Kommandos
     */
    private function getAvailablePermissionCommands($dir) {
        $commands = [];
        
        // Linux/macOS Kommandos
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            if ($this->isToolAvailable('chmod')) {
                $commands[] = "chmod -R 755 \"$dir\"";
            }
        }
        
        // Windows Kommandos
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            if ($this->isToolAvailable('icacls')) {
                $commands[] = "icacls \"$dir\" /grant Everyone:F /T";
            }
            if ($this->isToolAvailable('powershell')) {
                $commands[] = "powershell -Command \"Get-ChildItem -Path '$dir' -Recurse | ForEach-Object { \$_.Attributes = 'Normal' }\"";
            }
        }
        
        return $commands;
    }
    
    /**
     * Installation abschließen
     */
    private function finalizeInstallation() {
        if ($this->showOutput) {
            echo "<h3>8. Installation abschließen...</h3>\n";
        }
        
        // Installation-Log erstellen
        $logContent = "Terminal Module Installation abgeschlossen am " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "Installation erfolgreich: " . (empty($this->errors) ? "Ja" : "Nein") . "\n";
        $logContent .= "Anzahl Fehler: " . count($this->errors) . "\n";
        $logContent .= "Anzahl Warnungen: " . count($this->warnings) . "\n";
        
        file_put_contents($this->modulePath . '/install.log', $logContent);
        
        $this->success[] = "Installation abgeschlossen";
    }
    
    /**
     * Ergebnisse anzeigen
     */
    private function showResults() {
        if ($this->showOutput) {
            echo "<h3>Installation Ergebnisse</h3>\n";
        }
        
        if (!empty($this->success)) {
            echo "<h4 style='color: green;'>Erfolgreich:</h4>\n";
            echo "<ul>\n";
            foreach ($this->success as $msg) {
                echo "<li style='color: green;'>" . htmlspecialchars($msg) . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($this->warnings)) {
            echo "<h4 style='color: orange;'>Warnungen:</h4>\n";
            echo "<ul>\n";
            foreach ($this->warnings as $msg) {
                echo "<li style='color: orange;'>" . htmlspecialchars($msg) . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($this->errors)) {
            echo "<h4 style='color: red;'>Fehler:</h4>\n";
            echo "<ul>\n";
            foreach ($this->errors as $msg) {
                echo "<li style='color: red;'>" . htmlspecialchars($msg) . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (empty($this->errors)) {
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4 style='color: #155724; margin: 0;'>Installation erfolgreich abgeschlossen!</h4>";
            echo "<p style='margin: 10px 0 0 0; color: #155724;'>Das Terminal-Modul ist jetzt einsatzbereit.</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
            echo "<h4 style='color: #721c24; margin: 0;'>Installation mit Fehlern abgeschlossen</h4>";
            echo "<p style='margin: 10px 0 0 0; color: #721c24;'>Bitte beheben Sie die Fehler und führen Sie die Installation erneut aus.</p>";
            echo "</div>";
        }
    }
    
    /**
     * Datei herunterladen (robuste Methode mit mehreren Fallbacks)
     */
    private function downloadFile($url, $path) {
        // Erstelle Verzeichnis falls nicht vorhanden
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Methode 1: cURL mit File-Handle (wie in download.php)
        if (function_exists('curl_version')) {
            if ($this->downloadWithCurlFile($url, $path)) {
                return true;
            }
        }
        
        // Methode 2: cURL mit Return-Transfer
        if ($this->downloadWithCurlReturn($url, $path)) {
            return true;
        }
        
        // Methode 3: file_get_contents (Fallback)
        if ($this->downloadWithFileGetContents($url, $path)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Download mit cURL und File-Handle (robuste Methode)
     */
    private function downloadWithCurlFile($url, $path) {
        $ch = curl_init($url);
        $fp = fopen($path, "w+");
        
        if (!$fp) {
            curl_close($ch);
            return false;
        }
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TerminalModule/1.0');
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        fclose($fp);
        
        if ($result === false || $httpCode !== 200) {
            if ($error) {
                $this->warnings[] = "cURL Fehler: $error";
            }
            return false;
        }
        
        return file_exists($path) && filesize($path) > 0;
    }
    
    /**
     * Download mit cURL und Return-Transfer
     */
    private function downloadWithCurlReturn($url, $path) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TerminalModule/1.0');
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($httpCode === 200 && $data !== false) {
            return file_put_contents($path, $data) !== false;
        }
        
        if ($error) {
            $this->warnings[] = "cURL Return Fehler: $error";
        }
        
        return false;
    }
    
    /**
     * Download mit file_get_contents (Fallback)
     */
    private function downloadWithFileGetContents($url, $path) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'user_agent' => 'TerminalModule/1.0',
                'follow_location' => 1
            ]
        ]);
        
        $data = file_get_contents($url, false, $context);
        
        if ($data === false) {
            $this->warnings[] = "file_get_contents Download fehlgeschlagen";
            return false;
        }
        
        return file_put_contents($path, $data) !== false;
    }
    
    /**
     * ZIP-Datei entpacken
     */
    private function extractZip($zipPath, $extractPath) {
        if (!class_exists('ZipArchive')) {
            $this->log("ZipArchive class not available", 'ERROR');
            return false;
        }
        
        // Stelle sicher, dass das Zielverzeichnis existiert
        if (!is_dir($extractPath)) {
            if (!mkdir($extractPath, 0755, true)) {
                $this->log("Failed to create directory: $extractPath", 'ERROR');
                return false;
            }
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $this->log("Extracting ZIP to: $extractPath", 'INFO');
            $result = $zip->extractTo($extractPath);
            $zip->close();
            
            if ($result) {
                $this->log("ZIP extraction successful", 'INFO');
                return true;
            } else {
                $this->log("ZIP extraction failed", 'ERROR');
                return false;
            }
        } else {
            $this->log("Failed to open ZIP file: $zipPath", 'ERROR');
            return false;
        }
    }
}

// Installation ausführen
if (php_sapi_name() === 'cli' || (isset($_GET['install']) && $_GET['install'] === 'terminal')) {
    $installer = new TerminalModuleInstaller();
    $installer->install();
} elseif (isset($_POST['action']) && $_POST['action'] === 'install') {
    // AJAX Installation
    header('Content-Type: application/json');
    
    try {
        $installer = new TerminalModuleInstaller();
        $installer->install();
        
        echo json_encode([
            'success' => true,
            'message' => 'Installation erfolgreich abgeschlossen'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    // Nur ausgeben wenn direkt aufgerufen
    if (basename($_SERVER['PHP_SELF']) === 'install.php') {
        echo "<h2>Terminal Module Installation</h2>";
        echo "<p>Führen Sie die Installation aus, indem Sie <code>?install=terminal</code> an die URL anhängen.</p>";
        echo "<p>Beispiel: <code>http://yourdomain.com/src/module/terminal/install.php?install=terminal</code></p>";
    }
}
?>
