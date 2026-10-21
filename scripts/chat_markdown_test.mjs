/**
 * The chat widget's reply renderer, tested against the real shipped file.
 *
 * Two things have to hold. The formatting the system prompt permits must
 * actually render — before this, `el.textContent = text` put literal
 * asterisks and hyphens on screen while the prompt told the model to use
 * bold and bullet lists. And model output must never become markup: the
 * reply is a string from an external service that an attacker may be able
 * to influence through what they type, so every piece of text is set with
 * textContent and nothing reaches innerHTML.
 *
 * The functions are lifted out of the shipped widget rather than copied, so
 * this cannot pass against a stale duplicate. A minimal DOM shim stands in
 * for the browser; it records whether text was set as text or as markup.
 */
import { readFileSync } from 'node:fs';

const SOURCE = 'public/dashboard/js/chat-widget.js';
const src = readFileSync(SOURCE, 'utf8');

let passed = 0;
let failed = 0;

function check(label, ok, detail = '') {
    if (ok) { passed++; console.log(`  PASS  ${label}`); }
    else { failed++; console.log(`  FAIL  ${label}${detail ? `  (${detail})` : ''}`); }
}

function section(title) {
    console.log(`\n${title}\n${'-'.repeat(title.length)}`);
}

/* ---- a DOM just large enough, which refuses to parse markup -------------- */

class Node_ {
    constructor(tag) {
        this.tagName = tag ? tag.toUpperCase() : null;
        this.childNodes = [];
        this._text = '';
    }

    appendChild(child) { this.childNodes.push(child); return child; }

    set textContent(value) { this._text = String(value); this.childNodes = []; }
    get textContent() {
        if (this.childNodes.length) {
            return this.childNodes.map(c => c.textContent).join('');
        }
        return this._text;
    }

    // Deliberately absent: innerHTML. Anything that tries to use it throws,
    // which is the point — the renderer must never reach for it.
    set innerHTML(v) { throw new Error('renderer used innerHTML with: ' + v); }

    /** A readable tree, for assertions. */
    outline() {
        if (this.tagName === null) { return JSON.stringify(this._text); }
        const inner = this.childNodes.length
            ? this.childNodes.map(c => c.outline()).join('')
            : JSON.stringify(this._text);
        return `<${this.tagName.toLowerCase()}>${inner}</${this.tagName.toLowerCase()}>`;
    }

    tags() {
        const out = this.tagName ? [this.tagName] : [];
        this.childNodes.forEach(c => out.push(...c.tags()));
        return out;
    }
}

class TextNode extends Node_ {
    constructor(text) { super(null); this._text = String(text); }
    outline() { return JSON.stringify(this._text); }
    tags() { return []; }
}

const document = {
    createElement: tag => new Node_(tag),
    createTextNode: text => new TextNode(text),
};

/* ---- lift the two functions out of the shipped file --------------------- */

function extract(name) {
    const start = src.indexOf(`function ${name}(`);
    if (start < 0) { throw new Error(`${name}() not found in ${SOURCE}`); }

    // Walk braces from the first { after the signature.
    let i = src.indexOf('{', start);
    let depth = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') { depth++; }
        else if (src[i] === '}') { depth--; if (depth === 0) { return src.slice(start, i + 1); } }
    }
    throw new Error(`${name}() is unbalanced`);
}

const t = () => 'Open';
const factory = new Function('document', 't',
    `${extract('appendInline')}\n${extract('renderRich')}\nreturn { appendInline, renderRich };`);
const { renderRich } = factory(document, t);

function render(text) {
    const el = new Node_('div');
    renderRich(el, text);
    return el;
}

console.log(`renderer lifted from ${SOURCE}\n`);

/* ---------------------------------------------------------------------- */
section('The formatting the system prompt permits actually renders');

let out = render('Your balance is **150,000 FCFA**.');
check('bold becomes <strong>', out.tags().includes('STRONG'), out.outline());
check('and the asterisks are gone', !out.textContent.includes('**'), out.textContent);
check('the figure survives intact', out.textContent.includes('150,000 FCFA'));

