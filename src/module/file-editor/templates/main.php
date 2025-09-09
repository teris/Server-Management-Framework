<?php
/**
 * File Editor Module - Main Template
 */

// Modul-Instanz
$module = new FileEditorModule();
?>

<div class="file-editor-module">
    <!-- Header -->
    <div class="module-header">
        <h2><i class="bi bi-file-earmark-code"></i> Datei Editor</h2>
        <p class="text-muted">Erweiterter Datei-Editor mit automatischer Dateityp-Erkennung</p>
    </div>

    <!-- Navigation -->
    <div class="row mb-3">
        <div class="col-12">
            <nav aria-label="breadcrumb" id="file-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#" data-directory=".">
                            <i class="bi bi-house"></i> Root
                        </a>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- File List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-folder"></i> <span id="current-directory">.</span>
                    </h5>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" id="refresh-btn">
                            <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Typ</th>
                                    <th>Größe</th>
                                    <th>Geändert</th>
                                    <th>Schreibrecht</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="files-list">
                                <!-- Files werden hier dynamisch geladen -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- File Editor Modal -->
    <div class="modal fade" id="file-editor-modal" tabindex="-1" aria-labelledby="file-editor-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="file-editor-modal-label">
                        <i class="bi bi-file-earmark-code"></i> <span id="modal-file-name"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Schreibberechtigung-Info -->
                    <div id="write-permission-info" class="alert alert-warning mb-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span id="permission-message"></span>
                    </div>
                    
                    <!-- Editor Content -->
                    <div id="editor-content">
                        <!-- Editor wird hier dynamisch geladen -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> <?= t('close') ?>
                    </button>
                    <button type="button" class="btn btn-success" id="save-file-btn">
                        <i class="bi bi-save"></i> <?= t('save_file') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="delete-confirmation-modal" tabindex="-1" aria-labelledby="delete-confirmation-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="delete-confirmation-modal-label">
                        <i class="bi bi-exclamation-triangle text-warning"></i> <?= t('confirm_delete') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('confirm_delete_message') ?></p>
                    <p class="text-danger"><strong><?= t('changes_immediate') ?></strong></p>
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle"></i>
                        <strong><?= t('no_undo') ?></strong> <?= t('no_undo_message') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> <?= t('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-btn">
                        <i class="bi bi-trash"></i> <?= t('delete_file') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Globale Variablen
let currentDirectory = '.';
let currentFile = null;
let fileToDelete = null;

// Dateiliste laden
function loadFileList(directory = '.') {
    currentDirectory = directory;
    document.getElementById('current-directory').textContent = directory;
    
    // AJAX-Request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plugin=file-editor&action=get_files_list&directory=' + encodeURIComponent(directory)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderFileList(data.data.files);
            updateBreadcrumb(directory);
        } else {
            showError(data.message || 'Fehler beim Laden der Dateiliste');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showError('Fehler beim Laden der Dateiliste');
    });
}

// Dateiliste rendern
function renderFileList(files) {
    const tbody = document.getElementById('files-list');
    
    if (files.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Keine Dateien gefunden</td></tr>';
        return;
    }
    
    tbody.innerHTML = files.map(file => {
        const icon = file.is_dir ? 'bi-folder' : 'bi-file-earmark';
        const size = file.is_dir ? '-' : formatFileSize(file.size);
        const modified = new Date(file.modified * 1000).toLocaleString('de-DE');
        const writableIcon = file.is_writable ? 'bi-check-circle text-success' : 'bi-x-circle text-danger';
        
        let html = '<tr>';
        html += '<td><i class="bi ' + icon + '"></i> ' + escapeHtml(file.name) + '</td>';
        html += '<td>' + escapeHtml(file.type) + '</td>';
        html += '<td>' + size + '</td>';
        html += '<td>' + modified + '</td>';
        html += '<td><i class="bi ' + writableIcon + '"></i></td>';
        html += '<td>';
        
        if (file.is_dir) {
            html += '<button class="btn btn-sm btn-outline-primary" data-action="loadDirectory" data-path="' + escapeHtml(file.path) + '">';
            html += '<i class="bi bi-folder-open"></i> Öffnen';
            html += '</button>';
        } else {
            html += '<button class="btn btn-sm btn-outline-primary" data-action="editFile" data-path="' + escapeHtml(file.path) + '">';
            html += '<i class="bi bi-pencil"></i> Bearbeiten';
            html += '</button>';
            html += '<button class="btn btn-sm btn-outline-danger" data-action="deleteFile" data-path="' + escapeHtml(file.path) + '">';
            html += '<i class="bi bi-trash"></i> Löschen';
            html += '</button>';
        }
        
        html += '</td>';
        html += '</tr>';
        
        return html;
    }).join('');
    
    // Event-Listener für Action-Buttons hinzufügen
    tbody.querySelectorAll('button[data-action]').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const path = this.getAttribute('data-path');
            
            switch(action) {
                case 'loadDirectory':
                    loadDirectory(path);
                    break;
                case 'editFile':
                    editFile(path);
                    break;
                case 'deleteFile':
                    deleteFile(path);
                    break;
            }
        });
    });
}

