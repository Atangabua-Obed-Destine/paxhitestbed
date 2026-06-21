@extends('admin.layouts.master')
@section('title', 'Payment Verifications')

@section('content')
<style>
    .verifications-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .filter-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .status-badge {
        padding: 6px 15px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .badge-approved {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .btn-action {
        padding: 6px 12px;
        border-radius: 8px;
        border: none;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-approve {
        background: #10b981;
        color: white;
    }
    
    .btn-approve:hover {
        background: #059669;
        transform: translateY(-2px);
    }
    
    .btn-reject {
        background: #ef4444;
        color: white;
    }
    
    .btn-reject:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }
    
    .btn-view {
        background: #3b82f6;
        color: white;
    }
    
    .btn-view:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
    }
    
    .receipt-preview {
        max-width: 100%;
        max-height: 500px;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .info-label {
        font-weight: 600;
        color: #64748b;
        margin-bottom: 5px;
    }
    
    .info-value {
        color: #1e293b;
        font-size: 16px;
        margin-bottom: 15px;
    }
</style>

@push('styles')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
@endpush

<div class="content-header row">
</div>

<div class="content-body">
    <section id="platform-fee-verifications">
        <div class="verifications-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-check-double"></i> Payment Verifications</h2>
                    <p class="mb-0 mt-2" style="opacity: 0.9;">Review and verify student platform fee payment receipts</p>
                </div>
                <div>
                    <button class="btn btn-light" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-filter"></i> Filter by Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">All Payments</option>
                        <option value="pending" selected>Pending Only</option>
                        <option value="approved">Approved Only</option>
                        <option value="rejected">Rejected Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- DataTable Card -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="paymentsTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Session</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">Student Name</div>
                        <div class="info-value" id="modal-student-name"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Student ID</div>
                        <div class="info-value" id="modal-student-id"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Session</div>
                        <div class="info-value" id="modal-session"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Amount</div>
                        <div class="info-value" id="modal-amount"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Payment Date</div>
                        <div class="info-value" id="modal-payment-date"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Submitted On</div>
                        <div class="info-value" id="modal-submitted-date"></div>
                    </div>
                    <div class="col-md-12" id="student-note-section" style="display:none;">
                        <div class="info-label">Student Note</div>
                        <div class="info-value" id="modal-student-note"></div>
                    </div>
                    <div class="col-md-12 mt-3">
                        <div class="info-label">Receipt</div>
                        <div class="text-center" id="receipt-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approveForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="approve-payment-id">
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i> You are about to approve this payment and grant student access to the portal.
                    </div>
                    <div class="form-group">
                        <label>Admin Note (Optional)</label>
                        <textarea class="form-control" id="approve-note" rows="3" placeholder="Add any verification notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="reject-payment-id">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Student will be able to reupload a new receipt after rejection.
                    </div>
                    <div class="form-group">
                        <label>Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject-note" rows="3" placeholder="Explain why this payment is being rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#paymentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.platform-fee.verifications.data') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'student_name', name: 'students.name' },
            { data: 'student_id', name: 'students.student_id' },
            { data: 'session', name: 'sessions.name' },
            { data: 'fee_amount', name: 'platform_fee_payments.fee_amount' },
            { data: 'payment_date', name: 'platform_fee_payments.payment_date' },
            { data: 'status', name: 'platform_fee_payments.status', orderable: false },
            { data: 'created_at', name: 'platform_fee_payments.created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        pageLength: 25
    });

    // Filter change event
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // View payment
    window.viewPayment = function(id) {
        console.log('View payment clicked for ID:', id);
        
        $.ajax({
            url: "{{ url('admin/platform-fee/verifications') }}/" + id,
            type: "GET",
            success: function(payment) {
                console.log('Payment data:', payment);
                
                $('#modal-student-name').text(payment.student_name);
                $('#modal-student-id').text(payment.student_id);
                $('#modal-session').text(payment.session);
                $('#modal-amount').text(payment.fee_amount);
                $('#modal-payment-date').text(payment.payment_date);
                $('#modal-submitted-date').text(payment.created_at);
                
                if(payment.student_note) {
                    $('#modal-student-note').text(payment.student_note);
                    $('#student-note-section').show();
                } else {
                    $('#student-note-section').hide();
                }
                
                // Display receipt
                var receiptExt = payment.receipt_path.split('.').pop().toLowerCase();
                if(['jpg', 'jpeg', 'png', 'gif'].includes(receiptExt)) {
                    $('#receipt-container').html('<img src="' + payment.receipt_url + '" class="receipt-preview" alt="Receipt">');
                } else if(receiptExt === 'pdf') {
                    $('#receipt-container').html('<a href="' + payment.receipt_url + '" target="_blank" class="btn btn-primary"><i class="fas fa-file-pdf"></i> View PDF Receipt</a>');
                }
                
                $('#viewPaymentModal').modal('show');
            },
            error: function(xhr) {
                console.log('Error:', xhr.responseText);
                alert('Failed to load payment details: ' + (xhr.responseJSON?.message || xhr.responseText));
            }
        });
    };

    // Approve payment
    window.approvePayment = function(id) {
        $('#approve-payment-id').val(id);
        $('#approveModal').modal('show');
    };

    $('#approveForm').submit(function(e) {
        e.preventDefault();
        var id = $('#approve-payment-id').val();
        var note = $('#approve-note').val();
        
        $.ajax({
            url: "{{ url('admin/platform-fee/verifications') }}/" + id + "/approve",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                admin_note: note
            },
            success: function(response) {
                $('#approveModal').modal('hide');
                table.ajax.reload();
                alert('Payment approved successfully!');
            },
            error: function(xhr) {
                console.log('Error:', xhr.responseText);
                alert('Failed to approve payment: ' + (xhr.responseJSON?.message || xhr.responseText));
            }
        });
    });

    // Reject payment
    window.rejectPayment = function(id) {
        $('#reject-payment-id').val(id);
        $('#reject-note').val('');
        $('#rejectModal').modal('show');
    };

    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        var id = $('#reject-payment-id').val();
        var note = $('#reject-note').val();
        
        if(!note) {
            alert('Please provide a reason for rejection');
            return;
        }
        
        $.ajax({
            url: "{{ url('admin/platform-fee/verifications') }}/" + id + "/reject",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                admin_note: note
            },
            success: function(response) {
                $('#rejectModal').modal('hide');
                table.ajax.reload();
                alert('Payment rejected successfully!');
            },
            error: function(xhr) {
                console.log('Error:', xhr.responseText);
                alert('Failed to reject payment: ' + (xhr.responseJSON?.message || xhr.responseText));
            }
        });
    });
});
</script>
@endpush
