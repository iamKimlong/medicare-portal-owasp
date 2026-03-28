document.addEventListener('DOMContentLoaded', function () {
    scrollChatToBottom();
    highlightActiveNav();
    initModeToggle();
    initHintsToggle();
});

function scrollChatToBottom() {
    var chatBox = document.getElementById('chatMessages');
    if (!chatBox) {
        return;
    }
    chatBox.scrollTop = chatBox.scrollHeight;
}

function highlightActiveNav() {
    var currentPath = window.location.pathname;
    var links = document.querySelectorAll('.nav-link');

    links.forEach(function (link) {
        if (link.getAttribute('href') !== currentPath) {
            return;
        }
        link.style.background = '#132D54';
        link.style.color = '#FFFFFF';
    });
}

function postToggle(target) {
    var body = new URLSearchParams();
    body.append('target', target);
    return fetch('/toggle_mode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        redirect: 'manual'
    });
}

function initModeToggle() {
    var btn = document.getElementById('modeToggle');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        if (btn.classList.contains('is-loading')) {
            return;
        }
        btn.classList.add('is-loading');

        var secureSpan = btn.querySelector('.mode-pill-secure');
        var vulnSpan = btn.querySelector('.mode-pill-vuln');
        secureSpan.classList.toggle('is-active');
        vulnSpan.classList.toggle('is-active');

        postToggle('secure_mode')
            .then(function () { window.location.reload(); })
            .catch(function () { window.location.reload(); });
    });
}

function initHintsToggle() {
    var btn = document.getElementById('hintsToggle');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        if (btn.classList.contains('is-loading')) {
            return;
        }
        btn.classList.add('is-loading');

        var wasActive = btn.classList.contains('is-active');
        btn.classList.toggle('is-active');

        var label = btn.querySelector('.hints-toggle-label');
        label.textContent = wasActive ? 'Show Hints' : 'Hide Hints';

        var hintCards = document.querySelectorAll('.vuln-hint');
        hintCards.forEach(function (card) {
            card.style.display = wasActive ? 'none' : '';
        });

        postToggle('vuln_hint')
            .then(function () { btn.classList.remove('is-loading'); })
            .catch(function () { window.location.reload(); });
    });
}
