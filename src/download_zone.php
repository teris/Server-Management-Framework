<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

// Sicherheitsprüfung
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('Dateiname erforderlich');
}

$filename = basename($_GET['file']);

// Validiere Dateiname (nur alphanumerische Zeichen, Punkte, Unterstriche und Bindestriche)
if (!preg_match('/^[a-zA-Z0-9._-]+\.txt$/', $filename)) {
    http_response_code(400);
    die('Ungültiger Dateiname');
}

$filepath = sys_get_temp_dir() . '/' . $filename;

// Prüfe, ob Datei existiert
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Datei nicht gefunden: ' . $filename);
}

// Prüfe, ob Datei nicht älter als 1 Stunde ist
if (time() - filemtime($filepath) > 3600) {
    unlink($filepath);
    http_response_code(410);
    die('Datei abgelaufen');
}

// Setze Download-Header
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Ausgabe der Datei
readfile($filepath);

// Lösche temporäre Datei nach Download
unlink($filepath);
?>
