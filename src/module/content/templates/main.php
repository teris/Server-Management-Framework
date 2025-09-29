<?php
// Admin-UI für Content Management mit ACE Editor
require_once __DIR__ . '/../Module.php';
$contentModule = new ContentModule('content');

// Falls Installation notwendig ist, Installations-Template laden und beenden
if ($contentModule->needsInstallation()) {
    include __DIR__ . '/install.php';
    return;
}
?>
<div class="container-fluid">
    <h2 class="mb-4">Content Management</h2>
    
    <!-- Seiten-Liste -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Seiten verwalten</h5>
                    <button class="btn btn-primary btn-sm" id="new-page-btn">
                        <i class="bi bi-plus"></i> Neue Seite
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="pages-table">
                            <thead><tr><th>Slug</th><th>Titel</th><th>Status</th><th>Aktualisiert</th><th>Aktionen</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Editor-Modal -->
    <div class="modal fade" id="page-editor-modal" tabindex="-1" aria-labelledby="page-editor-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="page-editor-modal-label">
                        <i class="bi bi-file-earmark-text"></i> <span id="modal-page-title">Neue Seite</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="page-form">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Slug (URL)</label>
                                <input class="form-control" name="slug" placeholder="meine-seite" required>
                                <div class="form-text">Wird in der URL verwendet: /public/page.php?slug=meine-seite</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Titel</label>
                                <input class="form-control" name="title" placeholder="Meine Seite" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="published">Veröffentlicht</option>
                                    <option value="draft">Entwurf</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Inhalt (HTML)</label>
                            <div class="editor-toolbar mb-2">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleEditorMode()">
                                        <i class="bi bi-code-slash"></i> Editor umschalten
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatCode()">
                                        <i class="bi bi-braces"></i> Code formatieren
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWordWrap()">
                                        <i class="bi bi-text-wrap"></i> Zeilenumbruch
                                    </button>
                                </div>
                            </div>
                            
                            <!-- ACE Editor -->
                            <div id="page-editor-ace" style="height: 500px; width: 100%; border: 1px solid #ccc; display: none;"></div>
                            <!-- Fallback Textarea -->
                            <textarea id="page-editor-textarea" name="content" style="width: 100%; height: 500px; font-family: monospace; font-size: 14px; border: 1px solid #ccc; padding: 10px; resize: vertical;" placeholder="HTML-Inhalt hier eingeben..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Abbrechen
                    </button>
                    <button type="button" class="btn btn-success" id="save-page-btn">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vorschau-Modal -->
    <div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="preview-modal-label">
                        <i class="bi bi-eye"></i> <span id="preview-page-title">Vorschau</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Slug:</strong> <code id="preview-slug"></code>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> <span id="preview-status" class="badge"></span>
                        </div>
                    </div>
                    <hr>
                    <div id="preview-content" style="border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; background: #fff;">
                        <!-- Vorschau-Inhalt wird hier angezeigt -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Schließen
                    </button>
                    <a id="preview-frontend-link" href="#" target="_blank" class="btn btn-primary">
                        <i class="bi bi-box-arrow-up-right"></i> Im Frontend öffnen
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Globale Variablen
let currentEditor = null;
let isAceMode = false;

// AJAX-Request Funktion
async function request(action, data) {
    const formData = { plugin: 'content', action, ...data };
    const res = await fetch(window.location.pathname, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
        body: new URLSearchParams(formData) 
    });
    return res.json();
}

