// End-to-end test of the team code editor and kiosk mode against a running
// DOMjudge with the example data (team demo/demo) and judgehosts. It changes
// the configuration (only the code editor, ranking and submissions shown) and
// submits solutions, so only run it against a test installation. See run.sh.
'use strict';
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const fs = require('node:fs');

const BASE = process.env.BASE_URL || 'http://domjudge';
const ADMIN = process.env.ADMIN_AUTH;            // user:password
const TEAM_USER = process.env.TEAM_USER || 'demo';
const TEAM_PASS = process.env.TEAM_PASS || 'demo';
const CONTEST = process.env.CONTEST || 'demo';
const SHOTS = process.env.SHOTS;  // directory for screenshots, optional
const KIOSK = process.env.KIOSK_SCRIPT || '/repo/misc-tools/dj_kiosk';
const CHROME = process.env.CHROME || '/ms-playwright/chromium-1223/chrome-linux64/chrome';

if (SHOTS) {
    fs.mkdirSync(SHOTS, { recursive: true });
}
const screenshot = (page, name) => SHOTS && page.screenshot({ path: `${SHOTS}/${name}.png` });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let step = 0;
const log = msg => console.log(`[${++step}] ${msg}`);

async function api(path, init = {}) {
    const res = await fetch(BASE + '/api/v4' + path, {
        ...init,
        headers: { Authorization: 'Basic ' + Buffer.from(ADMIN).toString('base64'), ...(init.headers || {}) },
    });
    assert.ok(res.ok, `API ${path}: HTTP ${res.status}`);
    return res.status === 204 ? null : res.json();
}

async function login(page) {
    await page.goto(BASE + '/login');
    await page.fill('#username', TEAM_USER);
    await page.fill('#inputPassword', TEAM_PASS);
    await Promise.all([page.waitForURL(u => !u.pathname.startsWith('/login')), page.click('button:has-text("Sign in")')]);
}

const editorReady = page => page.waitForFunction(() => window.monaco && window.__pasteEditor() && window.__pasteEditor().getModel());
const code = page => page.evaluate(() => window.__pasteEditor().getValue());
const setCode = (page, value) => page.evaluate(v => window.__pasteEditor().setValue(v), value);
const syntaxMarkers = page => page.evaluate(() => monaco.editor.getModelMarkers({ owner: 'syntax' }).map(m => `${m.startLineNumber}:${m.startColumn} ${m.message}`));
const select = async (page, which, value) => {
    await page.selectOption(`#submit_problem_paste_${which}`, value);
};
// Wait until the syntax markers (joined messages) match, e.g. /missing ";"/ or /^$/ for none.
const waitMarkers = (page, regex, what) => page.waitForFunction(
    src => new RegExp(src).test(monaco.editor.getModelMarkers({ owner: 'syntax' }).map(m => m.message).join('\n')),
    regex.source, { timeout: 15000 })
    .catch(async () => assert.fail(`${what}; markers: ${JSON.stringify(await syntaxMarkers(page))}`));

async function suggestions(page, text) {
    await page.keyboard.type(text, { delay: 50 });
    await page.waitForSelector('.suggest-widget.visible .monaco-list-row', { timeout: 10000 });
    await sleep(300);
    const labels = await page.$$eval('.suggest-widget.visible .monaco-list-row', rows => rows.map(r => r.getAttribute('aria-label')));
    return labels.join(' | ');
}

