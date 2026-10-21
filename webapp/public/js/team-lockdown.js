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
document.head.append(Object.assign(document.createElement('style'), {
    textContent: '@media print { body { display: none !important; } }',
}));

// Require full screen. Full screen started with the button below ends when
// another page opens; full screen started with F11 or in kiosk mode does not.
if (window.top === window) {
    const dialog = document.createElement('dialog');
    dialog.className = 'fullscreen-required bg-body text-body border rounded p-4';
    dialog.innerHTML = `
        <p>Use DOMjudge in full screen.</p>
        <button type="button" class="btn btn-primary">Enter full screen</button>
        <p class="small text-muted mt-3 mb-0">Full screen with F11 stays on when you open another page.</p>`;
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
