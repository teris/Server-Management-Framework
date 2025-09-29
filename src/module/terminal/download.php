<?php
// URL der ZIP-Datei
$url = "https://github.com/xtermjs/xterm.js/releases/download/5.3.0/xterm-5.3.0.zip";
$savePath = __DIR__ . "/xterm-5.3.0.zip";

// Prüfen ob cURL verfügbar ist
if (function_exists('curl_version')) {
    $ch = curl_init($url);
    $fp = fopen($savePath, "w+");

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Weiterleitungen folgen
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // SSL überprüfen
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);         // Timeout in Sekunden

    $result = curl_exec($ch);

    if ($result === false) {
        echo "Download fehlgeschlagen: " . curl_error($ch);
    } else {
        echo "Download erfolgreich: $savePath\n";
    }

    curl_close($ch);
    fclose($fp);
} else {
    // Fallback mit file_get_contents
    $data = file_get_contents($url);
    if ($data === false) {
        die("Download fehlgeschlagen!");
    }
    file_put_contents($savePath, $data);
    echo "Download erfolgreich (Fallback): $savePath\n";
}