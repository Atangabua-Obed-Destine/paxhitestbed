@extends('student.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-envelope"></i> {{ $title }}</h5>
                <p class="text-muted mb-0">{{ __('Write to your lecturers and the school offices, and read their replies. You also get an email when a new message arrives.') }}</p>
            </div>
            <div class="card-block">
                @include('common.mailbox._nav')
                @include('common.mailbox._list')
            </div>
        </div>
    </div>
</div>
@endsection
