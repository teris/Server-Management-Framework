  // Session-Informationen aus PHP
        const sessionInfo = <?= json_encode($session_info) ?>;
        let sessionTimer;
        let warningShown = false;
        
        // Session-Timer initialisieren
        function initSessionTimer() {
            if (!sessionInfo) return;
            
            updateSessionDisplay();
            
            // Timer jede Sekunde aktualisieren
            sessionTimer = setInterval(() => {
                sessionInfo.timeRemaining--;
                updateSessionDisplay();
                
                // Warnung bei 2 Minuten
                if (sessionInfo.timeRemaining <= 120 && !warningShown) {
                    showSessionWarning();
                    warningShown = true;
                }
                
                // Session abgelaufen
                if (sessionInfo.timeRemaining <= 0) {
                    showSessionExpired();
                }
            }, 1000);
        }
        
        function updateSessionDisplay() {
            const timer = document.getElementById('sessionTimer');
            const timeDisplay = document.getElementById('timeRemaining');
            
            if (!timer || !timeDisplay) return;
            
            const minutes = Math.floor(sessionInfo.timeRemaining / 60);
            const seconds = sessionInfo.timeRemaining % 60;
            
            timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Timer-Style anpassen
            timer.className = 'session-timer';
            if (sessionInfo.timeRemaining <= 120) {
                timer.className += ' warning';
            }
            if (sessionInfo.timeRemaining <= 60) {
                timer.className += ' danger';
            }
        }
        
        function showSessionWarning() {
            const warning = document.getElementById('sessionWarning');
            const warningTime = document.getElementById('warningTime');
            
            if (warning && warningTime) {
                const minutes = Math.floor(sessionInfo.timeRemaining / 60);
                warningTime.textContent = `${minutes} Minute${minutes !== 1 ? 'n' : ''}`;
                warning.style.display = 'block';
                
                // Warnung bei Klick verstecken und Session verlängern
                warning.onclick = () => {
                    warning.style.display = 'none';
                    extendSession();
                };
                
                // Warnung automatisch nach 10 Sekunden verstecken
                setTimeout(() => {
                    if (warning.style.display === 'block') {
                        warning.style.display = 'none';
                    }
                }, 10000);
            }
        }
        
        function showSessionExpired() {
            clearInterval(sessionTimer);
            document.getElementById('sessionExpired').style.display = 'flex';
            
            // Nach 5 Sekunden automatisch zur Login-Seite
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 5000);
        }
        
        function extendSession() {
            // Session durch einen leeren AJAX-Call verlängern
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=extend_session'
            }).then(() => {
                // Session-Zeit zurücksetzen
                sessionInfo.timeRemaining = sessionInfo.timeout;
                warningShown = false;
                showNotification('Session verlängert');
            });
        }
        
        // Session bei jeder Benutzeraktivität verlängern
        let activityTimer;
        function resetActivityTimer() {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(() => {
                extendSession();
            }, 30000); // Alle 30 Sekunden bei Aktivität
        }
        
        // Event-Listener für Benutzeraktivität
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, true);
        });