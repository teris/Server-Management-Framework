// Dashboard behaviour; configuration is loaded from the JSON data block.
// URL-Parameter auswerten
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\?&]' + name + '=([^&#]*)');
            const results = regex.exec(window.location.search);
            return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        // Sidebar-Navigation - Aktive Links hervorheben
        function highlightActiveNavLink() {
            const currentOption = getUrlParameter('option');
            const navLinks = document.querySelectorAll('#sidebarMenu .nav-link');

            navLinks.forEach(link => {
                link.classList.remove('active');
                const href = link.getAttribute('href');
                if (href && href.includes('option=' + currentOption)) {
                    link.classList.add('active');
                }
            });
        }

        // Beim Laden aktive Links hervorheben
        highlightActiveNavLink();

        // Editor-Funktionen für E-Mail-Templates
        window.toggleWordWrap = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                textarea.style.whiteSpace = textarea.style.whiteSpace === 'nowrap' ? 'pre-wrap' : 'nowrap';
            }
        };

        window.toggleLineNumbers = function(editorId) {
            // Einfache Implementierung für Textarea
            console.log("Zeilennummern umschalten für Editor:", editorId);
        };

        window.changeMode = function(editorId, mode) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                textarea.setAttribute('data-mode', mode);
                console.log("Editor-Modus geändert zu:", mode);
            }
        };


        // Vollständige HTML-Struktur aus Body-Inhalt wiederherstellen
        window.reconstructFullHtml = function(bodyContent) {
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
        };

        window.saveEditor = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            const preview = document.getElementById("ace-editor_preview");
    
            // Inhalt aus dem aktiven Modus holen
            let content = '';
            if (textarea && textarea.style.display !== 'none') {
                content = textarea.value;
            } else if (preview && preview.style.display !== 'none') {
                // Vollständige HTML-Struktur wiederherstellen
                content = reconstructFullHtml(preview.innerHTML);
            } else if (textarea) {
                content = textarea.value;
            } else if (preview) {
                content = reconstructFullHtml(preview.innerHTML);
            }
    
            if (content) {
                // Inhalt in das versteckte Input-Feld übertragen
                const hiddenInput = document.getElementById('template-content');
                if (hiddenInput) {
                    hiddenInput.value = content;
                }
        
                console.log("Speichere Inhalt:", content);
        
                // Template-ID und Content-Type ermitteln
                const templateId = document.getElementById('template-id').value;
                const contentType = document.querySelector('input[name="template_type"]:checked').value;
        
                if (templateId) {
                    // Über AJAX speichern
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
                            alert("Template erfolgreich gespeichert!");
                        } else {
                            alert("Fehler beim Speichern: " + (data.message || "Unbekannter Fehler"));
                        }
                    })
                    .catch(error => {
                        console.error("Fehler beim Speichern:", error);
                        alert("Fehler beim Speichern des Templates");
                    });
                } else {
                    alert("Bitte speichern Sie das Template über das Formular");
                }
            }
        };

        window.formatCode = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                const content = textarea.value;
                // Einfache HTML-Formatierung
                const formatted = content
                    .replace(/></g, ">\\n<")
                    .replace(/^\\s+|\\s+$/g, "");
        
                textarea.value = formatted;
            }
        };
