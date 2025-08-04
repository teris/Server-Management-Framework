// Session-Informationen werden global als window.sessionInfo erwartet
let sessionTimer;
let warningShown = false;

// Session-Timer initialisieren
function initSessionTimer() {
    if (!window.sessionInfo) return;
    updateSessionDisplay();
    // Timer jede Sekunde aktualisieren
    sessionTimer = setInterval(() => {
        window.sessionInfo.timeRemaining--;
        updateSessionDisplay();
        // Warnung bei 2 Minuten
        if (window.sessionInfo.timeRemaining <= 120 && !warningShown) {
            showSessionWarning();
            warningShown = true;
        }
        // Session abgelaufen
        if (window.sessionInfo.timeRemaining <= 0) {
            showSessionExpired();
        }
    }, 1000);
}

function updateSessionDisplay() {
    const timer = document.getElementById('sessionTimer');
    const timeDisplay = document.getElementById('timeRemaining');
    if (!timer || !timeDisplay) return;
    const minutes = Math.floor(window.sessionInfo.timeRemaining / 60);
    const seconds = window.sessionInfo.timeRemaining % 60;
    timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    timer.className = 'session-timer';
    if (window.sessionInfo.timeRemaining <= 120) {
        timer.className += ' warning';
    }
    if (window.sessionInfo.timeRemaining <= 60) {
        timer.className += ' danger';
    }
}

function showSessionWarning() {
    const warning = document.getElementById('sessionWarning');
    const warningTime = document.getElementById('warningTime');
    if (warning && warningTime) {
        const minutes = Math.floor(window.sessionInfo.timeRemaining / 60);
        warningTime.textContent = `${minutes} Minute${minutes !== 1 ? 'n' : ''}`;
        warning.style.display = 'block';
        warning.onclick = () => {
            warning.style.display = 'none';
            extendSession();
        };
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
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 5000);
}

function extendSession() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=extend_session'
    }).then(() => {
        window.sessionInfo.timeRemaining = window.sessionInfo.timeout;
        warningShown = false;
        if (typeof showNotification === 'function') showNotification('Session verlängert');
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
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, resetActivityTimer, true);
});