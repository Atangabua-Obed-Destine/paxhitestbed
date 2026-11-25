@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <form action="{{ $row->exists ? route($route.'.update', $row->id) : route($route.'.store') }}" method="post">
                    @csrf
                    @if($row->exists) @method('PUT') @endif
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ $row->exists ? __('Edit course group') : __('New course group') }}</h5>
                        </div>
                        <div class="card-block">
                            @if($errors->any())
                            <div class="alert alert-danger">
                                <strong>{{ __('The group was not saved:') }}</strong>
                                <ul class="mb-0">
                                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                                </ul>
                            </div>
                            @endif

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="title">{{ __('Group name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="title" required maxlength="191"
                                           value="{{ old('title', $row->title) }}" placeholder="{{ __('e.g. Principles of Management') }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="note">{{ __('Note') }}</label>
                                    <input type="text" class="form-control" name="note" id="note" maxlength="2000" value="{{ old('note', $row->note) }}">
                                </div>
                            </div>

                            <label class="d-block">{{ __('Courses in this group') }} <span class="text-danger">*</span></label>
                            <p class="text-muted small">{{ __('Tick the course each programme takes. A programme can take only one course of a group, and a course can be in only one group. Credits may differ between programmes.') }}</p>

                            <input type="search" class="form-control mb-2" id="cg-filter" placeholder="{{ __('Find a course by code, title or programme') }}" autocomplete="off">
                            @php $old = array_map('intval', (array) old('subjects', $selected)); @endphp
                            <div class="table-responsive" style="max-height:28rem;overflow:auto;border:1px solid #e3e7ee;border-radius:4px">
                                <table class="table table-sm table-hover mb-0" id="cg-courses">
                                    <thead style="position:sticky;top:0;background:#f6f8fb">
                                        <tr><th></th><th>{{ __('Code') }}</th><th>{{ __('Title') }}</th><th>{{ __('Programmes') }}</th><th>{{ __('Credits') }}</th><th>{{ __('Group') }}</th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach($subjects->sortBy(fn ($s) => (in_array($s->id, $old) ? '0' : '1') . $s->code) as $s)
                                        @php $other = $s->course_group_id && (int) $s->course_group_id !== (int) $row->id; @endphp
                                        <tr data-text="{{ strtolower($s->code.' '.$s->title.' '.($subjectProgrammes[$s->id] ?? '')) }}">
                                            <td><input type="checkbox" name="subjects[]" value="{{ $s->id }}" id="s{{ $s->id }}" {{ in_array($s->id, $old) ? 'checked' : '' }}></td>
                                            <td><label for="s{{ $s->id }}" style="font-family:monospace;margin:0">{{ $s->code }}</label></td>
                                            <td><label for="s{{ $s->id }}" style="margin:0">{{ $s->title }}</label></td>
                                            <td class="small">{{ ($subjectProgrammes[$s->id] ?? '') ?: '—' }}</td>
                                            <td>{{ $s->credit_hour }}</td>
                                            <td class="small">@if($other)<span class="text-warning">{{ $s->courseGroup->title ?? '' }}</span>@endif</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="small mt-2 mb-0"><span id="cg-count">0</span> {{ __('courses ticked') }}</p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('Save group') }}</button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">{{ __('btn_close') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    var box = document.getElementById('cg-filter');
    var rows = document.querySelectorAll('#cg-courses tbody tr');
    var count = document.getElementById('cg-count');
    function recount() { count.textContent = document.querySelectorAll('#cg-courses input:checked').length; }
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase();
        rows.forEach(function (r) {
            var ticked = r.querySelector('input').checked;
            r.style.display = (!q || ticked || r.dataset.text.indexOf(q) !== -1) ? '' : 'none';
        });
    });
    document.getElementById('cg-courses').addEventListener('change', recount);
    recount();
})();
</script>
@endsection