// Verzeichnis laden
function loadDirectory(directory) {
    loadFileList(directory);
}

// Datei bearbeiten
function editFile(filePath) {
    currentFile = filePath;
    document.getElementById('modal-file-name').textContent = filePath.split('/').pop();
    
    // Datei-Inhalt laden
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plugin=file-editor&action=get_file_content&file_path=' + encodeURIComponent(filePath)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Debug-Log
            console.log('Loading file content:', {
                file: filePath,
                type: data.data.file_type,
                contentLength: data.data.content.length,
                rawContent: data.data.raw_content
            });
            
            renderEditor(data.data, filePath);
            showWritePermissionInfo(data.data.is_writable, filePath);
        } else {
            showError(data.message || 'Fehler beim Laden der Datei');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showError('Fehler beim Laden der Datei');
    });
    
    // Modal anzeigen
    const modal = new bootstrap.Modal(document.getElementById('file-editor-modal'));
    modal.show();
}

// Editor rendern
function renderEditor(data, filePath) {
    const editorContent = document.getElementById('editor-content');
    
    // Sicherstellen, dass Inhalt als reiner Text behandelt wird
    console.log('Rendering editor for file type:', data.file_type);
    
    if (data.file_type === 'XML') {
        // XML als Tabelle anzeigen
        editorContent.innerHTML = renderXMLTableEditor(filePath, data.content, data.is_writable);
    } else if (data.file_type === 'JSON') {
        // Strukturierter Editor für JSON
        editorContent.innerHTML = renderStructuredDataEditor(filePath, data.content, data.file_type, data.is_writable);
    } else {
        // Standard-Editor mit Farbcode-Erkennung - Inhalt als reiner Text
        editorContent.innerHTML = renderAdvancedEditor(filePath, data.content, data.file_type, data.is_writable);
    }
}

