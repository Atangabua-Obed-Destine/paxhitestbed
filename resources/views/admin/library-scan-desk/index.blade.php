@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ __('Library scan desk') }}</h4>
                <p class="eq-sub">{{ __('Scan the borrower\'s ID card and the book\'s barcode to issue it. Scan a book that is out to take it back.') }}</p>
            </div>
            <div class="eq-head-side">
                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.issue-return.index') }}">{{ __('Issue and return list') }}</a>
            </div>
        </div>

        <div class="eq-desk">
            <section class="eq-card" aria-label="{{ __('Scanner') }}">
                <div id="eq-reader" class="eq-reader" hidden></div>
                <button type="button" id="eq-camera" class="eq-btn eq-btn-ghost eq-btn-wide"><i class="fas fa-camera"></i> <span>{{ __('Use the camera') }}</span></button>
                <label for="eq-code" class="eq-lbl">{{ __('Or scan with the handheld scanner, or type the code') }}</label>
                <form id="eq-scan-form" class="eq-scanform" autocomplete="off">
                    <input id="eq-code" class="eq-input scan" type="text" autocomplete="off" spellcheck="false" placeholder="{{ __('Card or book') }}" autofocus>
                    <button type="submit" class="eq-btn eq-btn-primary">{{ __('Look up') }}</button>
                </form>
                <p class="eq-hint">{{ __('A book\'s barcode is its ISBN, the same on every copy: the desk knows the title, not the copy.') }}</p>
                <div id="eq-flash" class="eq-flash" role="status" aria-live="polite"></div>
                <button type="button" id="eq-reset" class="eq-btn eq-btn-ghost eq-btn-sm" style="margin-top:12px">{{ __('Next borrower') }}</button>
            </section>

            <section class="eq-card eq-slip" aria-live="polite">
                <div id="eq-person" class="eq-ent"></div>
                <div id="eq-book" class="eq-ent"></div>
                <div id="eq-action" class="eq-slip"></div>
            </section>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
@php
    $deskBase = request()->getBaseUrl();
    $deskUrls = [
        'scan' => $deskBase . route('admin.library.scan-desk.scan', [], false),
        'issue' => $deskBase . route('admin.library.scan-desk.issue', [], false),
        'ret' => $deskBase . route('admin.library.scan-desk.return', [], false),
    ];
@endphp
@php
    $deskText = [
        'scanPerson' => __('Scan the borrower\'s ID card'),
        'scanPersonSub' => __('Staff or student. Not a member yet? They become one when you issue.'),
        'scanBook' => __('Scan the book\'s barcode'),
        'scanBookSub' => __('The label the library prints, or the ISBN on the cover.'),
        'member' => __('Library ID :id'),
        'notMember' => __('Not a library member yet: becomes one on this issue'),
        'holds' => __('Has out:'),
        'nothingOut' => __('Has no books out'),
        'overdue' => __('overdue'),
        'onShelf' => __(':n of :q copies on the shelf'),
        'noneOnShelf' => __('All copies are out'),
        'outWith' => __('Out with :name, due :due'),
        'issue' => __('Issue to :name'),
        'issueAnother' => __('Issue another copy to :name'),
        'returnIt' => __('Return it'),
        'returnFrom' => __('Take it back from :name'),
        'dueLabel' => __('Due back'),
        'whichOne' => __('More than one person has this title out. Who is returning it? (Or scan their card.)'),
        'scanCardToIssue' => __('Scan the borrower\'s card to issue this book.'),
        'scanBookNext' => __('Scan the book to issue it, or a book they are returning.'),
        'camOn' => __('Use the camera'),
        'camOff' => __('Stop the camera'),
        'camFail' => __('The camera could not start. Allow camera access, or use the handheld scanner.'),
        'netFail' => __('The desk could not reach the server. Check the connection and scan again.'),
        'inactive' => __('No longer active'),
        'unavailable' => __('Not available for lending'),
    ];
