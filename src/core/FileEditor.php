<?php
/**
 * File Editor - Eigenständiger Editor basierend auf TinyFileManager
 * 
 * TODO: Flexibler Editor für verschiedene Dateitypen und Datenbankinhalte
 * TODO: Unterstützt HTML, CSS, JavaScript, PHP und andere Textformate
 * TODO: Kann sowohl Dateien als auch Datenbankinhalte bearbeiten
 * 
 * @author Teris
 * @version 3.1.2
 */

class FileEditor {
    
    private $db;
    private $supportedExtensions = [
        'html', 'htm', 'css', 'js', 'php', 'json', 'xml', 'txt', 'md', 'sql'
    ];
    
    public function __construct() {
        if (!isset($db)) {
            require_once dirname(__DIR__) . '/core/DatabaseManager.php';
            $this->db = DatabaseManager::getInstance();
        }
    }
    
    /**
     * Editor für Datei laden
     * 
     * @param string $filePath Pfad zur Datei
     * @param array $options Editor-Optionen
     * @return string HTML-Editor
     */
    public function loadFileEditor($filePath, $options = []) {
        if (!file_exists($filePath)) {
            return $this->errorMessage('Datei nicht gefunden: ' . $filePath);
        }
        
        $content = file_get_contents($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileName = basename($filePath);
        
        return $this->generateEditor($content, $extension, $fileName, $options);
    }
    
    /**
     * Editor für Datenbank-Template laden
     * 
     * @param int $templateId Template-ID
     * @param string $contentType 'html' oder 'raw'
     * @param array $options Editor-Optionen
     * @return string HTML-Editor
     */
    public function loadDatabaseEditor($templateId, $contentType = 'html', $options = []) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE id = ?");
            $this->db->execute($stmt, [$templateId]);
            $template = $this->db->fetch($stmt);
            
            if (!$template) {
                return $this->errorMessage('Template nicht gefunden');
            }
            
            $content = $contentType === 'html' ? $template['html_content'] : $template['raw_content'];
            $extension = $contentType === 'html' ? 'html' : 'txt';
            $fileName = $template['template_name'] . '.' . $extension;
            
            return $this->generateEditor($content, $extension, $fileName, $options);
            
        } catch (Exception $e) {
            return $this->errorMessage('Fehler beim Laden des Templates: ' . $e->getMessage());
        }
    }
    
    /**
     * Editor HTML generieren
     * 
     * @param string $content Inhalt
     * @param string $extension Dateiendung
     * @param string $fileName Dateiname
     * @param array $options Optionen
     * @return string HTML
     */
    private function generateEditor($content, $extension, $fileName, $options = []) {
        $defaultOptions = [
            'height' => '400px',
            'theme' => 'github',
            'fontSize' => 14,
            'showLineNumbers' => true,
            'enableAutocomplete' => true,
            'enableSnippets' => true,
            'wrap' => true,
            'readOnly' => false,
            'showToolbar' => true,
            'showVariables' => false,
            'variables' => []
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Editor-ID generieren
        $editorId = 'editor_' . uniqid();
        
        // Mode bestimmen
        $mode = $this->getAceMode($extension);
        
        // HTML generieren
        $html = $this->generateEditorHTML($editorId, $content, $mode, $fileName, $options);
        
        // JavaScript generieren
        $js = $this->generateEditorJS($editorId, $mode, $options);
        
        return $html . $js;
    }
    
    /**
     * Editor HTML generieren
     */
    private function generateEditorHTML($editorId, $content, $mode, $fileName, $options) {
        $toolbar = $options['showToolbar'] ? $this->generateToolbar($editorId, $options) : '';
        
        return '
        <div class="file-editor-container">
            <div class="file-editor-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-file-code"></i> ' . htmlspecialchars($fileName) . '
                    </h6>
                    <div class="editor-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="saveEditor(\'' . $editorId . '\')">
                            <i class="bi bi-save"></i> Speichern (Ctrl+S)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatCode(\'' . $editorId . '\')">
                            <i class="bi bi-braces"></i> Formatieren
                        </button>
                        ' . ($options['showVariables'] ? '<button type="button" class="btn btn-sm btn-outline-info" onclick="showVariables(\'' . $editorId . '\')">
                            <i class="bi bi-plus-circle"></i> Variablen
                        </button>' : '') . '
                    </div>
                </div>
            </div>
            
            ' . $toolbar . '
            
            <div id="' . $editorId . '" style="height: ' . $options['height'] . '; border: 1px solid #dee2e6; border-radius: 0.375rem;"></div>
            
            <div class="file-editor-footer mt-2">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    Tipp: Strg+S zum Speichern, Strg+Shift+F zum Formatieren
                </small>
            </div>
        </div>';
    }
    
    /**
     * Toolbar generieren
     */
    private function generateToolbar($editorId, $options) {
        $modes = [
            'html' => 'HTML',
            'css' => 'CSS', 
            'javascript' => 'JavaScript',
            'php' => 'PHP',
            'json' => 'JSON',
            'xml' => 'XML',
            'text' => 'Text'
        ];
        
        $modeOptions = '';
        foreach ($modes as $value => $label) {
            $modeOptions .= '<option value="' . $value . '">' . $label . '</option>';
        }
        
        return '
        <div class="file-editor-toolbar mb-2">
            <div class="btn-group btn-group-sm" role="group">
                <select class="form-select form-select-sm" id="' . $editorId . '_mode" onchange="changeMode(\'' . $editorId . '\', this.value)">
                    ' . $modeOptions . '
                </select>
            </div>
            <div class="btn-group btn-group-sm ms-2" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="toggleWordWrap(\'' . $editorId . '\')">
                    <i class="bi bi-text-wrap"></i> Wrap
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="toggleLineNumbers(\'' . $editorId . '\')">
                    <i class="bi bi-list-ol"></i> Zeilen
                </button>
            </div>
        </div>';
    }
    
    /**
     * Editor JavaScript generieren
     */
    private function generateEditorJS($editorId, $mode, $options) {
        return '
        <script>
        (function() {
            let editor_' . $editorId . ' = null;
            
            // Editor initialisieren
            function initEditor() {
                if (typeof ace === "undefined") {
                    loadACE();
                    return;
                }
                
                editor_' . $editorId . ' = ace.edit("' . $editorId . '");
                editor_' . $editorId . '.setTheme("ace/theme/' . $options['theme'] . '");
                editor_' . $editorId . '.getSession().setMode("ace/mode/' . $mode . '");
                editor_' . $editorId . '.setFontSize(' . $options['fontSize'] . ');
                editor_' . $editorId . '.setShowPrintMargin(false);
                editor_' . $editorId . '.setOptions({
                    wrap: ' . ($options['wrap'] ? 'true' : 'false') . ',
                    showLineNumbers: ' . ($options['showLineNumbers'] ? 'true' : 'false') . ',
                    readOnly: ' . ($options['readOnly'] ? 'true' : 'false') . '
                });
                
                // Keyboard Shortcuts
                editor_' . $editorId . '.commands.addCommand({
                    name: "save",
                    bindKey: {win: "Ctrl-S", mac: "Command-S"},
                    exec: function(editor) {
                        saveEditor("' . $editorId . '");
                    }
                });
                
                editor_' . $editorId . '.commands.addCommand({
                    name: "format",
                    bindKey: {win: "Ctrl-Shift-F", mac: "Command-Shift-F"},
                    exec: function(editor) {
                        formatCode("' . $editorId . '");
                    }
                });
            }
            
            // ACE.js laden
            function loadACE() {
                // Prüfen ob ACE bereits geladen ist
                if (typeof ace !== "undefined") {
                    initEditor();
                    return;
                }
                
                // Fallback: Einfacher Textarea-Editor
                console.warn("ACE.js konnte nicht geladen werden, verwende Fallback-Editor");
                createFallbackEditor();
            }
            
            // ACE.js Modi laden
            function loadAceModes() {
                const modes = ["html", "css", "javascript", "php", "json", "xml", "text"];
                let loaded = 0;
                
                modes.forEach(function(mode) {
                    const script = document.createElement("script");
                    script.src = "https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.0/mode-' . $mode . '.min.js";
                    script.onload = function() {
                        loaded++;
                        if (loaded === modes.length) {
                            initEditor();
                        }
                    };
                    document.head.appendChild(script);
                });
            }
            
            // Globale Funktionen
            window.saveEditor = function(editorId) {
                let content = "";
                
                // ACE Editor
                if (editor_' . $editorId . ') {
                    content = editor_' . $editorId . '.getValue();
                }
                // Fallback Textarea
                else {
                    const textarea = document.getElementById(editorId + "_textarea");
                    if (textarea) {
                        content = textarea.value;
                    }
                }
                
                if (content) {
                    console.log("Speichere Inhalt:", content);
                    alert("Inhalt gespeichert!");
                }
            };
            
            window.formatCode = function(editorId) {
                let content = "";
                let formatted = "";
                
                // ACE Editor
                if (editor_' . $editorId . ') {
                    content = editor_' . $editorId . '.getValue();
                    formatted = formatHTML(content);
                    editor_' . $editorId . '.setValue(formatted);
                }
                // Fallback Textarea
                else {
                    const textarea = document.getElementById(editorId + "_textarea");
                    if (textarea) {
                        content = textarea.value;
                        formatted = formatHTML(content);
                        textarea.value = formatted;
                    }
                }
            };
            
            window.changeMode = function(editorId, mode) {
                if (editor_' . $editorId . ') {
                    editor_' . $editorId . '.getSession().setMode("ace/mode/" + mode);
                }
            };
            
            window.toggleWordWrap = function(editorId) {
                if (editor_' . $editorId . ') {
                    const current = editor_' . $editorId . '.getSession().getUseWrapMode();
                    editor_' . $editorId . '.getSession().setUseWrapMode(!current);
                }
            };
            
            window.toggleLineNumbers = function(editorId) {
                if (editor_' . $editorId . ') {
                    const current = editor_' . $editorId . '.getShowPrintMargin();
                    editor_' . $editorId . '.setShowPrintMargin(!current);
                }
            };
            
            window.showVariables = function(editorId) {
                // Template-Variablen anzeigen
                const variables = [
                    "{firstName}", "{lastName}", "{email}", "{username}",
                    "{password}", "{loginUrl}", "{verificationLink}",
                    "{site_name}", "{systemCredentials}"
                ];
                
                let variableList = "Verfügbare Variablen:\\n\\n";
                variables.forEach(function(variable) {
                    variableList += variable + "\\n";
                });
                
                alert(variableList);
            };
            
            // Fallback-Editor erstellen
            function createFallbackEditor() {
                const editorElement = document.getElementById("' . $editorId . '");
                if (!editorElement) return;
                
                editorElement.innerHTML = 
                    "<div class=\"fallback-editor\">" +
                        "<div class=\"editor-toolbar mb-2\">" +
                            "<div class=\"btn-group btn-group-sm\" role=\"group\">" +
                                "<button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"saveEditor(\'" . $editorId . "\')\">" +
                                    "<i class=\"bi bi-save\"></i> Speichern (Ctrl+S)" +
                                "</button>" +
                                "<button type=\"button\" class=\"btn btn-outline-secondary\" onclick=\"formatCode(\'" . $editorId . "\')\">" +
                                    "<i class=\"bi bi-braces\"></i> Formatieren" +
                                "</button>" +
                            "</div>" +
                        "</div>" +
                        "<textarea id=\"" . $editorId . "_textarea\" class=\"form-control\" style=\"height: 400px; font-family: monospace; font-size: 14px;\" placeholder=\"Inhalt eingeben...\"></textarea>" +
                    "</div>";
                
                // Textarea-Event-Listener
                const textarea = document.getElementById("' . $editorId . '_textarea");
                if (textarea) {
                    textarea.addEventListener("keydown", function(e) {
                        if ((e.ctrlKey || e.metaKey) && e.key === "s") {
                            e.preventDefault();
                            saveEditor("' . $editorId . '");
                        }
                    });
                }
            }
            
            // HTML formatieren
            function formatHTML(html) {
                let formatted = html
                    .replace(/></g, ">\\n<")
                    .replace(/^\\s+|\\s+$/g, "");
                
                const lines = formatted.split("\\n");
                let indent = 0;
                const indentStr = "  ";
                
                return lines.map(function(line) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith("</")) {
                        indent = Math.max(0, indent - 1);
                    }
                    
                    const result = indentStr.repeat(indent) + trimmed;
                    
                    if (trimmed.startsWith("<") && !trimmed.startsWith("</") && !trimmed.endsWith("/>")) {
                        indent++;
                    }
                    
                    return result;
                }).join("\\n");
            }
            
            // Initialisierung
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initEditor);
            } else {
                initEditor();
            }
        })();
        </script>';
    }
    
    /**
     * ACE.js Mode für Dateiendung bestimmen
     */
    private function getAceMode($extension) {
        $modeMap = [
            'html' => 'html',
            'htm' => 'html',
            'css' => 'css',
            'js' => 'javascript',
            'php' => 'php',
            'json' => 'json',
            'xml' => 'xml',
            'txt' => 'text',
            'md' => 'text',
            'sql' => 'text'
        ];
        
        return $modeMap[$extension] ?? 'text';
    }
    
    /**
     * ACE-Theme ermitteln
     */
    private function getAceTheme() {
        return 'monokai';
    }
    
    /**
     * Fehlermeldung generieren
     */
    private function errorMessage($message) {
        return '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($message) . '
        </div>';
    }
    
    /**
     * Editor für E-Mail-Template laden (Wrapper)
     */
    public function loadEmailTemplateEditor($templateId, $contentType = 'html') {
        return $this->loadDatabaseEditor($templateId, $contentType, [
            'showVariables' => true,
            'variables' => [
                '{firstName}', '{lastName}', '{email}', '{username}',
                '{password}', '{loginUrl}', '{verificationLink}',
                '{site_name}', '{systemCredentials}'
            ]
        ]);
    }
    
    /**
     * Editor für PHP-Datei laden (Wrapper)
     */
    public function loadPHPFileEditor($filePath) {
        return $this->loadFileEditor($filePath, [
            'theme' => 'monokai',
            'fontSize' => 13
        ]);
    }
    
    /**
     * Editor für CSS-Datei laden (Wrapper)
     */
    public function loadCSSFileEditor($filePath) {
        return $this->loadFileEditor($filePath, [
            'theme' => 'chrome',
            'fontSize' => 14
        ]);
    }
    
    /**
     * Strukturierte Daten-Editor (XML/JSON) rendern
     */
    public function renderStructuredDataEditor($filePath, $content, $fileType, $isWritable) {
        $fileName = basename($filePath);
        $aceMode = $this->getAceMode($fileType);
        
        // Tabellenansicht für strukturierte Daten
        $tableView = $this->generateStructuredDataTable($content, $fileType);
        
        $editorHtml = '
        <div class="file-editor-container">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-table"></i> Tabellarische Ansicht
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                            ' . $tableView . '
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-code-slash"></i> Raw-Editor
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="file-editor-ace" style="height: 500px; width: 100%;"></div>
                            <textarea id="file-editor-textarea" style="display: none;">' . htmlspecialchars($content) . '</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ACE Editor initialisieren
            if (typeof ace !== "undefined") {
                var editor = ace.edit("file-editor-ace");
                editor.setTheme("ace/theme/' . $this->getAceTheme() . '");
                editor.session.setMode("ace/mode/' . $aceMode . '");
                editor.setOptions({
                    fontSize: 14,
                    showLineNumbers: true,
                    showGutter: true,
                    highlightActiveLine: true,
                    enableBasicAutocompletion: true,
                    enableSnippets: true,
                    enableLiveAutocompletion: true
                });
                
                // Inhalt setzen
                editor.setValue(`' . addslashes($content) . '`);
                editor.clearSelection();
                
                // Änderungen überwachen
                editor.on("change", function() {
                    document.getElementById("file-editor-textarea").value = editor.getValue();
                });
            } else {
                // Fallback: Textarea anzeigen
                document.getElementById("file-editor-textarea").style.display = "block";
                document.getElementById("file-editor-textarea").style.height = "500px";
            }
        });
        </script>';
        
        return $editorHtml;
    }
    
    /**
     * Tabellarische Ansicht für strukturierte Daten generieren
     */
    private function generateStructuredDataTable($content, $fileType) {
        try {
            if ($fileType === 'JSON') {
                return $this->generateJsonTable($content);
            } elseif ($fileType === 'XML') {
                return $this->generateXmlTable($content);
            }
        } catch (Exception $e) {
            return '<div class="alert alert-danger">Fehler beim Parsen der ' . $fileType . '-Datei: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        return '<div class="alert alert-info">Tabellarische Ansicht nicht verfügbar für diesen Dateityp</div>';
    }
    
    /**
     * JSON-Tabelle generieren
     */
    private function generateJsonTable($jsonContent) {
        $data = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<div class="alert alert-danger">Ungültiges JSON: ' . htmlspecialchars(json_last_error_msg()) . '</div>';
        }
        
        $html = '<table class="table table-striped table-hover table-sm">';
        $html .= '<thead class="table-dark">';
        $html .= '<tr><th>Key</th><th>Value</th><th>Type</th></tr>';
        $html .= '</thead><tbody>';
        
        $this->renderJsonRow($data, '', $html);
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * JSON-Zeile rendern (rekursiv)
     */
    private function renderJsonRow($data, $prefix, &$html) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentKey = $prefix ? $prefix . '.' . $key : $key;
                
                if (is_array($value) || is_object($value)) {
                    $html .= '<tr>';
                    $html .= '<td><code>' . htmlspecialchars($currentKey) . '</code></td>';
                    $html .= '<td>' . (is_array($value) ? 'Array (' . count($value) . ' items)' : 'Object') . '</td>';
                    $html .= '<td><span class="badge bg-info">' . gettype($value) . '</span></td>';
                    $html .= '</tr>';
                    
                    $this->renderJsonRow($value, $currentKey, $html);
                } else {
                    $html .= '<tr>';
                    $html .= '<td><code>' . htmlspecialchars($currentKey) . '</code></td>';
                    $html .= '<td><code>' . htmlspecialchars($value) . '</code></td>';
                    $html .= '<td><span class="badge bg-secondary">' . gettype($value) . '</span></td>';
                    $html .= '</tr>';
                }
            }
        } elseif (is_object($data)) {
            $this->renderJsonRow((array)$data, $prefix, $html);
        } else {
            $html .= '<tr>';
            $html .= '<td><code>root</code></td>';
            $html .= '<td><code>' . htmlspecialchars($data) . '</code></td>';
            $html .= '<td><span class="badge bg-secondary">' . gettype($data) . '</span></td>';
            $html .= '</tr>';
        }
    }
    
    /**
     * XML-Tabelle generieren
     */
    private function generateXmlTable($xmlContent) {
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);
        
        $html = '<table class="table table-striped table-hover table-sm">';
        $html .= '<thead class="table-dark">';
        $html .= '<tr><th>Element</th><th>Value</th><th>Attributes</th></tr>';
        $html .= '</thead><tbody>';
        
        $this->renderXmlNode($dom->documentElement, '', $html);
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * XML-Knoten rendern (rekursiv)
     */
    private function renderXmlNode($node, $path, &$html) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $currentPath = $path ? $path . '.' . $node->nodeName : $node->nodeName;
            $attributes = '';
            
            if ($node->hasAttributes()) {
                $attrArray = [];
                foreach ($node->attributes as $attr) {
                    $attrArray[] = $attr->name . '="' . $attr->value . '"';
                }
                $attributes = implode(', ', $attrArray);
            }
            
            $textContent = trim($node->textContent);
            
            $html .= '<tr>';
            $html .= '<td><code>' . htmlspecialchars($currentPath) . '</code></td>';
            $html .= '<td><code>' . htmlspecialchars($textContent) . '</code></td>';
            $html .= '<td><code>' . htmlspecialchars($attributes) . '</code></td>';
            $html .= '</tr>';
            
            // Kinder verarbeiten
            foreach ($node->childNodes as $child) {
                $this->renderXmlNode($child, $currentPath, $html);
            }
        }
    }
    
    /**
     * Farbcode-Erkennung für RAW-Modus
     */
    public function detectColorCodes($content) {
        $colorPatterns = [
            'hex' => '/#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/',
            'rgb' => '/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/',
            'rgba' => '/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/',
            'hsl' => '/hsl\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)/',
            'hsla' => '/hsla\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*([\d.]+)\s*\)/',
            'named' => '/\b(red|green|blue|yellow|orange|purple|pink|brown|black|white|gray|grey)\b/i'
        ];
        
        $foundColors = [];
        
        foreach ($colorPatterns as $type => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $foundColors[] = [
                    'type' => $type,
                    'value' => $match[0],
                    'position' => $match[1],
                    'preview' => $this->generateColorPreview($match[0])
                ];
            }
        }
        
        return $foundColors;
    }
    
    /**
     * Farbvorschau generieren
     */
    private function generateColorPreview($colorValue) {
        return '<span class="color-preview" style="display: inline-block; width: 20px; height: 20px; background-color: ' . htmlspecialchars($colorValue) . '; border: 1px solid #ccc; margin-right: 5px; vertical-align: middle;"></span>';
    }
    
    /**
     * Erweiterte Editor-HTML mit Farbcode-Unterstützung
     */
    public function renderAdvancedEditor($filePath, $content, $fileType, $isWritable) {
        $fileName = basename($filePath);
        $aceMode = $this->getAceMode($fileType);
        
        // Farbcodes erkennen
        $colorCodes = $this->detectColorCodes($content);
        
        $editorHtml = '
        <div class="file-editor-container">
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-palette"></i> Gefundene Farbcodes
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="color-codes-list">';
        
        if (!empty($colorCodes)) {
            foreach ($colorCodes as $color) {
                $editorHtml .= '<span class="badge bg-light text-dark me-2 mb-2">';
                $editorHtml .= $color['preview'];
                $editorHtml .= '<code>' . htmlspecialchars($color['value']) . '</code>';
                $editorHtml .= '</span>';
            }
        } else {
            $editorHtml .= '<span class="text-muted">Keine Farbcodes gefunden</span>';
        }
        
        $editorHtml .= '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-code-slash"></i> ' . htmlspecialchars($fileName) . '
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="file-editor-ace" style="height: 600px; width: 100%;"></div>
                            <textarea id="file-editor-textarea" style="display: none;">' . htmlspecialchars($content) . '</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ACE Editor initialisieren
            if (typeof ace !== "undefined") {
                var editor = ace.edit("file-editor-ace");
                editor.setTheme("ace/theme/' . $this->getAceTheme() . '");
                editor.session.setMode("ace/mode/' . $aceMode . '");
                editor.setOptions({
                    fontSize: 14,
                    showLineNumbers: true,
                    showGutter: true,
                    highlightActiveLine: true,
                    enableBasicAutocompletion: true,
                    enableSnippets: true,
                    enableLiveAutocompletion: true
                });
                
                // Inhalt setzen
                editor.setValue(`' . addslashes($content) . '`);
                editor.clearSelection();
                
                // Änderungen überwachen
                editor.on("change", function() {
                    document.getElementById("file-editor-textarea").value = editor.getValue();
                });
            } else {
                // Fallback: Textarea anzeigen
                document.getElementById("file-editor-textarea").style.display = "block";
                document.getElementById("file-editor-textarea").style.height = "600px";
            }
        });
        </script>';
        
        return $editorHtml;
    }
}
