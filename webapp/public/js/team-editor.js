// Team code editor (the "paste" submit method): per-problem drafts, the problem
// statement next to the editor, completions and syntax checking. Everything runs
// in the browser, so this also works on locked-down (kiosk) team machines.
'use strict';

// Snippets, library names and members (offered after '.' or '->') per Monaco language.
// Keywords come from Monaco's own syntax definitions.
const EDITOR_LIBRARY = (() => {
    const forLoop = 'for (int ${1:i} = 0; ${1:i} < ${2:n}; ${1:i}++) {\n\t$0\n}';
    const cMain = '#include <stdio.h>\n\nint main(void) {\n\t$0\n\treturn 0;\n}\n';
    const cNames = 'printf scanf puts getchar putchar fgets malloc calloc realloc free memset memcpy strlen strcmp strncmp strcpy strcat qsort abs llabs sqrt pow floor ceil INT_MAX INT_MIN LLONG_MAX';
    return {
        c: {
            snippets: {main: cMain, for: forLoop},
            names: cNames,
        },
        cpp: {
            snippets: {
                main: '#include <bits/stdc++.h>\nusing namespace std;\n\nint main() {\n\tios::sync_with_stdio(false);\n\tcin.tie(nullptr);\n\t$0\n\treturn 0;\n}\n',
                for: forLoop,
            },
            names: cNames + ' std vector string map set multiset multimap unordered_map unordered_set pair tuple array deque list queue priority_queue stack bitset greater less cin cout cerr endl getline ios sync_with_stdio sort stable_sort reverse unique lower_bound upper_bound binary_search min max min_element max_element swap fill iota accumulate partial_sum count find next_permutation prev_permutation make_pair make_tuple tie to_string stoi stol stoll stod gcd lcm numeric_limits nullptr size_t int64_t uint64_t',
            members: 'push_back emplace_back pop_back push emplace pop top front back size empty clear begin end rbegin rend insert erase find count lower_bound upper_bound resize reserve assign substr length first second at data c_str append replace tie precision',
        },
        java: {
            snippets: {
                main: 'import java.io.*;\nimport java.util.*;\n\npublic class ${1:Main} {\n\tpublic static void main(String[] args) throws IOException {\n\t\tBufferedReader in = new BufferedReader(new InputStreamReader(System.in));\n\t\t$0\n\t}\n}\n',
                for: forLoop,
                sout: 'System.out.println($0);',
            },
            names: 'String System Scanner BufferedReader InputStreamReader PrintWriter BufferedWriter OutputStreamWriter StringBuilder StringTokenizer IOException Integer Long Double Character Math Arrays Collections List ArrayList LinkedList Map HashMap TreeMap Set HashSet TreeSet Deque ArrayDeque Queue PriorityQueue Iterator Comparator',
            members: 'out in err println print printf flush close nextInt nextLong nextDouble next nextLine hasNext hasNextInt readLine nextToken hasMoreTokens add addAll get set remove size isEmpty contains containsKey getOrDefault put putIfAbsent merge keySet values entrySet getKey getValue push pop peek poll offer addFirst addLast pollFirst pollLast peekFirst peekLast firstKey lastKey floorKey ceilingKey length charAt substring indexOf equals compareTo split trim toCharArray toString append reverse sort stream parseInt parseLong parseDouble valueOf max min abs sqrt pow fill asList',
        },
        kotlin: {
            snippets: {main: 'fun main() {\n\t$0\n}\n', for: 'for (${1:i} in 0 until ${2:n}) {\n\t$0\n}'},
            names: 'readln readLine readlnOrNull println print listOf mutableListOf arrayOf IntArray LongArray Array mapOf mutableMapOf setOf mutableSetOf HashMap ArrayDeque maxOf minOf repeat until downTo step',
            members: 'split map toInt toLong toDouble trim size add remove get set contains forEach filter sorted sortedBy sum count first last joinToString indices reversed',
        },
        python: {
            snippets: {main: 'import sys\n\n\ndef main():\n\t$0\n\n\nmain()\n', for: 'for ${1:i} in range(${2:n}):\n\t$0'},
            names: 'sys stdin stdout math collections deque defaultdict Counter heapq heappush heappop bisect bisect_left bisect_right itertools permutations combinations product accumulate functools lru_cache reduce',
            members: 'append extend insert pop remove index count sort reverse copy clear split strip lstrip rstrip join replace find startswith endswith upper lower isdigit isalpha format items keys values get setdefault update add discard readline readlines read write stdin stdout appendleft popleft sqrt floor ceil gcd inf',
        },
    };
})();

