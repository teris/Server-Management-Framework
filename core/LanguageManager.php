<?php
/**
 * LanguageManager - Verwaltet mehrsprachige XML-Dateien für Module
 */

class LanguageManager {
    private static $instance = null;
    private $current_language;
    private $default_language = 'de';
    private $available_languages;
    private $translations = [];
    private $module_cache = [];
    
    private function __construct() {
        global $system_config;
        $this->current_language = $system_config['language'] ?? 'de';
        $this->available_languages = $system_config['available_languages'] ?? ['de'];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LanguageManager();
        }
        return self::$instance;
    }
    
    /**
     * Setzt die aktuelle Sprache
     */
    public function setLanguage($language) {
        if (in_array($language, $this->available_languages)) {
            $this->current_language = $language;
            // Cache leeren, da sich die Sprache geändert hat
            $this->translations = [];
            $this->module_cache = [];
            return true;
        }
        return false;
    }
    
    /**
     * Gibt die aktuelle Sprache zurück
     */
    public function getCurrentLanguage() {
        return $this->current_language;
    }
    
    /**
     * Gibt alle verfügbaren Sprachen zurück
     */
    public function getAvailableLanguages() {
        return $this->available_languages;
    }
    
    /**
     * Lädt die Übersetzungen für ein Modul
     */
    public function loadModuleTranslations($module_key) {
        // Prüfe Cache
        if (isset($this->module_cache[$module_key][$this->current_language])) {
            return $this->module_cache[$module_key][$this->current_language];
        }
        
        $translations = [];
        
        // Versuche zuerst die aktuelle Sprache zu laden
        $xml_file = $this->getLanguageFilePath($module_key, $this->current_language);
        
        if (file_exists($xml_file)) {
            $translations = $this->loadXMLTranslations($xml_file);
        } else {
            // Fallback auf Standardsprache (deutsch)
            $default_xml_file = $this->getLanguageFilePath($module_key, $this->default_language);
            if (file_exists($default_xml_file)) {
                $translations = $this->loadXMLTranslations($default_xml_file);
            }
        }
        
        // Cache speichern
        if (!isset($this->module_cache[$module_key])) {
            $this->module_cache[$module_key] = [];
        }
        $this->module_cache[$module_key][$this->current_language] = $translations;
        
        return $translations;
    }
    
    /**
     * Gibt den Pfad zur Sprachdatei zurück
     */
    private function getLanguageFilePath($module_key, $language) {
        // Spezialbehandlung für Core-Sprachdateien
        if ($module_key === 'core') {
            return dirname(__DIR__) . '/core/lang/' . $language . '.xml';
        }
        
        $module_config = getPluginConfig($module_key);
        if (!$module_config) {
            return null;
        }
        
        $module_path = $module_config['path'];
        return $module_path . '/lang/' . $language . '.xml';
    }
    
    /**
     * Lädt Übersetzungen aus einer XML-Datei
     */
    private function loadXMLTranslations($xml_file) {
        if (!file_exists($xml_file)) {
            return [];
        }
        
        try {
            $xml = simplexml_load_file($xml_file);
            if (!$xml) {
                error_log("Failed to load XML file: $xml_file");
                return [];
            }
            
            $translations = [];
            
            // Parse XML-Struktur
            foreach ($xml->children() as $element) {
                $key = (string)$element->getName();
                $value = (string)$element;
                $translations[$key] = $value;
            }
            
            return $translations;
            
        } catch (Exception $e) {
            error_log("Error loading XML translations from $xml_file: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Übersetzt einen Schlüssel für ein Modul
     */
    public function translate($module_key, $key, $default = null) {
        $translations = $this->loadModuleTranslations($module_key);
        
        if (isset($translations[$key])) {
            return $translations[$key];
        }
        
        // Fallback auf Standardsprache
        if ($this->current_language !== $this->default_language) {
            $default_translations = $this->loadModuleTranslations($module_key);
            if (isset($default_translations[$key])) {
                return $default_translations[$key];
            }
        }
        
        // Fallback auf übergebenen Standardwert oder Schlüssel
        return $default !== null ? $default : $key;
    }
    
    /**
     * Übersetzt mehrere Schlüssel auf einmal
     */
    public function translateMultiple($module_key, $keys) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->translate($module_key, $key);
        }
        return $result;
    }
    
    /**
     * Übersetzt einen Schlüssel für Core-Dateien (Hauptdateien)
     */
    public function translateCore($key, $default = null) {
        return $this->translate('core', $key, $default);
    }
    
    /**
     * Übersetzt mehrere Schlüssel für Core-Dateien auf einmal
     */
    public function translateCoreMultiple($keys) {
        return $this->translateMultiple('core', $keys);
    }
    
    /**
     * Erstellt eine Sprachdatei für ein Modul
     */
    public function createLanguageFile($module_key, $language, $translations) {
        $xml_file = $this->getLanguageFilePath($module_key, $language);
        
        if (!$xml_file) {
            return false;
        }
        
        // Stelle sicher, dass das Verzeichnis existiert
        $dir = dirname($xml_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Erstelle XML-Struktur
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><translations></translations>');
        
        foreach ($translations as $key => $value) {
            $element = $xml->addChild($key, htmlspecialchars($value));
        }
        
        // Speichere XML-Datei
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return file_put_contents($xml_file, $dom->saveXML()) !== false;
    }
    
    /**
     * Prüft ob eine Sprachdatei für ein Modul existiert
     */
    public function hasLanguageFile($module_key, $language) {
        $xml_file = $this->getLanguageFilePath($module_key, $language);
        return $xml_file && file_exists($xml_file);
    }
    
    /**
     * Gibt alle verfügbaren Sprachdateien für ein Modul zurück
     */
    public function getAvailableLanguagesForModule($module_key) {
        $available = [];
        
        foreach ($this->available_languages as $language) {
            if ($this->hasLanguageFile($module_key, $language)) {
                $available[] = $language;
            }
        }
        
        return $available;
    }
    
    /**
     * Cache leeren
     */
    public function clearCache() {
        $this->translations = [];
        $this->module_cache = [];
    }
} 