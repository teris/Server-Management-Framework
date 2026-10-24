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
    const expired = document.getElementById('sessionExpired');
    if (expired) expired.style.display = 'flex';
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 5000);
}

let extendingSession = false;
let lastSessionExtension = Date.now();
function extendSession() {
    if (extendingSession || !window.sessionInfo) return;
    extendingSession = true;
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=extend_session'
    }).then(async response => {
        const result = await response.json();
        if (!response.ok || !result.success) {
            if (response.status === 401) window.location.href = 'login.php';
            return;
        }
        window.sessionInfo.timeRemaining = result.remaining;
        window.sessionInfo.timeout = result.timeout;
        lastSessionExtension = Date.now();
        warningShown = false;
        updateSessionDisplay();
    }).catch(() => {
        if (typeof showNotification === 'function') showNotification('Sitzung konnte nicht verlängert werden', 'error');
    }).finally(() => { extendingSession = false; });
}

// Throttle real user activity; continuous mouse movement must not postpone it forever.
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, () => {
        if (Date.now() - lastSessionExtension >= 30000) extendSession();
    }, true);
});