// Monaco language => tree-sitter grammar in js/tree-sitter (from @vscode/tree-sitter-wasm).
// C is checked with the C++ grammar, which accepts all C code in our test corpus.
const EDITOR_GRAMMARS = {
    c: 'cpp', cpp: 'cpp', csharp: 'c-sharp', go: 'go', java: 'java',
    python: 'python', ruby: 'ruby', rust: 'rust', shell: 'bash',
};
// C++ keywords that are valid names in C, e.g. a variable "new".
const CXX_ONLY_KEYWORDS = 'alignas alignof and and_eq bitand bitor catch class compl concept const_cast consteval constexpr constinit co_await co_return co_yield decltype delete dynamic_cast explicit export friend mutable namespace new noexcept not not_eq nullptr operator or or_eq private protected public reinterpret_cast requires static_assert static_cast template this throw try typeid typename using virtual xor xor_eq';

const editorStorage = {
    get(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            // Storage is disabled or full; the editor still works without drafts.
        }
    },
};

$(function () {
    const form = document.querySelector('form[name=submit_problem_paste]');
    if (!form) {
        return;
    }
    const problem = document.getElementById('submit_problem_paste_problem');
    const language = document.getElementById('submit_problem_paste_language');

    initStatementPanel(problem);

    require(['vs/editor/editor.main'], function () {
        // The codeEditor Twig filter creates the editor, normally before we get here.
        let started = false;
        const start = () => {
            const editor = monaco.editor.getEditors().find(
                e => e.getModel() && e.getDomNode().closest('.paste-editor-container'));
            if (editor && !started) {
                started = true;
                initEditor(editor, form, problem, language);
            }
        };
        start();
        // The filter sets its model right after creating the editor.
        monaco.editor.onDidCreateEditor(() => setTimeout(start));
    });
});

function initStatementPanel(problem) {
    const panel = document.getElementById('paste-statement');
    const toggle = document.getElementById('paste-statement-toggle');
    const frame = panel.querySelector('iframe');
    const urls = Object.fromEntries(JSON.parse(panel.dataset.statementUrls));

    const update = () => {
        const url = urls[problem.value];
        panel.classList.toggle('has-statement', !!url);
        // Load lazily and do not reload (and lose the scroll position) on every toggle.
        if (url && !panel.hidden && frame.getAttribute('src') !== url) {
            frame.setAttribute('src', url);
        }
    };
    const show = (visible) => {
        panel.hidden = !visible;
        toggle.classList.toggle('active', visible);
        toggle.setAttribute('aria-expanded', visible);
        editorStorage.set('domjudge_statement_panel', visible ? '1' : '0');
        update();
    };

    toggle.addEventListener('click', () => show(panel.hidden));
    $(problem).on('change', update);
    show(editorStorage.get('domjudge_statement_panel') !== '0');
}

function initEditor(editor, form, problem, language) {
    const model = editor.getModel();
    initDrafts(editor, form, problem, language);
    initRun(editor, language);

    let javaClass = javaPublicClass(model.getValue());
    model.onDidChangeContent($.debounce(() => {
        lintModel(model);
        // The Java filename follows the public class, see updatePasteDerivedFilename().
        const cls = javaPublicClass(model.getValue());
        if (cls !== javaClass) {
            javaClass = cls;
            updatePasteDerivedFilename('submit_problem_paste');
        }
    }, 300));
    model.onDidChangeLanguage(() => {
        registerCompletions(model.getLanguageId());
        lintModel(model);
    });
    registerCompletions(model.getLanguageId());
    lintModel(model);
}

