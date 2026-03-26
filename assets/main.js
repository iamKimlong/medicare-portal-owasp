document.addEventListener('DOMContentLoaded', function () {
    scrollChatToBottom();
    highlightActiveNav();
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
