@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
@php $mode = old('mode', 'people'); @endphp
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-pen"></i> {{ $title }}</h5>
            </div>
            <div class="card-block">
                @include('common.mailbox._nav', ['folder' => null, 'unread' => 0])

                <form method="post" action="{{ route('admin.mailbox.store') }}" enctype="multipart/form-data" id="mbx-compose" style="max-width:52rem">
                    @csrf
                    <div class="mb-3">
                        <div class="btn-group" role="group" aria-label="{{ __('Send to') }}">
                            <input type="radio" class="btn-check" name="mode" id="mbx-mode-people" value="people" @checked($mode === 'people')>
                            <label class="btn btn-outline-primary" for="mbx-mode-people"><i class="fas fa-user"></i> {{ __('Named people') }}</label>
                            <input type="radio" class="btn-check" name="mode" id="mbx-mode-group" value="group" @checked($mode === 'group') @disabled(!$canBroadcast && $courses->isEmpty())>
                            <label class="btn btn-outline-primary" for="mbx-mode-group"><i class="fas fa-users"></i> {{ __('A group') }}</label>
                        </div>
                        @if(!$canBroadcast && $courses->isEmpty())
                        <div class="mbx-help mt-1">{{ __('Group messages are for lecturers (to the classes they teach) and for staff allowed to send to groups.') }}</div>
                        @endif
                    </div>

                    {{-- Named people --}}
                    <div class="mb-3" data-mode="people">
                        <label class="form-label fw-bold" for="mbx-to">{{ __('To') }}</label>
                        <select id="mbx-to" name="to[]" multiple class="form-control @error('to') is-invalid @enderror" style="width:100%">
                            @foreach($prefill as $p)
                            <option value="{{ $p['id'] }}" selected>{{ $p['text'] }}</option>
                            @endforeach
                        </select>
                        @error('to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="mbx-help mt-1">{{ __('Type at least two letters of a name, staff ID or matricule. Everyone you add sees the whole conversation.') }}</div>
                    </div>

                    {{-- A group --}}
                    <div class="mb-3" data-mode="group">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="mbx-group-type">{{ __('Group') }}</label>
                                <select id="mbx-group-type" name="group_type" class="form-control @error('group_type') is-invalid @enderror">
                                    <option value="course" @selected(old('group_type') === 'course')>{{ __('A course class') }}</option>
                                    @if($canBroadcast)
                                    <option value="programme" @selected(old('group_type') === 'programme')>{{ __('A programme / semester') }}</option>
                                    <option value="department" @selected(old('group_type') === 'department')>{{ __('A staff department') }}</option>
                                    <option value="all_staff" @selected(old('group_type') === 'all_staff')>{{ __('All staff') }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-8" data-group="course">
                                <label class="form-label fw-bold" for="mbx-group-subject">{{ __('Course') }}</label>
                                <select id="mbx-group-subject" name="group_subject" class="form-control">
                                    @if($courses->count())
                                    <optgroup label="{{ __('Courses you teach') }}">
                                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('group_subject') == $c->id)>{{ $c->code }} — {{ $c->title }}</option>@endforeach
                                    </optgroup>
                                    @endif
                                    @if($allCourses->count())
                                    <optgroup label="{{ __('All courses') }}">
                                        @foreach($allCourses as $c)<option value="{{ $c->id }}" @selected(old('group_subject') == $c->id)>{{ $c->code }} — {{ $c->title }}</option>@endforeach
                                    </optgroup>
                                    @endif
                                </select>
                                <div class="mbx-help mt-1">{{ __('Students registered for the course this session, including courses linked to it in a course group.') }}</div>
                            </div>
                            @if($canBroadcast)
                            <div class="col-md-8" data-group="programme">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label fw-bold" for="mbx-group-program">{{ __('Programme') }}</label>
                                        <select id="mbx-group-program" name="group_program" class="form-control">
                                            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(old('group_program') == $p->id)>{{ $p->title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="mbx-group-session">{{ __('Session') }}</label>
                                        <select id="mbx-group-session" name="group_session" class="form-control">
                                            @foreach($sessions as $s)<option value="{{ $s->id }}" @selected(old('group_session', $sessions->firstWhere('current', 1)?->id) == $s->id)>{{ $s->title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="mbx-group-semester">{{ __('Semester') }}</label>
                                        <select id="mbx-group-semester" name="group_semester" class="form-control">
                                            <option value="0">{{ __('All') }}</option>
                                            @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(old('group_semester') == $s->id)>{{ $s->title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="mbx-group-section">{{ __('Section') }}</label>
                                        <select id="mbx-group-section" name="group_section" class="form-control">
                                            <option value="0">{{ __('All') }}</option>
                                            @foreach($sections as $s)<option value="{{ $s->id }}" @selected(old('group_section') == $s->id)>{{ $s->title }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8" data-group="department">
                                <label class="form-label fw-bold" for="mbx-group-department">{{ __('Department') }}</label>
                                <select id="mbx-group-department" name="group_department" class="form-control">
                                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('group_department') == $d->id)>{{ $d->title }}</option>@endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                        @error('group_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="alert alert-info mt-2 mb-0 py-2" id="mbx-audience" aria-live="polite">{{ __('Counting…') }}</div>
                        <input type="hidden" name="confirm_count" id="mbx-confirm-count" value="">
                        <div class="mbx-help mt-1">{{ __('Each person gets their own copy, and their reply comes back to you alone.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="mbx-subject">{{ __('Subject') }}</label>
                        <input id="mbx-subject" name="subject" class="form-control @error('subject') is-invalid @enderror" required maxlength="190" value="{{ old('subject') }}">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold" for="mbx-body">{{ __('Message') }}</label>
                        <textarea id="mbx-body" name="body" rows="8" class="form-control @error('body') is-invalid @enderror" required maxlength="20000">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @include('common.mailbox._attach', ['attachId' => 'c'])
                    <div class="mt-3 d-flex gap-3 align-items-center flex-wrap">
                        <button type="submit" class="btn btn-primary" id="mbx-send"><i class="fas fa-paper-plane"></i> {{ __('Send') }}</button>
                        <span class="mbx-help">{{ __('Each recipient is told by email that a message is waiting; the message itself stays in PAXHI.') }}</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script>
(function () {
    var form = document.getElementById('mbx-compose');
    var audienceBox = document.getElementById('mbx-audience');
    var confirmInput = document.getElementById('mbx-confirm-count');
    var count = null;

    function mode() { return form.querySelector('input[name="mode"]:checked').value; }
    function groupType() { return document.getElementById('mbx-group-type').value; }

    function show() {
        form.querySelectorAll('[data-mode]').forEach(function (el) { el.style.display = el.getAttribute('data-mode') === mode() ? '' : 'none'; });
        form.querySelectorAll('[data-group]').forEach(function (el) { el.style.display = el.getAttribute('data-group') === groupType() ? '' : 'none'; });
        if (mode() === 'group') { countAudience(); }
    }

    function countAudience() {
        var params = new URLSearchParams();
        ['group_type', 'group_subject', 'group_program', 'group_session', 'group_semester', 'group_section', 'group_department'].forEach(function (n) {
            var el = form.querySelector('[name="' + n + '"]'); if (el) { params.append(n, el.value); }
        });
        audienceBox.textContent = @json(__('Counting…'));
        count = null; confirmInput.value = '';
        fetch(@json(request()->getBaseUrl() . route('admin.mailbox.audience', [], false)) + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                count = d.count; confirmInput.value = d.count;
                if (!d.allowed) { audienceBox.textContent = @json(__('You cannot send to this group.')); return; }
                audienceBox.textContent = d.count > 0
                    ? @json(__('This will go to')) + ' ' + d.count + ' ' + (d.count === 1 ? @json(__('person')) : @json(__('people'))) + ': ' + d.label
                    : @json(__('Nobody is in this group.'));
            })
            .catch(function () { audienceBox.textContent = @json(__('Could not count the group. Check your connection and try again.')); });
    }

    form.querySelectorAll('input[name="mode"]').forEach(function (r) { r.addEventListener('change', show); });
    form.querySelectorAll('[name^="group_"]').forEach(function (el) { el.addEventListener('change', function () { show(); }); });

    form.addEventListener('submit', function (e) {
        if (mode() === 'group') {
            if (!count) { e.preventDefault(); alert(@json(__('Choose a group with at least one person.'))); return; }
            if (!confirm(@json(__('Send this message to')) + ' ' + count + ' ' + (count === 1 ? @json(__('person')) : @json(__('people'))) + '?')) { e.preventDefault(); }
        }
    });

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#mbx-to').select2({
            placeholder: @json(__('Search staff or students')),
            minimumInputLength: 2,
            ajax: {
                url: @json(request()->getBaseUrl() . route('admin.mailbox.search', [], false)),
                dataType: 'json',
                delay: 250,
                data: function (p) { return { q: p.term }; },
                processResults: function (d) { return d; }
            }
        });
    }
    show();
})();
</script>
@endsection
