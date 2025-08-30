/**
 * JavaScript-Loader für alle inc-js Dateien
 * Diese Datei lädt alle benötigten JavaScript-Dateien basierend auf der aktuellen Seite
 */

(function() {
    'use strict';
    
    // Funktion zum Laden von JavaScript-Dateien
    function loadScript(src, callback) {
        var script = document.createElement('script');
        script.src = src;
        script.onload = callback;
        script.onerror = function() {
            console.error('Fehler beim Laden von:', src);
        };
        document.head.appendChild(script);
    }
    
    // Funktion zum Laden von CSS-Dateien
    function loadCSS(href) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = href;
        document.head.appendChild(link);
    }
    
    // Bestimme die aktuelle Seite basierend auf der URL oder anderen Indikatoren
    function getCurrentPage() {
        var url = window.location.href;
        var path = window.location.pathname;
        
        // Prüfe verschiedene Möglichkeiten, die aktuelle Seite zu identifizieren
        if (url.includes('option=users') || url.includes('users.php') || document.querySelector('#customers-tab')) {
            return 'users';
        } else if (url.includes('option=createuser') || url.includes('createuser.php')) {
            return 'createuser';
        } else if (url.includes('option=settings') || url.includes('settings.php')) {
            return 'settings';
        } else if (url.includes('option=resources') || url.includes('resources.php')) {
            return 'resources';
        } else if (url.includes('option=system') || url.includes('system.php')) {
            return 'system';
        } else if (url.includes('option=domain-settings') || url.includes('domain-settings.php')) {
            return 'domain-settings';
        } else if (url.includes('option=domain-registrations') || url.includes('domain-registrations.php')) {
            return 'domain-registrations';
        } else if (url.includes('option=logs') || url.includes('logs.php')) {
            return 'logs';
        }
        
        return null;
    }
    
    // Lade die entsprechenden JavaScript-Dateien basierend auf der aktuellen Seite
    function loadPageSpecificScripts() {
        var currentPage = getCurrentPage();
        
        if (!currentPage) {
            console.log('Keine spezifische Seite erkannt, lade keine zusätzlichen Scripts');
            return;
        }
        
        console.log('Lade Scripts für Seite:', currentPage);
        
        // Lade die entsprechende JavaScript-Datei
        var scriptPath = 'assets/inc-js/' + currentPage + '.js';
        loadScript(scriptPath, function() {
            console.log('Script geladen:', scriptPath);
        });
    }
    
    // Warte bis das DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadPageSpecificScripts);
    } else {
        loadPageSpecificScripts();
    }
    
    // Exportiere Funktionen für externe Verwendung
    window.IncJsLoader = {
        loadScript: loadScript,
        loadCSS: loadCSS,
        getCurrentPage: getCurrentPage,
        loadPageSpecificScripts: loadPageSpecificScripts
    };
    
})();