// Seiten laden
async function loadPages() {
    const r = await request('list_pages', {});
    const tbody = document.querySelector('#pages-table tbody');
    tbody.innerHTML = '';
    if (r.success && Array.isArray(r.data)) {
        r.data.forEach(p => {
            const tr = document.createElement('tr');
            const statusBadge = p.status === 'published' ? 'success' : 'warning';
            tr.innerHTML = `
                <td><code>${p.slug}</code></td>
                <td>${p.title}</td>
                <td><span class="badge bg-${statusBadge}">${p.status}</span></td>
                <td>${p.updated_at ? new Date(p.updated_at).toLocaleString() : ''}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="previewPage('${p.slug}')">
                        <i class="bi bi-eye"></i> Vorschau
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="editPage('${p.slug}')">
                        <i class="bi bi-pencil"></i> Bearbeiten
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePage('${p.slug}')">
                        <i class="bi bi-trash"></i> Löschen
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
}

// Seite bearbeiten
async function editPage(slug) {
    const r = await request('get_page_content', { slug });
    if (r.success) {
        const page = r.data;
        document.querySelector('input[name="slug"]').value = page.slug;
        document.querySelector('input[name="title"]').value = page.title;
        document.querySelector('select[name="status"]').value = page.status;
        document.getElementById('modal-page-title').textContent = `Bearbeiten: ${page.title}`;
        
        // Inhalt in Editor laden
        const textarea = document.getElementById('page-editor-textarea');
        textarea.value = page.content || '';
        
        // ACE Editor initialisieren falls verfügbar
        if (typeof ace !== 'undefined') {
            initAceEditor(page.content || '');
        }
        
        // Modal anzeigen
        const modal = new bootstrap.Modal(document.getElementById('page-editor-modal'));
        modal.show();
    } else {
        alert('Fehler beim Laden der Seite: ' + r.error);
    }
}

// Seite vorschauen
async function previewPage(slug) {
    const r = await request('get_page_content', { slug });
    if (r.success) {
        const page = r.data;
        
        // Modal-Daten setzen
        document.getElementById('preview-page-title').textContent = `Vorschau: ${page.title}`;
        document.getElementById('preview-slug').textContent = page.slug;
        
        // Status-Badge
        const statusElement = document.getElementById('preview-status');
        const statusBadge = page.status === 'published' ? 'success' : 'warning';
        statusElement.textContent = page.status;
        statusElement.className = `badge bg-${statusBadge}`;
        
        // Inhalt anzeigen
        document.getElementById('preview-content').innerHTML = page.content || '<p class="text-muted">Kein Inhalt vorhanden</p>';
        
        // Frontend-Link setzen
        const frontendLink = document.getElementById('preview-frontend-link');
        frontendLink.href = `../../public/page.php?slug=${encodeURIComponent(page.slug)}`;
        
        // Modal anzeigen
        const modal = new bootstrap.Modal(document.getElementById('preview-modal'));
        modal.show();
    } else {
        alert('Fehler beim Laden der Seite: ' + r.error);
    }
}

// Neue Seite
function newPage() {
    document.getElementById('page-form').reset();
    document.getElementById('modal-page-title').textContent = 'Neue Seite';
    document.getElementById('page-editor-textarea').value = '';
    
    // ACE Editor initialisieren
    if (typeof ace !== 'undefined') {
        initAceEditor('');
    }
    
    const modal = new bootstrap.Modal(document.getElementById('page-editor-modal'));
    modal.show();
}

// Seite löschen
async function deletePage(slug) {
    if (confirm(`Seite "${slug}" wirklich löschen?`)) {
        const r = await request('delete_page', { slug });
        if (r.success) {
            await loadPages();
            showNotification('Seite gelöscht', 'success');
        } else {
            showNotification('Fehler beim Löschen: ' + r.error, 'error');
        }
    }
}

// ACE Editor initialisieren
function initAceEditor(content) {
    if (typeof ace === 'undefined') {
        console.log('ACE Editor nicht verfügbar');
        return;
    }
    
    try {
        const aceDiv = document.getElementById('page-editor-ace');
        const textarea = document.getElementById('page-editor-textarea');
        
        if (currentEditor) {
            currentEditor.destroy();
        }
        
        currentEditor = ace.edit('page-editor-ace');
        currentEditor.setTheme('ace/theme/monokai');
        currentEditor.session.setMode('ace/mode/html');
        // Basis-Optionen immer setzen
        currentEditor.setOptions({
            fontSize: 14,
            showLineNumbers: true,
            showGutter: true,
            highlightActiveLine: true,
            readOnly: false
        });

        // Language Tools (Autocompletion) optional laden, um Warnungen zu vermeiden
        const ensureLanguageTools = () => {
            try {
                const langTools = ace.require ? ace.require('ace/ext/language_tools') : null;
                if (langTools) {
                    currentEditor.setOptions({
                        enableBasicAutocompletion: true,
                        enableSnippets: true,
                        enableLiveAutocompletion: true
                    });
                }
            } catch (e) {
                // Ignorieren, wenn nicht verfügbar
            }
        };

        // Wenn Plugin nicht geladen ist, dynamisch nachladen
        let hasLanguageTools = false;
        try { hasLanguageTools = !!(ace.require && ace.require('ace/ext/language_tools')); } catch(e) { hasLanguageTools = false; }
        if (!hasLanguageTools) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.0/ext-language_tools.min.js';
            script.onload = ensureLanguageTools;
            document.head.appendChild(script);
        } else {
            ensureLanguageTools();
        }
        
        currentEditor.setValue(content);
        currentEditor.session.setUseWrapMode(false);
        currentEditor.session.setUseSoftTabs(true);
        currentEditor.clearSelection();
        currentEditor.focus();
        
        // Synchronisation mit Textarea
        currentEditor.on('change', function() {
            textarea.value = currentEditor.getValue();
        });
        
        aceDiv.style.display = 'block';
        textarea.style.display = 'none';
        isAceMode = true;
        
    } catch (e) {
        console.error('Fehler beim Initialisieren des ACE Editors:', e);
        document.getElementById('page-editor-ace').style.display = 'none';
        document.getElementById('page-editor-textarea').style.display = 'block';
        isAceMode = false;
    }
}

// Editor-Modus umschalten
function toggleEditorMode() {
    const aceDiv = document.getElementById('page-editor-ace');
    const textarea = document.getElementById('page-editor-textarea');
    
    if (isAceMode) {
        // Zu Textarea wechseln
        aceDiv.style.display = 'none';
        textarea.style.display = 'block';
        isAceMode = false;
    } else {
        // Zu ACE Editor wechseln
        if (typeof ace !== 'undefined') {
            initAceEditor(textarea.value);
        }
    }
}

// Code formatieren
function formatCode() {
    if (isAceMode && currentEditor) {
        const content = currentEditor.getValue();
        const formatted = content
            .replace(/></g, '>\n<')
            .replace(/^\s+|\s+$/g, '');
        currentEditor.setValue(formatted);
    } else {
        const textarea = document.getElementById('page-editor-textarea');
        const content = textarea.value;
        const formatted = content
            .replace(/></g, '>\n<')
            .replace(/^\s+|\s+$/g, '');
        textarea.value = formatted;
    }
}

// Zeilenumbruch umschalten
function toggleWordWrap() {
    if (isAceMode && currentEditor) {
        const useWrap = currentEditor.session.getUseWrapMode();
        currentEditor.session.setUseWrapMode(!useWrap);
    } else {
        const textarea = document.getElementById('page-editor-textarea');
        textarea.style.whiteSpace = textarea.style.whiteSpace === 'nowrap' ? 'pre-wrap' : 'nowrap';
    }
}

// Seite speichern
async function savePage() {
    const form = document.getElementById('page-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    const r = await request('save_page', data);
    if (r.success) {
        await loadPages();
        const modal = bootstrap.Modal.getInstance(document.getElementById('page-editor-modal'));
        modal.hide();
        showNotification('Seite gespeichert', 'success');
    } else {
        showNotification('Fehler beim Speichern: ' + r.error, 'error');
    }
}

// Benachrichtigung anzeigen
function showNotification(message, type) {
    // Einfache Alert-Box als Fallback
    alert(message);
}

// Event Listener
document.addEventListener('DOMContentLoaded', function() {
    // Neue Seite Button
    document.getElementById('new-page-btn').addEventListener('click', newPage);
    
    // Speichern Button
    document.getElementById('save-page-btn').addEventListener('click', savePage);
    
    // Seiten laden
    loadPages();
});
</script>