// XML-Tabellen-Editor rendern
function renderXMLTableEditor(filePath, content, isWritable) {
    try {
        // XML parsen
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(content, 'text/xml');
        
        // Prüfen auf XML-Fehler
        const parseError = xmlDoc.getElementsByTagName('parsererror');
        if (parseError.length > 0) {
            return '<div class="alert alert-danger">XML-Parsing-Fehler: ' + parseError[0].textContent + '</div>';
        }
        
        // XML-Daten extrahieren
        const translations = xmlDoc.getElementsByTagName('translations')[0];
        if (!translations) {
            return '<div class="alert alert-warning">Keine &lt;translations&gt; Sektion gefunden</div>';
        }
        
        const children = translations.children;
        const translationData = [];
        
        for (let i = 0; i < children.length; i++) {
            const child = children[i];
            if (child.nodeType === 1) { // Element node
                // Kommentar vor dem Element suchen
                let comment = '';
                let prevSibling = child.previousSibling;
                while (prevSibling) {
                    if (prevSibling.nodeType === 8) { // Comment node
                        comment = prevSibling.textContent.replace(/<!--\s*|\s*-->/g, '').trim();
                        break;
                    }
                    prevSibling = prevSibling.previousSibling;
                }
                
                translationData.push({
                    key: child.tagName,
                    value: child.textContent || '',
                    comment: comment
                });
            }
        }
        
        // HTML für Tabelle generieren
        let html = '<div class="xml-table-editor">';
        html += '<div class="row mb-3">';
        html += '<div class="col-12">';
        html += '<div class="card">';
        html += '<div class="card-header">';
        html += '<h6 class="mb-0">';
        html += '<i class="bi bi-table"></i> XML-Übersetzungen bearbeiten';
        html += '</h6>';
        html += '</div>';
        html += '<div class="card-body">';
        
        if (translationData.length === 0) {
            html += '<div class="alert alert-info">Keine Übersetzungen gefunden</div>';
        } else {
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped">';
            html += '<thead>';
            html += '<tr>';
            html += '<th style="width: 30%;">Schlüssel</th>';
            html += '<th style="width: 50%;">Wert</th>';
            html += '<th style="width: 20%;">Kommentar</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody id="xml-translations-table">';
            
            translationData.forEach((item, index) => {
                html += '<tr>';
                html += '<td><code>' + escapeHtml(item.key) + '</code></td>';
                html += '<td>';
                html += '<input type="text" class="form-control form-control-sm" ';
                html += 'data-key="' + escapeHtml(item.key) + '" ';
                html += 'value="' + escapeHtml(item.value) + '" ';
                html += 'placeholder="Übersetzung eingeben..."';
                if (!isWritable) {
                    html += ' readonly';
                }
                html += '>';
                html += '</td>';
                html += '<td>';
                html += '<small class="text-muted">' + escapeHtml(item.comment) + '</small>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        // Versteckte Textarea für den ursprünglichen Inhalt
        html += '<textarea id="file-editor-textarea" style="display: none;">' + escapeHtml(content) + '</textarea>';
        
        html += '</div>';
        
        return html;
        
    } catch (error) {
        console.error('XML-Parsing-Fehler:', error);
        return '<div class="alert alert-danger">Fehler beim Parsen der XML-Datei: ' + error.message + '</div>';
    }
}

// Strukturierter Daten-Editor rendern
function renderStructuredDataEditor(filePath, content, fileType, isWritable) {
    // Hier würde der strukturierte Editor gerendert werden
    // Für jetzt verwenden wir den Standard-Editor
    return renderAdvancedEditor(filePath, content, fileType, isWritable);
}

// Erweiterte Editor mit Farbcode-Erkennung rendern
function renderAdvancedEditor(filePath, content, fileType, isWritable) {
    const aceMode = getAceMode(fileType);
    const colorCodes = detectColorCodes(content);
    
    let html = '<div class="file-editor-container">';
    html += '<div class="row mb-3">';
    html += '<div class="col-12">';
    html += '<div class="card">';
    html += '<div class="card-header">';
    html += '<h6 class="mb-0">';
    html += '<i class="bi bi-palette"></i> Farbcodes';
    html += '</h6>';
    html += '</div>';
    html += '<div class="card-body">';
    html += '<div class="color-codes-list">';
    
    if (colorCodes.length > 0) {
        colorCodes.forEach(color => {
            html += '<span class="badge bg-light text-dark me-2 mb-2">';
            html += '<span class="color-preview" style="display: inline-block; width: 20px; height: 20px; background-color: ' + escapeHtml(color.value) + '; border: 1px solid #ccc; margin-right: 5px; vertical-align: middle;"></span>';
            html += '<code>' + escapeHtml(color.value) + '</code>';
            html += '</span>';
        });
    } else {
        html += '<span class="text-muted">Keine Farbcodes gefunden</span>';
    }
    
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '<div class="row">';
    html += '<div class="col-12">';
    html += '<div class="card">';
    html += '<div class="card-header">';
    html += '<h6 class="mb-0">';
    html += '<i class="bi bi-code-slash"></i> ' + escapeHtml(filePath.split('/').pop());
    html += '</h6>';
    html += '</div>';
    html += '<div class="card-body">';
    html += '<div id="file-editor-ace" style="height: 600px; width: 100%; border: 1px solid #ccc; display: none;"></div>';
    html += '<textarea id="file-editor-textarea" style="width: 100%; height: 600px; font-family: monospace; font-size: 14px; border: 1px solid #ccc; padding: 10px; resize: vertical;">' + escapeHtml(content) + '</textarea>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    
    // JavaScript separat ausführen - ohne Inhalt im Script
    html += '<script>';
    html += 'function initEditor() {';
    html += 'try {';
    html += 'var aceDiv = document.getElementById("file-editor-ace");';
    html += 'var textarea = document.getElementById("file-editor-textarea");';
    html += 'if (aceDiv && textarea && typeof ace !== "undefined" && ace.edit) {';
    html += 'var editor = ace.edit("file-editor-ace");';
    html += 'editor.setTheme("ace/theme/monokai");';
    html += 'editor.session.setMode("ace/mode/' + aceMode + '");';
    html += 'editor.setOptions({';
    html += 'fontSize: 14,';
    html += 'showLineNumbers: true,';
    html += 'showGutter: true,';
    html += 'highlightActiveLine: true,';
    html += 'enableBasicAutocompletion: true,';
    html += 'enableSnippets: true,';
    html += 'enableLiveAutocompletion: true,';
    html += 'readOnly: false';
    html += '});';
    html += 'editor.setValue(textarea.value);'; // Inhalt aus Textarea laden
    html += 'editor.session.setUseWrapMode(false);';
    html += 'editor.session.setUseSoftTabs(true);';
    html += 'editor.clearSelection();';
    html += 'editor.focus();';
    html += 'editor.on("change", function() {';
    html += 'textarea.value = editor.getValue();';
    html += '});';
    html += 'aceDiv.style.display = "block";';
    html += 'textarea.style.display = "none";';
    html += 'console.log("ACE Editor initialized successfully");';
    html += '} else {';
    html += 'console.log("ACE Editor not available, using textarea");';
    html += 'if (aceDiv) aceDiv.style.display = "none";';
    html += 'if (textarea) textarea.style.display = "block";';
    html += '}';
    html += '} catch (e) {';
    html += 'console.error("Error initializing ACE Editor:", e);';
    html += 'var aceDiv = document.getElementById("file-editor-ace");';
    html += 'var textarea = document.getElementById("file-editor-textarea");';
    html += 'if (aceDiv) aceDiv.style.display = "none";';
    html += 'if (textarea) textarea.style.display = "block";';
    html += '}';
    html += '}';
    html += 'setTimeout(initEditor, 500);';
    html += '<\/script>';
    
    return html;
}

// Schreibberechtigung-Info anzeigen
function showWritePermissionInfo(isWritable, filePath) {
    const infoDiv = document.getElementById('write-permission-info');
    const messageSpan = document.getElementById('permission-message');
    
    if (isWritable) {
        infoDiv.className = 'alert alert-warning mb-3';
        if (filePath.endsWith('.xml')) {
            messageSpan.innerHTML = '<strong>Achtung:</strong> Nach dem Speichern werden die XML-Änderungen sofort wirksam!';
        } else {
            messageSpan.innerHTML = '<strong>Achtung:</strong> Nach dem Speichern werden die Änderungen sofort wirksam!';
        }
    } else {
        infoDiv.className = 'alert alert-danger mb-3';
        messageSpan.innerHTML = '<strong>Schreibberechtigung fehlt:</strong> Datei manuell bearbeiten: <code>' + escapeHtml(filePath) + '</code>';
    }
    
    infoDiv.style.display = 'block';
}

// Datei speichern
function saveFile() {
    if (!currentFile) {
        showError('Keine Datei ausgewählt');
        return;
    }
    
    // Bestätigung vor dem Speichern
    if (!confirm('Datei speichern?')) {
        return;
    }
    
    // Inhalt aus Editor holen
    const content = getEditorContent();
    
    // Debug-Log
    console.log('Saving file:', currentFile);
    console.log('Content length:', content.length);
    
    // AJAX-Request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plugin=file-editor&action=save_file&file_path=' + encodeURIComponent(currentFile) + '&content=' + encodeURIComponent(content)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'Datei gespeichert');
            // Modal schließen
            const modal = bootstrap.Modal.getInstance(document.getElementById('file-editor-modal'));
            modal.hide();
        } else {
            showError(data.message || 'Fehler beim Speichern');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showError('Fehler beim Speichern der Datei');
    });
}

// Datei löschen
function deleteFile(filePath) {
    fileToDelete = filePath;
    
    // Modal anzeigen
    const modal = new bootstrap.Modal(document.getElementById('delete-confirmation-modal'));
    modal.show();
}

// Löschen bestätigen
function confirmDelete() {
    if (!fileToDelete) {
        showError('Keine Datei zum Löschen ausgewählt');
        return;
    }
    
    // AJAX-Request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plugin=file-editor&action=delete_file&file_path=' + encodeURIComponent(fileToDelete)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'Datei gelöscht');
            // Dateiliste aktualisieren
            loadFileList(currentDirectory);
            // Modal schließen
            const modal = bootstrap.Modal.getInstance(document.getElementById('delete-confirmation-modal'));
            modal.hide();
        } else {
            showError(data.message || 'Fehler beim Löschen');
        }
    })
    .catch(error => {
        console.error('Fehler:', error);
        showError('Fehler beim Löschen der Datei');
    });
}