async function editorFlow(browser) {
    // Kiosk setup: only the code editor, and a scoreboard to check its links.
    const config = await api('/config', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ submit_methods: ['paste'], enable_ranking: true, show_teams_submissions: true }),
    });
    assert.deepEqual([config.submit_methods, config.enable_ranking], [['paste'], true]);
    const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
    await context.addInitScript(() => {
        window.__pasteEditor = () => window.monaco && monaco.editor.getEditors().find(e => e.getDomNode().closest('.paste-editor-container'));
    });
    const popups = [], downloads = [], problems = [];
    const page = await context.newPage();
    context.on('page', p => popups.push(p.url()));
    page.on('download', d => downloads.push(d.suggestedFilename()));
    page.on('console', m => { if (m.type() === 'error') problems.push('console: ' + m.text()); });
    page.on('pageerror', e => problems.push('pageerror: ' + e.message));
    page.on('response', r => { if (r.status() >= 400) problems.push(`HTTP ${r.status()} ${r.request().method()} ${r.url()} (on ${page.url()})`); });

    await login(page);
    log('logged in as team ' + TEAM_USER);

    // Problem list: with only the editor enabled, Submit opens the editor for that problem (no modal).
    await page.goto(BASE + '/team/problems');
    assert.equal(await page.locator('a[target="_blank"]').count(), 0, 'no new-window links on the problem list');
    const submitLink = page.locator('.problem-list .card').filter({ hasText: 'Hello World' }).locator('a[href*="/team/submit/"]');
    assert.equal(await submitLink.getAttribute('data-ajax-modal'), null);
    const [submitPage] = await Promise.all([page.waitForResponse(r => /\/team\/submit\/\d+$/.test(r.url())), submitLink.click()]);
    const csp = submitPage.headers()['content-security-policy'];
    log('editor page CSP: ' + csp);
    assert.match(csp, /'wasm-unsafe-eval'/);
    if (process.env.EXPECT_PROD) {
        assert.doesNotMatch(csp, /'unsafe-eval'/, 'DOMjudge must run in prod mode to test the production CSP');
    }
    await editorReady(page);
    assert.equal(await page.inputValue('#submit_problem_paste_problem'), 'hello');
    log('problem card Submit opens the editor with "hello" preselected');

    // Statement next to the editor, framed despite the webserver's X-Frame-Options: DENY.
    const frameEl = page.locator('#paste-statement iframe');
    await page.waitForFunction(() => document.querySelector('#paste-statement iframe').getAttribute('src'));
    assert.equal(await frameEl.getAttribute('src'), '/team/problems/hello/statement#navpanes=0');
    const frame = await (await frameEl.elementHandle()).contentFrame();
    await sleep(1500);
    assert.ok(frame.url().endsWith('/team/problems/hello/statement#navpanes=0'), 'statement frame loaded, got ' + frame.url());
    assert.ok(await page.locator('#paste-statement').isVisible());
    await screenshot(page, '01-editor-with-statement');
    await page.click('#paste-statement-toggle');
    assert.ok(!(await page.locator('#paste-statement').isVisible()), 'statement toggles off');
    await page.click('#paste-statement-toggle');
    log('statement shown in an iframe next to the editor (frame url ' + frame.url() + '), toggle works');

    // Completions: keywords/library names, members after '.', and snippets.
    await select(page, 'language', 'cpp');
    await page.waitForFunction(() => window.__pasteEditor().getModel().getLanguageId() === 'cpp');
    await setCode(page, '#include <bits/stdc++.h>\nusing namespace std;\nint main() {\n    \n}\n');
    await page.evaluate(() => { const e = window.__pasteEditor(); e.setPosition({ lineNumber: 4, column: 5 }); e.focus(); });
    let labels = await suggestions(page, 'vec');
    assert.match(labels, /vector/);
    await page.keyboard.press('Escape');
    await page.keyboard.press('Backspace'); await page.keyboard.press('Backspace'); await page.keyboard.press('Backspace');
    labels = await suggestions(page, 'whi');
    assert.match(labels, /while, Keyword/);
    await page.keyboard.press('Escape');
    await page.keyboard.press('Backspace'); await page.keyboard.press('Backspace'); await page.keyboard.press('Backspace');
    await page.keyboard.type('vec');
    await page.keyboard.type('tor<int> v;\n    ');
    labels = await suggestions(page, 'v.pu');
    assert.match(labels, /push_back/);
    await screenshot(page, '02-member-completion');
    await page.keyboard.press('Escape');
    log('completions: "vec" offers vector, "whi" the keyword while, "v.pu" offers push_back');

    await setCode(page, '');
    await page.evaluate(() => window.__pasteEditor().focus());
    labels = await suggestions(page, 'main');
    assert.match(labels, /main/);
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => window.__pasteEditor().getValue().includes('sync_with_stdio'));
    log('snippet "main" expands to a C++ template');

    // Syntax checking with tree-sitter (WebAssembly, under the production CSP).
    await setCode(page, 'int main() {\n    int x = 1\n    return x;\n}\n');
    await waitMarkers(page, /missing ";"/, 'expected a C++ syntax error');
    const cppMarkers = await syntaxMarkers(page);
    assert.match(cppMarkers.join(), /missing ";"/);
    await page.waitForSelector('.paste-editor-container .squiggly-error');
    await screenshot(page, '03-syntax-error');
    await setCode(page, 'int main() {\n    int x = 1;\n    return x;\n}\n');
    await waitMarkers(page, /^$/, 'expected no C++ syntax errors');
    log('C++ lint: ' + cppMarkers.join('; ') + ' -> cleared after fix');

    await select(page, 'language', 'python3');
    await setCode(page, 'def f(:\n    pass\n');
    await waitMarkers(page, /Syntax error/, 'expected a Python syntax error');
    const pyMarkers = await syntaxMarkers(page);
    await select(page, 'language', 'java');
    await setCode(page, 'public class Main {\n  public static void main(String[] a) {\n    System.out.println("x")\n  }\n}\n');
    await waitMarkers(page, /missing ";"/, 'expected a Java syntax error');
    const javaMarkers = await syntaxMarkers(page);
    log('Python lint: ' + pyMarkers.join('; ') + ' | Java lint: ' + javaMarkers.join('; '));

    // Valid code is not marked: macros from contest templates, C names that are C++ keywords.
    await select(page, 'language', 'cpp');
    await setCode(page, 'int main() { return 0 }\n');
    await waitMarkers(page, /missing ";"/, 'expected a C++ syntax error');
    await setCode(page, '#include <bits/stdc++.h>\n#define rep(i, a, b) for (int i = a; i < (b); ++i)\nusing namespace std;\n' +
        'int main() {\n    int n; cin >> n;\n    vector<int> a(n);\n    rep(i, 0, n) cin >> a[i];\n    rep(i, 0, n) {\n        cout << a[i];\n    }\n}\n');
    await waitMarkers(page, /^$/, 'a template with loop macros must not be marked');
    await select(page, 'language', 'c');
    await setCode(page, 'int main(void) { return 0 }\n');
    await waitMarkers(page, /missing ";"/, 'expected a C syntax error');
    await setCode(page, '#include <stdlib.h>\nstruct node { int v; struct node *next; };\nint main(void) {\n' +
        '    struct node *new = malloc(sizeof *new);\n    new->v = 1;\n    return 0;\n}\n');
    await waitMarkers(page, /^$/, 'C using C++ keywords as names must not be marked');
    log('no false positives for loop macros (C++) or C++ keywords used as names (C)');

    // Drafts per problem survive reloads and problem switches.
    const helloCpp = '#include <iostream>\nint main() {\n    std::cout << "Hello world!" << std::endl;\n    return 0;\n}\n';
    await select(page, 'language', 'cpp');
    await setCode(page, helloCpp);
    await sleep(700);
    await page.reload();
    await editorReady(page);
    await page.waitForFunction(c => window.__pasteEditor().getValue() === c, helloCpp, { timeout: 10000 });
    assert.equal(await page.inputValue('#submit_problem_paste_language'), 'cpp');
    await page.waitForFunction(() => window.__pasteEditor().getModel().getLanguageId() === 'cpp');
    assert.equal(await page.textContent('.paste-derived-filename'), 'A.cpp');
    log('draft and language restored after reload (filename A.cpp)');

    await select(page, 'problem', 'fltcmp');
    assert.equal(await code(page), helloCpp, 'switching to a problem without draft keeps the code');
    assert.equal(await frameEl.getAttribute('src'), '/team/problems/fltcmp/statement#navpanes=0');
    await setCode(page, '// fltcmp draft\n');
    await sleep(700);
    await select(page, 'problem', 'hello');
    assert.equal(await code(page), helloCpp);
    await select(page, 'problem', 'fltcmp');
    assert.equal(await code(page), '// fltcmp draft\n');
    await select(page, 'problem', 'hello');
    log('per-problem drafts: hello and fltcmp keep their own code');

    // Submit in three languages through the editor.
    const before = Math.max(0, ...(await api(`/contests/${CONTEST}/submissions`)).map(s => +s.id));
    const helloJava = 'import java.util.*;\n\npublic class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello world!");\n    }\n}\n';
    const helloPy = 'print("Hello world!")\n';
    const dialogs = [];
    page.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
    for (const [lang, src, file] of [['cpp', helloCpp, 'A.cpp'], ['java', helloJava, 'Main.java'], ['python3', helloPy, 'A.py']]) {
        await page.goto(BASE + '/team/submit');
        await editorReady(page);
        await select(page, 'problem', 'hello');
        await select(page, 'language', lang);
        await setCode(page, src);
        await page.waitForFunction(f => document.querySelector('.paste-derived-filename').textContent === f, file, { timeout: 5000 });
        await Promise.all([page.waitForURL(u => u.pathname === '/team'), page.click('.paste-toolbar-submit button[type=submit]')]);
        assert.match(await page.textContent('.alert-success'), /Submission done/);
        log(`submitted ${lang} as ${file}; confirm dialog: ${JSON.stringify(dialogs.at(-1))}`);
    }
    await screenshot(page, '04-after-submit');

    // The judgehosts compile and run them; check verdicts and stored sources.
    let mine = [];
    for (let i = 0; i < 90; i++) {
        mine = (await api(`/contests/${CONTEST}/submissions`)).filter(s => +s.id > before && s.team_id === 'exteam');
        const judgements = await Promise.all(mine.map(s => api(`/contests/${CONTEST}/judgements?submission_id=${s.id}`)));
        mine.forEach((s, k) => { s.verdict = judgements[k].find(j => j.judgement_type_id)?.judgement_type_id; });
        if (mine.length === 3 && mine.every(s => s.verdict)) break;
        await sleep(2000);
    }
    for (const s of mine) {
        const files = await api(`/contests/${CONTEST}/submissions/${s.id}/source-code`);
        s.files = files.map(f => f.filename);
        s.source = Buffer.from(files[0].source, 'base64').toString();
    }
    console.log(mine.map(s => `    submission ${s.id}: ${s.language_id} ${s.files} -> ${s.verdict}`).join('\n'));
    assert.deepEqual(mine.map(s => [s.language_id, s.files.join(), s.verdict]),
        [['cpp', 'A.cpp', 'AC'], ['java', 'Main.java', 'AC'], ['python3', 'A.py', 'AC']]);
    assert.deepEqual(mine.map(s => s.source), [helloCpp, helloJava, helloPy], 'sources stored byte-identical');
    log('all three editor submissions judged CORRECT, sources byte-identical');

    // Drafts are kept after submitting.
    await page.goto(BASE + '/team/submit');
    await editorReady(page);
    await select(page, 'problem', 'hello');
    assert.equal(await code(page), helloPy);
    log('draft kept after submitting');

    // A syntax error is mentioned when confirming a submission.
    await select(page, 'language', 'cpp');
    await setCode(page, 'int main() { return 0 }\n');
    await waitMarkers(page, /missing ";"/, 'expected a syntax error before submit');
    page.removeAllListeners('dialog');
    const dialogText = new Promise(resolve => page.once('dialog', d => { resolve(d.message()); d.dismiss(); }));
    await page.click('.paste-toolbar-submit button[type=submit]');
    assert.match(await dialogText, /syntax error/);
    log('submit confirmation warns about syntax errors');

    // No team page opens a new window.
    for (const path of ['/team', '/team/scoreboard', '/team/problems', '/team/submit']) {
        await page.goto(BASE + path);
        assert.equal(await page.locator('a[target="_blank"]').count(), 0, `no target=_blank on ${path}`);
    }
    await page.goto(BASE + '/team/scoreboard');
    const headerLinks = await page.$$eval('.scoreheader a', links => links.map(a => a.getAttribute('target')));
    assert.ok(headerLinks.length > 0 && headerLinks.every(t => t === '_self'), 'scoreboard problem links stay in the window: ' + headerLinks);
    // A contest problemset is shown when no problem (or one without statement) is selected.
    const problemset = new FormData();
    problemset.append('problemset', new Blob([fs.readFileSync('/repo/doc/logos/DOMjudgelogo.pdf')], { type: 'application/pdf' }), 'problemset.pdf');
    await api(`/contests/${CONTEST}/problemset`, { method: 'POST', body: problemset });
    try {
        await page.goto(BASE + '/team/submit');
        await editorReady(page);
        await select(page, 'problem', '');
        assert.equal(await frameEl.getAttribute('src'), '/team/problemset#navpanes=0');
        const problemsetFrame = await (await frameEl.elementHandle()).contentFrame();
        await page.waitForFunction(f => f.contentWindow && f.contentWindow.location.href.endsWith('/team/problemset#navpanes=0'),
            await frameEl.elementHandle(), { timeout: 10000 });
        log('contest problemset shown next to the editor (frame url ' + problemsetFrame.url() + ')');
    } finally {
        await api(`/contests/${CONTEST}/problemset`, { method: 'DELETE' });
    }

    assert.deepEqual(popups, [], 'no popups or new windows');
    assert.deepEqual(downloads, [], 'no downloads');
    const realProblems = problems.filter(p => !/favicon/.test(p));
    assert.deepEqual(realProblems, [], 'no console or page errors');
    log('no new windows, downloads, console errors or page errors during the whole flow');
    await context.close();
}

