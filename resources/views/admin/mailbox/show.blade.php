@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('common.mailbox._style')
<div class="main-body mbx">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-block">
                @include('common.mailbox._thread')
            </div>
        </div>
    </div>
</div>
@endsection