// Breadcrumb aktualisieren
function updateBreadcrumb(directory) {
    const breadcrumb = document.getElementById('file-breadcrumb');
    const pathParts = directory.split('/').filter(part => part);
    
    let html = '<ol class="breadcrumb">';
    html += '<li class="breadcrumb-item"><a href="#" data-directory="."><i class="bi bi-house"></i> Root</a></li>';
    
    let currentPath = '.';
    pathParts.forEach(part => {
        currentPath += '/' + part;
        html += '<li class="breadcrumb-item"><a href="#" data-directory="' + currentPath + '">' + escapeHtml(part) + '</a></li>';
    });
    
    html += '</ol>';
    breadcrumb.innerHTML = html;
    
    // Event-Listener für Breadcrumb-Links hinzufügen
    breadcrumb.querySelectorAll('a[data-directory]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            loadDirectory(this.getAttribute('data-directory'));
        });
    });
}

// Hilfsfunktionen
function refreshFileList() {
    loadFileList(currentDirectory);
}

function getEditorContent() {
    const textarea = document.getElementById('file-editor-textarea');
    const xmlTable = document.getElementById('xml-translations-table');
    const aceDiv = document.getElementById('file-editor-ace');
    
    // XML-Tabellen-Editor
    if (xmlTable) {
        return generateXMLFromTable();
    }
    // ACE Editor (nur wenn das Element existiert und ACE verfügbar ist)
    else if (aceDiv && window.ace && aceDiv.style.display !== 'none') {
        try {
            const aceEditor = ace.edit('file-editor-ace');
            return aceEditor.getValue();
        } catch (e) {
            console.log('ACE Editor not available, using textarea');
            return textarea ? textarea.value : '';
        }
    } 
    // Textarea
    else if (textarea) {
        return textarea.value;
    }
    
    return '';
}