@endphp
<script>
{
    const URLS = @json($deskUrls);
    const TOKEN = @json(csrf_token());
    const DUE = @json($dueDate);
    const T = @json($deskText);

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fill = (s, v) => String(s).replace(/:(\w+)/g, (m, k) => (k in v ? v[k] : m));
    const state = { person: null, book: null };

    let audio = null;
    const beep = ok => { try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); const o = audio.createOscillator(), g = audio.createGain(); o.frequency.value = ok ? 880 : 220; g.gain.value = 0.06; o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? 0.08 : 0.25)); } catch (e) {} };
    const flash = (msg, ok) => { const f = $('eq-flash'); f.textContent = msg; f.className = 'eq-flash ' + (ok ? 'ok' : 'no'); };
    const avatar = p => p.photo ? '<span class="eq-av"><img src="' + esc(p.photo) + '" alt=""></span>' : '<span class="eq-av">' + esc(p.initials) + '</span>';
    const post = async (url, data) => {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(data) });
        let body = {};
        try { body = await res.json(); } catch (e) {}
        if (!res.ok && !body.message) body.message = body.errors ? Object.values(body.errors).flat().join(' ') : T.netFail;
        return { ok: res.ok && body.ok !== false, body };
    };

    function renderPerson() {
        const p = state.person, el = $('eq-person');
        if (!p) { el.innerHTML = '<span class="eq-av wait"><i class="fas fa-id-card"></i></span><div><span class="eq-name">' + esc(T.scanPerson) + '</span><span class="eq-meta">' + esc(T.scanPersonSub) + '</span></div>'; return; }
        const pills = [];
        if (!p.active) pills.push('<span class="eq-pill bad">' + esc(T.inactive) + '</span>');
        pills.push(p.is_member ? '<span class="eq-pill">' + esc(fill(T.member, { id: p.library_id })) + '</span>' : '<span class="eq-pill acc">' + esc(T.notMember) + '</span>');
        if (!p.holding.length) pills.push('<span class="eq-pill good">' + esc(T.nothingOut) + '</span>');
        const holding = p.holding.length ? '<ul class="eq-holding"><li><b>' + esc(T.holds) + '</b></li>' + p.holding.map(h => '<li>' + esc(h.title) + ' (' + esc(h.due) + ')' + (h.overdue ? ' <span class="eq-pill bad">' + esc(T.overdue) + '</span>' : '') + '</li>').join('') + '</ul>' : '';
        const stops = (p.blockers || []).map(b => '<div class="eq-stop">' + esc(b) + '</div>').join('');
        el.innerHTML = avatar(p) + '<div><span class="eq-name">' + esc(p.name) + '</span><span class="eq-meta">' + esc(p.role) + (p.code ? ', ' + esc(p.code) : '') + '</span><div class="eq-pills">' + pills.join('') + '</div>' + holding + stops + '</div>';
    }

    function renderBook() {
        const b = state.book, el = $('eq-book');
        if (!b) { el.innerHTML = '<span class="eq-av wait"><i class="fas fa-book"></i></span><div><span class="eq-name">' + esc(T.scanBook) + '</span><span class="eq-meta">' + esc(T.scanBookSub) + '</span></div>'; return; }
        const pills = [];
        if (!b.available) pills.push('<span class="eq-pill warn">' + esc(T.unavailable) + '</span>');
        pills.push(b.on_shelf > 0 ? '<span class="eq-pill good">' + esc(fill(T.onShelf, { n: b.on_shelf, q: b.quantity })) + '</span>' : '<span class="eq-pill bad">' + esc(T.noneOnShelf) + '</span>');
        const meta = [b.author, b.shelf, b.isbn ? 'ISBN ' + b.isbn : ''].filter(Boolean).map(esc).join(' · ');
        el.innerHTML = '<span class="eq-av book"><i class="fas fa-book"></i></span><div><span class="eq-name">' + esc(b.title) + '</span><span class="eq-meta">' + meta + '</span><div class="eq-pills">' + pills.join('') + '</div></div>';
    }

    function renderAction() {
        const p = state.person, b = state.book, el = $('eq-action');
        let html = '';
        if (b && p) {
            const mine = p.holding.find(h => h.book_id === b.id);
            if (mine) html += '<button type="button" class="eq-btn eq-btn-good eq-btn-wide" data-return="' + esc(mine.issue_id) + '">' + esc(T.returnIt) + '</button>';
            const blocked = (p.blockers || []).length > 0 || b.on_shelf < 1 || !b.available;
            html += '<div class="eq-row"><label class="k" for="eq-due">' + esc(T.dueLabel) + '</label><input id="eq-due" class="eq-input" type="date" style="max-width:220px" value="' + esc(DUE) + '"></div>';
            html += '<button type="button" id="eq-issue-btn" class="eq-btn ' + (mine ? 'eq-btn-ghost' : 'eq-btn-primary') + ' eq-btn-wide"' + (blocked ? ' disabled' : '') + '>' + esc(fill(mine ? T.issueAnother : T.issue, { name: p.name })) + '</button>';
        } else if (b) {
            if (b.holders.length === 1) {
                const h = b.holders[0];
                html = '<div class="eq-info">' + esc(fill(T.outWith, { name: h.name, due: h.due })) + (h.overdue ? ' <span class="eq-pill bad">' + esc(T.overdue) + '</span>' : '') + '</div>'
                    + '<button type="button" class="eq-btn eq-btn-good eq-btn-wide" data-return="' + esc(h.issue_id) + '">' + esc(fill(T.returnFrom, { name: h.name })) + '</button>';
            } else if (b.holders.length > 1) {
                html = '<div class="eq-info">' + esc(T.whichOne) + '</div>' + b.holders.map(h => '<button type="button" class="eq-btn eq-btn-ghost eq-btn-wide" data-return="' + esc(h.issue_id) + '">' + esc(fill(T.returnFrom, { name: h.name })) + ' (' + esc(h.due) + ')</button>').join('');
            } else {
                html = '<div class="eq-info">' + esc(T.scanCardToIssue) + '</div>';
            }
        } else if (p) {
            html = '<div class="eq-info">' + esc(T.scanBookNext) + '</div>';
        }
        el.innerHTML = html;
    }

    const render = () => { renderPerson(); renderBook(); renderAction(); };

    async function scan(code) {
        code = String(code || '').trim();
        if (!code) return;
        const r = await post(URLS.scan, { code });
        if (!r.ok) { beep(false); flash(r.body.message || T.netFail, false); return; }
        beep(true); $('eq-flash').className = 'eq-flash';
        if (r.body.kind === 'person') state.person = r.body.person; else state.book = r.body.book;
        render();
        if (state.book && window.innerWidth < 992) document.querySelector('.eq-slip').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('eq-action').addEventListener('click', async e => {
        const ret = e.target.closest('[data-return]');
        if (ret) {
            ret.disabled = true;
            const r = await post(URLS.ret, { issue_id: Number(ret.dataset.return) });
            if (!r.ok) { beep(false); flash(r.body.message, false); ret.disabled = false; return; }
            beep(true); flash(r.body.message, true);
            if (state.person) state.person.holding = state.person.holding.filter(h => String(h.issue_id) !== ret.dataset.return);
            state.book = null; render(); $('eq-code').focus();
            return;
        }
        if (e.target.closest('#eq-issue-btn')) {
            const btn = $('eq-issue-btn'); btn.disabled = true;
            const r = await post(URLS.issue, { person: state.person.key, book_id: state.book.id, due_date: $('eq-due').value });
            if (!r.ok) { beep(false); flash(r.body.message, false); btn.disabled = false; return; }
            beep(true); flash(r.body.message, true);
            // Keep the borrower for their next book.
            state.person = r.body.person; state.book = null; render(); $('eq-code').focus();
        }
    });

    const startOver = () => { state.person = null; state.book = null; render(); $('eq-flash').className = 'eq-flash'; $('eq-code').value = ''; $('eq-code').focus(); };
    $('eq-scan-form').addEventListener('submit', e => { e.preventDefault(); const v = $('eq-code').value; $('eq-code').value = ''; scan(v); });
    $('eq-reset').addEventListener('click', startOver);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') startOver(); });

    let cam = null, last = '', lastAt = 0;
    $('eq-camera').addEventListener('click', async () => {
        const label = $('eq-camera').querySelector('span');
        if (cam) { try { await cam.stop(); } catch (e) {} cam = null; $('eq-reader').hidden = true; label.textContent = T.camOn; return; }
        $('eq-reader').hidden = false;
        try {
            cam = new Html5Qrcode('eq-reader');
            await cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 170 } }, text => {
                if (text === last && Date.now() - lastAt < 2500) return;
                last = text; lastAt = Date.now(); scan(text);
            });
            label.textContent = T.camOff;
        } catch (e) { cam = null; $('eq-reader').hidden = true; flash(T.camFail, false); }
    });

    render();
}
</script>
@endsection
