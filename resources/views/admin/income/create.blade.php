@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- The form is shared with the other side of the sheet, and with the edit form.
     The four files had already drifted — the income edit form offered a
     different list of payment methods than the income create form that wrote
     the values. --}}
@include('admin.account.partials.transaction_form', [
    'mode' => 'create',
    'account_help' => 'payment_account_help_text_income',
])

@endsection