out = render('You owe:\n- First instalment\n- Second instalment');
check('a dash list becomes <ul> with <li> items',
    out.tags().includes('UL') && out.tags().filter(x => x === 'LI').length === 2, out.outline());
check('the bullet characters are gone', !out.textContent.includes('- First'), out.textContent);

out = render('Steps:\n1. Pay the fee\n2. Collect the receipt');
check('a numbered list becomes <ol>',
    out.tags().includes('OL') && out.tags().filter(x => x === 'LI').length === 2, out.outline());

out = render('Use `my_fees` for that.');
check('backticks become <code>', out.tags().includes('CODE'), out.outline());

out = render('First paragraph.\n\nSecond paragraph.');
check('a blank line starts a new paragraph',
    out.tags().filter(x => x === 'P').length === 2, out.outline());

out = render('Line one\nLine two');
check('a single newline stays in one paragraph, with a break',
    out.tags().filter(x => x === 'P').length === 1 && out.tags().includes('BR'), out.outline());

out = render('*Soon* is not a date.');
check('single asterisks become <em>', out.tags().includes('EM'), out.outline());

/* ---------------------------------------------------------------------- */
section('Model output can never become markup');

const hostile = [
    ['a script tag', '<script>alert(1)</script>'],
    ['an image with an onerror handler', '<img src=x onerror="alert(1)">'],
    ['an anchor with a javascript: href', '<a href="javascript:alert(1)">click</a>'],
    ['an iframe', '<iframe src="https://evil.example"></iframe>'],
    ['a bold tag spelled as HTML', '<b>not bold</b>'],
    ['markup smuggled inside a bullet', '- <script>alert(1)</script>'],
    ['markup smuggled inside bold', '**<img src=x onerror=alert(1)>**'],
    ['an event attribute on its own', '" onmouseover="alert(1)'],
];

for (const [label, payload] of hostile) {
    let el;
    let threw = null;

    try {
        el = render(payload);
    } catch (e) {
        threw = e.message;
    }

    if (threw) {
        check(`${label} is refused safely`, false, threw);
        continue;
    }

    const tags = el.tags();
    const dangerous = tags.filter(x => ['SCRIPT', 'IMG', 'IFRAME', 'A', 'B', 'OBJECT', 'EMBED'].includes(x));

    check(`${label} produces no such element`, dangerous.length === 0,
        `created ${dangerous.join(', ')}`);

    // The characters must still be visible to the reader, as text.
    const marker = payload.replace(/^-\s*/, '').replace(/^\*\*|\*\*$/g, '');
    check(`${label} is shown to the reader as text`,
        el.textContent.includes(marker.slice(0, 18)), el.textContent.slice(0, 60));
}

/* ---------------------------------------------------------------------- */
section('Odd replies do not produce an empty bubble');

for (const [label, payload] of [
    ['an empty string', ''],
    ['only whitespace', '   \n  \n '],
    ['only a newline', '\n'],
    ['a lone asterisk', '*'],
    ['an unclosed bold', '**unfinished'],
    ['a very long single word', 'x'.repeat(2000)],
]) {
    const el = render(payload);
    check(`${label} still renders something`, el.childNodes.length > 0, el.outline().slice(0, 50));
}

/* ---------------------------------------------------------------------- */
section('The shipped file keeps its safety properties');

check('the widget never assigns model text to innerHTML',
    !/innerHTML\s*=\s*(?!'')/.test(src.replace("log.innerHTML = '';", '')),
    'found an innerHTML assignment other than clearing our own log');
check('replies are rendered through renderRich', src.includes('renderRich(el, text)'));
check("the person's own words use textContent", src.includes('p.textContent = text'));
check('the degraded flag reaches the UI', src.includes('degraded: !!data.degraded'));
check('a hung request is aborted', src.includes('AbortController') && src.includes('AbortError'));
check('focus returns to the launcher on close', src.includes('toggle.focus()'));
check('the launcher reports its state', src.includes("aria-expanded"));

/* ---------------------------------------------------------------------- */
section("The widget holds its own look inside somebody else's page");

