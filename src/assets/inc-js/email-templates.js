/**
 * E-Mail-Template-Verwaltung JavaScript
 * 
 * TODO: Vereinfachte Version mit eigenständigem FileEditor
 * TODO: Verwendet FileEditor aus src/core/ für bessere Kompatibilität
 * 
 * @author Teris
 * @version 3.1.2
 */

(function() {
    'use strict';
    
    // Initialisierung
    function init() {
        console.log('E-Mail-Template-Verwaltung wird initialisiert...');
        
        // Test-AJAX-Verbindung
        testConnection();
        
        // Event-Listener hinzufügen
        setupEventListeners();
        
        // Templates laden
        loadTemplates();
    }
    
    // Event-Listener einrichten
    function setupEventListeners() {
        // Template erstellen
        const createBtn = document.getElementById('create-template-btn');
        if (createBtn) {
            createBtn.addEventListener('click', createNewTemplate);
        }
        
        // Editor schließen
        const closeBtn = document.getElementById('close-editor-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeEditor);
        }
        
        // Vorschau schließen
        const closePreviewBtn = document.getElementById('close-preview-btn');
        if (closePreviewBtn) {
            closePreviewBtn.addEventListener('click', closePreview);
        }
        
        // Template-Formular
        const templateForm = document.getElementById('template-form');
        if (templateForm) {
            templateForm.addEventListener('submit', saveTemplate);
        }
        
        // Template-Typ-Änderung
        const templateTypeRadios = document.querySelectorAll('input[name="template_type"]');
        templateTypeRadios.forEach(radio => {
            radio.addEventListener('change', handleTemplateTypeChange);
        });
        
        // Vorschau-Button
        const previewBtn = document.getElementById('preview-btn');
        if (previewBtn) {
            previewBtn.addEventListener('click', previewTemplate);
        }
        
    }
    
    // Vollständige HTML-Struktur aus Body-Inhalt wiederherstellen
    function reconstructFullHtml(bodyContent) {
        // Prüfen ob bereits vollständige HTML-Struktur vorhanden ist
        if (bodyContent.includes('<html') && bodyContent.includes('</html>')) {
            return bodyContent;
        }
        
        // HTML-Struktur extrahieren aus dem ursprünglichen Textarea-Inhalt
        const textarea = document.getElementById('ace-editor_textarea');
        if (textarea && textarea.value) {
            const originalContent = textarea.value;
            
            // Head-Teil extrahieren
            const headMatch = originalContent.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
            const headContent = headMatch ? headMatch[0] : '<head><title>E-Mail Template</title></head>';
            
            // Body-Teil mit dem bearbeiteten Inhalt ersetzen
            const bodyStart = originalContent.indexOf('<body');
            const bodyEnd = originalContent.lastIndexOf('</body>');
            
            if (bodyStart !== -1 && bodyEnd !== -1) {
                const beforeBody = originalContent.substring(0, bodyStart);
                const afterBody = originalContent.substring(bodyEnd + 7);
                
                // Neuen Body mit bearbeitetem Inhalt erstellen
                const newBody = `<body>${bodyContent}</body>`;
                
                return beforeBody + newBody + afterBody;
            }
        }
        
        // Fallback: Standard HTML-Struktur
        return `<!DOCTYPE html>
<html>
<head>
    <title>E-Mail Template</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
${bodyContent}
</body>
</html>`;
    }

    // Template-Typ-Änderung behandeln
    function handleTemplateTypeChange(event) {
        const templateType = event.target.value;
        const textarea = document.getElementById('ace-editor_textarea');
        const preview = document.getElementById('ace-editor_preview');
        
        if (!textarea || !preview) return;
        
        if (templateType === 'html') {
            // HTML-Vorschau anzeigen - nur Body-Inhalt extrahieren
            const currentContent = textarea.value;
            textarea.style.display = 'none';
            preview.style.display = 'block';
            
            // Body-Inhalt extrahieren für die Vorschau
            const bodyMatch = currentContent.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
            if (bodyMatch) {
                preview.innerHTML = bodyMatch[1];
            } else {
                preview.innerHTML = currentContent;
            }
        } else {
            // Raw-Text-Editor anzeigen - Inhalt aus Vorschau übernehmen
            const currentContent = preview.innerHTML;
            textarea.style.display = 'block';
            preview.style.display = 'none';
            
            // Vollständige HTML-Struktur wiederherstellen
            const fullHtml = reconstructFullHtml(currentContent);
            textarea.value = fullHtml;
        }
    }
    
    // Test-AJAX-Verbindung
    function testConnection() {
        console.log('Teste AJAX-Verbindung...');
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=email_templates&subaction=test'
        })
        .then(response => {
            console.log('Test Response Status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Test Response Data:', data);
        })
        .catch(error => {
            console.error('Test Error:', error);
        });
    }
    
    // Templates laden
    function loadTemplates() {
        console.log('Lade Templates...');
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=email_templates&subaction=get_templates'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('AJAX Response:', data);
            if (data.success) {
                displayTemplates(data.templates);
            } else {
                console.error('Fehler beim Laden der Templates:', data.message || 'Unbekannter Fehler');
                console.error('Debug Info:', data.debug || 'Keine Debug-Informationen');
                alert('Fehler beim Laden der Templates: ' + (data.message || 'Unbekannter Fehler'));
                displayTemplates([]); // Leere Liste anzeigen
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Templates:', error);
            console.error('Error Details:', error.message, error.stack);
            displayTemplates([]); // Leere Liste anzeigen
        });
    }
    
    // Templates anzeigen
    function displayTemplates(templates) {
        const templatesList = document.getElementById('templates-list');
        if (!templatesList) return;
        
        if (templates.length === 0) {
            templatesList.innerHTML = '<div class="list-group-item text-center text-muted">Keine Templates vorhanden</div>';
            return;
        }
        
        templatesList.innerHTML = templates.map(template => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${template.template_name}</h6>
                    <small class="text-muted">${template.subject}</small>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editTemplate(${template.id})">
                        <i class="bi bi-pencil"></i> Bearbeiten
                    </button>
                    <button class="btn btn-outline-info" onclick="previewTemplateById(${template.id})">
                        <i class="bi bi-eye"></i> Vorschau
                    </button>
                    <button class="btn btn-outline-success" onclick="testEmailById(${template.id})">
                        <i class="bi bi-envelope"></i> Test-E-Mail
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteTemplate(${template.id})">
                        <i class="bi bi-trash"></i> Löschen
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // Template bearbeiten
    function editTemplate(templateId) {
        // Template-Daten laden
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=email_templates&subaction=get_template&template_id=${templateId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.template;
                
                // Formular ausfüllen
                document.getElementById('template-id').value = template.id;
                document.getElementById('template-name').value = template.template_name;
                document.getElementById('template-subject').value = template.subject;
                document.getElementById('template-variables').value = template.variables;
                
                // Template-Typ setzen
                const templateType = template.html_content ? 'html' : 'raw';
                document.querySelector(`input[name="template_type"][value="${templateType}"]`).checked = true;
                
                // Editor laden
                loadEditor(templateId, templateType);
                
                // Inhalt in den Editor laden
                setTimeout(() => {
                    const textarea = document.getElementById('ace-editor_textarea');
                    const preview = document.getElementById('ace-editor_preview');
                    if (textarea) {
                        const content = templateType === 'html' ? template.html_content : template.raw_content;
                        textarea.value = content || '';
                        
                        // Vorschau aktualisieren falls HTML-Modus - nur Body-Inhalt anzeigen
                        if (preview && templateType === 'html') {
                            const bodyMatch = (content || '').match(/<body[^>]*>([\s\S]*?)<\/body>/i);
                            if (bodyMatch) {
                                preview.innerHTML = bodyMatch[1];
                            } else {
                                preview.innerHTML = content || '';
                            }
                        }
                    }
                }, 100);
                
                // Editor anzeigen
                document.getElementById('template-editor').style.display = 'block';
                document.getElementById('template-editor').scrollIntoView({ behavior: 'smooth' });
            } else {
                alert('Fehler beim Laden des Templates: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden des Templates:', error);
            alert('Fehler beim Laden des Templates');
        });
    }
    
    // Editor laden
    function loadEditor(templateId, contentType) {
        const editorContainer = document.getElementById('editor-container');
        if (!editorContainer) return;
        
        // Einfacher Fallback-Editor erstellen
        const editorHtml = `
            <div class="fallback-editor">
                <div class="editor-toolbar mb-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="saveEditor('ace-editor')">
                            <i class="bi bi-save"></i> Speichern (Ctrl+S)
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatCode('ace-editor')">
                            <i class="bi bi-braces"></i> Formatieren
                        </button>
                    </div>
                </div>
                <div id="editor-content-area">
                    <textarea id="ace-editor_textarea" class="form-control" style="height: 400px; font-family: monospace; font-size: 14px; display: none;" placeholder="Template-Inhalt eingeben..."></textarea>
                    <div id="ace-editor_preview" class="form-control" contenteditable="true" style="height: 400px; overflow-y: auto; background: white; border: 1px solid #ced4da; display: none; padding: 10px;" placeholder="Template-Inhalt eingeben..."></div>
                </div>
            </div>
        `;
        
        editorContainer.innerHTML = editorHtml;
        
        // Event-Listener für Textarea
        const textarea = document.getElementById('ace-editor_textarea');
        const preview = document.getElementById('ace-editor_preview');
        
        if (textarea) {
            textarea.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    saveEditor('ace-editor');
                }
            });
            
            // Bei Änderungen in der Textarea die Vorschau aktualisieren
            textarea.addEventListener('input', function() {
                if (preview && preview.style.display !== 'none') {
                    preview.innerHTML = textarea.value;
                }
            });
        }
        
        // Event-Listener für editierbare HTML-Vorschau
        if (preview) {
            preview.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    saveEditor('ace-editor');
                }
            });
            
            // Bei Änderungen in der Vorschau den Textarea-Inhalt aktualisieren
            preview.addEventListener('input', function() {
                if (textarea && textarea.style.display !== 'none') {
                    // Vollständige HTML-Struktur wiederherstellen
                    const fullHtml = reconstructFullHtml(preview.innerHTML);
                    textarea.value = fullHtml;
                }
            });
            
            // Bei Änderungen in der Vorschau den Textarea-Inhalt aktualisieren (auch bei paste)
            preview.addEventListener('paste', function() {
                setTimeout(() => {
                    if (textarea && textarea.style.display !== 'none') {
                        // Vollständige HTML-Struktur wiederherstellen
                        const fullHtml = reconstructFullHtml(preview.innerHTML);
                        textarea.value = fullHtml;
                    }
                }, 10);
            });
        }
        
        // Initialen Modus setzen
        const templateType = document.querySelector('input[name="template_type"]:checked').value;
        if (templateType === 'html') {
            textarea.style.display = 'none';
            preview.style.display = 'block';
        } else {
            textarea.style.display = 'block';
            preview.style.display = 'none';
        }
        
        console.log('Fallback-Editor erfolgreich geladen');
    }
    
    // Editor-Inhalt speichern
    function saveEditorContent(templateId, contentType) {
        // Fallback-Editor Textarea und Vorschau finden
        const textarea = document.getElementById('ace-editor_textarea');
        const preview = document.getElementById('ace-editor_preview');
        
        if (!textarea && !preview) {
            alert('Editor nicht gefunden');
            return;
        }
        
        // Inhalt aus dem aktiven Modus holen
        let content = '';
        if (textarea && textarea.style.display !== 'none') {
            content = textarea.value;
        } else if (preview && preview.style.display !== 'none') {
            // Vollständige HTML-Struktur wiederherstellen
            content = reconstructFullHtml(preview.innerHTML);
        } else {
            content = textarea ? textarea.value : reconstructFullHtml(preview.innerHTML);
        }
        
        // Inhalt auch in das versteckte Input-Feld übertragen
        const hiddenInput = document.getElementById('template-content');
        if (hiddenInput) {
            hiddenInput.value = content;
        }
        
        // Speichern
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=email_templates&subaction=save_editor&template_id=${templateId}&content=${encodeURIComponent(content)}&content_type=${contentType}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Template erfolgreich gespeichert!');
                loadTemplates(); // Templates neu laden
            } else {
                alert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Speichern:', error);
            alert('Fehler beim Speichern des Templates');
        });
    }
    
    // Neues Template erstellen
    function createNewTemplate() {
        // Formular zurücksetzen
        document.getElementById('template-form').reset();
        document.getElementById('template-id').value = '';
        
        // Editor-Container leeren
        const editorContainer = document.getElementById('editor-container');
        if (editorContainer) {
            editorContainer.innerHTML = '<div class="text-center text-muted">Wählen Sie einen Template-Typ aus</div>';
        }
        
        // Editor anzeigen
        document.getElementById('template-editor').style.display = 'block';
        document.getElementById('template-editor').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Template speichern (Fallback für Formular)
    function saveTemplate(event) {
        event.preventDefault();
        
        // Inhalt aus dem aktiven Editor in das versteckte Input-Feld übertragen
        const textarea = document.getElementById('ace-editor_textarea');
        const preview = document.getElementById('ace-editor_preview');
        const hiddenInput = document.getElementById('template-content');
        
        if (hiddenInput) {
            let content = '';
            if (textarea && textarea.style.display !== 'none') {
                content = textarea.value;
            } else if (preview && preview.style.display !== 'none') {
                // Vollständige HTML-Struktur wiederherstellen
                content = reconstructFullHtml(preview.innerHTML);
            } else {
                content = textarea ? textarea.value : (preview ? reconstructFullHtml(preview.innerHTML) : '');
            }
            hiddenInput.value = content;
        }
        
        const formData = new FormData(event.target);
        formData.append('action', 'email_templates');
        formData.append('subaction', 'save_template');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Template erfolgreich gespeichert!');
                loadTemplates();
                closeEditor();
            } else {
                alert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Speichern:', error);
            alert('Fehler beim Speichern des Templates');
        });
    }
    
    // Template löschen
    function deleteTemplate(templateId) {
        if (!confirm('Template wirklich löschen?')) return;
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=email_templates&subaction=delete_template&template_id=${templateId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Template erfolgreich gelöscht!');
                loadTemplates();
            } else {
                alert('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Löschen:', error);
            alert('Fehler beim Löschen des Templates');
        });
    }
    
    // Template-Vorschau
    function previewTemplate() {
        const templateId = document.getElementById('template-id').value;
        if (!templateId) {
            alert('Bitte speichern Sie das Template zuerst');
            return;
        }
        previewTemplateById(templateId);
    }
    
    // Test-E-Mail senden (nach Template-ID)
    window.testEmailById = function(templateId) {
        if (!templateId) {
            alert('Bitte wählen Sie ein Template aus');
            return;
        }
        
        // Bestätigung vor dem Senden
        if (!confirm('Möchten Sie wirklich eine Test-E-Mail senden? Diese wird an die konfigurierte E-Mail-Adresse gesendet.')) {
            return;
        }
        
        // Test-E-Mail über AJAX senden
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=email_templates&subaction=test_email&template_id=${templateId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
            } else {
                alert('❌ ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler beim Senden der Test-E-Mail:', error);
            alert('❌ Fehler beim Senden der Test-E-Mail');
        });
    };
    
    // Template-Vorschau nach ID
    function previewTemplateById(templateId) {
        const sampleVariables = {
            firstName: 'Max',
            lastName: 'Mustermann',
            email: 'max.mustermann@example.com',
            username: 'max.mustermann',
            password: 'geheim123',
            loginUrl: 'https://example.com/login',
            verificationLink: 'https://example.com/verify?token=abc123',
            site_name: 'Meine Website',
            systemCredentials: '<div>System-Anmeldedaten hier...</div>'
        };
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=email_templates&subaction=preview_template&template_id=${templateId}&variables=${encodeURIComponent(JSON.stringify(sampleVariables))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPreview(data.preview);
            } else {
                alert('Fehler bei der Vorschau: ' + (data.message || 'Unbekannter Fehler'));
            }
        })
        .catch(error => {
            console.error('Fehler bei der Vorschau:', error);
            alert('Fehler bei der Vorschau');
        });
    }
    
    // Vorschau anzeigen
    function showPreview(content) {
        // Vorschau-Modal erstellen falls nicht vorhanden
        let previewModal = document.getElementById('template-preview-modal');
        if (!previewModal) {
            previewModal = document.createElement('div');
            previewModal.id = 'template-preview-modal';
            previewModal.className = 'modal fade';
            previewModal.setAttribute('tabindex', '-1');
            previewModal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Template-Vorschau</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="preview-content-modal"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(previewModal);
        }
        
        const previewContent = document.getElementById('preview-content-modal');
        if (previewContent) {
            previewContent.innerHTML = content;
        }
        
        // Modal anzeigen
        const modal = new bootstrap.Modal(previewModal);
        modal.show();
    }
    
    // Editor schließen
    function closeEditor() {
        const editor = document.getElementById('template-editor');
        if (editor) {
            editor.style.display = 'none';
        }
    }
    
    // Vorschau schließen
    function closePreview() {
        const preview = document.getElementById('template-preview');
        if (preview) {
            preview.style.display = 'none';
        }
    }
    
    // Globale Funktionen für onclick-Handler
    window.editTemplate = editTemplate;
    window.deleteTemplate = deleteTemplate;
    window.previewTemplateById = previewTemplateById;
    
    // Initialisierung beim Laden der Seite
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();