// Run the code with the given input on the Piston server, see SubmissionController::runAction().
function initRun(editor, language) {
    const button = document.getElementById('paste-run-button');
    if (!button) {
        return;
    }
    const input = document.getElementById('paste-run-input');
    const status = document.getElementById('paste-run-status');
    const output = document.getElementById('paste-run-output');

    button.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = 'Running…';
        output.textContent = '';
        try {
            // Unlike FormData, this keeps the line endings.
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                body: new URLSearchParams({language: language.value, code: editor.getValue(), input: input.value}),
            });
            [status.textContent, output.textContent] = runResult(await response.json());
        } catch (e) {
            status.textContent = 'Running the code failed.';
        }
        button.disabled = false;
    });
}

// The status line and output of a run, from Piston's response.
function runResult(result) {
    if (result.error) {
        return [result.error, ''];
    }
    const {compile, run} = result;
    const compiler = `${result.language} ${result.version}, the judge may differ`;
    // When compiling fails, Piston repeats its output as the run.
    if (compile && compile.code !== 0) {
        return [`${compile.status === 'TO' ? 'Compiling took too long' : 'Compilation failed'} (${compiler})`, compile.output];
    }
    const message = run.message || `Finished in ${(run.wall_time / 1000).toFixed(2)} s`;
    return [`${message} (${compiler})`, run.output];
}

// Keep a draft per problem in the browser, so code survives reloads, logouts,
// session timeouts, submitting and switching problems.
function initDrafts(editor, form, problem, language) {
    const prefix = form.dataset.draftKey;
    let current = problem.value;

    let unsaved = false;
    const save = () => {
        unsaved = false;
        editorStorage.set(prefix + current, JSON.stringify({code: editor.getValue(), language: language.value}));
    };
    const saveSoon = $.debounce(save, 300);
    const restore = () => {
        let draft = null;
        try {
            draft = JSON.parse(editorStorage.get(prefix + current));
        } catch (e) {
            // Ignore a corrupt draft.
        }
        // Switching to a problem without a draft keeps the code, so code written
        // before selecting a problem is not lost.
        if (draft) {
            editor.setValue(draft.code);
            if ([...language.options].some(o => o.value === draft.language)) {
                language.value = draft.language;
            }
        }
        // Also (re)derives the filename and syntax highlighting, which could not
        // be done on page load since Monaco was not loaded yet.
        $(language).trigger('change');
    };

    restore();
    editor.onDidChangeModelContent(() => {
        unsaved = true;
        saveSoon();
    });
    $(language).on('change', save);
    $(problem).on('change', () => {
        save();
        current = problem.value;
        restore();
    });
    // Only flush pending changes, so closing a stale second tab keeps the newer draft.
    window.addEventListener('pagehide', () => unsaved && save());
}

const registeredCompletions = new Set();

function registerCompletions(languageId) {
    if (registeredCompletions.has(languageId)) {
        return;
    }
    registeredCompletions.add(languageId);

    const library = EDITOR_LIBRARY[languageId] || {};
    const words = s => (s || '').split(' ').filter(w => w);
    const names = words(library.names);
    const members = words(library.members);
    let keywords = [];
    const info = monaco.languages.getLanguages().find(l => l.id === languageId);
    if (info && info.loader) {
        info.loader().then(def => {
            keywords = ['keywords', 'typeKeywords', 'builtins', 'constants']
                .flatMap(k => def.language[k] || [])
                .filter(k => /^[A-Za-z_]\w*$/.test(k));
        }).catch(() => {});
    }

    monaco.languages.registerCompletionItemProvider(languageId, {
        triggerCharacters: ['.'],
        provideCompletionItems(model, position) {
            const word = model.getWordUntilPosition(position);
            const range = new monaco.Range(position.lineNumber, word.startColumn, position.lineNumber, word.endColumn);
            const before = model.getLineContent(position.lineNumber).slice(0, word.startColumn - 1);
            const Kind = monaco.languages.CompletionItemKind;
            const suggestions = [];
            const seen = new Set();
            const add = (label, kind) => {
                if (!seen.has(label)) {
                    seen.add(label);
                    suggestions.push({label, kind, insertText: label, range});
                }
            };

            if (/(\.|->)\s*$/.test(before)) {
                members.forEach(m => add(m, Kind.Method));
            } else if (/::\s*$/.test(before)) {
                names.forEach(n => add(n, Kind.Function));
            } else {
                for (const [label, body] of Object.entries(library.snippets || {})) {
                    suggestions.push({
                        label, kind: Kind.Snippet, detail: 'snippet', insertText: body, range,
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                    });
                }
                keywords.forEach(k => add(k, Kind.Keyword));
                names.forEach(n => add(n, Kind.Function));
            }
            // Monaco drops its word based suggestions once a provider answers, so add them here.
            for (const w of model.getValue().match(/[A-Za-z_]\w+/g) || []) {
                if (w !== word.word) {
                    add(w, Kind.Text);
                }
            }
            return {suggestions};
        },
    });
}

