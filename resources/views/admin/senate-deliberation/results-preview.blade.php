@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    .senate-tabs .nav-link { font-weight: 600; font-size: 13px; padding: 10px 18px; }
    .senate-tabs .nav-link.active { background: #2c3e50; color: #fff !important; border-color: #2c3e50; }
    .senate-tabs .nav-link i { margin-right: 5px; }
    .kpi-card { border-radius: 8px; transition: transform 0.2s; border: 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.12); }
    .kpi-card .kpi-value { font-size: 28px; font-weight: 700; margin-bottom: 2px; }
    .kpi-card .kpi-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
    .faculty-accordion .card { border-radius: 6px; margin-bottom: 8px; border: 1px solid #dee2e6; }
    .faculty-accordion .card-header { cursor: pointer; background: #f8f9fa; transition: background 0.2s; }
    .faculty-accordion .card-header:hover { background: #e9ecef; }
    .faculty-accordion .card-header h6 { margin: 0; font-weight: 600; }
    .dept-section { background: #fafbfc; border-radius: 6px; padding: 16px; margin-bottom: 14px; border-left: 4px solid #3498db; }
    .dept-section h6 { color: #2c3e50; font-weight: 700; }
    .course-table { font-size: 11.5px; }
    .course-table th { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
    .course-table td { vertical-align: middle; }
    .grade-cell { text-align: center; min-width: 28px; font-size: 10.5px; padding: 2px 3px !important; }
    .course-table .pass-rate-high { color: #28a745; font-weight: 700; }
    .course-table .pass-rate-mid { color: #ffc107; font-weight: 700; }
    .course-table .pass-rate-low { color: #dc3545; font-weight: 700; }
    .lecturer-card { border-radius: 8px; border: 1px solid #e9ecef; transition: all 0.2s; }
    .lecturer-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.1); }
    .lecturer-rank { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
    .performance-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
    .performance-bar .fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
    .top-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .student-matrix-container { max-height: 600px; overflow: auto; }
    .student-matrix { font-size: 10.5px; }
    .student-matrix th { font-size: 10px; position: sticky; top: 0; z-index: 2; }
    .student-matrix td { padding: 3px 5px !important; white-space: nowrap; text-align: center; }
    .student-matrix .student-name { text-align: left; white-space: nowrap; min-width: 150px; }
    .student-matrix .gpa-cell { font-weight: 700; }
    .status-P { color: #28a745; font-weight: 600; }
    .status-F { color: #dc3545; font-weight: 600; }
    .status-NR { color: #adb5bd; }
    .status-NP, .status-NS { color: #6c757d; font-style: italic; }
    .readiness-ring { width: 100px; height: 100px; position: relative; display: inline-block; }
    .summary-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 2px; }
    .coverage-bar { width: 60px; height: 6px; background: #e9ecef; border-radius: 3px; display: inline-block; vertical-align: middle; }
    .coverage-bar .fill { height: 100%; border-radius: 3px; }
    @media print {
        .no-print, .page-header, .pcoded-header, .pcoded-sidebar, .card-header form { display: none !important; }
        .tab-content > .tab-pane { display: block !important; opacity: 1 !important; page-break-before: always; }
        .faculty-accordion .collapse { display: block !important; }
        .course-table { font-size: 9px; }
        body { font-size: 10px; }
    }
</style>
@endsection

@section('content')

{{-- ═══ PAGE HEADER ═══ --}}
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="page-header-title">
                <i class="fas fa-university bg-c-blue"></i>
                <div class="d-inline">
                    <h5>{{ $title }}</h5>
                    <span>Comprehensive results preview for senate review</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 text-right no-print">
            @if(count($semester_blocks) > 0)
            <a href="{{ route('admin.senate-deliberation.export-results-preview', count($selected_semesters) > 1 ? ['session' => $selected_session, 'semester_type' => $selected_semester_type, 'semester' => $selected_semesters, 'faculty' => $selected_faculty ?? '0'] : ['session' => $selected_session, 'semester' => $selected_semester, 'faculty' => $selected_faculty ?? '0']) }}" class="btn btn-success btn-sm mr-2">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            @endif
        </div>
    </div>
</div>

{{-- ═══ FILTER CARD ═══ --}}
<div class="card no-print">
    <div class="card-header">
        <h5><i class="fas fa-filter"></i> {{ __('Select Academic Period') }}</h5>
    </div>
    <div class="card-block">
        <form method="GET" action="{{ route('admin.senate-deliberation.results-preview') }}">
            <div class="row gx-2">
                <div class="col-md-3">
                    <label class="form-label fw-bold">{{ __('Session') }} <span class="text-danger">*</span></label>
                    <select name="session" class="form-control" required>
                        <option value="0">{{ __('Select Session') }}</option>
                        @foreach($sessions as $s)
                        <option value="{{ $s->id }}" {{ $selected_session == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
                @include('common.inc.semester_type_field', ['type_col' => 'col-md-2', 'semester_col' => 'col-md-3'])
                <div class="col-md-3">
                    <label class="form-label fw-bold">{{ __('Faculty') }} <small class="text-muted">(optional)</small></label>
                    <select name="faculty" class="form-control">
                        <option value="0">{{ __('All Faculties') }}</option>
                        @foreach($faculties as $fac)
                        <option value="{{ $fac->id }}" {{ $selected_faculty == $fac->id ? 'selected' : '' }}>{{ $fac->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> {{ __('Generate Preview') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(count($semester_blocks) > 0)
@foreach($semester_blocks as $block)
@include('admin.senate-deliberation.partials.results-preview-semester', $block + [
    'selected_semester' => (string) $block['semester']->id,
    'bk' => count($semester_blocks) > 1 ? '-s' . $block['semester']->id : '',
    'show_heading' => count($semester_blocks) > 1,
])
@endforeach
@include('admin.senate-deliberation.partials.skipped-semesters')

@else
@include('admin.senate-deliberation.partials.skipped-semesters')
{{-- No filters selected --}}
<div class="card">
    <div class="card-block text-center py-5">
        <i class="fas fa-search text-muted fa-4x mb-3"></i>
        <h5 class="text-muted">Select Session and Semester</h5>
        <p class="text-muted">Choose an academic session and semester above to generate the comprehensive results preview for senate review.</p>
    </div>
</div>
@endif

@endsection

@section('page_js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {

    // Initialize datatable for publishing readiness details
    if ($.fn.DataTable) {
        var pubTables = $('.pub-details-table').DataTable({
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Fix column widths when a tab is shown
        $('a[data-bs-toggle="tab"], a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
    }

    // ── Lecturer row expand/collapse ────────────────────────────────────────
    $('.lecturer-row').css('cursor', 'pointer').on('click', function() {
        var id = $(this).data('lecturer-id');
        $('#lecDetail' + id + ($(this).data('bk') || '')).toggle();
    });


});
</script>
@endsection