const css = readFileSync('public/dashboard/css/chat-widget.css', 'utf8');

// Measured, not assumed: with a plain "p { color: #5b6977 }" on the host
// page, the computed colour inside a reply bubble came back as the host's.
// The renderer emits real <p>, <ul> and <li>, so the host's typography
// applied to every answer — and to the user's own white-on-navy bubble.
check('reply paragraphs state their own colour', css.includes('#pax-chat .pax-msg p'));
check('list items state their own colour', css.includes('#pax-chat .pax-msg li'));
check('the defence is at id-strength, so an ordinary host rule cannot win',
    (css.match(/#pax-chat \.pax-msg/g) || []).length >= 8);
check('lists keep their markers even if the host removed them',
    css.includes('list-style: disc outside') && css.includes('display: list-item'));

// The bubble itself must NOT be in the reset: "background: none" there
// out-specifies .pax-msg-user and leaves white text on a white bubble.
const resetStart = css.indexOf('#pax-chat .pax-msg p,');
const resetBlock = css.slice(resetStart, css.indexOf('float: none;', resetStart));
check('the bubble itself is excluded from the background reset',
    resetStart > -1 && !/^#pax-chat \.pax-msg,$/m.test(resetBlock),
    'the reset would blank the bubble background');
check('each speaker still gets its own bubble colour',
    css.includes('#pax-chat .pax-msg-user,') && css.includes('#pax-chat .pax-msg-assistant,'));

// Dark mode follows the page the widget is sitting on, not the viewer's OS.
check('dark mode is not keyed to the operating system',
    !css.includes('prefers-color-scheme'),
    'an OS-dark viewer would get a dark widget on a light portal');
check('dark mode is keyed to the host page instead',
    css.includes('[data-scheme="dark"]') && css.includes('html[data-theme="dark"]'));
check('the script decides the scheme from the host background',
    src.includes('effectiveBackground') && src.includes('data-scheme'));
check('and follows the host if it switches theme later', src.includes('MutationObserver'));

// The waiting state. The dots are spans inside a bubble, and the typography
// reset that stops a host page restyling a reply carries
// "#pax-chat .pax-msg span { background: none }" — which blanked them too,
// leaving an empty white box where the answer was being prepared.
check('the typing dots out-specify the typography reset',
    css.includes('#pax-chat .pax-typing span'),
    'the reset would blank them');
check('the dots are given a colour of their own',
    /#pax-chat \.pax-typing span \{[^}]*background:/.test(css));
check('the waiting state says what it is doing in words',
    src.includes('pax-thinking-label') && css.includes('.pax-thinking'));
check('and the wording escalates so a long wait does not read as a hang',
    src.includes("'Looking that up") && src.includes("'Still working"));
check('its timers are cleared when it is removed', src.includes('timers.forEach(clearTimeout)'));
check('it is announced to a screen reader once',
    src.includes("setAttribute('role', 'status')"));
// Sliced out of the block itself rather than matched within a fixed
// distance, which a comment pushed the rule beyond.
const reducedBlock = (() => {
    const at = css.indexOf('prefers-reduced-motion');
    return at < 0 ? '' : css.slice(at, css.indexOf('\n}', at));
})();

check('the dots stay legible when motion is reduced',
    reducedBlock.includes('#pax-chat .pax-typing span') && reducedBlock.includes('opacity'),
    'the reduced-motion block does not keep the dots visible');
check('the starter chips clear once a question is asked',
    src.includes('clearStarters()') && src.includes("querySelector('.pax-starters')"));

check('a short thread sits on the composer', css.includes('#pax-chat-log::before'));
check('phones get a height the keyboard respects', css.includes('dvh'));
check('reduced motion is honoured', css.includes('prefers-reduced-motion'));
check('the institutional navy is used, not the Bootstrap default',
    css.includes('#1e3a5f') && !/--pc-accent:\s*#0d6efd/.test(css));

console.log('\n' + '='.repeat(60));
console.log(`passed ${passed}, failed ${failed}`);
process.exit(failed > 0 ? 1 : 0);