function launchKiosk(profile, port) {
    const child = spawn(KIOSK, [BASE + '/team', '--headless=new', '--no-sandbox', `--remote-debugging-port=${port}`, '--window-size=1600,900'], {
        env: { ...process.env, DJ_KIOSK_BROWSER: CHROME, DJ_KIOSK_PROFILE: profile },
        stdio: ['ignore', 'ignore', 'pipe'],
    });
    return child;
}

async function connectKiosk(port) {
    for (let i = 0; i < 50; i++) {
        try {
            return await chromium.connectOverCDP(`http://127.0.0.1:${port}`);
        } catch (e) {
            await sleep(200);
        }
    }
    throw new Error('kiosk browser did not start');
}

async function kioskFlow() {
    const profile = '/tmp/dj-kiosk-profile';
    fs.rmSync(profile, { recursive: true, force: true });
    const draft = 'words = input().split()\nprint(len(words))  # kiosk draft\n';

    // First boot: the launcher opens DOMjudge and only DOMjudge is reachable.
    let child = launchKiosk(profile, 9222);
    let browser = await connectKiosk(9222);
    let page = browser.contexts()[0].pages()[0];
    await page.waitForLoadState();
    await page.waitForURL(/\/login/);
    log('dj_kiosk opened ' + page.url());
    const blocked = await page.goto('http://example.com/').then(() => 'loaded', e => e.message.split('\n')[0]);
    assert.match(blocked, /ERR_NAME_NOT_RESOLVED/);
    log('other hosts are blocked: ' + blocked);
    await page.waitForURL(/^chrome-error:/).catch(() => {});  // let the error page commit first
    await login(page);
    await page.goto(BASE + '/team/submit');
    await page.waitForFunction(() => window.monaco && monaco.editor.getEditors().length);
    await page.selectOption('#submit_problem_paste_problem', 'jumble');
    await page.selectOption('#submit_problem_paste_language', 'python3');
    await page.evaluate(v => monaco.editor.getEditors()[0].setValue(v), draft);
    await sleep(800);
    await screenshot(page, '05-kiosk');
    // Close the browser like a user would, so it writes its storage to the profile.
    const exited = new Promise(r => child.once('exit', r));
    await (await browser.newBrowserCDPSession()).send('Browser.close').catch(() => {});
    await exited;

    // Second boot with the same profile: the draft is still there.
    child = launchKiosk(profile, 9223);
    browser = await connectKiosk(9223);
    page = browser.contexts()[0].pages()[0];
    await page.waitForLoadState();
    if (page.url().includes('/login')) {
        await login(page);
    }
    await page.goto(BASE + '/team/submit');
    await page.waitForFunction(() => window.monaco && monaco.editor.getEditors().length);
    await page.selectOption('#submit_problem_paste_problem', 'jumble');
    await page.waitForFunction(v => monaco.editor.getEditors()[0].getValue() === v, draft, { timeout: 10000 });
    assert.equal(await page.inputValue('#submit_problem_paste_language'), 'python3');
    log('after restarting the kiosk browser the jumble draft (python3) is restored');
    await browser.close().catch(() => {});
    child.kill();
}

(async () => {
    const browser = await chromium.launch({ channel: 'chromium' });
    try {
        await editorFlow(browser);
    } finally {
        await browser.close();
    }
    await kioskFlow();
    console.log('ALL E2E CHECKS PASSED');
})().catch(e => {
    console.error('E2E FAILED:', e);
    process.exit(1);
});