// XML aus Tabelle generieren
function generateXMLFromTable() {
    const table = document.getElementById('xml-translations-table');
    if (!table) return '';
    
    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<translations>\n';
    
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const keyCell = row.querySelector('td:first-child code');
        const valueInput = row.querySelector('input[data-key]');
        const commentCell = row.querySelector('td:last-child small');
        
        if (keyCell && valueInput) {
            const key = keyCell.textContent;
            const value = valueInput.value;
            const comment = commentCell ? commentCell.textContent.trim() : '';
            
            if (comment) {
                xml += '    <!-- ' + comment + ' -->\n';
            }
            xml += '    <' + key + '>' + escapeXml(value) + '</' + key + '>\n';
        }
    });
    
    xml += '</translations>';
    return xml;
}

// XML-Escaping
function escapeXml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getAceMode(fileType) {
    const modes = {
        'HTML': 'html',
        'CSS': 'css',
        'PHP': 'php',
        'JavaScript': 'javascript',
        'JSON': 'json',
        'XML': 'xml',
        'SQL': 'sql',
        'Text': 'text',
        'Markdown': 'markdown',
        'YAML': 'yaml',
        'INI': 'ini',
        'Shell': 'sh'
    };
    
    return modes[fileType] || 'text';
}

function detectColorCodes(content) {
    const patterns = {
        hex: /#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g,
        rgb: /rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/g,
        rgba: /rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/g,
        hsl: /hsl\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)/g,
        hsla: /hsla\s*\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*,\s*([\d.]+)\s*\)/g,
        named: /\b(red|green|blue|yellow|orange|purple|pink|brown|black|white|gray|grey)\b/gi
    };
    
    const foundColors = [];
    
    Object.keys(patterns).forEach(type => {
        const matches = content.match(patterns[type]);
        if (matches) {
            matches.forEach(match => {
                foundColors.push({
                    type: type,
                    value: match
                });
            });
        }
    });
    
    return foundColors;
}

function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    bytes = Math.max(bytes, 0);
    const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    const powMin = Math.min(pow, units.length - 1);
    bytes /= Math.pow(1024, powMin);
    return Math.round(bytes * 100) / 100 + ' ' + units[powMin];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showSuccess(message) {
    // Hier würde eine Success-Meldung angezeigt werden
    alert('✅ ' + message);
}

function showError(message) {
    // Hier würde eine Error-Meldung angezeigt werden
    alert('❌ ' + message);
}

// Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    loadFileList('.');
    
    // Event-Listener für statische Buttons hinzufügen
    document.getElementById('refresh-btn').addEventListener('click', function() {
        refreshFileList();
    });
    
    document.getElementById('save-file-btn').addEventListener('click', function() {
        saveFile();
    });
    
    document.getElementById('confirm-delete-btn').addEventListener('click', function() {
        confirmDelete();
    });
    
    // Event-Listener für Breadcrumb-Links hinzufügen
    document.getElementById('file-breadcrumb').addEventListener('click', function(e) {
        if (e.target.closest('a[data-directory]')) {
            e.preventDefault();
            const link = e.target.closest('a[data-directory]');
            loadDirectory(link.getAttribute('data-directory'));
        }
    });
});
</script>