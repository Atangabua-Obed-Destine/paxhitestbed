@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span class="d-block m-t-5">{{ __('View all your resit requests and their status') }}</span>
                    </div>
                    <div class="card-block">
                        <div class="mb-3">
                            <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New Resit Request') }}</a>
                        </div>

                        @php
                            $pendingPayments = $requests->filter(fn($r) => $r->workflow_state === 'awaiting_payment' && $r->payment_status === 'pending');
                        @endphp
                        @if($pendingPayments->count() > 0)
                        <div class="alert alert-warning" style="border-left: 4px solid #ffc107;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle fa-lg me-3 mt-1" style="color: #e67e00;"></i>
                                <div>
                                    <strong>{{ __('You have') }} {{ $pendingPayments->count() }} {{ __('unpaid resit fee(s)') }}</strong>
                                    <p class="mb-1 mt-1">{{ __('To complete your resit request, please pay using one of these options:') }}</p>
                                    <ul class="mb-1" style="padding-left: 18px;">
                                        <li><i class="fas fa-laptop text-primary"></i> <strong>{{ __('Online:') }}</strong> {{ __('Go to') }} <a href="{{ route('student.fees.index') }}" class="font-weight-bold">{{ __('My Fees') }}</a> {{ __('and pay directly from your portal.') }}</li>
                                        <li><i class="fas fa-money-bill-alt text-info"></i> <strong>{{ __('Cash:') }}</strong> {{ __('Pay cash directly at the Finance Office.') }}</li>
                                        <li><i class="fas fa-university text-success"></i> <strong>{{ __('Bank/Mobile Money:') }}</strong> {{ __('Pay via bank or mobile money, then bring your receipt to the Finance Office for verification.') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(count($requests) > 0)
                        <div class="table-responsive">
                            <table id="resit-history-table" class="display table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_subject') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('Request Date') }}</th>
                                        <th>{{ __('Resit Session') }}</th>
                                        <th>{{ __('Workflow Status') }}</th>
                                        <th>{{ __('Payment Status') }}</th>
                                        <th>{{ __('Fee Amount') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $key => $request)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $request->subject->code ?? 'N/A' }}</strong><br>
                                            <small>{{ $request->subject->title ?? 'N/A' }}</small>
                                        </td>
                                        <td>{{ $request->studentEnroll->program->title ?? 'N/A' }}</td>
                                        <td>{{ $request->session->title ?? 'N/A' }}</td>
                                        <td>{{ $request->studentEnroll->semester->title ?? 'N/A' }}</td>
                                        <td>{{ $request->created_at->format('d M Y H:i') }}</td>
                                        <td>
                                            @if($request->resitSession)
                                                {{ $request->resitSession->title }}
                                                @if($request->resitSemester)
                                                    <br><small>{{ $request->resitSemester->title }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">{{ __('Not Scheduled') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $state = $request->workflow_state;
                                                $badge_class = match($state) {
                                                    'requested' => 'secondary',
                                                    'awaiting_payment' => 'warning',
                                                    'finance_review' => 'info',
                                                    'approved' => 'primary',
                                                    'scheduled' => 'success',
                                                    'rejected' => 'danger',
                                                    'cancelled' => 'dark',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-pill badge-{{ $badge_class }}">
                                                {{ ucfirst(str_replace('_', ' ', $state)) }}
                                            </span>
                                            @if($request->state_changed_at)
                                            <br><small class="text-muted">{{ $request->state_changed_at->format('d M Y') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $payment = $request->payment_status;
                                                $payment_badge = match($payment) {
                                                    'paid' => 'success',
                                                    'partial' => 'info',
                                                    'pending' => 'warning',
                                                    'cancelled' => 'danger',
                                                    'waived' => 'primary',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-pill badge-{{ $payment_badge }}">
                                                {{ ucfirst($payment) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($request->fee_amount > 0)
                                                {{ number_format($request->fee_amount, 2) }} {{ __('field_currency_symbol') }}
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($request->notes)
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#notesModal-{{ $request->id }}">
                                                    <i class="fas fa-eye"></i> {{ __('View') }}
                                                </button>
                                                <!-- Notes Modal -->
                                                <div class="modal fade" id="notesModal-{{ $request->id }}" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">{{ __('Notes') }}</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>{{ $request->notes }}</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $cancellable = in_array($request->workflow_state, ['requested', 'awaiting_payment']);
                                                $cancelled = $request->workflow_state === 'cancelled';
                                            @endphp
                                            <div class="d-flex align-items-center flex-wrap" style="gap:5px;">
                                            @if($request->workflow_state === 'awaiting_payment' && $request->payment_status === 'pending' && $request->fee_id)
                                                <a href="{{ route('student.fees.index') }}" class="btn btn-sm btn-success" title="{{ __('Pay this fee online from your Fees page') }}">
                                                    <i class="fas fa-credit-card"></i> {{ __('Pay Online') }}
                                                </a>
                                            @endif
                                            @if($cancellable)
                                                <form method="post" action="{{ route($route.'.cancel', $request->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('Are you sure you want to cancel this resit request? This may allow you to progress to the next semester if you have no failed courses.') }}');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times-circle"></i> {{ __('Cancel') }}
                                                    </button>
                                                </form>
                                            @elseif($cancelled)
                                                <span class="badge badge-secondary">{{ __('Cancelled') }}</span>
                                            @elseif($request->payment_status === 'paid')
                                                <span class="text-success"><i class="fas fa-check-circle"></i> {{ __('Paid') }}</span>
                                            @else
                                                <span class="text-muted">{{ __('N/A') }}</span>
                                            @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ __('You have not made any resit requests yet.') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
    $(document).ready(function() {
        $('#resit-history-table').DataTable({
            "order": [[ 5, "desc" ]], // Order by request date descending
            "pageLength": 25
        });
    });
</script>
@endsection
