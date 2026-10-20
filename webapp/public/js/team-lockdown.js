'use strict';

// Loaded on the team pages when the team_lockdown setting is on.

// Copy and paste only work within DOMjudge: they use a clipboard of their own,
// kept for this browser tab, and never read or write the system clipboard.
const lockdownClipboard = {
    get() {
        try {
            return JSON.parse(sessionStorage.getItem('domjudge_clipboard')) || {text: ''};
        } catch (e) {
            return {text: ''};
        }
    },
    set(value) {
        try {
            sessionStorage.setItem('domjudge_clipboard', JSON.stringify(value));
        } catch (e) {
            // Storage is disabled; then copy and paste do not work.
        }
    },
};

function selectedText(element) {
    if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
        return element.value.slice(element.selectionStart ?? 0, element.selectionEnd ?? 0);
    }
    return document.getSelection().toString();
}

// Runs after the handlers of the page, so the code editor has put its text
// (and data on how to paste it) on the event already.
function onCopy(e) {
    const handledByEditor = e.defaultPrevented;
    lockdownClipboard.set({
        text: handledByEditor ? e.clipboardData.getData('text/plain') : selectedText(e.target),
        editorData: handledByEditor ? e.clipboardData.getData('vscode-editor-data') : '',
    });
    e.clipboardData.clearData();
    e.preventDefault();
    if (e.type === 'cut' && !handledByEditor) {
        document.execCommand('delete');
    }
}

document.addEventListener('copy', onCopy);
document.addEventListener('cut', onCopy);

window.addEventListener('paste', e => {
    e.preventDefault();
    e.stopImmediatePropagation();
    const {text, editorData} = lockdownClipboard.get();
    const editor = window.monaco && monaco.editor.getEditors().find(ed => ed.getDomNode()?.contains(e.target));
    if (editor) {
        let data = {};
        try {
            data = JSON.parse(editorData) || {};
        } catch (err) {
            // Copied from outside the editor.
        }
        editor.trigger('keyboard', 'paste', {
            text,
            pasteOnNewLine: !!data.isFromEmptySelection,
            multicursorText: data.multicursorText ?? null,
            mode: data.mode ?? null,
        });
    } else {
        document.execCommand('insertText', false, text);
    }
}, true);

// The paste item in the context menu of the code editor uses the Clipboard API.
if (navigator.clipboard) {
    navigator.clipboard.readText = async () => lockdownClipboard.get().text;
    navigator.clipboard.writeText = async text => lockdownClipboard.set({text, editorData: ''});
    navigator.clipboard.read = async () => [];
    navigator.clipboard.write = async () => {};
}

// No dragging text in or out of the page.
for (const type of ['dragstart', 'dragover', 'drop']) {
    window.addEventListener(type, e => {
        e.preventDefault();
        e.stopImmediatePropagation();
    }, true);
}

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