let treeSitter = null;
const treeSitterParsers = {};

function getParser(languageId) {
    const grammar = EDITOR_GRAMMARS[languageId];
    if (!grammar) {
        return Promise.resolve(null);
    }
    const base = require.getConfig().paths['tree-sitter'] + '/';
    treeSitter = treeSitter || new Promise((resolve, reject) => require(['tree-sitter/tree-sitter'], resolve, reject))
        .then(ts => ts.Parser.init({locateFile: file => base + file}).then(() => ts));
    treeSitterParsers[grammar] = treeSitterParsers[grammar] || treeSitter.then(async ts => {
        const parser = new ts.Parser();
        parser.setLanguage(await ts.Language.load(`${base}tree-sitter-${grammar}.wasm`));
        return parser;
    });
    return treeSitterParsers[grammar];
}

async function lintModel(model) {
    const languageId = model.getLanguageId();
    const parser = await getParser(languageId).catch(() => null);
    if (model.isDisposed() || model.getLanguageId() !== languageId) {
        return;
    }
    const markers = [];
    if (parser) {
        const tree = parser.parse(model.getValue());
        collectSyntaxErrors(tree.rootNode, model, markers);
        tree.delete();
    }
    const ignore = ignoredSyntaxLines(model);
    monaco.editor.setModelMarkers(model, 'syntax',
        ignore ? markers.filter(m => !ignore.test(model.getLineContent(m.startLineNumber))) : markers);
}

// Tree-sitter does not run the preprocessor and checks C with the C++ grammar, so
// ignore errors on lines that use a macro (e.g. "rep(i, 0, n) {") or, in C, a C++ keyword.
function ignoredSyntaxLines(model) {
    const languageId = model.getLanguageId();
    if (languageId !== 'c' && languageId !== 'cpp') {
        return null;
    }
    const words = [...model.getValue().matchAll(/^\s*#\s*define\s+(\w+)/gm)].map(m => m[1]);
    if (languageId === 'c') {
        words.push(...CXX_ONLY_KEYWORDS.split(' '));
    }
    return words.length ? new RegExp(`\\b(?:${words.join('|')})\\b`) : null;
}

function collectSyntaxErrors(node, model, markers) {
    if (markers.length >= 25) {
        return;
    }
    if (node.isMissing || node.isError) {
        markers.push(syntaxErrorMarker(node, model));
    } else if (node.hasError) {
        node.children.forEach(child => collectSyntaxErrors(child, model, markers));
    }
}

// Tree-sitter positions are 0-based UTF-16 offsets, Monaco's are 1-based.
function syntaxErrorMarker(node, model) {
    const line = node.startPosition.row + 1;
    let startColumn = node.startPosition.column + 1;
    let endColumn = node.endPosition.column + 1;
    let message;
    if (node.isMissing) {
        message = `Syntax error: missing "${node.type}"`;
        // Underline the character just before the missing token.
        startColumn = Math.max(1, startColumn - 1);
        endColumn = startColumn + 1;
    } else {
        const text = node.text.trim().split('\n')[0];
        message = text ? `Syntax error near "${text.length > 30 ? text.slice(0, 30) + '…' : text}"` : 'Syntax error';
        if (node.endPosition.row !== node.startPosition.row) {
            endColumn = model.getLineMaxColumn(line);
        }
    }
    return {
        severity: monaco.MarkerSeverity.Error,
        message,
        startLineNumber: line,
        startColumn,
        endLineNumber: line,
        endColumn: Math.max(endColumn, startColumn + 1),
    };
}
