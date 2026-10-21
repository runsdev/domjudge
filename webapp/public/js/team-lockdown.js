'use strict';

// Loaded on the team pages when the team_lockdown setting is on.

// No copy and paste at all, also not within DOMjudge, and no dragging text in or out.
for (const type of ['copy', 'cut', 'paste', 'dragstart', 'dragover', 'drop']) {
    window.addEventListener(type, e => {
        e.preventDefault();
        e.stopImmediatePropagation();
    }, true);
}

// The code editor also copies and pastes with the Clipboard API, e.g. from its context menu.
if (navigator.clipboard) {
    navigator.clipboard.readText = async () => '';
    navigator.clipboard.writeText = async () => {};
    navigator.clipboard.read = async () => [];
    navigator.clipboard.write = async () => {};
}

// For initEditor() in team-editor.js, which then also turns off moving text by dragging it.
window.domjudgeLockdown = true;

// No saving or printing the page, e.g. a problem statement.
window.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && ['p', 's'].includes(e.key.toLowerCase())) {
        e.preventDefault();
    }
}, true);
// No selecting text outside the code editor and input fields, and no context menu of the
// browser, which can search for text or ask AI about it. The code editor keeps its own menu.
document.head.append(Object.assign(document.createElement('style'), {
    textContent: 'body { -webkit-user-select: none; user-select: none; } @media print { body { display: none !important; } }',
}));
window.addEventListener('contextmenu', e => e.preventDefault(), true);

// With team_lockdown_fullscreen (data-fullscreen on this script), require full screen. Browsers end
// the full screen that a page started when another page opens, so the team pages then open in a
// frame of a page that stays open, team/lockdown.html.twig. Full screen with F11 or in kiosk mode
// stays on anyway.
if (window.frameElement?.id === 'lockdown-frame') {
    // Show the address and title of this page on the page with the frame, also after a reload.
    top.history.replaceState(null, '', top.location.pathname + '#' + location.pathname + location.search);
    top.document.title = document.title;
} else if (window.top === window && document.currentScript.hasAttribute('data-fullscreen')) {
    // This script is in js/.
    location.replace(new URL('../team/lockdown#' + location.pathname + location.search, document.currentScript.src));
} else if (window.top === window && document.getElementById('lockdown-frame')) {
    // Open the page in the address, if it is one of DOMjudge.
    const url = new URL(location.hash.slice(1) || '../team', location.href);
    document.getElementById('lockdown-frame').src = url.origin === location.origin ? url : '../team';

    const dialog = document.createElement('dialog');
    dialog.className = 'fullscreen-required bg-body text-body border rounded p-4';
    dialog.innerHTML = `
        <p>Use DOMjudge in full screen.</p>
        <button type="button" class="btn btn-primary">Enter full screen</button>
        <p class="small text-muted mt-3 mb-0">Full screen with F11 stays on when you press Esc or open another website.</p>`;
    const fullscreen = matchMedia('(display-mode: fullscreen)');
    const update = () => {
        if (document.fullscreenElement || fullscreen.matches) {
            dialog.close();
        } else if (!dialog.open) {
            // Makes the rest of the page inert.
            dialog.showModal();
        }
    };
    dialog.querySelector('button').addEventListener('click',
        () => document.documentElement.requestFullscreen().catch(() => {}));
    // Escape closes the dialog, so open it again.
    dialog.addEventListener('close', () => setTimeout(update));
    document.addEventListener('fullscreenchange', update);
    fullscreen.addEventListener('change', update);
    document.body.append(dialog);
    update();
}
