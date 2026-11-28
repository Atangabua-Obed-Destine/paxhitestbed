@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ __('Equipment desk') }}</h4>
                <p class="eq-sub">{{ __('Scan the borrower\'s ID card and the item\'s sticker, in any order. Scan an item that is out to check it in.') }}</p>
            </div>
            <div class="eq-head-side">
                @if($desks->count() > 1)
                <label for="eq-deskid">{{ __('Desk') }}
                    <select id="eq-deskid" class="eq-select" style="width:auto;min-height:38px;font-size:14px">
                        @foreach($desks as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </label>
                @elseif($desks->count() === 1)
                <input type="hidden" id="eq-deskid" value="{{ $desks->first()->id }}">
                <span class="eq-pill">{{ $desks->first()->name }}</span>
                @endif
                @canany(['equipment-view', 'equipment-desk', 'equipment-manage'])
                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.equipment.board') }}">{{ __('Out right now') }}</a>
                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.equipment.items.index') }}">{{ __('Equipment list') }}</a>
                @endcanany
            </div>
        </div>

        <div class="eq-desk">
            <section class="eq-card" aria-label="{{ __('Scanner') }}">
                <div id="eq-reader" class="eq-reader" hidden></div>
                <button type="button" id="eq-camera" class="eq-btn eq-btn-ghost eq-btn-wide"><i class="fas fa-camera"></i> <span>{{ __('Use the camera') }}</span></button>
                <label for="eq-code" class="eq-lbl">{{ __('Or scan with the handheld scanner, or type the code') }}</label>
                <form id="eq-scan-form" class="eq-scanform" autocomplete="off">
                    <input id="eq-code" class="eq-input scan" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="{{ __('Card or sticker') }}" autofocus>
                    <button type="submit" class="eq-btn eq-btn-primary">{{ __('Look up') }}</button>
                </form>
                <p class="eq-hint">{{ __('A handheld scanner types the code and presses Enter for you.') }}</p>
                <div id="eq-flash" class="eq-flash" role="status" aria-live="polite"></div>
                <button type="button" id="eq-reset" class="eq-btn eq-btn-ghost eq-btn-sm" style="margin-top:12px">{{ __('Start over') }}</button>
            </section>

            <section class="eq-card eq-slip" aria-live="polite">
                <div id="eq-person" class="eq-ent"></div>
                <div id="eq-item" class="eq-ent"></div>

                <div id="eq-lend" class="eq-slip" hidden>
                    <div class="eq-row">
                        <span class="k">{{ __('For') }}</span>
                        <div class="eq-seg" id="eq-purpose" role="group" aria-label="{{ __('Who it is for') }}">
                            <button type="button" data-v="self" aria-pressed="true">{{ __('Own use') }}</button>
                            <button type="button" data-v="class" aria-pressed="false">{{ __('A class') }}</button>
                            <button type="button" data-v="lecturer" aria-pressed="false">{{ __('A lecturer') }}</button>
                        </div>
                    </div>
                    <div class="eq-row" id="eq-class-row" hidden>
                        <label class="k" for="eq-class">{{ __('Class') }}</label>
                        <input id="eq-class" class="eq-input" maxlength="190" placeholder="{{ __('e.g. Year 2 Nursing, NUR 221 in room B4') }}">
                    </div>
                    <div class="eq-row" id="eq-lecturer-row" hidden>
                        <label class="k" for="eq-lecturer">{{ __('Lecturer') }}</label>
                        <select id="eq-lecturer" class="eq-select">
                            <option value="">{{ __('Choose the lecturer') }}</option>
                            @foreach($lecturers as $l)
                            <option value="{{ $l->id }}">{{ trim($l->first_name . ' ' . $l->last_name) }}{{ $l->staff_id ? ' (' . $l->staff_id . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="eq-row">
                        <span class="k">{{ __('Due back') }}</span>
                        <div>
                            <div class="eq-seg" id="eq-due-choices" role="group" aria-label="{{ __('Due back') }}">
                                @foreach($dueChoices as $c)
                                <button type="button" data-at="{{ $c['at'] }}" aria-pressed="{{ $c['default'] ? 'true' : 'false' }}">{{ $c['label'] }}</button>
                                @endforeach
                            </div>
                            <input id="eq-due" class="eq-input" type="datetime-local" style="margin-top:6px;max-width:260px" value="{{ collect($dueChoices)->firstWhere('default', true)['at'] ?? '' }}" aria-label="{{ __('Due back at') }}">
                        </div>
                    </div>
                    <button type="button" id="eq-lend-btn" class="eq-btn eq-btn-primary eq-btn-wide" disabled>{{ __('Lend') }}</button>
                </div>

                <div id="eq-return" class="eq-slip" hidden>
                    <div id="eq-returner-note"></div>
                    <div id="eq-ticks-wrap">
                        <span class="eq-lbl" style="margin-top:0">{{ __('What came back') }}</span>
                        <div class="eq-ticks" id="eq-ticks"></div>
                    </div>
                    <div class="eq-row">
                        <span class="k">{{ __('Condition') }}</span>
                        <div class="eq-seg warn" id="eq-condition" role="group" aria-label="{{ __('Condition') }}">
                            <button type="button" data-v="fine" aria-pressed="true">{{ __('All fine') }}</button>
                            <button type="button" data-v="damaged" aria-pressed="false">{{ __('Damaged') }}</button>
                            <button type="button" data-v="missing" aria-pressed="false">{{ __('Something missing') }}</button>
                        </div>
                    </div>
                    <div class="eq-row">
                        <label class="k" for="eq-note">{{ __('Note') }}</label>
                        <input id="eq-note" class="eq-input" maxlength="1000" placeholder="{{ __('Needed if anything is damaged or missing') }}">
                    </div>
                    <button type="button" id="eq-checkin-btn" class="eq-btn eq-btn-good eq-btn-wide" disabled>{{ __('Check in') }}</button>
                </div>
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
        'scan' => $deskBase . route('admin.equipment.desk.scan', [], false),
        'lend' => $deskBase . route('admin.equipment.desk.lend', [], false),
        'checkin' => $deskBase . route('admin.equipment.desk.check-in', [], false),
        'person' => $deskBase . route('admin.equipment.history.person', ['type' => 'staff', 'id' => 0], false),
        'item' => $deskBase . route('admin.equipment.history.item', ['item' => 0], false),
    ];
@endphp
@php
    $deskText = [
        'scanPerson' => __('Scan the borrower\'s ID card'),
        'scanPersonSub' => __('Their photo appears so you can see it is really them.'),
        'scanItem' => __('Scan the item\'s sticker'),
        'scanItemSub' => __('A sticker that is out checks the item in.'),
        'nothingOut' => __('Has nothing out'),
        'holds' => __('Has out:'),
        'overdue' => __('overdue'),
        'students' => __('Students may borrow'),
        'staffOnly' => __('Staff only'),
        'comesWith' => __('Comes with'),
        'nothingExtra' => __('Nothing else goes with it'),
        'outWith' => __('Out with :name since :since (:purpose). Due back :due.'),
        'dueAt' => __('due :when'),
        'returnerNeeded' => __('Scan the card of whoever brought it back.'),
        'notBorrower' => __('Not the borrower. :borrower stays named on the loan; :returner is recorded as bringing it back.'),
        'isBorrower' => __(':name is bringing back their own loan.'),
        'lendTo' => __('Lend :code to :name'),
        'checkIn' => __('Check in :code'),
        'notLendable' => __(':code is marked ":state", so it cannot be lent.'),
        'notForStudents' => __(':code is not marked for students. A member of staff has to borrow it.'),
        'history' => __('History'),
        'camOn' => __('Use the camera'),
        'camOff' => __('Stop the camera'),
        'camFail' => __('The camera could not start. Allow camera access, or use the handheld scanner.'),
        'netFail' => __('The desk could not reach the server. Check the connection and scan again.'),
        'inactive' => __('No longer active'),
    ];
@endphp
<script>
{
    const URLS = @json($deskUrls);
    const TOKEN = @json(csrf_token());
    const T = @json($deskText);

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fill = (s, v) => String(s).replace(/:(\w+)/g, (m, k) => (k in v ? v[k] : m));
    const state = { person: null, item: null, purpose: 'self', condition: 'fine' };

    // A short beep tells the attendant a scan registered without looking up.
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
        if (!p) {
            el.innerHTML = '<span class="eq-av wait"><i class="fas fa-id-card"></i></span><div><span class="eq-name">' + esc(T.scanPerson) + '</span><span class="eq-meta">' + esc(T.scanPersonSub) + '</span></div>';
            return;
        }
        let pills = '';
        if (!p.active) pills += '<span class="eq-pill bad">' + esc(T.inactive) + '</span>';
        else if (!p.holding.length) pills += '<span class="eq-pill good">' + esc(T.nothingOut) + '</span>';
        const holding = p.holding.length ? '<ul class="eq-holding"><li><b>' + esc(T.holds) + '</b></li>' + p.holding.map(h => '<li>' + esc(h.code) + ', ' + esc(h.name) + ' (' + esc(fill(T.dueAt, { when: h.due })) + ')' + (h.overdue ? ' <span class="eq-pill bad">' + esc(T.overdue) + '</span>' : '') + '</li>').join('') + '</ul>' : '';
        const stops = (p.blockers || []).map(b => '<div class="eq-stop">' + esc(b) + '</div>').join('');
        const link = URLS.person.replace(/\/staff\/0$/, '/' + p.type + '/' + p.id);
        el.innerHTML = avatar(p) + '<div><span class="eq-name">' + esc(p.name) + '</span><span class="eq-meta">' + esc(p.role) + (p.code ? ', ' + esc(p.code) : '') + ' · <a href="' + esc(link) + '" target="_blank" rel="noopener">' + esc(T.history) + '</a></span><div class="eq-pills">' + pills + '</div>' + holding + stops + '</div>';
    }

    function renderItem() {
        const it = state.item, el = $('eq-item');
        if (!it) {
            el.innerHTML = '<span class="eq-av wait"><i class="fas fa-qrcode"></i></span><div><span class="eq-name">' + esc(T.scanItem) + '</span><span class="eq-meta">' + esc(T.scanItemSub) + '</span></div>';
            return;
        }
        const pills = [];
        pills.push(it.students ? '<span class="eq-pill good">' + esc(T.students) + '</span>' : '<span class="eq-pill">' + esc(T.staffOnly) + '</span>');
        if (it.state !== 'active') pills.push('<span class="eq-pill warn">' + esc(it.state_label) + '</span>');
        if (it.loan) pills.push('<span class="eq-pill ' + esc(it.loan.status.tone) + '">' + esc(it.loan.status.text) + '</span>');
        const extras = it.accessories.length ? esc(T.comesWith) + ' ' + it.accessories.map(esc).join(', ') : esc(T.nothingExtra);
        const out = it.loan ? '<p class="eq-meta" style="margin-top:6px">' + esc(fill(T.outWith, { name: it.loan.borrower.name, since: it.loan.lent_at_mid, purpose: it.loan.purpose, due: it.loan.due_at_mid })) + '</p>' : '';
        const link = URLS.item.replace(/\/0$/, '/' + it.id);
        el.innerHTML = '<span class="eq-av it">' + esc(it.code.slice(0, 3)) + '</span><div><span class="eq-name">' + esc(it.code) + ', ' + esc(it.name) + '</span><span class="eq-meta">' + extras + ' · <a href="' + esc(link) + '" target="_blank" rel="noopener">' + esc(T.history) + '</a></span><div class="eq-pills">' + pills.join('') + '</div>' + out + '</div>';
    }

    function renderModes() {
        const p = state.person, it = state.item;
        const returning = !!(it && it.loan);
        const lending = !!(it && !it.loan);
        $('eq-return').hidden = !returning;
        $('eq-lend').hidden = !lending;

        if (lending) {
            const problems = [];
            if (it.state !== 'active') problems.push(fill(T.notLendable, { code: it.code, state: it.state_label }));
            if (p && p.type === 'student' && !it.students) problems.push(fill(T.notForStudents, { code: it.code }));
            const btn = $('eq-lend-btn');
            btn.disabled = !p || problems.length > 0 || (p.blockers || []).length > 0;
            btn.textContent = p ? fill(T.lendTo, { code: it.code, name: p.name }) : T.scanPerson;
            let warn = $('eq-lend-warn');
            if (!warn) { warn = document.createElement('div'); warn.id = 'eq-lend-warn'; $('eq-lend').prepend(warn); }
            warn.innerHTML = problems.map(x => '<div class="eq-stop">' + esc(x) + '</div>').join('');
            $('eq-class-row').hidden = state.purpose !== 'class';
            $('eq-lecturer-row').hidden = state.purpose !== 'lecturer';
        }

        if (returning) {
            const note = $('eq-returner-note');
            if (!p) note.innerHTML = '<div class="eq-info">' + esc(T.returnerNeeded) + '</div>';
            else if (p.key === it.loan.borrower.key) note.innerHTML = '<div class="eq-info">' + esc(fill(T.isBorrower, { name: p.name })) + '</div>';
            else note.innerHTML = '<div class="eq-info">' + esc(fill(T.notBorrower, { borrower: it.loan.borrower.name, returner: p.name })) + '</div>';
            const out = it.loan.accessories_out || [];
            $('eq-ticks-wrap').hidden = !out.length;
            if ($('eq-ticks').dataset.loan !== String(it.loan.id)) {
                $('eq-ticks').dataset.loan = String(it.loan.id);
                $('eq-ticks').innerHTML = out.map((a, i) => '<label><input type="checkbox" checked value="' + esc(a) + '" id="eq-tick-' + i + '"> ' + esc(a) + '</label>').join('');
            }
            const btn = $('eq-checkin-btn');
            btn.disabled = !p;
            btn.textContent = p ? fill(T.checkIn, { code: it.code }) : T.returnerNeeded;
        }
    }

    const render = () => { renderPerson(); renderItem(); renderModes(); };

    const press = (groupId, value) => {
        $(groupId).querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String((b.dataset.v ?? b.dataset.at) === value)));
    };

    async function scan(code) {
        code = String(code || '').trim();
        if (!code) return;
        const r = await post(URLS.scan, { code });
        if (!r.ok) { beep(false); flash(r.body.message || T.netFail, false); return; }
        beep(true);
        $('eq-flash').className = 'eq-flash';
        if (r.body.kind === 'person') state.person = r.body.person;
        else state.item = r.body.item;
        render();
        // On a phone the slip sits under the scanner: bring it up once there is something to do.
        if (state.person && state.item && window.innerWidth < 992) document.querySelector('.eq-slip').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function resetLend() {
        state.purpose = 'self'; press('eq-purpose', 'self');
        $('eq-class').value = ''; $('eq-lecturer').value = '';
        const def = document.querySelector('#eq-due-choices button[aria-pressed="true"]') || document.querySelector('#eq-due-choices button');
        state.condition = 'fine'; press('eq-condition', 'fine'); $('eq-note').value = '';
        $('eq-ticks').dataset.loan = '';
    }

    function startOver() {
        state.person = null; state.item = null; resetLend(); render();
        $('eq-flash').className = 'eq-flash'; $('eq-code').value = ''; $('eq-code').focus();
    }

    // Due-time quick choices fill the date field; the field can still be edited.
    $('eq-due-choices').addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        press('eq-due-choices', b.dataset.at); $('eq-due').value = b.dataset.at;
    });
    $('eq-due').addEventListener('input', () => press('eq-due-choices', $('eq-due').value));
    $('eq-purpose').addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        state.purpose = b.dataset.v; press('eq-purpose', state.purpose); renderModes();
    });
    $('eq-condition').addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        state.condition = b.dataset.v; press('eq-condition', state.condition);
    });
    // Unticking an accessory means something is missing.
    $('eq-ticks').addEventListener('change', () => {
        const missing = [...$('eq-ticks').querySelectorAll('input')].some(i => !i.checked);
        if (missing && state.condition === 'fine') { state.condition = 'missing'; press('eq-condition', 'missing'); }
    });

    $('eq-scan-form').addEventListener('submit', e => { e.preventDefault(); const v = $('eq-code').value; $('eq-code').value = ''; scan(v); });
    $('eq-reset').addEventListener('click', startOver);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') startOver(); });

    $('eq-lend-btn').addEventListener('click', async () => {
        const btn = $('eq-lend-btn'); btn.disabled = true;
        const desk = $('eq-deskid');
        const r = await post(URLS.lend, {
            person: state.person.key, item_id: state.item.id, purpose: state.purpose,
            purpose_detail: $('eq-class').value, lecturer_id: $('eq-lecturer').value || null,
            due_at: $('eq-due').value, desk_id: desk ? desk.value : null,
        });
        if (!r.ok) { beep(false); flash(r.body.message, false); renderModes(); return; }
        beep(true); flash(r.body.message, true);
        // Keep the borrower: they may be taking a cable with the projector.
        state.person = r.body.person || state.person;
        state.item = null; resetLend(); render();
        $('eq-code').focus();
    });

    $('eq-checkin-btn').addEventListener('click', async () => {
        const btn = $('eq-checkin-btn'); btn.disabled = true;
        const back = [...$('eq-ticks').querySelectorAll('input:checked')].map(i => i.value);
        const r = await post(URLS.checkin, {
            item_id: state.item.id, returner: state.person ? state.person.key : null,
            back, condition: state.condition, note: $('eq-note').value,
        });
        if (!r.ok) { beep(false); flash(r.body.message, false); renderModes(); return; }
        beep(true); flash(r.body.message, true);
        state.person = null; state.item = null; resetLend(); render(); $('eq-code').focus();
    });

    // Remember which desk this computer sits at.
    const deskSel = $('eq-deskid');
    if (deskSel && deskSel.tagName === 'SELECT') {
        try { const saved = localStorage.getItem('eq-desk'); if (saved && deskSel.querySelector('option[value="' + saved + '"]')) deskSel.value = saved; } catch (e) {}
        deskSel.addEventListener('change', () => { try { localStorage.setItem('eq-desk', deskSel.value); } catch (e) {} });
    }

    // Camera: reads both the QR code and the barcode on cards and stickers.
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
