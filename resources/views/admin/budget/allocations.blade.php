@extends('admin.layouts.master')
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
                        <h5>{{ __('text_budget_allocations') }} - {{ $budget->budget_code }}</h5>
                        <div class="card-header-right">
                            <a href="{{ route('admin.budget.show', $budget->id) }}" class="btn btn-secondary btn-sm">
                                <i class="feather icon-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-block">
                        <!-- Budget Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="alert alert-primary">
                                    <strong>{{ __('field_total_amount') }}:</strong><br>
                                    {{ number_format($budget->total_amount, 2) }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-info">
                                    <strong>{{ __('field_allocated_amount') }}:</strong><br>
                                    {{ number_format($budget->allocated_amount, 2) }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-warning">
                                    <strong>{{ __('field_spent_amount') }}:</strong><br>
                                    {{ number_format($budget->spent_amount, 2) }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success">
                                    <strong>{{ __('field_unallocated') }}:</strong><br>
                                    {{ number_format($budget->total_amount - $budget->allocated_amount, 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Add Allocation Form -->
                        @if(!in_array($budget->status, ['active', 'closed', 'cancelled']))
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6>{{ __('text_add_allocation') }}</h6>
                            </div>
                            <div class="card-block">
                                <form method="post" action="{{ route('admin.budget.allocations.store', $budget->id) }}" id="allocationForm">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="expense_category_id">{{ __('field_expense_category') }} <span>*</span></label>
                                                <select class="form-control" name="expense_category_id" id="expense_category_id" required>
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($expenseCategories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="title">{{ __('field_title') }} <span>*</span></label>
                                                <input type="text" class="form-control" name="title" id="title" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="allocated_amount">{{ __('field_amount') }} <span>*</span></label>
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       max="{{ $budget->total_amount - $budget->allocated_amount }}"
                                                       class="form-control" 
                                                       name="allocated_amount" 
                                                       id="allocated_amount" 
                                                       required>
                                                <small class="form-text text-muted">
                                                    Max: {{ number_format($budget->total_amount - $budget->allocated_amount, 2) }}
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="period">{{ __('field_period') }} <span>*</span></label>
                                                <select class="form-control" name="period" id="period" required>
                                                    <option value="yearly">{{ __('text_yearly') }}</option>
                                                    <option value="q1">Q1</option>
                                                    <option value="q2">Q2</option>
                                                    <option value="q3">Q3</option>
                                                    <option value="q4">Q4</option>
                                                    <option value="semester1">Semester 1</option>
                                                    <option value="semester2">Semester 2</option>
                                                </select>
                                            </div>
                                        </div>
                                        @if($budget->type != 'departmental')
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="department_id">{{ __('field_department') }}</label>
                                                <select class="form-control" name="department_id" id="department_id">
                                                    <option value="">{{ __('select') }}</option>
                                                    @foreach($departments as $department)
                                                    <option value="{{ $department->id }}">{{ $department->title }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">Optional: Sub-divide by department</small>
                                            </div>
                                        </div>
                                        @else
                                        <input type="hidden" name="department_id" value="{{ $budget->department_id }}">
                                        @endif
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="description">{{ __('field_description') }}</label>
                                                <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather icon-plus"></i> {{ __('btn_add_allocation') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif

                        <!-- Allocations Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_category') }}</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th>{{ __('field_period') }}</th>
                                        <th>{{ __('field_allocated') }}</th>
                                        <th>{{ __('field_spent') }}</th>
                                        <th>{{ __('field_remaining') }}</th>
                                        <th>{{ __('field_utilization') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($budget->allocations as $allocation)
                                    <tr>
                                        <td>{{ $allocation->expenseCategory->title ?? '-' }}</td>
                                        <td>{{ $allocation->title }}</td>
                                        <td>{{ $allocation->department->title ?? '-' }}</td>
                                        <td>
                                            @if($allocation->period == 'yearly')
                                            <span class="badge badge-secondary">{{ __('text_yearly') }}</span>
                                            @elseif($allocation->period == 'q1')
                                            <span class="badge badge-primary">Q1</span>
                                            @elseif($allocation->period == 'q2')
                                            <span class="badge badge-info">Q2</span>
                                            @elseif($allocation->period == 'q3')
                                            <span class="badge badge-warning">Q3</span>
                                            @elseif($allocation->period == 'q4')
                                            <span class="badge badge-danger">Q4</span>
                                            @elseif($allocation->period == 'semester1')
                                            <span class="badge badge-success">Sem 1</span>
                                            @else
                                            <span class="badge badge-dark">Sem 2</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($allocation->allocated_amount, 2) }}</td>
                                        <td>{{ number_format($allocation->spent_amount, 2) }}</td>
                                        <td>{{ number_format($allocation->remaining_amount, 2) }}</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $allocation->status_color }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $allocation->utilization_percentage }}%;">
                                                    {{ $allocation->utilization_percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if(!in_array($budget->status, ['active', 'closed', 'cancelled']))
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" 
                                                    data-toggle="modal" 
                                                    data-target="#editModal{{ $allocation->id }}"
                                                    title="{{ __('btn_edit') }}">
                                                <i class="feather icon-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" 
                                                    title="{{ __('btn_delete') }}" 
                                                    onclick="if(confirm('{{ __('msg_confirm_delete') }}')) { document.getElementById('delete-form-{{ $allocation->id }}').submit(); }">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                            <form id="delete-form-{{ $allocation->id }}" 
                                                  action="{{ route('admin.budget.allocations.delete', [$budget->id, $allocation->id]) }}" 
                                                  method="POST" 
                                                  style="display: none;">
                                                @csrf
                                            </form>
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal{{ $allocation->id }}" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ __('text_edit_allocation') }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <form method="post" action="{{ route('admin.budget.allocations.update', [$budget->id, $allocation->id]) }}">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_expense_category') }} <span>*</span></label>
                                                                    <select class="form-control" name="expense_category_id" required>
                                                                        @foreach($expenseCategories as $category)
                                                                        <option value="{{ $category->id }}" {{ $allocation->expense_category_id == $category->id ? 'selected' : '' }}>
                                                                            {{ $category->title }}
                                                                        </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_title') }} <span>*</span></label>
                                                                    <input type="text" class="form-control" name="title" value="{{ $allocation->title }}" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_amount') }} <span>*</span></label>
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="allocated_amount" value="{{ $allocation->allocated_amount }}" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_period') }} <span>*</span></label>
                                                                    <select class="form-control" name="period" required>
                                                                        <option value="yearly" {{ $allocation->period == 'yearly' ? 'selected' : '' }}>{{ __('text_yearly') }}</option>
                                                                        <option value="q1" {{ $allocation->period == 'q1' ? 'selected' : '' }}>Q1</option>
                                                                        <option value="q2" {{ $allocation->period == 'q2' ? 'selected' : '' }}>Q2</option>
                                                                        <option value="q3" {{ $allocation->period == 'q3' ? 'selected' : '' }}>Q3</option>
                                                                        <option value="q4" {{ $allocation->period == 'q4' ? 'selected' : '' }}>Q4</option>
                                                                        <option value="semester1" {{ $allocation->period == 'semester1' ? 'selected' : '' }}>Semester 1</option>
                                                                        <option value="semester2" {{ $allocation->period == 'semester2' ? 'selected' : '' }}>Semester 2</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_department') }}</label>
                                                                    <select class="form-control" name="department_id">
                                                                        <option value="">{{ __('select') }}</option>
                                                                        @foreach($departments as $department)
                                                                        <option value="{{ $department->id }}" {{ $allocation->department_id == $department->id ? 'selected' : '' }}>
                                                                            {{ $department->title }}
                                                                        </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label>{{ __('field_description') }}</label>
                                                                    <textarea class="form-control" name="description" rows="2">{{ $allocation->description }}</textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('btn_cancel') }}</button>
                                                        <button type="submit" class="btn btn-primary">{{ __('btn_update') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('msg_no_allocations') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                                @if($budget->allocations->count() > 0)
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="4">{{ __('text_total') }}</th>
                                        <th>{{ number_format($budget->allocated_amount, 2) }}</th>
                                        <th>{{ number_format($budget->spent_amount, 2) }}</th>
                                        <th>{{ number_format($budget->remaining_amount, 2) }}</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const allocatedAmountInput = document.getElementById('allocated_amount');
        const maxAmount = {{ $budget->total_amount - $budget->allocated_amount }};
        
        if (allocatedAmountInput) {
            allocatedAmountInput.addEventListener('input', function() {
                const value = parseFloat(this.value) || 0;
                
                if (value > maxAmount) {
                    this.setCustomValidity('Amount exceeds available budget of ' + maxAmount.toFixed(2));
                    this.style.borderColor = '#dc3545';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '';
                }
            });
        }
    });
</script>
@endsection
